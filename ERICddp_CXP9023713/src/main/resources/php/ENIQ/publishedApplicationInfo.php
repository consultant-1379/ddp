<?php
$pageTitle = "Published Application";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function showApplicationGraph() {
    $host = requestValue('server');
    drawHeader( "OCS Published Application : $host", '1', '' );
    drawHeader( 'Published Application Count', '2', 'applicationHelp' );
    $modelledGraph = new ModelledGraph( 'ENIQ/publishedApplicationInfo' );
    $params = array( 'hostname' => $host );
    plotgraphs( array( $modelledGraph->getImage($params) ) );
    echo addLineBreak();
    $table = new ModelledTable( 'ENIQ/publishedApplicationInfo', 'publishedApplicationInfoHelp' );
    echo $table->getTableWithHeader( "Published Application" );
}

showApplicationGraph();

include_once "../common/finalise.php";
