package CppNotif;

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

    if ( exists $r_incr->{'CppNotif'} ) {
        $self->{'r_countsByType'} = $r_incr->{'CppNotif'}->{'r_countsByType'};
        $self->{'r_countsByNode'} = $r_incr->{'CppNotif'}->{'r_countsByNode'};
    }
    else {
        $self->{'r_countsByType'} = {};
        $self->{'r_countsByNode'} = {};
    }

    my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, 'mscm');

    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS'} );
    }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "CppNotif::handle got message from $host $program : \"$message\"\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\]\s+\([^\)]+\)\s+\[\S+, CPP_NODE_NOTIFICATIONS.NOTIFICATION_RECEIVER_HANDLER, (.*)/ ) {
        my $remainder = $1;
        if ( $::DEBUG > 6 ) { print "CppNotif::handle remainder:\"$remainder\"\n"; }
        my ($from,$to,$data) = $remainder =~
            /\{NOTIFICATION_STATS (\d{4}-\d{2}-\d{2}.\d{2}:\d{2}:\d{2})\s*-\s*(\d{4}-\d{2}-\d{2}.\d{2}:\d{2}:\d{2})\}\s+(.*)/;
        if ( defined $data ) {
            if ( $::DEBUG > 6 ) { print "CppNotif::handle timestamp=$timestamp from=$from $to=$to data=$data\n"; }

            if ( $data =~ /^NODE_NOTIFICATIONS\(Top\s+\d+\/(\d+)\)\s*=\s*\[([^\]]*)\]/ ) {
                my ($totalNodes, $nodesStr) = ($1, $2);
                if ( $::DEBUG > 5 ) { print "CppNotif::handle nodesStr=\"$nodesStr\"\n"; }
                my @nodes = ();
                foreach my $nodeStr ( split(/,/, $nodesStr) ) {
                    my ($node, $count) = $nodeStr =~ /(.*):(\d+)/;
                    $self->{'r_countsByNode'}->{$node} += $count;
                }
            } elsif ( $data =~/^NOTIFICATION_TYPES\(Top\s+\d+\/(\d+)\)\s*=\s*\[([^\]]*)\]/ ) {
                my ($total, $typesStr) = ($1, $2);
                foreach my $typeStr ( split(/,/, $typesStr) ) {
                    my ($type, $count) = $typeStr =~ /(.*):(\d+)/;
                    my @typeFields = split(/_/, $type);
                    if ( $::DEBUG > 6 ) { print Dumper("CppNotif::handle typeFields", \@typeFields); }
                    if ( $typeFields[0] eq 'UPDATE' ) {
                        $self->{'r_countsByType'}->{'AVC'}->{$typeFields[1]}->{$typeFields[2]} += $count;
                    } elsif ( $typeFields[0] eq 'SEQUENCE' ) {
                        $self->{'r_countsByType'}->{'SDN'}->{$typeFields[2]}->{$typeFields[3]} += $count;
                    } elsif ( $typeFields[0] eq 'CREATE' ) {
                        $self->{'r_countsByType'}->{'CREATE'}->{$typeFields[1]} += $count;
                    } elsif ( $typeFields[0] eq 'DELETE' ) {
                        $self->{'r_countsByType'}->{'DELETE'}->{$typeFields[1]} += $count;
                    }
                }
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 3 ) {
        print Dumper("CppNotif::done r_countsByType", $self->{'r_countsByType'});
        print Dumper("CppNotif::done r_countsByNode", $self->{'r_countsByNode'});
    }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    my %allMo = ();
    my %allAttrib = ();
    foreach my $eventType ( keys %{$self->{'r_countsByType'}} ) {
        foreach my $moType ( keys %{$self->{'r_countsByType'}->{$eventType}} ) {
            $allMo{$moType}++;

            if ( $eventType eq 'AVC' || $eventType eq 'SDN' ) {
                foreach my $attrib ( keys %{$self->{'r_countsByType'}->{$eventType}->{$moType}} ) {
                    $allAttrib{$attrib}++;
                }
            }
        }
    }

    my @allMoList = keys %allMo;
    my $r_MoIdMap = getIdMap($dbh, "mo_names", "id", "name", \@allMoList );
    my @allAttribList = keys %allAttrib;
    push @allAttribList, 'NA';
    my $r_AttribIdMap = getIdMap($dbh, "enm_mscm_attrib_names", "id", "name", \@allAttribList );

    # Store 'Notification Received Details'
    my $bcpFileNotifrec = "$tmpDir/enm_mscm_notifrec.bcp";
    open (BCP, "> $bcpFileNotifrec");

    foreach my $eventType ( keys %{$self->{'r_countsByType'}} ) {
        foreach my $moType ( keys %{$self->{'r_countsByType'}->{$eventType}} ) {
            my $moId = $r_MoIdMap->{$moType};

            if ( $eventType eq 'AVC' || $eventType eq 'SDN' ) {
                foreach my $attrib ( keys %{$self->{'r_countsByType'}->{$eventType}->{$moType}} ) {
                    print BCP $self->{'date'} . "\t" . $self->{'siteId'} . "\t" . $eventType . "\t" .
                              $moId . "\t" . $r_AttribIdMap->{$attrib} . "\t" .
                              $self->{'r_countsByType'}->{$eventType}->{$moType}->{$attrib} . "\n";
                }
            }
            else {
                print BCP $self->{'date'} . "\t" . $self->{'siteId'} . "\t" . $eventType . "\t" .
                          $moId . "\t" . $r_AttribIdMap->{'NA'} . "\t" .
                          $self->{'r_countsByType'}->{$eventType}->{$moType} . "\n";
            }
        }
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_mscm_notifrec WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_mscm_notifrec";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotifrec' INTO TABLE enm_mscm_notifrec" )
        or die "Failed to load new data from '$bcpFileNotifrec' file to 'enm_mscm_notifrec' table";

    # Store 'Top Notification Nodes'
    my $bcpFileNotiftop = "$tmpDir/enm_mscm_notiftop.bcp";
    open (BCP, "> $bcpFileNotiftop");

    my $r_neIdMap = getIdMap($dbh, "enm_ne", "id", "name", [], $self->{'siteId'}, "siteid");
    while ( my ($neName, $count) = each %{$self->{'r_countsByNode'}} ) {
        if ( exists $r_neIdMap->{$neName} ) {
            print BCP $self->{'date'} . "\t" . $self->{'siteId'} . "\t" .
                      $r_neIdMap->{$neName} . "\t" . $count . "\n";
        }
        else {
            print "WARN: Could not get id for $neName\n;"
        }
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_mscm_notiftop WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_mscm_notiftop";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotiftop' INTO TABLE enm_mscm_notiftop" )
        or die "Failed to load new data from '$bcpFileNotiftop' file to 'enm_mscm_notiftop' table";

    unlink($bcpFileNotifrec);
    unlink($bcpFileNotiftop);

    $r_incr->{'CppNotif'} = {
                                 'r_countsByType' => $self->{'r_countsByType'},
                                 'r_countsByNode' => $self->{'r_countsByNode'}
                                 };
}

1;
