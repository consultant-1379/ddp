package CmNodeEvictions;

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

    if ( exists $r_incr->{'CmNodeEvictions'} ) {
        $self->{'r_instrDataEvent'} = $r_incr->{'CmNodeEvictions'}->{'r_instrDataEvent'};
    }
    else {
        $self->{'r_instrDataEvent'} = {};
    }

    $self->{'r_serverMap'} = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, 'mscm');
    my @programsToFilter = ('JBOSS', 'UNKNOWN');

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'r_serverMap'}}  ) {
        foreach my $program ( @programsToFilter ) {
            push ( @subscriptions, {'server' => $server, 'prog' => $program} );
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    if ( $::DEBUG > 9 ) { print "CmNodeEvictions::handle got message from $host $program : $message\n"; }

    if ( $severity ne 'warning' ) {
        return;
    }

    my ($date, $time) = $timestamp =~ m/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2}).*/g;

    if ( $::DEBUG > 7 ) { print "CmNodeEvictions::handle got message from $host $program : $message\n"; }

    my $serverid=$self->{'r_serverMap'}->{$host};

    if( $message !~ /com.ericsson.oss.mediation.cpp.notificationhandling.handlers.buffer.*Evicted (\d+) notifications.*MeContext=([^,]+)/ ) {
        return;
    }
       
    my $nodeEvictionKey = $date . '@@' . $time . '@@' . $host . '@@' .  $2;
    my $evictedNotifications = $1;
    my $networkelementname = $2;

    $self->{'r_instrDataEvent'}->{$nodeEvictionKey} = {
                'serverid' => $serverid,
                'time' => $date. " " .$time,
                'networkelement' => $networkelementname,
                'evictednotificationcount' => $evictedNotifications
            }; 
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("CmNodeEvictions::done r_instrDataEvent", $self->{'r_instrDataEvent'}); }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    # Get server ID map
    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});

    if ( $::DEBUG > 9 ) { print Dumper("CmNodeEvictions::done serverIdMap ", $serverIdMap); }

    my $nodeEvictionStats = "$tmpDir/enm_cm_nodeevictions.bcp";
    open (BCP, ">$nodeEvictionStats");

    foreach my $nodeEvictionKey (sort keys %{$self->{'r_instrDataEvent'}}) {
        print BCP $self->{'siteId'} . "\t" .
            $self->{'r_instrDataEvent'}->{$nodeEvictionKey}->{'serverid'} . "\t" .
            $self->{'r_instrDataEvent'}->{$nodeEvictionKey}->{'time'} . "\t" .
            $self->{'r_instrDataEvent'}->{$nodeEvictionKey}->{'networkelement'} . "\t" .
            $self->{'r_instrDataEvent'}->{$nodeEvictionKey}->{'evictednotificationcount'} . "\n" ;
    }
    close BCP;

    # Store 'CM Node Eviction Details'
    dbDo( $dbh, sprintf("DELETE FROM enm_cm_nodeevictions WHERE siteid = %d AND time BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $self->{'siteId'}, $self->{'date'}, $self->{'date'}) )
       or die "Failed to delete old data from enm_cm_nodeevictions";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$nodeEvictionStats' INTO TABLE enm_cm_nodeevictions" )
        or die "Failed to load new data from '$nodeEvictionStats' file to 'enm_cm_nodeevictions' table";

    unlink($nodeEvictionStats);

    $r_incr->{'CmNodeEvictions'} = {
                                'r_instrDataEvent' => $self->{'r_instrDataEvent'}
                            };
}

1;

