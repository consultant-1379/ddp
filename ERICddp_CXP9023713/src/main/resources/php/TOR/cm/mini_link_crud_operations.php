<?php

$pageTitle = "MINI-LINK Indoor CRUD Instrumentation";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once "./mini_link_crud_func.php";

function mainFlow() {
    $operationList = array('Create', 'Modify', 'Delete', 'Action', 'Read');

    drawHeader("Daily Totals", 1, "dailyTotalsHelp");
    mlCRUDDailyTotals( $operationList, 'enm_cmwriter_minilink_indoor' );

    $graphSet = new ModelledGraphSet('TOR/cm/ml_indoor');

    if ( issetURLParam('id') ) {
        $mbean = requestValue('id');
        $helpBubble = "${mbean}OperationHelp";
        drawHeader("Daily Summary Table for $mbean", 2, $helpBubble);
        mlCRUDSummary($mbean, 'enm_cmwriter_minilink_indoor');

        drawHeader("Instrumentation Graphs for $mbean", 2, $helpBubble);
        drawGraphGroup($graphSet->getGroup($mbean));
    } else {
        drawHeader("Instrumentation Graphs for SNMP Operation", 2, "snmpinstrumentationHelp");
        drawGraphGroup($graphSet->getGroup("snmp"));
    }
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

