<?php
$pageTitle = "Heap Usage";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function main() {
    drawHeader( 'Heap Usage', 1, 'heapUsageHelp' );
    $modelledGraph = new ModelledGraph('ENIQ/engine_heap_memory');
    plotgraphs( array( $modelledGraph->getImage() ) );
    $modelledGraph = new ModelledGraph('ENIQ/scheduler_heap_memory');
    plotgraphs( array( $modelledGraph->getImage() ) );
}

main();
include_once PHP_ROOT . "/common/finalise.php";
