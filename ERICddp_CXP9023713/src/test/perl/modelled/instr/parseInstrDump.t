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

require ParseInstrDump;

our $MODEL_DIR = $THIS_DIR. "/loadmetrics";

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

sub runTest($$) {
    my ($r_testData, $testDir) = @_;

    if ( $DEBUG > 1 ) { printf("runTest: model=%s\n", $r_testData->{'model'}); }

    my $dumpIndex = 1;
    my @outputFiles = ();
    foreach my $dumpFile ( @{$r_testData->{'dump_files'}} ) {
        my $inputFile = $MODEL_DIR . "/" . $dumpFile;
        my $outputFile = sprintf("%s/dump.%03d.gz", $testDir, $dumpIndex);
        if ( $DEBUG > 1 ) { printf("runTest: inputFile=%s outputFile=%s\n", $inputFile, $outputFile); }
        $dumpIndex++;
        system("gzip -c  $inputFile > $outputFile");
        push @outputFiles, $outputFile;
    }

    my $r_models = ModelledInstr::loadModels([$MODEL_DIR . "/" . $r_testData->{'model'}]);

    my $r_prometheusMetrics = ParseInstrDump::getPrometheusMetrics($r_models);

    my $r_results = ParseInstrDump::loadMetrics(
        $testDir,
        {},
        $r_testData->{'instmap'},
        $r_prometheusMetrics,
        $r_testData->{'namespace'},
        undef,
        "site"
    );
    if ( ! is_deeply($r_results, $r_testData->{'expected'}, $r_testData->{'desc'}) ) {
        print Dumper("is_deeply failed", $r_results, $r_testData->{'expected'});
    } else {
        foreach my $outputFile ( @outputFiles ) {
            unlink $outputFile;
        }
    }
}

sub e2e_test($$) {
    my ($r_testData, $testDir) = @_;

    my $dumpIndex = 1;
    my @outputFiles = ();
    foreach my $dumpFile ( @{$r_testData->{'dump_files'}} ) {
        my $inputFile = $THIS_DIR . "/e2e/data/" . $dumpFile;
        my $outputFile = sprintf("%s/dump.%03d.gz", $testDir, $dumpIndex);
        if ( $DEBUG > 1 ) { printf("e2e_test: inputFile=%s outputFile=%s\n", $inputFile, $outputFile); }
        $dumpIndex++;
        system("gzip -c  $inputFile > $outputFile");
        push @outputFiles, $outputFile;
    }

    # Make sure any bcp files are removed so that later when we're checking them
    # we can be sure this test run created them
    foreach my  $bcpFile ( @{$r_testData->{'bcp_files'}} ) {
        my $fileName = basename($bcpFile);
        unlink("/tmp/" . $fileName);
    }

    @ARGV = (
        '--site',
        'Test',
        '--data',
        $testDir,
        '--model',
        sprintf("%s/e2e/model/%s", $THIS_DIR, $r_testData->{'model_dir'}),
        '--date',
        $r_testData->{'date'},
        '--k8snamespace',
        'test'
    );

    StatsDB::testMode(sprintf("%s/e2e/statsdb/%s", $THIS_DIR, $r_testData->{'statsdb'}));

    ParseInstrDump::main();

    foreach my $outputFile ( @outputFiles ) {
        unlink $outputFile;
    }

    my $result = 1;
    foreach my  $bcpFile ( @{$r_testData->{'bcp_files'}} ) {
        my $fileName = basename($bcpFile);
        my $diffResult = system("diff --brief /tmp/$fileName $THIS_DIR/e2e/statsdb/$bcpFile");
        if ( $diffResult != 0 ) {
            print "ERROR: diff failed for $bcpFile\n";
            $result = 0;
        }
    }

    ok( $result, "e2e: " . $r_testData->{'description'} );
}

sub e2eTests($) {
    my ($testDir) = @_;

    $ENV{'SITE_TZ'} = 'Europe/Dublin';
    $ENV{'TMP_DIR'} = '/tmp';

    my $e2eDir = sprintf("%s/e2e/", $THIS_DIR);
    my @files = ();
    opendir(DIR, $e2eDir) or die "Failed to open $e2eDir";
    foreach my $file (readdir(DIR)) {
        if ( $file =~ /test_.*.json$/ ) {
            push @files, $file;
        }
    }
    closedir(DIR);

    foreach my $jsonFile ( @files ) {
        my $filePath = $e2eDir . "/" . $jsonFile;
        open INPUT, $filePath or die "Cannot open $filePath";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        my $r_testData = decode_json($file_content);

        e2e_test($r_testData, $testDir);
    }
}

our $testDir = "/tmp/parseInstrDump" . sprintf("%d", time());
mkdir $testDir;

my @files = ();
opendir(DIR, $MODEL_DIR) or die "Failed to open $MODEL_DIR";
foreach my $file (readdir(DIR)) {
    if ( $file =~ /.json$/ && $file !~ /^dump/ ) {
        push @files, $file;
    }
}
closedir(DIR);

$DEBUG = 0;
foreach my $jsonFile ( @files ) {
    my $filePath = $MODEL_DIR . "/" . $jsonFile;
    open INPUT, $filePath or die "Cannot open $filePath";
    my $file_content = do { local $/; <INPUT> };
    close INPUT;
    my $r_testData = decode_json($file_content);

    if ( $DEBUG > 1 ) { printf("jsonFile: %s\n", $jsonFile); }
    runTest($r_testData, $testDir);
}

e2eTests($testDir);

done_testing();
