package CriticalErrors;

use strict;
use warnings;

use Data::Dumper;
use StatsDB;
use StatsTime;
use EnmCluster;
use DBI;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}


sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};
    if ( exists $r_incr->{'CriticalErrors'} ) {
        $self->{'r_criticalErrors'} = $r_incr->{'CriticalErrors'}->{'r_criticalErrors'};
        if ( exists $r_incr->{'CriticalErrors'}->{'openfiles'} ) {
            $self->{'openfiles'} = $r_incr->{'CriticalErrors'}->{'openfiles'};
        }
    } else {
        $self->{'r_criticalErrors'} = {};
        $self->{'openfiles'} = {};
    }

    return undef;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $program eq 'JBOSS' || $program eq 'JMS' ) {
        # We're only interested in warnings/errors
        if ( $severity eq 'info' ) {
            return;
        }

        if ( $message =~ /"(jms\.[^"]*)"\s*is\s*full/ ) {
            (my $errorStr = $1) =~ s/\s+$//;
            $self->{'r_criticalErrors'}->{$host}->{"Queue is full: $errorStr"}++;
        } elsif ( $message =~ /IOException: No space left on device/ ) {
            $self->{'r_criticalErrors'}->{$host}->{'IOException: No space left on device'}++;
        } elsif ( $message =~ /^WARNING.*Exception in XA Resource end.*SM_LOCK_DEADLOCK: Deadlock detected on attempt to lock/ ) {
            $self->{'r_criticalErrors'}->{$host}->{'SM_LOCK_DEADLOCK: Deadlock detected on attempt to lock'}++;
        } elsif ( $message =~ /java\.io\.IOException: Too many open files/ ) {
            my $r_entry = $self->{'openfiles'}->{$host}->{$program};
            if ( ! defined $r_entry ) {
                $r_entry = {
                    'starttime' => $timestamp,
                    'count' => 0
                };
                $self->{'openfiles'}->{$host}->{$program} = $r_entry;
            }
            $r_entry->{'count'}++;
        } elsif ( $program eq 'JMS' ) {
            if ( $message =~ /maximum delivery attempts/ && $message =~ /Dead Letter Address/ ) {
                my $eventName = 'NA';
                if ( $message =~ /__sdk_eb_event_name__\s*=\s*([^,\]]*)/i ) {
                     $eventName = $1;
                }
                my $address = 'NA';
                if ( $message =~ /address\s*=\s*([^,]*)/i ) {
                     $address = $1;
                }
                my $errorStr = "Maximum delivery attempts exceeded: $eventName, $address";
                $errorStr =~ s/\s+$//;
                $self->{'r_criticalErrors'}->{$host}->{$errorStr}++;
            } elsif ( $message =~ /There are possibly consumers/ ) {
                my $queue = 'NA';
                $queue = $1    if ( $message =~ /\bQueue\s+(.*?)\s+was/i );
                my $errorStr = "There are possibly consumers hanging on a network operation with queue: $queue";
                $errorStr =~ s/\s+$//;
                $self->{'r_criticalErrors'}->{$host}->{$errorStr}++;
            }
        }
    } elsif ( $program eq 'kernel' ) {
        if ( $message =~ /Out of memory: Kill process.*score.*or sacrifice child/ ) {
            $self->{'r_criticalErrors'}->{$host}->{'Out of memory: Kill process'}++;
        } elsif ( $message =~ /\S+: Multicast hash table maximum of \d+ reached/ ||
                  $message =~ /Multicast hash table chain limit/ ) {
            if ( $::DEBUG > 4 ) { print "CriticalErrors::handle multicast error: \"$message\"\n"; }
            # Stop storing the timestamp in the message
            # [86574.128276] br0: Multicast hash table chain limit reached: eth0
            $message =~ s/.* br\d: //;
            $self->{'r_criticalErrors'}->{$host}->{$message}++;
        } elsif ( $message =~ /page allocation failure: order:(\d+)/ ) {
            $self->{'r_criticalErrors'}->{$host}->{'page allocation failure: order:' . $1}++;
        }
    }

}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    my $servername2IdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});

    # Get 'MESSAGE' -> 'ID' mapping for all the critical errors
    my @errorMessages = ();
    foreach my $error (keys %{$self->{'r_criticalErrors'}}) {
        my @messages = keys %{$self->{'r_criticalErrors'}->{$error}};
        push (@errorMessages, @messages);
    }
    my $errorMsg2IdMap = getIdMap($dbh, "enm_vm_critical_error_messages", "id", "message", \@errorMessages);

    # Get the 'enm_vm_critical_errors.bcp' file ready to store the parsed critical error data
    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    my $bcpFile = "$tmpDir/enm_vm_critical_errors.bcp";
    open (BCP, "> $bcpFile");

    while ( my ($serverName,$r_serverErrors) = each %{$self->{'r_criticalErrors'}} ) {
        my $serverId = $servername2IdMap->{$serverName};
        if ( defined $serverId ) {
            while ( my ($errorMsg,$errorCount) = each %{$r_serverErrors} ) {
                printf BCP "%s\t%d\t%d\t%d\t%d\n", $self->{'date'}, $self->{'siteId'}, $errorMsg2IdMap->{$errorMsg}, $errorCount, $serverId;
            }
        }
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_vm_critical_errors WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_vm_critical_errors";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_vm_critical_errors" )
          or die "Failed to load new data from '$bcpFile' file to 'enm_vm_critical_errors' table";

    my @values = ();
    while ( my ($host,$r_programEntries) = each %{$self->{'openfiles'}} ) {
        my $serverId = $servername2IdMap->{$host};
        if ( defined $serverId ) {
            while ( my ($program,$r_entry) = each %{$r_programEntries} ) {
                my $time = parseTime($r_entry->{'starttime'}, $StatsTime::TIME_ELASTICSEARCH_MSEC);
                push @values, sprintf("(%d,'%s',%d,'%s',%d)",
                                      $self->{'siteId'},
                                      formatTime($time,$StatsTime::TIME_SQL),
                                      $serverId,
                                      $program,
                                      $r_entry->{'count'});
            }
        }
    }
    if ( $#values > -1 ) {
        dbDo( $dbh, sprintf("DELETE FROM enm_file_descriptors WHERE siteid = %d AND date BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                            $self->{'siteId'}, $self->{'date'}, $self->{'date'})
          ) or die "Failed to delete from enm_file_descriptors".$dbh->errstr."\n";
        dbDo( $dbh, "INSERT INTO enm_file_descriptors (siteid,date,serverId,program,warningCount) VALUES " .
                  join(",",@values)
              ) or die "Failed to load new data to 'enm_file_descriptors' table".$dbh->errstr."\n";
    }


    $r_incr->{'CriticalErrors'} = {
        'r_criticalErrors' => $self->{'r_criticalErrors'},
        'openfiles' => $self->{'openfiles'}
    };
}

1;
