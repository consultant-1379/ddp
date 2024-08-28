<?php
$pageTitle = "Notification Analysis";

include_once "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const ENM_MSCM_NOTIF_LOG = "enm_mscmnotification_logs";
const ENM_CM_NODE_EVIC  = "enm_cm_nodeevictions";
const RECEIVED = "Received";
const PROCESSED = "Processed";
const DISCARDED = "Discarded";
const LEADTIMEMAX = "Lead Time Max";
const VALIDATIONMAX = "Validation Handler Max";
const VALIDATIONAVG = "Validation Handler Avg";
const WRITEHANDLERMAX = "Write Handler Max";
const CNT = "Count";
const EVICTIONS = "evictions";

function processingDailyTotalsTable() {
    global $statsDB;
    $where = $statsDB->where( ENM_MSCM_NOTIF_LOG );
    $where .= " AND enm_mscmnotification_logs.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $tables = array( ENM_MSCM_NOTIF_LOG, StatsDB::SITES, StatsDB::SERVERS );

    $table = SqlTableBuilder::init()
        ->name("processingDailyTotals")
        ->tables($tables)
        ->where($where)
        ->addColumn('inst', "IFNULL(servers.hostname,'All Instances')", 'Instance')
        ->addSimpleColumn('SUM(totalnotificationsreceived)', RECEIVED)
        ->addSimpleColumn('SUM(totalnotificationsprocessed)', PROCESSED)
        ->addSimpleColumn('SUM(totalnotificationsdiscarded)', DISCARDED)
        ->addSimpleColumn('SUM(evictions)', 'Evictions')
        ->addSimpleColumn('MAX(largeNodeCacheMax)', 'Large Node Cache Max')
        ->addSimpleColumn('MAX(cachesizemax)', 'Small Node Cache Max')
        ->addSimpleColumn('MAX(leadtimemax)', LEADTIMEMAX)
        ->addSimpleColumn('MAX(validationhandlertimemax)', VALIDATIONMAX)
        ->addSimpleColumn('MAX(writehandlertimemax)', WRITEHANDLERMAX)
        ->build();

    if ( $table->hasRows() ) {
        echo drawHeader("Daily Totals", HEADER_1, "processingDailyTotals");
        echo $table->getTable();
    }
}

function nodeEvictionDetailsTable() {
    global $statsDB;
    $where = $statsDB->where( ENM_CM_NODE_EVIC );
    $where .= " AND enm_cm_nodeevictions.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $tables = array( ENM_CM_NODE_EVIC, StatsDB::SITES, StatsDB::SERVERS );

    $table = SqlTableBuilder::init()
        ->name("nodeEvictionDetails")
        ->tables($tables)
        ->where($where)
        ->addColumn('time', 'time', 'Time', DDPTable::FORMAT_TIME)
        ->addSimpleColumn('servers.hostname', 'MSCM Instance')
        ->addSimpleColumn('IFNULL(\'networkelement\', \'NA\')', 'Network Element')
        ->addSimpleColumn('evnotificationcount', 'Evicted Notifications')
        ->build();

    if ( $table->hasRows() ) {
        echo drawHeader("Daily Totals (Node Eviction)", HEADER_1, "nodeEvictionDetails");
        echo $table->getTable();
    }
}

function getInstrParams() {
    return array(
        array(
            SqlPlotParam::TITLE => RECEIVED,
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array('totalnotificationsreceived' => RECEIVED)
        ),
        array(
            SqlPlotParam::TITLE => PROCESSED,
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array('totalnotificationsprocessed' => PROCESSED)
        ),
        array(
            SqlPlotParam::TITLE => DISCARDED,
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array('totalnotificationsdiscarded' => DISCARDED)
        ),
        array(
            SqlPlotParam::TITLE => 'Evicted',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(EVICTIONS => EVICTIONS)
        ),
        array(
            SqlPlotParam::TITLE => 'Small Node Cache Max',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array('cachesizemax' => 'Cache Max')
        ),
        array(
            SqlPlotParam::TITLE => 'Large Node Cache Max',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array('largeNodeCacheMax' => 'Large Cache Max')
        ),
        array(
            SqlPlotParam::TITLE => LEADTIMEMAX,
            SqlPlotParam::Y_LABEL => 'ms',
            'cols' => array('leadtimemax' => LEADTIMEMAX)
        ),
        array(
            SqlPlotParam::TITLE => 'Lead Time Avg',
            SqlPlotParam::Y_LABEL => 'ms',
            'cols' => array('leadtimeavg' => 'Lead Time Avg')
        ),
        array(
            SqlPlotParam::TITLE => VALIDATIONMAX,
            SqlPlotParam::Y_LABEL => 'ms',
            'cols' => array('validationhandlertimemax' => VALIDATIONMAX)
        ),
        array(
            SqlPlotParam::TITLE => WRITEHANDLERMAX,
            SqlPlotParam::Y_LABEL => 'ms',
            'cols' => array('writehandlertimemax' => WRITEHANDLERMAX)
        ),
        array(
            SqlPlotParam::TITLE => 'Write Handler Avg',
            SqlPlotParam::Y_LABEL => 'ms',
            'cols' => array('writehandlertimeavg' => 'Write Handler Avg')
        )
    );
}

function plotInstrGraphs( $dbTable ) {
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    drawHeader("CPP CM Notification Instrumentation", HEADER_1, "notificationInstrumentationHelp");
    $instrParams = getInstrParams();

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id ";

    foreach ( $instrParams as $instr ) {
        $title = $instr[SqlPlotParam::TITLE];
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('%s')
            ->titleArgs(array('inst'))
            ->type(SqlPlotParam::STACKED_BAR)
            ->barwidth(60)
            ->yLabel($instr[SqlPlotParam::Y_LABEL])
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                $instr['cols'],
                $dbTables,
                $where,
                array( 'site' ),
                'servers.hostname'
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59 ", true, 640, 320, "inst=$title");
    }
    plotGraphs($row);
}

function mainFlow() {

    /* Daily Totals table */
    processingDailyTotalsTable();
    nodeEvictionDetailsTable();

    plotInstrGraphs( ENM_MSCM_NOTIF_LOG );
}

$statsDB = new StatsDB();
mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
