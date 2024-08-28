use strict;
use warnings;

use Data::Dumper;
use JSON;

use Test::More;
use Test::Exception;

use File::Basename;
use Cwd 'abs_path';
our $THIS_DIR = dirname($0);
our $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../main/resources/analysis");
our $PARSECONFIG_MODULE = $ANALYSIS_DIR . "/k8s/ParseConfig.pm";
require $PARSECONFIG_MODULE;

use StatsDB;

our $DEBUG = 0;

sub createFile($) {
    my ($path) = @_;

    open FILE, ">$path";
    close FILE;

    return $path;
}

sub removeFiles($) {
  my ($r_paths) = @_;
  foreach my $path ( @{$r_paths} ) {
      unlink $path;
  }
}

sub test_getFiles($$$$$) {
    my ($dir, $r_incr, $fileBase, $incrKey, $r_expectedResults) = @_;
    my $r_results = ParseConfig::getFiles($dir, $r_incr, $fileBase, $incrKey);
    my $result = 1;
    if ( $#{$r_results} != $#{$r_expectedResults} ) {
        $result = 0;
    } else {
        for ( my $resultIndex = 0; $resultIndex <= $#{$r_results}; $resultIndex++ ) {
            if ( $r_results->[$resultIndex] ne $r_expectedResults->[$resultIndex] ) {
                $result = 0;
            }
        }
    }

    if ( $result == 0 ) {
      printf "Expected %s != Actual %s\n", join(",", @{$r_expectedResults}), join(",", @{$r_results});
    }
    return $result;
}

