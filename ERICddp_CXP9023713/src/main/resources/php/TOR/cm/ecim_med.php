<?php

const SERVGROUP_ECIM = "comecimmscm";
const SERVGROUP_APG = "mscmapg";
const SERVICE_GROUP = 'servicegroup';
const NOTIF_ACT = 'notifAct';

if (isset($_REQUEST[SERVICE_GROUP]) && $_REQUEST[SERVICE_GROUP] === SERVGROUP_ECIM) { //NOSONAR
   $pageTitle='COM/ECIM Mediation';
} elseif (isset($_REQUEST[SERVICE_GROUP]) && $_REQUEST[SERVICE_GROUP] === SERVGROUP_APG) { //NOSONAR
   $pageTitle='COM/APG Mediation';
}

$DISABLE_UI_PARAMS = array( 'getdata', NOTIF_ACT );

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/TOR/cm/cm_functions.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const ACTION_PARAM = 'action';
const TOTAL_MO_CREATED = 'Total MOs Created';
const TOTAL_MO_UPDATED = 'Total MOs Updated';
const TOTAL_MO_DELETED = 'Total MOs Deleted';
const S_TIME = 'Start Time';
const NE = 'Network Element';
const DURATION = 'Duration';
const CNT = 'count';
const SERVERS_HOSTNAME = 'servers.hostname';
const HOSTNAME = "IFNULL(servers.hostname,'Totals')";
const ENM_NE_TABLE = 'enm_ne';
const ENM_NE_NAME = 'enm_ne.name';
const SYNCS = '#Syncs';
const FULL_SYNC_TABLE = 'enm_ecim_syncs';
const FILTER_ACTION_PARAM = 'filteraction';
const ROUTE_INSTR_TABLE = 'enm_route_instr';
const SUM_ROUTE_INSTR_TABLE = 'sum_enm_route_instr';
const IPOS_NOTIF_TABLE = "enm_ipos_notification";
const COMECIM_NOTIF_TABLE = "enm_comecim_notification";
const PHP_PAGE = 'PHP_SELF';
const PARAMS = 'params';
const TABLE_NAME = 'TABLE_NAME';
const TITLE = 'title';
const TIME_TO_SENT_NOTIF = 'Time Taken To Send Notifications to CM Events NBI';
const NUM_NOTIF_SENT = 'Num Of Notifications sent to CM Event NBI';
const SSI = 'Software Sync Invocations';
const PAGE = '/TOR/cm/ecim_med.php';
const QUEUES = 'Queues';
const HEARTBEAT = 'Heartbeat';
const SHOWREASONS = 'showreasons';
const SERVERS = 'servers';
const SELECTED = 'selected';
const MSCMCE_TAB = 'enm_mscmce_instr';
const DELTA_TABLE = 'enm_com_ecim_delta_syncs';
const SUPERVISION = 'supervision';
const SERVERIDS = 'serverids';
const MSCMCE_INSTR = 'TOR/cm/mscmce_instr';
const ECIM_NOTIF_SUPER_INSTR = 'TOR/cm/ecim_notif_super_instr';

function iposUsageParams() {
    $tableParams = array( 'useIpos_1' => 'IPOS Nodes on channel 1' );
    return array(
        TITLE => 'IPOS Notification Allocation',
        TABLE_NAME => "IPOS_USAGE",
        PARAMS => $tableParams
    );
}

function iposRateParams() {
    $tableParams = array( 'rateIpos_1' => 'IPOS Notification Rate for channel 1' );
    return array(
        TITLE => 'IPOS Notification Rate',
        TABLE_NAME => "IPOS_RATE",
        PARAMS => $tableParams
    );
}

function comEcimUsageParams() {
    $tableParams = array(
        'useCom_ecim_1' => 'COM_ECIM Nodes on channel 1',
        'useCom_ecim_2' => 'COM_ECIM Nodes on channel 2',
        'useCom_ecim_3' => 'COM_ECIM Nodes on channel 3',
        'useCom_ecim_4' => 'COM_ECIM Nodes on channel 4',
        'useCom_ecim_5' => 'COM_ECIM Nodes on channel 5',
        'useCom_ecim_6' => 'COM_ECIM Nodes on channel 6',
        'useCom_ecim_7' => 'COM_ECIM Nodes on channel 7',
        'useCom_ecim_8' => 'COM_ECIM Nodes on channel 8',
        'useCom_ecim_9' => 'COM_ECIM Nodes on channel 9',
        'useCom_ecim_10' => 'COM_ECIM Nodes on channel 10'
    );

    return array(
        TITLE => 'COM/ECIM Notification Allocation',
        TABLE_NAME => "COMECIM_USAGE",
        PARAMS => $tableParams
    );
}

