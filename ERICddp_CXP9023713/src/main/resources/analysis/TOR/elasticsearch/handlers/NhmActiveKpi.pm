package NhmActiveKpi;

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

    if ( exists $r_incr->{'NhmActiveKpi'} ) {
        $self->{'r_instrDataEvent'} = $r_incr->{'NhmActiveKpi'}->{'r_instrDataEvent'};
    }
    else {
        $self->{'r_instrDataEvent'} = {};
    }

    $self->{'r_serverMap'} = {};
    foreach my $sg ( 'kpiservice', 'conskpiserv' ) {
        my $r_srvMap = enmGetServiceGroupInstances($self->{'site'},$self->{'date'}, $sg);
        while ( my ($srv,$srvId) = each %{$r_srvMap} ) {
            $self->{'r_serverMap'}->{$srv} = $srvId;
        }
    }

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
    if ( $::DEBUG > 9 ) { print "NhmActiveKpi::handle got message from $host $program : $message\n"; }

    if ( $severity ne 'info' ) {
        return;
    }

    my %ebsmEpsId = ();
    my ($date, $time) = $timestamp =~ m/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2}).*/g;
    my ($timeStr)=$timestamp=~/(\d{2}:\d{2}:\d{2}).*/;

    if ( $::DEBUG > 7 ) { print "NhmActiveKpi::handle got message from $host $program : $message\n"; }

    my $serverid=$self->{'r_serverMap'}->{$host};

    if( $message !~ /KpiLogsCREATE_KPI.*CREATE_KPI.*Kpi\{name='([^']*)'.*reportingObjectType='([^,]+)'/ ) {
        return;
    }
       
    my $kpiActivationKey = $date . '@@' . $time . '@@' . $host . '@@' .  $1 . '@@' . $2;

    $self->{'r_instrDataEvent'}->{$kpiActivationKey} = {
                'serverid' => $serverid,
                'time' => $date. " " .$time,
                'name' => '\N',
                'reportingobjecttype' => '\N',
                'node_count' => '\N'
            };

    if ( $message =~ /Kpi\{name\s*=\s*'([^']*)'\s*/ ) {
        $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'name'} = $1;
    }
 
    if ( $message =~ /reportingObjectType\s*=\s*'([^,]+)'\s*/ ) {
        $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'reportingobjecttype'} = $1;
        $self->{'ronames'}->{$1} = $1;
    }

    if ( $message =~ /nodeCount\s*=\s*(\d+)\s*/ ) {
        $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'node_count'} = $1;
    }
 
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("NhmActiveKpi::done r_instrDataEvent", $self->{'r_instrDataEvent'}); }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    my @roNames = keys %{$self->{'ronames'}};

    # Get server ID map
    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});

    my $roNamesIdMap = getIdMap($dbh, "enm_nhm_ro", "id", "roname", \@roNames);

    if ( $::DEBUG > 9 ) { print Dumper("NhmActiveKpi::done serverIdMap ", $serverIdMap); }

    my $activeKpiStats = "$tmpDir/enm_nhm_activekpis.bcp";
    open (BCP, ">$activeKpiStats");

    foreach my $kpiActivationKey (sort keys %{$self->{'r_instrDataEvent'}}) {
        print BCP $self->{'siteId'} . "\t" .
            $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'serverid'} . "\t" .
            $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'time'} . "\t" .
            $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'name'} . "\t" .
            $roNamesIdMap->{$self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'reportingobjecttype'}} . "\t" .
            $self->{'r_instrDataEvent'}->{$kpiActivationKey}->{'node_count'} . "\n" ;
    }
    close BCP;

    # Store 'NHM Active KPI Details'
    dbDo( $dbh, sprintf("DELETE FROM enm_nhm_activekpis WHERE siteid = %d AND time BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $self->{'siteId'}, $self->{'date'}, $self->{'date'}) )
       or die "Failed to delete old data from enm_nhm_activekpis";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$activeKpiStats' INTO TABLE enm_nhm_activekpis" )
        or die "Failed to load new data from '$activeKpiStats' file to 'enm_nhm_activekpis' table";

    unlink($activeKpiStats);

    $r_incr->{'NhmActiveKpi'} = {
                                'r_instrDataEvent' => $self->{'r_instrDataEvent'}
                            };
}

1;

