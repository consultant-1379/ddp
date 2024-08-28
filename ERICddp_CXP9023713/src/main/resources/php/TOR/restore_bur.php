<?php

$pageTitle = "Restore Stage Info";
$pageType = "restore_stages";
if ( isset( $_GET['page_type'] ) ) {
    if ( $_GET['page_type'] == 'restore_throughput' ) {
        $pageTitle = "Restore Throughput Info";
        $pageType = "restore_throughput";
    } else if ( $_GET['page_type'] == 'restore_stages' ) {
        $pageTitle = "Restore Stage Info";
        $pageType = "restore_stages";
    }
}
$YUI_DATATABLE = true;

include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/JsPlot.php";

require_once 'HTML/Table.php';


class RestoreStageStats extends DDPObject {
    var $cols = array(
                      'start_time'   => 'Start Time',
                      'end_time'     => 'End Time',
                      'restore_stage' => 'Restore Stage',
                      'duration'     => 'Duration (hh:mm:ss)'
                      );
    var $keyword = "";

    public function __construct($keyword) {
        parent::__construct("bur" . $keyword);
        $this->keyword = $keyword;
    }

    function getData()  {
        global $date, $site;
        $keyword = $this->keyword;
        $sql = "
SELECT
    CAST(enm_bur_restore_stage_stats.start_time AS time) AS start_time,
    IFNULL( CAST(enm_bur_restore_stage_stats.end_time AS time), 'NA' ) AS end_time,
    enm_bur_restore_stage_names.restore_stage_name AS restore_stage,
    IFNULL( SEC_TO_TIME(enm_bur_restore_stage_stats.duration), 'NA')  AS duration
FROM
    enm_bur_restore_stage_stats,
    enm_bur_restore_stage_names,
    enm_bur_restore_keywords,
    sites
WHERE
    enm_bur_restore_stage_stats.siteid = sites.id AND
    enm_bur_restore_stage_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_stage_stats.restore_stage_id = enm_bur_restore_stage_names.id AND
    enm_bur_restore_keywords.restore_keyword = '$keyword' AND
    sites.name = '$site' AND
    enm_bur_restore_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY start_time";

        $this->populateData($sql);
        return $this->data;
    }
}


class RestoreThroughputDailyTotals extends DDPObject {
    var $cols = array(
                      array('key' => 'start_time', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'end_time', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'restore_keyword', 'label' => 'Restore'),
                      array('key' => 'overall_throughput_mbps', 'label' => 'Overall Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'overall_parallel_throughput_mbps', 'label' => 'Overall Parallel Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'no_of_filesystems', 'label' => 'Num of Filesystems', 'formatter' => 'ddpFormatNumber'),
                      array('key' => 'total_size', 'label' => 'Total Size (MB)', 'formatter' => 'ddpFormatNumber')
                      );

    function __construct() {
        parent::__construct("restoreThroughputDailyTotals");
    }

    var $title = "Daily Totals";

