package CAdvisor;

use warnings;
use strict;
use Data::Dumper;
use Storable qw(dclone);

use StatsTime;

require PromCommon;

our %CADVISOR_GROUPS = (
    'container_cpu_system_seconds_total' => 'cpu',
    'container_cpu_user_seconds_total' => 'cpu',
    'container_cpu_cfs_throttled_seconds_total' => 'cpu',
    'container_memory_working_set_bytes' => 'memory',
    'container_memory_cache' => 'memory',
    'container_fs_writes_sectors_total' => 'disk',
    'container_fs_reads_sectors_total' => 'disk',
    'container_network_receive_bytes_total' => 'network',
    'container_network_transmit_bytes_total' => 'network',
    'container_network_receive_packets_total' => 'network',
    'container_network_transmit_packets_total' => 'network',
    'container_network_transmit_errors_total' => 'network_error',
    'container_network_receive_errors_total' => 'network_error',
    'container_network_transmit_packets_dropped_total' => 'network_error',
    'container_network_receive_packets_dropped_total' => 'network_error'
);

our %CADVISOR_BEHAVIOUR = (
    'container_cpu_system_seconds_total' => 'delta',
    'container_cpu_user_seconds_total' => 'delta',
    'container_cpu_cfs_throttled_seconds_total' => 'delta',
    'container_memory_working_set_bytes' => 'absolute',
    'container_memory_cache' => 'absolute'
);

sub isMetric($) {
    my ($name) = @_;
    return exists $CADVISOR_GROUPS{$name};
}

sub handleMetric($$$$$$) {
    my ($r_metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name, $r_metricsInThisFile) = @_;

    my $instanceName = PromCommon::getLabelValue($r_labels, \@PromCommon::DEFAULT_INSTANCE_NAME_LABELS);
    my $group = $CADVISOR_GROUPS{$name};
    if ( $::DEBUG > 8 ) {
        printf "handleCAdvisorMetric: name=%s instanceName=%s group=%s\n",
            $name,
            (defined $instanceName ? $instanceName : "undef"),
            (defined $group ? $group : "undef");
    }
    if ( ! defined $instanceName  || ! defined $group ) {
        return;
    }

    my $container = $r_labels->{'container_name'};
    if ( ! defined $container ) {
        $container = $r_labels->{'container'};
    }
    if ( $::DEBUG > 8 ) { printf "handleCAdvisorMetric: container=%s\n", (defined $container ? $container : "undef") }
    if ( ! defined $container ) {
        # Later versions of cadvisor seem to have dropped the container
        # label from the network metrics, so set the container here
        if ( $group eq 'network' || $group eq 'network_error') {
            $container = 'POD';
        } else {
            return;
        }
    }

    # For CPU/memory, not clear what kubernetes is reporting when container = POD
    # We'll build our own value later in mergeCAdvisorSamples
    if ( $group eq 'cpu' || $group eq 'memory' ) {
        if  ($container eq 'POD') {
            return;
        }
    }

    my $r_instance = PromCommon::getInstance($instanceName, $r_instanceMap);
    if ( ! defined $r_instance ) {
        return;
    }

    my $mergeKey = $instanceName . $container . $name;
    my $mergeAt = $r_metricsInThisFile->{$mergeKey};
    if ( $::DEBUG > 5 ) { print "handleCAdvisorMetric: instance=$instanceName conainter=$container name=$name mergeAt=", (defined $mergeAt ? $mergeAt : "undef"), "\n"; }
    if ( defined $mergeAt  ) {
        mergeTS($r_metricsByInstance,$instanceName,'cadvisor', $container, $name, $r_ts, $mergeAt);
    } else {
        $r_metricsInThisFile->{$mergeKey} = PromCommon::appendTS(
            $r_metricsByInstance,
            $instanceName,
            'cadvisor',
            $container,
            $name,
            $r_ts,
            undef,
            undef
        );
    }
}