sub test_parseEvents($$$) {
    my ($testDir, $r_incr, $expected) = @_;

    my $r_rawEvents = ParseConfig::parseEvents($testDir, $r_incr);
    my $testResult = 1;
    if ( ($#{$r_rawEvents} + 1) != $expected ) {
        printf("Expected Events (%d) != Actual Events (%d)\n", $expected, ($#{$r_rawEvents} + 1));
        $testResult = 0;
    }

    return $testResult;
}

sub test_processHelm($$$$) {
    my ($dir, $r_Incr, $date, $r_expected) = @_;

    my $r_delta = ParseConfig::parseDelta($dir, $r_Incr);
    my $r_helmUpdates = ParseConfig::processHelm($r_delta->{'helm'}, $r_Incr, $date);
    if ( $DEBUG > 3 ) { print Dumper("test_processHelm: r_helmUpdates", $r_helmUpdates); }
    my $testResult = 1;
    if ( $#{$r_helmUpdates} != $#{$r_expected} ) {
        printf(
            "Mismatch in expected counts, expected %d found %d\n",
            $#{$r_expected} + 1,
            $#{$r_helmUpdates} + 1
        );
        $testResult = 0;
    } else {
        for ( my $index = 0; $index <= $#{$r_helmUpdates}; $index++ ) {
            if ( $DEBUG > 3 ) {
                printf(
                    "test_processHelm: index=%d uid=%s\n",
                    $index,
                    $r_helmUpdates->[$index]->{'metadata.uid'}
                );
            }
            if ( $r_helmUpdates->[$index]->{'metadata.uid'} ne $r_expected->[$index] ) {
                printf(
                    "Mismatch in uid index=%d, expected %s found %s\n",
                    $index,
                    $r_expected->[$index],
                    $r_helmUpdates->[$index]->{'metadata.uid'}
                );
                $testResult = 0;
            }
        }
    }

    return $testResult;
}

sub test_processHelmHooks($$$) {
    my ($dir, $date, $r_expected) = @_;

    my %incr = ();
    my $r_delta = ParseConfig::parseDelta($dir, \%incr);
    my $r_helmUpdates = ParseConfig::processHelm($r_delta->{'helm'}, \%incr, $date);
    my $start = @{$r_helmUpdates}[0]->{'data.release.hooks'}[0]->{'start'};
    my $end = @{$r_helmUpdates}[0]->{'data.release.hooks'}[0]->{'end'};
    my $testResult = 1;

    my $eStart = $r_expected->{'start'};
    my $eEnd = $r_expected->{'end'};

    if ( $start ne $eStart || $end ne $eEnd ) {
        $testResult = 0;
        printf(
            "Mismatch in time(s): Start: expected %s found %s, End: expected %s found %s\n",
            $eStart,
            $start,
            $eEnd,
            $end
        );
    }

    return $testResult;
}

sub test_processEventsForChart($$$$) {
    my ($dir, $date, $r_expected, $rr_firstResult) = @_;

    my %incr = ();

    my $r_delta = ParseConfig::parseDelta($dir, \%incr);
    my $r_helmUpdates = ParseConfig::processHelm($r_delta->{'helm'}, \%incr, $date);
    my $r_podStatusByName = ParseConfig::processPodStatus($r_delta->{'podstatus'}, \%incr);
    my %podToChart = (
        '49485390-27aa-4718-8ced-bcaf0912bdc2' => 'eric-enm-infra-integration-enm420',
        '87d21a56-e58f-4696-80d2-0689a0da67ad' => 'eric-enm-infra-integration-enm420',
        'f0d2c78a-c4b1-4263-8731-ba30a2b9841f' => 'eric-enm-infra-integration-enm420',
        '91742142-db4c-4415-8026-ff77107cc26f' => 'eric-enm-infra-integration-enm420'
    );
    my $r_result = ParseConfig::processEventsForChart($r_helmUpdates->[0], $r_delta->{'events'}, $r_podStatusByName, \%podToChart);

    ${$rr_firstResult} = $r_result;

    my $testResult = 1;
    if ( ! defined $r_expected ) {
        if ( defined $r_result ) {
            printf("Expected no result but got one\n");
            print Dumper($r_result);
            $testResult = 0;
        }
    } else {
        while ( my ($expectedKey, $expectedValue) = each %{$r_expected} ) {
            my $resultValue = $r_result->{$expectedKey};
            if ( defined $resultValue ) {
                if ( $resultValue ne $expectedValue ) {
                    printf("Expected %s for %s, got %s\n", $expectedValue, $expectedKey, $resultValue);
                    $testResult = 0;
                }
            } else {
                printf("Expected value for %s, none found\n", $expectedKey);
                $testResult = 0;
            }
        }
    }

    return $testResult;
}

sub test_writeHelmUpdate($$$$$) {
    my ($r_helmUpdate, $dir, $expectedFile, $expectedPodRows, $expectedHookRows) = @_;

    ParseConfig::writeHelmUpdate($r_helmUpdate, $dir);

    my $testResult = 1;
    if ( -r $expectedFile ) {
        open INPUT, $expectedFile or die "Cannot open $expectedFile";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        my $r_data = decode_json($file_content);
        my $r_podRows = $r_data->{'pods'};
        if ( ($#{$r_podRows} + 1) != $expectedPodRows ) {
            printf("Expected %d pod rows in %s, found %d\n", $expectedPodRows, $expectedFile, ($#{$r_podRows} + 1));
            $testResult = 0;
        }
        my $r_hookRows = $r_data->{'hooks'};
        if ( ($#{$r_hookRows} + 1) != $expectedHookRows ) {
            printf("Expected %d hook rows in %s, found %d\n", $expectedHookRows, $expectedFile, ($#{$r_hookRows} + 1));
            $testResult = 0;
        }
    } else {
        print "Could not find file $expectedFile\n";
        $testResult = 0;
    }

    return $testResult;
}

sub test_ProcessEvents($$) {
    my ($testDir, $r_testData) = @_;

    if ( $DEBUG > 1 ) { printf("test_ProcessEvents: deltafile=%s\n", $r_testData->{'deltafile'}); }
    my $inputFile = sprintf("%s/processevents/%s", $THIS_DIR, $r_testData->{'deltafile'});
    system("cp $inputFile $testDir/delta.json.001");

    my $r_inputData = ParseConfig::parseDelta($testDir, {});
    unlink "$testDir/delta.json.001";

    my $saveTz = undef;
    if ( exists $r_testData->{'timezone'} ) {
        $saveTz = $ENV{'SITE_TZ'};
        $ENV{'SITE_TZ'} = $r_testData->{'timezone'};
        $StatsTime::SITE_TZ = undef;
    }
    my $r_haEvents = ParseConfig::processEvents(
        $r_inputData->{'events'},
        {}, # r_incr
        {}, # r_nodes
        [], # r_pods
        {}, # r_replicaSets
        $testDir
    );
    if ( defined $saveTz ) {
        $ENV{'SITE_TZ'} = $saveTz;
        $StatsTime::SITE_TZ = undef;
    }

    my $testResult = 1;
    my $expectedFile = $testDir . "/k8s_events.json";

    if ( exists $r_testData->{'expected_output_events'} ) {
        if ( -r $expectedFile ) {
            open INPUT, $expectedFile or die "Cannot open $expectedFile";
            my $file_content = do { local $/; <INPUT> };
            close INPUT;
            my $r_outputEvents = decode_json($file_content);
            if ( ! is_deeply($r_outputEvents, $r_testData->{'expected_output_events'}, $r_testData->{'desc'} . " output events match") ) {
                print Dumper("is_deeply failed", $r_outputEvents, $r_testData->{'expected_output_events'});
                $testResult = 0;
            }
        } else {
            ok( 0, $r_testData->{'desc'} . " output file exists");
            $testResult = 0;
        }
    }
    unlink $expectedFile;

    if ( exists $r_testData->{'expected_ha_events'} ) {
        if ( ! is_deeply($r_haEvents, $r_testData->{'expected_ha_events'}, $r_testData->{'desc'} . " HA events match") ) {
            print Dumper("is_deeply failed", $r_haEvents, $r_testData->{'expected_ha_events'});
            $testResult = 0;
        }
    }

    return $testResult;
}

sub test_StoreHaEvents() {
    my @haEvents = (
        {
            'pod' => 'pe-testware-k6-httpd',
            'worker' => 'node-10-156-157-29',
            'container' => 'pe-testware-csars',
            'time' => '2022-12-22 00:04:10',
            'type' => 'Kill',
            'timestamp' => 1671667450
        }
    );
    my @nodes = (
        {
            'hostname' => 'node-10-156-157-29',
            'serverid' => 1
        }
    );
    StatsDB::testMode($THIS_DIR . "/statsdb_storeHaEvents.json");
    my $dbh = connect_db();
    ParseConfig::storeHaEvents($dbh, 1, \@haEvents, \@nodes);
    # If we get this far then everything worked okay
    ok( 1, "Store HA Event");
}

$ENV{'SITE_TZ'} = 'Europe/Dublin';
our $testDir = "/tmp/parseConfig" . sprintf("%d", time());
mkdir $testDir;

sub test_mapPodNameDies() {
    my %pod = (
        'name' => 'eric-pm-node-exporter-49mrk',
        'app' => 'eric-pm-node-exporter',
        'kind' => 'ReplicaSet',
    );

    my %mappedPodByServerName = ();

    for(my $i = 0; $i <= 510; $i++){
        $mappedPodByServerName{ sprintf( "eric-pm-node-exporter-%02d", $i ) } = [];
    }

    # test that ParseConfig::mapPodName lives with 511 values
    lives_ok { ParseConfig::mapPodName(\%pod, \%mappedPodByServerName) } 'Test: ParseConfig::mapPodName Lives as expected';

    $mappedPodByServerName{"eric-pm-node-exporter-511"} = [];
    # test that ParseConfig::mapPodName dies with 512 values
    dies_ok{ ParseConfig::mapPodName(\%pod, \%mappedPodByServerName) } 'Test: ParseConfig::mapPodName Dies as expected';
}

sub test_mapPodName() {
    my %podA = (
        'name' => 'sep-mt-123',
        'app' => 'sep',
        'kind' => 'ReplicaSet',
        'replicaset' => {
            'deployment' => {
                'name' => 'sep-mt-456'
            }
        }
    );

    my %podB = (
        'name' => 'eric-123',
        'app' => 'eric-A',
        'kind' => 'ReplicaSet',
        'replicaset' => {
            'deployment' => {
                'name' => 'eric-B'
            }
        }
    );

    my $got = ParseConfig::mapPodName(\%podA, \());
    my $expected = 'sep-00';
    # Tests that when we see 'sep' in the app name we don't use the replicaset deployment name
    ok($got eq $expected, 'Test mapPodName when filtering');

    $got = ParseConfig::mapPodName(\%podB, \());
    $expected = 'eric-B-00';
    # Tests that when we don't see 'sep' in the app name we use the replicaset deployment name
    ok($got eq $expected, 'Test mapPodName when not filtering');
}

#
# Test mapPodName
#

test_mapPodNameDies();
test_mapPodName();

#
# Test getFiles
#

my %incr = ();
my @eventFiles = ();
push @eventFiles, createFile($testDir . "/events.json.001");
push @eventFiles, createFile($testDir . "/events.json.002");
ok( test_getFiles($testDir, \%incr, "events.json", "fileIndex", \@eventFiles),
    "Two events.json files in dir, Return two events.json files" );
ok( test_getFiles($testDir, \%incr, "events.json", "fileIndex", []),
    "Two events.json files in dir, Return no files as in incr" );
push @eventFiles, createFile($testDir . "/events.json.003");
ok( test_getFiles($testDir, \%incr, "events.json", "fileIndex", [ $eventFiles[$#eventFiles] ]),
    "Three events.json files in dir, Return events.json.003");

removeFiles(\@eventFiles);

#
# Test parsing K8S events
#
# Remove fileIndex value from earlier testing
delete $incr{'fileIndex'};
system("cp $THIS_DIR/events.json.001 $testDir/events.json.001");
ok( test_parseEvents($testDir, \%incr, 17),
    "Test parsing of events.json.001 with 17 events");
system("cp $THIS_DIR/events.json.002 $testDir/events.json.002");
ok( test_parseEvents($testDir, \%incr, 24),
    "Test parsing of events.json.002 with 24 events");

unlink "$testDir/events.json.001";
unlink "$testDir/events.json.002";

#
# Test processing of Delta data
#
system("cp $THIS_DIR/delta.json.001 $testDir/delta.json.001");
system("cp $THIS_DIR/delta.json.002 $testDir/delta.json.002");
my @expectedProcessed = (
    '1570286e-15a9-43f0-904e-a5b871c6143a',
);
my @helmSessions = ();
ok( test_processHelm($testDir, \%incr, "2021-05-11", \@expectedProcessed),
    "Test processHelm");

my %expectedHelmResult = (
    'start' => '2021-05-11 17:40:46',
    'name' => 'eric-enm-infra-integration',
    'end' => '2021-05-11 17:53:59',
    'toVersion' => '1.10.0-32',
);
my $r_firstResult = undef;
ok( test_processEventsForChart($testDir, '2021-05-11', \%expectedHelmResult, \$r_firstResult),
    "Test processing of chart");

unlink "$testDir/delta.json.001";
unlink "$testDir/delta.json.002";

# Note there are 5 neo4j pods in the delta files but one is a job and should be
# dropped
my $expectedFile = $testDir . "/helm-eric-enm-infra-integration-1620752039.json";
my $expectedNumPodRows = 4;
my $expectedNumHookRows = 7;
ok( test_writeHelmUpdate($r_firstResult, $testDir, $expectedFile, $expectedNumPodRows, $expectedNumHookRows),
    "Test writing output to file");
unlink $expectedFile;

#
# Test time zone handling for helm charts
#
my $saveTz = $ENV{'SITE_TZ'};
$ENV{'SITE_TZ'} = 'America/Los_Angeles';
$StatsTime::SITE_TZ = undef;

system("cp $THIS_DIR/helm_timezone.json $testDir/delta.json.001");
my %tzExpectedHelmResult = (
    'start' => '2021-10-14 02:19:32',
    'name' => 'eric-enm-bro-integration',
    'end' => '2021-10-14 02:19:32',
    'toVersion' => '1.11.7-0',
);
my $dummy = undef;
ok( test_processEventsForChart($testDir, '2021-10-14', \%tzExpectedHelmResult, \$dummy),
    "Test processing of chart timezone");

$ENV{'SITE_TZ'} = $saveTz;
$StatsTime::SITE_TZ = undef;

#
# Test time zone handling for helm charts with hooks
#
$saveTz = $ENV{'SITE_TZ'};
$ENV{'SITE_TZ'} = 'Europe/Stockholm';
$StatsTime::SITE_TZ = undef;

system("cp $THIS_DIR/helm_timezone_hook.json $testDir/delta.json.001");
my %expected = (
    'end' => '2024-01-17 19:47:35',
    'start' => '2024-01-17 19:46:31'
);

ok( test_processHelmHooks( $testDir, '2024-01-17', \%expected ),
    "Test processing of chart timezone with hooks");

$ENV{'SITE_TZ'} = $saveTz;
$StatsTime::SITE_TZ = undef;

#
# Verify we ignore a chart where helm test is run days after
# the chart was installed/upgraded
#
$saveTz = $ENV{'SITE_TZ'};
$ENV{'SITE_TZ'} = 'Europe/Stockholm';
$StatsTime::SITE_TZ = undef;

system("cp $THIS_DIR/helm_testold.json $testDir/delta.json.001");
ok( test_processHelm($testDir, {}, "2023-09-29", []),
    "Test processing of old chart with helm test run is discarded");

system("cp $THIS_DIR/helm_test_today.json $testDir/delta.json.001");
my %helmTestExpectedResult = (
    'start' => '2023-09-29 10:04:51',
    'name' => 'eric-enm-pre-deploy-integration',
    'end' => '2023-09-29 10:08:16',
    'toVersion' => '1.46.3-1',
);
ok( test_processEventsForChart($testDir, '2023-09-29', \%helmTestExpectedResult, \$dummy),
    "Test processing of chart with helm test run ignores test hook for timing");

$ENV{'SITE_TZ'} = $saveTz;
$StatsTime::SITE_TZ = undef;

#
# ProcessEvents testing
#
our $PROCESS_EVENTS_DIR = $THIS_DIR . "/processevents";
opendir(DIR, $PROCESS_EVENTS_DIR) or die "Failed to open $PROCESS_EVENTS_DIR";
my @files = grep(/^test.*\.json$/,readdir(DIR));
closedir(DIR);

foreach my $jsonFile ( @files ) {
    my $filePath = $PROCESS_EVENTS_DIR . "/" . $jsonFile;
    open INPUT, $filePath or die "Cannot open $filePath";
    my $file_content = do { local $/; <INPUT> };
    close INPUT;
    my $r_testData = decode_json($file_content);

    if ( $DEBUG > 1 ) { printf("jsonFile: %s\n", $jsonFile); }
    test_ProcessEvents($testDir, $r_testData);
}

rmdir $testDir;

test_StoreHaEvents();

done_testing();

