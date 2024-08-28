package StatsTime;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw( $TIME_UNIX_DATE $TIME_MON_DYYYY_HMS $TIME_YYMDHMS $TIME_YYYYMDHMS $TIME_SYB $TIME_SQL $TIME_YYYYMD_HMS $TIME_DDMMYY_HM $TIME_DDMMYY_HMS $TIME_YYMDHM $TIME_YYYYMMDDHHMMSS $TIME_DAY_MON_DD_HHMMSS_TZ_YYYY $TIME_DAY_MON_DD_HHMMSS_YYYY $TIME_DAY_DD_MON_YYYY_HHMMSS_TT_TZ parseTime parseTimeSafe formatTime formatSiteTime setStatsTime_Debug instr2mysqlTime instr2unixTime $TIME_SYB_AMPM $TIME_ELASTICSEARCH convertDateToSqlFormat getCurrentTimeInMilliseconds getElasticsearchTimeInMicroSeconds getDateTimeInMilliSeconds getEsTzOffset $TIME_DD_MMM_YYYY $TZ_GMT $TZ_LOCAL $TIME_K8S $TIME_YYYYMMDD $TIME_ISO8601);

use strict;
use Time::Local;
use Carp qw(confess cluck);
use Time::HiRes qw(gettimeofday);
use DateTime;

our $TIME_UNIX_DATE                    = 0;
our $TIME_MON_DYYYY_HMS                = 1;
our $TIME_YYMDHMS                      = 2;
our $TIME_YYYYMDHMS                    = 3; # 2013-05-13:00:00:00
our $TIME_SYB                          = 4; # Apr 24 2014 12:03:03:000AM
our $TIME_SQL                          = 5;
our $TIME_YYYYMD_HMS                   = 6; # 2013-05-13 00:00:00 or 2013-05-13 00:00.00
our $TIME_DDMMYY_HM                    = 7; # 150513:00:00
our $TIME_DDMMYY_HMS                   = 8; # 15-05-13:00:00:00
our $TIME_YYMDHM                       = 9;
our $TIME_YYYYMMDDHHMMSS               = 10;
our $TIME_SYB_NO_SEC                   = 11; # May 14 2013 11:50PM
our $TIME_DAY_MON_DD_HHMMSS_TZ_YYYY    = 12; # Fri Jun 07 00:00:02 IST 2013
our $TIME_DAY_MON_DD_HHMMSS_YYYY       = 13; # Fri Jun 07 00:00:02 2013
our $TIME_DAY_DD_MON_YYYY_HHMMSS_TT_TZ = 14; # Fri 19 Jul 2013 09:23:07 AM IST
our $TIME_ELASTICSEARCH                = 15; # 2015-07-08T06:55:08.762836-04:00
our $TIME_DD_MMM_YYYY                  = 16; # 29/Oct/2014:10:00:41
our $TIME_DDMMYY                       = 17; # 300117
our $TIME_ELASTICSEARCH_MSEC           = 18; # 2015-07-08T06:55:08.762-04:00
our $TIME_WORKFLOWS_MSEC               = 19; # 2017-08-19T00:05:26.059Z
our $TIME_K8S                          = 20; # 2020-10-08T14:42:18Z
our $TIME_YYYYMMDD                     = 21; # 2020-10-15

# 2022-05-06T13:54:09.013636455+02:00 or 2022-05-26T09:29:27.59778743Z
# uses TZ offset in time string
our $TIME_ISO8601                      = 22;

our $TZ_GMT   = 0;
our $TZ_LOCAL = 1;
our $TZ_SITE = 2;

our $StatsTime_DEBUG = 0;

our $SITE_TZ = undef;

our %monthMap =
    (
     Jan => 1,
     Feb => 2,
     Mar => 3,
     Apr => 4,
     May => 5,
     Jul => 7,
     Jun => 6,
     Aug => 8,
     Sep => 9,
     Oct => 10,
     Nov => 11,
     Dec => 12
     );

our %dateCache = ();

sub setStatsTime_Debug {
    my ($newDebug) = @_;
    $StatsTime_DEBUG = $newDebug;
}

