<?php

$sg = $_REQUEST['servicegroup']; //NOSONAR
$pageTitle = "$sg Engine Stats";

include_once "../../common/init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const DB_TABLE = 'enm_mssnmpfm_instr';
const OPERATION_NODE = 'enm_fmsnmp_operationonnode';
const LOSS_OF_TRAPEVENT = 'enm_fmsnmp_lossoftrapevent';
const NODE_STATUS = 'enm_fmsnmp_nodestatus';
const SUPERVISION_STATUS = 'enm_fmsnmp_supervisionstatus';
const SYNC_STATUS = 'enm_fmsnmp_syncstatus';
const HEARTBEAT = 'enm_fmsnmp_heartbeat';
const SELF_PAGE = '/TOR/fm/fm_sdk.php';
const MEDIATION = 'fmSnmpMediation';
const FMGRAPHS = 'TOR/fm/fmsnmp_engine_stats';
const ACT = 'action';

function showLinks() {
    global $sg, $statsDB;
    $links = array();
    $links[] = makeFullGenJmxLink($sg, "Generic JMX");
    $links[] = queueLink();
    $links[] = makeAnchorLink("$sg anchor", "$sg Engine Stats Instrumentation");
    $links[] = routesLink();
    $others = array('servicegroup' => $sg, 'flow' => 'LowLevelCounters');
    $links[] = makeLink(SELF_PAGE, "$sg Low Level Counters", $others, 'Here');

    $nodeStatuslink = array(MEDIATION => '1', 'snmpNodeStatusTable' => '1', 'servicegroup' => $sg);
    $supervisionlink = array(MEDIATION => '1', 'snmpSupervisionStatusTable' => '1', 'servicegroup' => $sg);
    $syncstatuslink = array(MEDIATION => '1', 'snmpSyncStatusTable' => '1', 'servicegroup' => $sg);
    $operationlink = array(MEDIATION => '1', 'listOperationNodeTable' => '1', 'servicegroup' => $sg);
    $heartbeatlink = array(MEDIATION => '1', 'snmpHeartBreakOnNodeTable' => '1', 'servicegroup' => $sg);
    $lossoftraplink = array(MEDIATION => '1', 'snmpLossOfTrapEventTable' => '1', 'servicegroup' => $sg);

    if ( $statsDB->hasData( NODE_STATUS ) ) {
        $fmSdkLink[] = makeLink(SELF_PAGE, "$sg Node Status Change", $nodeStatuslink);
    }
    if ( $statsDB->hasData( SUPERVISION_STATUS ) ) {
        $fmSdkLink[] = makeLink(SELF_PAGE, "$sg Supervision Status Change", $supervisionlink);
    }
    if ( $statsDB->hasData( SYNC_STATUS ) ) {
        $fmSdkLink[] = makeLink(SELF_PAGE, "$sg Sync Status Change", $syncstatuslink);
    }
    if ( $statsDB->hasData( OPERATION_NODE ) ) {
        $fmSdkLink[] = makeLink(SELF_PAGE, "$sg Operation On Node", $operationlink);
    }
    if ( $statsDB->hasData( HEARTBEAT ) ) {
        $fmSdkLink[] = makeLink(SELF_PAGE, "$sg Heartbeat Operation On Node", $heartbeatlink);
    }
    if ( $statsDB->hasData( LOSS_OF_TRAPEVENT ) ) {
        $fmSdkLink[] = makeLink(SELF_PAGE, "$sg Loss Of Trap Event", $lossoftraplink);
    }
    echo makeHTMLList($links);

    if ( isset($fmSdkLink) ) {
        $eventLinks = array ("<b>Events</b>" . makeHTMLList($fmSdkLink));
        echo makeHTMLList($eventLinks);
    }
}

