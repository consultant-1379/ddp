package IpTrnsprtNotif;

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

    if ( exists $r_incr->{'IpTrnsprtNotif'} ) {
        $self->{'r_countsByType'} = $r_incr->{'IpTrnsprtNotif'}->{'r_countsByType'};
        $self->{'r_countsByNode'} = $r_incr->{'IpTrnsprtNotif'}->{'r_countsByNode'};
    }
    else {
        $self->{'r_countsByType'} = {};
        $self->{'r_countsByNode'} = {};
    }

   my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, 'mscmip');

my @programsToFilter = ('JBOSS');

    my @subscriptions = ();
	
	foreach my $server ( keys %{$r_serverMap} ) {

        foreach my $program ( @programsToFilter ) {

            push ( @subscriptions, {'server' => $server, 'prog' => $program} );

        }
    }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
  if ( $::DEBUG > 3 ) { print "IpTrnsprtNotif::::handle got message from $host $program : $message\n"; }
    if ( $program eq 'JBOSS' ) {
        if ( $message =~ /INFO\s+\[\S+\.EVENT_LOGGER\]\s+\([^\)]+\)\s+\[(.*)\s+(.*)\s(.*)\s+(.*)\s+(.*)\s(.*)\s(.*)\]/ ) {
            if ( $message =~ /\{NOTIFICATION_STATS\s+(\S+\s+\S+)\s+-\s+(\S+\s+\S+)\}\s+(.*)/ ) {
                my ($from, $to, $data) = ($1, $2, $3);

      if ( $::DEBUG > 6 ) { print "IpTrnsprtNotif::::handle timestamp=$timestamp from=$from to=$to data=$data\n"; }

                if ( $data =~ /NODE_NOTIFICATIONS\(Top\s+\d+\/(\d+)\)\s*=\s*\[([^\]]*)\]/ ) {
                    my ($totalNodes, $nodesStr) = ($1, $2);
                    my @nodes = ();
                    foreach my $nodeStr ( split(/,/, $nodesStr) ) {
                        my ($node, $count) = $nodeStr =~ /(.*):(\d+)/;
                        $self->{'r_countsByNode'}->{$node} += $count;
                    }
                }
                elsif ( $data =~/NOTIFICATION_TYPES\(Top\s+\d+\/(\d+)\)\s*=\s*\[([^\]]*)\]/ ) {
                    my ($total, $typesStr) = ($1, $2);
                    foreach my $typeStr ( split(/,/, $typesStr) ) {
                        my ($type, $count) = $typeStr =~ /(.*):(\d+)/;
                       my @typeFields = split(/_/, $type);
                        if ( $::DEBUG > 6 ) { print Dumper("IpTrnsprtNotif::::handle typeFields", \@typeFields); }
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
}
sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;
    if ( $::DEBUG > 3 ) {
        print Dumper("IpTrnsprtNotif::done r_countsByType", $self->{'r_countsByType'});
        print Dumper("IpTrnsprtNotif::done r_countsByNode", $self->{'r_countsByNode'});
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
    print Dumper("allAttribList :: ", \@allAttribList);
    my $r_AttribIdMap = getIdMap($dbh, "enm_iptrnsprt_attrib_names", "id", "name", \@allAttribList );
    my $bcpFileNotifrec = "$tmpDir/enm_iptrnsprt_notifrec.bcp";
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

    dbDo( $dbh, sprintf("DELETE FROM enm_iptrnsprt_notifrec WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_iptrnsprt_notifrec";
    
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotifrec' INTO TABLE enm_iptrnsprt_notifrec" )
        or die "Failed to load new data from '$bcpFileNotifrec' file to 'enm_iptrnsprt_notifrec' table";

    my $bcpFileNotiftop = "$tmpDir/enm_iptrnsprt_notiftop.bcp";
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

    dbDo( $dbh, sprintf("DELETE FROM enm_iptrnsprt_notiftop WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_iptrnsprt_notiftop";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotiftop' INTO TABLE enm_iptrnsprt_notiftop" )
        or die "Failed to load new data from '$bcpFileNotiftop' file to 'enm_mscm_notiftop' table";

    unlink($bcpFileNotifrec);
    unlink($bcpFileNotiftop);

    $r_incr->{'IpTrnsprtNotif'} = {
                                 'r_countsByType' => $self->{'r_countsByType'},
                                 'r_countsByNode' => $self->{'r_countsByNode'}
                                 };
}

1;