function comEcimRateParams() {
    $tableParams = array(
        'rateCom_ecim_1' => 'COM_ECIM Notification Rate for channel 1',
        'rateCom_ecim_2' => 'COM_ECIM Notification Rate for channel 2',
        'rateCom_ecim_3' => 'COM_ECIM Notification Rate for channel 3',
        'rateCom_ecim_4' => 'COM_ECIM Notification Rate for channel 4',
        'rateCom_ecim_5' => 'COM_ECIM Notification Rate for channel 5',
        'rateCom_ecim_6' => 'COM_ECIM Notification Rate for channel 6',
        'rateCom_ecim_7' => 'COM_ECIM Notification Rate for channel 7',
        'rateCom_ecim_8' => 'COM_ECIM Notification Rate for channel 8',
        'rateCom_ecim_9' => 'COM_ECIM Notification Rate for channel 9',
        'rateCom_ecim_10' => 'COM_ECIM Notification Rate for channel 10'
    );

    return array(
        TITLE => 'COM/ECIM Notification Rate',
        TABLE_NAME => "COMECIM_RATE",
        PARAMS => $tableParams
    );
}

function showSyncStatusChanges($selectedNeIds, $serviceGrp) {
    global $site, $date, $statsDB;

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, $serviceGrp);
    $serverIds=implode(",", array_values($processingSrv));
    drawHeaderWithHelp("Sync Status Changes", 2, "syncstatuschanges");

    $dbTables = array(
        'enm_comecim_syncstatus',
        'enm_comecim_syncstatus_reason',
        ENM_NE_TABLE,
        StatsDB::SITES,
        StatsDB::SERVERS
    );
    $where = <<<EOT
enm_comecim_syncstatus.siteid = sites.id AND sites.name = '$site' AND
enm_comecim_syncstatus.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_comecim_syncstatus.serverid = servers.id AND
enm_comecim_syncstatus.serverid  IN($serverIds) AND
enm_comecim_syncstatus.neid = enm_ne.id AND
enm_comecim_syncstatus.reasonid = enm_comecim_syncstatus_reason.id
EOT;

    if ( ! is_null($selectedNeIds) ) {
        $where = $where . " AND enm_comecim_syncstatus.neid IN ( $selectedNeIds )";
    }

    $builder = SqlTableBuilder::init()
             ->name("SyncStatus")
             ->tables($dbTables)
             ->where($where)
             ->addHiddenColumn('id', 'enm_ne.id')
             ->addSimpleColumn("DATE_FORMAT(enm_comecim_syncstatus.time,'%H:%i:%s')", 'Time')
             ->addSimpleColumn(ENM_NE_NAME, NE)
             ->addSimpleColumn(HOSTNAME, 'Server')
             ->addSimpleColumn("enm_comecim_syncstatus.syncstatus", 'Status')
             ->addSimpleColumn('enm_comecim_syncstatus_reason.name', 'Reason')
             ->paginate( array(20, 100, 1000, 10000) )
             ->dbScrolling();

    if ( is_null($selectedNeIds) ) {
        $builder->ctxMenu(
            FILTER_ACTION_PARAM,
            true,
            array( SHOWREASONS => 'Filter Selected'),
            makeSelfLink() . "&servicegroup=$serviceGrp",
            'id'
        );
    }

    echo $builder->build()->getTable();
}

function showTcimStatusChanges() {
    $table = new ModelledTable( "TOR/cm/enm_tcim_state_changes", 'tcimStateChanges' );
    echo $table->getTableWithHeader('TCIM State Changes');
    echo addLineBreak();
}

function getFullSyncTotals($serverIds, $instName) {
    global $site, $date;

    $where = "enm_ecim_syncs.siteid = sites.id
              AND sites.name = '$site'
              AND enm_ecim_syncs.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              AND enm_ecim_syncs.serverid IN($serverIds)
              AND enm_ecim_syncs.serverid = servers.id
              GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
            ->name("fullsynctotals")
            ->tables(array( FULL_SYNC_TABLE, StatsDB::SITES,StatsDB::SERVERS))
            ->where($where)
            ->addSimpleColumn(HOSTNAME, $instName)
            ->addSimpleColumn(StatsDB::ROW_COUNT, SYNCS)
            ->addSimpleColumn('SEC_TO_TIME(ROUND(AVG(enm_ecim_syncs.duration/1000)))', 'Avg Duration')
            ->addSimpleColumn('SEC_TO_TIME(ROUND(MAX(enm_ecim_syncs.duration/1000)))', 'Max Duration')
            ->addSimpleColumn('IFNULL( SUM(enm_ecim_syncs.n_mo_create), 0 )', TOTAL_MO_CREATED )
            ->addSimpleColumn('IFNULL( SUM(enm_ecim_syncs.n_mo_update), 0 )', TOTAL_MO_UPDATED )
            ->addSimpleColumn('IFNULL( SUM(enm_ecim_syncs.n_mo_delete), 0 )', TOTAL_MO_DELETED )
            ->addSimpleColumn('ROUND(AVG((enm_ecim_syncs.mo_parsed / enm_ecim_syncs.duration) * 1000))', 'Avg MOs/sec' )
            ->addSimpleColumn('SUM(enm_ecim_syncs.mo_parsed)', 'Total MOs Parsed')
            ->build();
    echo $table->getTableWithHeader("Full Syncs", 2, "", "", "Full_Syncs");
}

