<?php
echo <<<EOS
     <ul id=menu>
      <li><a href="$php_webroot/site_index.php">site index</a></li>
      <li><a href="$php_webroot/../adminui/faq.php">FAQ</a></li>
EOS;

if (! isset($pageTitle)) $pageTitle = "";

if (valueExists($site)) {
    echo "      <li><a href=\"" . $link . "\">" . $site . "</a></li>\n";
    if (valueExists($date)) {
        echo "      <li>";
        // TODO: A function to get the day before / after today.
        // Get previous day
        $mDay = $mMonth = $mYear = 0;
        if (valueExists($dir)) {
            $todayInfo = str_split($dir, 2);
        } else {
            $tmpTodayInfo = explode("-", $date);
            $todayInfo = array($tmpTodayInfo[2], $tmpTodayInfo[1], $tmpTodayInfo[0]);
        }
        $dayInfo = getYesterday($todayInfo);
        $yDate = date("Y-m-d", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $yDir = date("dmy", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $dayInfo = getTomorrow($todayInfo);
        $tDate = date("Y-m-d", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $tDir = date("dmy", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));

        $yArgs = "";
        $tArgs = "";
        foreach ($_GET as $key => $val) {
            if ($key == "dir" || $key == "date") {
                continue;
            } else if ($key == "tstart") {
                $yArgs .= "&" . $key . "=" . urlencode("$yDate 00:00:00");
                $tArgs .= "&" . $key . "=" . urlencode("$tDate 00:00:00");
            } else if ($key == "tend") {
                $yArgs .= "&" . $key . "=" . urlencode("$yDate 23:59:59");
                $tArgs .= "&" . $key . "=" . urlencode("$tDate 23:59:59");
            } else {
                $yArgs .= "&" . $key . "=" . $val;
                $tArgs .= "&" . $key . "=" . $val;
            }
        }

        $self = fromServer(PHP_SELF);

        if (! valueExists($pageTitle)) {
            if ( $self != '/php/index.php' ) {
                error_log("No title set for $self");
            }

            echo "<a title=\"Previous Day (Index)\" href=\"" .
                $link . "&date=" . $yDate . "&dir=" . $yDir . "\"><</a>\n";
        }
        echo "<a title=\"Index\" href=\"" . $link . "&date=" . $date . "&dir=" . $dir . "\">" . $date . "</a>\n";
        if (! valueExists($pageTitle)) {
            echo "<a title=\"Next Day (Index)\" href=\"" .
                $link . "&date=" . $tDate . "&dir=" . $tDir . "\">></a>\n";
        }
        echo "</li>\n";
        if (valueExists($pageTitle)) {
            echo "      <li>";
            echo "<a title=\"Previous Day (" . $pageTitle . ")\" " .
                "href=\"" . $_SERVER['PHP_SELF'] . "?" . $yArgs . "&date=" . $yDate . "&dir=" . $yDir . "\"><</a>\n";
            echo "<a href=#>" . $pageTitle . "</a>\n";
            echo "<a title=\"Next Day (" . $pageTitle . ")\" " .
                "href=\"" . $_SERVER['PHP_SELF'] . "?" . $tArgs . "&date=" . $tDate . "&dir=" . $tDir . "\">></a>\n";
            echo "</li>\n";
        }
    } // end if (valueExists($date))
    else if (valueExists($year) && valueExists($month)) {
        $localDate = array(1, $month, $year);
        $dayInfo = getYesterday($localDate);
        $yDayInfo = $dayInfo;
        // TODO: reduce number of calls to date() - should be able to get month and year in one call to date().
        $yMonth = date("m", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $yYear = date("Y", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $localDate = array(date("t", mktime(0,0,0,$month,1,$year)), $month, $year);
        $dayInfo = getTomorrow($localDate);
        $tDayInfo = $dayInfo;
        $tMonth = date("m", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $tYear = date("Y", mktime(0,0,0,$dayInfo[1],$dayInfo[0],$dayInfo[2]));
        $args = "";
        $start = "";
        $yMonStart = "";
        $tMonStart = "";
        $yMonTstart = "";
        $tMonTstart = "";
        $end = "";
        $yMonEnd = "";
        $tMonEnd = "";
        $yMonTend = "";
        $tMonTend = "";
        foreach ($_GET as $key => $val) {
            if ($key == "start") {
                $start = $val;
                $yMonStart = date("Y-m-01", mktime(0,0,0,$yDayInfo[1],1,$yDayInfo[2]));
                $tMonStart = date("Y-m-01", mktime(0,0,0,$tDayInfo[1],1,$tDayInfo[2]));
            } else if ($key == "tstart") {
                $yMonTstart = urlencode( date( "Y-m-01 00:00:00", mktime(0,0,0,$yDayInfo[1],1,$yDayInfo[2]) ) );
                $tMonTstart = urlencode( date( "Y-m-01 23:59:59", mktime(0,0,0,$tDayInfo[1],1,$tDayInfo[2]) ) );
            } else if ($key == "end") {
                $end = $val;
                $yMonEnd = date("Y-m-t", mktime(0,0,0,$yDayInfo[1],1,$yDayInfo[2]));
                $tMonEnd = date("Y-m-t", mktime(0,0,0,$tDayInfo[1],1,$tDayInfo[2]));
            } else if ($key == "tend") {
                $yMonTend = urlencode( date( "Y-m-t 00:00:00", mktime(0,0,0,$yDayInfo[1],1,$yDayInfo[2]) ) );
                $tMonTend = urlencode( date( "Y-m-t 23:59:59", mktime(0,0,0,$tDayInfo[1],1,$tDayInfo[2]) ) );
            } else if ($key != "month" && $key != "year") {
                $args .= "&" . $key . "=" . $val;
            }
        }
        if (! valueExists($start)) $start = date("Y-m-01", mktime(0,0,0,$month,1,$year));
        if (! valueExists($end)) $end = date("Y-m-t", mktime(0,0,0,$month,1,$year));

        echo "<li>";
        if (! valueExists($pageTitle)) {
            echo "<a title=\"Previous Month (Index)\" href=\"" . $php_webroot . "/monthly/index.php?" . $args .
                "&month=" . $yMonth . "&year=" . $yYear . "\"><</a>\n";
        }
        echo "<a href=\"" . $php_webroot . "/monthly/index.php?" . $args .
            "&month=" . $month . "&year=" . $year . "\">" .
            date("F Y", mktime(0,0,0,$month, 1, $year)) . "</a>\n";
        if (! valueExists($pageTitle)) {
            echo "<a title=\"Next Month (Index)\" href=\"" . $php_webroot . "/monthly/index.php?" . $args .
                "&month=" . $tMonth . "&year=" . $tYear . "\">></a>\n";
        }
        echo "</li>\n";
        if (valueExists($pageTitle)) {
            echo "<li>";
            $yArgs = $args . "&month=" . $yMonth . "&year=" . $yYear;
            if (valueExists($yMonStart)) $yArgs .= "&start=" . $yMonStart;
            if (valueExists($yMonEnd)) $yArgs .= "&end=" . $yMonEnd;
            if (valueExists($yMonTstart)) $yArgs .= "&tstart=" . $yMonTstart;
            if (valueExists($yMonTend)) $yArgs .= "&tend=" . $yMonTend;
            echo "<a title=\"Previous Month (" . $pageTitle . ")\" " .
                "href=\"" . $_SERVER['PHP_SELF'] . "?" . $yArgs . "\"><</a>\n";
            echo "<a href=#>" . $pageTitle . "</a>\n";
            $tArgs = $args . "&month=" . $tMonth . "&year=" . $tYear;
            if (valueExists($tMonStart)) $tArgs .= "&start=" . $tMonStart;
            if (valueExists($tMonEnd)) $tArgs .= "&end=" . $tMonEnd;
            if (valueExists($yMonTend)) $tArgs .= "&tstart=" . $tMonTstart;
            if (valueExists($tMonTend)) $tArgs .= "&tend=" . $tMonTend;
            echo "<a title=\"Next Month (" . $pageTitle . ")\" " .
                "href=\"" . $_SERVER['PHP_SELF'] . "?" . $tArgs . "\">></a>\n";
        }
    }

} // end if (valueExists($site))
echo "     </ul>\n";
?>