    function getData()  {
        global $site, $date;

        $sql = "
SELECT
    IFNULL( MIN(enm_bur_restore_throughput_stats.start_time), 'NA' ) AS 'start_time',
    IFNULL( MAX(enm_bur_restore_throughput_stats.end_time), 'NA' ) AS 'end_time',
    IFNULL(enm_bur_restore_keywords.restore_keyword, 'All') AS 'restore_keyword',
    IFNULL( ROUND(
        SUM( CASE
                WHEN enm_bur_restore_throughput_stats.start_time IS NOT NULL AND
                     enm_bur_restore_throughput_stats.end_time IS NOT NULL
                THEN enm_bur_restore_throughput_stats.filesystem_used_size
                ELSE 0
             END ) /
        SUM( CASE
                WHEN enm_bur_restore_throughput_stats.start_time = enm_bur_restore_throughput_stats.end_time
                THEN 1
                ELSE TIMESTAMPDIFF(SECOND, enm_bur_restore_throughput_stats.start_time, enm_bur_restore_throughput_stats.end_time)
             END )
        , 2 ), 'NA' ) AS 'overall_throughput_mbps',
    IFNULL( ROUND(
        SUM(enm_bur_restore_throughput_stats.filesystem_used_size) /
        CASE
            WHEN MIN(enm_bur_restore_throughput_stats.start_time) = MAX(enm_bur_restore_throughput_stats.end_time)
            THEN 1
            ELSE TIMESTAMPDIFF( SECOND, MIN(enm_bur_restore_throughput_stats.start_time), MAX(enm_bur_restore_throughput_stats.end_time) )
        END
        , 2 ), 'NA' ) AS 'overall_parallel_throughput_mbps',
    CASE
        WHEN MIN(enm_bur_restore_throughput_stats.start_time) = MAX(enm_bur_restore_throughput_stats.end_time)
        THEN 1
        WHEN MIN(enm_bur_restore_throughput_stats.start_time) IS NULL OR
             MAX(enm_bur_restore_throughput_stats.end_time) IS NULL
        THEN 'NA'
        ELSE TIMESTAMPDIFF( SECOND, MIN(enm_bur_restore_throughput_stats.start_time), MAX(enm_bur_restore_throughput_stats.end_time) )
    END AS 'overall_parallel_restore_time',
    COUNT(enm_bur_restore_throughput_stats.backup_mount_point_id) AS 'no_of_filesystems',
    SUM(enm_bur_restore_throughput_stats.filesystem_used_size) AS 'total_size'
FROM
    sites,
    enm_bur_restore_keywords,
    enm_bur_restore_throughput_stats
WHERE
    enm_bur_restore_throughput_stats.siteid = sites.id AND
    enm_bur_restore_throughput_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    sites.name = '$site' AND
    enm_bur_restore_throughput_stats.date = '$date'
GROUP BY enm_bur_restore_keywords.restore_keyword WITH ROLLUP";

        $this->populateData($sql);

        $overallParallelBkpTimeTotal = 0;
        $overallRestoreSizeTotal = 0;
        foreach ($this->data as $key => $row) {
            if ( $row['restore_keyword'] != 'All' && $row['overall_parallel_restore_time'] != 'NA' ) {
                $overallParallelBkpTimeTotal += $row['overall_parallel_restore_time'];
                $overallRestoreSizeTotal += $row['total_size'];
            }
        }
        if ( $overallParallelBkpTimeTotal <= 0 ) {
            $overallParallelBkpTimeTotal = 1;
        }
        $overallParallelThruputTotal = round($overallRestoreSizeTotal/$overallParallelBkpTimeTotal, 2);

        foreach ($this->data as &$row) {
            if ( $row['restore_keyword'] == 'All' ) {
                $row['start_time'] = "Totals";
                $row['end_time'] = "";
                $row['overall_parallel_throughput_mbps'] = $overallParallelThruputTotal;
            }
        }

        return $this->data;
    }
}


