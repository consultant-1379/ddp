use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;
use Time::Local;

use File::Basename;
use Cwd 'abs_path';

our $THIS_DIR;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/sql";
}

require PartitionManager;

our $DEBUG = 0;

sub testEeEDelete() {
    StatsDB::testMode(sprintf("%s/statsdb_delete.json", $THIS_DIR));

    @ARGV = (
        '--action',
        'deleteold',
        '--date',
        '2023-12-01',
        '--config',
        sprintf("%s/%s", $THIS_DIR, "delete_base.json"),
        '--config',
        sprintf("%s/%s", $THIS_DIR, "delete_custom.json")
    );
    PartitionManager::main();
    my $result = StatsDB_TestConfig::errstr();
    ok( ! defined $result, 'Delete Partitions');
}

sub testDeleteConfig() {
    my @configFiles = (
        sprintf("%s/%s", $THIS_DIR, "delete_base.json"),
        sprintf("%s/%s", $THIS_DIR, "delete_custom.json")
    );
    my $r_config = PartitionManager::readConfigFiles(\@configFiles);

    # baseTime = 2023-12-01 1701388800
    my $baseTime = timelocal(0,0,0,1,11,123);
    is( PartitionManager::getDeleteTime("table1", $baseTime, $r_config), 1698796800, "Default is 2023-11-01");
    is( PartitionManager::getDeleteTime("table2", $baseTime, $r_config), 1669852800, "Long retention table 2022-12-01");
    is( PartitionManager::getDeleteTime("table3", $baseTime, $r_config), 1696118400, "Custom 2 month  retention table 2023-10-01");
    is( PartitionManager::getDeleteTime("table4", $baseTime, $r_config), 1688169600, "Overridden 5 month  retention table 2023-07-01");
}

testDeleteConfig();
testEeEDelete();

done_testing();



