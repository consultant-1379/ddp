<?php

$pageTitle = "NSM";

include_once "../../common/init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const PATH = 'TOR/cm/enm_cm_nsm_instr';

function nsmGraphs() {

    drawHeader('DPS Events', 1, 'dpsevents');
    getGraphsFromSet( "DPSEvents", $graphs, PATH);
    plotGraphs( $graphs );
    $graphs = array();

    drawHeader('Configuration Events', 1, 'configurationevents');
    getGraphsFromSet( "ConfigurationEvents", $graphs, PATH);
    plotGraphs( $graphs );
    $graphs = array();

    drawHeader('Alarm Requests', 1, 'alarmrequests');
    getGraphsFromSet( "AlarmRequests", $graphs, PATH);
    plotGraphs( $graphs );

}

function mainFlow() {

    drawHeader("NSM Daily Totals", 2, 'nsm');
    $table = new ModelledTable( PATH, 'nsm' );
    echo $table->getTable();
    echo addLineBreak();

    nsmGraphs();
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
