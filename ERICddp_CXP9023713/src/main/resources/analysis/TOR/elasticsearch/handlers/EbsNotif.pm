package EbsNotif;

use strict;
use warnings;

use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DataStore;
use StatsTime;

our @METRIC_LIST =
  (
   'expectedNumInputRops', 'ropsReceived', 'incompleteInputRops', 'invalidEvents',
   'filesReceived', 'erroneousFiles', 'fileOutputTimeInMilliSec',
   'countersProduced', 'numOfFilesWritten', 'eventsProcessed'
  );

our $MAX_VER = 1084113;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self, $r_cliArgs, $r_incr, $dbh) = @_;

    # After 1.86.1, switch to instr
    if ( (exists $r_cliArgs->{'iso_num'}) && ($r_cliArgs->{'iso_num'} > $MAX_VER) ) {
        if ( $::DEBUG ) { print "EbsNotif::init disabling\n"; }
        return [];
    }

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    if ( exists $r_incr->{'EbsNotif'}->{'byinst'} ) {
        $self->{'byinst'} = $r_incr->{'EbsNotif'}->{'byinst'};
    }
    else {
        $self->{'byinst'} = {};
    }

    $self->{'svcServerMap'} = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, 'ebsm');
    my @subscriptions = ();
    # The eps JVMs aren't setting program so use '*'
    foreach my $server ( keys %{$self->{'svcServerMap'}}  ) {
        push ( @subscriptions, {'server' => $server, 'prog' => "*"} );
    }

    if ( $::DEBUG > 5 ) { print Dumper("EbsNotif::init subscriptions", \@subscriptions); }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    if ( $::DEBUG > 9 ) { print "EbsNotif::handle got message from $host $program : $message\n"; }

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 9 ) { print "EbsNotif::handle got message from $host $program : $message\n"; }

    # com.ericsson.oss.itpf.EVENT_LOGGER [NO USER DATA, EBS Instrumentation, COARSE, EBSM-Counter-Generation-Service, EBSM-Counter-Generation-Metrics, EpsId=evt-5-ebsm3_ebs-eps3,ropId=88907259,ropName=NetworkElement=ieatnetsimv7038-45_LTE47ERBS00007:20191114.2345+0000,expectedNumInputRops=1,ropsReceived=1,incompleteInputRops=0,eventsProcessed=10995,ignoredEvents=0,invalidEvents=0,filesReceived=1,erroneousFiles=0,processingTimeInMilliSec=269,fileOutputTimeInMilliSec=23,countersProduced=9647,numOfFilesWritten=1,isDuplicate=false,numOfFileReWritten=0
    if ( $message =~ /^ com\.ericsson\.oss\.itpf\.EVENT_LOGGER \[NO USER DATA, EBS Instrumentation, COARSE, EBSM-Counter-Generation-Service, EBSM-Counter-Generation-Metrics, (.*)\]$/ ) {
        my %nameValues = ();
        foreach my $nameValue ( split(/,/, $1) ) {
            if ( $::DEBUG > 8 ) { print "EbsNotif::handle nameValue=$nameValue\n"; }
            my ($name,$value) = $nameValue =~ /^([^=]+)=(.*)/;
            $nameValues{$name} = $value;
        }
        if ( $::DEBUG > 6 ) { print Dumper("EbsNotif::handle nameValues", \%nameValues); }

        my ($epsId) = $nameValues{'EpsId'} =~ /_([^_]+)$/;
        my $r_instStats = $self->{'byinst'}->{$host}->{$epsId};
        if ( ! defined $r_instStats ) {
            $r_instStats = [];
            $self->{'byinst'}->{$host}->{$epsId} = $r_instStats;
        }
        my ($sampleDate,$sampleTime) = $timestamp =~ /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/;
        my $sampleDateTime = $sampleDate . " " . $sampleTime . ":00";
        my $r_currentSample = undef;
        if ( $#{$r_instStats} == -1 || $r_instStats->[$#{$r_instStats}]->{'timestamp'} ne $sampleDateTime ) {
            $r_currentSample = {
                'timestamp' => $sampleDateTime,
                'time' => parseTime($sampleDateTime, $StatsTime::TIME_SQL),
            };
            foreach my $metricName ( @METRIC_LIST ) {
                $r_currentSample->{$metricName} = 0;
            }
            push @{$r_instStats}, $r_currentSample;
        } else {
            $r_currentSample = $r_instStats->[$#{$r_instStats}];
        }

        foreach my $metricName ( @METRIC_LIST ) {
            my $metricValue = $nameValues{$metricName};
            if ( defined $metricValue ) {
                $r_currentSample->{$metricName} += $metricValue;
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("EbsNotif::done self", $self); }

    if ( (! exists $self->{'byinst'}) || !(%{$self->{'byinst'}}) ) {
        if ( $::DEBUG ) { print "EbsNotif::done no data\n"; }
        return;
    }

    my %tableModel = (
        'name' => 'enm_ebsm_inst_stats',
        'keycol' => [
            { 'name' => 'serverid', 'reftable' => 'servers' },
            { 'name' => 'epsid' => 'reftable' => 'enm_ebsm_epsid', 'refnamecol' => 'EpsIdText' }
        ]
    );

    my %columnMap =
      (
       'expectedNumInputRops' => 'expected_num_input_rop',
       'ropsReceived' => 'rop_received',
       'incompleteInputRops' => 'incomplete_input_rops',
       'invalidEvents' => 'invalid_events',
       'filesReceived' => 'files_received',
       'erroneousFiles' => 'erroneous_files',
       'fileOutputTimeInMilliSec' => 'file_output_time',
       'countersProduced' => 'countersproducedLTE',
       'eventsProcessed' => 'eventsprocessedLTE',
       'numOfFilesWritten' => 'numoffileswrittenLTE'
      );

    my %propertyValues = (
        'site' => $self->{'site'},
    );

    while ( my ($hostname,$r_byEpsId) = each %{$self->{'byinst'}} ) {
        if ( $::DEBUG > 3 ) { print "EbsNotif::done processing data for host $hostname\n"; }
        my @dataSets = ();
        $propertyValues{'server'} = $hostname;
        $propertyValues{'serverid'} = $self->{'svcServerMap'}->{$hostname};

        while ( my ($epsId,$r_instStats) = each %{$r_byEpsId} ) {
            if ( $::DEBUG > 3 ) { print "EbsStreamNotif::done processing data for epsId $epsId\n"; }
            my %dataSet = (
                'properties' => {
                    'epsid' => { 'sourcevalue' => $epsId }
                },
                'property_options' => {
                    'epsid' => { 'ignorefordelete' => 1 }
                },
                'samples' => $r_instStats
            );
            push @dataSets, \%dataSet;
        }
        DataStore::storePeriodicData($DataStore::ONE_MINUTE,
                                     \%tableModel,
                                     undef,
                                     "ebsm",
                                     \%propertyValues,
                                     \%columnMap,
                                     \@dataSets);
    }

    # We only need to key the last minute sample for the byinst stats
    my %byInst = ();
    while ( my ($hostname,$r_byEpsId) = each %{$self->{'byinst'}} ) {
        while ( my ($epsId,$r_instStats) = each %{$r_byEpsId} ) {
            $byInst{$hostname}->{$epsId} = [ $r_instStats->[$#{$r_instStats}] ];
        }
    }
    $r_incr->{'EbsNotif'} = {
        'byinst' => \%byInst
    };
}

1;