#
# Here we build the "POD" level view, i.e. the sum of the metrics across the containers in the pod
#
sub getPod($) {
    my ($r_ts) = @_;

    if ( $::DEBUG > 9 ) { print Dumper("processCAdvisor: r_ts", $r_ts); }
    #
    # Containers can restart individual to create the sum, we need to delta at a container level first
    # and then sum the deltas
    #

    # First remove the POD container
    my $r_pod = delete $r_ts->{'POD'};

    while ( my ($container,$r_containerMetrics) = each %{$r_ts} ) {
        while ( my ($metric,$r_containerSamples) = each %{$r_containerMetrics} ) {
            my $behaviour = $CADVISOR_BEHAVIOUR{$metric};
            if ( $::DEBUG > 8 ) { print "processCAdvisor: container=$container metric=$metric behaviour=", (defined $behaviour ? $behaviour : "undef"), "\n" };
            if ( ! defined $behaviour ) {
                next;
            }
            my $doDelta = $behaviour eq 'delta';
            if ( $doDelta ) {
                $r_containerSamples = deltaMetric($r_containerSamples);
            }

            my $r_podSamples = $r_pod->{$metric};
            if ( ! defined $r_podSamples) {
                $r_pod->{$metric} = dclone($r_containerSamples);
            } else {
                mergeCAdvisorSamples($r_podSamples, $r_containerSamples, $doDelta);
            }
        }
    }
    if ( $::DEBUG > 8 ) { print "processCAdvisor: containers=", join(",", keys %{$r_ts}), "\n"; }
    if ( $::DEBUG > 9 ) { print Dumper("processCAdvisor: r_pod", $r_pod); }

    return $r_pod;
}

#
# Internal functions
#

#
# Merge (add) the POD level metrics with the metrics from a single container
#
sub mergeCAdvisorSamples($$$) {
    my ($r_podSamples, $r_containerSamples) = @_;

    my $r_podTimeStamps = $r_podSamples->{'timestamps'};
    my $r_podValues = $r_podSamples->{'values'};
    my $r_containerTimeStamps = $r_containerSamples->{'timestamps'};
    my $r_containerValues = $r_containerSamples->{'values'};

    my $maxDiffMsec = $Instr::MAX_TIME_DIFFERENCE * 1000;

    # If the delta between the timestamp of the pod sample and the container s
    my $podSampleIndex = 0;
    my $containerSampleIndex = 0;
    while ( $containerSampleIndex <= $#{$r_containerTimeStamps} && $podSampleIndex <= $#{$r_podTimeStamps} ) {
        if ( $::DEBUG > 9 ) {
            printf "mergeCAdvisorSamples: containerSampleIndex=%d podSampleIndex=%d r_containerTimeStamps=%d podTimeStamps=%d\n",
            $containerSampleIndex, $podSampleIndex, $r_containerTimeStamps->[$containerSampleIndex], $r_podTimeStamps->[$podSampleIndex];
        }
        my $timeDelta = abs($r_containerTimeStamps->[$containerSampleIndex] - $r_podTimeStamps->[$podSampleIndex]);
        if ( $timeDelta < $maxDiffMsec ) {
            # Sample in sync
            $r_podValues->[$podSampleIndex] += $r_containerValues->[$containerSampleIndex];
            $podSampleIndex++;
            $containerSampleIndex++;
        } elsif ( $r_containerTimeStamps->[$containerSampleIndex] < $r_podTimeStamps->[$podSampleIndex] ) {
            # Container sample is later then pod sample so pod view seems to be missing a sample
            # Add missing sample to pod view
            splice @{$r_podTimeStamps}, $podSampleIndex, 0, $r_containerTimeStamps->[$containerSampleIndex];
            splice @{$r_podValues}, $podSampleIndex, 0, $r_containerValues->[$containerSampleIndex];
            $podSampleIndex++;
            $containerSampleIndex++;
        } else {
            # container seems to be missing a sample
            # So move onto the next pod sample to see if we can resync
            $podSampleIndex++;
        }
    }
}


#
# Return the deltas, handling reset
#
sub deltaMetric($) {
    my ($r_samples) = @_;

    my $r_inTimeStamps = $r_samples->{'timestamps'};
    my $r_inValues = $r_samples->{'values'};
    my @outTimestamps = ();
    my @outValues = ();

    my $prevValue = $r_inValues->[0];
    for ( my $index = 1; $index <= $#{$r_inTimeStamps}; $index++ ) {
        push @outTimestamps, $r_inTimeStamps->[$index];
        my $currValue = $r_inValues->[$index];
        if ( $currValue >= $prevValue ) {
            push @outValues, ($currValue - $prevValue);
        } else {
            if ( $::DEBUG > 5 ) { print "deltaMetric: reset @ $r_inTimeStamps->[$index] currValue=$currValue prevValue=$prevValue\n"; }
            push @outValues, $currValue;
        }
        $prevValue = $currValue;
    }

    return { 'timestamps' => \@outTimestamps, 'values' => \@outValues };
}

