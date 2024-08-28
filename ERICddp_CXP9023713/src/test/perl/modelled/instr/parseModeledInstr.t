use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;

use File::Basename;
use Cwd 'abs_path';

our $THIS_DIR;
our $DEBUG = 0;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/modelled/instr";
}

require ParseModeledInstr;

sub e2e_test($$) {
    my ($r_testData, $testDir) = @_;

    # Make sure any bcp files are removed so that later when we're checking them
    # we can be sure this test run created them
    foreach my  $bcpFile ( @{$r_testData->{'bcp_files'}} ) {
        my $fileName = basename($bcpFile);
        unlink("/tmp/" . $fileName);
    }

    print Dumper($r_testData);

    @ARGV = (
        '--site',
        'Test',
        '--server',
        'simple-01',
        '--service',
        $r_testData->{'service'},
        '--cfg',
        sprintf("%s/e2e_instr/cfg/%s", $THIS_DIR, $r_testData->{'cfg_dir'}),
        '--data',
        sprintf("%s/e2e_instr/data/%s", $THIS_DIR, $r_testData->{'data_file'}),
        '--model',
        sprintf("%s/e2e_instr/model/%s", $THIS_DIR, $r_testData->{'model_dir'}),
        '--date',
        $r_testData->{'date'}
    );

    StatsDB::testMode(sprintf("%s/e2e_instr/statsdb/%s", $THIS_DIR, $r_testData->{'statsdb'}));

    ParseModeledInstr::main();

    my $result = 1;
    foreach my  $bcpFile ( @{$r_testData->{'bcp_files'}} ) {
        my $fileName = basename($bcpFile);
        my $diffResult = system("diff --ignore-all-space --brief /tmp/$fileName $THIS_DIR/e2e_instr/statsdb/$bcpFile");
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

    my $e2eDir = sprintf("%s/e2e_instr/", $THIS_DIR);
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

our $testDir = "/tmp/parseModelledInstr" . sprintf("%d", time());
mkdir $testDir;

e2eTests($testDir);

done_testing();
