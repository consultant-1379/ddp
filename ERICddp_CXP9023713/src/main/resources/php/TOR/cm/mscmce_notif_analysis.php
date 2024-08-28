<?php
$pageTitle = "Notification Analysis";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once 'HTML/Table.php';

const RECEIVED = 'Received';
const PROCESSED = 'Processed';
const DISCARDED = 'Discarded';
const VALIDATION_HANDLER_MAX = 'Validation Handler Max';
const WRITE_HANDLER_MAX = 'Write Handler Max';
const LEAD_TIME_MAX = 'Lead Time Max';
const TITLE = 'title';
const INST = 'COMECIM_Instance';

function processingDailyTotalsTable($serverIds) {
    global $site, $date;
    $where = "enm_mscmcenotification_logs.siteid = sites.id AND
            sites.name = '$site' AND
            enm_mscmcenotification_logs.serverid = servers.id AND
            enm_mscmcenotification_logs.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            enm_mscmcenotification_logs.serverid IN($serverIds)
            GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
           ->name("dt")
           ->tables(array('enm_mscmcenotification_logs', StatsDB::SITES, StatsDB::SERVERS))
           ->where($where)
           ->addSimpleColumn("IFNULL(servers.hostname, 'Totals')", INST)
           ->addSimpleColumn('SUM(totalnotificationsreceived)', RECEIVED)
           ->addSimpleColumn('SUM(totalnotificationsprocessed)', PROCESSED)
           ->addSimpleColumn('SUM(totalnotificationsdiscarded)', DISCARDED)
           ->addSimpleColumn('MAX(writehandlertimemax)', LEAD_TIME_MAX)
           ->addSimpleColumn('MAX(validationhandlertimemax)', VALIDATION_HANDLER_MAX)
           ->addSimpleColumn('MAX(writehandlertimemax)', WRITE_HANDLER_MAX)
           ->build();
    drawHeader('Daily Totals', 2, 'dt');
    echo $table->getTable();
}

function getInstrParams() {
    return array(
        array(
            'totalnotificationsreceived' => array(
                TITLE => RECEIVED,
                'type' => 'sb',
                'cols' => array('totalnotificationsreceived' => RECEIVED)
            ),
            'totalnotificationsprocessed' => array(
                TITLE => PROCESSED,
                'type' => 'sb',
                'cols' => array('totalnotificationsprocessed' => PROCESSED)
            )
        ),
        array(
            'totalnotificationsdiscarded' => array(
                TITLE => DISCARDED,
                'type' => 'sb',
                'cols' => array('totalnotificationsdiscarded' => DISCARDED)
            ),
                'leadtimemax' => array(
                TITLE => LEAD_TIME_MAX,
                'type' => 'sb',
                'cols' => array('leadtimemax' => LEAD_TIME_MAX)
            )
        ),
        array(
            'validationhandlertimemax' => array(
                TITLE => VALIDATION_HANDLER_MAX,
                'type' => 'sb',
                'cols' => array('validationhandlertimemax' => VALIDATION_HANDLER_MAX)
            ),
            'writehandlertimemax' => array(
                TITLE => WRITE_HANDLER_MAX,
                'type' => 'sb',
                'cols' => array('writehandlertimemax' => WRITE_HANDLER_MAX)
            )
        )
    );

}

function plotInstrGraphs($instrParams, $serverIds) {
    global $date, $site;

    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $where = "enm_mscmcenotification_logs.siteid = sites.id
              AND sites.name = '%s'
              AND enm_mscmcenotification_logs.serverid = servers.id
              AND enm_mscmcenotification_logs.serverid IN($serverIds)";
    $dbTables = array( "enm_mscmcenotification_logs", StatsDB::SITES, StatsDB::SERVERS );

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = SqlPlotParamBuilder::init()
                ->title($instrGraphParamName[TITLE])
                ->type($instrGraphParamName['type'])
                ->barwidth(60)
                ->yLabel('Count')
                ->forceLegend()
                ->addQuery(
                    'endtime',
                    $instrGraphParamName['cols'],
                    $dbTables,
                    $where,
                    array('site'),
                    "servers.hostname"
                    )
                ->build();
           $id = $sqlParamWriter->saveParams($sqlParam);
           $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        }

        $graphTable->addRow($row);
    }

    echo $graphTable->toHTML();
}

function mainFlow() {
    global $date, $site, $statsDB;
    $serviceGroup = requestValue('servicegroup');
    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, $serviceGroup);
    $serverIds = implode(",", array_values($processingSrv));
    if ( $statsDB->hasData('enm_mscmce_notification') ) {
        $table = new ModelledTable( "TOR/cm/enm_mscmce_notification", 'mscmceNotification' );
        echo $table->getTableWithHeader("Daily Totals");
        echo addLineBreak();
        $graphs = array();
        drawHeader( "Notification Instrumentation", 1, 'mscmceNotification' );
        getGraphsFromSet( 'notification', $graphs, 'TOR/cm/enm_mscmce_notification', null, 640, 320 );
        plotGraphs($graphs);
    } else {
        processingDailyTotalsTable($serverIds);
        echo addLineBreak();
        drawHeader( $serviceGroup . " Notification Instrumentation", 1, "Notification_Instrumentation_Help" );
        $instrGraphParams = getInstrParams();
        plotInstrGraphs($instrGraphParams, $serverIds);
    }
    if ( $statsDB->hasData('enm_mscmce_instr') ) {
        $graphs = array();
        drawHeader( "Yang Library Notification Instrumentation", 1, 'mscmceYangNotification' );
        getGraphsFromSet( 'notification', $graphs, 'TOR/cm/enm_mscmce_yang_notification', null, 640, 320 );
        plotGraphs($graphs);
    }
}


mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

