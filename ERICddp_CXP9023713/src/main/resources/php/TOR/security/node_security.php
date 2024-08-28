<?php

$pageTitle = "Node Security Jobs Statistics";

const NODE_SECURITY = 'Node Security Jobs Statistics';

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function mainFlow() {


    drawHeader('Daily Totals', 1, "nsjstatisticsdaily");
    $table = new ModelledTable('TOR/security/enm_node_daily_totals', 'nsjstatisticsdaily');
    echo $table->getTable();
    echo addLineBreak();

    $graphs = array();
    drawHeader('Node Security Jobs', 2, 'nsjstatisticsg');
    $modelledGraph = new ModelledGraph('TOR/security/node_sec_jobs', 'nsjstatisticsg');
    $graphs[] = $modelledGraph->getImage();
    plotGraphs($graphs);

    drawHeader(NODE_SECURITY, 1, "nsjstatistics");
    $table = new ModelledTable('TOR/security/nsj_statistics', 'nsjstatistics');
    echo $table->getTable();
    echo addLineBreak();

}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