sub mergeTS($$$$$$$) {
    my ($r_metricsByInstance, $instance, $profileNameSpace, $container, $metric, $r_ts, $mergeAt) = @_;
    my $r_tsData = $r_metricsByInstance->{$instance}->{'metrics'}->{$profileNameSpace}->{$container}->{$metric};

    if ( $::DEBUG > 9 ) { print Dumper("mergeTS: instance=$instance container=$container metric=$metric, r_tsData", $r_tsData);  }

    # Shouldn't have any empty existing data either
    my $lastExistingSampleIndex = $#{$r_tsData->{'timestamps'}};
    $lastExistingSampleIndex > -1 or die "Empty existing series for instance=$instance container=$container metric=$metric";

    # Empty timeseries are supposed to be dumped by loadMetrics
    $#{$r_ts->{'Timestamps'}} > -1 or die "Tried to merge empty time series";


    my $firstTimestamp = $r_ts->{'Timestamps'}->[0];
    my $mergeOkay = 0;

    # Some times we get multiple samples for the same pod,
    # e.g. when the container has been restarted
    my $maxDiffMsec = $Instr::MAX_TIME_DIFFERENCE * 1000;
    if ( $firstTimestamp > ($r_tsData->{'timestamps'}->[$lastExistingSampleIndex] + $maxDiffMsec) ) {
        # Just append
        if ( $::DEBUG > 3 ) {
            printf("mergeTS: firstTimestamp=%d lastExistingSample=%d so appending\n",
                $firstTimestamp, $r_tsData->{'timestamps'}->[$lastExistingSampleIndex]);
        }
        PromCommon::appendTS(
            $r_metricsByInstance,
            $instance,
            $profileNameSpace,
            $container,
            $metric,
            $r_ts,
            undef,
            undef
        );
        return;
    }

    # Merged series Times/Values
    my $r_mt = $r_tsData->{'timestamps'};
    my $r_mv = $r_tsData->{'values'};
    # Single individual series Times/Values
    my $r_it = $r_ts->{'Timestamps'};
    my $r_iv = $r_ts->{'Values'};

    my $mergeCount = 0; # How many samples did we merge
    my $mIndex = $mergeAt;
    my $iIndex = 0;
    while ( $mIndex <= $#{$r_mt} && $iIndex <= $#{$r_it} ) {
        my $timeDelta = abs($r_mt->[$mIndex] - $r_it->[$iIndex]);
        if ( $::DEBUG > 8 ) { print "megreTS: merged $mIndex/$r_mt->[$mIndex] individ=$iIndex/$r_it->[$iIndex] timeDelta=$timeDelta\n"; }
        if ( $timeDelta < $maxDiffMsec ) {
            # Sample in sync
            $r_mv->[$mIndex] += $r_iv->[$iIndex];
            $mIndex++;
            $iIndex++;
            $mergeCount++;
        } elsif ( $r_mt->[$mIndex] > $r_it->[$iIndex] ) {
            # Merged sample is later then individual sample
            # Add missing sample to merged
            splice @{$r_mt}, $mIndex, 0, $r_it->[$iIndex];
            splice @{$r_mv}, $mIndex, 0, $r_iv->[$iIndex];
            $mIndex++;
            $iIndex++;
            $mergeCount++;
        } else {
            # individ seems to be missing a sample
            # So move onto the next merged sample to see if we can resync
            $mIndex++;
        }
    }

    if ( $::DEBUG > 3 ) { print "megreTS: merged $mergeCount of ", $#{$r_it} + 1, " samples\n"; }


    if ( ! $mergeCount ) {
        print "CRITICAL: instance=$instance metric=$metric timestamp miss match @ $mergeAt\n";
        print Dumper("old data", $r_tsData);
        print Dumper("new data", $r_ts);

        my $r_oldts = $r_tsData->{'timestamps'};
        my $r_newts = $r_ts->{'Timestamps'};
        for( my $index = $mergeAt; $index <= $#{$r_oldts}; $index++ ) {
            printf "%s %s\n", formatSiteTime($r_oldts->[$index]/1000, $StatsTime::TIME_SQL), formatSiteTime($r_newts->[$index-$mergeAt]/1000, $StatsTime::TIME_SQL);
        }
        exit 1;
    }
}

1;
