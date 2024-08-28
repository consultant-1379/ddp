use strict;
use warnings;

use Data::Dumper;
use StatsTime;

use Test::More;

use File::Basename;
use Cwd 'abs_path';
our $THIS_DIR = dirname($0);
our $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis");
our $PARSEFLS_MODULE = $ANALYSIS_DIR . "/TOR/pm/ParseFLS.pm";
require $PARSEFLS_MODULE;

our $DEBUG = 0;

ParseFLS::parsePmFileCollectionCategories($ANALYSIS_DIR . "/TOR/pm/FileCollectionCategory.json");
$ENV{'SITE_TZ'} = 'Europe/Dublin';

sub test_extractFileCollectionStats($$$$$$$) {
    my ($neType, $dataType, $transferType, $ropStart, $date, $shouldHave, $ropIntervalMins) = @_;

    my $delay = 0;
    my $ropIntervalSecs = $ropIntervalMins * 60;
    if ( $transferType eq 'PULL' ) {
        $delay = 300;
    }

    my @allTxfr = (
        [
            'NE',
            $neType,
            $dataType,
            1024,
            $ropStart + $ropIntervalSecs + $delay,
            $ropStart,
            $ropStart + $ropIntervalSecs,
            $transferType
        ]
    );

    my $r_fccInfoByTxTypeRopPeriod = ParseFLS::extractFileCollectionStats(\@allTxfr, {}, $date);

    if ( $::DEBUG ) { print Dumper("test_extractFileCollectionStats: r_fccInfoByTxTypeRopPeriod", $r_fccInfoByTxTypeRopPeriod); }

    if ( $shouldHave ) {
        my $r_fccList = $r_fccInfoByTxTypeRopPeriod->{$transferType}->{$ropIntervalMins};
        if ( ! defined $r_fccList || $#{$r_fccList} != 0 ) {
            print "test_extractFileCollectionStats: Failed get expected FCC\n";
            return 0;
        }

        # Verify start time of the file collection cycle
        my $gotFirstStart = $r_fccList->[0]->{'start'};
        my $expectedFirstStart = $ropStart + $ropIntervalSecs + $delay;
        my $infoString = sprintf(
                "test_extractFileCollectionStats: gotFirstStart=%s (%d) expectedFirstStart=%s (%d)\n",
                formatTime($gotFirstStart, $StatsTime::TIME_SQL),
                $gotFirstStart,
                formatTime($expectedFirstStart, $StatsTime::TIME_SQL),
                $expectedFirstStart
            );
        if ( $::DEBUG ) { print $infoString; }
        if ( $gotFirstStart != $expectedFirstStart ) {
            print "FAIL: ", $infoString;
            return 0;
        }

        my $r_firstCycle = $r_fccList->[0]->{'ne_types'}->{$neType}->{$dataType};
        if ( $::DEBUG ) { print Dumper("test_extractFileCollectionStats: r_firstCycle", $r_firstCycle); }
        if ( ! defined $r_firstCycle || $r_firstCycle->{'files'} != 1 || $r_firstCycle->{'outside'} != 0 ) {
            print "test_extractFileCollectionStats: Failed stats for neType/dataType\n";
            return 0;
        }
        return 1;
    } else {
        if ( exists $r_fccInfoByTxTypeRopPeriod->{$transferType}->{$ropIntervalMins} ) {
            return 0;
        } else {
            return 1;
        }
    }
}

our $ROP_START_20200101_2330 = 1577921400;
our $ROP_START_20200701_2330 = 1593642600;
our $ROP_START_20200102_2330 = 1578007800;
our $ROP_START_20200702_2330 = 1593729000;
our $ROP_START_20200102_0000 = 1577923200;
our $ROP_START_20200702_0000 = 1593644400;
our $ROP_START_20200101_2300 = 1577919600;
our $ROP_START_20200701_2300 = 1593640800;
our $ROP_START_20200101_2200 = 1577916000;
our $ROP_START_20200701_2200 = 1593637200;
our $ROP_START_20200102_2300 = 1578006000;
our $ROP_START_20200702_2300 = 1593727200;
our $ROP_START_20200102_2200 = 1578002400;
our $ROP_START_20200702_2200 = 1593723600;

