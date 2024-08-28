<?php
$pageTitle = "IP Transport";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/TOR/cm/cm_functions.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const INSTANCE = "IFNULL(servers.hostname,'Totals')";
const SG_INSTANCE = 'MSCMIP Instance';
const LABEL = 'label';
const COUNT = 'count';
const YANG_TBL = 'mscmip_yangcud_instr';
const SELF = '/TOR/cm/ip_transport_stats.php';
const SUP = 'Supervision';
const ACT = 'action';
const SG = 'mscmip';
const MSCMIP_SYNC = 'enm_mscmip_syncs_stats';
const FULL_SYNC_STATS = 'fullsyncstats';
const SUPERVISION = 'supervision';
const SERVERIDS = 'serverids';

function fullSyncParams() {
    return array(
        INSTANCE => SG_INSTANCE,
        'COUNT(*)' => '#Syncs',
        'SEC_TO_TIME(ROUND(AVG(enm_mscmip_syncs_stats.duration/1000)))' => 'Avg Duration',
        'SEC_TO_TIME(ROUND(MAX(enm_mscmip_syncs_stats.duration/1000)))' => 'Max Duration',
        'ROUND(AVG((enm_mscmip_syncs_stats.total_mo_parsed / enm_mscmip_syncs_stats.duration)* 1000))' => 'Avg MOs/sec',
        'SUM(enm_mscmip_syncs_stats.ecim_mo_parsed)' => 'Total MOs Parsed'
    );
}

function getFullSyncTotals() {
    global $statsDB;

    $where = $statsDB->where('enm_mscmip_syncs_stats', 'start');
    $where .= " AND enm_mscmip_syncs_stats.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
          ->name("full_sync_totals")
          ->tables(array(MSCMIP_SYNC, StatsDB::SITES, StatsDB::SERVERS))
          ->where($where);

    foreach ( fullSyncParams() as $db => $lable) {
        $table->addSimpleColumn($db, $lable);
    }

    echo $table->build()->getTableWithHeader("Full Syncs", 2, "", "", "Full_Sync_Totals");
}

function getDailyNotificationGenricRoute($serverIds) {
    global $statsDB, $site, $date;

    $pattern = '//MEDIATION/IposCmNotificationHandlingFlow/%_jms:IposCmNotificationQueue%';
    $hasSummary = $statsDB->hasData("sum_enm_route_instr", "date", true);
    if ( $hasSummary ) {
        $instrTable = 'sum_enm_route_instr';
        $where = <<<EOT
sum_enm_route_instr.siteid = sites.id AND sites.name = '$site' AND
sum_enm_route_instr.date = '$date' AND
sum_enm_route_instr.serverid = servers.id AND sum_enm_route_instr.serverid IN($serverIds) AND
sum_enm_route_instr.routeid = enm_route_names.id AND enm_route_names.name LIKE '$pattern' AND
sum_enm_route_instr.ExchangesCompleted IS NOT NULL
GROUP BY servers.hostname WITH ROLLUP
EOT;
    } else {
        $instrTable = 'enm_route_instr';
        $where = $statsDB->where('enm_route_instr');
        $where .= "
AND enm_route_instr.serverid = servers.id AND
enm_route_instr.routeid = enm_route_names.id AND
enm_route_instr.serverid IN($serverIds) AND
enm_route_names.name LIKE '$pattern' AND
enm_route_instr.ExchangesCompleted IS NOT NULL
GROUP BY servers.hostname WITH ROLLUP";
    }

    $table = SqlTableBuilder::init()
          ->name("Daily_Notification_Totals")
          ->tables(array($instrTable, 'enm_route_names', StatsDB::SITES, StatsDB::SERVERS))
          ->where($where)
          ->addSimpleColumn(INSTANCE, SG_INSTANCE)
          ->addSimpleColumn('SUM(ExchangesCompleted)', 'Total Notifications Handled')
          ->build();

    echo $table->getTableWithHeader("Daily Notification Totals", 2, '');
}

