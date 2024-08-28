<?php
/*
 * This script will generate a calendar for the current site
 * based on the days that that site has analysis directories
 * defined. If there are no analysis directories, or there
 * is no directory defined, nothing will appear.
 * TODO: Make  this whole thing a class, to reduce the scope
 * of the variables.
 */

include PHP_ROOT . "/StatsDB.php";

class Calendar {
    var $dates = array();
    var $dateInfo = array();
    var $mDay;
    var $mMonth;
    var $mYear;
    var $viewingArchive = false;
    var $hasArchive = false;
    var $archiveYear;
    var $oldestCurrent;

    function __construct() {
        global $rootdir_base, $archive_dir, $nas_archive_dir, $dir, $view_archive, $archive_year, $oss, $site, $debug;

        if ( $debug ) { echo "<pre>Calendar::__construct rootdir: " . $rootdir_base . " ; archive_dir: " . $archive_dir .
            " ; nas_archive_dir: " . $nas_archive_dir . "; view archive: " . $view_archive . " </pre>\n"; }

        $searchDir = $rootdir_base;

        if ( !is_dir($searchDir) ) {
            return;
        }

        $currentDates = $this->getCurrentDatesFromDB($site);
        $dates = array();
        if ( $debug > 2 ) { echo "<pre>Calendar::__construct currentDates"; print_r($currentDates); echo "</pre>\n"; }
        if ( count($currentDates) > 0 ) {
            ksort($currentDates);
            if ( $debug > 3 ) { echo "<pre>Calendar::__construct sorted currentDates"; print_r($currentDates); echo "</pre>\n"; }
            $dateArr = str_split(array_keys($currentDates)[0], 2);
            $this->oldestCurrent = $dateArr[0] . $dateArr[1] . "-" . $dateArr[2] . "-" . $dateArr[3];
            if ( $debug ) { echo "<pre>Calendar::__construct oldestCurrent=$this->oldestCurrent</pre>\n"; }
        }

        if ($view_archive) {
            $this->viewingArchive = true;
            if ( $archive_year ) {
                $this->archiveYear = $archive_year;
                $from = $archive_year . "-01-01";
                $to = ($archive_year + 1) . "-01-01";
                if ( isset($this->oldestCurrent) && (substr($this->oldestCurrent, 0, strlen($archive_year)) === $archive_year) ) {
                    $to = $this->oldestCurrent;
                }
                $dates = $this->getDatesFromDB($site,$from,$to);
            }
            $this->hasArchive = true;
        } else {
            $dates = array_values($currentDates);
        }

        $this->dates = $dates;
        // Exit if nothing
        if ( $debug ) { echo "<p>Calendar::__construct count dates=" . count($dates) . "</p>\n"; }

        if (count($dates) <= 0) return;
        // Create a multi-dimensional array to match days, months, years
        $this->dateInfo = array();
        foreach ($dates as $aDate) {
            $dateArr = str_split($aDate, 2);
            $d = $dateArr[0];
            $m = $dateArr[1];
            $y = $dateArr[2];
            if (! isset($this->dateInfo[$y])) $this->dateInfo[$y] = array();
            if (! isset($this->dateInfo[$y][$m])) $this->dateInfo[$y][$m] = array();
            $this->dateInfo[$y][$m][$d] = $d;
        }

        // Sort the year arrays in reverse order by key.
        krsort($this->dateInfo);

        // Get current date
        $this->mDay = $this->mMonth = $this->mYear = 0;
        if (valueExists($dir)) {
            $dayInfo = str_split($dir, 2);
            $this->mDay = $dayInfo[0];
            $this->mMonth = $dayInfo[1];
            $this->mYear = $dayInfo[2];
        }
    }

    function getDataFromAnalysisDir($searchDir) {
        $currentDates = array();
        $analysisDir = opendir($searchDir);
        while ($dirEntry = readdir($analysisDir)) {
            if (is_dir($searchDir . "/" . $dirEntry) &&
                preg_match("/^[0-3][0-9][0-1][0-9][0-9][0-9]$/", $dirEntry)) {
                $dateArr = str_split($dirEntry, 2);
                $d = $dateArr[0];
                $m = $dateArr[1];
                $y = $dateArr[2];
                $date = sprintf("20%02d%02d%02s",$y,$m,$d);
                $currentDates[$date] = $dirEntry;
            }
        }

        return $currentDates;
    }

