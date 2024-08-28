<?php
$pageTitle = "Loading";
$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

class LoadDailyTotal extends DDPObject {
    var $cols = array(
                    'type'       => 'Type',
                    'avg_rows'   => 'Average Rows',
                    'max_rows'   => 'Max Rows',
                    'total_rows' => 'Total Rows',
                    'avg_time'   => 'Average Time',
                    'max_time'   => 'Max Time',
                    'total_time' => 'Total Time'
                );

    var $title = "Loading";

    function __construct() {
        parent::__construct("loadTotals");
        $this->defaultOrderBy = "total_rows";
        $this->defaultOrderDir = "DESC";
    }

    function getData() {
        global $date;
        global $site;

        $sqlParam =
            array( 'title'   => 'Loader Session Duration for %s',
                'targs'      => array('typename'),
                'ylabel'     => 'Duration (sec)',
                'type'       => 'sb',
                'useragg'    => 'true',
                'persistent' => 'true',
                'querylist'  =>
                array(
                    array(
                        'timecol' => 'eniq_stats_loader_sessions.minstart',
                        'whatcol' => array('TIME_TO_SEC(TIMEDIFF(eniq_stats_loader_sessions.maxend,eniq_stats_loader_sessions.minstart))' => 'Duration (sec)'),
                        'tables'  => "eniq_stats_loader_sessions, sites",
                        'where'   => "eniq_stats_loader_sessions.siteid = sites.id AND sites.name = '%s' AND eniq_stats_loader_sessions.typeid = %d",
                        'qargs'   => array('site', 'typeid')
                    )
                )
            );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $urlBase = $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59");

        $sql = "
            SELECT
             eniq_stats_loader_sessions.typeid, eniq_stats_types.name AS type,
             ROUND( AVG(eniq_stats_loader_sessions.total_rows), 0) AS avg_rows,
             MAX(eniq_stats_loader_sessions.total_rows) AS max_rows,
             SUM(eniq_stats_loader_sessions.total_rows) AS total_rows,
             SEC_TO_TIME( AVG( TIME_TO_SEC(TIMEDIFF(eniq_stats_loader_sessions.maxend,eniq_stats_loader_sessions.minstart) ) ) ) AS avg_time,
             SEC_TO_TIME( MAX( TIME_TO_SEC(TIMEDIFF(eniq_stats_loader_sessions.maxend,eniq_stats_loader_sessions.minstart) ) ) ) AS max_time,
             SEC_TO_TIME( SUM( TIME_TO_SEC(TIMEDIFF(eniq_stats_loader_sessions.maxend,eniq_stats_loader_sessions.minstart) ) ) ) AS total_time
            FROM
             eniq_stats_loader_sessions, eniq_stats_types, sites
            WHERE
             eniq_stats_loader_sessions.siteid = sites.id AND sites.name = '$site' AND
             eniq_stats_loader_sessions.typeid = eniq_stats_types.id AND
             eniq_stats_loader_sessions.minstart BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY eniq_stats_loader_sessions.typeid
        ";
        $this->populateData($sql);
        foreach ($this->data as &$row) {
            $row['type'] = sprintf("<a href=\"%s\">%s</a>", $urlBase . "&typeid=" . $row['typeid'] . "&typename=" . $row['type'], $row['type']);
        }
        return $this->data;
    }
}

if (isset($_GET['start'])) {
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
} else {
    $fromDate = $date;
    $toDate = $date;
}

$statsDB = new StatsDB();

echo "<H1>Loading</H1>\n";
drawHeader("Running Loader Sessions", 2, "runningLoaderHelp");
$sqlParam =
    array( 'title'   => 'Running Loader Sessions',
        'ylabel'     => 'Sessions',
        'type'       => 'sb',
        'useragg'    => 'false',
        'persistent' => 'true',
        'querylist'  =>
        array(
            array(
                'timecol' => 'time',
                'whatcol' => array('running' => 'Sessions'),
                'tables'  => "eniq_stats_loader_running, sites",
                'where'   => "eniq_stats_loader_running.siteid = sites.id AND sites.name = '%s'",
                'qargs'   => array('site')
            )
        )
    );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );

drawHeader("Loaders Processing Time", 2, "loadersProcessingHelp");
$sqlParam =
    array( 'title'   => 'Loaders Processing Time',
        'ylabel'     => 'Duration (sec)',
        'type'       => 'sb',
        'useragg'    => 'true',
        'persistent' => 'true',
        'presetagg'  => 'SUM:Per Minute',
        'querylist'  =>
        array(
            array(
                'timecol'  => 'minstart',
                'whatcol'  => array('TIME_TO_SEC(TIMEDIFF(eniq_stats_loader_sessions.maxend,eniq_stats_loader_sessions.minstart))' => 'Duration (sec)'),
                'tables'   => "eniq_stats_loader_sessions, eniq_stats_types, sites",
                'where'    => "eniq_stats_loader_sessions.siteid = sites.id AND eniq_stats_loader_sessions.typeid = eniq_stats_types.id  AND sites.name = '%s'",
                'qargs'    => array('site')
            )
        )
    );

$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240);
echo "<br>";

function plotGraphsforBulkCMloader() {

    $name = "%BULK_CM%";
    $params = array( 'name' => $name );
    drawHeader("Bulk CM Loader Set Count", 2, "bulkCMLodaerTriggerCount");
    $modelledGraph = new ModelledGraph('ENIQ/bulk_cm_loader_trigger_set_count');
    plotgraphs( array( $modelledGraph->getImage($params) ) );
    drawHeader("Non Bulk CM Loader Set Count", 2, "nonBulkCMLodaerTriggerCount");
    $modelledGraph = new ModelledGraph('ENIQ/non_bulk_cm_loader_trigger_set_count');
    plotgraphs( array( $modelledGraph->getImage($params) ) );
}

plotGraphsforBulkCMloader();
echo addLineBreak();
drawHeader("Daily Totals", 2, "dailyHelp");
$totalTable = new LoadDailyTotal();
echo $totalTable->getClientSortableTableStr();


include "../common/finalise.php";
?>