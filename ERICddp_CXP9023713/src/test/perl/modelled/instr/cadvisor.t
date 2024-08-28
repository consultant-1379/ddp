use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;

use File::Basename;
use Cwd 'abs_path';

our $THIS_DIR;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/modelled/instr";
}

require CAdvisor;

our $DEBUG = 0;

sub test_handleMetric($) {
    my ($r_testData) = @_;

    my %metricsByInstance = ();

    my $r_ts = $r_testData->{'ts'};
    my $r_instanceMap = $r_testData->{'instance_map'};
    CAdvisor::handleMetric(
        \%metricsByInstance,
        $r_instanceMap,
        $r_ts,
        $r_ts->{'Labels'},
        $r_ts->{'Labels'}->{'__name__'},
        {}
    );

    my $testResult = 1;
    my $r_expected = $r_testData->{'expected'};
    if ( ! is_deeply(\%metricsByInstance, $r_expected, $r_testData->{'desc'}) ) {
        print Dumper("is_deeply failed", \%metricsByInstance, $r_expected);
    }
}

our $TEST_DATA_DIR = $THIS_DIR . "/cadvisor";
opendir(DIR, $TEST_DATA_DIR) or die "Failed to open $TEST_DATA_DIR";
my @files = grep(/^test.*\.json$/,readdir(DIR));
closedir(DIR);

foreach my $jsonFile ( @files ) {
    my $filePath = $TEST_DATA_DIR . "/" . $jsonFile;
    open INPUT, $filePath or die "Cannot open $filePath";
    my $file_content = do { local $/; <INPUT> };
    close INPUT;
    my $r_testData = decode_json($file_content);

    if ( $DEBUG > 1 ) { printf("jsonFile: %s\n", $jsonFile); }
    test_handleMetric($r_testData);
}

done_testing();
