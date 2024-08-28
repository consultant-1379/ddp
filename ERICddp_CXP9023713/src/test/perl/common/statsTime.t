use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;
use File::Basename;

use StatsTime;

our $DEBUG = 0;

sub testGmtOffsets() {
    my $saveTz = $ENV{'SITE_TZ'};

    # 1717999200 = "2024-06-10 06:00:00 UTC"
    my @tests = (
        {
            'time' => '2024-06-10:04:00:00',
            'tz' => 'Etc/Gmt+2',
            'expected' => 1717999200
        },
        {
            'time' => '2024-06-10:08:00:00',
            'tz' => 'Etc/GMT-2',
            'expected' => 1717999200
        }
    );

    foreach my $r_test ( @tests ) {
        $StatsTime::SITE_TZ = undef;
        $ENV{'SITE_TZ'} = $r_test->{'tz'};
        is(
            parseTimeSafe($r_test->{'time'}, $StatsTime::TIME_YYYYMDHMS, $StatsTime::TZ_SITE),
            $r_test->{'expected'},
            sprintf("Parse %s %s", $r_test->{'time'}, $r_test->{'tz'})
        );
    }

    $StatsTime::SITE_TZ = undef;
    $ENV{'SIZE_TZ'} = $saveTz;
}

ok(
    defined parseTimeSafe('2022-02-08T07:06:39.000+00:00', $StatsTime::TIME_ELASTICSEARCH_MSEC),
    "Handle valid timestamp"
);

ok(
    ! defined parseTimeSafe('2022-02-08T07:06:39+00:00', $StatsTime::TIME_ELASTICSEARCH_MSEC),
    "Handle invalid timestamp"
);

is(
    parseTime('2022-05-06T13:54:09.013636455+02:00', $StatsTime::TIME_ISO8601),
    1651838049,
    "Parse 2022-05-06T13:54:09.013636455+02:00"
);

is(
    parseTime('2022-05-26T09:29:27.59778743Z', $StatsTime::TIME_ISO8601),
    1653557367,
    "Parse 2022-05-26T09:29:27.59778743Z"
);

testGmtOffsets();

done_testing();