function getDeltaSyncTotals($serverIds, $instName) {
    global $site, $date;

    $where = "enm_com_ecim_delta_syncs.siteid = sites.id
              AND sites.name = '$site'
              AND enm_com_ecim_delta_syncs.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              AND enm_com_ecim_delta_syncs.serverid IN($serverIds)
              AND enm_com_ecim_delta_syncs.serverid = servers.id
              GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
           ->name("deltasynctotals")
           ->tables(array( DELTA_TABLE, StatsDB::SITES, StatsDB::SERVERS ))
           ->where($where)
           ->addSimpleColumn(HOSTNAME, $instName)
           ->addSimpleColumn(StatsDB::ROW_COUNT, SYNCS)
           ->addSimpleColumn('SEC_TO_TIME(ROUND(AVG(TIMESTAMPDIFF(SECOND, starttime, endtime))))', 'Avg Duration')
           ->addSimpleColumn('SEC_TO_TIME(ROUND(MAX(TIMESTAMPDIFF(SECOND, starttime, endtime))))', 'Max Duration')
           ->addSimpleColumn('IFNULL( SUM(n_mo_created), 0 )', TOTAL_MO_CREATED )
           ->addSimpleColumn('IFNULL( SUM(n_mo_updated), 0 )', TOTAL_MO_UPDATED )
           ->addSimpleColumn('IFNULL( SUM(n_mo_deleted), 0 )', TOTAL_MO_DELETED )
           ->build();

    echo $table->getTableWithHeader("Delta Syncs", 2, "", "", "Delta_Syncs");
}

function getDailyNotifGenricRoute($serverIds, $instName, $serviceGrp, $notifRoutePatterns) {
    global $site, $date, $statsDB;

    $pattern = $notifRoutePatterns[$serviceGrp];
    $hasSummary = $statsDB->hasData(SUM_ROUTE_INSTR_TABLE, "date", true);
    if ( $hasSummary ) {
        $instrTable = SUM_ROUTE_INSTR_TABLE;
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
        $where = <<<EOT
        enm_route_instr.siteid = sites.id
        AND sites.name = '$site'
        AND enm_route_instr.serverid = servers.id
        AND enm_route_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        AND enm_route_instr.serverid IN($serverIds)
        AND enm_route_instr.routeid = enm_route_names.id
        AND enm_route_names.name LIKE '$pattern'
        AND enm_route_instr.ExchangesCompleted IS NOT NULL GROUP BY servers.hostname WITH ROLLUP
EOT;
    }

    $table = SqlTableBuilder::init()
        ->name("Daily_Notification_Totals_$serviceGrp")
        ->tables(array($instrTable, StatsDB::SITES,'enm_route_names',StatsDB::SERVERS))
        ->where($where)
        ->addSimpleColumn(HOSTNAME, $instName)
        ->addSimpleColumn('SUM(ExchangesCompleted)', 'Total Notifications Handled')
        ->build();
    echo $table->getTableWithHeader("Daily Notification Totals", 1, "", "", "Daily_Notification_Totals");
}

function showSyncs($serviceGrp) {
    $serverIds = makeSrvList($serviceGrp, true);

    /* COM/ECIM Syncs Table */
    $table = new ModelledTable( "TOR/cm/enm_ecim_syncs", 'Full_Sync_Stats', array(SERVERIDS => $serverIds));
    echo $table->getTableWithHeader("Successful Sync Stats");

    echo addLineBreak();

    $table = new ModelledTable( "TOR/cm/delta_sync", 'delta_sync_stats', array(SERVERIDS => $serverIds));
    echo $table->getTableWithHeader("Delta Sync Stats");
    echo addLineBreak();
}

function showSupervision($instType) {
    /* Supervision stats if available */
    $supervisionparams = supervisionGraphParams();
    foreach ( $supervisionparams as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 1, $help);
        $graphParams = $param[1];
        $sIds = array( 'types' => $instType );
        foreach ( $graphParams as $graphParam ) {
            $modelledGraph = new ModelledGraph( 'TOR/cm/' . $graphParam );
            $graphs[] = $modelledGraph->getImage($sIds);
        }
    }
    plotgraphs( $graphs );
}

function supervisionGraphParams() {
    $supervision = array( SUPERVISION );

    return array(
        array( 'Supervision', $supervision, SUPERVISION )
    );
}