function yangTableParams() {
    return array(
        INSTANCE => SG_INSTANCE,
        'ROUND(AVG(averageOverallYangOperationTimeTaken))' => 'Average Time Taken for Yang CRUD operations',
        'MAX(maxOverallYangOperationTimeTaken)' => 'Maximum Time Taken for Yang CRUD operations',
        'MIN(minOverallYangOperationTimeTaken)' => 'Minimum Time Taken for Yang CRUD operations'
    );
}

function getYangCudStatsTotals() {
    global $statsDB;

    $where = $statsDB->where(YANG_TBL);

    $where .= " AND mscmip_yangcud_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";

    $label = 'Average Time Taken for Yang CRUD operations';
    $tables = array(YANG_TBL, StatsDB::SITES, StatsDB::SERVERS);

    $table = SqlTableBuilder::init()
          ->name("daily_totals_yang")
          ->tables($tables)
          ->where($where);

    foreach ( yangTableParams() as $db => $label ) {
        $table->addSimpleColumn($db, $label);
    }

    echo $table->build()->getTableWithHeader("Daily Totals", 1, "", "", "Daily_Totals_Yang");
}

function ipsmservGraphs() {
    global $date;

    drawHeaderWithHelp("Node Stats", 1, "Node_Stats");

    $dbTables = array("enm_ipsmserv_instr", StatsDB::SITES, StatsDB::SERVERS);
    $where = "enm_ipsmserv_instr.siteid = sites.id AND sites.name = '%s' AND enm_ipsmserv_instr.serverid = servers.id";

    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();

    $sqlParam = SqlPlotParamBuilder::init()
              ->title('Nodes Added')
              ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
              ->barwidth(60)
              ->yLabel("")
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  array('nodecount'  => 'nodecount'),
                  $dbTables,
                  $where,
                  array('site')
    )
    ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);

    plotGraphs($graphs);
}

function supervisionParams() {
    return array(
            'startSupervision' => 'Start Supervision',
            'stoppedSupervision' => 'Stopped Supervision',
            'failedSubscriptionValidations' => 'Failed Subscription Validation',
            'successfullSubscriptionValidations' => 'Successful Subscription Validation'
    );
}

function supervisionGraph() {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    drawHeader("HeartBeat Instrumentation", 2, "HeartBeat_Instrumentation");

    $graphs = array();
    $dbtables = array("mscmip_supervision_instr", StatsDB::SITES, StatsDB::SERVERS);
    $where = "mscmip_supervision_instr.siteid = sites.id AND
              sites.name = '%s' AND
              mscmip_supervision_instr.serverid = servers.id";

    foreach ( supervisionParams() as $db => $label ) {
        $sqlParam = SqlPlotParamBuilder::init()
                      ->title($label)
                      ->type(SqlPlotParam::STACKED_BAR)
                      ->barwidth(60)
                      ->yLabel("")
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array($db),
                          $dbtables,
                          $where,
                          array('site')
                      )
                      ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
    }
    plotGraphs($graphs);
}

function syncGraph() {
    $graphs = array();
    drawHeader('Sync', 1, 'Sync');
    getGraphsFromSet( 'sync', $graphs, 'TOR/cm/ip_transport_stats_sync', null, 640, 320);
    plotGraphs($graphs);
}

function yangcudParams() {
    return array(
            'noOfFailedYangOperations' => 'Failed Yang CRUD operations',
            'numberOfYangOperationsForCreate' => 'Yang Operations For Create',
            'numberOfYangOperationsForDelete' => 'Yang Operations For Delete',
            'numberOfYangOperationsForModify' => 'Yang Operations For Modify',
            'numberOfYangRpcRequests' => 'Yang RPC Request Sent for CRUD operations',
            'overallYangOperationTimeTaken' => 'Time Taken for Yang CRUD operation',
            'yangRpcConstructionTime' => 'Yang RPC Request Construction Time for CRUD operations',
            'yangRpcResponseTime' => 'Yang RPC Request Response Time for CRUD operations'
    );
}

