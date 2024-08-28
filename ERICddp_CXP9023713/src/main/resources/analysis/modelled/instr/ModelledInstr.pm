package ModelledInstr;

use warnings;
use strict;

use Instr;
use DataStore;
use StatsTime;

use File::Basename;
use XML::LibXML;
use Storable qw(dclone);
use Data::Dumper;
use JSON;

our $SINGLE = 'single';

sub loadModels($$) {
    my ($r_modelPaths, $disabledModelsPath) = @_;

    my $r_models = [];
    my $dirname = dirname(__FILE__);
    my $xsd_doc = XML::LibXML::Schema->new(location => $dirname . "/models/modelledinstr.xsd");

    my $r_disabledModels = getDisabledModels($disabledModelsPath);

    # Normally modelDir is a direcory but we can pass it a file
    # to use only one model file (for debugging)
    foreach my $modelDir ( @{$r_modelPaths} ) {
        if ( -d $modelDir ) {
            getModels($modelDir,$r_models, $xsd_doc, $r_disabledModels);
        } else {
            push @{$r_models}, processModelFile($modelDir,$xsd_doc);
        }
    }

    if ( $::DEBUG > 7 ) { print Dumper("loadModels: r_models", $r_models); }
    return $r_models;
}

#
# Process the data extracted from the instr file for a model instance
#
sub processModel($$$$$$) {
    my ($r_model,
        $r_data, $r_getMgFn,
        $r_cliArgs,
        $dbh,
        $r_incrData
    ) = @_;

    my %metrics = ('t_start' => Time::HiRes::time());

    if ( $::DEBUG > 8 ) { print Dumper("processModel: r_model",$r_model); }
    if ( $::DEBUG > 4 ) { print "processModel: table=" . $r_model->{'table'}->{'name'} . "\n"; }
    if ( $::DEBUG > 9 ) { print Dumper("processModel: r_data",$r_data); }

    my $r_multiId = $r_model->{'multi'};

    my $r_groupFunc = undef;
    if ( $r_model->{'singleton'} == 0 ) {
        $r_groupFunc = \&instrJoinMetrics;
    }

    # Data from each provider, grouped by multiId
    # If it's not a multi-instance, then the key is 'single'
    my %modelData = ();

    # Properties grouped by multiId
    my %propertyMap = ();

    my %deltaMap = ();
    my %rateMap = ();
    my %scaleMap = ();
    my %filterIdleMap = ();
    my %filterStaticMap = ();
    my %filterValueMap = ();
    my %columnMap = ();

    my $useTimeStamp = 0;
    if ( exists $r_cliArgs->{'usetimestamp'} ) {
        $useTimeStamp = 1;
    }

    #
    # Loop through the metric groups in the model and
    # - Get the data from the corresponding provider
    # -
    $metrics{'t_start_mg'} = Time::HiRes::time();
    $metrics{'n_mg'} = 0;
    while ( my ($metricGroupName,$r_modelMetricGroup) = each %{$r_model->{'metricgroup'}} ) {
        if ( $::DEBUG > 5 ) { print "processModel: metricGroupName=$metricGroupName\n"; }
        if ( $::DEBUG > 8 ) { print Dumper("processModel: r_modelMetricGroup", $r_modelMetricGroup); }
        my $r_mgData = $r_getMgFn->($r_data, $metricGroupName);
        if ( $#{$r_mgData} == -1 ) {
            if ( $::DEBUG > 4 ) { print "processModel: zero samples for $metricGroupName\n"; }
            next;
        }
        $metrics{'n_mg'}++;

        my $sourceMgName = processMetricGroupProperties($r_model->{'matched_service'}, $metricGroupName, $r_modelMetricGroup, $r_multiId,
                                                        \%propertyMap, \%modelData, $r_mgData);

        processMetricGroupMetrics( $r_modelMetricGroup->{'metric'},
                                   $r_model->{'singleton'} == 0, $sourceMgName,
                                   \%columnMap, \%deltaMap, \%filterIdleMap, \%filterStaticMap,
                                   \%scaleMap, \%filterValueMap, \%rateMap );

    }
    $metrics{'t_end_mg'} = Time::HiRes::time();

    if ( $::DEBUG > 4 ) { print "processModel: have data for the keys " . join(",",keys %modelData) . "\n"; }
    if ( $::DEBUG > 9 ) { print Dumper("processModel: modelData", \%modelData); }

    my @deltaCols = keys %deltaMap;
    my @rateCols = keys %rateMap;
    my @filterIdleCols = keys %filterIdleMap;
    my @filterStaticCols = keys %filterStaticMap;

    my @dataSets = ();

    while ( my ($key,$r_modelData) = each %modelData ) {
        my $r_samples = undef;
        if ( defined $r_groupFunc ) {
            $r_samples = instrGroupDataFunc($r_modelData,$r_groupFunc);
        } else {
            # If there's no group function
            my @sourceMgNames = keys %{$r_modelData};
            $#sourceMgNames == 0 or die "Invalid number of providers for $key for " . $r_model->{'table'}->{'name'};
            $r_samples = $r_modelData->{$sourceMgNames[0]};
            # If the samples aren't grouped, then they don't have a 'time' field
            # so we add the time field here
            foreach my $r_sample ( @{$r_samples} ) {
                $r_sample->{'time'} = instrGetTime($r_sample);
            }
        }
        if ( $#{$r_samples} == -1 ) {
            next;
        }

        #
        # We need to pass processDataSet a unique key so use when accessing the incr data
        # We can construct this using the model name, table name which is unique per model and the key
        #
        my $incrKey = sprintf(
            "%s,%s,%s,%s",
            $r_model->{'name'},
            $r_model->{'table'}->{'name'},
            ( defined $r_cliArgs->{'server'} ? $r_cliArgs->{'server'} : "NA" ),
            $key
        );
        my $r_processedSamples = processDataSet($r_samples, $r_incrData, $incrKey, $r_cliArgs, $r_model, $dbh,
                                                \@deltaCols, \@rateCols, \@filterIdleCols, \@filterStaticCols, \%scaleMap, \%filterValueMap, \%columnMap);
        if ( $#{$r_processedSamples} > -1 ) {
            my $r_dataSet = { 'samples' => $r_processedSamples, 'properties' => $propertyMap{$key} };
            if ( $useTimeStamp ) {
                $r_dataSet->{'property_options'} = {'time' => {'usetimestamp' => 1 }};
            }
            push @dataSets, $r_dataSet;
        }
    }
    $metrics{'t_end_process'} = Time::HiRes::time();
    $metrics{'n_datasets'} += $#dataSets + 1;

    if ( $#dataSets > -1 ) {
        my $r_storeDataSets = \@dataSets;
        # A hook may want to override what's in cliArgs
        # so create a clone so it has it's own copy that it's
        # free to modify
        my $r_storeCliArgs = dclone($r_cliArgs);
        # Provide access to incr data by adding it with the key _incr
        $r_storeCliArgs->{'_incr'} = $r_incrData;
        if ( exists $r_model->{'hooks'}->{'prestore'} ) {
            $r_storeDataSets = $r_model->{'hooks'}->{'instance'}->prestore(
                $r_storeCliArgs,
                $dbh,
                $r_model,
                $r_storeDataSets,
                \%columnMap
            );
        }

        if ( $#{$r_storeDataSets} > -1 ) {
            my %propertyValues = (
                'site' => $r_storeCliArgs->{'site'},
                'server' => $r_storeCliArgs->{'server'},
                'siteid' => $r_storeCliArgs->{'siteid'},
                'serverid' => $r_storeCliArgs->{'serverid'}
                );
            if ( exists $r_model->{'prototype'} && $r_model->{'prototype'} == 1 ) {
                $propertyValues{'store_statsdb'} = 0;
            }

            if ( exists $r_storeCliArgs->{'deleterequired'} && $r_storeCliArgs->{'deleterequired'} == 0 ) {
                $propertyValues{'incremental'} = 1;
            }

            if ( exists $r_model->{'table'}->{'timecol'} ) {
                $columnMap{'time'} = $r_model->{'table'}->{'timecol'};
            }
            $metrics{'t_start_store'} = Time::HiRes::time();
            my $r_dataStoreMetrics = DataStore::storePeriodicDataWithOpts(
                $r_model->{'period'},
                $r_model->{'table'},
                $r_model->{'multi'},
                $r_model->{'matched_service'},
                \%propertyValues,
                \%columnMap,
                $r_storeDataSets,
                { 'dbh' => $dbh }
                );
            $metrics{'t_end_store'} = Time::HiRes::time();
            $metrics{'instr_store'} = $r_dataStoreMetrics->{'instr_store'};
            $metrics{'instr_delete'} = $r_dataStoreMetrics->{'instr_delete'};
            $metrics{'instr'} = $r_dataStoreMetrics->{'instr'};
        } else {
            print "WARN: prestore dropped all datasets\n";
        }
    }

    $metrics{'t_end'} = Time::HiRes::time();
    return \%metrics;
}