function showActiveSyncs($servicegrp) {
    global $rootdir, $debug, $date;

    $seriesFile = $rootdir . "/cm/" . $servicegrp . "_activesyncs.json";
    if ( ! file_exists($seriesFile) && $servicegrp === SERVGROUP_ECIM ) {
        $seriesFile = $rootdir . "/cm/comecim_activesyncs.json";
    }
    if ( $debug > 0 ) { echo "<pre>showActiveSyncs seriesFile=$seriesFile</pre>\n"; }
    if ( file_exists($seriesFile) ) {
        $graphTable = new HTML_Table("border=0");
        $row = array();
        $sqlParamWriter = new SqlPlotParam();
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title("Ongoing Full Sync Counts")
                  ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                  ->yLabel("#Syncs")
                  ->disableUserAgg()
                  ->seriesFromFile($seriesFile)
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
        $graphTable->addRow($row);
        drawHeaderWithHelp("Ongoing Full Sync Counts", 1, "Ongoing_Sync_Counts");
        echo $graphTable->toHTML();
    }
}

function getNotifRecTables($statsDB, $site, $date, $serviceGrp) {
  global $webargs;

  $row = $statsDB->queryRow("
SELECT COUNT(*)
 FROM enm_mscmce_notifrec, sites
 WHERE
   enm_mscmce_notifrec.date = '$date' AND
   enm_mscmce_notifrec.siteid = sites.id AND sites.name = '$site' AND
   enm_mscmce_notifrec.servicegroup = '$serviceGrp'
");
  if ( $row[0] == 0 ) {
    return NULL;
  }

  return array(
      SqlTableBuilder::init()
      ->name("notif_top_details")
      ->tables(array( 'enm_mscmce_notiftop', ENM_NE_TABLE, StatsDB::SITES ))
      ->where(
          "enm_mscmce_notiftop.date = '$date'
           AND enm_mscmce_notiftop.siteid = sites.id
           AND sites.name = '$site'
           AND enm_mscmce_notiftop.neid = enm_ne.id
           AND enm_mscmce_notiftop.servicegroup = '$serviceGrp'"
       )
      ->addHiddenColumn('id', 'enm_ne.id')
      ->addSimpleColumn(ENM_NE_NAME, NE)
      ->addColumn(CNT, 'enm_mscmce_notiftop.count', 'Count')
      ->sortBy(CNT, DDPTable::SORT_DESC)
      ->paginate()
      ->ctxMenu(
          NOTIF_ACT,
          true,
          array( 'plotnotiftop' => 'Plot for last month'),
          fromServer(PHP_PAGE) . "?" . $webargs . "&servicegroup=$serviceGrp",
          'id'
          )
      ->build()
     );
}

function plotNotifRec($site, $date, $selectedStr, $serviceGrp) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
enm_mscmce_notifrec.siteid = sites.id AND sites.name = '%s' AND
enm_mscmce_notifrec.eventtype = '%s' AND
enm_mscmce_notifrec.moid = mo_names.id AND mo_names.name = '%s' AND
enm_mscmce_notifrec.attribid = enm_mscm_attrib_names.id AND enm_mscm_attrib_names.name = '%s' AND
enm_mscmce_notifrec.servicegroup = '$serviceGrp'
";
    $queryList = array();
    foreach ( explode(",", $selectedStr) as $selected ) {
        $selectedParts = explode(":", $selected);
        $queryList[] = array(
            'timecol' => 'date',
            'whatcol' => array( CNT => $selected ),
            'tables' => "enm_mscmce_notifrec, mo_names, sites, enm_mscm_attrib_names",
            'where' => sprintf($where, $site, $selectedParts[0], $selectedParts[1], $selectedParts[2])
        );
    }

    $sqlParam = array(
        'title' => "Notifications Received",
        'type' => 'tsc',
        'ylabel' => "#Notifications",
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' => $queryList
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59"));
}

function plotNotifTop($date, $selectedStr, $serviceGrp) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
enm_mscmce_notiftop.siteid = sites.id AND sites.name = '%s' AND
enm_mscmce_notiftop.neid = enm_ne.id AND enm_ne.id IN ( %s ) AND
enm_mscmce_notiftop.servicegroup = '$serviceGrp'";

    $sqlParam = SqlPlotParamBuilder::init()
              ->title("Notifications Received")
              ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
              ->yLabel("#Notifications")
              ->addQuery(
                  'date',
                  array( CNT => "#Notifications" ),
                  array( "enm_mscmce_notiftop", "enm_ne", StatsDB::SITES ),
                  $where,
                  array( 'site', 'neids' ),
                  ENM_NE_NAME
                  )
              ->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59", "neids=$selectedStr"));
}

