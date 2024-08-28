<?php
$pageTitle = "PM File Access NBI";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function getTableData() {
    global $statsDB, $date, $site;

    $query = "
SELECT
  a.Host,
  b.success,
  a.failure,
 (b.success + a.failure) AS 'total'
FROM
(
SELECT
  servers.hostname AS 'Host',
  k8s_pod_app_names.name AS 'Pod',
  SUM(numRequests) AS 'failure'
from
  nginx_requests,
  k8s_pod_app_names,
  sites,
  servers
WHERE
  statusCode != 200 AND
  nginx_requests.siteid = sites.id AND
  sites.name = '$site' AND
  nginx_requests.serverid = servers.id AND
  nginx_requests.appid = k8s_pod_app_names.id AND
  nginx_requests.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname, k8s_pod_app_names.name
 ) as a,
(
SELECT
  servers.hostname AS 'Host',
  k8s_pod_app_names.name AS 'Pod',
  SUM(numRequests) AS 'success'
from
  nginx_requests,
  k8s_pod_app_names,
  sites,
  servers
WHERE
  statusCode = 200 AND
  nginx_requests.siteid = sites.id AND
  sites.name = '$site' AND
  nginx_requests.serverid = servers.id AND
  nginx_requests.appid = k8s_pod_app_names.id AND
  nginx_requests.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname, k8s_pod_app_names.name
 ) as b
 WHERE
   a.Host = b.Host AND
   a.Pod = b.Pod AND
   a.pod = 'fileaccessnbi'
 GROUP BY a.Host;
";

    $statsDB->query($query);
    $res = array();
    while ( $row = $statsDB->getNextNamedRow() ) {
        $res[] = $row;
    }

    return $res;
}

function drawTable( $data ) {
    $table = new DDPTable(
        "PM_NBI",
        array(
            array( DDPTable::KEY => 'Host', DDPTable::LABEL => 'Host' ),
            array( DDPTable::KEY => 'total', DDPTable::LABEL => 'Total' ),
            array( DDPTable::KEY => 'success', DDPTable::LABEL => 'Success' ),
            array( DDPTable::KEY => 'failure', DDPTable::LABEL => 'Failure' ),
        ),
        array('data' => $data)
    );

    echo $table->getTableWithHeader("PM File Access NBI", 1, "", "");
}

function mainFlow() {
    global $statsDB;
    drawHeader("Daily Totals", 2, 'fileacessnbiDailyTotals');
    $table = new ModelledTable( "TOR/pm/enm_pm_file_access_nbi_daily_totals", 'fileacessnbiDailyTotals' );
    echo $table->getTable();

    $graphs = array();
    drawHeader('Apache Load', 1, 'fileAcccessnbiGraphs');
    getGraphsFromSet( 'cpu', $graphs, 'TOR/pm/enm_pm_file_access_nbi', null, 640, 320 );
    plotGraphs( $graphs );

    $graphs = array();
    getGraphsFromSet( 'nbi', $graphs, 'TOR/pm/enm_pm_file_access_nbi', null, 640, 320 );
    plotGraphs( $graphs );
    echo addLineBreak();

    if ( $statsDB->hasData( "nginx_requests" ) ) {
       $data = getTableData();
       drawTable( $data );
       $graphs = array();
       $modelledGraph = new ModelledGraph( 'TOR/pm/nbiSucc' );
       $graphs[] = $modelledGraph->getImage();
       $modelledGraph = new ModelledGraph( 'TOR/pm/nbiFail' );
       $graphs[] = $modelledGraph->getImage();
       echo addLineBreak();
       plotGraphs( $graphs );
    }
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

