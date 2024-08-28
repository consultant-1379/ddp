package ComEcimNotif;

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
    $self->{'hostToSg'} = {};
    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    my @subscriptions = ();
    $self->{'srvIdMap'} = {};
    foreach my $service( "comecimmscm", "mscmapg" ) {
        if ( exists $r_incr->{'ComEcimNotif'}->{$service} ) {
           $self->{$service} = $r_incr->{'ComEcimNotif'}->{$service};
        } elsif ( $service eq 'comecimmscm' && exists $r_incr->{'ComEcimNotif'} ) {
            $self->{$service}->{'r_countsByType'} = $r_incr->{'ComEcimNotif'}->{'r_countsByType'};
            $self->{$service}->{'r_countsByNode'} = $r_incr->{'ComEcimNotif'}->{'r_countsByNode'};
        } else {
        $self->{$service}->{'r_countsByType'} = {};
        $self->{$service}->{'r_countsByNode'} = {};
        }
        my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'},$service);
        while ( my ($server,$serverId) = each %{$r_serverMap} ) {
            $self->{'hostToSg'}->{$server} = $service;
            push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS'} );
            $self->{'srvIdMap'}->{$server} = $serverId;
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    
    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 3 ) { print "ComEcimNotif::handle got message from $host $program : $message\n"; }
    my $service = $self->{'hostToSg'}->{$host};
    if ( $message =~ /INFO\s+\[\S+\.EVENT_LOGGER\]\s+\([^\)]+\)\s+\[(.*)\]/ ) {
        my $recording = $1;
        if ( $::DEBUG > 7 ) { print "ComEcimNotif::handle recording=$recording\n"; }

        #TORF-584567 Changed {NOTIFICATION_STATS to {_NOTIFICATION_STATS to disable parsing till TORF-580413 is resolved
        if ( $recording =~ /\{_NOTIFICATION_STATS\s+(\S+\s+\S+)\s+-\s+(\S+\s+\S+)\}\s+(.*)/ ) {
            my ($from, $to, $data) = ($1, $2, $3);
            if ( $::DEBUG > 6 ) { print "ComEcimNotif::handle timestamp=$timestamp from=$from $to=$to data=$data\n"; }

            if ( $data =~ /NODE_NOTIFICATIONS\(Top\s+\d+\/(\d+)\)\s*=\s*\[([^\]]*)\]/ ) {
                my ($totalNodes, $nodesStr) = ($1, $2);
                my @nodes = ();
                foreach my $nodeStr ( split(/,/, $nodesStr) ) {
                    my ($node, $count) = $nodeStr =~ /(.*):(\d+)/;
                    $self->{$service}->{'r_countsByNode'}->{$node} += $count;
                }
            }
            elsif ( $data =~/NOTIFICATION_TYPES\(Top\s+\d+\/(\d+)\)\s*=\s*\[([^\]]*)\]/ ) {
                my ($total, $typesStr) = ($1, $2);
                foreach my $typeStr ( split(/,/, $typesStr) ) {
                    my ($type, $count) = $typeStr =~ /(.*):(\d+)/;
                    my @typeFields = split(/_/, $type);
                    if ( $::DEBUG > 6 ) { print Dumper("ComEcimNotif::handle typeFields", \@typeFields); }
                    if ( $typeFields[0] eq 'UPDATE' ) {
                        $self->{$service}->{'r_countsByType'}->{'AVC'}->{$typeFields[1]}->{$typeFields[2]} += $count;
                    } elsif ( $typeFields[0] eq 'SEQUENCE' ) {
                        $self->{$service}->{'r_countsByType'}->{'SDN'}->{$typeFields[2]}->{$typeFields[3]} += $count;
                    } elsif ( $typeFields[0] eq 'CREATE' ) {
                        $self->{$service}->{'r_countsByType'}->{'CREATE'}->{$typeFields[1]} += $count;
                    } elsif ( $typeFields[0] eq 'DELETE' ) {
                        $self->{$service}->{'r_countsByType'}->{'DELETE'}->{$typeFields[1]} += $count;
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
    my @serviceGroups = ("comecimmscm", "mscmapg");

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    my $bcpFileNotifrec = "$tmpDir/enm_mscmce_notifrec.bcp";
    open (BCP, "> $bcpFileNotifrec");
    my $bcpFileNotiftop = "$tmpDir/enm_mscmce_notiftop.bcp";
    open (BCPTOP, "> $bcpFileNotiftop");

    my %allMo = ();
    my %allAttrib = ();
    foreach my $service(@serviceGroups) {
        if ( keys %{$self->{$service}->{'r_countsByType'}}) {
           foreach my $eventType ( keys %{$self->{$service}->{'r_countsByType'}} ) {
                foreach my $moType ( keys %{$self->{$service}->{'r_countsByType'}->{$eventType}} ) {
                    $allMo{$moType}++;
                    if ( $eventType eq 'AVC' || $eventType eq 'SDN' ) {
                        foreach my $attrib ( keys %{$self->{$service}->{'r_countsByType'}->{$eventType}->{$moType}} ) {
                            $allAttrib{$attrib}++;
                        }
                    }
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
    my $r_neIdMap = getIdMap($dbh, "enm_ne", "id", "name", [], $self->{'siteId'}, "siteid");
    foreach my $service(@serviceGroups) {
        foreach my $eventType ( keys %{$self->{$service}->{'r_countsByType'}} ) {
            foreach my $moType ( keys %{$self->{$service}->{'r_countsByType'}->{$eventType}} ) {
                my $moId = $r_MoIdMap->{$moType};
                if ( $eventType eq 'AVC' || $eventType eq 'SDN' ) {
                    foreach my $attrib ( keys %{$self->{$service}->{'r_countsByType'}->{$eventType}->{$moType}} ) {
                        print BCP $self->{'date'} . "\t" . $self->{'siteId'} . "\t" . $eventType . "\t" .
                          $moId . "\t" . $r_AttribIdMap->{$attrib} . "\t" .
                          $self->{$service}->{'r_countsByType'}->{$eventType}->{$moType}->{$attrib} . "\t" .
                          $service . "\n";
                    }
                }
                else {
                    print BCP $self->{'date'} . "\t" . $self->{'siteId'} . "\t" . $eventType . "\t" .
                      $moId . "\t" . $r_AttribIdMap->{'NA'} . "\t" .
                      $self->{$service}->{'r_countsByType'}->{$eventType}->{$moType} . "\t" .
                      $service . "\n";
                }
            }
        }
        while ( my ($neName, $count) = each %{$self->{$service}->{'r_countsByNode'}} ) {
            if ( exists $r_neIdMap->{$neName} ) {
                print BCPTOP $self->{'date'} . "\t" . $self->{'siteId'} . "\t" .
                $r_neIdMap->{$neName} . "\t" . $count . "\t" .
                $service . "\n";
            }
            else {
                print "WARN: Could not get id for $neName\n;"
            }
        }
        $r_incr->{'ComEcimNotif'}->{$service} = $self->{$service};
    }
    close BCPTOP;
    close BCP;

    if ( -s $bcpFileNotifrec) {
        dbDo( $dbh, sprintf("DELETE FROM enm_mscmce_notifrec WHERE siteid = %d  AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_mscmce_notifrec";

       dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotifrec' INTO TABLE enm_mscmce_notifrec" )
        or die "Failed to load new data from '$bcpFileNotifrec' file to 'enm_mscmce_notifrec' table";
    }
    # Store 'Top Notification Nodes'
    if ( -s $bcpFileNotiftop) {
        dbDo( $dbh, sprintf("DELETE FROM enm_mscmce_notiftop WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_mscmce_notiftop";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotiftop' INTO TABLE enm_mscmce_notiftop" )
        or die "Failed to load new data from '$bcpFileNotiftop' file to 'enm_mscmce_notiftop' table";
    }
}

1;
