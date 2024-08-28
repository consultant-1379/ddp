use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;

use File::Basename;
use Cwd 'abs_path';

use Instr;

our $THIS_DIR;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/modelled/instr";
}

require ModelledInstr;

our $MODEL_DIR = $THIS_DIR. "/loadmodels";
our $PROC_DATASET_DIR = $THIS_DIR. "/processdataset";

our $DEBUG = 0;

sub getBranch($$$);
sub getBranch($$$) {
    my ($r_parent, $r_path, $pathIndex) = @_;

    if ( $DEBUG > 2 ) { printf("getBranch: pathIndex=%d ref=%s\n", $pathIndex, ref $r_parent); }
    if ( $DEBUG > 5 ) { print Dumper("getBranch: parent", $r_parent); }

    if ( $pathIndex > $#{$r_path} ) {
        return $r_parent;
    } else {
        my $r_child = undef;
        if (ref $r_parent eq 'ARRAY') {
            $r_child = $r_parent->[$r_path->[$pathIndex]];
        } else {
            $r_child = $r_parent->{$r_path->[$pathIndex]};
        }
        if ( defined $r_child ) {
            $pathIndex++;
            return getBranch($r_child, $r_path, $pathIndex);
        } else {
            return undef;
        }
    }
}

sub runTest($) {
    my ($r_testData) = @_;

    if ( $DEBUG > 1 ) { printf("runTest: model=%s\n", $r_testData->{'model'}); }
    my $r_returnValue = ModelledInstr::loadModels([$MODEL_DIR . "/" . $r_testData->{'model'}])->[0];

    foreach my $r_check ( @{$r_testData->{'checks'}} ) {
        my $r_branchToCheck = getBranch($r_returnValue, $r_check->{'path'}, 0);
        if ( ! is_deeply($r_branchToCheck, $r_check->{'expected'}, $r_check->{'desc'}) ) {
            print Dumper("is_deeply failed", $r_branchToCheck, $r_check->{'expected'});
        }
    }
}

sub testProcessDataSet($) {
    my ($r_testData) = @_;

    if ( $DEBUG > 1 ) { printf("runTest: model=%s\n", $r_testData->{'model'}); }
    my $r_model = ModelledInstr::loadModels([$PROC_DATASET_DIR . "/" . $r_testData->{'model'}])->[0];

    my %incr = ();
    my $r_cliArgs = $r_testData->{'cliArgs'};
    my $dbh = undef;
    my $r_columnsToDelta = $r_testData->{'delta'};
    my $r_columnsToRate = $r_testData->{'rate'};
    my $r_columnsToFilterIdle = $r_testData->{'filteridle'};
    my $r_columnsToFilterStatic = $r_testData->{'filterstatic'};
    my $r_columnsToFilterValue = $r_testData->{'filtervalue'};
    my $r_columnsToScale = $r_testData->{'scale'};
    my $r_columnMap = $r_testData->{'columnmap'};

    #$DEBUG = 11;
    #setInstr_Debug($DEBUG);

    my $runIndex = 0;
    foreach my $r_run( @{$r_testData->{'runs'}} ) {
        $runIndex++;
        my $r_expected = $r_run->{'output'};
        my $r_actual = ModelledInstr::processDataSet(
            $r_run->{'input'},
            \%incr,
            "incrKey",
            $r_cliArgs,
            $r_model,
            $dbh,
            $r_columnsToDelta,
            $r_columnsToRate,
            $r_columnsToFilterIdle,
            $r_columnsToFilterStatic,
            $r_columnsToScale,
            $r_columnsToFilterValue,
            $r_columnMap
        );

        if ( ! is_deeply($r_expected, $r_actual, $r_testData->{'desc'} . " run " . $runIndex) ) {
            print Dumper("actual", $r_actual);
        }
    }
}

sub test_applySampleInterval() {
    my @samples = (
        {
            'timestamp' => '2021-10-20 23:59:33',
            'time' => 1634770773,
            'pg_database_size_bytes:-pg_database_size_bytes' => 19987591
        },
        {
            'timestamp' => '2021-10-21 00:00:33',
            'time' => 1634770833,
            'pg_database_size_bytes:-pg_database_size_bytes' => 20159623
        },
        {
            'timestamp' => '2021-10-21 00:01:33',
            'time' => 1634770893,
            'pg_database_size_bytes:-pg_database_size_bytes' => 20184199
        }
    );
    my $sampleInterval = 86400;
    my $prevSampleTime = undef;

    my @expectedResult = (
        {
            'timestamp' => '2021-10-21 00:00:33',
            'time' => 1634770833,
            'pg_database_size_bytes:-pg_database_size_bytes' => 20159623
        }
    );

    $ENV{'SITE_TZ'} = 'Europe/Dublin';
    my $r_actualResult = ModelledInstr::applySampleInterval(\@samples, $sampleInterval, $prevSampleTime, '2021-10-21');
    my $testResult = is_deeply(
        $r_actualResult,
        \@expectedResult,
        "applySampleInterval returns samples for today"
    );
    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_actualResult);
    }
}

sub runTestCases($$) {
    my ($dirPath, $r_testFn) = @_;

    opendir(DIR, $dirPath) or die "Failed to open $MODEL_DIR";
    my @files = grep(/\.json$/,readdir(DIR));
    closedir(DIR);

    foreach my $jsonFile ( @files ) {
        my $filePath = $dirPath . "/" . $jsonFile;
        open INPUT, $filePath or die "Cannot open $filePath";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        my $r_testData = decode_json($file_content);

        if ( $DEBUG > 1 ) { printf("jsonFile: %s\n", $jsonFile); }
        $r_testFn->($r_testData);
    }
}

sub test_disabledModels() {
    my $r_returnValue = ModelledInstr::loadModels([$MODEL_DIR], $THIS_DIR . "/disabled.json");
    ok( $#{$r_returnValue} == 0, "Verify disabledModels drops model");
}

test_applySampleInterval();

runTestCases($MODEL_DIR, \&runTest);

runTestCases($PROC_DATASET_DIR, \&testProcessDataSet);

test_disabledModels();

done_testing();