# Format the time using the time zone of the site
# Used when we're getting "epoch" times from the site
sub formatSiteTime($$) {
    my ($time,$format) = @_;

    _initSiteTz();
    my $dt = DateTime->from_epoch( 'epoch' => $time, 'time_zone' => $SITE_TZ );
    return getTimeString($format,
                         $dt->second(),$dt->minute(),$dt->hour(),
                         $dt->day(), $dt->month()-1,$dt->year() - 1900);
}

sub formatTime {
    # The third parameter '$tz' is optional and will be set to '$TZ_LOCAL' by
    #  default. Setting it to '$TZ_LOCAL' will convert the given epoch value
    #  to a timestamp in local timezone while setting it to '$TZ_GMT' will
    #  convert it to a timestamp in GMT
    my ($time,$format,$tz) = @_;

    if ( ! defined $tz ) {
        $tz = $TZ_LOCAL;
    }

    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst);
    if ( $tz == $TZ_LOCAL ) {
        ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time);
    }
    elsif ( $tz == $TZ_GMT ) {
        ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = gmtime($time);
    }

    return getTimeString($format,$sec,$min,$hour,$mday,$mon,$year);
}

sub getTimeString($$$$$$$) {
    my ($format,$sec,$min,$hour,$mday,$mon,$year) = @_;

    my $timeStr;
    if ( $format == $TIME_YYYYMDHMS ) {
        $timeStr = sprintf("%04d-%02d-%02d:%02d:%02d:%02d", $year + 1900, $mon + 1, $mday, $hour, $min, $sec);
    }
    elsif ( $format == $TIME_SQL ) {
        $timeStr = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year + 1900, $mon + 1, $mday, $hour, $min, $sec);
    }
    elsif ( $format == $TIME_DDMMYY_HMS ) {
        $timeStr = sprintf("%02d-%02d-%02d:%02d:%02d:%02d", $mday, $mon + 1, $year - 100, $hour, $min, $sec);
    }
    elsif ( $format == $TIME_DD_MMM_YYYY ) {
        $timeStr = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year + 1900, $mon + 1, $mday, $hour, $min, $sec);
    }
    elsif ( $format == $TIME_DDMMYY ) {
        $timeStr = sprintf("%02d%02d%02d", $mday, $mon + 1, $year - 100);
    } elsif ( $format == $TIME_ELASTICSEARCH_MSEC ) {
        $timeStr = sprintf("%04d-%02d-%02dT%02d:%02d:%02d.000", $year + 1900, $mon + 1, $mday, $hour, $min, $sec);
    } else {
        confess "formatTime: Unknown format $format";
    }

    return $timeStr;
}

# This will return the epoch time in milliseconds
sub getCurrentTimeInMilliseconds {
    my ($seconds, $microSeconds) = gettimeofday();
    my $currentTimeInMilliseconds = ($seconds*1000) + ($microSeconds/1000);
    return $currentTimeInMilliseconds;
}

# This will convert the provided TimeStamp(2017-09-22T10:13:27.014+01:00) in Millisec
sub getDateTimeInMilliSeconds {
    my $dateTime = shift;
    my ($millisec) = $dateTime =~ /^\d{4,4}-\d+-\d+T\d+:\d+:\d+\.(\d{3,3})/;
    my $epochInMilliseconds = ( parseTime($dateTime, $TIME_ELASTICSEARCH_MSEC) * 1000 ) + $millisec;
    return $epochInMilliseconds;
}

# This will convert the I/p date format of dd.mm.yy_hh:mm:ss into yy-mm-dd hh:mm:ss
sub convertDateToSqlFormat {
    my ($timeStamp) = @_;
    my @dateTimeFormat = split('_', $timeStamp);
    my $dateSubStr = $dateTimeFormat[0];
    my $sqlDateStr = join( "-", reverse split( /\./, $dateSubStr ) );
    my $timeSubStr = $dateTimeFormat[1];
    return "$sqlDateStr $timeSubStr";
}

