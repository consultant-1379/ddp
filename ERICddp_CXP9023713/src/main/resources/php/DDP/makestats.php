<?php
$pageTitle = "MakeStats";

$YUI_DATATABLE = TRUE;

if (isset($_REQUEST["action"]) && $_REQUEST["action"] == 'plotscripts' ) {
    $UI = false;
}

include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . '/classes/SqlTable.php';
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function showSite($selectedSite,$startdate,$enddate) {
    global $webargs;

    $sqlParam =
              array( 'title'  => 'Makestats for %s',
                     'targs'  => array( 'sitename' ),
                     'ylabel' => 'Seconds',
                     'useragg' => 'true',
                     'persistent' => 'false',
                     'querylist' =>
                     array(
                         array(
                             'timecol' => 'beginproc',
                             'whatcol' => array( 'TIME_TO_SEC(TIMEDIFF(endproc,beginproc))' => 'duration' ),
                             'tables'  => "ddpadmin.ddp_makestats, sites",
                             'where'   => "ddpadmin.ddp_makestats.siteid = sites.id AND sites.name = '%s'",
                             'qargs'   => array( 'sitename' )
                         )
                     )
              );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL( $id, "$startdate 00:00:00", "$enddate 23:59:59", true, 640, 240, "sitename=$selectedSite" );

    $sqlTable =
              new SqlTable("makestats_site",
                           array(
                               array( 'key' => 'id', 'db' => "CONCAT(filedate,'@',beginproc)", 'visible' => false ),
                               array( 'key' => 'date', 'db' => 'filedate', 'label' => 'DDC Date' ),
                               array( 'key' => 'size', 'db' => 'ROUND(filesize/(1024),0)', 'label' => 'Size (MB)' ),
                               array( 'key' => 'uploaded', 'db' => 'uploaded', 'label' => 'Uploaded', 'formatter' => 'ddpFormatTime' ),
                               array( 'key' => 'start', 'db' => 'beginproc', 'label' => 'Start Processing', 'formatter' => 'ddpFormatTime' ),
                               array( 'key' => 'proc_duration', 'db' => 'TIMEDIFF(endproc,beginproc)', 'label' => 'Processing Time' ),
                               array( 'key' => 'proc_delay', 'db' => 'TIMEDIFF(beginproc,uploaded)', 'label' => 'Processing Delay' ),

                           ),
                           array('sites','ddpadmin.ddp_makestats'),
                           "sites.id = ddpadmin.ddp_makestats.siteid AND ddpadmin.ddp_makestats.beginproc BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND sites.name = '$selectedSite'",
                           TRUE,
                           array(
                               'order' => array( 'by' => 'start', 'dir' => 'ASC' )
                           )
              );
    echo $sqlTable->getTable();
}

function monthlyView() {
    return array(
        'proc_dur_daily_avg',
        'proc_delay_daily_avg',
        'files_uploaded_daily_cnt',
        'files_processed_daily_cnt',
        'file_size_daily_sum',
    );
}

function dailyView() {
    return array(
        'proc_dur',
        'proc_delay',
        'files_uploaded_hourly_cnt',
        'files_processed_hourly_cnt',
        'file_size_hourly_sum',
    );
}

function plotGraphViews( $params, $startdate, $enddate ) {
    $graphs = array();
    foreach ( $params as $param ) {
        $modelledGraph = new ModelledGraph('DDP/' . $param);
        $graphs[] = $modelledGraph->getImage(array(), $startdate . ' 00:00:00', $enddate . ' 23:59:59');
    }
    plotGraphs( $graphs );
}

function mainFlow($startdate, $enddate, $monthly) {
    global $webargs, $date;

    if ( $monthly === 1 ) {
        $params = monthlyView();
        $tblArgs = $webargs . "&start=$startdate&end=$enddate";
    } else {
        $params = dailyView();
        $tblArgs = $webargs;
    }

    plotGraphViews($params, $startdate, $enddate );

    echo "<H1>Makestats Table</H1>\n";
    $selfLink = fromServer('PHP_SELF');
    $sqlTable =
              new SqlTable("makestats_allsites",
                           array(
                               array( 'key' => 'site', 'db' => 'sites.name', 'label' => 'Site' ),
                               array( 'key' => 'count', 'db' => 'COUNT(*)', 'label' => 'Count' ),
                               array( 'key' => 'avg', 'db' => 'SEC_TO_TIME(IFNULL(ROUND(AVG(TIME_TO_SEC(TIMEDIFF(endproc,beginproc)))),0))', 'label' => 'Average Time' ),
                               array( 'key' => 'max', 'db' => 'IFNULL(MAX(TIMEDIFF(endproc,beginproc)),0)', 'label' => 'Max Time' )
                           ),
                           array('sites','ddpadmin.ddp_makestats'),
                           "sites.id = ddpadmin.ddp_makestats.siteid AND ddpadmin.ddp_makestats.beginproc BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' GROUP BY ddpadmin.ddp_makestats.siteid",
                           TRUE,
                           array(
                               'order' => array( 'by' => 'max', 'dir' => 'DESC' ),
                               'ctxMenu' => array('key' => 'action',
                                                  'multi' => false,
                                                  'menu' => array( 'showsite' => 'Show makeStats for site'),
                                                  'url' => $selfLink . "?" . $tblArgs,
                                                  'col' => 'site')
                           )
              );
    echo $sqlTable->getTable();
}

$monthly = 0;

if ( $date ) {
    $startdate = $date;
    $enddate = $date;
} else {
    $startdate = $_GET['start'];
    $enddate = $_GET['end'];
    $monthly = 1;
}

if ( isset($_REQUEST['action']) ) {
    if ( $_REQUEST['action'] === 'showsite' ) {
        showSite($_REQUEST['selected'],$startdate,$enddate);
    }
} else {
    mainFlow($startdate, $enddate, $monthly);
}

include PHP_ROOT . "/common/finalise.php";
?>