function showAggreatedNotifRoute($statsDB, $serviceGrp, $notifRoutePatterns) {
    global $site, $date;

    $routePattern = $notifRoutePatterns[$serviceGrp];
    $baseWhere = $statsDB->where(ROUTE_INSTR_TABLE);

    $notifRouteIds = array();
    $hasSummary = $statsDB->hasData(SUM_ROUTE_INSTR_TABLE, "date", true);
    if ( $hasSummary ) {
        $query = <<<EOT
SELECT DISTINCT sum_enm_route_instr.routeid
FROM sum_enm_route_instr
JOIN sites ON sum_enm_route_instr.siteid = sites.id
JOIN enm_route_names ON sum_enm_route_instr.routeid = enm_route_names.id
WHERE
 sites.name = '$site' AND
 sum_enm_route_instr.date = '$date' AND
 enm_route_names.name LIKE '$routePattern'
EOT;
    } else {
        $query = <<<EOT
SELECT DISTINCT enm_route_instr.routeid
FROM enm_route_instr, enm_route_names, sites
WHERE $baseWhere AND
enm_route_instr.routeid = enm_route_names.id AND enm_route_names.name LIKE '$routePattern'
EOT;
    }
    $statsDB->query($query);
    while ( $row = $statsDB->getNextRow() ) {
        $notifRouteIds[] = $row[0];
    }

    if ( count($notifRouteIds) > 1 ) {
        drawHeaderWithHelp("Aggregated Notification Processing", 1, "aggnotifproc");
        $sqlParamWriter = new SqlPlotParam();
        $graphTable = new HTML_Table("border=0");

        $notifRouteIdsStr = implode(",", $notifRouteIds);
        $graphWhere = <<<EOT
$baseWhere AND
enm_route_instr.routeid IN ( $notifRouteIdsStr ) AND
enm_route_instr.serverid = servers.id
EOT;
        $dbTables = array( ROUTE_INSTR_TABLE, StatsDB::SITES, StatsDB::SERVERS);
        $row = array();
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title(Routes::EXCHANGES_COMPLETED)
                  ->type(SqlPlotParam::STACKED_BAR)
                  ->barwidth(60)
                  ->yLabel("")
                  ->forceLegend()
                  ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
                  ->disableUserAgg()
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array( Routes::EXCHANGES_COMPLETED => Routes::EXCHANGES_COMPLETED ),
                      $dbTables,
                      $graphWhere,
                      array('site'),
                      SERVERS_HOSTNAME
                  )
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 1200, 400);
        $graphTable->addRow($row);
        echo $graphTable->toHTML();
    }
}

function drawTable($params, $dbTable) {
    global $date,$site;
    $where = "$dbTable.siteid = sites.id AND sites.name = '$site' AND
              $dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              $dbTable.serverid = servers.id
              GROUP BY servers.hostname WITH ROLLUP";
    $table = SqlTableBuilder::init()
        ->name($params[TABLE_NAME])
        ->tables(array($dbTable, StatsDB::SITES, StatsDB::SERVERS))
        ->where($where)
        ->addSimpleColumn( "IFNULL(servers.hostname, 'Totals')", 'Instance');
    $prms = $params[PARAMS];

    foreach ($prms as $key => $value) {
        $table->addSimpleColumn("SUM($key)", $value);
    }
    echo $table->build()->getTableWithHeader($params[TITLE], 2, "", "", "");
}

function stickyGraphs($sectionTitle, $stickyparams, $dbTable) {
    global $date,$site;

    $graphs = array();
    $instances = getInstances($dbTable);

    drawHeader("$sectionTitle", 2, "");

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '%s'
              AND $dbTable.serverid = servers.id
              AND servers.hostname = '%s'";

    foreach ( $instances as $instance ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('%s')
            ->titleArgs(array('inst'))
            ->type(SqlPlotParam::STACKED_BAR)
            ->barWidth(100)
            ->makePersistent()
            ->addQuery(
                'time',
                $stickyparams,
                array($dbTable, 'sites', SERVERS),
                $where,
                array('site', 'inst')
            )
            ->build();
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $extraArgs = "inst=$instance";
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 750, 350, $extraArgs);
    }

    plotGraphs($graphs);
}