    function getCurrentDatesFromDB($site) {
        $statsDB = new StatsDB();
        $results = array();
        $statsDB->query("
SELECT DATE_FORMAT(date,'%d%m%y') AS dir, DATE_FORMAT(date,'%Y%m%d') AS date
FROM site_data, sites
WHERE
 site_data.siteid = sites.id AND sites.name = '$site' AND
 site_data.date >= DATE_ADD(DATE_ADD(LAST_DAY(NOW()),INTERVAL 1 DAY),INTERVAL - 3 MONTH)");
        while ( $row = $statsDB->getNextRow() ){
            $results[$row[1]] = $row[0];
        }
        return $results;
    }

    function getLatestDir() {
        if ( (! isset($this->dateInfo)) || (count($this->dateInfo) == 0) ) {
            return NULL;
        }

        $years = array_keys($this->dateInfo);
        $year = $years[0];
        $months = $this->dateInfo[$year];
        krsort($months);
        $monthKeys = array_keys($months);
        $month = $monthKeys[0];
        $days = $months[$month];
        krsort($days);
        $dayKeys = array_keys($days);

        return $dayKeys[0] . $month . $year;
    }

    function printYears() {
        global $site, $oss, $nas_archive_dir, $debug;
        if ( $debug > 0 ) { echo "<pre>printYears: nas_archive_dir=$nas_archive_dir</pre>\n"; }
        $viewPage = dirname($_SERVER['PHP_SELF']) . "/index.php";
        echo "<ul>";

        $years = $this->getYearsFromDB($site,$this->oldestCurrent);
        foreach ( $years as $year ) {
            echo " <li><a href=\"" . $viewPage . "?archive=true&site=" .
                                   $site . "&oss=" . $oss . "&archive_year=" . $year .
                                   "\">$year</a></li>\n";
        }
        echo "</ul>";
    }

    function getDatesFromDB($site,$from,$to) {
        $results = array();
        $statsDB = new StatsDB();
        $statsDB->query("
SELECT DATE_FORMAT(date,'%d%m%y')
FROM site_data, sites
WHERE
 site_data.siteid = sites.id AND sites.name = '$site' AND
 site_data.date >= '$from' AND site_data.date < '$to'");
        while ( $row = $statsDB->getNextRow() ){
            $results[] = $row[0];
        }
        return $results;
    }

    function getYearsFromDB($site,$before) {
        $statsDB = new StatsDB();
        $sql = "
SELECT DISTINCT DATE_FORMAT(date,'%Y')
FROM site_data, sites
WHERE
 site_data.siteid = sites.id AND sites.name = '$site'";
        if ( isset($before) ) {
            $sql = $sql . " AND date < '$before'";
        }
        $statsDB->query($sql);
        $results = array();
        while ( $row = $statsDB->getNextRow() ){
            $results[] = $row[0];
        }
        return $results;
    }

    function printDates($printDays, $isThisAMonthlyReport) {
        global $php_webroot, $site, $oss, $month, $year;
        $viewPage = dirname($_SERVER['PHP_SELF']) . "/index.php";

        foreach ($this->dateInfo as $calYear => $months) {
            // Assume the 21st century. Shouldn't be any data earlier than 2000,
            // and if this code is still around in 2100 I guess there will be
            // budget and competence to update it.
            $fullYear = "20" . $calYear;
            // sort the month arrays in reverse order by key
            krsort($months);
            foreach ($months as $calMonth => $days) {
                $selected = "";
                $calMonthLink = "";
                if (valueExists($month) && valueExists($year)) {
                    if ($calMonth == $month && $fullYear == $year) {
                        $selected = "class=selected ";
                    } else {
                        $selected = "";
                    }

                    $start = date("Y-m-01", mktime(0,0,0,$calMonth,1,$fullYear));
                    $end = date("Y-m-t", mktime(0,0,0,$calMonth,1,$fullYear));
                    $args = "start={$start}&end={$end}&year={$fullYear}&month={$calMonth}";
                    foreach ($_GET as $key => $val) {
                        if ($key == "start" || $key == "end" || $key == "year" || $key == "month") {
                            continue;
                        } else if ($key == "tstart") {
                            $args .= "&" . $key . "=" . urlencode("$start 00:00:00");
                        } else if ($key == "tend") {
                            $args .= "&" . $key . "=" . urlencode("$end 00:00:00");
                        } else {
                            $args .= "&" . $key . "=" . urlencode($val);
                        }
                    }
                    $calMonthLink = $_SERVER['PHP_SELF'] . "?" . $args;
                } else {
                    $selected = "";
                    $calMonthLink = $php_webroot . "/monthly/index.php?" .
                        "site=" . $site .
                        "&year=" . $fullYear .
                        "&month=" . $calMonth .
                        "&oss=" . $oss .
                        "&root=ONRM_RootMo_R";
                }

                echo "<h2><a " . $selected . "href=\"" . $calMonthLink . "\">" .
                    date("F Y", mktime(0,0,0,$calMonth,1,$fullYear)) .
                    "</a></h2>\n";
                if ($printDays) {
                    echo "<table>" .
                        "<tr><th>M</th><th>T</th><th>W</th>" .
                        "<th>T</th><th>F</th><th>S</th><th>S</th></tr>\n";
                    // The day of the week this month starts on
                    $dayOfWeek = date("N", mktime(0,0,0,$calMonth,1,$fullYear));
                    $weekDayIter = 1;
                    $maxDays = date("t", mktime(0,0,0,$calMonth,1,$fullYear));
                    for ($dayNum = 1 ; $dayNum <= $maxDays ; $dayNum++) {
                        while ($weekDayIter < $dayOfWeek) {
                            echo "<td></td>";
                            $weekDayIter++;
                        }
                        echo "<td>";
                        if ($dayNum < 10) $dayNum = 0 . $dayNum;
                        if (isset($days[$dayNum])) {
                            echo "<a ";
                            if ($calMonth == $this->mMonth && $dayNum == $this->mDay && $calYear == $this->mYear) {
                                $dateIsValid = true;
                                echo "class=selected ";
                            }
                            $yyyy_mm_dd = $fullYear . "-" . $calMonth . "-" . $dayNum;
                            $args = "dir=" . $dayNum . $calMonth . $calYear . "&date=" .
                                    $yyyy_mm_dd . "&oss=" . $oss;
                            if ( $isThisAMonthlyReport ) {
                                $args = "site=" . $site . "&" . $args;
                                echo "href=\"" . $php_webroot . "/index.php?" . $args . "\">" . $dayNum . "</a>";
                            } else {
                                foreach ($_GET as $key => $val) {
                                    if ($key == "dir" || $key == "date" || $key == "oss") {
                                        continue;
                                    } else if ($key == "tstart") {
                                        $args .= "&" . $key . "=" . urlencode("$yyyy_mm_dd 00:00:00");
                                    } else if ($key == "tend") {
                                        $args .= "&" . $key . "=" . urlencode("$yyyy_mm_dd 23:59:59");
                                    } else {
                                        $args .= "&" . $key . "=" . urlencode($val);
                                    }
                                }
                                echo "href=\"" . $_SERVER['PHP_SELF'] . "?" . $args . "\">" . $dayNum . "</a>";
                            }
                        } else {
                            echo $dayNum;
                        }
                        echo "</td>";
                        if ($weekDayIter < 7) {
                            $weekDayIter++;
                           $dayOfWeek++;
                        } else {
                            echo "</tr>\n";
                            $weekDayIter = $dayOfWeek = 1;
                        }
                    }
                    echo "</table>\n";
                }
            }
        }
    }

    function printCalendar($printDays = true, $isThisAMonthlyReport = false) {
        global $php_webroot, $site, $oss, $month, $year, $debug;

        if ( $debug ) { echo "<pre>Calendar.printCalendar viewingArchive=" . $this->viewingArchive . ", archiveYear=" . $this->archiveYear . ", count(dateInfo)=" . count($this->dateInfo) . "</pre>\n"; }

        $viewPage = $php_webroot . "/index.php";
        # Print current as a link if viewing the archive
        if ( $this->viewingArchive ) {
            echo "<b><a href=\"" . $viewPage . "?site=" . $site . "&oss=" . $oss . "\">current</a>";
        } else {
            echo "<b>current";
        }

        echo " | <a href=\"" . $viewPage . "?archive=true&site=" . $site . "&oss=" . $oss . "\">archive</a></b>\n";

        if ( count($this->dateInfo) > 0 ) {
            $this->printDates($printDays, $isThisAMonthlyReport);
        } else if ( $this->viewingArchive && ! $this->archiveYear ) {
            $this->printYears();
        }
    }
}

$cal = new Calendar();
$cal->printCalendar($doDayCalendar, $isThisAMonthlyReport);

?>