function yangcudGraph() {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    drawHeader("YANG CRUD Instrumentation", 2, "YANG_CRUD_Instrumentation");
    $graphs = array();

    $dbtables = array(YANG_TBL, StatsDB::SITES, StatsDB::SERVERS);
    $where = "mscmip_yangcud_instr.siteid = sites.id AND sites.name = '%s' AND mscmip_yangcud_instr.serverid = servers.id";

    foreach ( yangcudParams() as $db => $label) {
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title($label)
                  ->type(SqlPlotParam::STACKED_BAR)
                  ->barwidth(60)
                  ->yLabel("")
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array($db),
                      $dbtables,
                      $where,
                      array('site')
                  )
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
    }
    plotGraphs($graphs);
}

function getShowSync() {
    global $site, $date;

    $where = "enm_mscmip_syncs_stats.siteid = sites.id AND sites.name = '$site' AND
              enm_mscmip_syncs_stats.start BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              enm_mscmip_syncs_stats.neid = enm_ne.id AND enm_mscmip_syncs_stats.serverid = servers.id";

    $table = SqlTableBuilder::init()
          ->name("full_sync_stats")
          ->tables(array(MSCMIP_SYNC, 'enm_ne', StatsDB::SITES, StatsDB::SERVERS))
          ->where($where)
          ->addSimpleColumn("DATE_FORMAT(enm_mscmip_syncs_stats.start,'%H:%i:%s')", 'Start Time')
          ->addSimpleColumn('enm_ne.name', 'Network Element')
          ->addSimpleColumn('servers.hostname', 'MSCMIP Instance')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ne_type', 'NE Type')
          ->addSimpleColumn('enm_mscmip_syncs_stats.sync_type', 'Sync Type')
          ->addSimpleColumn('enm_mscmip_syncs_stats.total_mo_parsed', 'Num Total MOs Parsed')
          ->addSimpleColumn('enm_mscmip_syncs_stats.duration', 'Total Time(ms)')
          ->addSimpleColumn('(enm_mscmip_syncs_stats.total_mo_parsed/enm_mscmip_syncs_stats.duration)*1000', 'MOs Parsed/sec')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_mo_parsed', 'Num ECIM MOs Parsed')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_t_read_mo_ne', 'Time Read ECIM MOs from NE(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_t_ne_trans_mo', 'Time Transformed ECIM MOs from NE(ms)')
          ->addSimpleColumn('yang_mo_parsed', 'Num YANG MOs Parsed')
          ->addSimpleColumn('yang_t_read_mo_ne', 'Time Read YANG MOs from NE(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.t_read_mo_ne', 'Time Read MOs from NE(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.yang_t_ne_trans_mo', 'Time Transformed YANG MOs from NE(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.total_t_ne_trans_mo', 'Time Total Transformed MOs from NE(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.n_mo_write', 'Num MOs Write')
          ->addSimpleColumn('enm_mscmip_syncs_stats.t_mo_write', 'Time MOs Write(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.t_mo_delta', 'Time MOs Delta Calculation(ms)')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_n_mo_attr_read', 'Num ECIM MOs Attribute Read')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_n_mo_attr_trans', 'Num ECIM MOs Attribute Transformed')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_n_mo_attr_null', 'Num ECIM MOs Attribute Null')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_n_mo_attr_error_trans', 'Num ECIM MOs Attribute Error in Transformation')
          ->addSimpleColumn('enm_mscmip_syncs_stats.ecim_n_mo_error', 'Num ECIM MOs Mo Error')
          ->paginate()
          ->build();
    echo $table->getTableWithHeader("Full Sync Stats", 2, "", "", "Full_Sync_Stats");
}

