<?php
if (isset($_GET['chart'])) $UI = false;

$pageTitle = "Open LDAP Statistics";
include "common/init.php";
include "common/graphs.php";
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();

function getOperationsStatGraph($title,$ylabel,$start,$end,$whatCol,$graphType = 'tsc')
{
  global $site;
  $sqlParam =
    array( 'title'      => $title,
           'ylabel'     => $ylabel,
           'useragg'    => 'true',
           'persistent' => 'true',
           'type'       => $graphType,
           'querylist' =>
           array(
                 array (
                        'timecol' => 'time',
                        'whatcol' => $whatCol,
                        'tables'  => "open_ldap_monitor_info, sites",
                        'where'   => "open_ldap_monitor_info.siteid = sites.id AND sites.name = '%s'",
                        'qargs'   => array( 'site' )
                        )
                 )
           );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  return $sqlParamWriter->getImgURL( $id,
                                     "$start", "$end",
                                     true, 640, 240 );
}

function doChart($type,$yAxisTitle, $start, $end, $metrics, $forceLimits = true) {
    global $date, $site, $statsDB;
    $metricArgs = "";
    $delim = "";
    foreach ($metrics as $k => $v) {
        $metricArgs .= $delim . $k;
        $delim = ",";
    }
    $sql = "SELECT UNIX_TIMESTAMP(time) AS time,$metricArgs FROM open_ldap_monitor_info,sites WHERE " .
        "time BETWEEN '" . $start . "' AND '" . $end . "' AND " .
        "siteid = sites.id AND sites.name = '" . $site . "' ORDER BY time";
    $statsDB->query($sql);
    $datasets = array();
    foreach ($metrics as $k => $v) {
        $datasets[$k] =& Image_Graph::factory('dataset');
        $datasets[$k]->setName($v);
    }
    while ($row = $statsDB->getNextNamedRow()) {
        foreach ($row as $k => $v) {
	    if ($k == "time") continue;
            $datasets[$k]->addPoint($row['time'], $v);
        }
    }
    if ( $type == "stacked") {
       getStackedGraph($datasets, $forceLimits, $yAxisTitle, 640, 300)->done();
    } else {
        getLineGraph($datasets, $forceLimits, $yAxisTitle, 640, 300)->done();
    }
}

if (isset($_GET['chart'])) {
    $start = $date . " 00:00:00";
    $end = $date . " 23:59:59";
    $forceLimits = true;
    if (isset($_GET['start']) && isset($_GET['end'])) {
        $start = $_GET['start'];
        $end = $_GET['end'];
        $forceLimits = false;
    }
    switch ($_GET['chart']) {
        case "ops":
            $metrics = array (
                'operations_bind' => "Bind",
                'operations_unbind' => "Unbind",
                'operations_search' => "Search",
                'operations_compare' => "Compare",
                'operations_modify' => "Modify",
                'operations_modrdn' => "Mod RDN",
                'operations_add' => "Add",
                'operations_delete' => "Delete"
            );
            $yAxisTitle = "Completed";
            doChart("stacked", $yAxisTitle, $start, $end, $metrics, $forceLimits);
            break;
        case "stats_bytes":
            $metrics = array (
                'statistics_bytes' => "Bytes"
            );
            $yAxisTitle = "Counter";
            doChart("line",$yAxisTitle, $start, $end, $metrics, $forceLimits);
            break;
        case "stats_entries":
            $metrics = array (
                'statistics_entries' => "Entries"
            );
            $yAxisTitle = "Counter";
            doChart("line",$yAxisTitle, $start, $end, $metrics, $forceLimits);
            break;
        case "threads_max":
            $metrics = array (
                'threads_max' => "Max"
            );
            $yAxisTitle = "Info";
            doChart("line",$yAxisTitle, $start, $end, $metrics, $forceLimits);
            break;
        case "threads_open":
            $metrics = array (
                'threads_open' => "Open"
            );
            $yAxisTitle = "Info";
            doChart("line",$yAxisTitle, $start, $end, $metrics, $forceLimits);
            break;
        case "threads_active":
            $metrics = array (
                'threads_active' => "Active"
            );
            $yAxisTitle = "Info";
            doChart("line",$yAxisTitle, $start, $end, $metrics, $forceLimits);
            break;
        default:
            $yAxisTitle = "";
            doChart("line",$yAxisTitle, $start, $end, $metrics, $forceLimits);
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
<h1>OpenLDAP Statistics</h1>
<form name=range method=get">
Start: <input type=text name=start value="<?=$start?>" />
End: <input type=text name=end value="<?=$end?>" />
<input type=hidden name="date" value="<?=$date?>" />
<input type=hidden name="dir" value="<?=$dir?>" />
<input type=hidden name="oss" value="<?=$oss?>" />
<input type=hidden name="site" value="<?=$site?>" />
<input type=submit name=submit value="update" />
</form>
<h2>Overview</h2>
<h2>Operations</h2>
<?php
echo getOperationsStatGraph('Operations','Completed', $start,$end,
                             array (
                                    'operations_bind' => "Bind",
                                    'operations_unbind' => "Unbind",
                                    'operations_search' => "Search",
                                    'operations_compare' => "Compare",
                                    'operations_modify' => "Modify",
                                    'operations_modrdn' => "Mod RDN",
                                    'operations_add' => "Add",
                                    'operations_delete' => "Delete"
                                    ),
                             'sa' );
?>
<h2>Statistics</h2>
<h4>Bytes</h4>
<img src="?chart=stats_bytes&<?=$args?>" />
<h4>Entries</h4>
<img src="?chart=stats_entries&<?=$args?>" />
<h2>Threads</h2>
<h4>Max</h4>
<img src="?chart=threads_max&<?=$args?>" />
<h4>Open</h4>
<img src="?chart=threads_open&<?=$args?>" />
<h4>Active</h4>
<img src="?chart=threads_active&<?=$args?>" />

<?php

include "common/finalise.php";
?>