function instrComecimGraphs() {
    stickyGraphs(
        'Notification Rate COM_ECIM',
        array(
            'rateCom_ecim_1' => 'RateCOM_ECIM_1',
            'rateCom_ecim_2' => 'RateCOM_ECIM_2',
            'rateCom_ecim_3' => 'RateCOM_ECIM_3',
            'rateCom_ecim_4' => 'RateCOM_ECIM_4',
            'rateCom_ecim_5' => 'RateCOM_ECIM_5',
            'rateCom_ecim_6' => 'RateCOM_ECIM_6',
            'rateCom_ecim_7' => 'RateCOM_ECIM_7',
            'rateCom_ecim_8' => 'RateCOM_ECIM_8',
            'rateCom_ecim_9' => 'RateCOM_ECIM_9',
            'rateCom_ecim_10' => 'RateCOM_ECIM_10'
        ),
        COMECIM_NOTIF_TABLE
    );
    stickyGraphs(
        'Notification Allocation COM_ECIM',
        array(
            'useCom_ecim_1' => 'UseCOM_ECIM_1',
            'useCom_ecim_2' => 'UseCOM_ECIM_2',
            'useCom_ecim_3' => 'UseCOM_ECIM_3',
            'useCom_ecim_4' => 'UseCOM_ECIM_4',
            'useCom_ecim_5' => 'UseCOM_ECIM_5',
            'useCom_ecim_6' => 'UseCOM_ECIM_6',
            'useCom_ecim_7' => 'UseCOM_ECIM_7',
            'useCom_ecim_8' => 'UseCOM_ECIM_8',
            'useCom_ecim_9' => 'UseCOM_ECIM_9',
            'useCom_ecim_10' => 'UseCOM_ECIM_10'
        ),
        COMECIM_NOTIF_TABLE
    );
}
function stickyNotification() {
    global $debug, $webargs, $php_webroot;
    $serverLink= fromServer(PHP_PAGE);
    $msgsURL =  "$serverLink?$webargs&servicegroup=comecimmscm";
    echo "<a href=\"$msgsURL\">Return to COM/ECIM Mediation</a>\n";
    $iposUsageInputs = iposUsageParams();
    drawTable($iposUsageInputs, IPOS_NOTIF_TABLE);
    $iposRateInputs = iposRateParams();
    drawTable($iposRateInputs, IPOS_NOTIF_TABLE);
    echo addLineBreak(2);
    $comEcimUsageInputs = comEcimUsageParams();
    drawTable($comEcimUsageInputs, COMECIM_NOTIF_TABLE);
    $comEcimRateInputs = comEcimRateParams();
    drawTable($comEcimRateInputs, COMECIM_NOTIF_TABLE);
    echo addLineBreak(2);
    instrComecimGraphs();
}

function displaySyncStats($hasInstr, $notifRec, $instType, $serviceGrp, $srvIds, $instName) {
  global  $statsDB, $rootdir;

  /*Notification Analysis Stats */
  $links = array();
  $subLinks = array();
  $links[] = makeLink(
      PAGE,
      "Successful Sync Stats",
      array(
          'showsyncs' => '1',
          'servicegroup' => $serviceGrp
      )
  );

  if ( $statsDB->hasData('enm_comecim_syncstatus') || $statsDB->hasData("enm_comecim_tcim_status") ) {
      $links[] = makeLink(
          PAGE,
          "Status Sync and TCIM Change Reasons",
          array(SERVICE_GROUP => $serviceGrp, 'filteraction' => SHOWREASONS)
      );
  }

  $links[] = makeFullGenJmxLink($serviceGrp, "Generic JMX for $instType");

  $srvs = makeSrvList($serviceGrp);
  $links[] = makeLink('/TOR/dps.php', "$instType DPS Instrumentation", array(SERVERS => $srvs) );

  $links[] = makeLink(
      '/TOR/cm/mscmce_notif_analysis.php',
      "Notification Analysis",
      array(SERVICE_GROUP => $serviceGrp)
  );

  $hoverOverText = 'Click here to go to the "Supervision" stats in this page.';
  $links[] = makeAnchorLink(SUPERVISION, 'Supervision', $hoverOverText);

  $hoverOverText = 'Click here to go to the "Queues" stats in this page.';
  $links[] = makeAnchorLink('Queues_anchor', QUEUES, $hoverOverText);

  $hoverOverText = 'Click here to go to the "$instType Instrumentation" stats in this page.';
  $subLinks[] = makeAnchorLink(HEARTBEAT, HEARTBEAT);

  $subLinks[] = makeAnchorLink("Sync", "Sync");
  $subLinks[] = makeAnchorLink("numNodesWaiting", "Number Of Nodes Waiting For Resync");
  if ( $statsDB->hasData( 'enm_dynamic_flow_control' )) {
      $subLinks[] = makeAnchorLink("availablebucketsize", "Available Bucket Size Per Instance");
  }
  if ( $hasInstr ) {
      $subLinks[] = makeAnchorLink("NetConf_Subscriptions", "NetConf Subscriptions");
      $subLinks[] = makeAnchorLink("NetConf_Write", "NetConf Write");
      $subLinks[] = makeAnchorLink("NetConf_Non-persistent_Reads", "NetConf Non-persistent Reads");
      $subLinks[] = makeAnchorLink("NetConf_Session", "NetConf Session");
  }

  $subTitle =  makeAnchorLink("MSCMCE", "$instType Instrumentation", $hoverOverText);
  $links[] = $subTitle. makeHTMLList($subLinks);

  if ( $statsDB->hasData(MSCMCE_TAB) ) {
      $links[] = makeAnchorLink('Software_Sync_Invocations', SSI);
      $links[] = makeAnchorLink('Mib_Upgrade', "Mib Upgrade");
  }

  $links[] = routesLink();

  $links[] = makeLink(
      PAGE,
      'Sticky Notification Instrumentation',
      array('stickyNotification'=> '1')
  );

  if ( ! is_null($notifRec) ) {
      $links[] = makeAnchorLink("notifrec", "Notifications Received Details");
      $links[] = makeAnchorLink("notiftop", "Top Notification Nodes");
  }
  echo makeHTMLList($links);

  showSupervision($instType);

  /* This will check for the new file syncStatus_{$serviceGrp}_event.json first*/
  $sgSeriesFile = $rootdir . "/cm/syncStatus_" . $serviceGrp . "_event.json";
  if ( ! file_exists($sgSeriesFile) ) {
      /* This is for both old types syncStatus file with Service group and old file with Hardcoded instance type*/
      $sgSeriesFile = $rootdir . "/cm/syncStatus_$serviceGrp.json";
  }

  if ( file_exists($sgSeriesFile) ) {
      showSyncStatusEvents( strtolower($serviceGrp));
  } else {
      showSyncStatusEvents( strtolower($instType));
  }
  showActiveSyncs($serviceGrp);

    if ( $serviceGrp===SERVGROUP_ECIM) {
         $queueNames = array('ComEcimMdbNotificationListener_0', 'ClusteredMediationServiceConsumerCMCE0');
    }

    if ( $serviceGrp===SERVGROUP_APG) {
         $queueNames = array('ApgMdbNotificationListener_0', 'ClusteredMediationServiceConsumerCMCE0');
    }

    plotQueues( $queueNames );
}

