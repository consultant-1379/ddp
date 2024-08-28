<?php

$pageTitle = "SNMP CM Mediation";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/TOR/cm/cm_functions.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

const ACT = 'action';
const INST = 'Instance';
const NET = 'NE Type';
const NET_NAME = 'ne_types.name';
const STN_TBL = 'enm_stn_cmsync';
const MINI_TBL = 'enm_minilink_cmsync';
const MO_SYNC = 'MOs Synced';
const SYNC_TYPE = 'syncType';
const SELF = '/TOR/cm/mssnmpcm.php';
const ML_HEADING = 'MINI-LINK Full Syncs';
const STN_HEADING = 'STN Full Syncs';
const MSSNMPCM = 'mssnmpcm';

function showSupervision($statsDB, $instType) {
    global $site, $date;

    /* Supervision stats if available */
    $hasData = $statsDB->hasData( 'enm_cm_supervision', 'time', false, "enm_cm_supervision.type = '$instType'" );
    
    if ( $hasData ) {
        $where = "enm_cm_supervision.siteid = sites.id AND sites.name = '%s' AND enm_cm_supervision.type = '$instType'";
        $tables = array('enm_cm_supervision', StatsDB::SITES);
        $cols = array( 'supervised' => 'Supervised', 'synced' => 'Synced' );

        drawHeader("Supervision", 1, "supervision");

        $sqlParam = SqlPlotParamBuilder::init()
              ->title("SNMP Supervision")
              ->type('tsc')
              ->yLabel('Nodes')
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $cols,
                  $tables,
                  $where,
                  array('site')
              )
              ->build();

        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
    }
}

function showSummaryTable( $table, $cols, $title, $tableName ) {
    global $site, $date;

    $where = "$table.siteid = sites.id AND
              sites.name = '$site' AND
              $table.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              $table.neid = enm_ne.id AND
              $table.serverid = servers.id AND
              ne_types.id = enm_ne.netypeid
              GROUP BY $table.serverid, ne_types.name";

    $tables = array( "$table", 'enm_ne', StatsDB::SITES, StatsDB::SERVERS, 'ne_types');
    
    $table = SqlTableBuilder::init()
        ->name($tableName)
        ->tables($tables)
        ->where($where)
        ->addColumn('inst', SqlPlotParam::SERVERS_HOSTNAME, INST);

    foreach ($cols as $key => $value) {
        $table->addSimpleColumn($key, $value);
    }
    $table->sortBy('inst', DDPTable::SORT_ASC);
    echo drawHeader($title, 1, $tableName);
    echo $table->build()->getTable();
}

function miniLinkCols() {
    return array(
               NET_NAME => NET,
               'COUNT(*)' => '# Syncs',
               'SEC_TO_TIME(ROUND(AVG(enm_minilink_cmsync.duration/1000),0))' => 'Avg Duration (HH:MM:SS)',
               'SEC_TO_TIME(ROUND(MAX(enm_minilink_cmsync.duration)/1000,0))' => 'Max Duration (HH:MM:SS)',
               'ROUND(AVG((enm_minilink_cmsync.mo_synced/enm_minilink_cmsync.duration)*1000),1)' => 'Avg MOs/sec',
               'SUM(enm_minilink_cmsync.mo_createdUpdated)' => 'Total MOs Created/Updated',
               'SUM(enm_minilink_cmsync.mo_deleted)' => 'Total MOs Deleted',
               'SUM(enm_minilink_cmsync.mo_synced)' => 'Total MOs Synced',
               'SEC_TO_TIME(ROUND(AVG(enm_minilink_cmsync.cmDataRetrievalTime/1000),0))' =>
               'Avg Data Retreival Time (HH:MM:SS)',
               'SEC_TO_TIME(ROUND(AVG(enm_minilink_cmsync.cmDataTransformTime/1000),0))' =>
               'Avg Data Transformation Time (HH:MM:SS)',
               'SEC_TO_TIME(ROUND(AVG(enm_minilink_cmsync.cmDataWriterTime/1000),0))' =>
               'Avg Data Writing Time (HH:MM:SS)'
           );
}

function stnCols() {
    return array(
               NET_NAME => NET,
               'COUNT(*)' => ' # Syncs',
               'SEC_TO_TIME(ROUND(AVG(enm_stn_cmsync.duration/1000),0))' => ' Avg Duration',
               'SEC_TO_TIME(ROUND(MAX(enm_stn_cmsync.duration)/1000,0))' => 'Max Duration',
               'ROUND(AVG((enm_stn_cmsync.num_mo/enm_stn_cmsync.duration)*1000),1)' => 'Avg MOs/sec',
               'SUM(enm_stn_cmsync.num_mo)' => 'Total MOs',
           );
}

