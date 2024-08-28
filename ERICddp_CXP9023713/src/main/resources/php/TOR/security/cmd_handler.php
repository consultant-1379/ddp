<?php

$pageTitle = "Node Security Command Handler Statistics";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function mainFlow() {


    drawHeader('Daily Totals', 1, 'cmdhandlerdaily');
    $table = new ModelledTable('TOR/security/cmd_handler_daily_totals', 'cmdhandlerdaily');
    echo $table->getTable();
    echo addLineBreak();

    $graphs = array();
    drawHeader('Node Security Command Handler', 2, 'cmdstatisticsg');
    $modelledGraph = new ModelledGraph('TOR/security/cmd_handler', 'cmdstatisticsg');
    $graphs[] = $modelledGraph->getImage();
    plotGraphs($graphs);

    drawHeader('Node Security Command Handler Statistics', 1, "cmdstatistics");
    $table = new ModelledTable('TOR/security/cmd_handler_statistics', 'cmdstatistics');
    echo $table->getTable();
    echo addLineBreak();

}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