sub getEsTzOffset($) {
    my ($timestamp) = @_;
    my ($sign,$hour,$min) = $timestamp =~ /([+-])(\d{2}):(\d{2})$/;
    defined $sign or confess "ERROR: Could not get TZ Offset from \"$timestamp\"";
    my $minutes = ($hour * 60) + $min;
    if ( $sign eq '+' ) {
	return $minutes;
    } else {
	return 0 - $minutes;
    }
}

sub parseTime {
    # The third parameter '$tz' is optional and will be set to '$TZ_LOCAL' by
    #  default. Setting it to '$TZ_LOCAL' will consider the given timestamp to
    #  be in local timezone while setting it to '$TZ_GMT' will consider it to
    #  be in GMT and will return its epoch value accordingly
    my ($dateStr,$format,$tz) = @_;

    my $result = parseTimeSafe($dateStr,$format,$tz);
    if ( defined $result ) {
        return $result;
    } else {
        die "parseTime failed";
    }
}

sub parseTimeSafe {
    # The third parameter '$tz' is optional and will be set to '$TZ_LOCAL' by
    #  default. Setting it to '$TZ_LOCAL' will consider the given timestamp to
    #  be in local timezone while setting it to '$TZ_GMT' will consider it to
    #  be in GMT and will return its epoch value accordingly
    my ($dateStr,$format,$tz) = @_;
    my ($sec,$min,$hour,$day,$month,$year);

    if ( $StatsTime_DEBUG > 5 ) { printf "parseTimeSafe dateStr='%s' format=%d, tz=%s\n", $dateStr, $format, (defined $tz ? sprintf("%d", $tz) : "NA")}

    if ( ! defined $tz ) {
        $tz = $TZ_LOCAL;
    }

    # offset used if tz = TZ_GMT, what has to "added" to the time to get it to UTC
    # Used for formats where we want to take the timezone offset from the time string
    # itself, e.g. 2022-05-06T13:54:09.013636455+02:00
    my $offset = 0;

    if ( $format == $TIME_UNIX_DATE ) {
        my $monthName;
        ($monthName, $day, $hour, $min, $sec, $year ) = $dateStr =~
            /^(\S{3,3})\s{1,2}(\d{1,2})\s{1,2}(\d{1,2}):(\d{2,2}):(\d{2,2})\s+\S+\s+(\d{4,4})$/;
        #print "parseTime: dateStr=$dateStr monthName=$monthName\n";
        if ( ! exists $monthMap{$monthName} ) {
            cluck "ERROR: Could not parse \"$dateStr\" format=TIME_UNIX_DATE\n";
            return undef;
        }
        $month = $monthMap{$monthName} - 1;
        $year -= 1900;
    }
    elsif ( $format == $TIME_MON_DYYYY_HMS ) {
        my $monthName;
        ($monthName, $day, $year, $hour, $min, $sec ) = $dateStr =~
            /^(\S{3,3})\s{1,2}(\d{1,2})\s+(\d{4,4})\s{1,2}(\d{1,2}):(\d{2,2}):(\d{2,2})$/;
        #print "parseTime: dateStr=$dateStr monthName=$monthName\n";
        if ( ! exists $monthMap{$monthName} ) {
            cluck "ERROR: Could not parse \"$dateStr\" format=TIME_UNIX_DATE_NO_TZ\n";
            return undef;
        }
        $month = $monthMap{$monthName} - 1;
        $year -= 1900;
    }
    elsif ( $format == $TIME_YYMDHMS ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{2,2})-(\d+)-(\d+):(\d+):(\d+):(\d+)$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_YYMDHMS";
            return undef;
        }
        $year -= 100;
        $month--;
    }
    elsif ( $format == $TIME_YYYYMDHMS ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+):(\d+):(\d+):(\d+)$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_YYYYMDHMS";
            return undef;
        }
        $year -= 1900;
        $month--;
    }
    elsif ( $format == $TIME_YYYYMD_HMS ) {
        # Format from dumpLog
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+) (\d+):(\d+)[:\.](\d+)$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_YYMD_HMS";
            return undef;
        }
        $year -= 1900;
        $month--;
    }
    elsif ( $format == $TIME_SYB || $format == $TIME_SYB_NO_SEC ) {
        if ( $dateStr eq "NULL" ) {
            return 0;
        }

        my $ampm;
        if ( $format == $TIME_SYB ) {
            ($month,$day,$year,$hour,$min,$sec,$ampm) = $dateStr =~
                /^(\S+)\s+(\d+)\s+(\d+)\s+(\d+):(\d+):(\d+):\d+(\S+)$/;
        }
        else {
            $sec = 0;
            ($month,$day,$year,$hour,$min,$ampm) = $dateStr =~
                /^(\S+)\s+(\d+)\s+(\d+)\s+(\d+):(\d+)(\S+)$/;
        }

        if ( $month ) {
            my $monthNum = $monthMap{$month};
            if ( $ampm eq "PM" ) {
                if ( $hour != 12 ) {
                    $hour += 12;
                }
            }
            elsif ( $hour == 12 ) {
                $hour = 0;
            }
            my $midnightStr = sprintf("%d-%d-%d", $year, $monthNum, $day);
            my $midnight = $dateCache{$midnightStr};
            if ( ! $midnight ) {
                $midnight = timelocal(0,0,0,$day,$monthNum-1,$year - 1900);
                $dateCache{$midnightStr} = $midnight;
            }

            return ($midnight + (($hour * 3600) + ($min * 60) + $sec));
        }
        else {
            print "parseTime: failed to parse \"$dateStr\"\n";
            return -1;
        }
    }
    elsif ( $format == $TIME_DDMMYY_HM ) {
        ($day,$month,$year,$hour,$min) = $dateStr =~ /^(\d{2,2})(\d{2,2})(\d{2,2}):(\d{2,2}):(\d{2,2})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DDMMYY_HM";
            return undef;
        }
        $year += 100;
        $month--;
    }
    elsif ( $format == $TIME_DDMMYY_HMS ) {
        ($day,$month,$year,$hour,$min,$sec) = $dateStr =~ /^(\d{2,2})-(\d{2,2})-(\d{2,2})[ :](\d{2,2}):(\d{2,2}):(\d{2,2})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DDMMYY_HMS";
            return undef;
        }
        $year += 100;
        $month--;
    }
    elsif ( $format == $TIME_YYMDHM ) {
        ($year,$month,$day,$hour,$min) = $dateStr =~ /^(\d{2,2})(\d{2,2})(\d{2,2}):(\d{2,2}):(\d{2,2})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_YYMDHM";
            return undef;
        }
        $year += 100;
        $month--;
    }
    elsif ( $format == $TIME_YYYYMMDDHHMMSS ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})(\d{2,2})(\d{2,2})(\d{2,2})(\d{2,2})(\d{2,2})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_YYYYMMDDHHMMSS";
            return undef;
        }
        $year -= 1900;
        $month--;
    }
    elsif ( $format == $TIME_DAY_MON_DD_HHMMSS_TZ_YYYY ) {
        my $monthName;
        ($monthName,$day,$hour,$min,$sec,$year) = $dateStr =~ /^\S+\s+(\S+)\s+(\d+)\s+(\d{2,2}):(\d{2,2}):(\d{2,2})\s+\S+\s+(\d{4,4})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DAY_MON_DD_HHMMSS_TZ_YYYY";
            return undef;
        }
        $year -= 1900;
        if ( ! exists $monthMap{$monthName} ) {
            cluck "ERROR: Could not parse \"$dateStr\" format=TIME_DAY_MON_DD_HHMMSS_TZ_YYYY\n";
            return undef;
        }
        $month = $monthMap{$monthName} - 1;
    }
    elsif ( $format == $TIME_SQL ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+) (\d+):(\d+):(\d+)$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_SQL";
            return undef;
        }
        $year -= 1900;
        $month--;
    }
    elsif ( $format == $TIME_DAY_MON_DD_HHMMSS_YYYY ) {
        my $monthName;
        ($monthName,$day,$hour,$min,$sec,$year) = $dateStr =~ /^\S+\s+(\S+)\s+(\d+)\s+(\d{2,2}):(\d{2,2}):(\d{2,2})\s+(\d{4,4})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DAY_MON_DD_HHMMS_YYYY";
            return undef;
        }
        $year -= 1900;
        if ( ! exists $monthMap{$monthName} ) {
            cluck "ERROR: Could not parse \"$dateStr\" format=TIME_DAY_MON_DD_HHMMSS_YYYY\n";
            return undef;
        }
        $month = $monthMap{$monthName} - 1;
    }
    elsif ( $format == $TIME_DAY_DD_MON_YYYY_HHMMSS_TT_TZ ) {
        my ($monthName, $ampm);
        ($day,$monthName,$year,$hour,$min,$sec,$ampm) = $dateStr =~ /^\S+\s+(\d+)\s+(\S+)\s+(\d{4,4})\s+(\d{2,2}):(\d{2,2}):(\d{2,2})\s(\S+)\s+\S+$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DAY_DD_MON_YYYY_HHMMSS_TT_TZ";
            return undef;
        }
        $year -= 1900;
        if ( ! exists $monthMap{$monthName} ) {
            cluck "ERROR: Could not parse \"$dateStr\" format=TIME_DAY_DD_MON_YYYY_HHMMSS_TT_TZ\n";
            return undef;
        }
        $month = $monthMap{$monthName} - 1;
        if ( $ampm eq 'PM' ) {
            if ( $hour != 12 ) {$hour += 12;}
        }
        else {
            $hour = 0;
        }
    }
    elsif ( $format == $TIME_ELASTICSEARCH ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+)T(\d+):(\d+):(\d+)\.\d{6,6}/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_ELASTICSEARCH";
            return undef;
        }
        $year -= 1900;
        $month--;
    } elsif ( $format == $TIME_ELASTICSEARCH_MSEC ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+)T(\d+):(\d+):(\d+)\.\d{3,3}/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_ELASTICSEARCH_MSEC";
            return undef;
        }
        $year -= 1900;
        $month--;
    }
    elsif ( $format == $TIME_DD_MMM_YYYY ) {
        my $monthName;
        ($day,$monthName,$year,$hour,$min,$sec) = $dateStr =~ /^(3[01]|[12][0-9]|0[1-9])\/(\S*)\/([0-9][0-9][0-9][0-9]):(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DAY_MON_DD_HHMMSS_TZ_YYYY";
            return undef;
        }
        $year -= 1900;
        if ( ! exists $monthMap{$monthName} ) {
            cluck "ERROR: Could not parse \"$dateStr\" format=TIME_DAY_MON_DD_HHMMSS_TZ_YYYY\n";
            return undef;
        }
        $month = $monthMap{$monthName} - 1;
    }
    elsif ( $format == $TIME_DDMMYY ) {
        ($day,$month,$year) = $dateStr =~ /^(\d{2,2})(\d{2,2})(\d{2,2})$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_DDMMYY";
            return undef;
        }
        $year += 100;
        $month--;
        $hour = 0;
        $min = 0;
    }
    elsif ( $format == $TIME_WORKFLOWS_MSEC ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+)T(\d+):(\d+):(\d+)\.\d{3,3}/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_WORKFLOWS_MSEC";
            return undef;
        }
        $year -= 1900;
        $month--;
    } elsif ( $format == $TIME_K8S ) {
        ($year,$month,$day,$hour,$min,$sec) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+)T(\d+):(\d+):(\d+)Z$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_K8S";
            return undef;
        }
        $year -= 1900;
        $month--;
        $tz = $TZ_GMT;
    } elsif ( $format == $TIME_YYYYMMDD ) {
        ($year,$month,$day) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+)$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_YYYYMMDD";
            return undef;
        }
        $year -= 1900;
        $month--;
        $hour = 0;
        $min = 0;
        $sec = 0;
    } elsif ( $format == $TIME_ISO8601 ) {
        my ($tzInfo);
        ($year,$month,$day,$hour,$min,$sec,$tzInfo) = $dateStr =~ /^(\d{4,4})-(\d+)-(\d+)T(\d+):(\d+):(\d+)\.\d+([\-+:0-9Z]+)$/;
        if ( ! defined $year ) {
            cluck "parseTime: Failed to parse dateStr=$dateStr format=TIME_ISO8601";
            return undef;
        }
        $year -= 1900;
        $month--;

        if ( $tzInfo ne 'Z' ) {
            my ($plusMinus,$offsetHour,$offsetMin) = $tzInfo =~ /^([+-])(\d{2}):(\d{2})$/;
            $offset = (($offsetHour*60) + $offsetMin) * 60;
            if ($plusMinus eq '+') {
                $offset = 0 - $offset;
            }
        }
        $tz = $TZ_GMT;
    } else {
        cluck "parseTime: Unknown format $format";
        return undef;
    }

    if ( $StatsTime_DEBUG > 5 ) { print "parseTimeSafe sec,min,hour,day,month,year=$sec,$min,$hour,$day,$month,$year\n" }

    my $time;
    if ( $tz == $TZ_LOCAL ) {
        $time = timelocal($sec,$min,$hour,$day,$month,$year);
    } elsif ( $tz == $TZ_GMT ) {
        $time = timegm($sec,$min,$hour,$day,$month,$year);
        if ( $offset != 0 ) {
            $time += $offset;
        }
    } elsif ( $tz == $TZ_SITE ) {
        _initSiteTz();
        my $dt = DateTime->new(
            'year' => $year + 1900,
            'month' => $month + 1,
            'day' => $day,
            'hour' => $hour,
            'minute' => $min,
            'second' => $sec,
            'time_zone' => $SITE_TZ );
        $time = $dt->epoch();
    }

    if ( $StatsTime_DEBUG > 5 ) { print "parseTimeSafe time=$time\n" }

    return $time;
}