function mscmceInstrumentationGraphs($hasInstr, $instType, $serverIds) {
    global $statsDB;

    echo "<H1 id=\"MSCMCE\">$instType Instrumentation</H1>\n";

    $where = " enm_ecim_notif_supervision_instr.serverid IN($serverIds)";
    $hasNotifSuperInstr = $statsDB->hasData( 'enm_ecim_notif_supervision_instr', 'time', false, $where );

    $params = array(SERVERIDS => $serverIds);

    getGraphsFromSet( 'hb', $hb, MSCMCE_INSTR, $params, 640, 320 );
    drawHeaderWithHelp(HEARTBEAT, 2, HEARTBEAT . 'help');
    plotGraphs( $hb );

    if ( $hasInstr ) {
        getGraphsFromSet( 'sync', $sync, MSCMCE_INSTR, $params, 640, 320 );
        drawHeaderWithHelp('Sync', 2, 'Sync');
        plotGraphs( $sync );

        $graph=array();
        $numNodesgraph = new ModelledGraph('TOR/cm/ecim_nodes_waiting_for_resync');
        drawHeader('Number Of Nodes Waiting For Resync', 2, 'numNodesWaiting');
        $graph = $numNodesgraph->getImage();
        plotgraphs( array( $graph ) );

        if ( $statsDB->hasData( 'enm_dynamic_flow_control' )) {
            $graph=array();
            $dynamicgraph = new ModelledGraph('TOR/cm/ecim_available_bucket_size');
            drawHeader('Available Bucket Size Per Instance', 2, 'availablebucketsize');
            $graph = $dynamicgraph->getImage();
            plotgraphs( array( $graph ) );
        }

        getGraphsFromSet( 'netConfSub', $netConfSub, MSCMCE_INSTR, $params, 640, 320 );
        drawHeaderWithHelp('NetConf Subscriptions', 2, 'NetConf_Subscriptions');
        plotGraphs( $netConfSub );

        if ( ! $hasNotifSuperInstr ) {
            getGraphsFromSet( 'netConfWrite', $netConfWrite, MSCMCE_INSTR, $params, 640, 320 );
            drawHeaderWithHelp('NetConf Write', 2, 'NetConf_Write');
            plotGraphs( $netConfWrite );

            getGraphsFromSet( 'netConfPerReads', $netConfPerReads, MSCMCE_INSTR, $params, 640, 320 );
            drawHeaderWithHelp('NetConf Non-persistent Reads', 2, 'NetConf_Non-persistent_Reads');
            plotGraphs( $netConfPerReads );
        } else {
            $graphs = array();
            getGraphsFromSet( 'create', $graphs, ECIM_NOTIF_SUPER_INSTR, $params, 640, 320 );
            getGraphsFromSet( 'netConfPerReadsSuccFail', $graphs, MSCMCE_INSTR, $params, 640, 320 );
            getGraphsFromSet( 'update', $graphs, ECIM_NOTIF_SUPER_INSTR, $params, 640, 320 );
            getGraphsFromSet( 'delete', $graphs, ECIM_NOTIF_SUPER_INSTR, $params, 640, 320 );
            getGraphsFromSet( 'netConfWriteAcSuccAcFail', $graphs, MSCMCE_INSTR, $params, 640, 320 );
            drawHeaderWithHelp('CRUDA', 2, 'CRUDA');
            plotGraphs( $graphs );
        }

        getGraphsFromSet( 'netConfSes', $netConfSes, MSCMCE_INSTR, $params, 640, 320 );
        drawHeaderWithHelp('NetConf Session', 2, 'NetConf_Session');
        plotGraphs( $netConfSes );

        getGraphsFromSet( 'notifBuff', $notifBuff, MSCMCE_INSTR, $params, 640, 320 );
        drawHeaderWithHelp('Notification Buffering', 2, 'notif_bufferhelp');
        plotGraphs( $notifBuff );
    }
}

