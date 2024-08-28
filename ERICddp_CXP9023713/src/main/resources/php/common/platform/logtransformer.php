<?php

$pageTitle = "Log Transformer";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function main() {
    $params = array( 'plot' => 'appname', 'selected' => 'eric-log-transformer' );
    $link = makeLink( '/k8s/cadvisor.php', 'JVM Data', $params );
    echo makeHTMLList( array( $link ) );

    drawHeader('Log Transformer', 1, 'logTransformer');
    getGraphsFromSet( 'all', $graphs, 'common/platform/logtransformer', []);
    plotGraphs( $graphs );
}

main();

include_once PHP_ROOT . "/common/finalise.php";

