<?php
$pageTitle = "Report Statistics";

require_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
const SITES='sites';
const REPORT='Report Name';
const NAME='reportName';

function showReportStatisticsdataTable() {
    global $site, $date, $statsDB;
    $colsArr = array(
                    REPORT => NAME ,
                    'Refresh Time' => 'max(time)',
                    'Duration (Sec)' => 'round(duration/1000,2)'
                );
    $name = 'ReportStatisticsdata';
    $title = 'Report Refreshed';
    $table = array("bis_report_refresh_time", SITES);
    $where = $statsDB->where("bis_report_refresh_time", 'time');
    $where .= " AND bis_report_refresh_time.duration IN (SELECT MAX(duration) FROM
        bis_report_refresh_time, sites WHERE sites.name = '$site' AND
        sites.id = bis_report_refresh_time.siteid AND
        bis_report_refresh_time.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        GROUP BY reportName )";
    $where .= "GROUP BY bis_report_refresh_time.reportName ";
    $where .= "ORDER BY duration DESC ";

    drawHeaderWithHelp($title, 2, $name);

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables($table)
            ->where($where);

    foreach ($colsArr as $key => $value) {
        $table->addSimpleColumn($value, $key);
    }

    echo $table->paginate( array(20, 100, 1000, 10000) )
               ->build()
               ->getTable();
    echo addLineBreak(2);
}

function mainflow() {
    global $site, $date;
    $statsDB = new StatsDB();
    $table = new ModelledTable('ENIQ/bisReportInstancesTableInfo', 'ReportInstances');
    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader( 'Report Instances' );
    }
    $table = new ModelledTable('ENIQ/bisReportListTableInfo', 'ReportListInstances');
    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader( 'Report List' );
    }

    $statsDB->query("
        SELECT
            count(bis_report_refresh_time.cuid)
        FROM
            bis_report_refresh_time, bis_prompt_info, sites
        WHERE
            sites.name = '$site' AND
            sites.id = bis_report_refresh_time.siteid AND
            bis_report_refresh_time.siteid = bis_prompt_info.siteid AND
            bis_report_refresh_time.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            bis_prompt_info.cuid = bis_report_refresh_time.cuid
            having count(bis_report_refresh_time.cuid) > 0
    ");

    if ($statsDB->getNumRows() > 0) {
        $table = new ModelledTable('ENIQ/bisReportRefreshedTableInfo', 'ReportRefreshedData');
        if ( $table->hasRows() ) {
            echo $table->getTableWithHeader( 'Report Refreshed Data Instances' );
        }
        drawHeader( 'Report Refreshed Graph', 2, 'ReportDataHelp' );
        $modelledGraph = new ModelledGraph('ENIQ/bisReportRefreshedInfo');
        plotgraphs( array( $modelledGraph->getImage() ) );

    } else {

        if ( $statsDB->hasData('bis_report_refresh_time', 'time') ) {
            showReportStatisticsdataTable();
        }
        drawHeader( 'Report Refreshed Graph', 2, 'ReportDataHelp' );
        $modelledGraph = new ModelledGraph('ENIQ/bisReportRefreshedInfo');
        plotgraphs( array( $modelledGraph->getImage() ) );
    }
}

mainflow();
include_once "../common/finalise.php";
