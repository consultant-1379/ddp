<?php
$pageTitle = "Db Connection Information";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function main() {
    drawHeader( 'Database Connections Statistics', 2, 'NumberOfDwhdbConnectionsHelp' );
    $modelledGraph = new ModelledGraph('ENIQ/database_dwhdb_count');
    plotgraphs( array( $modelledGraph->getImage() ) );
    $modelledGraph = new ModelledGraph('ENIQ/database_repdb_count');
    plotgraphs( array( $modelledGraph->getImage() ) );
}

main();
include_once PHP_ROOT . "/common/finalise.php";
