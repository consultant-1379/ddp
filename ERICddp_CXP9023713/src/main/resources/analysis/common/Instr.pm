package Instr;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(parseData parseDataForCfg parseConfig setInstr_Debug deltaSamples groupData instrGroupDataFunc instrStoreData instrStoreDataSets instrGetTime instrSumMetrics instrJoinMetrics instrUnionMetrics instrFilterSamples instrFilterIdle instrFilterIdleSamples instrFilterIdleSamplesWithThresholds instrScaleSamples instrGetOffset instrRateSamples instrFilterStaticSamples);

use XML::DOM;
use Data::Dumper;
use strict;
use Carp;
use Time::HiRes;

use StatsDB;
use StatsTime;

our $Instr_DEBUG = 0;

our $MIN_VALID_TIME = 1262304000; # 2010-01-01 00:00:00

our %INSTR_SCHEMA_CACHE = ();

our $STORE_WITH_INSERT = 1;
our $STORE_WITH_LOAD = 2;
our $STORE_MODE = $STORE_WITH_LOAD;

our %STRING_DATA_TYPES = (
    'varchar' => 1,
    'enum' => 1,
    'datetime' => 1,
    'set' => 1
);

our $MAX_TIME_DIFFERENCE = 60;
our $DEFAULT_SAMPLE_INTERVAL = 60;

sub setInstr_Debug {
    my ($newDebug) = @_;
    $Instr_DEBUG = $newDebug;
}

sub instrGetOffset($) {
    my ($instrFile) = @_;

    my $result = undef;

    if ( isIncremental($instrFile) ) {
        my $index = 1;
        my $incrInstr = $instrFile . ".001";
        while ( -r $incrInstr ) {
            $index++;
            $incrInstr = $instrFile . sprintf(".%03d",$index);
        }
        $index--;
        $result = 0 - $index;
    }

    if ( ! defined $result ) {
        my @metadata = stat($instrFile);
        $result = $metadata[7];
    }

    return $result;
}

sub parseData {
    my ($config, $data) = @_;
    my $r_config;
    my @cfgFiles;

    foreach my $inputFile (split (/\,/, $config)) {
        if (-d $inputFile) {
            opendir(DIR, $inputFile);
            foreach my $file (readdir(DIR)) {
                unless ($file eq ".." || $file eq ".") {
                    push(@cfgFiles, $inputFile . "/" . $file);
                }
            }
            closedir(DIR);
        } else {
            my @inputFilesList = split (/\,/, $inputFile);
            foreach my $file (@inputFilesList) {
                push(@cfgFiles, $file);
            }
        }
    }

    foreach my $cfgFile (@cfgFiles) {
        my $r_configPart = parseConfig($cfgFile);
        foreach my $key (keys(%$r_configPart)) {
            $r_config->{$key} = $r_configPart->{$key};
        }
    }

    if ($Instr_DEBUG > 9) { print Dumper($r_config); }
    return parseDataForCfg($r_config, $data, 0);
}

