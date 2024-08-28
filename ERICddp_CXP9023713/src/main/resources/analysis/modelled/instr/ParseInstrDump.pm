package ParseInstrDump;

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use DBI;
use JSON;
use Module::Load;
use File::Basename;
use Storable qw(dclone);
use Time::HiRes;

use StatsDB;
use StatsCommon;
use StatsTime;
use Instr;

require ModelledInstr;
require PromCommon;
require CAdvisor;
require NodeExporter;
require ExternalStore;

use lib dirname(__FILE__) . "/modules";

our %podInfo = ();
our %serverInfo = ();
our $r_svcIdMap = {};
our %parseMetrics = ();

our %timestampCache = ();

our $INCR_VERSION = 2;

my %REPLACE_CHARS = (
    '_dh_' => '-',
    '_dt_' => '.',
    '_fs_' => '/',
    '_cl_' => ':',
    '_eq_' => '=',
    '_sp_' => ' ',
    '_rl_' => '(',
    '_rr_' => ')',
    '_dr_' => '$',
    '_at_' => '@',
    '_sc_' => ';',
    '_sl_' => '[',
    '_bs_' => '\\',
    '_ca_' => ','
    );

our @NAMESPACE_LABELS = ( 'namespace', 'kubernetes_namespace' );

sub serviceToSg($) {
    my ($service) = @_;

    my $result = $service;
    if ( $service =~ /(\S+)serv$/ ) {
        $result = $1 . "service";
    }

    return $result;
}

sub unManagleString($) {
    my ($str) = @_;
    while ( my ($from,$to) = each %REPLACE_CHARS ) {
        $str =~ s/$from/$to/g;
    }
    return $str;
}

sub getPrometheusMetricsInModel($$) {
    my ($r_model, $r_prometheusMetrics) = @_;

    my %serviceFilter = ();
    if ( exists $r_model->{'services'} ) {
        $serviceFilter{'type'} = 'match';
        $serviceFilter{'list'} = $r_model->{'services'};
    } elsif ( exists $r_model->{'blacklist'} ) {
        $serviceFilter{'type'} = 'notmatch';
        $serviceFilter{'list'} = $r_model->{'blacklist'};
    } else {
        $serviceFilter{'type'} = 'all';
    }

    my $r_multiId = $r_model->{'multi'};

    while ( my ($modelMgName, $r_modelMG) = each %{$r_model->{'metricgroup'}} ) {
        #
        # mgMetricInfo will have label_filters built from any labelproperties in the model metric
        # group
        #
        my %mgMetricInfo = (
            'group' => $modelMgName,
            'label_filter' => []
        );
        if ( exists $r_modelMG->{'property'} ) {
            foreach my $r_property ( @{$r_modelMG->{'property'}} ) {
                if ( $r_property->{'type'} eq 'multilabelproperty' ) {
                    # If this is a multi-instance metric group, then we have to
                    # add the value of any labelproperties to the metric group name
                    my $addtogroup = 0;
                    if ( $r_property->{'addtogroup'} eq 'auto' ) {
                        if ( defined $r_multiId ) {
                            foreach my $multiId ( @{$r_multiId} ) {
                                if ( $multiId eq $r_property->{'name'} ) {
                                    $addtogroup = 1;
                                    last;
                                }
                            }
                        }
                    } elsif ( $r_property->{'addtogroup'} eq 'true' ) {
                        $addtogroup = 1;
                    }
                    my %label_filter = (
                                        'name' => $r_property->{'label'},
                                        'addtogroup' => $addtogroup,
                                        'optional' => $r_property->{'optional'},
                                        'index' => $r_property->{'index'}
                                       );
                    if ( exists $r_property->{'filtervalue'} ) {
                        $label_filter{'value'} = $r_property->{'filtervalue'};
                    }

                    push @{$mgMetricInfo{'label_filter'}}, \%label_filter;
                }
            }
        }

        #
        # Populate r_prometheusMetrics with a mapping from the metric name & service back to
        # the meticInfo (metric group name/label_filters)
        #
        foreach my $r_modelMetric ( @{$r_modelMG->{'metric'}} ) {
            if ( $::DEBUG > 7 ) { print Dumper("getPrometheusMetricsInModel: r_modelMetric", $r_modelMetric); }
            my $r_metricInfo = undef;
            # If a metric has labelfilters, then we build a specific metricInfo for
            # this metric
            if ( exists $r_modelMetric->{'labelfilters'} ) {
                $r_metricInfo = dclone(\%mgMetricInfo);
                foreach my $r_labelfilter ( @{$r_modelMetric->{'labelfilters'}} ) {
                    push @{$r_metricInfo->{'label_filter'}}, {
                        'name' => $r_labelfilter->{'name'},
                        'value' => $r_labelfilter->{'value'},
                        'addtogroup' => 0,
                        'optional' => 0
                    }
                }
            } else {
                # no labelfilter, then we'll use %mgMetricInfo as the r_metricInfo
                $r_metricInfo = \%mgMetricInfo;
            }

            my $r_usedByModels = $r_prometheusMetrics->{$r_modelMetric->{'source'}};
            if ( ! defined $r_usedByModels ) {
                $r_usedByModels = [];
                $r_prometheusMetrics->{$r_modelMetric->{'source'}} = $r_usedByModels;
            }

            # The default behaviour of for getting the "instance name" is to
            # look for a one of the labels in @DEFAULT_INSTANCE_NAME_LABELS
            # A model can override this behaviour will the instnamelabel attribute
            # There is a "special" variant of this when instnamelabel="NONE"
            # This will cause getInstanceName to fallback on the IP address/hostname
            # part of the instance label (e.g. used for metrics from node exporter)
            my $r_instanceNameLabels = \@PromCommon::DEFAULT_INSTANCE_NAME_LABELS;
            my $instanceNameLabel = $r_model->{'instnamelabel'};
            if ( defined $instanceNameLabel ) {
                $r_instanceNameLabels = [];
                if ( $instanceNameLabel ne "NONE" ) {
                    push @{$r_instanceNameLabels}, $instanceNameLabel;
                }
            }
            my %usedByModel = (
                'sg' => \%serviceFilter,
                'mi' => $r_metricInfo,
                'namespace' => $r_model->{'namespace'},
                'instnamelabels' => $r_instanceNameLabels
            );

            push @{$r_usedByModels}, \%usedByModel;
        }
    }
}

