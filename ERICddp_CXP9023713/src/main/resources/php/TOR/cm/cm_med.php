<?php
$pageTitle = "CPP CM Mediation";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/TOR/cm/cm_functions.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const MSCM_INST = 'MSCM Instance';
const COUNT_ALL = 'COUNT(*)';
const TOTAL_NOTE = 'Total Notifications Handled';
const START = 'Start Time';
const NE = 'Network Element';
const ENM_NE = 'enm_ne.name';
const NE_NAME = 'ne_name';
const U_CNT = 'Count';
const L_CNT = 'count';
const ACTION = 'action';
const SERV_HOST = 'servers.hostname';
const RND = 'ROUND(1000 * n_mo / t_complete, 2)';
const MO_SEC = 'MOs/sec';
const CM_MED= '/TOR/cm/cm_med.php';
const SELECTED = 'selected';
const T_END_ANALYSIS = 'tendanalysis';
const T_START_ANALYSIS = 'tstartanalysis';
const SSI = 'Software Sync Invocations';
const DB_TABLES = 'enm_cm_med_instr, sites, servers';
const SITES = 'sites';
const SERVERS = 'servers';
const CM_SYNC_TAB = 'enm_cm_syncs';
const NE_TAB = 'enm_ne';
const TITLE = 'title';
const STRT = 'start';

$SG = requestValue('SG');
$display = 'none';

function getSyncTotals($type) {
    global $site, $date;

    if ( $type == 'DELTA' ) {
        $cols = array(
            array( 'key' => 'inst', 'db' => "IFNULL(servers.hostname,'Totals')", DDPTable::LABEL => MSCM_INST),
            array( 'key' => 'num', 'db' => COUNT_ALL, DDPTable::LABEL => '#Syncs' ),
            array( 'key' => 'avg_duration', 'db' => 'SEC_TO_TIME(ROUND(AVG(t_complete/1000)))',
                   DDPTable::LABEL => 'Avg Duration' ),
            array( 'key' => 'max_duration', 'db' => 'SEC_TO_TIME(ROUND(MAX(t_complete/1000)))',
                    DDPTable::LABEL => 'Max Duration' ),
            array( 'key' => 'total_created', 'db' => 'IFNULL(SUM(n_mo_created), 0)',
                   DDPTable::LABEL => 'Total MOs Created/Updated' ),
            array( 'key' => 'total_deleted', 'db' => 'IFNULL(SUM(n_mo_deleted), 0)',
                   DDPTable::LABEL => 'Total MOs Deleted' )
        );
    } else {
        $cols = array(
            array( 'key' => 'inst', 'db' => "IFNULL(servers.hostname,'Totals')", DDPTable::LABEL => MSCM_INST),
            array( 'key' => 'num', 'db' => COUNT_ALL, DDPTable::LABEL => '#Syncs' ),
            array( 'key' => 'avg_duration', 'db' => 'SEC_TO_TIME(ROUND(AVG(t_complete/1000)))',
                   DDPTable::LABEL => 'Avg Duration' ),
            array( 'key' => 'avg_mo_rate', 'db' => 'ROUND(IFNULL(1000 * SUM(n_mo)/SUM(t_complete), 0))',
                   DDPTable::LABEL => 'Avg MOs/Sec' ),
            array( 'key' => 'max_duration', 'db' => 'SEC_TO_TIME(ROUND(MAX(t_complete/1000)))',
                   DDPTable::LABEL => 'Max Duration' ),
            array( 'key' => 'total_created', 'db' => 'IFNULL(SUM(n_mo_created), 0)',
                   DDPTable::LABEL => 'Total MOs Created' ),
            array( 'key' => 'total_deleted', 'db' => 'IFNULL(SUM(n_mo_deleted), 0)',
                   DDPTable::LABEL => 'Total MOs Deleted' ),
            array( 'key' => 'total_syncs', 'db' => 'SUM(n_mo)', DDPTable::LABEL => 'Total MOs Synced' )
        );
    }

    $where = "enm_cm_syncs.siteid = sites.id AND sites.name = '$site' AND
              enm_cm_syncs.start BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              enm_cm_syncs.serverid = servers.id AND enm_cm_syncs.type = '$type'
              GROUP BY servers.hostname WITH ROLLUP;";

    $table = new SqlTable(
        "Sync_Totals_$type",
        $cols,
        array( CM_SYNC_TAB, SITES, SERVERS ),
        $where,
        true
    );
    echo $table->getTable();
}

function getDailyNotifTotals($srvIds) {
    global $site, $date, $statsDB;

    $servIdStr = implode(",", $srvIds);
    $cols = array(
        array( 'key' => 'inst', 'db' => 'IFNULL(servers.hostname,"Total")', DDPTable::LABEL => MSCM_INST),
        array( 'key' => 'notificationTotal', 'db' => 'SUM(ExchangesCompleted)',
               DDPTable::LABEL => TOTAL_NOTE )
    );

    $hasSummary = $statsDB->hasData("sum_enm_route_instr", "date", true);
    if ( $hasSummary ) {
        $instrTable = 'sum_enm_route_instr';
        $where = <<<EOT
sum_enm_route_instr.siteid = sites.id AND sites.name = '$site' AND
sum_enm_route_instr.serverid = servers.id AND sum_enm_route_instr.serverid IN ( $servIdStr ) AND
sum_enm_route_instr.date = '$date' AND
sum_enm_route_instr.routeid = enm_route_names.id AND
(enm_route_names.name = 'jms:network_element_notifications_channelId' OR enm_route_names.name =
 '//MEDIATION/NotificationHandlingFlow/1.0.0_jms:network_element_notifications_channelId') AND
sum_enm_route_instr.ExchangesCompleted IS NOT NULL
GROUP BY servers.hostname WITH ROLLUP
EOT;
    } else {
        $instrTable = 'enm_route_instr';
        $where = <<<EOT
enm_route_instr.siteid = sites.id AND sites.name = '$site' AND enm_route_instr.serverid = servers.id AND
enm_route_instr.serverid IN ( $servIdStr ) AND
enm_route_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_route_instr.routeid = enm_route_names.id AND
(enm_route_names.name = 'jms:network_element_notifications_channelId' OR enm_route_names.name =
'//MEDIATION/NotificationHandlingFlow/1.0.0_jms:network_element_notifications_channelId') AND
enm_route_instr.ExchangesCompleted IS NOT NULL GROUP BY servers.hostname WITH ROLLUP
EOT;
    }

    $table = new SqlTable(
        "Daily_Notification_Totals",
        $cols,
        array( $instrTable, 'enm_route_names', SITES, SERVERS ),
        $where,
        true
    );
    echo $table->getTableWithHeader("Daily Notification Totals", 2, "", "", "Daily_Notification_Totals");
}

