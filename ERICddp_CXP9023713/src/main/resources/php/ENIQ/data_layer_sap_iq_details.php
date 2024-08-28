<?php

$pageTitle = "Data Warehouse IQ Stats";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function plotIqGraphSet( $hostname, $groupName ) {
    $params = array( 'hostname' => $hostname );
    $graphs = array();
    $iqModelledGraphSet = new ModelledGraphSet( 'ENIQ/data_layer_sap_iq' );
    $iqGraphs = $iqModelledGraphSet->getGroup( $groupName );
    foreach ( $iqGraphs['graphs'] as $modelledGraph ) {
        $graphs[] = $modelledGraph->getImage( $params, null, null, 550, 320 );
    }
    plotgraphs( $graphs );
}

function showIqTempDiskUtilizationGraphSet( $hostname ) {
    drawHeader( 'Data Warehouse IQ Temp Usage In Percentage', 2, 'iqTempUsageHelp' );
    drawHeader( $selected, 3, '' );
    plotIqGraphSet( $hostname, 'temp_db_graphs' );
    echo addLineBreak();
}

function showIqCacheStatasGraphSet( $hostname ) {
    drawHeader( 'Data Warehouse IQ Cache Statistics', 2, 'iqCacheStatsHelp' );
    drawHeader( $hostname, 3, '' );
    plotIqGraphSet( $hostname, 'cache_stats_graphs' );
}

function showIqMainDiskUtilizationGraphSet( $hostname ) {
    drawHeader( 'Data Warehouse IQ Main & Sysmain Usage In Percentage', 2, 'iqMainSysMainUsageHelp' );
    drawHeader( $hostname, 3, '' );
    plotIqGraphSet( $hostname, 'main_sysmain_db_graphs' );
}

function showIqUserStatsGraphSet( $hostname ) {
    drawHeader( 'Data Warehouse IQ User Statistics', 2, 'iqUserStatsHelp' );
    drawHeader( $hostname, 3, '' );
    plotIqGraphSet( $hostname, 'user_stats_graphs' );
}

function showIqLargeMemoryStatsGraphSet( $hostname ) {
    drawHeader( 'Data Warehouse IQ Large Memory Statistics', 2, 'iqMemoryStatsHelp' );
    drawHeader( $hostname, 3, '' );
    plotIqGraphSet( $hostname, 'large_memory_stats_graphs' );
}

function mainFlow() {
    global $statsDB;

    if ( $statsDB->hasData( 'eniq_data_layer_sap_iq' ) ) {
        $plotType = requestValue('plot');
        if ( ! is_null($plotType) ) {
            $selected = requestValue('selected');
            if ( $plotType == 'diskTempUtilInstance' ) {
                showIqTempDiskUtilizationGraphSet( $selected );
            } elseif ( $plotType == 'diskMainUtilInstance' ) {
                showIqMainDiskUtilizationGraphSet( $selected );
            } elseif ( $plotType == 'cacheInstance' ) {
                showIqCacheStatasGraphSet( $selected );
            } elseif ( $plotType == 'memoryInstance' ) {
                showIqLargeMemoryStatsGraphSet( $selected );
            } elseif ( $plotType == 'userStatsInstance' ) {
                showIqUserStatsGraphSet( $selected );
            }
        }
    }
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