function showLowLevelCounters() {
    global $sg, $srvIdStr;

    $params = array( 'srvIds' => $srvIdStr );
    drawHeader("$sg Low Level Counters", 2, 'lowLevelCounters');
    getGraphsFromSet( lowLevelCounters, $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
}

function fmAlarms() {
    global $sg, $srvIdStr, $selfLink;

    $tblParams = array( 'serverIds' => $srvIdStr, ModelledTable::URL => $selfLink );

    drawHeader( "$sg Alarms Totals", 2, 'fmsdkalarmstotals' );
    $table = new ModelledTable( "TOR/fm/fm_alarms_total", 'fmsdkalarmstotals', $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function fmTraps() {
    global $sg, $srvIdStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdStr,  ModelledTable::URL => $selfLink );
    drawHeader("$sg Traps Totals", 1, 'fmsdkTrapTotalsHelp');
    $table = new ModelledTable( "TOR/fm/fm_traps_total", 'fmsdkTrapTotalsHelp', $tblParams );
    echo $table->getTable("FM SNMP Traps Totals");
    echo addLineBreak();
}

function fmGraphs() {
    global $sg, $srvIdStr;

    $params = array('srvIds' => $srvIdStr);
    drawHeader("$sg Stats Instrumentation", 1, "$sg anchor");
    $graphs = array();
    drawHeader('Nodes Operation', 1, 'nodesOperation');
    getGraphsFromSet( 'nodesOperation', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Overall', 1, 'overall');
    getGraphsFromSet( 'overall', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Received', 1, 'received');
    getGraphsFromSet( 'received', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Forwarded', 1, 'forwarded');
    getGraphsFromSet( 'forwarded', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Alarms Processed', 1, 'alarmsProc');
    getGraphsFromSet( 'alarmsProc', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Mutli Event Processed', 1, 'multiEventProc');
    getGraphsFromSet( 'multiEventProc', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Alarm Processing Failures', 1, 'alarmProcFail');
    getGraphsFromSet( 'alarmProcFail', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('Trap Issues', 1, 'trapIssue');
    getGraphsFromSet( 'trapIssue', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

    $graphs = array();
    drawHeader('MiniLink MTR Status', 1, 'destination');
    getGraphsFromSet( 'destination', $graphs, FMGRAPHS, $params );
    plotGraphs( $graphs );
    echo addLineBreak();

}

function snmpNodeStatusChange() {
    global $srvIdsStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdsStr,  ModelledTable::URL => $selfLink );
    drawHeader("FM SNMP Node Status Change", 2, NODE_STATUS);
    $table = new ModelledTable( "TOR/fm/fmsnmp_nodestatus", NODE_STATUS, $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function snmpSupervisionStatus() {
    global $srvIdsStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdsStr,  ModelledTable::URL => $selfLink );
    drawHeader("FM SNMP Supervision Status Change", 2, SUPERVISION_STATUS);
    $table = new ModelledTable( "TOR/fm/fmsnmp_supervisionstatus", SUPERVISION_STATUS, $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function snmpSyncStatus() {
    global $srvIdsStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdsStr,  ModelledTable::URL => $selfLink );
    drawHeader("FM SNMP Sync Status Change", 2, SYNC_STATUS);
    $table = new ModelledTable( "TOR/fm/fmsnmp_syncstatus", SYNC_STATUS, $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function listOperationNode() {
    global $srvIdsStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdsStr,  ModelledTable::URL => $selfLink );
    drawHeader("FM SNMP Operation On Node", 2, OPERATION_NODE);
    $table = new ModelledTable( "TOR/fm/fmsnmp_operationonnode", OPERATION_NODE, $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function snmpHeartbreakOnNode() {
    global $srvIdsStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdsStr,  ModelledTable::URL => $selfLink );
    drawHeader("FM SNMP Heartbeat Operation On Node", 2, HEARTBEAT);
    $table = new ModelledTable( "TOR/fm/fmsnmp_heartbeat", HEARTBEAT, $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function snmpLossOfTrapEvent() {
    global $srvIdsStr, $selfLink;

    $tblParams = array('serverIds' => $srvIdsStr,  ModelledTable::URL => $selfLink );
    drawHeader("FM SNMP Loss Of Trap Event", 2, LOSS_OF_TRAPEVENT);
    $table = new ModelledTable( "TOR/fm/fmsnmp_lossoftrapevent", LOSS_OF_TRAPEVENT, $tblParams );
    echo $table->getTable();
    echo addLineBreak();
}

function fmSnmpMediation() {
   global $srvIdsStr;
   if ( issetURLParam('snmpNodeStatusTable') ) {
       snmpNodeStatusChange( $srvIdsStr );
   } elseif ( issetURLParam('snmpSupervisionStatusTable') ) {
       snmpSupervisionStatus( $srvIdsStr );
   } elseif ( issetURLParam('snmpSyncStatusTable') ) {
       snmpSyncStatus( $srvIdsStr );
   } elseif ( issetURLParam('listOperationNodeTable') ) {
       listOperationNode( $srvIdsStr );
   } elseif ( issetURLParam('snmpHeartBreakOnNodeTable') ) {
       snmpHeartbreakOnNode( $srvIdsStr );
   } elseif ( issetURLParam('snmpLossOfTrapEventTable') ) {
       snmpLossOfTrapEvent( $srvIdsStr );
   }
}

function mainFlow() {
    global $sg, $srvIdStr, $srvIdArr;

    showLinks();
    fmAlarms();
    fmTraps();

    $neNotif = array(
        'NetworkElementFmNotifications_0',
        'NetworkElementFmNotifications_1',
        'NetworkElementFmNotifications_2',
        'NetworkElementFmNotifications_3',
        'NetworkElementSNMPFmNotifications_0'
    );

    $queueNames = array(
        'Mediation' => 'ClusteredMediationServiceConsumerFMSNMP0',
        'Network Element Notifications' => implode( $neNotif, "," )
    );

    plotQueues( $queueNames, true );

    fmGraphs();
    getRouteInstrTable( $srvIdArr );

}

$processingSrv = enmGetServiceInstances($statsDB, $site, $date, $sg);
$srvIdArr = array_values($processingSrv);
$srvIdStr = implode(",", $srvIdArr);
$selfLink = makeSelfLink();

if ( requestValue('flow') == 'LowLevelCounters' ) {
    if ( $statsDB->hasData( DB_TABLE ) ) {
        showLowLevelCounters();
    } else {
        echo "No data available for $date";
    }
} elseif (issetURLParam(MEDIATION)) {
    fmSnmpMediation();
} elseif ( issetUrlParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