function getAddNodeStats() {
    global $site, $date;
    $cols = array(
        array( 'key' => 'start_time', 'db' => "DATE_FORMAT(enm_cm_addnode_stats.start,'%H:%i:%s')",
               DDPTable::LABEL => START),
        array( 'key' => NE_NAME, 'db' => ENM_NE, DDPTable::LABEL => NE),
        array( 'key' => 'ne_time', 'db' => 'enm_cm_addnode_stats.ne_time', DDPTable::LABEL => 'NE Time'),
        array( 'key' => 'cpp_time', 'db' => 'enm_cm_addnode_stats.cpp_time', DDPTable::LABEL => 'CPP Time'),
        array( 'key' => 'total_time', 'db' => 'enm_cm_addnode_stats.total_time', DDPTable::LABEL => 'Total Time')
    );

    $where = "enm_cm_addnode_stats.siteid = sites.id AND sites.name = '$site' AND
              enm_cm_addnode_stats.start BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              enm_cm_addnode_stats.neid = enm_ne.id";

    $table = new SqlTable(
        "Add_Node_Stats",
        $cols,
        array( 'enm_cm_addnode_stats', SITES, NE_TAB ),
        $where,
        true,
        array('order' => array( 'by' => 'start_time', 'dir' => 'ASC'),
            'rowsPerPage' => 50,
            'rowsPerPageOptions' => array(100, 500,1000,5000,10000)
        )
    );
    echo $table->getTableWithHeader("Add-Node Stats", 2, "", "", "Add_Node_Stats");
}

function getTotalSyncCounts() {
    global $site, $date;
    $cols = array(
        array( 'key' => NE_NAME, 'db' => "enm_ne.name", DDPTable::LABEL => NE),
        array( 'key' => 'sync_count', 'db' => COUNT_ALL, DDPTable::LABEL => 'Sync Count' ),
    );

    $where = "enm_cm_syncs.siteid = sites.id AND sites.name = '$site' AND
             enm_cm_syncs.start BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             enm_cm_syncs.neid = enm_ne.id GROUP BY ne_name";

    $table = new SqlTable(
        "Total_Sync_Counts",
        $cols,
        array( CM_SYNC_TAB, SITES, NE_TAB ),
        $where,
        true,
        array('order' => array( 'by' => 'sync_count', 'dir' => 'DESC'),
            'rowsPerPage' => 25,
            'rowsPerPageOptions' => array(50, 100)
        )
    );
    echo $table->getTableWithHeader("Successful Sync Counts", 2, "", "", "Successful_Sync_Counts");
}

function getSyncKPIPerformance() {
    global $site, $date, $tstart, $networkElement, $tend;
    $cols = array(
        array( 'key' => 'num_syncs', 'db' => "COUNT(servers.hostname)", DDPTable::LABEL => 'Total Syncs'),
        array( 'key' => 'sum_mo', 'db' => 'SUM(enm_cm_syncs.n_mo)', DDPTable::LABEL => 'Total MOs'),
        array( 'key' => 'mo_syncs', 'db' => 'ROUND( IFNULL( 1000 * SUM(enm_cm_syncs.n_mo) / SUM(t_complete), 0 ), 2 )',
               DDPTable::LABEL => 'MOs Synced/sec'),
        array( 'key' => 'node_syncs', 'db' => 'ROUND( IFNULL( 1000 * COUNT(servers.hostname) /
               SUM(t_complete), 0 ), 2 )', DDPTable::LABEL => 'Nodes Synced/sec'),
        array( 'key' => 'mo_created', 'db' => 'ROUND( IFNULL( 1000 * SUM(n_mo_created) /
               SUM( IF( n_mo_created = 0, 0, t_complete ) ), 0 ), 2 )', DDPTable::LABEL => 'MOs Created/sec'),
        array( 'key' => 'mo_deleted', 'db' => 'ROUND( IFNULL( 1000 * SUM(n_mo_deleted) /
        SUM( IF( n_mo_deleted = 0, 0, t_complete ) ), 0 ), 2 )', DDPTable::LABEL => 'MOs Deleted/sec')
    );

    if ( valueExists($networkElement) ) {
        $where = "
                enm_cm_syncs.siteid = sites.id
                AND enm_cm_syncs.neid = enm_ne.id
                AND sites.name = '$site'
                AND enm_cm_syncs.start BETWEEN '$tstart' AND '$tend'
                AND enm_ne.name = '$networkElement'
                AND enm_cm_syncs.serverid = servers.id
                AND enm_cm_syncs.type = 'FULL'
        ";
        $tables = array( CM_SYNC_TAB, NE_TAB, SITES, SERVERS );
    } else {
        $where = "
            enm_cm_syncs.siteid = sites.id
            AND sites.name = '$site'
            AND enm_cm_syncs.start BETWEEN '$tstart' AND '$tend'
            AND enm_cm_syncs.serverid = servers.id
            AND enm_cm_syncs.type = 'FULL'
        ";
        $tables = array( CM_SYNC_TAB, SITES, SERVERS );
    }

    $table = new SqlTable(
        "Sync_KPI_Performance",
        $cols,
        $tables,
        $where,
        true,
        array('order' => array( 'by' => 'num_syncs', 'dir' => 'DESC')
        )
    );
    echo $table->getTable();
}

