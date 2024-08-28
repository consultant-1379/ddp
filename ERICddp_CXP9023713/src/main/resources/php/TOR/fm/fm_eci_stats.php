<?php
$pageTitle = "FM SNMP OSS MS Alarms";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const ACT = 'action';
$TABLE_FM_ECI_INSTR = "enm_fm_eci_instr";
const ALARMS = 'Alarms';
const SYNC = 'Sync';
const SERVICE_GRP = 'msosssnmpfm';
const MSOSSSNMP = '/TOR/fm/fm_eci_stats.php';

function showRouteInstr($statsDB, $srv) {
    global $date, $site;

    $srvIds = array_values($srv);
    $routes = new Routes($statsDB, $site, $date, $srvIds);
    return $routes->getGraphs();
}

function showRouteInstrGraphs($graphArray) {
    ksort($graphArray);
    drawHeaderWithHelp("Route Instrumentation", 2, "routeInstr");
    $routesList = array();
    foreach ( $graphArray as $routeName => $routeGraphs ) {
        $routesList[] = makeAnchorLink( $routeName, $routeName );
    }
    echo makeHTMLList( $routesList );
    foreach ( $graphArray as $routeName => $routeGraphs ) {
        echo "<H3 id=\"$routeName\">$routeName</H3>\n";
        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow( $routeGraphs );
        echo $graphTable->toHTML();
    }
}

function params($type) {
    if ($type === ALARMS) {
        return array(
                    'alarmsReceived' => 'Alarm Received',
                    'alarmProcessingSuccess' => 'Alarm Processing Success',
                    'alarmProcessingPing' =>  'Alarm Processing Ping',
                    'alarmsProcessingFailures' => 'Alarm Processing Failures',
                    'alarmProcessingLossOfTrap' => 'Alarm Processing LossOfTrap',
                    'alarmProcessingDiscarded' =>  'Alarm Processing Discarded',
                    'alarmProcessingInvalidRecordType' => 'Alarm Processing Invalid Record Type',
                    'alarmsProcessingNotSupported' => 'Alarms Processing Not Supported',
                    'alarmsForwarded' => 'Alarms Forwarded',
                    'forwardedProcessedAlarmFailures' => 'Forwarded Processed Alarm Failures'
                );
    }
    if ($type === SYNC) {
        return array(
                    'syncAlarmCommand' => 'Alarm Synchronizations'
                );
    }
}

function drawTable($params, $statsDB, $title, $type) {
    global $TABLE_FM_ECI_INSTR;
    $where =  $statsDB->where($TABLE_FM_ECI_INSTR) .
              "AND $TABLE_FM_ECI_INSTR.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $queryTable = SqlTableBuilder::init()
           ->name($TABLE_FM_ECI_INSTR.$type)
           ->tables(array($TABLE_FM_ECI_INSTR, StatsDB::SITES, StatsDB::SERVERS))
           ->where($where)
           ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    foreach ($params as $key => $value) {
        $queryTable->addSimpleColumn("SUM($key)", $value);
    }
    echo $queryTable->build()->getTableWithHeader("$title", 1, "", "", "$type");
}

function instrGraphs($serverIds) {
    drawHeaderWithHelp("MSOSSSNMPFM Engine Stats Instrumentation", 2, "MSOSSSNMPFM_Engine_Stats");

    showGraphs(
        'Management Systems Operation',
        $serverIds,
        array(
            array('Management System Supervised' => 'numOfSupervisedNodes',
                  'HB Failure Management System' => 'numOfHBFailureNodes')
        ),
        2,
        'msossSnmpFm_Management_Systems_Operation'
    );
    showGraphs(
        'Alarms',
        $serverIds,
        array(
            array('Forwarded' => 'alarmsForwarded',
                  'Received' => 'alarmsReceived')
        ),
        2,
        'msossSnmpFm_Alarms'
    );
    showGraphs(
        'Alarms Processed',
        $serverIds,
        array(
            array('Success' => 'alarmProcessingSuccess', 'Ping' => 'alarmProcessingPing'),
        ),
        2,
        'msossSnmpFm_Alarms_Processed'
    );
    showGraphs(
        'Alarm Processing Failures',
        $serverIds,
        array(
            array('Processing' => 'alarmsProcessingFailures',
                  'Loss Of Trap' => 'alarmProcessingLossOfTrap',
                  'Discarded' => 'alarmProcessingDiscarded'),
            array('Invalid Record Type' => 'alarmProcessingInvalidRecordType',
                  'Not Supported' => 'alarmsProcessingNotSupported',
                  'Forwarded Processed' => 'forwardedProcessedAlarmFailures')
        ),
        3,
        'msossSnmpFm_Alarm_Processing_Failures'
    );
    showGraphs(
        'Overall',
        $serverIds,
        array(
            array('Alarm Synchronizations' => 'syncAlarmCommand',
                  'Processing Time(msec)' => 'processingAlarmTime')
        ),
        2,
        'msossSnmpFm_Overall'
    );
}