sub parseDataForCfg($$$) {
    my ($r_config, $data, $dataOffset) = @_;

    my %providersByName = ();
    my %providersWithSpace = ();
    foreach my $profileName ( keys %{$r_config} ) {
        if ( $Instr_DEBUG > 9 ) { print "parseDataForCfg: profileName=$profileName\n"; }
        foreach my $providerName ( keys %{$r_config->{$profileName}->{'providers'}} ) {
            if ( $Instr_DEBUG > 9 ) { print "parseDataForCfg: providerName=$providerName\n"; }
            $providersByName{$providerName} = $r_config->{$profileName}->{'providers'}->{$providerName};

            my @nameParts = split(/\s+/,$providerName);
            if ( $#nameParts > 0 ) {
                $providersWithSpace{$nameParts[0]} = [ $providerName, ($#nameParts + 1) ];
            }
        }
    }
    if ( $Instr_DEBUG > 8 ) { print Dumper("parseDataForCfg: providersByName", \%providersByName); }
    if ( $Instr_DEBUG > 8 ) { print Dumper("parseDataForCfg: providersWithSpace", \%providersWithSpace); }

    my $isIncremental = isIncremental($data);

    if ( $isIncremental && $dataOffset > 0 ) {
        die "Cannot have a positive dataoffset for incremental files";
    }
    if ( (! $isIncremental) && ( $dataOffset < 0 ) ) {
        die "Cannot have a negative dataoffset for non-incremental files";
    }

    my @fileSet = ();
    if ( $isIncremental ) {
        getIncrementFileSet($data, 0 - $dataOffset, \@fileSet);
    } else {
        push @fileSet, { 'file' => $data, 'offset' => $dataOffset };
    }

    my %data = ();
    foreach my $r_file ( @fileSet ) {
        parseDataFile($r_file,\%data,\%providersByName,\%providersWithSpace);
    }

    return \%data;
}

sub parseDataFile($$$$) {
    my ($r_file,$r_data,$r_providersByName,$r_providersWithSpace) = @_;

    if ( $Instr_DEBUG ) { print "parseDataFile: processing $r_file->{'file'}\n"; }
    open DATA, $r_file->{'file'} or die "Could not open data file: $!";
    if ( $r_file->{'offset'} > 0 ) {
        seek DATA, $r_file->{'offset'}, 0;
    }

    while (my $line = <DATA>) {
        if ( $line !~ /^\d+-/ ) {
            if ( $Instr_DEBUG > 3 ) { print "parseDataFile: ignoring line $line"; }
            next;
        }

        chomp($line);
        if ($Instr_DEBUG > 9) {print "parseDataFile: processing line " . $line . "\n";}
        my @cols = split(/ /, $line);
        my $providerName = $cols[2];
        my $metricColumnIndex = undef;
        my $r_providerCfg = $r_providersByName->{$providerName};
        if ( defined $r_providerCfg ) {
            $metricColumnIndex = 3;
        } else {
            my $r_spaceCfg = $r_providersWithSpace->{$providerName};
            if ( defined $r_spaceCfg ) {
                $r_providerCfg = $r_providersByName->{$r_spaceCfg->[0]};
                $providerName = $r_spaceCfg->[0];
                $metricColumnIndex = $r_spaceCfg->[1] + 2;
            } else {
                if ( $providerName =~ /^CFG-(\S+)/ ) {
                    my $providerName = $1;
                    if ( $Instr_DEBUG > 3 ) { print "parseDataFile: CFG for $providerName\n"; }

                    my $r_providerCfg = $r_providersByName->{$providerName};
                    if ( ! defined $r_providerCfg ) {
                        # It's normal enough for this to happen (e.g. if we've been called with a filtered
                        # version of the config file
                        if ( $Instr_DEBUG > 0 ) { print "parseDataFile: Could not match CFG providerName $providerName\n"; }
                        next;
                    }
                    if ( $Instr_DEBUG > 3 ) { print Dumper("parseDataFile: r_providerCfg", $r_providerCfg); }

                    my $updateMetrics = 0;

                    my @metricIndexes = keys %{$r_providerCfg->{'metrics'}};
                    my $numMetricsInInit = $#cols - 2;
                    if ( $Instr_DEBUG > 3 ) { printf("parseDataForCfg: numMetricsInInit=%d metricIndexes=%d\n", $numMetricsInInit,($#metricIndexes+1)); }

                    if ( ($#metricIndexes + 1) != $numMetricsInInit ) {
                        $updateMetrics = 1;
                    } else {
                        foreach my $meticIndex ( sort { $a <=> $b } @metricIndexes ) {
                            if ( $Instr_DEBUG > 3 ) { printf("parseDataForCfg: meticIndex=%d col=%s provider=%s\n", $meticIndex, $cols[$meticIndex+2],$r_providerCfg->{'metrics'}->{$meticIndex}); }
                            if ( $cols[$meticIndex + 3] != $r_providerCfg->{'metrics'}->{$meticIndex} ) {
                                $updateMetrics = 1;
                            }
                        }
                    }

                    if ( $updateMetrics ) {
                        print "INFO: Metics mis-match, updating providerCfg $line\n";
                        my %metrics = ();
                        for ( my $metricIndex = 0; $metricIndex + 2 < $#cols; $metricIndex++ ) {
                            $metrics{$metricIndex} = $cols[3 + $metricIndex];
                        }
                        $r_providerCfg->{'metrics'} = \%metrics;
                    }
                }
            }
        }

        if ( $r_providerCfg ) {
            my $r_providerData = $r_data->{$providerName};
            if ( ! $r_providerData ) {
                $r_providerData = [];
                $r_data->{$providerName} = $r_providerData;
            }

            # Quick nadger on the COSM-FileAuditor data records to get rid of spaces in the last cleanup date-time metric
            # Will put a proper fix into the instr OutputHandler on the DDC side when time allows. [BG 2011-04-20]
            if ( $providerName eq 'COSM-FileAuditor' ) {
                if ($line =~ /^([0-3][0-9]-[0-1][0-9]-[0-9][0-9] [0-2][0-9]\:[0-5][0-9]\:[0-5][0-9]\.[0-9][0-9][0-9] COSM\-FileAuditor) ([A-Z][a-z][a-z] [A-Z][a-z][a-z] \d+ \d\d\:\d\d\:\d\d [A-Z]+ \d\d\d\d) (\S+ \d+ \d+ \d+\s*)$/) {
                    my ($startOfRecord, $lastCleanupOperationStartTime, $endOfRecord) = ($1, $2, $3);
                    $lastCleanupOperationStartTime =~ s/\s/\-/g;
                    $line = $startOfRecord . " " . $lastCleanupOperationStartTime . " " . $endOfRecord;
                    chop($line);
                    @cols = split(/ /, $line);
                }

            }

            my $r_timePeriod = { "timestamp" => $cols[0] . " " . $cols[1] };


            foreach my $metricIdx (sort keys %{$r_providerCfg->{'metrics'}} ) {
                my $metricName = $r_providerCfg->{'metrics'}->{$metricIdx};
                if ($Instr_DEBUG > 7) {print "metric name " . $metricName . " - idx " . $metricIdx . " - value " . $cols[$metricIdx + $metricColumnIndex] . "\n";}
                $r_timePeriod->{$metricName} = $cols[$metricIdx + $metricColumnIndex];
            }
            push(@{$r_providerData}, $r_timePeriod);
        }
    }

    close DATA;
}

sub parseConfig {
    my $file = shift;
    if ($Instr_DEBUG > 5) { print "Processing " . $file . "\n"; }
    my $parser = XML::DOM::Parser->new();
    my $doc = $parser->parsefile($file);
    my %data = ();

    # Get the instr element
    my $instrs = $doc->getElementsByTagName('instr');
    if ($instrs->getLength() != 1) {
        print "ERROR: Found " . $instrs->getLength() . " instr elements in " . $file . "\n";
        return \%data;
    }

    foreach my $profile ($instrs->item(0)->getElementsByTagName("profile",0)) {
        my %profileCfg = ();
        my $profileName = $profile->getAttributeNode('name')->getValue();
        # Ignore the collection interval, if any, at the end of the profile name
        if ( $profileName =~ /^(.*-Instrumentation)-\d+$/ ) {
            $profileName = $1;
        }
        if ($Instr_DEBUG > 2) { print "Got profile: " . $profileName . "\n"; }
        if ( ! exists $data{$profileName} ) {
            $data{$profileName} = {};
        }
        parseProfile($profile, $data{$profileName});
    }

    return \%data;
}

sub parseProfile {
    my $profile = shift;
    my $profileData = shift;

    if ( ! exists $profileData->{'providers'} ) {
        $profileData->{'providers'} = {};
    }
    foreach my $provider ($profile->getElementsByTagName("provider",0)) {
        my $providerName = $provider->getAttributeNode('name')->getValue();
        my $providerType = $provider->getAttributeNode('type')->getValue();
        if ($Instr_DEBUG > 2) {print "Got provider: " . $providerName . " - type " . $providerType . "\n"; }
        my %metrics = getMetrics($provider);
        my $size = scalar(keys %metrics);
        if ($size > 0) {
            %{$profileData->{'providers'}->{$providerName}} = (
                'type' => $providerType,
                'metrics' => \%metrics
                );
        }
        my %metricGroups = getMetricGroups($provider);
        # metricGroups get treated as a provider
        foreach my $mgName (keys %metricGroups) {
            my %mg = %{$metricGroups{$mgName}};
            %{$profileData->{'providers'}->{$mgName}} = %mg;
        }
    }

    my $pollInterval = $profile->getElementsByTagName("pollInterval",0)->item(0);
    if ( defined $pollInterval ) {
        $profileData->{'pollInterval'} = $pollInterval->getFirstChild()->getNodeValue() + 0;
    }
}

sub getMetrics {
    my $provider = shift;
    my %metrics = ();
    my $count = 0;
    foreach my $child ($provider->getChildNodes()) {
        for ($child->getNodeName()) {
            /^metric$/ and do {
                $metrics{$count++} = $child->getAttributeNode('name')->getValue();
                last;
            };
            /^compositeMetric$/ and do {
                my $compMetricName = $child->getAttributeNode('name')->getValue();
                my %compMetrics = getMetrics($child);
                foreach my $metNum (sort keys %compMetrics) {
                    $metrics{$count++} = $compMetricName . "-" . $compMetrics{$metNum};
                }
                last;
            };
        }
    }
    return %metrics;
}

sub getMetricGroups {
    my $provider = shift;
    my $providerName = $provider->getAttributeNode('name')->getValue();
    my %mgs = ();
    foreach my $child ($provider->getChildNodes()) {
        for ($child->getNodeName()) {
            /^metricGroup$/ and do {
                # TODO: merge with duplicate provider handling code, since
                # we just treat a metricGroup as a provider
                my $name = $child->getAttributeNode('name')->getValue();
                my $type = $provider->getAttributeNode('type')->getValue();
                my %mgMetrics = getMetrics($child);
                my $size = scalar(keys %mgMetrics);
                if ($size > 0) {
                    %{$mgs{$providerName . "-" . $name}} = (
                        'type' => $type,
                        'metrics' => \%mgMetrics
                    );
                    last;
                }
            };
        }
    }
    return %mgs;
}

## Function that converts absolute value metrics in an array of sample to delta-ed values (Example: 1, 2, 5 -> 1, 1, 3).
##
## Parameters:
##  r_samples: Reference to array of samples; each sample is a hash of metrics
##      -format = [
##              {metricName1 => metric1, metricName2 => metric2...}, <-- sample
##              {metricName1 => metric1, metricName2 => metric2...},
##              ...
##      ]
##  r_metricsToDelta: Reference to array of metrics names to delta in r_samples
##      -format = [metricName, metricName, ...]
##
## Returns: Nothing - modifies array of samples in place using reference provided
##
sub deltaSamples {
    my ($r_samples, $r_metricsToDelta) = @_;

    if ( $Instr_DEBUG > 3 ) { print "deltaSamples - metricsToDelta: " . join(", ",@{$r_metricsToDelta}) . "\n"; }
    my %lastSample = ();

    foreach my $r_sample ( @{$r_samples} ) {
        if ( $Instr_DEBUG > 8 ) { print "deltaSamples - Timestamp = " . $r_sample->{'timestamp'} . "\n"; }

        #
        # If any delta counter in the sample is less then the previous value, we assume that the
        # process/host/VM restarted. In this case we must assume that all the
        # delta counters in the sample reset
        #
        my $isReset = 0;
        foreach my $metricName ( @{$r_metricsToDelta} ) {
            if ( defined $r_sample->{$metricName} ) {
                my $currValue = $r_sample->{$metricName};
                my $prevValue = $lastSample{$metricName};
                if ( defined $prevValue && ($prevValue > $currValue) ) {
                    if ( $isReset == 0 ) {
                        print "deltaSamples: detected reset @ $r_sample->{'timestamp'} on $metricName\n";
                    }
                    $isReset = 1;
                }
            }
        }

        foreach my $metricName ( @{$r_metricsToDelta} ) {
            if ( defined $r_sample->{$metricName} ) {
                my $currentValue = $r_sample->{$metricName};
                my $previousValue = $lastSample{$metricName};

                if ( $Instr_DEBUG > 8 ) {
                    printf "deltaSamples - metricName=%s currentValue=%s previousValue=%s\n",
                        $metricName, $currentValue,
                        (defined $previousValue ? $previousValue : "undef");
                }

                if ( $isReset ) {
                    # In a reset, the delta is current value - 0, i.e. current value
                    $r_sample->{$metricName} = $currentValue;
                } elsif ( defined $previousValue ) {
                    # Normal "flow", i.e. delta = currentValue - previousValue
                    $r_sample->{$metricName} = $currentValue - $previousValue;
                } else {
                    # We don't have a previous so we can't calculate the delta
                    delete $r_sample->{$metricName};
                }
                $lastSample{$metricName} = $currentValue;
            } else {
                if ( $Instr_DEBUG > 6 ) { print Dumper("deltaSamples -  failure: $metricName not found in sample", $r_sample); }
            }
        }
    }
}

## Function that converts absolute value metrics in an array of sample to rate, i.e. divides the value by the duration
##
## Parameters:
##  r_samples: Reference to array of samples; each sample is a hash of metrics
##      -format = [
##              {metricName1 => metric1, metricName2 => metric2...}, <-- sample
##              {metricName1 => metric1, metricName2 => metric2...},
##              ...
##      ]
##  r_metricsToRate: Reference to array of metrics names to delta in r_samples
##      -format = [metricName, metricName, ...]
##
## Returns: array of samples
##
sub instrRateSamples {
    my ($r_samples, $r_metricsToRate) = @_;

    if ( $Instr_DEBUG > 3 ) { print "instrRateSamples - metricsToRate: " . join(", ",@{$r_metricsToRate}) . "\n"; }

    # Need a minimum of two samples to perform rate caluation
    if ( $#{$r_samples} < 1 ) {
        print "WARN: Insufficent samples to perform rate calculation\n";
        return [];
    }

    my @results = ();
    my $index = 0;
    foreach my $r_sample ( @{$r_samples} ) {
        if ( $Instr_DEBUG > 8 ) { print "instrRateSamples - Timestamp = " . $r_sample->{'timestamp'} . "\n"; }

        my $duration = undef;
        if ( $index == 0 ) {
            $duration = $r_samples->[1]->{'time'} - $r_sample->{'time'};
        } else {
            $duration = $r_sample->{'time'} - $results[$#results]->{'time'};
        }
        if ( $duration == 0 ) {
            printf "WARN: Zero duration for sample @ %s, dropping\n", $r_sample->{'time'};
            next;
        }

        $r_sample->{'_rate_duration'} = $duration;

        foreach my $metricName ( @{$r_metricsToRate} ) {
            if ( defined $r_sample->{$metricName} ) {
                my $value = $r_sample->{$metricName};
                my $rate = $value / $duration;
                $r_sample->{$metricName} = $rate;
            } else {
                if ( $Instr_DEBUG > 6 ) { print Dumper("instrRateSamples -  failure: $metricName not found in sample", $r_sample); }
            }
        }

        push @results, $r_sample;
        $index++
    }

    return \@results;
}

## Function that takes data dictionary containing samples from multiple providers over a single period
## of time and merges the samples together.
##
## Parameters:
##  r_data = {
##      providerName1 => (
##          {metricName1 => metric1, metricName2 => metric2...}, <-- sample
##          {metricName1 => metric1, metricName2 => metric2...},
##          ...
##      ),
##      providerName2 = (
##          {metricName3 => metric3, metricName4 => metric4...}, <-- sample
##          {metricName3 => metric3, metricName4 => metric4...},
##          ...
##      ),
##      ...
##  }
##
## Returns: Reference to list of samples merged from seperate samples in input data
##  samples = [
##      {metricName1 => metric1, metricName2 => metric2, metricName3 => metric3, metricName4 => metric4...}, <-- sample
##      {metricName1 => metric1, metricName2 => metric2, metricName3 => metric3, metricName4 => metric4...},
##      ...
##  ]
##
sub groupData {
    my $r_data = shift;

    return instrGroupDataFunc($r_data,\&instrUnionMetrics);
}

sub instrGroupDataFunc($$) {
    my ($r_data,$r_function) = @_;

    my @samples = ();
    my $moreSamplesExist = 1;
    if ( $Instr_DEBUG > 3 ) { print "instrGroupDataFunc - providers:\n", Dumper(keys %{$r_data}); }

    if ( $Instr_DEBUG > 10 ) { print "instrGroupDataFunc: r_data", Dumper($r_data); }

    #
    # If we have a case where a provider is added during an upgrade, we get into
    # problems if that provider is the first one we look at because it will start
    # at a later time, so we would skip all the samples from the other providers
    # up to the time of the first sample from the new provider
    #
    # To address then, we need to sort the providers based on the timestamp of
    # their first samples
    #
    my %firstSampleTime = ();
    while (my ($providerName, $r_providerSamples) = each %{$r_data}) {
        $firstSampleTime{$providerName} = instrGetTime($r_providerSamples->[0]);
    }
    my @sortedProviders = sort { $firstSampleTime{$a} <=> $firstSampleTime{$b} } keys %firstSampleTime;

    while ( $moreSamplesExist == 1 ) {
        my $r_aggregatedSample;
        foreach my $providerName ( @sortedProviders ) {
            my $r_providerSamples = $r_data->{$providerName};
            my $r_providerSample = shift @{$r_providerSamples};
            if ( isValidSample($r_providerSample, $providerName) == 0 ) {
                next;
            }
            if ( $Instr_DEBUG > 10 ) { print "groupDataFunc - provSample: " . Dumper($r_providerSample); }

            my $sampleTime = instrGetTime($r_providerSample);
            if ( $Instr_DEBUG > 7 ) { print Dumper("groupDataFunc - providerName=$providerName provider Time=" . $r_providerSample->{'timestamp'} . " r_aggregatedSample", $r_aggregatedSample); }

            # If aggregatedSample is empty, create with time information
            if ( ! $r_aggregatedSample ) {
                $r_aggregatedSample = generateSample($sampleTime, $r_providerSample);
                $moreSamplesExist = ( $#{$r_providerSamples} > -1 );
            } elsif ( ! sampleInSync($r_providerSample, $r_aggregatedSample->{'time'}) ) {
                print "ERROR: Time out of sync currentSample = " . $r_aggregatedSample->{'timestamp'} . ", providerName=$providerName, providerTime=" . $r_providerSample->{'timestamp'} . "\n";
                $r_providerSample = synchroniseSamples($r_providerSample, $r_aggregatedSample->{'time'}, $r_providerSamples);
            }

            if ( defined $r_providerSample ) {
                $r_function->($providerName, $r_providerSample, $r_aggregatedSample);
            }
        }

        if ( $Instr_DEBUG > 6 ) { print Dumper("groupDataFunc completed r_aggregatedSample", $r_aggregatedSample); }

        # If samples have been aggregated together, add reference to main samples array, else finish
        if ( defined $r_aggregatedSample ) {
            push @samples, $r_aggregatedSample;
        } else {
            $moreSamplesExist = 0;
        }
    }

    if ( $Instr_DEBUG > 9 ) { print Dumper("groupDataFunc r_samples", \@samples); }

    return \@samples;
}

## Function that "scales" the specified metrics
##
## Parameters:
##  r_samples: Reference to array of samples; each sample is a hash of metrics
##      -format = [
##              {metricName1 => metric1, metricName2 => metric2...}, <-- sample
##              {metricName1 => metric1, metricName2 => metric2...},
##              ...
##      ]
##  r_metricsToScale: Reference to hash of metrics names to scale valuies
##      -format = [metricName1 => scaleValue, ...]
##
## Returns: Nothing - modifies array of samples in place using reference provided
##
sub instrScaleSamples($$) {
    my ($r_samples,$r_metricsToScale) = @_;

    if ( $Instr_DEBUG > 3 ) { print Dumper("instrScaleSamples - r_metricsToScale:", $r_metricsToScale); }

    foreach my $r_sample ( @{$r_samples} ) {
        if ( $Instr_DEBUG > 8 ) { print "instrScaleSamples - Timestamp = " . $r_sample->{'timestamp'} . "\n"; }
        while ( my ($name,$scale) = each %{$r_metricsToScale} ) {
            my $unscaledValue = $r_sample->{$name};
            if ( defined $unscaledValue ) {
                $r_sample->{$name} = $unscaledValue / $scale;
            }
        }
    }
}

sub isValidSample {
    my ($r_sample, $providerName) = @_;
    my $isValidSample = 1;

    if ( ! defined $r_sample ) {
        print "WARN: out of samples for $providerName\n";
        $isValidSample = 0;
    } elsif ( ! exists $r_sample->{'timestamp'} ) {
        print Dumper("WARN: corrupt provider sample for $providerName", $r_sample);
        $isValidSample = 0;
    }

    return $isValidSample;
}

sub instrGetTime {
    my $r_sample = shift;

    if ( exists $r_sample->{'time'} ) {
        return $r_sample->{'time'};
    }

    my $sampleTimeString = $r_sample->{'timestamp'};
    if ( ! defined $sampleTimeString ) {
        print Dumper($r_sample);
        die "ERROR sample doesn't have timestamp field\n";
    }

    $sampleTimeString =~ s/ /:/;
    $sampleTimeString =~ s/\.(\d{3,3})$//;

    return parseTime($sampleTimeString, $StatsTime::TIME_DDMMYY_HMS);
}

sub generateSample {
    my ($time, $r_sample) = @_;

    if ( $Instr_DEBUG > 9 ) { print "generateSample: timestamp=", $r_sample->{'timestamp'}, "\n"; }

    my $r_initialSample = {
        'time' => $time,
        'timestamp' => $r_sample->{'timestamp'}
    };

    return $r_initialSample;
}

sub sampleInSync {
    my ($r_sample, $r_syncTime) = @_;
    my $delta = abs($r_syncTime - instrGetTime($r_sample));
    my $result = $delta <= $MAX_TIME_DIFFERENCE;
    if ( ! $result && $Instr_DEBUG > 8 ) {
        print "sampleInSync: delta=$delta\n"
    }
    return $result;
}

sub synchroniseSamples {
    my ($r_currentSample, $timeToSyncTo, $providerSamples) = @_;

    if ( instrGetTime($r_currentSample) < $timeToSyncTo  && ($#{$providerSamples} > -1) ) {
        $r_currentSample = findSampleInSync($timeToSyncTo, $providerSamples);
    }

    if ( ! sampleInSync($r_currentSample, $timeToSyncTo) ) {
        # If sample is too far ahead then it belongs in the next aggregatedSample, so put it back into the provider sample array
        if ( (instrGetTime($r_currentSample) -  $timeToSyncTo) > ($DEFAULT_SAMPLE_INTERVAL/2)  ) {
            if ( $Instr_DEBUG > 0 ) { print "synchroniseSamples - Provider sample too far ahead\n"; }
            unshift @{$providerSamples}, $r_currentSample;
        }
        $r_currentSample = undef;
    }

    return $r_currentSample;
}

sub findSampleInSync {
    my ($timeToSyncTo, $providerSamples) = @_;

    my $r_currentSample = shift @{$providerSamples};

    # If current sample is chronologically behind aggregatedSample time, shift data samples until we find a sample that's chronologically later
    while ( (instrGetTime($r_currentSample) < $timeToSyncTo) && ($#{$providerSamples} > -1) ) {
        $r_currentSample = shift @{$providerSamples};
        if ( $Instr_DEBUG > 0 ) { print "findSampleInSync - Skipped provider samples: provider time now=" . $r_currentSample->{'timestamp'} . "(" . instrGetTime($r_currentSample) . ")\n"; }
    }

    return $r_currentSample;
}

sub instrUnionMetrics($$$) {
    my ($providerName, $r_sourceMap, $r_destinationMap) = @_;

    while (my ($key, $value) = each %{$r_sourceMap}) {
        $r_destinationMap->{$key} = $value;
    }
}

sub instrJoinMetrics($$$) {
    my ($providerName, $r_sourceMap, $r_destinationMap) = @_;

    while (my ($key, $value) = each %{$r_sourceMap}) {
        if ( $key ne 'time' && $key ne 'timestamp' ) {
            $r_destinationMap->{$providerName . "-" . $key} = $value;
        }
    }
}

sub instrSumMetrics($$$) {
    my ($providerName, $r_sourceMap, $r_destinationMap) = @_;

    while (my ($key, $value) = each %{$r_sourceMap}) {
        if ( $key ne 'time' && $key ne 'timestamp' ) {
            if ( exists $r_destinationMap->{$key} ) {
                $r_destinationMap->{$key} += $value;
            } else {
                $r_destinationMap->{$key} = $value;
            }
        }
    }
}

#
# Filter Samples
#
sub instrFilterSamples($$) {
    my ($r_samples,$r_metricValues) = @_;

    my @filteredSamples = ();
    foreach my $r_sample ( @{$r_samples} ) {
        my $match = 1;
        while ( my ($name,$filterValue) = each %{$r_metricValues} ) {
            my $sampleValue = $r_sample->{$name};
            if ( (! defined $sampleValue) || ($sampleValue ne $filterValue) ) {
                $match = 0;
            }
            if ( $Instr_DEBUG > 9 ) { printf "instrFilterSamples name=%s filterValue=%s sampleValue=%s match=%d\n", $name, $filterValue, (defined $sampleValue ? $sampleValue : "undef"), $match; }
        }

        if ( $match ) {
            push @filteredSamples, $r_sample;
        }
    }

    if ( $Instr_DEBUG > 7 ) { print Dumper("instrFilterSamples filteredSamples", \@filteredSamples); }
    return \@filteredSamples;
}

# Remove idle (all zero samples)
# First is left to indicate presense of data
sub instrFilterIdle($$) {
    my ($r_samples,$r_metricNames) = @_;
    return instrFilterIdleSamples($r_samples,$r_metricNames,1);
}

sub instrFilterIdleSamples($$$) {
    my ($r_samples,$r_metricNames,$keepFirst) = @_;

    my %thresholds = ();
    foreach my $metricName ( @{$r_metricNames} ) {
        $thresholds{$metricName} = 0;
    }

    return instrFilterIdleSamplesWithThresholds($r_samples, \%thresholds, $keepFirst);
}

## Function filters out samples where the specified metrics don't exceed their thresholds
##
## Parameters:
##  r_samples: Array of samples
##  r_thresholds: Hash of metric name => threshold
##  keepFirst: Whether or not to drop the first sample
##
## To aid with plotting, any non-idle sample is "book-ended"
##
## Returns: Array containing the filtered samples
##
sub instrFilterIdleSamplesWithThresholds($$$) {
    my ($r_samples, $r_thresholds, $keepFirst) = @_;

    if ( $Instr_DEBUG > 4 ) { print Dumper("instrFilterIdleSamplesWithThresholds r_thresholds", $r_thresholds); }

    my @filteredSamples = ();

    my $lastWasIdle = 1;
    if ( $keepFirst ) {
        # copy over one value to indicate we have data
        push @filteredSamples, shift @{$r_samples};
        $lastWasIdle = checkIdleWithThreshold($filteredSamples[0], $r_thresholds);
    }

    my $r_last = undef;
    foreach my $r_sample ( @{$r_samples} ) {
        my $thisIsIdle = checkIdleWithThreshold($r_sample, $r_thresholds);
        if ( $Instr_DEBUG > 8 ) { print "instrFilterIdleSamplesWithThresholds: thisIsIdle=$thisIsIdle lastWasIdle=$lastWasIdle\n"; }

        # If this sample is non-idle or the previous sample was non-idle
        # (need to "bookend" non-idle samples with idle ones
        if ( (! $thisIsIdle) || (! $lastWasIdle) ) {
            if ( defined $r_last ) {
                push @filteredSamples, $r_last;
                $r_last = undef;
            }
            push @filteredSamples, $r_sample;
        } else {
            $r_last = $r_sample;
        }

        $lastWasIdle = $thisIsIdle;
    }

    if ( $Instr_DEBUG > 7 ) { print Dumper("instrFilterIdleSamplesWithThresholds filterIdleSamples", \@filteredSamples); }
    return \@filteredSamples;
}

# Return 1 if any of specified metrics below their threshold value, zero otherwise
sub checkIdleWithThreshold($$) {
    my ($r_sample, $r_thresholds) = @_;

    my $idle = 1;

    foreach my $metricName ( keys %{$r_thresholds} ) {
        my $metricValue = $r_sample->{$metricName};
        my $threshold = $r_thresholds->{$metricName};
        if ( $Instr_DEBUG > 9 ) { printf "checkIdleWithThreshold: %s=%s threshold=%s\n", $metricName, (defined $metricValue ? $metricValue : "undef"), $threshold; }
        if ( defined $metricValue && ( $metricValue > $threshold ) ) {
            if ( $Instr_DEBUG > 8 ) { printf "checkIdleWithThreshold: %s=%s above threshold=%s\n", $metricName, $metricValue, $threshold; }
            $idle = 0;
            last;
        }
    }

    if ( $Instr_DEBUG > 8 ) { printf "checkIdleWithThreshold: idle=%d\n", $idle; }

    return $idle;
}

## Function that filters static(non-changing) absolute value metrics in an array of sample

sub instrFilterStaticSamples {
    my ($r_samples, $r_metricsToFilter) = @_;

    if ( $Instr_DEBUG > 3 ) { print "instrFilterStaticSamples - metricsToFilter: " . join(", ",@{$r_metricsToFilter}) . "\n"; }

    my %lastSample = ();
    my @filteredSamples = ();

    foreach my $r_sample ( @{$r_samples} ) {
        foreach my $metricName ( @{$r_metricsToFilter} ) {
            if ( defined $r_sample->{$metricName} ) {
                my $currValue = $r_sample->{$metricName};
                my $prevValue = $lastSample{$metricName};
                if ( defined $prevValue ) {
                    if ( $prevValue != $currValue ) {
                        push @filteredSamples, $r_sample;
                    }
                } else {
                     if ( $Instr_DEBUG > 7 ) { print "Storing the first sample \n"; }
                     push @filteredSamples, $r_sample;
                }
                $lastSample{$metricName} = $currValue;
            } else {
                if ( $Instr_DEBUG > 6 ) { print Dumper("instrFilterStaticSamples -  failure: $metricName not found in sample", $r_sample); }
            }
        }
    }
    if ( $Instr_DEBUG > 7 ) { print Dumper("instrFilterStaticSamples: filteredSamples", \@filteredSamples); }
    return \@filteredSamples;
}

## Function that inputs data into the statsdb database.
##
## Parameters:
##  tableName: String name of table to insert data to
##  siteName: Name of site the data is associated with
##  r_propertiesMap: Reference to hash containing properties of dataset (e.g. associated JBoss instance number)
##      -format = {propertyName => value, ...}
##  r_samples: Reference to array of samples to add to database
##      -format = [
##          {
##              'time' => 1417788945,
##              'timestamp' => '05-12-14 14:15:45.212',
##              'metric1' => value,
##              'metric2' => value, ...
##          }, ...
##      ]
##  r_metricToColumnMap: Reference to hash that relates metric keys in r_samples to associated table column names
##      -format = {"metricKey" => "columnName", ...}
##
## Returns: Nothing - adds data to statsdb database
##
sub instrStoreData($$$$$) {
    my ($tableName, $siteName, $r_propertiesMap, $r_samples, $r_metricToColumnMap) = @_;

    my $dbh = connect_db();
    my @dataSets = ( { 'properties' => $r_propertiesMap, 'samples' => $r_samples } );
    instrStoreDataSets($dbh, $tableName,$siteName,\@dataSets,$r_metricToColumnMap,1);
    $dbh->disconnect();
}

sub getTableSchema($$) {
    my ($dbh,$tableName) = @_;

    my $r_tableSchema = $INSTR_SCHEMA_CACHE{$tableName};
    if ( ! defined $r_tableSchema ) {
        $r_tableSchema =
            dbSelectAllHash($dbh,
                            sprintf("SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '%s' AND table_schema = DATABASE()",
                                    $tableName));
        foreach my $r_colInfo ( @{$r_tableSchema} ) {
            if ( exists $STRING_DATA_TYPES{$r_colInfo->{'DATA_TYPE'}} ) {
                $r_colInfo->{'NUMBER'} = 0;
            } else {
                $r_colInfo->{'NUMBER'} = 1;
            }
        }
        $INSTR_SCHEMA_CACHE{$tableName} = $r_tableSchema;
    }

    if ( $Instr_DEBUG > 3 ) { print Dumper("getTableSchema: table=$tableName, r_tableSchema", $r_tableSchema); }
    return $r_tableSchema;
}


sub writeBcpFile($$$$) {
    my ($tableName, $r_datasets, $r_dbColumnNames, $r_metricColumns) = @_;

    my $bcpFile = getBcpFileName($tableName);
    open(my $fh, ">$bcpFile") or die "Cannot open $bcpFile";
    foreach my $r_dataset ( @{$r_datasets} ) {
        if ( $Instr_DEBUG > 4 ) { print "writeBcpFile - processing data with properties: ", Dumper($r_dataset->{'properties'}); }

        # property values need to be set in the order they are listed in dbColumnNames
        my @propertyValues = ();
        for my $dbColumn ( @{$r_dbColumnNames} ) {
            if ( exists $r_dataset->{'properties'}->{$dbColumn} ) {
                push @propertyValues, $r_dataset->{'properties'}->{$dbColumn}
            }
        }

        my $useTimeStamp = 0;
        if ( exists $r_dataset->{'property_options'}->{'time'}->{'usetimestamp'} ) {
            $useTimeStamp = 1;
        }

        foreach my $r_sample ( @{$r_dataset->{'samples'}} ) {
            my $r_row = createRow(\@propertyValues, $r_sample, $r_metricColumns, $useTimeStamp);
            # Replace any undef with '\N'
            $_ //= '\N' for @{$r_row};
            print $fh join("\t", @{$r_row}), "\n";
        }
    }
    close $fh;

    return $bcpFile;
}

sub storeNewDataWithLoad($$$$$$) {
    my ($dbh, $tableName, $timeColumn, $r_datasets, $r_dbColumnNames, $r_metricColumns) = @_;

    my $bcpFile = writeBcpFile($tableName, $r_datasets, $r_dbColumnNames, $r_metricColumns);
    dbDo(
        $dbh,
        sprintf(
            "LOAD DATA LOCAL INFILE '%s' INTO TABLE %s (%s)",
            $bcpFile,
            $tableName,
            join(",", @{$r_dbColumnNames})
        )
    ) or die "Failed to load data into $tableName from $bcpFile";
}

sub storeNewDataWithInsert($$$$$$) {
    my ($dbh, $tableName, $timeColumn, $r_datasets, $r_dbColumnNames, $r_metricColumns) = @_;

    $dbh->begin_work() or die "Failed to start transaction: " . $dbh->errstr;

    my $columnList = join(",", @{$r_dbColumnNames});
    my @paramList = ();
    foreach my $dbColumn ( @{$r_dbColumnNames} ) {
        push @paramList, '?';
    }
    my $paramListStr = join(",",@paramList);
    my $insertSQL = sprintf("INSERT INTO %s (%s) VALUES (%s)", $tableName, $columnList, $paramListStr);
    my $sth = $dbh->prepare($insertSQL) or die "Failed to create statement for $insertSQL";
    foreach my $r_dataset ( @{$r_datasets} ) {
        my @propertyValues = values %{$r_dataset->{'properties'}};

        my $useTimeStamp = 0;
        if ( exists $r_dataset->{'property_options'}->{'time'}->{'usetimestamp'} ) {
            $useTimeStamp = 1;
        }

        foreach my $r_sample ( @{$r_dataset->{'samples'}} ) {
            my $r_row = createRow(\@propertyValues, $r_sample, $r_metricColumns, $useTimeStamp);
            $sth->execute(@{$r_row})
                or die "Failed to execute for $tableName for row" . Dumper($r_row);
        }
    }

    $dbh->commit() or die "Failed to commit data for $tableName: " . $dbh->errstr;
}

sub storeNewData($$$$$) {
    my ($dbh, $tableName, $timeColumn, $r_metricToColumnMap, $r_datasets) = @_;

    my @dbColumnNames = ( $timeColumn );
    # All properties must have the same set of columns
    push (@dbColumnNames, sort keys %{$r_datasets->[0]->{'properties'}});

    my $r_tableSchema = getTableSchema($dbh, $tableName);

    my @metricColumns = ();
    my %metricColumnsByColName = ();

    # Need to sort by keys here so we have predictable (for test) column order
    foreach my $metric ( sort keys %{$r_metricToColumnMap} ) {
        my $column = $r_metricToColumnMap->{$metric};

        if ( $metric eq 'time' ) {
            next;
        }

        my $r_metricColumnInfo = $metricColumnsByColName{$column};
        if ( defined $r_metricColumnInfo ) {
            push @{$r_metricColumnInfo->{'src'}}, $metric;
        } else {
            my $r_thisColInfo = undef;
            foreach my $r_colInfo ( @{$r_tableSchema} ) {
                if ( $r_colInfo->{'COLUMN_NAME'} eq $column ) {
                    $r_thisColInfo = $r_colInfo;
                    last;
                }
            }
            (defined $r_thisColInfo ) || die "Could not find matching column info for $tableName.$column";
            $r_metricColumnInfo = {
                'column' => $column,
                'src' => [ $metric ],
                'schema' => $r_thisColInfo
            };
            push @metricColumns, $r_metricColumnInfo;
            $metricColumnsByColName{$column} = $r_metricColumnInfo;
            push @dbColumnNames, $column;
        }
    }
    if ( $Instr_DEBUG > 4 ) { print "instrStoreDataSets - metricColumns: ", Dumper(\@metricColumns); }

    if ( $STORE_MODE == $STORE_WITH_INSERT ) {
        storeNewDataWithInsert($dbh, $tableName, $timeColumn, $r_datasets, \@dbColumnNames, \@metricColumns);
    } else {
        storeNewDataWithLoad($dbh, $tableName, $timeColumn, $r_datasets, \@dbColumnNames, \@metricColumns);
    }
}

sub createRow {
    my ($r_propertiesValues, $r_sample, $r_metricColumns, $useTimeStamp) = @_;

    if ( $Instr_DEBUG > 8 ) { print "createRow - r_sample: ", Dumper($r_sample); }
    my @row = ();

    # Add time column
    if ( $useTimeStamp ) {
        push @row, $r_sample->{'timestamp'};
    } else {
        push @row, formatTime($r_sample->{'time'}, $StatsTime::TIME_SQL)
    }

    # Add property values
    push (@row, @{$r_propertiesValues});

    # Add metric values
    foreach my $r_metricColumn ( @{$r_metricColumns} ) {
        push @row, validateValue($r_sample, $r_metricColumn);
    }
    if ( $Instr_DEBUG > 8 ) { print "createRow - row: ", Dumper(\@row); }

    return \@row;
}

sub validateValue {
    my ($r_sample, $r_metricColumn) = @_;

    my $sampleValue = undef;
    foreach my $src ( @{$r_metricColumn->{'src'}} ) {
        $sampleValue = $r_sample->{$src};
        if ( defined $sampleValue ) {
            last;
        }
    }

    my $columnName = $r_metricColumn->{'column'};
    # Different handling for numbers and string types
    my $r_colSchema = $r_metricColumn->{'schema'};
    if ( $r_colSchema->{'NUMBER'} == 1 ) {
        if ( defined $sampleValue ) {
            my $columnType = $r_colSchema->{'DATA_TYPE'};
            if ( $columnType eq 'decimal' || $columnType eq 'float' ) {
                return sprintf("%f", $sampleValue);
            } else {
                return sprintf("%d", $sampleValue);
            }
        } else {
            if ( $Instr_DEBUG > 5 ) { print "validateValue - no value found for $columnName\n"; }
            if ( $r_colSchema->{'IS_NULLABLE'} eq 'YES') {
                return undef;
            } else {
                printf "WARNING: No value for %s for NOT NULL column %s\n", $columnName, $r_colSchema->{'COLUMN_NAME'};
                return 0;
            }
        }
    } else {
        if ( defined $sampleValue ) {
            return sprintf("%s", $sampleValue);
        } else {
            return undef;
        }
    }
}

sub deleteData ($$$$$$) {
    my ($databaseHandler, $tableName, $firstTimestamp, $lastTimestamp, $r_datasets, $timeColumn) = @_;

    # We can only handle the case where only one of the properties has multiple
    # values, so verify that this is what we have
    my %nameValues = ();
    my %ignoreForDelete = ();
    foreach my $r_dataset ( @{$r_datasets} ) {
        my $r_propertiesColumnMap = $r_dataset->{'properties'};
        if ( $Instr_DEBUG > 3 ) { print Dumper("deleteData: r_propertiesColumnMap", $r_propertiesColumnMap); }
        my $r_propertiesOptionMap = $r_dataset->{'property_options'};
        if ( $Instr_DEBUG > 3 ) { print Dumper("deleteData: r_propertiesOptionMap", $r_propertiesOptionMap); }
        while (my ($column, $value) = each %{$r_propertiesColumnMap}) {
            if ( defined $r_propertiesOptionMap &&
                 exists $r_propertiesOptionMap->{$column} &&
                 exists $r_propertiesOptionMap->{$column}->{'ignorefordelete'} ) {
                $ignoreForDelete{$column} = 1;
            } else {
                my $r_values = $nameValues{$column};
                if ( ! defined $r_values ) {
                    $r_values = {};
                    $nameValues{$column} = $r_values;
                }
                $r_values->{$value} = 1;
            }
        }
    }
    if ( $Instr_DEBUG > 3 ) { print Dumper("deleteData: nameValues", \%nameValues); }
    if ( $Instr_DEBUG > 3 ) { print Dumper("deleteData: ignoreForDelete", keys %ignoreForDelete); }

    my $multiValued = undef;
    my @tableConstraints = ( "$timeColumn BETWEEN '$firstTimestamp' AND '$lastTimestamp'" );
    foreach my $column ( sort keys %nameValues ) {
        my $r_values = $nameValues{$column};
        if ( exists $ignoreForDelete{$column} ) {
            if ( $Instr_DEBUG > 3 ) { print "deleteData: ignoring $column\n"; }
            next;
        }
        # Quote non-numeric values
        my @values = keys %{$r_values};
        if ( $#values > 0 ) {
            if ( defined $multiValued ) {
                die "More then one multi-value property found: $multiValued and $column";
            } else {
                $multiValued = $column;
            }

            if ( $values[0] =~ /^[\d\.]+$/ ) {
                push @tableConstraints, "$column IN ( " . join(",",@values) . " )";
            } else {
                push @tableConstraints, "$column IN ( '" .  join("','",@values) . "' )";
            }
        } else {
            if ( $values[0] =~ /^[\d\.]+$/ ) {
                push @tableConstraints, "$column = " . $values[0];
            } else {
                push @tableConstraints, "$column = '" . $values[0] . "'";
            }
        }
    }
    my $query = "DELETE FROM $tableName WHERE  " . join(" AND ", @tableConstraints);
    dbDo($databaseHandler, $query) or die "Failed to delete from $tableName";
}

sub isIncremental($) {
    my ($instrFile) = @_;

    my $result = 0;
    if ( $instrFile =~ /\/instr.txt$/ ) {
        my $incrInstr = $instrFile . ".001";
        if ( $Instr_DEBUG ) { print "isIncremental: checking for $incrInstr\n"; }
        if ( -r $incrInstr ) {
            $result = 1;
        }
    }

    if ( $Instr_DEBUG ) { print "isIncremental: instrFile=$instrFile result=$result\n"; }
    return $result;
}

sub getIncrementFileSet($$$) {
    my ($instrFile, $fileIndex, $r_fileSet) = @_;

    my $incrInstr = undef;
    do {
        $fileIndex++;
        $incrInstr = $instrFile . sprintf(".%03d",$fileIndex);
        if ( -r $incrInstr ) {
            push @{$r_fileSet}, { 'file' => $incrInstr, 'offset' => 0 };
        }
    } while ( -r $incrInstr );

    my @metadata = stat($instrFile);
    if ( $metadata[7] > 0 ) {
        push @{$r_fileSet}, { 'file' => $instrFile, 'offset' => 0 };
    }
}

sub getMinMaxTimes($) {
    my ($r_datasets) = @_;

    my ($minTime, $maxTime, $startTimestamp, $endTimestamp);
    foreach my $r_dataset ( @{$r_datasets} ) {
        if ( $Instr_DEBUG > 4 ) {
            print "instrStoreDataSets - processing data with properties: ", Dumper($r_dataset->{'properties'});
            print "instrStoreDataSets - processing data with property_options: ", Dumper($r_dataset->{'property_options'});
        }

        my $useTimeStamp = 0;
        if ( exists $r_dataset->{'property_options'}->{'time'}->{'usetimestamp'} ) {
            $useTimeStamp = 1;
        }

        if ( (! defined $minTime) || ($r_dataset->{'samples'}->[0]->{'time'} < $minTime) ) {
            $minTime = $r_dataset->{'samples'}->[0]->{'time'};
            if ( $useTimeStamp ) {
                $startTimestamp = $r_dataset->{'samples'}->[0]->{'timestamp'};
            }
        }
        if ( (! defined $maxTime) || ($r_dataset->{'samples'}->[$#{$r_dataset->{'samples'}}]->{'time'} > $maxTime) ) {
            $maxTime = $r_dataset->{'samples'}->[$#{$r_dataset->{'samples'}}]->{'time'};
            if ( $useTimeStamp ) {
                $endTimestamp = $r_dataset->{'samples'}->[$#{$r_dataset->{'samples'}}]->{'timestamp'};
            }
        }
    }

    if ( ! defined $startTimestamp ) {
        $startTimestamp = formatTime($minTime, $StatsTime::TIME_SQL);
        $endTimestamp = formatTime($maxTime, $StatsTime::TIME_SQL);
    }

    # We will refuse to process any data older then this
    # This is to prevent us deleting data that has the wrong
    # timestamp (e.g. TORF-153166)
    if ( $minTime < $MIN_VALID_TIME || $maxTime < $MIN_VALID_TIME ) {
        die "Invalid time range: " . $startTimestamp . "->" . $endTimestamp;
    }

    return ($startTimestamp, $endTimestamp);
}

## Function that inputs multiple dataSets into the statsdb database.
##
## Parameters:
##  dbh: DB handle
##  tableName: String name of table to insert data to
##  siteName: Name of site the data is associated with
##  r_dataSets: An array of hash. In each has
##   'properties' => Reference to hash containing properties of dataset (e.g. associated JBoss instance number)
##      -format = {propertyName => value, ...}
##    'samples' => Reference to array of samples to add to database
##      -format = [
##          {
##              'time' => 1417788945,
##              'timestamp' => '05-12-14 14:15:45.212',
##              'metric1' => value,
##              'metric2' => value, ...
##          }, ...
##    'property_options' => Reference to hash, this is optional
##      -format = {propertyName => hash}
##        where hash contains the "options" for this property
##         Only option is 'ignorefordelete'
##      ]
##  r_metricToColumnMap: Reference to hash that relates metric keys in r_samples to associated table column names
##      -format = {"metricKey" => "columnName", ...}
##  deleteOld: Indicates whether to run an delete for the time range of the samples. If you're doing
##             incremently loading (i.e. you're sure that the data hasn't been loaded before), then
#              set this to 0, otherwise set to 1 (.i.e. perform the delete)
##
## Returns: hash reference containing metrics
##
sub instrStoreDataSets($$$$$$) {
    my ($dbh, $tableName, $siteName, $r_datasets, $r_metricToColumnMap, $deleteOld) = @_;

    my %metrics = ( 't_start' => Time::HiRes::time() );

    foreach my $r_dataset ( @{$r_datasets} ) {
        if ( $#{$r_dataset->{'samples'}} == -1 ) {
            print "WARN: No data";
            return;
        }
    }

    my $timeColumn = 'time';
    if ( exists $r_metricToColumnMap->{'time'} ) {
        $timeColumn = $r_metricToColumnMap->{'time'};
    }

    if ( defined $siteName ) {
        my $siteId = getSiteId($dbh, $siteName);
        ($siteId > -1 ) or die "Failed to retrieve site ID";

        if ( $Instr_DEBUG > 3 ) { print "instrStoreData - Site name: $siteName, Site ID: $siteId, Table: $tableName\n"; }

        foreach my $r_dataset ( @{$r_datasets} ) {
            $r_dataset->{'properties'}->{"siteid"} = $siteId; # Add site ID to properties map
        }
    }

    my ($startTimestamp, $endTimestamp) = getMinMaxTimes($r_datasets);

    if ( $Instr_DEBUG > 5 ) { print "instrStoreDataSets - Data time range: timeColumn=$timeColumn $startTimestamp - $endTimestamp\n"; }

    if ( $deleteOld ) {
        $metrics{'t_deletion_start'} = Time::HiRes::time();
        deleteData($dbh, $tableName, $startTimestamp, $endTimestamp, $r_datasets, $timeColumn);
        $metrics{'t_deletion_end'} = Time::HiRes::time();
    }

    $metrics{'t_store_start'} = Time::HiRes::time();
    storeNewData($dbh, $tableName, $timeColumn, $r_metricToColumnMap, $r_datasets);
    $metrics{'t_store_end'} = Time::HiRes::time();

    $metrics{'t_end'} = Time::HiRes::time();

    return \%metrics;
}

1;
