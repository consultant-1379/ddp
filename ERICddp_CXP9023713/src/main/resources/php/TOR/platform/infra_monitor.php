<?php
$pageTitle = "Infrastructure Monitor";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function mainFlow() {
    drawHeader( "Infrastructure Monitor", 1, "infra_monitors" );
    $table = new ModelledTable( 'TOR/platform/infrastructure_monitor', 'infra_monitors' );
    echo $table->getTable();
    echo addLineBreak();

    $graphs = array();
    $graphs[0] = '<h3>Network Ingress</h3>';
    $graphs[1] = '<h3>Shared File Storage Write</h3>';
    getGraphsFromSet( 'networkingress', $graphs, 'TOR/platform/infra_monitor', null, 640, 320 );
    drawHeader( "Infrastructure Monitor Graphs", 1, "infra_monitor" );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( "Block Storage", 1, "infra_blockstorage" );
    $modelledGraph = new ModelledGraph("TOR/platform/infra_supervision");
    $graphs[] = $modelledGraph->getImage();

    $modelledGraph = new ModelledGraph("TOR/platform/infra_messages");
    $graphs[] = $modelledGraph->getImage();

    $modelledGraph = new ModelledGraph("TOR/platform/infra_bufferednotifications");
    $graphs[] = $modelledGraph->getImage();

    $modelledGraph = new ModelledGraph("TOR/platform/infra_boltproctime");
    $graphs[] = $modelledGraph->getImage();
    plotGraphs($graphs);
}

mainFlow();

include PHP_ROOT . "/common/finalise.php";