function showGraphs($sectionTitle, $serverIds, $graphParams, $colCount, $helpBubbleName) {
    global $date, $TABLE_FM_ECI_INSTR;

    if ( $colCount == 2 ) {
        $width = 600;
    } elseif ( $colCount == 3 ) {
        $width = 400;
    } else {
        $width = 300;
    }
    drawHeaderWithHelp($sectionTitle, 2, $helpBubbleName);

    $sqlParamWriter = new SqlPlotParam();

    $graphTable = new HTML_Table("border=0");
    $where = "$TABLE_FM_ECI_INSTR.siteid = sites.id AND sites.name = '%s' AND
             $TABLE_FM_ECI_INSTR.serverid IN(%s) AND $TABLE_FM_ECI_INSTR.serverid = servers.id";

    $dbTables = array( $TABLE_FM_ECI_INSTR, StatsDB::SITES, StatsDB::SERVERS );

    foreach ( $graphParams as $graphRow ) {
        $row = array();
        foreach ( $graphRow as $title => $column ) {
            if ( $column == "processingAlarmTime" ) {
                $ylabel = "ms";
            } else {
                $ylabel = "Count";
            }
            $sqlParam = SqlPlotParamBuilder::init()
                      ->title($title)
                      ->type(SqlPlotParam::STACKED_BAR)
                      ->yLabel($ylabel)
                      ->forceLegend()
                      ->makePersistent()
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array( $column => $title ),
                          $dbTables,
                          $where,
                          array('site','serverid'),
                          SqlPlotParam::SERVERS_HOSTNAME
                      )
                      ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $row[] = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                $width,
                320,
                "serverid=$serverIds"
            );
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    global $date, $site, $statsDB;

    $params = params(ALARMS);
    drawTable($params, $statsDB, 'FM SNMP OSS MS Alarms Totals', ALARMS);

    $params = params(SYNC);
    drawTable($params, $statsDB, 'FM SNMP OSS MS Sync Alarms Totals', SYNC);

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, SERVICE_GRP);
    $srvIdArr = array_values($processingSrv);
    $srvIdStr = implode(",", $srvIdArr);

    $jmxLinks = array(
        "<a href=\"" . makeGenJmxLink(SERVICE_GRP) . "\">MSOSSSNMPFM</a>"
    );

    $links = array(
        "Generic JMX" . makeHTMLList($jmxLinks)
    );
    echo makeHTMLList($links);
    $queueslink = makeAnchorLink('queues_anchor', "Queues" );
    $instrlink = makeAnchorLink('MSOSSSNMPFM_Engine_Stats_anchor', "MSOSSSNMPFM Engine Stats Instrumentation" );
    $routeslink = routesLink();
    echo makeHTMLList(array($queueslink, $instrlink, $routeslink));


    $queueNames = array(
        'Mediation' => 'ClusteredMediationServiceConsumer_MSOSSNMPFM',
        'Management System Notifications' =>
            implode(
                array(
                    'EciNetworkElementNotifications',
                    'EciHeartbeatNotifications'
                ),
                ","
            )
    );
    plotQueues( $queueNames, true );

    getRouteInstrTable( $srvIdArr );
    instrGraphs($srvIdStr);
}

if ( issetUrlParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