sub getModelInstance ($$$$) {
    my ($r_model, $r_services, $profileNameSpace, $period) = @_;

    # If the model has a services element then check
    # that the service group for this server is in the
    # services list
    my $matched_service = undef;
    if ( exists $r_model->{'services'} ) {
        foreach my $service ( @{$r_services} ) {
            if ( exists $r_model->{'services'}->{$service} ) {
                $matched_service = $service;
            }
        }
        if ( ! defined $matched_service ) {
            # For services running on phyiscal, service may not not defined
            # To cater for this, the model must include NONE in the services list
            if ( ! exists $r_model->{'services'}->{"NONE"} ) {
                if ( $::DEBUG > 3 ) { print "getModelInstance: service not in services list: " .  join(",",keys %{$r_model->{'services'}}) . "\n"; }
                return (undef, undef);
            }
        }
    }

    #
    # If we haven't already matched a specific service, then use the first
    # service (which assumes that the first service is the JBoss one)
    #
    if ( (!defined $matched_service) && ($#{$r_services} >= 0) ) {
        $matched_service = $r_services->[0];
    }

    # The namespace can contain a regex that we extract params for
    # These params are reused to replace variables in the metricgroup names
    # e.g. %1% will be replaced by the first param
    my @params = $profileNameSpace =~ $r_model->{'namespace'};
    if ( $::DEBUG > 5 ) {
        printf("getModelInstance: table=%s  matched_service=%s  params=[%s]\n",
               $r_model->{'table'}->{'name'},
               (defined $matched_service ? $matched_service : "undef"),
               join(",",@params)
           );
    }

    #
    # modelInstance represents an instance of this model updated to
    # include the matching providers we found defined on the cfg files
    #
    # Parameters (%x%) in the metricgroup names are substituted with
    # the values taken from the namespace pattern
    #
    my %modelInstance = (
        'name' => $profileNameSpace,
        'table' => $r_model->{'table'},
        'modeldef' => $r_model,
        'period' => $period,
        'metricgroup' => {}
    );
    foreach my $copyKey ( 'hooks', 'multi', 'prototype', 'sampleinterval', 'instnamelabel' ) {
        if ( exists $r_model->{$copyKey} ) {
            $modelInstance{$copyKey} = $r_model->{$copyKey};
        }
    }
    if ( defined $matched_service ) {
        $modelInstance{'matched_service'} = $matched_service;
    }

    my %instanceMgByName = ();
    while ( my ($modelMgName,$r_modelMG) = each %{$r_model->{'metricgroup'}} ) {
        my $instMgName = replaceParams($modelMgName,\@params);
        #
        # The entries in metrcgroup need to have a property set that
        # is the pattern used to match this metric group (used later for
        # multi instance metrcgroups to extract the instance name). So
        # we need to copy the metric group hash to that this model instance
        # can have it's own value for this pattern
        #
        $instanceMgByName{$instMgName} = { %$r_modelMG };
        $instanceMgByName{$instMgName}->{'instance_pattern'} = $instMgName;
    }
    if ( $::DEBUG > 5 ) { print Dumper("getModelInstance:  instanceMgByName", \%instanceMgByName); }

    return (\%modelInstance, \%instanceMgByName);
}

sub replaceParams($$) {
    my ($str,$r_params) = @_;

    for (my $index = 0; $index <= $#{$r_params}; $index++ ) {
        my $paramId = $index + 1;
        $str =~ s/%$paramId%/$r_params->[$index]/g;
    }

    return $str;
}

#
# Internal functions
#
sub processModelFile($$) {
    my ($modelFilePath,$xsd_doc) = @_;

    my $dom = XML::LibXML->load_xml(location => $modelFilePath, XML_LIBXML_LINENUMBERS => 1);
    $xsd_doc->validate($dom) == 0 or die "Validation failed for $modelFilePath";

    my $modelledInstr = $dom->findnodes("/modelledinstr")->[0];
    my %model = (
        'namespace' => $modelledInstr->findvalue('@namespace'),
        'instances' => {}
    );

    my $sampleInterval = $modelledInstr->findvalue('@sampleinterval');
    if ( $sampleInterval ne '') {
        $model{'sampleinterval'} = $sampleInterval;
    }

    my $instanceNameLabel = $modelledInstr->findvalue('@instnamelabel');
    if ( $instanceNameLabel ne '') {
        $model{'instnamelabel'} = $instanceNameLabel;
    }

    if ( $modelFilePath =~ /\/prototype\// ) {
        $model{'prototype'} = 1;
    }

    my $r_table = $modelledInstr->findnodes("table")->[0];
    my %table = (
        'name' => $r_table->findvalue('@name'),
        'keycol' => []
    );
    foreach my $r_keycol ( $r_table->findnodes("keycol") ) {
        my $refnamecol = $r_keycol->getAttribute('refnamecol');
        if ( (! defined $refnamecol) || ($refnamecol eq '') ) {
            $refnamecol = 'name';
        }
        my %keyColumn = (
            'name' => $r_keycol->getAttribute('name'),
            'reftable' => $r_keycol->getAttribute('reftable'),
            'refnamecol' => $refnamecol
        );
        my $reffiltercol = $r_keycol->getAttribute('reffiltercol');
        if ( defined $reffiltercol ) {
            $keyColumn{'reffiltercol'} = $reffiltercol;
        }

        push @{$table{'keycol'}}, \%keyColumn;
    }
    my $timecol =  $r_table->findvalue('@timecol');
    if ( defined $timecol && $timecol ne '' ) {
        $table{'timecol'} = $timecol;
    }

    $model{'table'} = \%table;

    my $r_metricGroups = $modelledInstr->findnodes("metricgroups")->pop();

    my @multiIds = ();
    foreach my $r_multi ( $r_metricGroups->findnodes("multi") ) {
        push @multiIds, $r_multi->to_literal();
    }
    if ( $#multiIds > -1 ) {
        $model{'multi'} = \@multiIds;
    }

    my %metricGroupsByName = ();
    foreach my $r_metricGroup ( $modelledInstr->findnodes("metricgroups/metricgroup") ) {
        my $name = $r_metricGroup->findvalue('@name');
        my @metrics = ();
        foreach my $r_metric ( $r_metricGroup->findnodes("metric") ) {
            my %metric = (
                'source' => $r_metric->findvalue('@source'),
                'target' => $r_metric->findvalue('@target')
                );

            foreach my $name ( 'delta', 'filteridle', 'filterstatic', 'scale', 'filtervalue', 'store', 'rate' ) {
                my $value = $r_metric->findvalue('@' . $name);
                if ( defined $value && $value ne '') {
                    $metric{$name} = $value;
                }
            }

            # Prometheus specific stuff here
            my @labelfilters = ();
            foreach my $r_labelfilter ( $r_metric->findnodes("labelfilter") ) {
                push @labelfilters, {
                    'name' => $r_labelfilter->findvalue('@name'),
                    'value' => $r_labelfilter->findvalue('@value')
                };
            }
            if ( @labelfilters ) {
                $metric{'labelfilters'} = \@labelfilters;
            }

            push @metrics, \%metric;
        }
        $metricGroupsByName{$name} = {
            'name' => $name,
            'metric' => \@metrics
        };

        my @properties = ();
        foreach my $r_property ( $r_metricGroup->findnodes("property") ) {
            my %property = (
                'name' => $r_property->getAttribute('name'),
                'type' => $r_property->getAttribute("xsi:type")
            );
            if ( $property{'type'} eq 'fixedproperty' ) {
                $property{'value'} = $r_property->getAttribute('value');
            } elsif ( $property{'type'} eq 'nameproperty' ) {
                $property{'index'} = $r_property->getAttribute('index');
            } elsif ( $property{'type'} eq 'multilabelproperty' ) {
                $property{'label'} = $r_property->getAttribute('label');
                $property{'index'} = $r_property->getAttribute('index');
                if ( defined $r_property->getAttribute('filtervalue') ) {
                    $property{'filtervalue'} = $r_property->getAttribute('filtervalue');
                }
                $property{'optional'} = 0;
                if ( defined $r_property->getAttribute('optional') && $r_property->getAttribute('optional') eq 'true' ) {
                    $property{'optional'} = 1;
                }
                $property{'addtogroup'} = 'auto';
                if ( defined $r_property->getAttribute('addtogroup') ) {
                    $property{'addtogroup'} = $r_property->getAttribute('addtogroup');
                }
            }

            push @properties, \%property;
        }
        if ( $#properties > -1 ) {
            $metricGroupsByName{$name}->{'property'} = \@properties;
        }

        my $providerName = $r_metricGroup->getAttribute('providername');
        if ( defined $providerName && $providerName ne '' ) {
            $metricGroupsByName{$name}->{'providername'} = $providerName;
        }
    }
    $model{'metricgroup'} = \%metricGroupsByName;


    my $r_hooks = $modelledInstr->findnodes("hooks")->pop();
    if ( defined $r_hooks ) {
        my %hooks = (
            'module' => $r_hooks->getAttribute('module')
            );
        foreach my $r_hook ( $r_hooks->findnodes("hook") ) {
            $hooks{$r_hook->to_literal()} = 1;
        }
        $model{'hooks'} = \%hooks;
    }

    my %services = ();
    foreach my $r_service ( $modelledInstr->findnodes("services/service") ) {
        $services{$r_service->getAttribute("name")} = 1;
    }
    if ( %services ) {
        $model{'services'} = \%services;
    }

    my %blacklist = ();
    foreach my $r_service ( $modelledInstr->findnodes("blacklist/service") ) {
        $blacklist{$r_service->getAttribute("name")} = 1;
    }
    if ( %blacklist ) {
        $model{'blacklist'} = \%blacklist;
    }

    return \%model;
}

sub getDisabledModels($) {
    my ($filePath) = @_;

    my $r_disabledModels = [];
    if ( defined $filePath ) {
        open INPUT, $filePath or die "Cannot open $filePath";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        $r_disabledModels = decode_json($file_content);
    }

    return $r_disabledModels;
}

sub isModelDisabled($$) {
    my ($modelPath, $r_disabledModels) = @_;

    foreach my $pattern ( @{$r_disabledModels} ) {
        if ( $modelPath =~ /$pattern/ ) {
            return 1;
        }
    }

    return 0;
}

sub getModels {
    my ($modelDir,$r_models, $xsd_doc, $r_disabledModels) = @_;

    my @modelFiles = ();
    opendir(my $dh, $modelDir) or die "Cannot open $modelDir";
    foreach my $file (readdir($dh)) {
        if ( $file !~ /^\./ ) {
            my $path = $modelDir . "/" . $file;
            if ( ($path =~ /\.xml$/) && (! isModelDisabled($path, $r_disabledModels)) ) {
                push @modelFiles, $path;
            } elsif ( -d $path ) {
                getModels($path, $r_models, $xsd_doc);
            }
        }
    }
    closedir($dh);

    if ( $::DEBUG > 3 ) { print Dumper("getModels: modelFiles", \@modelFiles); }
    foreach my $modelFilePath ( @modelFiles ) {
        push @{$r_models}, processModelFile($modelFilePath,$xsd_doc);
    }
}

sub applySampleInterval($$$$) {
    my ($r_inSamples, $sampleInterval, $prevSampleTime, $date) = @_;
    if ( $::DEBUG > 5 ) {
        printf(
            "applySampleInterval: sampleInterval=%d, prevSampleTime=%s, #r_inSamples=%d\n",
            $sampleInterval,
            (defined $prevSampleTime ? $prevSampleTime : "undef"),
            $#{$r_inSamples}
        );
    }

    # If this is the first time we're being called
    # then we need to make sure that we only consider samples
    # for the current day. So we set prevSampleTime = day start - sampleInterval
    # that way we only consider samples that are >
    # (day start - sampleInterval) + sampleInterval = day start
    if ( ! defined $prevSampleTime ) {
        $prevSampleTime =
            parseTime("$date 00:00:00", $StatsTime::TIME_SQL, $StatsTime::TZ_SITE) - $sampleInterval;
        if ( $::DEBUG > 5 ) {
            printf(
                "applySampleInterval: using %d (%s) for prevSampleTime\n",
                $prevSampleTime,
                formatSiteTime($prevSampleTime, $StatsTime::TIME_SQL)
            );
        }
    }

    my @outSamples = ();
    foreach my $r_sample ( @{$r_inSamples} ) {
        if ( $::DEBUG > 6 ) { printf("applySampleInterval: r_sample time=%d, prevSampleTime=%d\n", $r_sample->{'time'}, $prevSampleTime); }
        if ( ($r_sample->{'time'} - $prevSampleTime) >= ($sampleInterval - 1) ) {
            push @outSamples, $r_sample;
            $prevSampleTime = $r_sample->{'time'};
        }
    }
    if ( $::DEBUG > 5 ) { printf("applySampleInterval: #outSamples=%d\n", $#outSamples); }
    if ( $::DEBUG > 10 ) { print Dumper("applySampleInterval: outSamples", \@outSamples); }
    return \@outSamples;
}

sub processDataSet($$$$$$$$$$$$) {
    my ($r_samples,
        $r_incrData,$incrKey,
        $r_cliArgs,$r_model,$dbh,
        $r_columnsToDelta, $r_columnsToRate, $r_columnsToFilterIdle,
        $r_columnsToFilterStatic, $r_columnsToScale,$r_columnsToFilterValue,
        $r_columnMap) = @_;

    if ( $::DEBUG > 5 ) { print "processDataSet: incrKey=$incrKey\n"; }

    # sampleinterval needs to be done first so that when we're saving the
    # last sample in the next step, it's the last "downsampled" sample, not the
    # last raw sample (TORF-568825)
    if ( exists $r_model->{'sampleinterval'} ) {
        my $date = $r_cliArgs->{'date'};
        defined $date or die "processData: date not defined";
        my $prevSampleTime =  undef;
        if (exists $r_incrData->{'lastsample'}->{$incrKey} ) {
            $prevSampleTime = $r_incrData->{'lastsample'}->{$incrKey}->{'time'}
        }
        $r_samples = applySampleInterval($r_samples, $r_model->{'sampleinterval'}, $prevSampleTime, $date);
    }

    # Save the last sample (needed for incremental processing of deta cols + sampleinterval)
    my $r_savedLastSample = undef;
    if ( @{$r_samples} && ( @{$r_columnsToDelta} || @{$r_columnsToFilterStatic} || exists $r_model->{'sampleinterval'} ) ) {
        $r_savedLastSample = dclone($r_samples->[$#{$r_samples}]);
    }

    if ( $#{$r_columnsToDelta} > -1 ) {
        # Consider the last sample of the previous incremental processing for the given JVM
        # if it exists, for delta calculation with the first sample of the present processing
        if ( exists $r_incrData->{'lastsample'}->{$incrKey} ) {
            if ( $::DEBUG > 5 ) { print "processDataSet: adding lastsample from r_incrData\n"; }
            unshift @{$r_samples}, $r_incrData->{'lastsample'}->{$incrKey};
        }

        deltaSamples($r_samples, $r_columnsToDelta );

        # Delta's will produce null's in the first sample (nothing to delta against)
        # so discard it
        shift @{$r_samples};

        if ( $::DEBUG > 8 ) { print Dumper( "processDataSet: delta'd r_samples", $r_samples ); }
    }

    if ( $#{$r_columnsToFilterStatic} > -1 ) {
        # Consider the last sample of the previous incremental processing for the given JVM
        # if it exists, for comparison with the first sample of the present processing
        if ( exists $r_incrData->{'lastsample'}->{$incrKey} ) {
            if ( $::DEBUG > 5 ) { print "processDataSet: adding lastsample from r_incrData\n"; }
            unshift @{$r_samples}, $r_incrData->{'lastsample'}->{$incrKey};
            $r_samples = instrFilterStaticSamples($r_samples, $r_columnsToFilterStatic );
            #Discard the first sample after filter only incase of incremental parsing
            shift @{$r_samples};
        } else {
            # This is for the first execution of the day
            $r_samples = instrFilterStaticSamples($r_samples, $r_columnsToFilterStatic );
        }
        if ( $::DEBUG > 8 ) { print Dumper( "processDataSet: filtered static r_samples", $r_samples ); }
    }

    if ( defined $r_savedLastSample ) {
        $r_incrData->{'lastsample'}->{$incrKey} = $r_savedLastSample;
    }

    if ( ! @{$r_samples} ) {
        # No samples, nothing more to do
        return $r_samples;
    }

    if ( $#{$r_columnsToRate} > -1 ) {
        $r_samples = instrRateSamples( $r_samples, $r_columnsToRate );
        if ( $::DEBUG > 8 ) { print Dumper( "processDataSet: rated r_samples", $r_samples ); }
    }

    if ( $#{$r_columnsToFilterIdle} > -1 ) {
        $r_samples = instrFilterIdle($r_samples,$r_columnsToFilterIdle);
        if ( $::DEBUG > 8 ) { print Dumper( "processDataSet: filtered idle r_samples", $r_samples ); }
    }

    if ( %{$r_columnsToFilterValue} ) {
        $r_samples = instrFilterSamples($r_samples,$r_columnsToFilterValue);
        if ( $::DEBUG > 8 ) { print Dumper( "processDataSet: filtered r_samples", $r_samples ); }
    }

    if ( %{$r_columnsToScale} ) {
        instrScaleSamples( $r_samples, $r_columnsToScale );
        if ( $::DEBUG > 8 ) { print Dumper( "processDataSet: scaled r_samples", $r_samples ); }
    }

    return $r_samples;
}


sub compareMetrics($$) {
    my ($r_a,$r_b) = @_;

    my @a_keys = keys %{$r_a};
    my @b_keys = keys %{$r_b};
    if ( $#a_keys != $#b_keys ) {
        return 1;
    }

    foreach my $a_key ( @a_keys ) {
        if ( $r_a->{$a_key} ne $r_b->{$a_key} ) {
            return 1;
        }
    }

    return 0;
}

sub getMetricGroupKey($$$) {
    my ($metricGroupName, $r_multiId, $r_mgProperties) = @_;

    if ( $::DEBUG > 9 ) { print Dumper("getMetricGroupKey: metricGroupName=$metricGroupName, r_multiId", $r_multiId); }

    my $key = $SINGLE;
    my $sourceMgName = $metricGroupName;
    if ( defined $r_multiId ) {
        my @multiIdValues = ();
        foreach my $multiIdName ( @{$r_multiId} ) {
            my $multiIdValue = $r_mgProperties->{$multiIdName}->{'sourcevalue'};
            if ( defined $multiIdValue ) {
                push @multiIdValues, $multiIdValue;
                # We strip out the "key" in multi instance metric groups so that
                # each instance can have the same columnMap
                $sourceMgName =~ s/$multiIdValue//;
            } elsif ( ! $r_mgProperties->{$multiIdName}->{'optional'} ) {
                print Dumper($r_mgProperties->{$multiIdName});
                die "No value of property $multiIdValue found in $sourceMgName";
            }
        }
        $key = join("@", @multiIdValues);
    }

    return ($key, $sourceMgName);
}

sub getMetricGroupProperties($$$) {
    my ($serviceName, $metricGroupName, $r_modelMetricGroup) = @_;

    my %mgProperties = ();
    if ( exists $r_modelMetricGroup->{'property'} ) {
        my @mgParams = $metricGroupName =~ $r_modelMetricGroup->{'instance_pattern'};
        foreach my $r_property ( @{$r_modelMetricGroup->{'property'}} ) {
            my $sourceValue = undef;
            if ( $r_property->{'type'} eq 'fixedproperty' ) {
                if ( $r_property->{'value'} eq '_servicegroup' ) {
                    $sourceValue = $serviceName;
                } else {
                    $sourceValue = $r_property->{'value'};
                }
            } elsif ( $r_property->{'type'} eq 'nameproperty' || $r_property->{'type'} eq 'multilabelproperty' ) {
                my $paramIndex = $r_property->{'index'} - 1;
                $paramIndex <= $#mgParams or die "Invalid index " . $r_property->{'index'}. " value for " . $r_property->{'name'} . " in " . $metricGroupName . ": " . join(",", @mgParams);
                $sourceValue = $mgParams[$paramIndex];
            }
            $mgProperties{$r_property->{'name'}} = { 'sourcevalue' => $sourceValue };
        }
    }
    if ( $::DEBUG > 6 ) { print Dumper("getMetricGroupProperties: mgProperties", \%mgProperties); }
    return \%mgProperties;
}

sub processMetricGroupProperties($$$$$$$) {
    my ($serviceName, $metricGroupName, $r_modelMetricGroup, $r_multiId, $r_propertyMap, $r_modelData, $r_mgData) = @_;

    my $r_mgProperties = getMetricGroupProperties($serviceName, $metricGroupName, $r_modelMetricGroup);
    my ($key, $sourceMgName) = getMetricGroupKey($metricGroupName, $r_multiId, $r_mgProperties);
    $r_modelData->{$key}->{$sourceMgName} = $r_mgData;

    #
    # Properties are common across metric groups with the same
    # key, so merge them here
    #
    my $r_propertiesForKey = $r_propertyMap->{$key};
    if ( ! defined $r_propertiesForKey ) {
        $r_propertyMap->{$key} = $r_mgProperties;
    } else {
        while ( my ($k,$v) = each %{$r_mgProperties} ) {
            $r_propertiesForKey->{$k} = $v;
        }
    }

    return $sourceMgName;
}

sub processMetricGroupMetrics($$$$$$$$$) {
    my ($r_metrics, $hasGroupFunc, $sourceMgName,
        $r_columnMap, $r_deltaMap, $r_filterIdleMap, $r_filterStaticMap, $r_scaleMap, $r_filterValueMap, $r_rateMap ) = @_;
    my $processMetricGroupMetricsStartTime = Time::HiRes::time();

    foreach my $r_metric ( @{$r_metrics} ) {
        my $fullMetricName = undef;
        if ( $hasGroupFunc ) {
            # If we're grouping with instrJoinMetrics which prepends the mg name
            $fullMetricName = $sourceMgName . "-" . $r_metric->{'source'};
        } else {
            # If no r_groupFunc defined we keep the name of the metric
            $fullMetricName = $r_metric->{'source'};
        }
        if ( $::DEBUG > 7 ) { print "processMetricGroupMetrics: fullMetricName=$fullMetricName\n"; }

        my $storeCol = 1;
        if ( exists $r_metric->{'store'} && $r_metric->{'store'} eq 'false' ) {
            $storeCol = 0;
        }

        if ( $storeCol ) {
            $r_columnMap->{$fullMetricName} = $r_metric->{'target'};
        }

        if ( exists $r_metric->{'delta'} && $r_metric->{'delta'} eq 'true' ) {
            $r_deltaMap->{$fullMetricName} = 1;
        }

        if ( exists $r_metric->{'filteridle'} && $r_metric->{'filteridle'} eq 'true' ) {
            $r_filterIdleMap->{$fullMetricName} = 1;
        }

        if ( exists $r_metric->{'filterstatic'} && $r_metric->{'filterstatic'} eq 'true' ) {
             $r_filterStaticMap->{$fullMetricName} = 1;
        }

        if ( exists $r_metric->{'scale'} ) {
            $r_scaleMap->{$fullMetricName} = $r_metric->{'scale'};
        }

        if ( exists $r_metric->{'filtervalue'} ) {
            $r_filterValueMap->{$fullMetricName} = $r_metric->{'filtervalue'};
        }

        if ( exists $r_metric->{'rate'}  && $r_metric->{'rate'} eq 'true' ) {
            $r_rateMap->{$fullMetricName} = 1;
        }
    }
}

1;