function getNotifRecTables($statsDB, $site, $date) {
    global $webargs;

    $row = $statsDB->queryRow("
SELECT
    COUNT(*)
FROM
    enm_mscm_notifrec, sites
WHERE
    enm_mscm_notifrec.date = '$date' AND
    enm_mscm_notifrec.siteid = sites.id AND sites.name = '$site'");

    if ( $row[0] == 0 ) {
        return null;
    }

    return array(
        new SqlTable(
            "notif_rec_details",
            array(
                array( 'key' => 'id', 'visible' => false,
                'db' => 'CONCAT(enm_mscm_notifrec.eventtype,":",mo_names.name,":",enm_mscm_attrib_names.name)'),
                array( 'key' => 'eventtype', DDPTable::LABEL => 'Event Type' ),
                array( 'key' => 'mo', 'db' => 'mo_names.name', DDPTable::LABEL => 'MO' ),
                array( 'key' => 'attrib', 'db' => 'enm_mscm_attrib_names.name', DDPTable::LABEL => 'Attribute' ),
                array( 'key' => L_CNT, DDPTable::LABEL => U_CNT )
            ),
            array( 'enm_mscm_notifrec', 'mo_names', SITES, 'enm_mscm_attrib_names' ),
            "enm_mscm_notifrec.date = '$date' AND enm_mscm_notifrec.siteid = sites.id AND sites.name = '$site' AND
            enm_mscm_notifrec.moid = mo_names.id AND enm_mscm_notifrec.attribid = enm_mscm_attrib_names.id",
            true,
            array( 'order' => array( 'by' => L_CNT, 'dir' => 'DESC'),
                   'rowsPerPage' => 25,
                   'rowsPerPageOptions' => array(50, 100, 1000, 10000),
                   DDPTable::CTX_MENU => array('key' => DDPTable::ACTION,
                                      DDPTable::MULTI => true,
                                      'menu' => array( 'plotnotifrec' => 'Plot for last month'),
                                      'url' => makeSelfLink(),
                                      'col' => 'id')
            )
        ),
        new SqlTable(
            "notif_top_details",
            array(
                array( 'key' => 'id', 'db' => 'enm_ne.id', 'visible' => false ),
                array( 'key' => 'name', 'db' => ENM_NE, DDPTable::LABEL => NE ),
                array( 'key' => L_CNT, 'db' => 'enm_mscm_notiftop.count', DDPTable::LABEL => U_CNT ),
            ),
            array( 'enm_mscm_notiftop', NE_TAB, SITES ),
            "enm_mscm_notiftop.date = '$date' AND enm_mscm_notiftop.siteid = sites.id AND sites.name = '$site' AND
            enm_mscm_notiftop.neid = enm_ne.id",
            true,
            array( 'order' => array( 'by' => L_CNT, 'dir' => 'DESC'),
                   'rowsPerPage' => 25,
                   'rowsPerPageOptions' => array(50, 100, 1000, 10000),
                   DDPTable::CTX_MENU => array('key' => DDPTable::ACTION,
                                      DDPTable::MULTI => true,
                                      'menu' => array( 'plotnotiftop' => 'Plot for last month'),
                                      'url' => makeSelfLink(),
                                      'col' => 'id')
            )
        ),
    );
}

function plotNotifRec($site, $date, $selectedStr) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
enm_mscm_notifrec.siteid = sites.id AND sites.name = '%s' AND
enm_mscm_notifrec.eventtype = '%s' AND
enm_mscm_notifrec.moid = mo_names.id AND mo_names.name = '%s' AND
enm_mscm_notifrec.attribid = enm_mscm_attrib_names.id AND enm_mscm_attrib_names.name = '%s'
";
    $queryList = array();
    foreach ( explode(",", $selectedStr) as $selected ) {
        $selectedParts = explode(":", $selected);
        $queryList[] = array(
            'timecol' => 'date',
            SqlPlotParam::WHAT_COL => array( L_CNT => $selected ),
            'tables' => "enm_mscm_notifrec, mo_names, sites, enm_mscm_attrib_names",
            'where' => sprintf($where, $site, $selectedParts[0], $selectedParts[1], $selectedParts[2])
        );
    }

    $sqlParam = array(
        TITLE => "Notifications Received",
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

function plotNotifTop($date, $selectedStr) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
enm_mscm_notiftop.siteid = sites.id AND sites.name = '%s' AND
enm_mscm_notiftop.neid = enm_ne.id AND enm_ne.id IN ( %s )";

    $sqlParam = array(
        TITLE => "Notifications Received",
        'type' => 'tsc',
        'ylabel' => "#Notifications",
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' => array(
            array(
                'timecol' => 'date',
                SqlPlotParam::MULTI_SERIES=> ENM_NE,
                SqlPlotParam::WHAT_COL => array( L_CNT => '#Notifications' ),
                'tables' => "enm_mscm_notiftop, enm_ne, sites",
                'where' => $where,
                'qargs'   => array( 'site', 'neids' )
            )
        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59", "neids=$selectedStr"));
}

function showAnalysisDetails() {
    global $site, $tStartAnalysis, $tEndAnalysis, $networkElementAnalysis;

    $table =
      new SqlTable("syncAnalysis",
                   array(
                       array( 'key' => 'time', 'db' => 'enm_cm_syncs.start', DDPTable::LABEL => START ),
                       array( 'key' => NE_NAME, 'db' => ENM_NE, DDPTable::LABEL => NE ),
                       array( 'key' => 'inst', 'db' => SERV_HOST, DDPTable::LABEL => MSCM_INST ),
                       array( 'key' => 'type', 'db' => 'enm_cm_syncs.type', DDPTable::LABEL => 'Type' ),
                       array( 'key' => 't_complete', 'db' => 'enm_cm_syncs.t_complete', DDPTable::LABEL => 'Duration' ),
                       array( 'key' => 'n_mo', 'db' => 'enm_cm_syncs.n_mo', DDPTable::LABEL => 'MOs' ),
                       array( 'key' => 'n_mo_created', 'db' => 'enm_cm_syncs.n_mo_created',
                              DDPTable::LABEL => 'MOs Created' ),
                       array( 'key' => 'n_mo_deleted', 'db' => 'enm_cm_syncs.n_mo_deleted',
                              DDPTable::LABEL => 'MOs Deleted' ),
                       array( 'key' => 'n_attr', 'db' => 'enm_cm_syncs.n_attr', DDPTable::LABEL => 'Attributes' ),
                       array( 'key' => 'n_mo_rate', 'db' => RND,
                              DDPTable::LABEL => MO_SEC ),
                       array( 'key' => 'n_adj_mo_rate', 'db' => 'ROUND(1000 * n_mo / (t_dps_top + t_dps_attr), 2)',
                              DDPTable::LABEL => 'Adjusted MO/Sec' ),
                       array( 'key' => 't_ne_top', 'db' => 'enm_cm_syncs.t_ne_top', DDPTable::LABEL => 'NE Topology' ),
                       array( 'key' => 't_dps_top', 'db' => 'enm_cm_syncs.t_dps_top',
                              DDPTable::LABEL => 'DPS Topology'  ),
                       array( 'key' => 't_ne_attr', 'db' => 'enm_cm_syncs.t_ne_attr',
                              DDPTable::LABEL => 'NE Attribute' ),
                       array( 'key' => 't_dps_attr', 'db' => 'enm_cm_syncs.t_dps_attr',
                              DDPTable::LABEL => 'DPS Attribute' ),
                   ),
                   array( CM_SYNC_TAB, NE_TAB, SITES, SERVERS ),
                   "
                enm_cm_syncs.siteid = sites.id
                AND enm_cm_syncs.neid = enm_ne.id
                AND sites.name = '$site'
                AND enm_cm_syncs.start BETWEEN '$tStartAnalysis' AND '$tEndAnalysis'
                AND enm_cm_syncs.neid =
                (SELECT enm_ne.id FROM enm_ne,sites WHERE enm_ne.name='$networkElementAnalysis' AND
                enm_ne.siteid = sites.id AND sites.name = '$site')
                AND enm_cm_syncs.serverid = servers.id
               ",
                   false,
                   array( 'order' => array( 'by' => 'time', 'dir' => 'ASC'),
                          'rowsPerPage' => 20,
                          'rowsPerPageOptions' => array( 100,1000, 10000 )
                          )
                   );
    echo $table->getTableWithHeader("Sync Stats", 2, "", "", "Sync_Stats");
}

function syncAnalysisGraph() {
    global $site,$networkElementAnalysis,$tStartAnalysis,$tEndAnalysis;

    drawHeaderWithHelp("Network Element Analysis", 2, "Network_Element_Analysis");
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $row = array();

        $sqlParam =
               array( TITLE => "Sync Times By stage",
                      'ylabel' => 'Time(msec)',
                      'type' => 'sb',
                      'useragg' => 'true',
                      'sb.barwidth' => '60',
                      'persistent' => 'false',
                      'querylist' =>
                        array(
                             array(
                                   'timecol' => STRT,
                                   SqlPlotParam::WHAT_COL => array ( 't_ne_top' => 'NE Topology',
                                                                     't_dps_top' => 'DPS Topology',
                                                                     't_ne_attr'=>'NE Attribute',
                                                                     't_dps_attr' => 'DPS Attribute'),
                                   'tables' => "enm_cm_syncs, enm_ne, sites, servers",
                                   'where' => "enm_cm_syncs.siteid = sites.id
                                                AND enm_cm_syncs.neid = enm_ne.id
                                                AND sites.name = '$site'
                                                AND enm_cm_syncs.start BETWEEN '$tStartAnalysis' AND '$tEndAnalysis'
                                                AND enm_cm_syncs.neid =
                                                (SELECT enm_ne.id FROM enm_ne,sites WHERE
                                                enm_ne.name='$networkElementAnalysis' AND enm_ne.siteid = sites.id AND
                                                sites.name = '$site')
                                                AND enm_cm_syncs.serverid = servers.id",
                                   'qargs' => array( 'site' )
                                  )
                            )
                     );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$tStartAnalysis", "$tEndAnalysis", true, 480, 280);

    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function syncAnalysisDurationGraph() {
    global $site,$networkElementAnalysis,$tStartAnalysis,$tEndAnalysis;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $row = array();

        $sqlParam =
               array( TITLE => "Sync Duration",
                      'ylabel' => 'Time(msec)',
                      'type' => 'xy',
                      'useragg' => 'true',
                      'sb.barwidth' => '60',
                      'persistent' => 'false',
                      'querylist' =>
                        array(
                             array(
                                   'timecol' => STRT,
                                   SqlPlotParam::WHAT_COL => array ( 't_complete' => 'Duration'),
                                   'tables' => "enm_cm_syncs, enm_ne, sites, servers",
                                   'where' => "enm_cm_syncs.siteid = sites.id
                                                AND enm_cm_syncs.neid = enm_ne.id
                                                AND sites.name = '$site'
                                                AND enm_cm_syncs.start BETWEEN '$tStartAnalysis' AND '$tEndAnalysis'
                                                AND enm_cm_syncs.neid =
                                                (SELECT enm_ne.id FROM enm_ne,sites WHERE
                                                enm_ne.name='$networkElementAnalysis' AND
                                                enm_ne.siteid = sites.id AND sites.name = '$site')
                                                AND enm_cm_syncs.serverid = servers.id
                                              ",
                                   'qargs' => array( 'site' )
                                  )
                            )
                     );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$tStartAnalysis", "$tEndAnalysis", true, 480, 280);

    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function syncAnalysisMOGraph() {
    global $site,$networkElementAnalysis,$tStartAnalysis,$tEndAnalysis;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $row = array();

        $sqlParam =
               array( TITLE => "Sync Rate",
                      'ylabel' => MO_SEC,
                      'type' => 'xy',
                      'useragg' => 'true',
                      'sb.barwidth' => '60',
                      'persistent' => 'false',
                      'querylist' =>
                        array(
                             array(
                                   'timecol' => STRT,
                                   SqlPlotParam::WHAT_COL => array ( RND => 'MO/sec'),
                                   'tables' => "enm_cm_syncs, enm_ne, sites, servers",
                                   'where' => "enm_cm_syncs.siteid = sites.id
                                                AND enm_cm_syncs.neid = enm_ne.id
                                                AND sites.name = '$site'
                                                AND enm_cm_syncs.start BETWEEN '$tStartAnalysis' AND '$tEndAnalysis'
                                                AND enm_cm_syncs.neid =
                                                (SELECT enm_ne.id FROM enm_ne,sites WHERE
                                                enm_ne.name='$networkElementAnalysis' AND
                                                enm_ne.siteid = sites.id AND sites.name = '$site')
                                                AND enm_cm_syncs.serverid = servers.id",
                                   'qargs' => array( 'site' )
                                  )
                            )
                     );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$tStartAnalysis", "$tEndAnalysis", true, 480, 280);

    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function syncAnalysis() {
    global $displayAnalysis,$date,$tStartAnalysis,$tEndAnalysis,$networkElementAnalysis;

    drawHeaderWithHelp("Sync Analysis", 2, "Sync_Analysis");

    $myURL = makeSelfLink();

  echo <<<EOT
    <form action="$myURL" method="get" name="timerange" id="timerange">
EOT;
  foreach ( $_GET as $name => $value ) {
    if ( $name != T_START_ANALYSIS && $name != T_END_ANALYSIS ) {
      echo "<input type=\"hidden\" name=\"$name\" value=\"$value\">\n";
    }
  }

if (! issetURLParam(T_START_ANALYSIS) ) {
    $tStartAnalysis = "$date 00:00:00";
}
if (! issetURLParam(T_END_ANALYSIS) ) {
    $tEndAnalysis = "$date 23:59:59";
}
if (! issetURLParam('network_element_analysis') ) {
    $networkElementAnalysis = "";
}
    echo <<<EOT
            <form method='GET'>
            <table border="0">
            <tr>
            <td align="right" valign="top"><b>From:</b></td>
            <td valign="top" align="left">
                <input size="20" maxlength="20" name="tstartanalysis" type="text" value="$tStartAnalysis" /></td>
            <td align="right" valign="top"><b>To:</b></td>
            <td valign="top" align="left">
                <input size="20" maxlength="20" name="tendanalysis" type="text" value="$tEndAnalysis" /></td>
            <td align="right" valign="top"><b>Network Element:</b></td>
            <td valign="top" align="left">
                <input size="20" maxlength="128" name="network_element_analysis" type="text"
                value="$networkElementAnalysis" /></td>
            <td align="right" valign="top"><b></b></td>
            <td valign="top" align="left">
                <input onclick='document.getElementById("SyncAnalysisTable").style.display = "$displayAnalysis"'
                type=submit name='run_analysis' value='FetchNEDetails' /></td>
            </tr>
            </table>
            </form>
EOT;

    if ( issetURLParam('run_analysis') ) {
        if ( valueExists($networkElementAnalysis) ) {
            echo "<br/>";
            showAnalysisDetails();
            echo "<br/>";
            syncAnalysisGraph();
            echo "<br/>";
            syncAnalysisDurationGraph();
            echo "<br/>";
            syncAnalysisMOGraph();
        } else {
            echo "<b>Please provide Network elements details</b>\n<br/>\n";
        }
    }
}

function showSyncs() {
    global $site, $date, $statsDB;

    /* CM Syncs Table */
    $where = $statsDB->where(CM_SYNC_TAB, STRT);
    $where .= " AND enm_cm_syncs.neid = enm_ne.id
                AND enm_cm_syncs.serverid = servers.id
                AND ne_types.id = enm_ne.netypeid";

    $durCol = "TIME_FORMAT(SEC_TO_TIME(ROUND(enm_cm_syncs.t_complete/1000)), '%H:%i:%s')";

    $table = SqlTableBuilder::init()
        ->name('Sync_Success')
        ->tables(array( CM_SYNC_TAB, NE_TAB, 'ne_types', SITES, SERVERS ))
        ->where($where)
        ->addColumn( 'time', 'DATE_FORMAT(enm_cm_syncs.start,"%H:%i:%s")', START )
        ->addSimpleColumn( ENM_NE, NE )
        ->addSimpleColumn( 'ne_types.name', 'NE Type' )
        ->addSimpleColumn( SERV_HOST, MSCM_INST )
        ->addSimpleColumn( 'enm_cm_syncs.type', 'Type' )
        ->addSimpleColumn( $durCol, 'Duration (hh:mm:ss)' )
        ->addSimpleColumn( 'enm_cm_syncs.n_mo', 'MOs' )
        ->addSimpleColumn( 'enm_cm_syncs.n_mo_created', 'MOs Created' )
        ->addSimpleColumn( 'enm_cm_syncs.n_mo_deleted', 'MOs Deleted' )
        ->addSimpleColumn( 'enm_cm_syncs.n_attr', 'Attributes' )
        ->addSimpleColumn( RND, MO_SEC )
        ->addSimpleColumn( 'ROUND(1000 * n_mo / (t_dps_top + t_dps_attr), 2)', 'Adjusted MO/Sec' )
        ->addSimpleColumn( 'enm_cm_syncs.t_ne_top', 'NE Topology Time (ms)' )
        ->addSimpleColumn( 'enm_cm_syncs.t_dps_top', 'DPS Topology Time (ms)' )
        ->addSimpleColumn( 'enm_cm_syncs.t_ne_attr', 'NE Attribute Time (ms)' )
        ->addSimpleColumn( 'enm_cm_syncs.t_dps_attr', 'DPS Attribute Time (ms)' )
        ->sortBy('time', DDPTable::SORT_ASC)
        ->paginate()
        ->dbScrolling()
        ->build();

    echo $table->getTableWithHeader("Sync Stats", 2, "", "", "Sync_Success");
}

function filterSelected($rows, $selected) {
    $selected = explode(',', $selected);
    $filteredData = array();
    // We need to make sure each selected value is used only once in the filtering
    // Without using array_unique() we get duplicate data
    $selected = array_unique($selected);

    foreach ( $rows as $row) {
        foreach ( $selected as $key ) {
            if ($row['ne'] == $key) {
                $filteredData[] = $row;
            }
        }
    }

    return $filteredData;
}

function showFailedSyncs() {
    global $site, $date, $rootdir, $debug;

    $selected = requestValue(SELECTED);

    $rows = array();
    $maxCount = 1000;
    $fileName = $rootdir . "/enmlogs/cpp_failed_syncs.json";
    if ( $debug ) {
        echo "<pre>showFailedSyncs opening $fileName</pre>\n";
    }
    $handle = fopen($fileName, "r");
    if ( $handle ) {
        $count = 0;
        while ( ($line = fgets($handle)) !== false && $count < $maxCount ) {
            $rows[] = json_decode($line, true);
            $count++;
        }
        fclose($handle);

        if ( !is_null($selected) ) {
            $rows = filterSelected($rows, $selected);
        }

        $table = new DDPTable("cpp_failed_syncs",
                              array(
                                  array('key' => STRT, DDPTable::LABEL => 'Sync Start'),
                                  array('key' => 'ne', DDPTable::LABEL => 'NE'),
                                  array('key' => 'error', DDPTable::LABEL => 'Error'),
                              ),
                              array('data' => $rows),
                              array(
                                  'rowsPerPage' => 25,
                                  'rowsPerPageOptions' => array(50, 100),
                                  DDPTable::CTX_MENU => array('key' => 'Filtered',
                                                     DDPTable::MULTI => true,
                                                     'menu' => array( 'plot' => 'Filter Selected (NE)' ),
                                                     'url' => makeSelfLink() . "&showfailedsyncs=1",
                                                     'col' => 'ne'
                              )
                            )
        );
        echo $table->getTableWithHeader('Failed Syncs', 1);
    }
}

function showSyncKPIPerformance() {
    global $display, $date, $tstart, $tend, $networkElement;

    drawHeaderWithHelp("Sync KPI Performance", 3, "Sync_KPI_Performance");

    $myURL = makeSelfLink();

  echo <<<EOT
    <form action="$myURL" method="get" name="timerange" id="timerange">
EOT;
  foreach ( $_GET as $name => $value ) {
    if ( $name != 'tstart' && $name != 'tend' ) {
      echo "<input type=\"hidden\" name=\"$name\" value=\"$value\">\n";
    }
  }

if (! issetURLParam('tstart') ) {
    $tstart = "$date 00:00:00";
}
if (! issetURLParam('tend') ) {
    $tend = "$date 23:59:59";
}
if (! issetURLParam('network_element') ) {
    $networkElement = "";
}
    echo <<<EOT
            <form method='GET'>
            <table border="0">
            <tr>
            <td align="right" valign="top"><b>From:</b></td>
            <td valign="top" align="left">
                <input size="20" maxlength="20" name="tstart" type="text" value="$tstart" /></td>
            <td align="right" valign="top"><b>To:</b></td>
            <td valign="top" align="left">
                <input size="20" maxlength="20" name="tend" type="text" value="$tend" /></td>
            <td align="right" valign="top"><b>Network Element:</b></td>
            <td valign="top" align="left">
                <input size="20" maxlength="128" name="network_element" type="text" value="$networkElement" /></td>
            <td align="right" valign="top"><b></b></td>
            <td valign="top" align="left">
                <input onclick='document.getElementById("SyncKPIPerformanceTable").style.display = "$display"'
                type=submit name='run' value='Update' /></td>
            </tr>
            </table>
            </form>
EOT;

    echo '<div id="SyncKPIPerformanceTable" style="display:'.$display.'">';
    getSyncKPIPerformance();
    echo '</div>';
}

function createGraphTable($instrGraphs) {
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    foreach ( $instrGraphs as $instrGraphsRow ) {
      $row = array();
      foreach ( $instrGraphsRow as $column => $title ) {
       if ($column != "DeadMscms") {
        $sqlParam = array(
            TITLE => $title,
            'type' => 'sb',
            'sb.barwidth' => 60,
            'ylabel' => "",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    SqlPlotParam::MULTI_SERIES=> SERV_HOST,
                    SqlPlotParam::WHAT_COL => array( $column => $column ),
                    'tables' => DB_TABLES,
                    'where' => "enm_cm_med_instr.siteid = sites.id AND sites.name = '%s' AND
                    enm_cm_med_instr.serverid = servers.id",
                    'qargs' => array(
                        'site',
                    )
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 320);
       } else {
        $sqlParam = array(
            TITLE => $title,
            'type' => 'sb',
            'sb.barwidth' => 60,
            'ylabel' => "",
            'useragg' => 'true',
            'persistent' => 'true',
            'forcelegend'=> 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    SqlPlotParam::MULTI_SERIES=> SERV_HOST,
                    SqlPlotParam::WHAT_COL => array( $column => $column ),
                    'tables' => "enm_dead_mscms, sites, servers",
                    'where' => "enm_dead_mscms.siteid = sites.id AND sites.name = '%s' AND
                    enm_dead_mscms.serverid = servers.id",
                    'qargs' => array(
                        'site',
                    )
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 320);
       }
      }
      $graphTable->addRow($row);
    }
    return $graphTable;
}

function appNotifHandParams() {
    return array(
        'notif_total' => array(
            TITLE => TOTAL_NOTE,
            'cols' => array(
                'notif_total' => TOTAL_NOTE
             )
        )
    );
}

function createGraphTableApplication($instances) {
    global $date;

    $instrParams = appNotifHandParams();
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($instances, null, 'th');

    foreach ( $instrParams as $instrGraphApplicationNotifParam ) {
        $row = array();
        $sqlParam = array(
            TITLE => $instrGraphApplicationNotifParam[TITLE],
            'ylabel' => "",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    SqlPlotParam::WHAT_COL => $instrGraphApplicationNotifParam['cols'],
                    'tables' => DB_TABLES,
                    'where' => "enm_cm_med_instr.siteid = sites.id AND sites.name = '%s' AND
                    enm_cm_med_instr.serverid = servers.id AND servers.hostname = '%s'",
                    'qargs' => array(
                        'site',
                        'server'
                    )
                )
            )
        );

        if (array_key_exists('type', $instrGraphApplicationNotifParam)) {
            $sqlParam['type'] = $instrGraphApplicationNotifParam['type'];
        }

        $id = $sqlParamWriter->saveParams($sqlParam);

        foreach ( $instances as $instance ) {
            $row[] = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                480,
                240,
                "server=$instance"
            );
        }
        $graphTable->addRow($row);
    }

    drawHeader("Application Notification Handling", 2, "Application_Notification_Handling");
    echo $graphTable->toHTML();
}

function showSupervision($statsDB) {
  global $site, $date;
    /* Supervision stats if available */
    $graphTable = new HTML_Table("border=0");
    $rowshowSupervision = array();
    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_cm_supervision, sites
WHERE
 enm_cm_supervision.siteid = sites.id AND sites.name = '$site' AND
 enm_cm_supervision.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_cm_supervision.type = 'CPP'");
    if ( $row[0] > 0 ) {

      $sqlParam =
        array(
              TITLE => "CPP Supervision",
              'type' => 'tsc',
              'ylabel' => "Nodes",
              'useragg' => 'true',
              'persistent' => 'true',
              'querylist' => array(
                                   array(
                                         'timecol' => 'time',
                                         SqlPlotParam::WHAT_COL => array( 'supervised' => 'Supervised',
                                                                          'subscribed' => 'Subscribed',
                                                                          'synced' => 'Synced' ),
                                         'tables' => "enm_cm_supervision, sites",
                                         'where' => "enm_cm_supervision.siteid = sites.id AND sites.name = '%s' AND
                                                    enm_cm_supervision.type = 'CPP'",
                                         'qargs' => array( 'site' )
                                         )
                                       )
              );
      $sqlParamWriter = new SqlPlotParam();
      $id = $sqlParamWriter->saveParams($sqlParam);
      $rowshowSupervision[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
      drawHeaderWithHelp("Supervision", 2, "supervision");
    }
    $graphTable->addRow($rowshowSupervision);
    echo $graphTable;
}

function showCPPSyncGraph($instrParams) {
/* changes for new graph */
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();

    foreach ( $instrParams as $instrGraphParam ) {
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                TITLE => $instrGraphParamName[TITLE],
                'ylabel' => U_CNT,
                'type' => 'sb',
                'useragg' => 'true',
                'sb.barwidth' => '60',
                'persistent' => 'true',
                'querylist' => array(
                    array(
                        'timecol' => 'time',
                        SqlPlotParam::WHAT_COL => $instrGraphParamName['cols'],
                        SqlPlotParam::MULTI_SERIES => SERV_HOST,
                        'tables' => "enm_cppsync_instr, sites, servers",
                        'where' => "enm_cppsync_instr.siteid = sites.id AND  sites.name = '%s' AND
                                   enm_cppsync_instr.serverid = servers.id",
                        'qargs' => array( 'site' )
                    )
                )
            );

            $id = $sqlParamWriter->saveParams($sqlParam);
            $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        }
    }
    plotGraphs($graphs);
}

function showMibUpgradeGraph($instrParams) {
/* changes for new graph */
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();

    foreach ( $instrParams as $instrGraphParam ) {
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                TITLE => $instrGraphParamName[TITLE],
                'ylabel' => U_CNT,
                'type' => 'sb',
                'useragg' => 'true',
                'sb.barwidth' => '60',
                'persistent' => 'true',
                'querylist' => array(
                    array(
                        'timecol' => 'time',
                        SqlPlotParam::WHAT_COL => $instrGraphParamName['cols'],
                        SqlPlotParam::MULTI_SERIES => SERV_HOST,
                        'tables' => DB_TABLES,
                        'where' => "enm_cm_med_instr.siteid = sites.id AND  sites.name = '%s' AND
                                   enm_cm_med_instr.serverid = servers.id",
                        'qargs' => array( 'site' )
                    )
                )
            );

            $id = $sqlParamWriter->saveParams($sqlParam);
            $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        }
    }
    plotGraphs($graphs);
}