function getNotifRecTables() {
    global $statsDB, $site, $date;

    if ( !$statsDB->hasData('enm_iptrnsprt_notifrec', 'date', true) ) {
        return NULL;
    }

    return array(
           new SqlTable("Notifications_Received_Details",
                array(
                  array( 'key' => 'eventtype', LABEL => 'Event Type' ),
                  array( 'key' => 'mo', 'db' => 'mo_names.name', LABEL => 'MO' ),
                  array( 'key' => 'attrib', 'db' => 'enm_iptrnsprt_attrib_names.name', LABEL => 'Attribute' ),
                  array( 'key' => COUNT, LABEL => 'Count' )
                  ),
                array( 'enm_iptrnsprt_notifrec', 'mo_names', 'sites', 'enm_iptrnsprt_attrib_names' ),
                "enm_iptrnsprt_notifrec.date = '$date' AND enm_iptrnsprt_notifrec.siteid = sites.id AND sites.name = '$site' AND
                                enm_iptrnsprt_notifrec.moid = mo_names.id AND enm_iptrnsprt_notifrec.attribid = enm_iptrnsprt_attrib_names.id",
                TRUE,
                array( 'order' => array( 'by' => COUNT, 'dir' => 'DESC'),
                       'rowsPerPage' => 25,
                       'rowsPerPageOptions' => array(50, 100, 1000, 10000)
                   )
                ),
           new SqlTable("Top_Notification_Nodes",
                array(
                  array( 'key' => 'name', 'db' => 'enm_ne.name', LABEL => 'Network Element' ),
                  array( 'key' => COUNT, 'db' => 'enm_iptrnsprt_notiftop.count', LABEL => 'Count' ),
                  ),
                array( 'enm_iptrnsprt_notiftop', 'enm_ne', 'sites' ),
 "enm_iptrnsprt_notiftop.date = '$date' AND enm_iptrnsprt_notiftop.siteid = sites.id AND sites.name = '$site' AND
                                enm_iptrnsprt_notiftop.neid = enm_ne.id",
 TRUE,
                array( 'order' => array( 'by' => COUNT, 'dir' => 'DESC'),
                       'rowsPerPage' => 25,
                       'rowsPerPageOptions' => array(50, 100, 1000, 10000),
                   )
                ),
           );
}

function showRouterSupervision($statsDB){

  global $site, $date;

  $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_cm_supervision, sites
WHERE
 enm_cm_supervision.siteid = sites.id AND sites.name = '$site' AND
 enm_cm_supervision.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_cm_supervision.type = 'ROUTER'");

     if ( $row[0] > 0 ) {

        drawHeaderWithHelp(SUP, 1, SUP, '');
        $sqlParam =
                  array(
                      'title' => "Router Supervision",
                      'type' => 'tsc',
                      'ylabel' => "Nodes",
                      'useragg' => 'true',
                      'persistent' => 'true',
                      'querylist' => array(
                          array(
                              'timecol' => 'time',
                              'whatcol' => array( 'supervised' => 'Supervised', 'subscribed' => 'Subscribed', 'synced' => 'Synced' ),
                              'tables' => "enm_cm_supervision, sites",
                              'where' => "enm_cm_supervision.siteid = sites.id AND sites.name = '%s' AND enm_cm_supervision.type = 'ROUTER'",
                              'qargs' => array( 'site' )
                          )
                      )
                  );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
    }

}

function alternateFlow() {
    echo addLineBreak(2);
    echo makeLink(SELF, 'Back to Application-level data for IP Transport');
    echo addLineBreak();
    getYangCudStatsTotals();
    yangcudGraph();
}

