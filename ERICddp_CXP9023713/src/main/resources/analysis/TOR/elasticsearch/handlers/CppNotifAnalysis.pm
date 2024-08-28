package CppNotifAnalysis;

use strict;
use warnings;

use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DBI;


sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self, $r_cliArgs, $r_incr, $dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    if ( exists $r_incr->{'CppNotifAnalysis'} ) {
        $self->{'r_notifStats'} = $r_incr->{'CppNotifAnalysis'}->{'r_notifStats'};
    }
    else {
        $self->{'r_notifStats'} = {};
    }

    my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, 'mscm');
    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS' } );
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "CppNotifAnalysis::handle got message from $host $program : \"$message\"\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\]\s+\([^\)]+\)\s+\[[^,]+, CPP_NODE_NOTIFICATIONS.NOTIFICATION_RECEIVER_HANDLER, (.*)/ ) {
        my ($remainder) = $1;
        if ( $::DEBUG > 6 ) { print "CppNotifAnalysis::handle remainder:\"$remainder\"\n"; }

        my ($startTime,$endTime,$data) = $remainder =~
            /\{NOTIFICATION_STATS (\d{4}-\d{2}-\d{2}.\d{2}:\d{2}:\d{2})\s*-\s*(\d{4}-\d{2}-\d{2}.\d{2}:\d{2}:\d{2})\}\s+(.*)/;
        if ( defined $data ) {
            # Return here itself, if it's not one of the following log lines
            if ( $data !~ /TOTAL_NOTIFICATIONS|BUFFERED_NODE_NOTIFICATIONS|DISCARDED_NODE_NOTIFICATIONS|EVICTED_NODE_NOTIFICATIONS|LARGE_NOTIFICATION_CACHE_SIZE|NOTIFICATION_CACHE_SIZE|NOTIFICATION_LEAD_TIME|VALIDATION_HANDLER_TIME|WRITE_HANDLER_TIME/ ) {
                return;
            }

            if ( $::DEBUG > 5 ) { print "CppNotifAnalysis::handle data=\"$data\"\n"; }

            my $notifStatKey = $startTime . '@@' . $endTime . '@@' . $host;
            my $r_notifStatsSample = $self->{'r_notifStats'}->{$notifStatKey};
            if ( ! defined $r_notifStatsSample ) {
                my $esLogTime = $endTime;
                if ( $timestamp =~ /^\s*(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})/ ) {
                    $esLogTime = $1 . ' ' . $2;
                }
                $r_notifStatsSample = {
                    'time'                        => $esLogTime,
                    'totalnotificationsreceived'  => '',
                    'totalnotificationsprocessed' => '',
                    'totalnotificationsdiscarded' => '',
                    'evictions'                   => '',
                    'largeNodeCacheMax'           => '',
                    'cachesizemax'                => '',
                    'cachesizeavg'                => '',
                    'leadtimemax'                 => '',
                    'leadtimeavg'                 => '',
                    'validationhandlertimemax'    => '',
                    'validationhandlertimeavg'    => '',
                    'writehandlertimemax'         => '',
                    'writehandlertimeavg'         => '',
                    'bufferednodenotifications'   => ''
                };
                $self->{'r_notifStats'}->{$notifStatKey} = $r_notifStatsSample;
            }

            if( $data =~ /TOTAL_NOTIFICATIONS\s*=\s*\[\s*Received\s*:\s*(\d+)\s*,\s*Processed\s*:\s*(\d+).*/ ) {
                $r_notifStatsSample->{'totalnotificationsreceived'} = $1;
                $r_notifStatsSample->{'totalnotificationsprocessed'} = $2;
            }
            elsif ( $data =~ /DISCARDED_NODE_NOTIFICATIONS.*Total_\d+\s*:\s*(\d+)\s*\]/ ) {
                $r_notifStatsSample->{'totalnotificationsdiscarded'} = $1;
            }
            elsif ( $data =~ /EVICTED_NODE_NOTIFICATIONS.*Total_\d+\s*:\s*(\d+)/ ) {
                $r_notifStatsSample->{'evictions'} = $1;
            }
            elsif ( $data =~ /LARGE_NOTIFICATION_CACHE_SIZE\s*=\s*\[.*Max\s*:\s*(\d+)/ ) {
                $r_notifStatsSample->{'largeNodeCacheMax'} = $1;
            }
            elsif ( $data =~ /NOTIFICATION_CACHE_SIZE\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $r_notifStatsSample->{'cachesizemax'} = $1;
                $r_notifStatsSample->{'cachesizeavg'} = $2;
            }
            elsif ( $data =~ /NOTIFICATION_LEAD_TIME\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $r_notifStatsSample->{'leadtimemax'} = $1;
                $r_notifStatsSample->{'leadtimeavg'} = $2;
            }
            elsif ( $data =~ /VALIDATION_HANDLER_TIME\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $r_notifStatsSample->{'validationhandlertimemax'} = $1;
                $r_notifStatsSample->{'validationhandlertimeavg'} = $2;
            }
            elsif ( $data =~ /WRITE_HANDLER_TIME\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $r_notifStatsSample->{'writehandlertimemax'} = $1;
                $r_notifStatsSample->{'writehandlertimeavg'} = $2;
            }
            elsif ( $data =~ /BUFFERED_NODE_NOTIFICATIONS.*Total_\d+\s*:\s*(\d+)\s*\]/ ) {
                $r_notifStatsSample->{'bufferednodenotifications'} = $1;
            }

            if ( $::DEBUG > 5 ) { print Dumper("CppNotifAnalysis::handle r_notifStatsSample", $r_notifStatsSample); }
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("CppNotifAnalysis::done r_notifStats", $self->{'r_notifStats'}); }

    # If $self->{'r_notifStats'} is empty, then nothing to do
    if ( ! %{$self->{'r_notifStats'}} ) {
        if ( $::DEBUG ) { print "CppNotifAnalysis::done no data found\n"; }
        return;
    }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    # Get server ID map
    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});
    if ( $::DEBUG > 9 ) { print Dumper("CppNotifAnalysis::done serverIdMap ", $serverIdMap); }

    # Store 'Notification Analysis Details'
    my $bcpFileNotifStats = "$tmpDir/enm_mscmnotification_logs.bcp";
    open (BCP, "> $bcpFileNotifStats");

    foreach my $notifStatKey (sort keys %{$self->{'r_notifStats'}}) {
        # Get 'Start Time', 'End Time' and 'Hostname' by splitting the $notifStatKey
        my @notifStatKey = split(/@@/, $notifStatKey);
        print BCP $self->{'siteId'} . "\t" . $self->{'r_notifStats'}->{$notifStatKey}->{'time'} . "\t" .
                  $serverIdMap->{$notifStatKey[2]} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsreceived'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsprocessed'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsdiscarded'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'evictions'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'largeNodeCacheMax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'cachesizemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'cachesizeavg'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'leadtimemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'leadtimeavg'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'validationhandlertimemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'validationhandlertimeavg'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'writehandlertimemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'writehandlertimeavg'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'bufferednodenotifications'} . "\n";
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_mscmnotification_logs WHERE siteid = %d AND time BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $self->{'siteId'}, $self->{'date'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_mscmnotification_logs";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotifStats' INTO TABLE enm_mscmnotification_logs" )
        or die "Failed to load new data from '$bcpFileNotifStats' file to 'enm_mscmnotification_logs' table";

    unlink($bcpFileNotifStats);

    $r_incr->{'CppNotifAnalysis'} = {
                                     'r_notifStats' => $self->{'r_notifStats'}
                                     };
}

1;
