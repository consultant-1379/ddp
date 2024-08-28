<?php

$pageTitle = "Data Warehouse IQ Stats";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

const VIEW_PARAM = 'view';
const DISK_VIEW = 'disk';
const CACHE_VIEW = 'cache';
const USER_VIEW = 'user';
const TIMESTAMP = 'timeStamp';
const DATA_LAYER_SAP_IQ_DETAILS = '/ENIQ/data_layer_sap_iq_details.php';
const ENIQ_DATA_LAYER_SAP_IQ = 'eniq_data_layer_sap_iq';
const DATA_LAYER_IQ = '/ENIQ/data_layer_sap_iq.php';

function getIQLatestTimeStamp() {
    global $statsDB, $site, $date;

    $statsDB->query("
SELECT
    MAX(eniq_data_layer_sap_iq.time)
FROM
    eniq_data_layer_sap_iq
JOIN
    sites ON eniq_data_layer_sap_iq.siteid = sites.id
JOIN
    servers ON eniq_data_layer_sap_iq.serverid = servers.id
WHERE
    sites.name = '$site' AND
    eniq_data_layer_sap_iq.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY
    servers.hostname
    ");
    $timeStampArray = array();
    while ( $row = $statsDB->getNextRow() ) {
        $timeStampArray[] = $row[0];
    }
    return implode( "','", $timeStampArray );
}

function getIQVersionTable( $statsDB ) {
    global $site, $date;

    $rowData = array();
    $statsDB->query("
SELECT
    eniq_data_layer_sap_iq_versions.name AS sapIqVersion,
    MAX(time)
FROM
    eniq_data_layer_sap_iq
JOIN
    sites ON eniq_data_layer_sap_iq.siteid = sites.id
JOIN
    eniq_data_layer_sap_iq_versions ON eniq_data_layer_sap_iq.sapIqVersionId = eniq_data_layer_sap_iq_versions.id
WHERE
    eniq_data_layer_sap_iq.sapIqVersionId IS NOT NULL AND
    time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    sites.name = '$site';
    ");
    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }
    $table = new DDPTable(
        'sapIqVersionTable',
        array(
            array('key' => 'sapIqVersion', 'label' => 'Data Warehouse IQ Version'),
        ),
        array('data' => $rowData)
    );
    echo $table->getTable();
}

function getTempDiskUtiliztionTable( $params ) {
    $iqDiskTable = new ModelledTable(
        'ENIQ/sap_iq_disk_utilization_temp',
        'iqTempDiskTable',
        $params
    );
    echo $iqDiskTable->getTableWithHeader( 'Data Warehouse IQ Temp Disk Utilization', 3 );
}

function getCacheStatsTable( $params ) {
    $iqPerfTable = new ModelledTable(
        'ENIQ/sap_iq_cache_stats',
        'iqCacheStatsTable',
        $params
    );
    echo $iqPerfTable->getTableWithHeader( 'Data Warehouse IQ Cache Statistics', 3 );
    echo addLineBreak();
}

function getMainSysDiskUtiliztionTable( $params ) {
    $iqCatlogTable = new ModelledTable(
        'ENIQ/sap_iq_disk_utilization_main_sys',
        'iqMainSysDiskTable',
        $params
    );
    echo $iqCatlogTable->getTableWithHeader( 'Data Warehouse IQ Main & Sysmain Disk Utilization', 3 );
    echo addLineBreak();
}

function getUserStatsTable( $params ) {
    $iqUserStatsTable = new ModelledTable(
        'ENIQ/sap_iq_user_stats',
        'iqUserStatsTable',
        $params
    );
    echo $iqUserStatsTable->getTableWithHeader( 'Data Warehouse Users Statistics', 3 );
    echo addLineBreak();
}

function getLargeMemoryStatsTable( $params ) {
    $iqPerfTable = new ModelledTable(
        'ENIQ/sap_iq_large_memory_stats',
        'iqMemoryStatsTable',
        $params
    );
    echo $iqPerfTable->getTableWithHeader( 'Data Warehouse IQ Large Memory Statistics', 3 );
    echo addLineBreak();
}

function mainFlow() {
    global $statsDB;

    drawHeader( 'Data Warehouse IQ Stats', 2, '' );
    $links = array();
    if ( $statsDB->hasData( ENIQ_DATA_LAYER_SAP_IQ ) ) {
        $links[] = makeLink( DATA_LAYER_IQ, 'Disk Utilization', array(VIEW_PARAM => DISK_VIEW) );
        $links[] = makeLink( DATA_LAYER_IQ, 'Cache Statistics', array(VIEW_PARAM => CACHE_VIEW) );
        $links[] = makeLink( DATA_LAYER_IQ, 'User Statistics', array(VIEW_PARAM => USER_VIEW) );
        $view = requestValue(VIEW_PARAM);
        getIQVersionTable( $statsDB );
        echo makeHTMLList($links);
        $timeStamp = getIQLatestTimeStamp();
        $url = makeURL( DATA_LAYER_SAP_IQ_DETAILS );
        $params = array( ModelledTable::URL => $url, TIMESTAMP => $timeStamp );
        if ( $view === DISK_VIEW ) {
            getMainSysDiskUtiliztionTable( $params );
            getTempDiskUtiliztionTable( $params );
        } elseif ( $view === CACHE_VIEW ) {
            getCacheStatsTable( $params );
            getLargeMemoryStatsTable( $params );
        } elseif ( $view == USER_VIEW ) {
            getUserStatsTable( $params );
        }
    }
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