function showSubLinks() {
    global $date, $site, $statsDB;
    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, SG);
    $srvIdArr = array_values($processingSrv);
    $srvIdStr = implode(",", $srvIdArr);
    $links = array();
    $sublinks = array();

    $hasData = $statsDB->hasData('enm_cm_supervision', 'time', false, "enm_cm_supervision.type = 'ROUTER'");
    if ( $hasData ) {
        $sublinks[] = makeAnchorLink(SUPERVISION, SUP);
    }
    if ( $statsDB->hasData('mscmip_supervision_instr') ){
        $sublinks[] = makeAnchorLink('heartbeat', 'Heartbeat');
    }

    $hasData = $statsDB->hasData( 'enm_mscmce_instr', 'time', false, "enm_mscmce_instr.serverid IN ($srvIdStr)");
    if ( $hasData ) {
        $sublinks[] = makeAnchorLink('syncgraphs', 'Sync');
        $sublinks[] = makeAnchorLink('software_sync', 'Software Sync Invocations');
        $sublinks[] = makeAnchorLink('netconfsubscriptions', 'NetConf subscriptions');
        $sublinks[] = makeAnchorLink('netconfwrite', 'NetConf Write');
        $sublinks[] = makeAnchorLink('netconf_non_persistence_reads', 'NetConf Non-Persistence Reads');
        $sublinks[] = makeAnchorLink('netconfsession', 'NetConf Session');
    }

    $subTitle =  makeAnchorLink("instrumentation", "Instrumentation");
    $links[] = $subTitle. makeHTMLList($sublinks);

    echo makeHTMLList($links);
}

function getFullSyncStats() {
    global $date, $site, $statsDB;

    showSubLinks();

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, SG);
    $srvIdArr = array_values($processingSrv);
    $srvIdStr = implode(",", $srvIdArr);
    $table = new ModelledTable( "TOR/cm/enm_ecim_syncs", FULL_SYNC_STATS, array(SERVERIDS => $srvIdStr));
    echo $table->getTableWithHeader("Successful Sync Stats");
    echo addLineBreak();

    $table = new ModelledTable( "TOR/cm/delta_sync", 'delta_sync_stats', array(SERVERIDS => $srvIdStr));
    echo $table->getTableWithHeader("Delta Sync Stats");
    echo addLineBreak();

    drawHeader('Instrumentation', 1, 'instrumentation');

    $hasData = $statsDB->hasData('enm_cm_supervision', 'time', false, "enm_cm_supervision.type = 'ROUTER'");
    if ($hasData) {
        $supervisionparams = supervisionGraphParams();
        makeGraphs($supervisionparams);
    }

    if ( $statsDB->hasData('mscmip_supervision_instr') ) {
        $heartbeatparams = heartBeatGraphParams();
        makeGraphs($heartbeatparams);
    }

    $sId = array(SERVERIDS => $srvIdStr);

    $hasData = $statsDB->hasData( 'enm_mscmce_instr', 'time', false, "enm_mscmce_instr.serverid IN ($srvIdStr)");
    if ( $hasData ) {
        $sections = array (
            array( 'Sync', 'syncgraphs', 'sync' ),
            array( 'Software Sync Invocations', 'software_sync', 'softSyncInv' ),
            array( 'NetConf Subscriptions', 'netconfsubscriptions', 'netConfSub' ),
            array( 'NetConf Write', 'netconfwrite', 'netConfWrite' ),
            array( 'NetConf Non-Persistence Reads', 'netconf_non_persistence_reads', 'netConfPerReads' ),
            array( 'NetConf Session', 'netconfsession', 'netConfSes' )
        );

        foreach ( $sections as $section ) {
            $graphs = array();
            $params = array('serverids' => $srvIdStr);
            getGraphsFromSet( $section[2], $graphs, 'TOR/cm/mscmce_instr', $params, 640, 320 );
            drawHeaderWithHelp($section[0], 2, $section[1]);
            plotGraphs( $graphs );
        }
    }
}

function heartBeatGraphParams() {
    $heartBeatGraphs = array(
         'startSupervision',
         'stoppedSupervision',
         'failedSubscriptionValidations',
         'successfullSubscriptionValidations'
    );

    return array(
        array( 'Heartbeat', $heartBeatGraphs, 'heartbeat' )
    );
}

function supervisionGraphParams() {
    $supervision = array( SUPERVISION );
    return array(
        array( SUP, $supervision, SUPERVISION )
    );
}

function makeGraphs($params) {
    global $statsDB;
    foreach ( $params as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 1, $help);
        $graphParams = $param[1];
        $types = array( 'types' => 'ROUTER');
        foreach ( $graphParams as $graphParam ) {
            $modelledGraph = new ModelledGraph( 'TOR/cm/' . $graphParam );
            if ( $graphParam = 'SUPERVISION' ) {
                $graphs[] = $modelledGraph->getImage($types);
            } else {
                $graphs[] = $modelledGraph->getImage();
            }
        }
    }
    plotgraphs( $graphs );
}

