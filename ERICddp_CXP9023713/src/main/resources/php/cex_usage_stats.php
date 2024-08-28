<?php
$pageTitle = "CEX Event Statistics";
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$statsDB = new StatsDB();
?>

<br>
<h2><u>Total time CEX was Active</u></h2>
<table border>
<tr>
    <th>Total Time</th>
<tr>

 <?php
 require_once 'HTML/Table.php';

 $sqlTotalTCA = "
        SELECT  cen.name AS 'name',
                TIMEDIFF(CONCAT(cus.event_stop,'.',cus.event_stop_millis),CONCAT(cus.event_start,'.',cus.event_start_millis)) AS 'timediff'
        FROM    cex_usage_stats cus,cex_event_types cen, sites
        WHERE   cus.event_start BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
        AND     cus.siteid = sites.id AND sites.name = '" . $site . "' AND cus.event_type = cen.id AND cen.name = 'SessionEvent'
      ";

    if ( $debug ) { echo "<p>sql = $sqlTotalTCA</p>"; }

    $dataForTotalTCA = populate_data($sqlTotalTCA);
    $dataForTable = sum_the_time($dataForTotalTCA);
    foreach ($dataForTable as $rows => $row)
    {
        echo "<tr>
            <td>$row[0]</td>
        </tr>\n";
    }
?>



</table>

<br>
<h2><u>Total time in each Perspective</u></h2>
<table border>
<tr>
    <th>Name</th>
    <th>Total Time</th>
<tr>



 <?php
 $sqlTotalTSP = "
        SELECT  cen.name AS 'name',
                TIMEDIFF(CONCAT(cus.event_stop,'.',cus.event_stop_millis),CONCAT(cus.event_start,'.',cus.event_start_millis)) AS 'timediff'
        FROM    cex_usage_stats cus,cex_event_names cen,cex_event_types cet, sites
        WHERE   cus.event_start BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
        AND     cus.siteid = sites.id AND sites.name = '" . $site . "' AND cus.event_type = cet.id AND cus.event_name = cen.id AND cet.name = 'PerspectiveEvent'
      ";

    if ( $debug ) { echo "<p>sql = $sqlTotalTSP</p>"; }

    $dataForTotalTSP = populate_data($sqlTotalTSP);
    $dataForTables= sum_the_time($dataForTotalTSP);
    foreach ($dataForTables as $rows => $row)
    {
        echo "<tr>
            <td>$rows</td>
            <td>$row[0]</td>
        </tr>\n";
    }
?>



</table>

<br>
<h2><u>Total time in each  View</u></h2>
<table border>
<tr>
    <th>View Name</th>
    <th>Total Time</th>
<tr>



 <?php
 $sqlTotalVT = "
        SELECT  cen.name AS 'name',
                TIMEDIFF(CONCAT(cus.event_stop,'.',cus.event_stop_millis),CONCAT(cus.event_start,'.',cus.event_start_millis)) AS 'timediff'
        FROM    cex_usage_stats cus, cex_event_types cet, cex_event_names cen, sites
        WHERE   cus.event_start BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
        AND     cus.siteid = sites.id AND sites.name = '" . $site . "' AND cus.event_type = cet.id AND cet.name = 'PartEvent' AND cus.event_name = cen.id
      ";

    if ( $debug ) { echo "<p>sql = $sqlTotalVT</p>"; }

    $dataForTotalVT =  populate_data($sqlTotalVT);
    $dataForTable = sum_the_time($dataForTotalVT);
    foreach ($dataForTable as $rows => $row)
    {
        echo "<tr>
            <td>$rows</td>
            <td>$row[0]</td>
        </tr>\n";
    }
?>



</table>

<br>
<h2><u>Properites View:Total time in Finger Tab</u></h2>
<table border>
<tr>
    <th>View Name</th>
    <th>Total Time</th>
<tr>



 <?php
 $sqlPropView = "
        SELECT  cen.name AS 'name',
                TIMEDIFF(CONCAT(cus.event_stop,'.',cus.event_stop_millis),CONCAT(cus.event_start,'.',cus.event_start_millis)) AS 'timediff'
        FROM    cex_usage_stats cus, cex_event_types cet, cex_event_names cen, sites
        WHERE   cus.event_start BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
        AND     cus.siteid = sites.id AND sites.name = '" . $site . "' AND cus.event_type = cet.id AND cet.name = 'PropertyTabEvent' AND cus.event_name = cen.id
      ";

    if ( $debug ) { echo "<p>sql = $sqlPropView</p>"; }

    $dataForPropView = populate_data($sqlPropView);
    $dataForTable = sum_the_time($dataForPropView);
    foreach ($dataForTable as $rows => $row)
    {
        echo "<tr>
            <td>$rows</td>
            <td>$row[0]</td>
        </tr>\n";
    }
?>



</table>

<br>
<h2><u>Total Number of Command Executions</u></h2>
<table border>
<tr>
    <th>Command Name</th>
    <th>Total Executions</th>
<tr>



 <?php
 require_once 'HTML/Table.php';

 $statsDB = new StatsDB();

 $sqlTotalNCE = "
        SELECT  cen.name AS 'name',
                count(cen.name)
        FROM    cex_usage_stats cus, cex_event_types cet, cex_event_names cen, sites
        WHERE   cus.event_start BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
        AND     cus.siteid = sites.id AND sites.name = '" . $site . "' AND cus.event_type = cet.id AND cet.name = 'CommandEvent' AND cus.event_name = cen.id GROUP BY cen.name
      ";

    if ( $debug ) { echo "<p>sql = $sqlTotalNCE</p>"; }

    $statsDB->query($sqlTotalNCE);
    while($row = $statsDB->getNextRow()) {
                echo "<tr>
            <td>$row[0]</td>
            <td>$row[1]</td>
        </tr>\n";
    }
?>
</table>

<?php
function populate_data($sql){
    $statsDB = new StatsDB();
    $array = array();

    $statsDB->query($sql);
    while($row = $statsDB->getNextRow()) {
        $array[$row[0]][] = str_replace(".",':',$row[1]);
    }

    return $array;
}

//Function to add the overall time difference together.
function sum_the_time($totalTime) {
    $table = array();
    $millis = 0;
    foreach ($totalTime as $name => $timediff) {
        foreach ($timediff as $time) {
                list($hour,$minute,$second,$milli) = explode(':', $time);
                $millis += $hour*3600000;
                $millis += $minute*60000;
                $millis += $second*1000;
                $millis += $milli/1000;
        }

        $hours = floor($millis/3600000);
        $millis -= $hours*3600000;
        $minutes  = floor($millis/60000);
        $millis -= $minutes*60000;
        $seconds  = floor($millis/1000);
        $millis -= $seconds*1000;

        $timestr = sprintf("%02s",$hours) .':'. sprintf("%02s",$minutes) .':'. sprintf("%02s",$seconds) .':'. sprintf("%03s",$millis);
        $table[$name][] = "$timestr";
    }

    return $table;
}

include "../php/common/finalise.php";
return;
?>
