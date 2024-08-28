<?php

$pageTitle = "FCM";

include_once "../../common/init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function mainFlow() {

    drawHeader("Flexible Counter Management (FCM) Daily Totals", 2, 'fcmCounters');
    $table = new ModelledTable( "TOR/pm/enm_flexible_counters", 'fcmCounters' );
    echo $table->getTable();
    echo addLineBreak();

    drawHeader("FCM Graphs", 2, "fcmGraphs");
    $fcmModelledGraphSet = new ModelledGraphSet('TOR/pm/enm_flexible_counters');
    $fcmGraphs = $fcmModelledGraphSet->getGroup("fcm");
    foreach ( $fcmGraphs['graphs'] as $modelledGraph ) {
        $graphs[] = array( $modelledGraph->getImage(null, null, null, 640, 240));
    }
    plotgraphs( $graphs );
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

