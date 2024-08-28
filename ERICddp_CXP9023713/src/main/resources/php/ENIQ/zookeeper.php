<?php
$pageTitle = "Zookeeper Statistics";
include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

const ZAPPNAME = 'eric-data-coordinator-zk';

function showZookeeperGraphSet( $appName ) {
    global $statsDB, $site, $date;

    $statsDB->query("
SELECT
    DISTINCT(zookeeper.serverid)
FROM
    zookeeper
JOIN
    sites ON zookeeper.siteid = sites.id
JOIN
    servers ON zookeeper.serverid = servers.id
JOIN
    k8s_pod_app_names ON zookeeper.appid = k8s_pod_app_names.id
WHERE
    zookeeper.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    sites.name = '$site' AND
    k8s_pod_app_names.name = '$appName'
    ");

    $zookeeperModelledGraphSet = new ModelledGraphSet( "ENIQ/zookeeper" );
    $zookeeperGraphs = $zookeeperModelledGraphSet->getGroup( "zookeeper_graphs" );
    $srvIds = array();
    while ( $row = $statsDB->getNextRow() ) {
        $srvIds[] = $row[0];
    }
    $params = array( 'serverid' => implode( ",", $srvIds ) );
    $graphs = array();
    foreach ( $zookeeperGraphs['graphs'] as $modelledGraph ) {
        $graphs[] = $modelledGraph->getImage( $params, null, null, 500, 320 );
    }
    plotgraphs( $graphs );
}

function mainFlow() {
    global $statsDB;

    drawHeader( "Zookeeper", 2, "" );
    if ( $statsDB->hasData( "zookeeper" ) ) {
        drawHeader( "Zookeeper Statistics", 3, "zookeeperHelp" );
        showZookeeperGraphSet( ZAPPNAME );
    }
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