function getCols( $table ) {
    if ( $table == STN_TBL ) {
        return array(
                    'enm_ne.name' => 'Network Element',
                    SqlPlotParam::SERVERS_HOSTNAME => INST,
                    NET_NAME => NET,
                    "$table.duration" => 'Duration',
                    "$table.num_mo" => 'MOs',
                    "($table.num_mo/$table.duration)*1000" => 'MOs/sec'
                );
    } elseif ( $table == MINI_TBL ) {
        return array(
                    'enm_ne.name' => 'Network Element',
                    SqlPlotParam::SERVERS_HOSTNAME => INST,
                    NET_NAME => NET,
                    "$table.duration" => 'Duration',
                    "$table.mo_synced" => MO_SYNC,
                    "$table.numberOfCliMOsSynched" => 'CLI MOs Synced',
                    "$table.mo_createdUpdated" => 'MOs Created/Updated',
                    "$table.mo_deleted" => 'MOs Deleted',
                    "($table.mo_synced/$table.duration)*1000" => 'MOs/sec',
                    "$table.cmDataRetrievalTime" => 'SNMP Data Retreival Time',
                    "$table.cliDataRetrievalTime" => 'CLI Data Retrieval Time',
                    "$table.cmDataTransformTime" => 'Data Transformation Time',
                    "$table.cmDataWriterTime" => 'Data Writing Time',
                    "$table.model" => 'Model'
                );
    }
}

function showSyncs( $table, $title, $filter = null ) {
    global $site, $date, $webargs;

    $where = "$table.siteid = sites.id AND
              sites.name = '$site' AND
              $table.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              $table.neid = enm_ne.id AND
              $table.serverid = servers.id AND
              ne_types.id=enm_ne.netypeid";

    if ( $filter ) {
        $filter = str_replace(',', '\', \'', $filter);
        $filter = "'" . $filter . "'";
        $where .= " AND ne_types.name IN ($filter)";
    }

    $tables = array($table, 'enm_ne', 'ne_types', StatsDB::SITES, StatsDB::SERVERS);

    $cols = getCols($table);
    
    $sqlTable = SqlTableBuilder::init()
        ->name($table)
        ->tables($tables)
        ->where($where)
        ->addColumn('time', "$table.time", 'Time', DDPTable::FORMAT_TIME)
        ->addHiddenColumn('id', NET_NAME);

    foreach ($cols as $key => $value) {
        $sqlTable->addSimpleColumn($key, $value);
    }
    $type = requestValue(SYNC_TYPE);
    $sqlTable->paginate()->dbScrolling()->sortBY('time', DDPTable::SORT_ASC);
    $sqlTable->ctxMenu(
        ACT,
        true,
        array( 'filter' => 'Filter by NE Type'),
        fromServer(SELF) . "?" . $webargs . "&syncType=$type",
        'id'
    );

    echo drawHeader($title, 1, $table);
    echo $sqlTable->build()->getTable();
}

function getInstrParams() {
    return array(
            array(
                'startSupervision' => array(
                                            SqlPlotParam::TITLE => 'Start Supervision',
                                            'cols' => array('startSupervision' => 'Start Supervision')
                ),
                'stoppedSupervision' => array(
                                        SqlPlotParam::TITLE => 'Stopped Supervision',
                                        'type' => 'sb',
                                        'cols' => array('stoppedSupervision' => 'Stopped Supervision')
                )
            ),
            array(
                'successfullSync' => array(
                                        SqlPlotParam::TITLE => 'Successful Syncs',
                                        'cols' => array('successfullSync' => 'Successful Syncs')
                ),
                'failedSyncs' => array(
                                    SqlPlotParam::TITLE => 'Failed Syncs',
                                    'cols' => array('failedSyncs' => 'Failed Syncs')
                )
            ),
            array(
                'mosSynced' => array(
                                SqlPlotParam::TITLE => MO_SYNC,
                                'cols' => array('mosSynced' => MO_SYNC)
                )
            )
    );
}

