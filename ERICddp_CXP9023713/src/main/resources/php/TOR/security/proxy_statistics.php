<?php

$pageTitle = "Proxy Account Statistics";

include_once "../../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function mainFlow() {

     /* Get the graphs */
    drawHeader('Proxy Account Statistics', 2, 'proxyStatistics');
    getGraphsFromSet('proxy', $graphs, 'TOR/security/proxy_account_statistics');
    plotGraphs($graphs);
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
