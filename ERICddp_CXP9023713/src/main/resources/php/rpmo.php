<?php
if (isset($_GET['chart'])) $UI = false;

$pageTitle = "RPMO";
include "common/init.php";
include "common/graphs.php";
$statsDB = new StatsDB();

function doMetricChart($metric, $start, $end, $forceLimits = true) {
    global $date, $site, $statsDB;
    $dataSets = array();
    $sql = "SELECT UNIX_TIMESTAMP('" . $start . "') AS time, 0 AS value";
    $statsDB->query($sql);
    $startRow = $statsDB->getNextNamedRow();

    $sql = "SELECT UNIX_TIMESTAMP(time) AS time,value FROM rpmo_metrics,sites WHERE " .
        "time BETWEEN '" . $start . "' AND '" . $end . "' AND " .
        "siteid = sites.id AND sites.name = '" . $site . "' AND " .
        "type = '" . $metric . "' " .
        " ORDER BY time";
    $statsDB->query($sql);
    $dataset =& Image_Graph::factory('dataset');
    $dataset->setName($metric);
    $min = -1;
    $max = -1;
    $addedStart = false;
    while ($row = $statsDB->getNextNamedRow()) {
        if (! $addedStart) { $dataset->addPoint($startRow['time'], $row['value']); $addedStart = true; }
        $dataset->addPoint($row['time'], $row['value']);
        if ($min > $row['value'] || $min < 0) $min = $row['value'];
        if ($max < $row['value'] || $max < 0) $max = $row['value'];
    }
    $dataSets[0] = $dataset;
    if ($max < 1000) $multFactor = 0.05;
    else $multFactor = 0.005;
    $max += intval($max * $multFactor);
    $min -= intval($min * $multFactor);
    $range = array("max" => $max, "min" => $min);
    getSteppedGraph($dataSets, $forceLimits, $metric, 640, 400, $range)->done();
}

function doMemChart($start, $end, $forceLimits = true) {
    global $date, $site, $statsDB, $debug, $rootdir;

    # do we have an RPMO server?
    $dir = $rootdir . "/remotehosts";
    $hasEBA = false;
    if ( is_dir($dir) && ($dh = opendir($dir)) ) {
        while (($sub = readdir($dh)) != false) {
            if ( preg_match('/_EBAS$/', $sub) ) {
                $host = preg_replace("/_EBAS$/", "", $sub);
                $hasEBA = true;
                break;
            }
        }
    }

    if (! $hasEBA) {
        $sql = "SELECT servers.id FROM servers,sites where servers.siteid = sites.id AND sites.name = '" . $site . "' AND servers.type = 'MASTER' LIMIT 1";
        $res = $statsDB->queryRow($sql);
        $serverid = $res[0];
    } else {
        $sql = "SELECT servers.id FROM servers,sites where servers.siteid = sites.id AND sites.name = '" . $site . "' AND servers.hostname = '" . $host . "' LIMIT 1";
        $res = $statsDB->queryRow($sql);
        $serverid = $res[0];
    }

    $dataSets = array();
    $sql = "SELECT UNIX_TIMESTAMP(time) AS time, mem, rss FROM proc_stats,process_names WHERE " .
        "time BETWEEN '" . $start . "' AND '" . $end . "' AND " .
        "proc_stats.serverid = " . $serverid . " AND " .
        "proc_stats.procid = process_names.id AND process_names.name LIKE '%bin/eba_rpmo_server%' GROUP BY time ORDER BY time";
    $statsDB->query($sql);
    $memdataset =& Image_Graph::factory('dataset');
    $rssdataset =& Image_Graph::factory('dataset');
    $memdataset->setName("Memory");
    $rssdataset->setName("RSS");
    $min = -1;
    $max = -1;
    while ($row = $statsDB->getNextNamedRow()) {
        $memdataset->addPoint($row['time'], $row['mem']);
        $rssdataset->addPoint($row['time'], $row['rss']);
        # assume RSS is lower and mem is higher ...
        if ($min > $row['rss'] || $min < 0) $min = $row['rss'];
        if ($max < $row['mem'] || $max < 0) $max = $row['mem'];
    }
    $dataSets[0] = $memdataset;
    $dataSets[1] = $rssdataset;
    $max += intval($max * 0.005);
    $min -= intval($min * 0.005);
    $range = array("max" => $max, "min" => $min);
    getLineGraph($dataSets, $forceLimits, "Memory", 640, 400, $range)->done();
}

if (isset($_GET['chart'])) {
    $start = $date . " 00:00:00";
    $end = $date . " 23:59:59";
    $forceLimits = true;
    if (isset($_GET['start']) && isset($_GET['end'])) {
        $start = $_GET['start'];
        $end = $_GET['end'];
        //$forceLimits = false;
    }
    switch ($_GET['chart']) {
    case "valueHolders":
        doMetricChart("valueHolders", $start, $end, $forceLimits);
        break;
    case "Monitor":
        doMetricChart("Monitor", $start, $end, $forceLimits);
        break;
    case "StatisticsReportNotification":
        doMetricChart("StatisticsReportNotification", $start, $end, $forceLimits);
        break;
    case "EventConsumers":
        doMetricChart("EventConsumers", $start, $end, $forceLimits);
        break;
    default:
        doMemChart($start, $end, $forceLimits);
    }
    exit;
}

$args = "date=" . $date . "&dir=" . $dir . "&oss=" . $oss . "&site=" . $site;
$start = $date . " 00:00:00";
$end = $date . " 23:59:59";

if (isset($_GET['start']) && isset($_GET['end'])) {
    $args .= "&start=" . $_GET['start'] . "&end=" . $_GET['end'];
    $start = $_GET['start'];
    $end = $_GET['end'];
}
?>
<h1>RPMO Statistics</h1>
<form name=range method=get">
Start: <input type=text name=start value="<?=$start?>" />
End: <input type=text name=end value="<?=$end?>" />
<input type=hidden name="date" value="<?=$date?>" />
<input type=hidden name="dir" value="<?=$dir?>" />
<input type=hidden name="oss" value="<?=$oss?>" />
<input type=hidden name="site" value="<?=$site?>" />
<input type=submit name=submit value="update" />
</form>
<h3>Memory Usage</h3>
<img src="?chart=memory&<?=$args?>" />
<h3>ValueHolders</h3>
<img src="?chart=valueHolders&<?=$args?>" />
<h3>Monitor</h3>
<img src="?chart=Monitor&<?=$args?>" />
<h3>Statistics Report Notifications</h3>
<img src="?chart=StatisticsReportNotification&<?=$args?>" />
<h3>Event Consumers</h3>
<img src="?chart=EventConsumers&<?=$args?>" />
<?php

include "common/finalise.php";
?>
