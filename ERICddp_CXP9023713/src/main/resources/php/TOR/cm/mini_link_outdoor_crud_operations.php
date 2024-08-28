<?php

$pageTitle = "MINI-LINK Outdoor CRUD Instrumentation";

include_once "../../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once "./mini_link_crud_func.php";

function mainFlow() {
    $operationList = array('Create', 'Modify', 'Delete', 'Action', 'Read');

    drawHeader("Daily Totals", 1, "dailyTotalsOutdoor");
    mlCRUDDailyTotals( $operationList, 'enm_cmwriter_minilink_outdoor' );
    $graphSet = new ModelledGraphSet('TOR/cm/cm_minilink_outdoor_graphs');

    if ( issetURLParam('id') ) {
        $mbean = requestValue('id');
        $helpBubble = "${mbean}OperationHelp";
        drawHeader("Daily Summary Table for $mbean", 2, "$helpBubble");
        mlCRUDSummary($mbean, 'enm_cmwriter_minilink_outdoor');

        drawHeader("Instrumentation Graphs for $mbean", 2, "$helpBubble");
        drawGraphGroup($graphSet->getGroup($mbean));
    } else {
        drawHeader("Instrumentation Graphs for SNMP Operation", 2, "snmpOutdoorinstrumentationHelp");
        drawGraphGroup($graphSet->getGroup("snmp"));
        drawHeader("Instrumentation Graphs for CLI Operation", 2, "cliinstrumentationHelp");
        drawGraphGroup($graphSet->getGroup("cli"));
    }
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
