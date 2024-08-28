package FMX;

use strict;
use warnings;

use Data::Dumper;
use JSON;
use Storable qw(dclone);

use StatsDB;
use StatsTime;
use Instr;
use EnmServiceGroup;

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

    if ( exists $r_incr->{'FMX'}->{'host'} ) {
        $self->{'host'} = $r_incr->{'FMX'}->{'host'};
        $self->{'last'} = $r_incr->{'FMX'}->{'last'};
    }

    $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},'fmx');
    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'fmxstats' };
    }
    if ( $::DEBUG > 5 ) { print Dumper("FMX::init subscriptions",\@subscriptions); }

    return \@subscriptions;
}


sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $::DEBUG > 7 ) { print "FMX::handle got message from $host $program \"$message\"\n"; }

    if ( $severity ne 'info' ) {
        return;
    }

    if($message =~/\s+monitor:\s+alarmsCreatedCount=(\d+),alarmsDeletedCount=(\d+),contextsCreatedCount=(\d+),contextsDeletedCount=(\d+)/) {
        push @{$self->{'byhost'}->{$host}->{'fmxMonitorMetrics'}}, {
            'startTime'          => $timestamp,
            'alarmCreated'       => $1,
            'alarmDeleted'       => $2,
            'activeAlarms'       => $1 - $2,
            'RuleContextCreated' => $3,
            'RuleContextDeleted' => $4,
            'activeRuleContext'  => $3 - $4
        };
    } elsif( $message =~ /\s+mq:\s+allQueueLength=(\d+),allQueueRate=(\d+.\d{1,2}),contextsQueueLength=(\d+),contextsQueueRate=(\d+.\d{1,2})/ ) {
        push @{$self->{'byhost'}->{$host}->{'fmxEngineMqMetrics'}}, {
            'startTime' => $timestamp,
            'allQueueLength' => $1,
            'allQueueRate' => $2,
            'contextsQueueLength' => $3,
            'contextsQueueRate' => $4
        };
    } elsif($message =~ /\s+rule:\s+engine=(\d+),blockType=(.*),moduleName=(.*),ruleName=(.*),blockID=(\d+),blockName=(.*),count=(\d+)/) {
        my $r_fmxEngineRuleMetrics = {
            'startTime'          => $timestamp,
            'engine'             => $1,
            'blockType'          => $2,
            'moduleName'         => $3,
            'ruleName'           => $4,
            'blockID'            => $5,
            'blockName'          => $6,
            'count'              => $7
        };
        my $ruleKey = $r_fmxEngineRuleMetrics->{'moduleName'} . "," . $r_fmxEngineRuleMetrics->{'ruleName'} . "," .
            $r_fmxEngineRuleMetrics->{'blockID'};
        push @{$self->{'byhost'}->{$host}->{'fmxRuleMetrics'}->{$ruleKey}}, $r_fmxEngineRuleMetrics;
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    my @hosts = sort keys %{$self->{'byhost'}};
    if ( $#hosts == -1 ) {
        # No stats
        return;
    }

    # We only need stats from one host
    my $host = $self->{'host'};
    if ( ! defined $host ) {
        $host = $hosts[0];
    }
    my $serverId = $self->{'srvIdMap'}->{$host};

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    my %saveSamples = ();

    my $r_fmxMonitorMetrics = $self->{'byhost'}->{$host}->{'fmxMonitorMetrics'};
    if ( $#{$r_fmxMonitorMetrics} > -1 ) {
        # Save last sample for next iteration
        $saveSamples{'fmxMonitorMetrics'} = dclone($r_fmxMonitorMetrics->[$#{$r_fmxMonitorMetrics}]);
        # If we have previous execution, then inject last sample from previous execution
        # so we can delta
        if ( exists $self->{'last'} ) {
            unshift @{$r_fmxMonitorMetrics}, $self->{'last'}->{'fmxMonitorMetrics'};
        }
        deltaSamples( $r_fmxMonitorMetrics, [ 'alarmCreated', 'alarmDeleted', 'RuleContextCreated', 'RuleContextDeleted'] );
        shift @{$r_fmxMonitorMetrics};
        storeFmxMonitor($r_fmxMonitorMetrics,$self->{'date'},$self->{'siteId'}, $serverId, $dbh, $tmpDir );
    }

    my $r_fmxMq = $self->{'byhost'}->{$host}->{'fmxEngineMqMetrics'};
    if ( $#{$r_fmxMq} > -1 ) {
        storeFmxMq( $r_fmxMq,$self->{'date'},$self->{'siteId'}, $serverId, $dbh, $tmpDir );
    }

    my $r_fmxRuleRuleMetrics = $self->{'byhost'}->{$host}->{'fmxRuleMetrics'};
    if ( keys %{$r_fmxRuleRuleMetrics} ) {
        while ( my ($ruleName,$r_ruleMetrics) = each %{$r_fmxRuleRuleMetrics} ) {
            # Save last sample for next iteration
            $saveSamples{'fmxRuleMetrics'}->{$ruleName} = dclone($r_ruleMetrics->[$#{$r_ruleMetrics}]);
            # If we have previous execution, then inject last sample from previous execution
            # so we can delta
            if ( exists $self->{'last'} ) {
                unshift @{$r_ruleMetrics}, $self->{'last'}->{'fmxRuleMetrics'}->{$ruleName};
            }
            deltaSamples( $r_ruleMetrics, ['count'], $dbh, $tmpDir );
            shift @{$r_ruleMetrics};
        }
        storeFmxRule( $r_fmxRuleRuleMetrics,$self->{'date'},$self->{'siteId'}, $serverId, $dbh, $tmpDir );
    }

    $r_incr->{'FMX'} = {
        'host' => $host,
        'last' => \%saveSamples
    };
}

sub storeFmxMonitor($$$$) {

    my $fmxMonitor = shift;
    my $date = shift;
    my $siteId = shift;
    my $serverId = shift;
    my $dbh = shift;
    my $tmpDir = shift;

    # Write FMX Montior Metrics   related data to 'enm_fmx_monitor' file
    my $bcpFileFmxMonitor = "$tmpDir/enm_fmx_monitor.bcp";
    open BCP, ">$bcpFileFmxMonitor" or die "Failed to open $bcpFileFmxMonitor";
    my ($minTime,$maxTime);
    foreach my $monitor (@{$fmxMonitor}) {
        my $time = parseTime($monitor->{startTime},$StatsTime::TIME_ELASTICSEARCH_MSEC);
        my $timestamp = formatTime($time,$StatsTime::TIME_SQL);
        if ( (!defined $minTime) || ($time < $minTime) ) {
            $minTime = $time;
        }
        if ( (!defined $maxTime) || ($time > $maxTime) ) {
            $maxTime = $time;
        }

        # Some entries may be removed during the deltaing, so we insert null for them.
        my $alarmsCreated = exists $monitor->{alarmCreated} ? $monitor->{alarmCreated} : '\N';
        my $alarmsDeleted = exists $monitor->{alarmDeleted} ? $monitor->{alarmDeleted} : '\N';
        my $ruleContextCreated = exists $monitor->{RuleContextCreated} ? $monitor->{RuleContextCreated} : '\N';
        my $ruleContextDeleted = exists $monitor->{RuleContextDeleted} ? $monitor->{RuleContextDeleted} : '\N';
        print BCP "$siteId\t$serverId\t$timestamp\t$alarmsCreated\t$alarmsDeleted\t$monitor->{activeAlarms}\t$ruleContextCreated\t$ruleContextDeleted\t$monitor->{activeRuleContext}\n";
    }

    close BCP or die "unable to close BCP file";

    dbDo( $dbh, sprintf("DELETE FROM enm_fmx_monitor WHERE siteid = %d AND startTime BETWEEN '%s' AND '%s'",
                        $siteId,
                        formatTime($minTime,$StatsTime::TIME_SQL),
                        formatTime($maxTime,$StatsTime::TIME_SQL)
                    )
      ) or die "Failed to delete from enm_fmx_monitor" .$dbh->errstr."\n";
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileFmxMonitor' INTO TABLE enm_fmx_monitor" )
       or die "Failed to load new data from '$bcpFileFmxMonitor' file to 'enm_fmx_monitor' table".$dbh->errstr."\n";
}

sub storeFmxRule($$$$$$) {
    my $fmxRules = shift;
    my $date = shift;
    my $siteId = shift;
    my $serverId = shift;
    my $dbh = shift;
    my $tmpDir = shift;

    # Write FMX Rule Metrics   related data to 'enm_fmx_rule' file
    my ($minTime,$maxTime);
    my $bcpFileFmxRule = "$tmpDir/enm_fmx_rule.bcp";
    open BCP, ">$bcpFileFmxRule" or die "Failed to open $bcpFileFmxRule";
    foreach my $fmxRuleArray (values %{$fmxRules}) {
        foreach my $rule (@{$fmxRuleArray}) {
            my $time = parseTime($rule->{startTime},$StatsTime::TIME_ELASTICSEARCH_MSEC);
            my $timestamp = formatTime($time,$StatsTime::TIME_SQL);
            if ( (!defined $minTime) || ($time < $minTime) ) {
                $minTime = $time;
            }
            if ( (!defined $maxTime) || ($time > $maxTime) ) {
                $maxTime = $time;
            }
            print BCP "$siteId\t$serverId\t$timestamp\t$rule->{engine}\t$rule->{blockType}\t$rule->{moduleName}\t$rule->{ruleName}\t$rule->{blockID}\t$rule->{blockName}\t$rule->{count}\n";
        }
    }
    close BCP or die "unable to close BCP file";

    dbDo( $dbh, sprintf("DELETE FROM enm_fmx_rule WHERE siteid = %d AND startTime BETWEEN '%s' AND '%s'",
                        $siteId,
                        formatTime($minTime,$StatsTime::TIME_SQL),
                        formatTime($maxTime,$StatsTime::TIME_SQL)
                    )
      ) or die "Failed to delete from enm_fmx_rule" .$dbh->errstr."\n";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileFmxRule' INTO TABLE enm_fmx_rule" )
       or die "Failed to load new data from '$bcpFileFmxRule' file to 'enm_fmx_rule' table".$dbh->errstr."\n";
}

sub storeFmxMq($$$$) {

    my $fmxMq = shift;
    my $date = shift;
    my $siteId = shift;
    my $serverId = shift;
    my $dbh = shift;
    my $tmpDir = shift;

    # Write FMX Message Queue Metrics related data to 'enm_fmx_message_queue' file
    my $bcpFileFmxMq = "$tmpDir/enm_fmx_message_queue.bcp";

    open BCP, ">$bcpFileFmxMq" or die "Failed to open $bcpFileFmxMq";
    my ($minTime,$maxTime);
    foreach my $msgqueue (@{$fmxMq}) {
        my $time = parseTime($msgqueue->{startTime},$StatsTime::TIME_ELASTICSEARCH_MSEC);
        my $timestamp = formatTime($time,$StatsTime::TIME_SQL);
        if ( (!defined $minTime) || ($time < $minTime) ) {
            $minTime = $time;
        }
        if ( (!defined $maxTime) || ($time > $maxTime) ) {
            $maxTime = $time;
        }
        print BCP "$siteId\t$serverId\t$timestamp\t$msgqueue->{allQueueLength}\t$msgqueue->{allQueueRate}\t$msgqueue->{contextsQueueLength}\t$msgqueue->{contextsQueueRate}\n";
    }
    close BCP or die "unable to close BCP file";

    dbDo( $dbh, sprintf("DELETE FROM enm_fmx_message_queue WHERE siteid = %d AND startTime BETWEEN '%s' AND '%s'",
                        $siteId,
                        formatTime($minTime,$StatsTime::TIME_SQL),
                        formatTime($maxTime,$StatsTime::TIME_SQL)
                    )
      ) or die "Failed to delete from enm_fmx_message_queue" .$dbh->errstr."\n";
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileFmxMq' INTO TABLE enm_fmx_message_queue" )
       or die "Failed to load new data from '$bcpFileFmxMq' file to 'enm_fmx_message_queue' table".$dbh->errstr."\n";
}

1;