#
# PULL
#
# In ROP that ends today so should be kept

ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200101_2330, '2020-01-02', 1, 15),
   'extractFileCollectionStats ERBS PULL UTC 2020-01-01 23:50 keep');
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200701_2330, '2020-07-02', 1, 15),
   'extractFileCollectionStats ERBS PULL DST 2020-07-01 23:50 keep');
# In ROP that ends tomorrow so should be dropped
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200102_2330, '2020-01-02', 0, 15),
   'extractFileCollectionStats ERBS PULL UTC 2020-01-02 23:50 drop');
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200702_2330, '2020-07-02', 0, 15),
   'extractFileCollectionStats ERBS PULL DST 2020-07-02 23:50 drop');

#
# PUSH
#
# In ROP that ends today so should be kept
ok(test_extractFileCollectionStats('SCU', 'PM_STATISTICAL', 'PUSH', $ROP_START_20200102_2330, '2020-01-02', 1, 15),
   'extractFileCollectionStats SCU PUSH UTC 2020-01-02 23:45 keep');
ok(test_extractFileCollectionStats('SCU', 'PM_STATISTICAL', 'PUSH', $ROP_START_20200702_2330, '2020-07-02', 1, 15),
   'extractFileCollectionStats SCU PUSH DST 2020-07-02 23:45 keep');
# In ROP that ends yesterday so should be dropped
ok(test_extractFileCollectionStats('SCU', 'PM_STATISTICAL', 'PUSH', $ROP_START_20200101_2330, '2020-01-02', 0, 15),
   'extractFileCollectionStats SCU PUSH UTC 2020-01-01 23:45 drop');
ok(test_extractFileCollectionStats('SCU', 'PM_STATISTICAL', 'PUSH', $ROP_START_20200701_2330, '2020-07-02', 0, 15),
   'extractFileCollectionStats SCU PUSH DST 2020-07-01 23:45 drop');


#
#GENERATION
#
# In ROP that ends today so should be kept
ok(test_extractFileCollectionStats('JUNIPER-MX', 'PM_STATISTICAL', 'GENERATION', $ROP_START_20200102_0000, '2020-01-02', 1, 15),
   'extractFileCollectionStats JUNIPER-MX GENERATION UTC 2020-01-02 00:00 keep');
ok(test_extractFileCollectionStats('JUNIPER-MX', 'PM_STATISTICAL', 'GENERATION', $ROP_START_20200702_0000, '2020-07-02', 1, 15),
   'extractFileCollectionStats JUNIPER-MX GENERATION DST 2020-07-02 00:00 keep');

#
# PULL 30MINS
#
# In ROP that ends today so should be kept

ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200102_2300, '2020-01-02', 1, 30),
   'extractFileCollectionStats ERBS PULL UTC 2020-01-02 23:35 keep');
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200702_2300, '2020-07-02', 1, 30),
   'extractFileCollectionStats ERBS PULL DST 2020-07-02 23:35 keep');

ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200101_2300, '2020-01-02', 0, 30),
   'extractFileCollectionStats ERBS PULL UTC 2020-01-01 23:35 drop');
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200701_2300, '2020-07-02', 0, 30),
   'extractFileCollectionStats ERBS PULL DST 2020-07-01 23:35 drop');

#
# PULL 60MINS
#
# In ROP that ends today so should be kept

ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200102_2200, '2020-01-02', 1, 60),
   'extractFileCollectionStats ERBS PULL UTC 2020-01-02 23:05 keep');
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200702_2200, '2020-07-02', 1, 60),
   'extractFileCollectionStats ERBS PULL DST 2020-07-02 23:05 keep');

ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200101_2200, '2020-01-02', 0, 60),
   'extractFileCollectionStats ERBS PULL UTC 2020-01-01 23:05 drop');
ok(test_extractFileCollectionStats('ERBS', 'PM_STATISTICAL', 'PULL', $ROP_START_20200701_2200, '2020-07-02', 0, 60),
   'extractFileCollectionStats ERBS PULL DST 2020-07-01 23:05 drop');
done_testing();