function showSynMibUpgrade( $serverIds ) {
    $params = array(SERVERIDS => $serverIds);
    getGraphsFromSet( 'softSyncInv', $softSyncInv, MSCMCE_INSTR, $params, 640, 320 );
    drawHeaderWithHelp( 'Software Sync Invocations', 2, 'Software_Sync_Invocations' );
    plotGraphs( $softSyncInv );

    getGraphsFromSet( 'mib', $mib, MSCMCE_INSTR, $params, 640, 320 );
    drawHeaderWithHelp( 'Mib Upgrade', 2, 'Mib_Upgradehelp' );
    plotGraphs( $mib );
}

function mainFlow() {
    global $date, $site, $statsDB;

    $serviceGrp = requestValue(SERVICE_GROUP);

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, $serviceGrp);
    $serverIdsArr = array_values($processingSrv);
    $serverIdsStr = implode(",", $serverIdsArr);

    if ($serviceGrp === SERVGROUP_ECIM) {
        $instType='COMECIM';
    } elseif ($serviceGrp === SERVGROUP_APG) {
        $instType='APG';
    }

    $instName=$instType."_Instance";

    /* Daily Summary table */
    echo "<H1>Daily Totals</H1>\n";
    getFullSyncTotals($serverIdsStr, $instName);
    getDeltaSyncTotals($serverIdsStr, $instName);

    $notifRoutePatterns = array(
        SERVGROUP_ECIM => '//MEDIATION/EcimCmNotifHandlingFlow%_jms:com_ecim_notifications_channelId%',
        SERVGROUP_APG => '//BSC_MED/BscNotifHandlingFlow%_jms:bsc_notifications_channelId%'
    );

    getDailyNotifGenricRoute($serverIdsStr, $instName, $serviceGrp, $notifRoutePatterns);

    $notifRecTables = getNotifRecTables($statsDB, $site, $date, $serviceGrp);

    $where = " enm_mscmce_instr.serverid IN($serverIdsStr) AND enm_mscmce_instr.syncSucc IS NOT NULL";
    $hasInstr = $statsDB->hasData( MSCMCE_TAB, 'time', false, $where );

    displaySyncStats($hasInstr, $notifRecTables, $instType, $serviceGrp, $serverIdsStr, $instName);
    mscmceInstrumentationGraphs($hasInstr, $instType, $serverIdsStr);
    showAggreatedNotifRoute($statsDB, $serviceGrp, $notifRoutePatterns);
    showSynMibUpgrade($serverIdsStr);
    getRouteInstrTable( $serverIdsArr );

    if (! is_null($notifRecTables)) {
        $selfLink = makeSelfLink();
        $selfLink .= "&servicegroup=$serviceGrp";

        $params = array('sg' => $serviceGrp, ModelledTable::URL => $selfLink );
        $table = new ModelledTable( "TOR/cm/ecim_notif_rec", 'notifrec', $params );
        echo $table->getTableWithHeader('Notifications Received Details');
        echo addLineBreak();

        #echo "<H2 id=\"notiftop\"></H2>\n";
        #echo $notifRecTables[1]->getTableWithHeader("Top Notification Nodes", 1, "", "");
    }
}

$selected = requestValue(SELECTED);
$serviceGrp = requestValue(SERVICE_GROUP);

if (isset($_GET['showsyncs'])) {
    showSyncs( $serviceGrp );
} elseif (isset($_REQUEST[FILTER_ACTION_PARAM])) {
    $action = requestValue(FILTER_ACTION_PARAM);
    if ( $action === SHOWREASONS ) {
        showSyncStatusChanges($selected, $serviceGrp);
        showTcimStatusChanges();
    }
} elseif ( issetURLParam('shownodeindex') ) {
    $instType = getArgs('instType');
    showNodeIndex( $instType );
} elseif (issetURLParam('stickyNotification')) {
    stickyNotification();
} elseif ( issetURLParam(ACTION_PARAM) && requestValue(ACTION_PARAM) === 'plotRouteGraphs' ) {
    plotRoutes($selected);
} elseif ( issetURLParam(NOTIF_ACT) ) {
    $action = requestValue(NOTIF_ACT);
    if ( $action === 'plotnotifrec' ) {
        plotNotifRec($site, $date, $selected, $serviceGrp);
    } elseif ( $action === 'plotnotiftop' ) {
        plotNotifTop($date, $selected, $serviceGrp);
    }
} else {
  mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