function plotInstrGraphs($instrParams) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    $tables = array('enm_mssnmpcm_instr', StatsDB::SITES, StatsDB::SERVERS);
    $where = "enm_mssnmpcm_instr.siteid = sites.id AND sites.name = '%s' AND
              enm_mssnmpcm_instr.serverid = servers.id";

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = SqlPlotParamBuilder::init()
                    ->title($instrGraphParamName[SqlPlotParam::TITLE])
                    ->type('sb')
                    ->barwidth(60)
                    ->yLabel('')
                    ->makePersistent()
                    ->forceLegend()
                    ->addQuery(
                        SqlPlotParam::DEFAULT_TIME_COL,
                        $instrGraphParamName['cols'],
                        $tables,
                        $where,
                        array('site'),
                        SqlPlotParam::SERVERS_HOSTNAME
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

    $serviceGrp = MSSNMPCM;
    $jmxLink = makeFullGenJmxLink($serviceGrp, 'Generic Jmx');
    $links[] = $jmxLink;

    $miniLinks = array();

    if ( $statsDB->hasData(MINI_TBL) ) {
        $miniLink = makeLink(SELF, ML_HEADING, array(SYNC_TYPE => 'mini') );
        $miniLinks[] = $miniLink;
    }
    if ( $statsDB->hasData('enm_cmwriter_minilink_indoor') ) {
        $minilinkIndoor = makeLink(
            '/TOR/cm/mini_link_crud_operations.php',
            'MINI LINK Indoor Crud',
            array(SYNC_TYPE => 'mini')
        );
        $miniLinks[] = $minilinkIndoor;
    }
    if ( $statsDB->hasData('enm_cmwriter_minilink_outdoor') ) {
        $minilinkOutdoor = makeLink(
            '/TOR/cm/mini_link_outdoor_crud_operations.php',
            'MINI LINK Outdoor Crud',
            array(SYNC_TYPE => 'mini')
        );
        $miniLinks[] = $minilinkOutdoor;
    }
    if ( $statsDB->hasData('enm_sync_status_changes') ) {
        $miniLink = makeLink(SELF, 'Sync Status Changes', array(SYNC_TYPE => 'sync'));
        $miniLinks[] = $miniLink;
    }
    if ( $statsDB->hasData('enm_cm_snmp_node_heartbeat_status') ) {
        $miniLink = makeLink(SELF, 'CM SNMP Node Heartbeat Status Change', array(SYNC_TYPE => 'heartbeat'));
        $miniLinks[] = $miniLink;
    }
    if ( $statsDB->hasData('enm_minilink_failed_syncs_summary') ) {
        $miniLink = makeLink(SELF, 'MINI-LINK Failed Syncs Summary', array(SYNC_TYPE => 'fail'));
        $miniLinks[] = $miniLink;
    }
    if ( !empty($miniLinks) ) {
        $links[] = "MINI-LINK" . makeHTMLList($miniLinks);
    }
    if ( $statsDB->hasData(MINI_TBL) ) {
        $cols = miniLinkCols();
        showSummaryTable( MINI_TBL, $cols, 'MINI-LINK Full Syncs Summary', 'minilink_table' );
    }

    $stnLinks = array();
    if ( $statsDB->hasData(STN_TBL) ) {
        $stnLink = makeLink( SELF, STN_HEADING, array(SYNC_TYPE => 'stn') );
        $stnLinks[] = $stnLink;
        $cols = stnCols();
        showSummaryTable( STN_TBL, $cols, 'STN Full Syncs Summary', 'stn_table' );
    }

    if ( $statsDB->hasData('enm_mssnmpcm_instr') ) {
        $stnInstrLink = makeLink(SELF, 'STN Instrumentation', array('instrType' => 'stn'));
        $stnLinks[] = $stnInstrLink;
    }
    if ( $statsDB->hasData( 'enm_mssnmpcm_eci_syncstat' ) ) {
        $stnSynstatLink = makeLink('/TOR/cm/mssnmpcm_eci_syncstat.php', 'ECI NBI Mediation');
        $links[] = $stnSynstatLink;
    }
    if ( !empty($stnLinks) ) {
        $links[] = "STN" . makeHTMLList($stnLinks);
    }
    echo makeHTMLList($links);

    showSupervision($statsDB, 'SNMP');

    showSyncStatusEvents( MSSNMPCM );

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, $serviceGrp);
    $serverIdsArr = array_values($processingSrv);

    getRouteInstrTable( $serverIdsArr );
}

$syncType = requestValue(SYNC_TYPE);
$instrType = requestValue('instrType');

if ( issetURLParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ( $action === 'filter' ) {
        if ( $syncType === 'stn' ) {
            showSyncs( STN_TBL, STN_HEADING, $selected );
        } elseif ( $syncType === 'mini' ) {
            showSyncs( MINI_TBL, ML_HEADING, $selected );
            $table = new ModelledTable( "TOR/cm/enm_cm_snmp_sync_failures", 'syncfailures' );
            echo $table->getTableWithHeader("SNMP Sync Failures");
        }
    } elseif ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} elseif ( $syncType === 'stn' ) {
    showSyncs( STN_TBL, STN_HEADING );
} elseif ( $syncType === 'mini' ) {
    showSyncs( MINI_TBL, ML_HEADING );
    $table = new ModelledTable( "TOR/cm/enm_cm_snmp_sync_failures", 'syncfailures' );
    echo $table->getTableWithHeader("SNMP Sync Failures");
} elseif ( $syncType === 'sync' ) {
    $table = new ModelledTable( "TOR/cm/enm_sync_status_changes", 'syncStatus' );
    echo $table->getTableWithHeader("Sync Status Changes");
} elseif ( $syncType === 'heartbeat' ) {
    $table = new ModelledTable( "TOR/cm/enm_cm_snmp_node_heartbeat_status", 'heartbeatStatus' );
    echo $table->getTableWithHeader("CM SNMP Node Heartbeat Status Change");
} elseif ( $syncType === 'fail' ) {
    $table = new ModelledTable( "TOR/cm/enm_minilink_failed_syncs_summary", 'failSyncSummary' );
    echo $table->getTableWithHeader("MINI-LINK Failed Syncs Summary");
} elseif ( $instrType === 'stn') {
    drawHeader("Instrumentation", 1, "instrHelp");
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($instrGraphParams, 'cmserv_clistatistics_instr');
} elseif ( issetURLParam('shownodeindex') ) {
    showNodeIndex( MSSNMPCM );
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