#
# prometheusMetrics hash metric_name -> [ usedByModel ]
# where useByModel is a hash
#  sg -> hash
#    'type' => match, nonmatch, all
#    'servicelist' => hash by service name
#  mi -> metricInfo
# "Normal" case is for a metric name to sample to a single metricInfo with an empty label_filter
#
# metricInfo
#  group -> model metric group name
#  label_filter -> list of { name, value, addtogroup }
#   addtogroup to use for labelproperties where you end up with multiple instances
#   of the metric group and the value of the label is added to the metric group name
#
sub getPrometheusMetrics($) {
    my ($r_models) = @_;

    my %prometheusMetrics = ();
    foreach my $r_model ( @{$r_models} ) {
        if ( $::DEBUG > 10 ) { print Dumper("getPrometheusMetrics: r_model", $r_model); }

        if ( $r_model->{'namespace'} =~ /^prometheus@/ ) {
            getPrometheusMetricsInModel($r_model, \%prometheusMetrics);
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("getPrometheusMetrics: prometheusMetrics", \%prometheusMetrics); }

    return \%prometheusMetrics;
}

#
# Check if the metrics labels match those required by the r_metricInfo
# Return
#  undef if match fails
#  hash of label values if match successful
#
sub filterPromLabels($$) {
    my ($r_labels, $r_metricInfo) = @_;

    my $matched = 1;
    my %labelValues = ();
    foreach my $r_filterLabel ( @{$r_metricInfo->{'label_filter'}} ) {
        my $metricLabelValue = $r_labels->{$r_filterLabel->{'name'}};
        if ( $::DEBUG > 5 ) {
            printf("filterPromLabels: filterLabelName=%s, filterLabelValue=%s, metricLabelValue=%s\n",
                   $r_filterLabel->{'name'},
                   (defined $r_filterLabel->{'value'} ? $r_filterLabel->{'value'} : "undef"),
                   (defined $metricLabelValue ? $metricLabelValue : "undef")
                );
        }
        if ( ($r_filterLabel->{'optional'} == 0 and  ! defined $metricLabelValue) ||
             (defined $r_filterLabel->{'value'} && ($metricLabelValue !~ /$r_filterLabel->{'value'}/)) ) {
            if ( $::DEBUG > 4 ) { print "filterPromLabels: filter drop filterLabelName=$r_filterLabel->{'name'}\n"; }
            return undef;
        } elsif ( $r_filterLabel->{'addtogroup'} ) {
            if ( defined $metricLabelValue ) {
                $labelValues{$r_filterLabel->{'name'}} = $metricLabelValue;
            } else {
                $labelValues{$r_filterLabel->{'name'}} = '';
            }
        }
    }

    if ( $::DEBUG > 5 ) { print Dumper("filterPromLabels: labelValues", \%labelValues); }

    return \%labelValues;
}

#
# Get the instance, if its a known instance get the app
# Lookup r_promMetricInfo to see if we interested in this metric for this app (or all if we want it for
# all apps)
# Iterate through the metricInfos to see if the labels match the filter and if so save the data
#
sub handlePromMetric($$$$$$) {
    my ($r_metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name, $r_usedByModels) = @_;

    foreach my $r_usedByModel ( @{$r_usedByModels} ) {
        if ( $::DEBUG > 8 ) { print Dumper("handlePromMetric: r_usedByModel", $r_usedByModel); }

        my $instanceName = getInstanceName($r_labels, $r_usedByModel->{'instnamelabels'});
        if ( $::DEBUG > 8 ) { printf "handlePromMetric: name=%s instanceName=%s\n", $name, (defined $instanceName ? $instanceName : "undef"); }

        my $r_instance = PromCommon::getInstance($instanceName, $r_instanceMap);
        if ( ! defined $r_instance ) {
            next;
        }
        setInstanceInfo($r_metricsByInstance, $instanceName, $r_labels);

        my $instanceApp = $r_metricsByInstance->{$instanceName}->{'info'}->{'app'};
        if ( $::DEBUG > 8 ) { printf "handlePromMetric: instance app=%s\n", (defined $instanceApp ? $instanceApp : "undef"); }

        #
        # If this model is filtering by servicegroup, then apply that filter
        #
        my $sgFilterPassed = 0;
        my $sgMatchType = $r_usedByModel->{'sg'}->{'type'};
        if ( $sgMatchType eq 'all' ) {
            $sgFilterPassed = 1;
        } elsif ( defined $instanceApp ) {
            my $inList = exists $r_usedByModel->{'sg'}->{'list'}->{$instanceApp};
            if ( $sgMatchType eq 'match' ) {
                $sgFilterPassed = $inList;
            } else {
                $sgFilterPassed = $inList == 0;
            }
        }
        if ( ! $sgFilterPassed ) {
            next;
        }

        my $r_metricInfo = $r_usedByModel->{'mi'};
        if ( $::DEBUG > 7 ) { print Dumper("handlePromMetric: r_metricInfo", $r_metricInfo); }
        my $r_labelValues = filterPromLabels($r_labels, $r_metricInfo);
        if ( defined $r_labelValues ) {
            PromCommon::appendTS(
                $r_metricsByInstance,
                $instanceName,
                $r_usedByModel->{'namespace'},
                $r_metricInfo->{'group'},
                $name,
                $r_ts,
                $r_labelValues,
                $r_metricInfo->{'label_filter'}
            );
            last;
        }
    }
}

sub handleInstrMetric($$$$$$) {
    my ($r_metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name, $ns) = @_;

    my $instanceName = getInstanceName($r_labels, \@PromCommon::DEFAULT_INSTANCE_NAME_LABELS);
    my $r_instance = PromCommon::getInstance($instanceName, $r_instanceMap);
    if ( ! defined $r_instance ) {
        return;
    }

    setInstanceInfo($r_metricsByInstance, $instanceName, $r_labels);

    # If an MBean has a custom collection interval, then e2e will add the
    # interval to the profile name but the models don't have this interval in
    # their namespace so we need to strip off the number here
    my $profileName = $r_labels->{'pn'};
    $profileName =~ s/Instrumentation-\d+$/Instrumentation/;
    my $profileNameSpace = $profileName . '@' . $ns;
    if ( $::DEBUG > 8 ) { print "handleInstrMetric: name=$name instance=$instanceName profileNameSpace=$profileNameSpace\n"; }
    if ( $name =~ /^(\S+)_nm_(\S+)/ ) {
        my ($group,$metric) = ($1,$2);
        $group = unManagleString($group);
        $metric = unManagleString($metric);
        PromCommon::appendTS(
            $r_metricsByInstance,
            $instanceName,
            $profileNameSpace,
            $group,
            $metric,
            $r_ts,
            undef,
            undef
        );
    }
}

# Checks that incr data has the right version and that the remote_writer files haven't changed
# i.e. that the cENM system hasn't been re-installed
sub validateIncr($) {
    my ($r_incrData) = @_;

    # Check that the incremental data is compatiple with this version
    if ( ! defined $r_incrData->{'version'} || $r_incrData->{'version'} != $INCR_VERSION ) {
        $r_incrData = {};
    }
    $r_incrData->{'version'} = $INCR_VERSION;

    my $fileTimestamp = $r_incrData->{'fileTimestamp'};
    if ( defined $fileTimestamp ) {
        my @fileStats = stat($r_incrData->{'fileName'});
        if ( $fileTimestamp != $fileStats[9] ) {
            print "WARN: Timestamp mis-match for " . $r_incrData->{'fileName'} . "\n";
            $r_incrData = { 'version' => $INCR_VERSION };
        }
    }

    return $r_incrData;
}

# Read the labels section from version 2 files
# keep reading until we hit the section line for the values
sub readLabels($) {
    my ($r_labelMap) = @_;

    <INPUT>; # skip section line
    my $done = 0;
    while ( ! $done ) {
        my $line = <INPUT>;
        $done = 1;
        if ( defined $line ) {
            my $r_json = decode_json($line);
            if ( ! exists $r_json->{'section'} ) {
                $r_labelMap->{$r_json->{'Id'}} = $r_json->{'Labels'};
                $done = 0;
            }
        }
    }

    if ( $::DEBUG > 5 ) { print Dumper("readLabels r_labelMap", $r_labelMap); }
}

sub readBaseTime() {
    my $line = <INPUT>;
    my $r_json = decode_json($line);
    return $r_json->{'basetime'};
}

sub preProcessTs($$$) {
    my ($r_ts, $r_idLabelsMap, $baseTime) = @_;

    my $r_labels = $r_idLabelsMap->{$r_ts->{'Id'}};
    if ( defined $r_labels ) {
        $r_ts->{'Labels'} = $r_labels;
    } else {
        return undef;
    }

    my $r_timestamps = $r_ts->{'Timestamps'};
    my $r_values = $r_ts->{'Values'};
    my $numSamples = $#{$r_timestamps} + 1;
    for ( my $index = 0; $index < $numSamples; $index++ ) {
        $r_timestamps->[$index] = ($r_timestamps->[$index] + $baseTime) * 1000;
        if ( $index > 0 ) {
            $r_values->[$index] = $r_values->[$index-1] + $r_values->[$index];
        }
    }

    return $r_labels;
}

sub loadMetrics($$$$$$$) {
    my ($metricsDir, $r_incrData, $r_instanceMap, $r_prometheusMetrics, $k8sNamespace, $externalEndPoint, $site) = @_;

    my $loadStart = Time::HiRes::time();

    my %metricsByInstance = ();

    my $fileIndex = $r_incrData->{'fileIndex'};
    if ( ! defined $fileIndex ) {
        $fileIndex = 0;
    }

    my @allFiles = ();
    opendir(my $dh, $metricsDir) or die "Cannot open $metricsDir";
    foreach my $file (readdir($dh)) {
        if ( $file =~ /^dump\.\d+\.gz$/ ) {
            push @allFiles, $file;
        }
    }
    closedir($dh);

    my @filesToLoad = ();
    foreach my $file ( sort @allFiles ) {
        my ($index) = $file =~ /^dump\.(\d+)\.gz$/;
        if ( $::DEBUG > 3 ) { print "loadMetrics: fileIndex=$fileIndex index=$index\n"; }
        if ( $index > $fileIndex ) {
            push @filesToLoad, $metricsDir. "/" . $file;
            $fileIndex = $index;
        }
    }
    $r_incrData->{'fileIndex'} = $fileIndex;
    if ( $::DEBUG > 3 ) { print Dumper("loadMetrics: filesToLoad", \@filesToLoad); }

    my $r_externalStore = undef;
    if ( defined $externalEndPoint ) {
        $r_externalStore = new ExternalStore($externalEndPoint, $site);
        $r_externalStore->open();
    }

    my $emptySamples = 0;
    my %wrongK8sNamespace = ();
    my $totalSamples = 0;
    my $totalReadTime = 0;
    my $totalDecodeTime = 0;
    my $totalHandleTime = 0;
    my $totalUncompressedSize = 0;

    if ( ! exists  $r_incrData->{'idLabelsMap'} ) {
        $r_incrData->{'idLabelsMap'} = {};
    }
    my $r_idLabelsMap = $r_incrData->{'idLabelsMap'};
    foreach my $metricsFile ( @filesToLoad ) {
        StatsCommon::logMsg("Processing %s", $metricsFile);

        if ( ! exists $r_incrData->{'fileTimestamp'} ) {
            my @fileStats = stat($metricsFile);
            $r_incrData->{'fileTimestamp'} = $fileStats[9];
            $r_incrData->{'fileName'} = $metricsFile;
        }

        open INPUT, "<:gzip", "$metricsFile" or die "Cannot open file $metricsFile";
        my $header_line = <INPUT>;
        my $r_header = decode_json($header_line);
        my $fileVersion = $r_header->{'version'};
        my $baseTime = undef;
        if ( $::DEBUG > 1 ) { print "loadMetrics fileVersion=$fileVersion header_line=$header_line"; }
        if ( $fileVersion > 1 ) {
            $baseTime = readBaseTime();
            readLabels($r_idLabelsMap);
        }

        my %metricsInThisFile = ();
        my $startSampleCount = $totalSamples;
        my $keepGoing = 1;
        while ( $keepGoing ) {
            my $readStart = Time::HiRes::time();
            my $json_str = <INPUT>;
            $totalReadTime += Time::HiRes::time() - $readStart;
            if ( ! defined $json_str ) {
                $keepGoing = 0;
                next;
            }

            $totalUncompressedSize += length($json_str);

            $totalSamples++;
            my $decodeStart = Time::HiRes::time();
            my $r_ts = decode_json($json_str);
            my $decodeEnd = Time::HiRes::time();
            $totalDecodeTime += $decodeEnd - $decodeStart;

            if ( $::DEBUG > 9 ) { print Dumper("loadMetrics: r_ts", $r_ts); }

            # Drop empty samples
            if ( $#{$r_ts->{'Timestamps'}} == -1 ) {
                if ( $::DEBUG > 3 ) { print "loadMetrics: dropping empty sample\n"; }
                $emptySamples++;
                next;
            }

            if ( defined $r_externalStore ) {
                $r_externalStore->process($r_ts);
            }

            my $r_labels = undef;
            if ( $fileVersion > 1 ) {
                $r_labels = preProcessTs($r_ts, $r_idLabelsMap, $baseTime);
                if ( ! defined $r_labels ) {
                    printf "WARN: Could not map labels for Id %d\n", $r_ts->{'Id'};
                    next;
                }
            } else {
                $r_labels = $r_ts->{'Labels'};
            }

            if ( $::DEBUG > 8 ) { print Dumper("loadMetrics: r_labels", $r_labels); }

            my $name = $r_labels->{'__name__'};

            my $nameSpaceOkay = 1;
            my $sampleK8sNamespace;
            if ( defined $k8sNamespace ) {
                $sampleK8sNamespace = PromCommon::getLabelValue($r_labels, \@NAMESPACE_LABELS);
                if ( defined $sampleK8sNamespace && $sampleK8sNamespace ne $k8sNamespace ) {
                    StatsCommon::debugMsg(3, "loadMetrics: dropping %s due wrong namespace %s", $name, $sampleK8sNamespace);
                    $nameSpaceOkay = 0;
                }
            }

            my $ns = $r_labels->{'ns'};

            my $handleStart = Time::HiRes::time();
            if ( defined $ns && $nameSpaceOkay ) {
                handleInstrMetric(\%metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name, $ns);
            } elsif ( CAdvisor::isMetric($name) && $nameSpaceOkay ) {
                CAdvisor::handleMetric(\%metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name, \%metricsInThisFile);
            } elsif ( NodeExporter::isMetric($name) ) {
                NodeExporter::handleMetric(\%metricsByInstance, $r_instanceMap, $r_ts, $r_labels, $name);
            } elsif ( exists $r_prometheusMetrics->{$name} && $nameSpaceOkay ) {
                handlePromMetric(\%metricsByInstance, $r_instanceMap, $r_ts,
                                 $r_labels, $name, $r_prometheusMetrics->{$name});

            } elsif ( ! $nameSpaceOkay ) {
                $wrongK8sNamespace{$sampleK8sNamespace}++;
            }
            my $handleEnd = Time::HiRes::time();
            $totalHandleTime += $handleEnd - $handleStart;
        }
        close INPUT;
        if ( $totalSamples == $startSampleCount ) {
            print "WARNING: 0 samples found in $metricsFile\n";
        }
    }

    if ( defined $r_externalStore ) {
        $r_externalStore->close();
    }

    if ( $emptySamples > 0 ) {
        print "WARN: Dropped $emptySamples empty samples out of a total of $totalSamples\n";
    }
    if ( %wrongK8sNamespace ) {
        while ( my ($ns, $count) = each %wrongK8sNamespace ) {
            print "WARN: Dropped $count samples out of a total of $totalSamples from wrong namespace \"$ns\"\n";
        }
    }

    $parseMetrics{'load.timeTotal'} = Time::HiRes::time() - $loadStart;
    $parseMetrics{'load.timeRead'} = $totalReadTime;
    $parseMetrics{'load.timeDecode'} = $totalDecodeTime;
    $parseMetrics{'load.timeHandle'} = $totalHandleTime;
    $parseMetrics{'load.fileCount'} = $#filesToLoad + 1;
    $parseMetrics{'load.uncompressedSizeMB'} = $totalUncompressedSize / (1024 * 1024);
    $parseMetrics{'load.totalSamples'} = $totalSamples;

    return \%metricsByInstance;
}

#
# create the grouped sample timestamps
#
sub makeGroupTimestamps($) {
    my ($r_samples) = @_;
    if ( $::DEBUG > 10 ) { print Dumper("makeGroupTimestamps: r_samples", $r_samples); }
    my @results = ();
    foreach my $timestamp ( @{$r_samples} ) {
        my $time = int($timestamp / 1000);
        # formatSiteTime seems expensive so we cache the timestamps
        # For a run with 9 files/2280115 samples, caching reduces the total time in this
        # function from 220 seconds to 7 seconds
        my $timestamp = $timestampCache{$time};
        if ( ! defined $timestamp ) {
            $timestamp = formatSiteTime( $time, $StatsTime::TIME_SQL );
            $timestampCache{$time} = $timestamp;
        }
        my $r_sample = { 'timestamp' => $timestamp, 'time' => $time };
        push @results, $r_sample;
    }

    if ( $::DEBUG > 10 ) { print Dumper("makeGroupTimestamps: results", \@results); }

    return \@results;
}

#
# Simple merge used when we have the exact same number of samples for each metric
# in the group
#
sub simpleGroupMerge($$) {
    my ($r_metrics, $metricsGroup) = @_;

    my $r_results = undef;
    while ( my ($metric,$r_seriesData) = each %{$r_metrics} ) {
        if ( ! defined $r_results ) {
            $r_results = makeGroupTimestamps($r_seriesData->{'timestamps'});
        }
        for ( my $index = 0; $index <= $#{$r_results}; $index++ ) {
            if ( $::DEBUG ) {
                if ( $r_results->[$index]->{'time'} != int($r_seriesData->{'timestamps'}->[$index]/1000) ) {
                    print "$metricsGroup $metric $index $r_results->[$index]->{'time'} $r_seriesData->{'timestamps'}->[$index]\n";
                }
            }
            $r_results->[$index]->{$metric} = $r_seriesData->{'values'}->[$index];
        }
    }

    return $r_results;
}

#
# When the metrics in the group have different numbers of samples then we have to use the timestamp of each
# sample to merge the metrics
#
sub timeGroupMerge($$) {
    my ($r_metrics, $r_sampleCounts) = @_;

    # First figure out which metric has the most samples, that's the one we'll use for the timestamps
    my @sortedMetricNames = sort { $r_sampleCounts->{$b} <=> $r_sampleCounts->{$a} } keys %{$r_sampleCounts};
    if ( $::DEBUG > 8 ) { print Dumper("timeGroupMerge: sortedMetricNames", \@sortedMetricNames); }
    my $r_results = makeGroupTimestamps($r_metrics->{$sortedMetricNames[0]}->{'timestamps'});
    my %metricIndex = ();
    foreach my $metricName ( @sortedMetricNames ) {
        $metricIndex{$metricName} = 0;
    }

    foreach my $r_aggregatedSample ( @{$r_results} ) {
        if ( $::DEBUG > 8 ) { print Dumper("timeGroupMerge: pre r_aggregatedSample", $r_aggregatedSample); }
        while ( my ($metric,$r_seriesData) = each %{$r_metrics} ) {
            my $keepGoing = 1;
            my $index = $metricIndex{$metric};
            my $r_metricTimestamps = $r_seriesData->{'timestamps'};
            if ( $::DEBUG > 8 ) { printf "timeGroupMerge: metric=%s #r_metricTimestamps=%d\n", $metric, $#{$r_metricTimestamps}; }
            while ( $keepGoing ) {
                # Check if we have run out of samples for this metric
                if ( $#{$r_metricTimestamps} < $index ) {
                    $keepGoing = 0;
                } else {
                    my $timeDelta = ($r_metricTimestamps->[$index]/1000) - $r_aggregatedSample->{'time'};
                    if ( $::DEBUG > 8 ) { printf "timeGroupMerge: timeDelta=%d\n", $timeDelta; }
                    if ( abs($timeDelta) < $Instr::MAX_TIME_DIFFERENCE ) {
                        $r_aggregatedSample->{$metric} = $r_seriesData->{'values'}->[$index];
                        $index++;
                        $keepGoing = 0;
                    } elsif ( $timeDelta < 0 ) {
                        # Metric sample is too old, try the next sample
                        $index++;
                    } else {
                        # Metric sample is ahead of aggregated sample so don't have
                        # a value for this metric to add to the aggreated sample so just
                        # move on
                        if ( $::DEBUG > 7 ) { printf "timeGroupMerge: missing value for metric=%s at %s\n", $metric, $r_aggregatedSample->{'timestamp'}; }
                        $keepGoing = 0;
                    }
                }
            }
            $metricIndex{$metric} = $index;
        }
        if ( $::DEBUG > 8 ) { print Dumper("timeGroupMerge: post r_aggregatedSample", $r_aggregatedSample); }
    }

    return $r_results;
}

#
# Calback function used by ModelInstr::processModel
#  We have to merge the sample for the metrics in the group
#
sub getMetricGroup($$) {
    my ($r_ts, $metricsGroup) = @_;

    my $getMetricGroupStartTime = Time::HiRes::time();

    if ( ! exists $::parseMetrics{'getMetricGroup.count'} ) {
        $::parseMetrics{'getMetricGroup.count'} = 0;
        $::parseMetrics{'getMetricGroup.timeTotal'} = 0;
        $::parseMetrics{'getMetricGroup.simple'} = 0;
    }
    $::parseMetrics{'getMetricGroup.count'}++;

    my $r_metrics = $r_ts->{$metricsGroup};
    if ( $::DEBUG > 9 ) { print Dumper("getMetricGroup: metricsGroup=$metricsGroup r_metrics", $r_metrics); }

    # First off, check that each series has the same number of entries
    my %sampleCount = ();
    my $sameSize = 1;
    my $size = undef;
    while ( my ($metric,$r_seriesData) = each %{$r_metrics} ) {
        my $metricSampleCount = $#{$r_seriesData->{'timestamps'}} + 1;
        $sampleCount{$metric} = $metricSampleCount;
        if ( $sameSize && defined $size ) {
            $sameSize = ($size == $metricSampleCount)
        } elsif ( ! defined $size ) {
            $size = $metricSampleCount
        }
    }

    my $r_results = undef;
    if ( $sameSize ) {
        $::parseMetrics{'getMetricGroup.simple'}++;
        $r_results = simpleGroupMerge($r_metrics, $metricsGroup);
    } else {
        if ( $::DEBUG ) {
            print "WARN: Inconsistent count for metrics in $metricsGroup\n";
            while ( my ($metric,$metricSampleCount) = each %sampleCount ) {
                printf " %4d %s\n", $metricSampleCount, $metric;
            }
        }
        $r_results = timeGroupMerge($r_metrics, \%sampleCount);
    }

    if ( $::DEBUG > 8 ) { print Dumper("getMetricGroup: results", $r_results); }

    $::parseMetrics{'getMetricGroup.timeTotal'} += Time::HiRes::time() - $getMetricGroupStartTime;

    return $r_results;
}


#
# We've found a metric group used by this model. This function checks
# that the service is supported for this model
#
sub modelActive($$$$$$) {
    my ($r_model, $server, $profileNameSpace, $r_ts, $sg, $r_hookModules) = @_;

    if ( $::DEBUG > 4 ) { print "modelActive: table=" . $r_model->{'table'}->{'name'} . "\n"; }

    my @services = ();
    if ( defined $sg ) {
        push @services, $sg;
        if ( $::DEBUG > 4 ) { print "modelActive: serviceGroup=$services[0]\n"; }
    }
    my ($r_modelInstance, $r_instanceMgByName) = ModelledInstr::getModelInstance(
        $r_model, \@services, $profileNameSpace, 60
    );
    if ( ! defined $r_modelInstance ) {
        return undef;
    }

    #
    # Now we interate through the time series, and look to see if any of
    # any have a match in the model (i.e is there any entry in instanceMgByName who's
    # key matches the metric group name)
    #
    foreach my $tsGroup ( keys %{$r_ts} ) {
        if ( $::DEBUG > 5 ) { print "modelActive: tsGroup \"$tsGroup\"\n"; }
        while ( my ($modelMgName,$r_modelMG) = each %{$r_instanceMgByName} ) {
            if ( $::DEBUG > 7 ) { print "modelActive:  checking modelMgName $modelMgName\n"; }

            if ( $tsGroup =~ /$modelMgName/ ) {
                if ( $::DEBUG > 5 ) { print "modelActive:   matched $tsGroup\n"; }
                $r_modelInstance->{'metricgroup'}->{$tsGroup} = $r_modelMG;
            } elsif ( exists $r_modelMG->{'providername'} ) {
                # In some cases the provider name name is different in the cfg file and
                # the instr file (i.e. for jvmgc), so check if the model sets the provider name
                # and if so use it
                # The only place this is currently used is in generic_jmx_stats.xml
                if ( $modelMgName =~ /^\^(\S+)\$$/ ) {
                    my $param = $1;
                    my $providerName = ModelledInstr::replaceParams(
                        $r_modelMG->{'providername'}, [ $param ]
                    );
                    if ( $::DEBUG > 6 ) { print "modelActive:    replacing $param in $r_modelMG->{'providername'} gives providername of $providerName\n"; }
                    if ( $tsGroup =~ /$providerName/ ) {
                        if ( $::DEBUG > 5 ) { print "modelActive:    overriding providername with $providerName\n"; }
                        $r_modelInstance->{'metricgroup'}->{$tsGroup} = $r_modelMG;
                    }
                }
            };
        }
    }

    # If we have matching metrics groups, then add this modelInstance to the modelsWithProfiles
    if ( %{$r_modelInstance->{'metricgroup'}} ) {
        my $r_instancesForServer = $r_model->{'instances'}->{$server};
        if ( ! defined $r_instancesForServer ) {
            $r_instancesForServer = [];
            $r_model->{'instances'}->{$server} = $r_instancesForServer;
        }
        push @{$r_instancesForServer}, $r_modelInstance;

        if ( exists $r_modelInstance->{'hooks'} ) {
            my $hookModule = $r_modelInstance->{'hooks'}->{'module'};
            if ( ! exists $r_hookModules->{$hookModule} ) {
                load $hookModule;
                $r_hookModules->{$hookModule} = $hookModule->new();
            }
            $r_modelInstance->{'hooks'}->{'instance'} = $r_hookModules->{$hookModule};
        }

        # Figure out if there can only be one provider (later this will tell us
        # if we need to run a group function)
        my $isSingleton = 0;
        if ( keys %{$r_model->{'metricgroup'}} == 1 ) {
            $isSingleton = 1;
        }
        $r_modelInstance->{'singleton'} = $isSingleton;

        if ( $::DEBUG > 4 ) { print Dumper("modelActive: modelInstance", $r_modelInstance); }

        return $r_modelInstance;
    } else {
        return undef;
    }
}


#
# Create the mapping hash used to map the pod where the metric orignated to the "pseudo" server
#
# key: pod name or IP address
# value: hash containing
#   srvname: Pseudo server hostname
#   srvid: Pseudo server hostname id (in servers table)
#   sg: Pods app or service group name (may not be present)
#
# Places we get mapping from
#
#  1. k8s_pods, servers, k8s_pod_app_names will give us the
#     mapped info for k8s pods keyed by mapped server name and podIP
#
#  2. enm_servicegroup_instances, enm_servicegroup_names, servers will give us the
#     mapped info for k8s pods keyed by mapped server name. This may overwrite the
#     mapping from 1. due to the service name/service group mapping need by ENM
#     We also handle non cENM deployments here
#
#  3. server_availability, servers
#
#  4. k8s_node, servers: This is used so we can add the nodes by IP address.
#
#
sub getInstanceMap($$$) {
    my ($dbh, $siteId, $date) = @_;

    my %instanceMap = ();

    my $r_rows = dbSelectAllHash($dbh, "
SELECT
 servers.hostname AS srvname,
 servers.id AS srvid,
 k8s_pod.pod AS podName,
 k8s_pod.podIP AS podIP,
 k8s_pod_app_names.name AS app
FROM k8s_pod
JOIN servers ON k8s_pod.serverid = servers.id
JOIN k8s_pod_app_names ON k8s_pod.appid = k8s_pod_app_names.id
WHERE
 k8s_pod.siteid = $siteId AND
 k8s_pod.date = '$date'
") or die "Select failed";
    foreach my $r_row ( @{$r_rows} ) {
        my %entry = (
            'srvname' => $r_row->{'srvname'},
            'srvid' => $r_row->{'srvid'},
            'sg' => $r_row->{'app'}
        );
        $instanceMap{$r_row->{'podName'}} = \%entry;
        if ( defined $r_row->{'podIP'} ) {
            $instanceMap{$r_row->{'podIP'}} = \%entry;
        } else {
            printf "WARN: Cannot add instanceMap entry for podIP for pod %s\n", $r_row->{'podName'};
        }
    }

    # Now use enm_servicegroup_instances to see if we need to override the app/sg
    # This is for cENM, the models have the service group name
    # but the app is set to the service name
    $r_rows = dbSelectAllHash($dbh,"
SELECT
 enm_servicegroup_instances.serverid AS srvid,
 enm_servicegroup_names.name AS sg,
 servers.hostname AS srvname
FROM enm_servicegroup_instances
JOIN enm_servicegroup_names ON enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
JOIN servers ON enm_servicegroup_instances.serverid = servers.id
WHERE
 enm_servicegroup_instances.siteid = $siteId AND
 enm_servicegroup_instances.date = '$date'
")  or die "Select failed";
    foreach my $r_row ( @{$r_rows} ) {
        my $foundMatch = 0;
        while ( my ($instanceName, $r_entry) = each %instanceMap ) {
            if ( $r_entry->{'srvid'} == $r_row->{'srvid'} && $r_entry->{'sg'} ne $r_row->{'sg'} ) {
                if ( $::DEBUG > 3 ) { printf "getInstanceMap: replacing sg %s with %s for %s\n", $r_entry->{'sg'}, $r_row->{'sg'}, $instanceName; }
                $r_entry->{'sg'} = $r_row->{'sg'};
                $foundMatch = 1;
            }
        }
        # If we didn't find a match, this is probably a non cENM deployment
        # So add a new entry with the hostname as the instance id
        if ( ! $foundMatch ) {
            $instanceMap{$r_row->{'srvname'}} = {
                'srvname' => $r_row->{'srvname'},
                'srvid' => $r_row->{'srvid'},
                'sg' => $r_row->{'sg'}
            }
        }
    }

    #Get servers from server_availability
    $r_rows = dbSelectAllHash($dbh, "
SELECT
    serverid AS srvid,
    servers.hostname AS srvname
FROM
    server_availability,
    servers
WHERE
    server_availability.siteid = $siteId AND
    server_availability.serverid = servers.id AND
    server_availability.date = '$date'") or die "Select failed";
    foreach my $r_row ( @{$r_rows} ) {
        if ( ! exists $instanceMap{$r_row->{'srvname'}} ) {
            $instanceMap{$r_row->{'srvname'}} = {
                'srvname' => $r_row->{'srvname'},
                'srvid' => $r_row->{'srvid'}
            }
        }
    }

    # Add in k8 nodes by IP
    $r_rows = dbSelectAllHash($dbh, "
SELECT
    servers.hostname AS srvname,
    k8s_node.intIP AS intIP,
    servers.id AS srvid
FROM
    k8s_node, servers
WHERE
    k8s_node.siteid = $siteId AND
    k8s_node.date = '$date' AND
    k8s_node.serverid = servers.id") or die "Select failed";

    foreach my $r_row ( @{$r_rows} ) {
        if ( ! defined $instanceMap{$r_row->{'srvname'}} ) {
            $instanceMap{$r_row->{'srvname'}} = {
                'srvname' => $r_row->{'srvname'},
                'srvid' => $r_row->{'srvid'}
            }
        }
        $instanceMap{$r_row->{'intIP'}} = $instanceMap{$r_row->{'srvname'}};
    }

    if ( $::DEBUG > 3 ) { print Dumper("getInstanceMap: instanceMap", \%instanceMap); }
    return \%instanceMap;
}

sub getInstanceName($$) {
    my ($r_labels, $r_labelsToCheck) = @_;

    my $instanceName = PromCommon::getLabelValue($r_labels, $r_labelsToCheck);
    # If we couldn't get the pod name then return the IP address/hostname part of the instance label
    if ( ! defined $instanceName ) {
        $instanceName = $r_labels->{'instance'};
        $instanceName =~ s/:\d+$//;
    }

    return $instanceName;
}

sub setInstanceInfo($$$) {
    my ($r_metricsByInstance, $instanceName, $r_labels) = @_;

    if ( ! exists $r_metricsByInstance->{$instanceName}->{'info'} ) {
        if ( $::DEBUG > 10 ) { print Dumper("setInstanceInfo: instanceName=$instanceName r_labels", $r_labels); }

        my %info = ();
        foreach my $label ( 'instance', 'kubernetes_pod_name', 'app', 'pod', 'service' ) {
            my $value = $r_labels->{$label};
            if ( defined $value ) {
                $info{$label} = $value;
            }
        };
        if ( $::DEBUG > 9 ) { print Dumper("setInstanceInfo: instanceName=$instanceName info", \%info); }

        $r_metricsByInstance->{$instanceName}->{'info'} = \%info;
    }
}

sub mapBoolean($) {
    my ($r_models) = @_;

    foreach my $r_model ( @{$r_models} ) {
        while ( my ($key, $r_metricGroup) = each %{$r_model->{'metricgroup'}} ) {
            foreach my $r_metric ( @{$r_metricGroup->{'metric'}} ) {
                if ( exists $r_metric->{'filtervalue'} && $r_metric->{'filtervalue'} eq 'true' ) {
                    if ( $::DEBUG > 5 ) { printf("mapBoolean: Updating filtervalue for %s in %s\n", $r_metric->{'source'}, $key); }
                    $r_metric->{'filtervalue'} = '1';
                }
            }
        }
    }
}

sub getActiveModels($$$$) {
    my ($r_metricsByInstance, $r_instanceMap, $r_models, $r_tsForModelInstance) = @_;

    my %hooksModules = ();
    while ( my ($instance,$r_instanceData) = each %{$r_metricsByInstance} ) {
        if ( $::DEBUG > 10 )  { print Dumper("getActiveModels: instance=$instance instanceData", $r_instanceData); }

        my $r_srvInstance = PromCommon::getInstance($instance, $r_instanceMap);
        if ( ! defined $r_srvInstance ) {
            print "INFO: Skipping instance $instance\n";
            next;
        }

        if ( ! exists $r_instanceData->{'metrics'} ) {
            if ( $::DEBUG ) { print "getActiveModels: skipping $instance, no metrics\n"; }
            next;
        }

        my $serviceGroup = $r_srvInstance->{'sg'};
        if ( $::DEBUG > 3 )  {
            my @metricKeys = keys %{$r_instanceData->{'metrics'}};
            print "getActiveModels:instance=$instance metrics serviceGroup=",
                (defined $serviceGroup ? $serviceGroup : "undef"),
                ", keys=", join(",", @metricKeys), "\n";
        }

        #
        # Special handling for node_exporter and cadvisor metrics
        #
        if ( exists $r_instanceData->{'metrics'}->{'node_exporter'} ) {
            NodeExporter::process($r_instanceData->{'metrics'}->{'node_exporter'});
        }
        if ( exists $r_instanceData->{'metrics'}->{'cadvisor'} ) {
            $r_instanceData->{'metrics'}->{'cadvisor_pod'} = {
                'POD' => CAdvisor::getPod($r_instanceData->{'metrics'}->{'cadvisor'})
            };
        }

        while ( my ($profileNameSpace,$r_ts) = each %{$r_instanceData->{'metrics'}} ) {
            if ( $::DEBUG > 3 ) { print "getActiveModels:checking $instance profileNameSpace=$profileNameSpace\n"; }
            foreach my $r_model ( @{$r_models} ) {
                my $modelNamespace = $r_model->{'namespace'};
                if ( $::DEBUG > 10 ) { print "getActiveModels: checking modelNamespace=$modelNamespace\n"; }
                if ( $profileNameSpace =~ /$modelNamespace/ ) {
                    if ( $::DEBUG > 3 ) { print "getActiveModels: matched $profileNameSpace with $modelNamespace serviceGroup=", (defined $serviceGroup ? $serviceGroup : "undef"), "\n"; }
                    my $r_modelInstance =
                        modelActive(
                            $r_model,$instance, $profileNameSpace,
                            $r_ts, $serviceGroup,
                            \%hooksModules
                        );
                    if ( defined $r_modelInstance ) {
                        $r_tsForModelInstance->{"$r_modelInstance"} = $r_ts;

                        if ( $::DEBUG ) {
                            printf("getActiveModels: processing instance=%s, server=%s, serviceGroup=%s, profileNameSpace=%s, modelNamespace=%s, table=%s\n",
                                   $instance, $r_srvInstance->{'srvname'}, (defined $serviceGroup ? $serviceGroup : "undef"),
                                   $profileNameSpace, $modelNamespace, $r_model->{'table'}->{'name'}
                                );
                        }
                    }
                }
            }
        }
    }
}

sub processModelInstances($$$$$$) {
    my ($r_model, $r_tsForModelInstance, $r_instanceMap, $r_cliArgs, $dbh, $r_incrData) = @_;

    my $modelStartTime = Time::HiRes::time();

    my $metricTotalsKey = "processModel._totals.";
    my $metricModelKey = "processModel." . $r_model->{'table'}->{'name'} . ".";
    if ( ! exists $parseMetrics{$metricTotalsKey . 'count'} ) {
        $parseMetrics{$metricTotalsKey . 'count'} = 0;
        $parseMetrics{$metricTotalsKey . 'duration'} = 0;
        $parseMetrics{$metricTotalsKey . 'dataSets'} = 0;
        $parseMetrics{$metricTotalsKey . 'timeStore'} = 0;
        $parseMetrics{$metricTotalsKey . 'timeProcessMetricGroup'} = 0;
        $parseMetrics{$metricTotalsKey . 'countProcessMetricGroup'} = 0;
        $parseMetrics{$metricTotalsKey . 'timeProcessDataSet'} = 0;
    }

    # Use a foreach on sorted keys cause we're modifying instances which causes problems for each
    foreach my $instance ( sort keys %{$r_model->{'instances'}} ) {
        my $r_modelInstances = $r_model->{'instances'}->{$instance};
        my $r_srvInstance = PromCommon::getInstance($instance, $r_instanceMap);
        my $r_instanceArgs = dclone($r_cliArgs);
        $r_instanceArgs->{'server'} = $r_srvInstance->{'srvname'};
        $r_instanceArgs->{'serverid'} = $r_srvInstance->{'srvid'};
        if ( exists $r_srvInstance->{'sg'} ) {
            $r_instanceArgs->{'sg'} = $r_srvInstance->{'sg'};
        }

        # We need to update the model instance here because in modelActive, we updated the
        # model->instances using the pod name but when we call processModel we're going to
        # pass it the mapped name, so we need to update model->instances to have the
        # mapped name instead of the pod name
        $r_model->{'instances'}->{$r_srvInstance->{'srvname'}} =
            delete $r_model->{'instances'}->{$instance};

        # Note there can be multiple model instances for a given server (e.g multiple JVMs in a server)
        foreach my $r_modelInstance ( @{$r_modelInstances} ) {
            $parseMetrics{$metricTotalsKey . 'count'}++;

            my $r_ts = $r_tsForModelInstance->{"$r_modelInstance"};
            defined $r_ts or die "Could not get timeseries for modelInstance";
            StatsCommon::debugMsg(1, "Processing model instance for %s", $instance);
            my $r_metrics = ModelledInstr::processModel(
                $r_modelInstance,
                $r_ts, \&getMetricGroup,
                $r_instanceArgs, $dbh, $r_incrData
            );

            StatsCommon::debugMsgWithObj(5, $r_metrics, "processModel: metrics");

            my $timeProcessMetricGroup = $r_metrics->{'t_end_mg'} - $r_metrics->{'t_start_mg'};
            my $timeProcessDataSet = $r_metrics->{'t_end_process'} - $r_metrics->{'t_end_mg'};
            my $timeStore = 0;
            if ( exists $r_metrics->{'t_end_store'} ) {
                $timeStore = $r_metrics->{'t_end_store'} - $r_metrics->{'t_start_store'};
            }
            my $timeOverall = $r_metrics->{'t_end'} - $r_metrics->{'t_start'};

            $parseMetrics{$metricTotalsKey . 'timeProcessMetricGroup'} += $timeProcessMetricGroup;
            $parseMetrics{$metricTotalsKey . 'countProcessMetricGroup'} += $r_metrics->{'n_mg'};
            $parseMetrics{$metricTotalsKey . 'timeProcessDataSet'} += $timeProcessDataSet;
            $parseMetrics{$metricTotalsKey . 'dataSets'} += $r_metrics->{'n_datasets'};
            $parseMetrics{$metricTotalsKey . 'timeStore'} += $timeStore;
            $parseMetrics{$metricTotalsKey . 'duration'} += $timeOverall;
            if ( exists $r_metrics->{'instr_store'} ) {
                $parseMetrics{$metricTotalsKey . 'instr_store'} += $r_metrics->{'instr_store'};
                $parseMetrics{$metricTotalsKey . 'instr_delete'} += $r_metrics->{'instr_delete'};
                $parseMetrics{$metricTotalsKey . 'instr'} += $r_metrics->{'instr'};
            }
            if ( $::DEBUG ) {
                $parseMetrics{$metricModelKey . 'timeProcessMetricGroup'} += $timeProcessMetricGroup;
                $parseMetrics{$metricModelKey . 'countProcessMetricGroup'} += $r_metrics->{'n_mg'};
                $parseMetrics{$metricModelKey . 'timeProcessDataSet'} += $timeProcessDataSet;
                $parseMetrics{$metricModelKey . 'dataSets'} += $r_metrics->{'n_datasets'};
                $parseMetrics{$metricModelKey . 'timeStore'} += $timeStore;
                $parseMetrics{$metricModelKey . 'duration'} += $timeOverall;
                if ( exists $r_metrics->{'instr_store'} ) {
                    $parseMetrics{$metricModelKey . 'instr_store'} += $r_metrics->{'instr_store'};
                    $parseMetrics{$metricModelKey . 'instr_delete'} += $r_metrics->{'instr_delete'};
                    $parseMetrics{$metricModelKey . 'instr'} += $r_metrics->{'instr'};
                }
            }
        }
    }

    my $modelDuration = Time::HiRes::time() - $modelStartTime;
    if ( $::DEBUG ) { $parseMetrics{'processModel..' . $r_model->{'table'}->{'name'}} = $modelDuration; }
}

sub main() {
    my ($site, $dataDir, @modelPaths, $incrFile, $date,$k8sNamespace, $externalEndPoint,$disabledModels);
    my @services = ();
    my $deleteRequired = 1;
    my $result = GetOptions(
        "site=s"    => \$site,
        "data=s"    => \$dataDir,
        "model=s"   => \@modelPaths,
        "date=s"    => \$date,
        "incr=s"    => \$incrFile,
        "k8snamespace=s" => \$k8sNamespace,
        "external=s" => \$externalEndPoint,
        "disabled=s" => \$disabledModels,
        "deleterequired=s" => \$deleteRequired,
        "debug=s"   => \$::DEBUG
    );
    ($result == 1) or die "Invalid options";

    StatsCommon::logMsg("Starting");

    my $mainStartTime = Time::HiRes::time();
    setStatsDB_Debug($::DEBUG);
    setInstr_Debug($::DEBUG);
    $Data::Dumper::Indent = 1;

    my $dbh = connect_db();

    my $siteId = getSiteId( $dbh, $site );
    ($siteId > -1 ) or die "Failed to get siteid for $site";

    my %cliArgs = (
        'site' => $site,
        'siteid' => $siteId,
        'date' => $date,
        'usetimestamp' => 1,
        'deleterequired' => $deleteRequired
    );

    # We expect samples every 60 seconds, so we if we're off by
    # more then 30, then align to the next sample
    $Instr::MAX_TIME_DIFFERENCE = 30;

    my $r_incrData = incrRead( $incrFile );
    $r_incrData = validateIncr($r_incrData);

    my $r_instanceMap = getInstanceMap($dbh, $siteId, $date);

    # Load the list of known models
    StatsCommon::logMsg("Loading models");
    my $r_models = ModelledInstr::loadModels(\@modelPaths, $disabledModels);
    mapBoolean($r_models);

    my $r_prometheusMetrics = getPrometheusMetrics($r_models);

    # Load the metrics
    my $r_metricsByInstance = loadMetrics($dataDir, $r_incrData, $r_instanceMap, $r_prometheusMetrics, $k8sNamespace, $externalEndPoint, $site);

    # Figure out which models we have data for
    my %tsForModelInstance = ();
    getActiveModels($r_metricsByInstance, $r_instanceMap, $r_models, \%tsForModelInstance);

    foreach my $r_model ( @{$r_models} ) {
        if ( %{$r_model->{'instances'}} ) {
            my $numInstances = keys %{$r_model->{'instances'}};
            StatsCommon::logMsg("INFO: Processing model %s with %d instances", $r_model->{'table'}->{'name'}, $numInstances);
            processModelInstances($r_model, \%tsForModelInstance, $r_instanceMap, \%cliArgs, $dbh, $r_incrData);
        }
    }

    $dbh->disconnect();

    incrWrite( $incrFile, $r_incrData );

    $parseMetrics{'main.totalTime'} = Time::HiRes::time() - $mainStartTime;
    foreach my $metricName ( sort keys %parseMetrics ) {
        printf("%4d %s \n", $parseMetrics{$metricName}, $metricName);
    }
}

1;
