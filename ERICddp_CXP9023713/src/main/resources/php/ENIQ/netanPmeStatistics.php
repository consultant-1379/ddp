<?php

$pageTitle = 'NetAn PME Details';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function main() {
    drawHeader( 'PM Explorer Statistics', 1, 'pmExplorerHelp');
    drawHeader( 'Report Metrics', 2, 'reportMatricesHelp');
    $modelledGraph = new ModelledGraph('ENIQ/netanNumberOfReportsCreated');
    plotgraphs( array( $modelledGraph->getImage() ) );
    drawHeader( 'Most Frequently Used Measure Type', 3, 'netanMostFrequentMeasureTypeHelp' );
    $table = new ModelledTable('ENIQ/netanMostFrequentMeasureType', 'netanMostFrequentMeasureType');
    echo $table->getTable();
    drawHeader( 'Most Frequently Queried MOs', 3, 'netanMostFrequentQueriedMoHelp' );
    $table = new ModelledTable('ENIQ/netanMostFrequentQueriedMo', 'netanMostFrequentQueriedMo');
    echo $table->getTable();
    drawHeader( 'Query Criteria', 3, 'netanQueryCriteriaHelp' );
    $table = new ModelledTable('ENIQ/netanQueryCriteria', 'netanQueryCriteria');
    echo $table->getTable();
    echo addLineBreak();
    $modelledGraph = new ModelledGraph('ENIQ/netanNumberOfQueriesTriggered');
    plotgraphs( array( $modelledGraph->getImage() ) );
    echo addLineBreak();
    drawHeader( 'Collection Metrics', 2, 'collectionMatricesHelp');
    $modelledGraph = new ModelledGraph('ENIQ/netanNumberOfCollection');
    plotgraphs( array( $modelledGraph->getImage() ) );
    drawHeader( 'Static Collection Summary', 3, 'netanStaticCollectionHelp' );
    $table = new ModelledTable('ENIQ/netanStaticCollection', 'netanStaticCollection');
    echo $table->getTable();
    drawHeader( 'Dynamic Collection Summary', 3, 'netanDynamicCollectionHelp' );
    $table = new ModelledTable('ENIQ/netanDynamicCollection', 'netanDynamicCollection');
    echo $table->getTable();
    echo addLineBreak();
    drawHeader( 'Custom KPI Count', 2, 'AlarmStateHelp' );
    $modelledGraph = new ModelledGraph('ENIQ/netanCustomKPICount');
    plotgraphs( array( $modelledGraph->getImage() ) );
}

main();
include_once PHP_ROOT . "/common/finalise.php";