# This will return the epoch time for the given '$TIME_ELASTICSEARCH' (eg: 2015-07-08T06:55:08.762836-04:00) in microseconds
sub getElasticsearchTimeInMicroSeconds {
    my $elasticsearchTime = shift;
    my ($microsec) = $elasticsearchTime =~ /^\d{4,4}-\d+-\d+T\d+:\d+:\d+\.(\d{6,6})/;
    my $epochInMicroseconds = ( parseTime($elasticsearchTime, $TIME_ELASTICSEARCH) * 1000000 ) + $microsec;
    return $epochInMicroseconds;
}


sub instr2mysqlTime {
    my $instrTime = shift;
    my ($d, $m, $y, $h, $M, $s, $ms) = $instrTime =~ /^([0-3][0-9])-([0-1][0-9])-([0-9][0-9]) ([0-2][0-9]):([0-5][0-9]):([0-5][0-9])\.([0-9][0-9][0-9])$/;
    return "20" . $y . "-" . $m . "-" . $d . " " . $h . ":" . $M . ":" . $s;
}

sub instr2unixTime {
    my $instrTime = shift;
    my ($d, $m, $y, $h, $M, $s, $ms) = $instrTime =~ /^([0-3][0-9])-([0-1][0-9])-([0-9][0-9]) ([0-2][0-9]):([0-5][0-9]):([0-5][0-9])\.([0-9][0-9][0-9])$/;
    return timelocal($s,$M,$h,$d,$m - 1,$y);
}

sub _initSiteTz() {
    if ( ! defined $SITE_TZ ) {
        if ( ! exists $ENV{'SITE_TZ'} ) {
            confess "SITE_TZ not set";
        }
        $SITE_TZ = $ENV{'SITE_TZ'};
        if ( $SITE_TZ eq "" ) {
            confess "SITE_TZ is empty";
        }

        # TORF-71786 DateTime doesn't handle Etc/GMT-+ timezones
        if ( $SITE_TZ =~ /^Etc\/GMT([-+])(\d+)/i ) {
            my ($plusMinus,$offsetHours) = ($1,$2);
            if ( $plusMinus eq "-") {
                $SITE_TZ = sprintf("+%02d00", $offsetHours);
            } else {
                $SITE_TZ = sprintf("-%02d00", $offsetHours);
            }
        }

        if ( $StatsTime_DEBUG > 0 ) { print "_initSiteTz: SITE_TZ=$SITE_TZ\n"; }
    }
}

1;