function addLinks($notifRecTables, $instances) {
    global $debug, $date, $site, $rootdir, $statsDB, $SG;

    $analysisURL = makeLink(CM_MED, 'Sync Analysis', array('syncanalysis' => '1'));
    $syncURL = makeLink(CM_MED, 'Successful Stats', array('showsyncs' => '1'));
    $countURL = makeLink(CM_MED, 'Successful Counts', array('showcounts' => '1'));
    $syncsLinks = array($analysisURL,  $syncURL, $countURL);

    $failedFile = $rootdir . "/enmlogs/cpp_failed_syncs.json";
    if ( $debug ) {
        echo "<pre>Checking for $failedFile</pre>\n";
    }

    if ( file_exists($failedFile) ) {
        array_push($syncsLinks, makeLink(CM_MED, 'Failed Syncs', array('showfailedsyncs' => '1' ) ) );
    }

    $jmxLinks = array(
        "<a href=\"" . makeGenJmxLink("mscm") . "\">MSCM</a>",
        "<a href=\"" . makeGenJmxLink("$SG") . "\">$SG</a>"
    );

    $linkList =  array(
                    "Syncs" . makeHTMLList($syncsLinks),
                    "Generic JMX" . makeHTMLList($jmxLinks),
                    makeLink( CM_MED, 'Add-Node Stats', array( 'showaddnodestats' => '1' ) ),
                    makeLink( '/TOR/dps.php', 'MSCM DPS Instrumentation', array(SERVERS => implode( ",", $instances )) )
                 );

    array_push($linkList, routesLink());
    if ( ! is_null($notifRecTables) ) {
        array_push($linkList, makeAnchorLink('notifrec', 'Notifications Received Details'));
    }

    /*Notification Analysis Stats */
    $row = $statsDB->queryRow("
SELECT
    COUNT(*)
FROM
    enm_mscmnotification_logs, sites
WHERE
    enm_mscmnotification_logs.siteid = sites.id AND sites.name = '$site' AND
    enm_mscmnotification_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ($row[0] > 0) {
        array_push($linkList, makeLink('/TOR/notif_analysis.php', 'Notification Analysis'));
    }

    echo makeHTMLList($linkList);
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site, $rootdir;

    /* Daily Summary table */
    drawHeader("Daily Totals", 2, "Daily_Totals");
    drawHeader("Full Syncs", 3, "");
    getSyncTotals('FULL');
    drawHeader("Delta Syncs", 3, "");
    getSyncTotals('DELTA');

    $srv = enmGetServiceInstances($statsDB, $site, $date, "mscm");
    $srvIds = array_values($srv);
    getDailyNotifTotals($srvIds);

    $notifRecTables = getNotifRecTables($statsDB, $site, $date);
    $instances = getInstances("enm_cm_med_instr");
    showSyncKPIPerformance();

    /* Links */
    addLinks($notifRecTables, $instances);

    showSupervision($statsDB);

    /* This is to support both new syncStatus file with Service group and old file with Hardcoded instance type. */
    /* As still old date data having syncStatus file with hardcoded instance type */
    $seriesFile = $rootdir . "/cm/syncStatus_mscm.json";
    if ( file_exists($seriesFile) ) {
        showSyncStatusEvents( 'mscm' );
    } else {
        showSyncStatusEvents( 'cpp' );
    }

    $instrGraph = array(
        array(
            'dpsCounterForSuccessfulSync' => 'DPS Successful Full Syncs',
            'dpsSuccessfulDeltaSync'=> 'DPS Successful Delta Syncs'
        ),
        array(
            'dpsNumberOfFailedSyncs' => 'DPS Failed Full Syncs',
            'dpsFailedDeltaSync' => 'DPS Failed Delta Syncs'
        ),
        array(
            'numberOfFailedSyncs' => 'NE Failed Full Syncs',
            'DeadMscms' => 'CPP MscmHealthcheck'
        )
    );

    drawHeaderWithHelp("Instrumentation", 2, "Instrumentation");
    $graphTable = createGraphTable($instrGraph);
    echo $graphTable->toHTML();


    $queueNames = array('NetworkElementNotifications', 'ClusteredMediationServiceConsumerCM0');
    plotQueues( $queueNames );

    getRouteInstrTable( $srvIds );

    createGraphTableApplication($instances);

    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_cppsync_instr, sites
WHERE
 enm_cppsync_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_cppsync_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ( $row[0] > 0 ) {
      drawHeader(SSI, 1, "Software_Sync_Invocations");

      $instrGraphSoftwareSyncParams = array(
          array('softwareSyncInvocations' => array(
              TITLE => SSI,
              'cols' => array ('softwareSyncInvocations' => SSI)
          )
          ),
          array('numberOfSoftwareSyncWithError' => array(
              TITLE => 'Number Of Software Sync With Error',
              'cols' => array ('numberOfSoftwareSyncWithError' => 'Number Of Software Sync With Error')
          )
          ),
          array('numberOfSoftwareSyncWithModelIdCalculation' => array(
              TITLE => 'Number Of Software Sync With ModelIdCalculation',
              'cols' => array ('numberOfSoftwareSyncWithModelIdCalculation' =>
                               'Number Of Software Sync With ModelIdCalculation')
          )
          ),
          array('numberOfSoftwareSyncWithoutModelIdCalculation' => array(
              TITLE => 'Number Of Software Sync Without ModelIdCalculation',
              'cols' => array ('numberOfSoftwareSyncWithoutModelIdCalculation' =>
                               'Number Of Software Sync Without ModelIdCalculation')
          )
          )
      );

        showCPPSyncGraph($instrGraphSoftwareSyncParams);

        drawHeader("Mib Upgrade", 1, "Mib_Upgrade");

        $instrGraphSyncMibUpgradeParams = array(
            array('numberOfSuccessfulMibUpgrade' => array(
                                                        TITLE => 'Number of MIB upgrade performed successfully',
                                                        'cols' => array ('numberOfSuccessfulMibUpgrade' =>
                                                        'Number of MIB upgrade performed successfully')
                                                    )
            ),
            array('numberOfFailedMibUpgrade' => array(
                                                        TITLE => 'Number of failed MIB upgrade',
                                                        'cols' => array ('numberOfFailedMibUpgrade' =>
                                                        'Number of failed MIB upgrade')
                                                    )
            )
        );

        showMibUpgradeGraph($instrGraphSyncMibUpgradeParams);
    }
    if ( ! is_null($notifRecTables) ) {
        echo "<H2 id=\"notifrec\"></H2>\n";
        echo $notifRecTables[0]->getTableWithHeader("Notifications Received Details", 1, "", "", "");
        echo $notifRecTables[1]->getTableWithHeader("Top Notification Nodes", 1, "", "");
    }
}

if ( issetURLParam('run') ) {
    $tstart = requestValue('tstart');
    $tend = requestValue('tend');
    $networkElement = requestValue('network_element');
    $display = 'block';
}
if ( issetURLParam('run_analysis') ) {
    $tStartAnalysis = requestValue(T_START_ANALYSIS);
    $tEndAnalysis = requestValue(T_END_ANALYSIS);
    $networkElementAnalysis = requestValue('network_element_analysis');
    $displayAnalysis = 'block';
}

if (issetURLParam('syncanalysis')) {
    syncAnalysis();
} elseif (issetURLParam('showsyncs')) {
    showSyncs();
} elseif (issetURLParam('showfailedsyncs')) {
    showFailedSyncs();
} elseif (issetURLParam('showcounts')) {
    getTotalSyncCounts();
} elseif (issetURLParam('showaddnodestats')) {
    getAddNodeStats();
} elseif (issetURLParam('shownodeindex')) {
    showNodeIndex( 'mscm' );
} elseif (issetURLParam(ACTION)) {
    $selected = requestValue(SELECTED);
    $action = requestValue(ACTION);

    if ( $action === 'plotnotifrec' ) {
        plotNotifRec($site, $date, $selected);
    } elseif ( $action === 'plotnotiftop' ) {
        plotNotifTop($date, $selected);
    } elseif ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} else {
    mainFlow($statsDB);
}
include_once PHP_ROOT . "/common/finalise.php";
