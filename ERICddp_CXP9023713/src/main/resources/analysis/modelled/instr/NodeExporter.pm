package NodeExporter;

use warnings;
use strict;

use Data::Dumper;

require PromCommon;

our %NODE_EXPORTER_GROUPS = (
    'node_cpu_seconds_total' => 'cpu',
    'node_disk_read_time_seconds_total' => 'disk',
    'node_disk_write_time_seconds_total' => 'disk',
    'node_disk_writes_completed_total' => 'disk',
    'node_disk_reads_completed_total' => 'disk',
    'node_disk_read_bytes_total' => 'disk',
    'node_disk_written_bytes_total' => 'disk',
    'node_disk_io_time_seconds_total' => 'disk',
    'node_memory_MemFree_bytes' => 'memory',
    'node_memory_SwapFree_bytes' => 'memory',
    'node_memory_Buffers_bytes' => 'memory',
    'node_memory_Cached_bytes' => 'memory',
    'node_memory_MemTotal_bytes' => 'memory',
    'node_network_receive_packets_total' => 'network',
    'node_network_transmit_packets_total' => 'network',
    'node_network_transmit_bytes_total' => 'network',
    'node_network_receive_bytes_total' => 'network'
);

our %NODE_EXPORTER_DEVICE_LABEL = (
    'cpu' => 'cpu',
    'disk' => 'device',
    'network' => 'device'
);

our @NODE_LABELS = ( 'kubernetes_pod_node_name', 'node_ip_address', 'instance' );

our $NIC_FILTER = '^eth|^ens|^bond';

sub isMetric($) {
    my ($name) = @_;
    return exists $NODE_EXPORTER_GROUPS{$name};
}

sub handleMetric($$$$$) {
    my ($r_metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name) = @_;

    my $instanceName = PromCommon::getLabelValue( $r_labels, \@NODE_LABELS );
    my $group = $NODE_EXPORTER_GROUPS{$name};
    if ( $::DEBUG > 9 ) {
        printf(
            "NodeExporter::handleMetric name=%s group=%s\n",
            $name,
            (defined $group ? $group : "NA")
        );
    }
    if ( ! defined $group ) {
        return;
    }
    my $r_instance = PromCommon::getInstance($instanceName, $r_instanceMap);
    if ( ! defined $r_instance ) {
        return;
    }

    # Filter NIC devices
    if ( $group eq 'network' && $r_labels->{'device'} !~ /$NIC_FILTER/ ) {
        return;
    }

    my $deviceLabel = $NODE_EXPORTER_DEVICE_LABEL{$group};
    if ( defined $deviceLabel ) {
        $group .=  ":" . $r_labels->{$deviceLabel};
    }
    my $attributeName = $name;
    if ( $attributeName eq 'node_cpu_seconds_total' ) {
        $attributeName = $r_labels->{'mode'};
    }

    PromCommon::appendTS(
        $r_metricsByInstance,
        $instanceName,
        'node_exporter',
        $group,
        $attributeName,
        $r_ts,
        undef,
        undef
    );
}

sub processNodeExporterDisk($$) {
    my ($diskName, $r_timeValues) = @_;

    # https://www.robustperception.io/mapping-iostat-to-the-node-exporters-node_disk_-metrics
    # Note: calculation of avgserv is done in the DiskStats module
    if ( $::DEBUG > 8 ) { print Dumper ("processNodeExporterDisk: disk $diskName",$r_timeValues); }

    my $r_times = $r_timeValues->{'node_disk_writes_completed_total'}->{'timestamps'};
    my @rwsValues = ();
    my @avservValues = ();
    my @byteValues = ();

    my $r_readCounts = $r_timeValues->{'node_disk_reads_completed_total'}->{'values'};
    my $r_writeCounts = $r_timeValues->{'node_disk_writes_completed_total'}->{'values'};
    my $r_readBytes = $r_timeValues->{'node_disk_read_bytes_total'}->{'values'};
    my $r_writeBytes = $r_timeValues->{'node_disk_written_bytes_total'}->{'values'};
    my $r_ioTimes = $r_timeValues->{'node_disk_io_time_seconds_total'}->{'values'};

    if ( defined $r_ioTimes && defined $r_readBytes && defined $r_writeBytes ) {
        for ( my $index = 0; $index <= $#{$r_times}; $index++ ) {
            push @rwsValues, $r_readCounts->[$index] + $r_writeCounts->[$index];
            push @byteValues, $r_readBytes->[$index] + $r_writeBytes->[$index];
        }

        $r_timeValues->{'rws'} = {
            'timestamps' => $r_times,
            'values' => \@rwsValues
        };
        $r_timeValues->{'byte'} = {
            'timestamps' => $r_times,
            'values' => \@byteValues
        };
    } else {
        print Dumper("bad data", $r_timeValues);
    }
}

sub process($) {
    my ($r_ts) = @_;

    if ( $::DEBUG > 9 ) { print Dumper("processNodeExporter: r_ts", $r_ts); }

    my $numCpu = 0;
    my %totalCpuStats = ();
    while ( my ($group,$r_groupStats) = each %{$r_ts} ) {
        if ( $group =~ /^cpu:\d+/ ) {
            $numCpu++;
            while ( my ($mode,$r_samples) = each %{$r_groupStats} ) {
                my $r_timestamps = $r_samples->{'timestamps'};
                my $r_values = $r_samples->{'values'};
                for ( my $index = 0; $index <= $#{$r_timestamps}; $index++ ) {
                    $totalCpuStats{$mode}->{$r_timestamps->[$index]} +=
                        $r_values->[$index];
                }
            }

            delete $r_ts->{$group};
        }
    }
    my %results = ();
    while ( my ($mode,$r_timeValues) = each %totalCpuStats ) {
        my @timestamps = ();
        my @values = ();
        foreach my $timestamp ( sort { $a <=> $b } keys %{$r_timeValues} ) {
            push @timestamps, $timestamp;
            push @values, $r_timeValues->{$timestamp} / $numCpu;
        }

        $results{$mode} = {
            'timestamps' => \@timestamps,
            'values' => \@values
        };
    }
    if ( $::DEBUG > 8 ) { print Dumper("processNodeExporter: results", \%results); }
    $r_ts->{'cpu'} = \%results;

    my $totalMemory = $r_ts->{'memory'}->{'node_memory_MemTotal_bytes'}->{'values'}->[0];
    my $r_memFreeSamples = $r_ts->{'memory'}->{'node_memory_MemFree_bytes'};
    my $r_timestamps = $r_memFreeSamples->{'timestamps'};
    my $r_memFreeValues = $r_memFreeSamples->{'values'};
    my @memUsedValues = ();
    for ( my $index = 0; $index <= $#{$r_timestamps}; $index++ ) {
        push @memUsedValues, $totalMemory - $r_memFreeValues->[$index];
    }
    $r_ts->{'memory'}->{'node_memory_MemUsed_bytes'} = {
        'timestamps' => $r_timestamps,
        'values' => \@memUsedValues
    };

    while ( my ($key,$r_timeValues) = each %{$r_ts} ) {
        if ( $key =~ /^disk:(\S+)/ ) {
            processNodeExporterDisk($1,$r_timeValues);
        }
    }
}

1;