function showLinks( $countNotifRec ) {
    global $statsDB;

    $links = array();

    if ( $statsDB->hasData('mscmip_sync_instr') ) {
        $links[] = makeAnchorLink('Sync_anchor', 'Sync');
    }

    $hasDataSup = $statsDB->hasData('enm_cm_supervision', 'time', false, "enm_cm_supervision.type = 'ROUTER'");
    if ( $statsDB->hasData('mscmip_supervision_instr') || $hasDataSup ) {
        $links[] = makeAnchorLink('Supervision_anchor', SUP);
    }

    if ( $statsDB->hasData(YANG_TBL) ) {
        $links[] = makeLink(SELF, 'YANG CRUD Operations', array(ACT => 'Yangcud'));
    }

    $links[] = makeFullGenJmxLink('servicemanagement', 'Generic JMX for IPSMSERV');
    $links[] = makeFullGenJmxLink(SG, 'Generic JMX for MSCMIP');

    $srvs = makeSrvList(SG);

    $links[] = makeLink( '/TOR/dps.php', 'MSCMIP DPS Instrumentation', array('servers' => $srvs) );
    $links[] = queueLink();
    $links[] = routesLink();

    $hoverOverText = 'Click here to view Successful Sync Stats.';
    $links[] = makeLink(SELF, 'Successful Sync Stats', array('showsyncs' => '1'), $hoverOverText);

    $links[] = makeLink('/TOR/cm/mscmip_notif_analysis.php', 'Notification Analysis');
    $links[] = makeLink(SELF, 'Transport-COM-ECIM-Successful full sync Stats', array(FULL_SYNC_STATS => '2'));

    if ( $countNotifRec > 0 ) {
        $links[] = makeAnchorLink('notifrec', 'Notifications Received Details');
        $links[] = makeAnchorLink('notiftop', 'Top Notification Nodes');
    }

    echo makeHTMLList($links);
}

function mainFlow() {
    global $date, $site, $statsDB;

    getFullSyncTotals();

    $notifRecTables = getNotifRecTables();
    $countNotifRec = 0;
    if (is_array($notifRecTables)) {
        $countNotifRec = count($notifRecTables);
    }

    if ( requestValue(ACT) === 'Yangcud' ) {
        alternateFlow();
    } else {
        $processingSrv = enmGetServiceInstances($statsDB, $site, $date, SG);
        $srvIdArr = array_values($processingSrv);
        $srvIdStr = implode(",", $srvIdArr);

        if ( $srvIdStr ) {
            getDailyNotificationGenricRoute($srvIdStr);
        }
        showLinks( $countNotifRec );

        showSyncStatusEvents( SG );

        ipsmservGraphs();
        syncGraph();
        showRouterSupervision($statsDB);
        supervisionGraph();

        $queueNames = array('CmipNotificationConsumer_0', 'ClusteredMediationServiceConsumerCMIP0');
        plotQueues( $queueNames );

        if ( $srvIdArr ) {
            getRouteInstrTable( $srvIdArr );
        }
    }

    if ( $countNotifRec > 0 ) {
        echo "<H2 id=\"notifrec\"></H2>\n";
        echo $notifRecTables[0]->getTableWithHeader("Notifications Received Details", 1, "","");
        echo "<H2 id=\"notiftop\"></H2>\n";
        echo $notifRecTables[1]->getTableWithHeader("Top Notification Nodes", 1, "","");
    }
}

if (isset($_GET['showsyncs'])) {
    getShowSync();
} elseif ( issetURLParam(FULL_SYNC_STATS) ) {
    getFullSyncStats();
} elseif ( issetURLParam('shownodeindex') ) {
    showNodeIndex( SG );
} elseif ( issetUrlParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    } else {
        mainFlow();
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

