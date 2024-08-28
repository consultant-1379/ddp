<?php

$pageTitle = "Element Manager Usage";

include_once "../../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function mainFlow() {
    $graphs = array();
    drawHeader( "Remote Desktop", 1, 'sessions' );
    getGraphsFromSet( 'element', $graphs, 'TOR/cm/element_manager_usage', null, 640, 320 );
    plotGraphs($graphs);

}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