class RestoreThroughputDetails extends DDPObject {
    var $cols = array(
                      array('key' => 'start_time', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'end_time', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'restore_keyword', 'label' => 'Restore'),
                      array('key' => 'host', 'label' => 'Host'),
                      array('key' => 'throughput_mb_per_sec', 'label' => 'Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'backup_mount_point', 'label' => 'Backup Mount Point'),
                      array('key' => 'filesystem', 'label' => 'Filesystem'),
                      array('key' => 'filesystem_used_size', 'label' => 'Filesystem Used Size (MB)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'filesystem_size', 'label' => 'Filesystem Total Size (MB)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );
    var $restoreKeyword = "";

    function __construct($restoreKeyword = '%', $restoreSerialNo = 1) {
        parent::__construct("RestoreThroughputDetails_" . $restoreSerialNo);
        $this->restoreKeyword = $restoreKeyword;
    }

    var $title = "Throughput Details";

    function getData()  {
        global $site, $date;

        $sql = "
SELECT
    IFNULL(enm_bur_restore_throughput_stats.start_time, 'NA') AS 'start_time',
    IFNULL(enm_bur_restore_throughput_stats.end_time, 'NA') AS 'end_time',
    enm_bur_restore_keywords.restore_keyword AS 'restore_keyword',
    servers.hostname AS 'host',
    IFNULL( ROUND(enm_bur_restore_throughput_stats.throughput_mb_per_sec, 2), 'NA' ) AS 'throughput_mb_per_sec',
    IF(enm_bur_backup_mount_points.backup_mount_point = '', 'NA', backup_mount_point) AS 'backup_mount_point',
    IF(enm_bur_restore_filesystems.fs_name = '', 'NA', fs_name) AS 'filesystem',
    IFNULL(enm_bur_restore_throughput_stats.filesystem_used_size, 'NA') AS 'filesystem_used_size',
    IFNULL(enm_bur_restore_throughput_stats.filesystem_size, 'NA') AS 'filesystem_size'
FROM
    sites,
    servers,
    enm_bur_restore_filesystems,
    enm_bur_restore_keywords,
    enm_bur_backup_mount_points,
    enm_bur_restore_throughput_stats
WHERE
    enm_bur_restore_throughput_stats.siteid = sites.id AND
    enm_bur_restore_throughput_stats.serverid = servers.id AND
    enm_bur_restore_throughput_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_throughput_stats.backup_mount_point_id = enm_bur_backup_mount_points.id AND
    enm_bur_restore_throughput_stats.filesystem_id = enm_bur_restore_filesystems.id AND
    servers.siteid = sites.id AND
    enm_bur_restore_keywords.restore_keyword like '$this->restoreKeyword' AND
    sites.name = '$site' AND
    enm_bur_restore_throughput_stats.date = '$date'
ORDER BY start_time";

        $this->populateData($sql);
        $this->defaultOrderBy = "start_time";
        $this->defaultOrderDir = "ASC";

        return $this->data;
    }
}


function getThroughputPerfQueryList($restoreKeyword = "", $statsDB) {
    global $site, $date, $debug;

    # Get the list of all hosts associated with the given restore
    $hosts = array();
    $statsDB->query("
SELECT
    DISTINCT servers.hostname
FROM
    enm_bur_restore_throughput_stats,
    enm_bur_restore_keywords,
    servers,
    sites
WHERE
    enm_bur_restore_throughput_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_throughput_stats.serverid = servers.id AND
    enm_bur_restore_throughput_stats.siteid = sites.id AND
    servers.siteid = sites.id AND
    enm_bur_restore_keywords.restore_keyword = '$restoreKeyword' AND
    enm_bur_restore_throughput_stats.date = '$date' AND
    sites.name = '$site'");

    while ( $row_data = $statsDB->getNextRow() ) {
        $hosts[] = $row_data[0];
    }

    $queryList = array();
    foreach ($hosts as $index => $host) {
        $query = array(
                       'timecol' => 'start_time',
                       'whatcol' => array('ROUND(enm_bur_restore_throughput_stats.throughput_mb_per_sec, 2)' => $host),
                       'tables' => "enm_bur_restore_throughput_stats, enm_bur_restore_keywords, servers, sites",
                       'where' => "enm_bur_restore_throughput_stats.siteid = sites.id AND
                                   enm_bur_restore_throughput_stats.serverid = servers.id AND
                                   enm_bur_restore_throughput_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
                                   servers.siteid = sites.id AND
                                   enm_bur_restore_throughput_stats.start_time IS NOT NULL AND
                                   enm_bur_restore_throughput_stats.throughput_mb_per_sec IS NOT NULL AND
                                   enm_bur_restore_keywords.restore_keyword = '$restoreKeyword' AND
                                   servers.hostname = '$host' AND
                                   sites.name = '$site' AND
                                   enm_bur_restore_throughput_stats.date = '$date'",
                       );
        $queryList[] = $query;
    }

    return $queryList;
}


function mainFlowRestoreStages($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site, $web_temp_dir;

    $restoreStageKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_restore_keywords.restore_keyword
FROM
    enm_bur_restore_stage_stats,
    enm_bur_restore_keywords,
    sites
WHERE
    enm_bur_restore_stage_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_stage_stats.siteid = sites.id AND
    enm_bur_restore_keywords.restore_keyword != '' AND
    sites.name = '$site' AND
    enm_bur_restore_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY enm_bur_restore_stage_stats.start_time");

    while ( $row_data = $statsDB->getNextRow() ) {
        $restoreStageKeywords[] = $row_data[0];
    }

    $restoreThruputKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_restore_keywords.restore_keyword
FROM
    enm_bur_restore_throughput_stats,
    enm_bur_restore_keywords,
    sites
WHERE
    enm_bur_restore_throughput_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_throughput_stats.siteid = sites.id AND
    enm_bur_restore_keywords.restore_keyword != '' AND
    sites.name = '$site' AND
    enm_bur_restore_throughput_stats.date = '$date'
ORDER BY enm_bur_restore_throughput_stats.start_time");

    while ( $row_data = $statsDB->getNextRow() ) {
        $restoreThruputKeywords[$row_data[0]] = 1;
    }

    if ( count($restoreThruputKeywords) > 0 ) {
        echo "<h2>Restore Throughput Details</h2>\n";
        echo "<ul>\n";
        echo "  <li><span title='Click here to go to the page containing the restore throughput details'>" .
             "<a href=\"$php_webroot/TOR/restore_bur.php?$webargs&page_type=restore_throughput\">Click Here</a></span></li>\n";
        echo "</ul><br/>\n";
    }

    foreach ($restoreStageKeywords as $value) {
        $timings = array();
        $row = array();
        $dbSizeHelpBubble = "DDP_Bubble_425_ENM_Bur_Restore_Logs";
        drawHeaderWithHelp("Restore " . $value, 2, "RestoreStages_$value", $dbSizeHelpBubble);
        if ( array_key_exists($value, $restoreThruputKeywords) ) {
            echo "<ul>\n";
            echo "  <li><span title='Click here to go to the table containing the throughput details of this restore'>" .
                 "<a href=\"$php_webroot/TOR/restore_bur.php?$webargs&page_type=restore_throughput#$value" . "_anchor\">Throughput Details</a></span></li>\n";
            echo "</ul>\n";
        }

        $restoreStageStats = new RestoreStageStats($value);
        $statsDB->query("
SELECT
    enm_bur_restore_stage_names.restore_stage_name AS restore_stage,
    SUM(enm_bur_restore_stage_stats.duration) AS duration
FROM
    enm_bur_restore_stage_stats,
    enm_bur_restore_stage_names,
    enm_bur_restore_keywords,
    sites
WHERE
    enm_bur_restore_stage_stats.siteid = sites.id AND
    enm_bur_restore_stage_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_stage_stats.restore_stage_id = enm_bur_restore_stage_names.id AND
    enm_bur_restore_stage_names.restore_stage_name != 'BOSsystem_restoreOperation' AND
    enm_bur_restore_stage_stats.duration IS NOT NULL AND
    enm_bur_restore_keywords.restore_keyword = '$value' AND
    sites.name = '$site' AND
    enm_bur_restore_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_bur_restore_stage_names.restore_stage_name");

        while ( $row = $statsDB->getNextNamedRow() ) {
            $duration = $row['duration'];
            $str_tmp = $row['restore_stage'];
            $timings[] = array($str_tmp, $duration);
        }

        $filename = tempnam($web_temp_dir, "");
        file_put_contents( $filename, json_encode(array('name' => 'Stages', 'data' => $timings)) );

        $sqlParam = array(
            'title' => "$value",
            'type' => 'pie',
            'ylabel' => "",
            'useragg' => 'false',
            'persistent' => 'false',
            'seriesfile' => $filename
        );

        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 400, 400);
        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow( array($restoreStageStats->getHtmlTableStr(),
                             $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", false, 400, 400) ) );
        echo $graphTable->toHTML();
        echo "<br/>";
    }
}


function mainFlowRestoreThroughput($statsDB) {
    global $site, $date, $php_webroot, $webargs, $debug;

    # Display the 'Daily Totals' table
    drawHeaderWithHelp("Daily Totals", 2, "dailyTotalsHelp", "DDP_Bubble_338_BUR_Restore_Throughput_Daily_Totals");
    $restoreThroughputDailyTotals = new RestoreThroughputDailyTotals();
    echo $restoreThroughputDailyTotals->getClientSortableTableStr() . "<br/>";

    # Get the list of restores for which throughput details are available for the given day
    $restoreThruputKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_restore_keywords.restore_keyword
FROM
    enm_bur_restore_throughput_stats,
    enm_bur_restore_keywords,
    sites
WHERE
    enm_bur_restore_throughput_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_throughput_stats.siteid = sites.id AND
    enm_bur_restore_throughput_stats.date = '$date' AND
    sites.name = '$site'
ORDER BY enm_bur_restore_throughput_stats.start_time");

    if ( isset($_GET['all_restores']) ) {
        # Display the throughput details for all the restores and return
        echo "<br/>";
        $thruputTableExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=restore_throughput_details">[Download Excel]</a>';
        drawHeaderWithHelp("Throughput Details (All Restores)", 2, "ThruputAllRestoresHelp", "DDP_Bubble_426_BUR_Restore_Throughput_Daily_Totals", "", $thruputTableExcelLink);
        $restoreThroughputDetails = new RestoreThroughputDetails();
        echo $restoreThroughputDetails->getClientSortableTableStr(1000, array(2000, 5000, 10000));
        echo "<br/>";
        return;
    }

    echo "<ul>\n";
    while ( $row_data = $statsDB->getNextRow() ) {
        echo "  <li><span title='Click here to go to the table containing the throughput details of this restore'>" .
             "<a href=\"#$row_data[0]" . "_anchor\">Restore $row_data[0]</a></span></li>";
        $restoreThruputKeywords[] = $row_data[0];
    }
    if ( count($restoreThruputKeywords) > 1 ) {
        echo "  <li><span title='Click here to view the throughput details of all the restores for the given day under one single table'>" .
             "<a href=\"$php_webroot/TOR/restore_bur.php?$webargs&page_type=restore_throughput&all_restores=1#ThruputAllRestoresHelp_anchor\">All Restores</a></span></li>";
    }
    echo "</ul><br/>\n";

    # Display the throughput details for each restore, one after the other
    $restoreSerialNo = 1;
    foreach ($restoreThruputKeywords as $restoreKeyword) {
        echo "<h2>Restore $restoreKeyword<a name=\"" . $restoreKeyword . "_anchor\"></a></h2>\n";

        # Display the throughput details performance graph (scatter plot)
        $queryList = getThroughputPerfQueryList($restoreKeyword, $statsDB);
        $sqlPlotParam = array(
            'title' => "Throughput Performance",
            'type' => 'xy',
            'xlabel' => "Start Time",
            'ylabel' => "Throughput (MB/sec)",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => $queryList
        );
        echo '<div id="throughputPerfGraph_' . $restoreSerialNo . '" style="height: 400px"></div>' . "\n";
        $tstart = "$date 00:00:00";
        $tend = date( 'Y-m-d', strtotime($date . ' +2 day') ) . ' 23:59:59';
        $jsPlot = new JsPlot();
        $jsPlot->show($sqlPlotParam, 'throughputPerfGraph_' . $restoreSerialNo , array(
                                                                                      'tstart' => $tstart,
                                                                                      'tend' => $tend,
                                                                                      'aggType' => 0,
                                                                                      'aggInterval' => 0,
                                                                                      'aggCol' => "",
                                                                                      )
                      );
        echo "<br/>";

        # Display the throughput details table
        $thruputTableExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=restore_throughput_details&restore_keyword=' .
                                 $restoreKeyword . '">[Download Excel]</a>';
        drawHeaderWithHelp("Throughput Details", 2, $restoreKeyword . "TdHelp", "DDP_Bubble_427_BUR_Restore_Throughput_Details", "", $thruputTableExcelLink);
        $restoreThroughputDetails = new RestoreThroughputDetails($restoreKeyword, $restoreSerialNo);
        echo $restoreThroughputDetails->getClientSortableTableStr(200, array(500, 1000, 5000));
        echo "<br/>";

        $restoreSerialNo++;
    }
}


if (isset($_GET['format']) && $_GET['format'] == "xls" && isset($_GET['table'])) {
    $table;
    if ( $_GET['table'] == "restore_throughput_details" ) {
        if ( isset($_GET['restore_keyword']) ) {
            $table = new RestoreThroughputDetails($_GET['restore_keyword']);
        } else {
            $table = new RestoreThroughputDetails();
        }
        $table->title = "Throughput Details";
    } else {
        echo "Invalid table name: " . $_GET['table'];
        exit;
    }

    ob_clean();
    $excel = new ExcelWorkbook();
    $excel->addObject($table);
    $excel->write();
    exit;
}

$statsDB = new StatsDB();
if ( $pageType == 'restore_throughput' ) {
    mainFlowRestoreThroughput($statsDB);
} else if ( $pageType == 'restore_stages' ) {
    mainFlowRestoreStages($statsDB);
}

include PHP_ROOT . "/common/finalise.php";

?>
