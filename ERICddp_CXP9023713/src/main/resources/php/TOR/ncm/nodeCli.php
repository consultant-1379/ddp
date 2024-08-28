<?php

$pageTitle = "Node Cli";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function drawGraphGroup($group) {
    $graphs = array();
    foreach ( $group['graphs'] as $modelledGraph ) {
        $graphs[] = array( $modelledGraph->getImage(null, null, null, 640, 240) );
    }
    plotgraphs( $graphs );
}

function mainFlow() {
    $nodecliGraphSet = new ModelledGraphSet('TOR/ncm/enm_ncmagent_instr');
    drawHeader('Node CLI', 1, 'nodeCliInstr');
    drawGraphGroup($nodecliGraphSet->getGroup("cli"));
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

