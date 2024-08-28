<?php
$pageTitle = "FM Emergency";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function mainFlow() {
    global $site;

    $graphs = array();
    $models = array(
        'TOR/fm/fm_emergency_supervised_nodes',
        'TOR/fm/fm_emergency_alarms_received',
        'TOR/fm/fm_emergency_failover_nodes',
        'TOR/fm/fm_emergency_heartbeat_nodes',
        'TOR/fm/fm_emergency_nbi_alarms'
    );

    foreach ( $models as $mod ) {
        $modelledGraph = new ModelledGraph($mod);
        $graphs[] = $modelledGraph->getImage();
    }

    drawHeader("Instrumentation Graphs", 1, 'instrGraphs' );
    plotgraphs( $graphs );

}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

