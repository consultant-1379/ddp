<?php
$pageTitle = "Instance Traffic";

require_once "./init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once 'HTML/Table.php';

function plotStats($selected, $type) {
    global $statsDB;
    $graphs = array();
    $graphTable = new HTML_Table("border=0");

    if ( $type == "instance" ) {
        $params = array( 'Instance' => $selected );
        drawHeader('Ingress Controller Traffic', 1, 'controller_traffic');
        getGraphsFromSet( 'controller', $graphs, 'TOR/platform/enm_controller_traffic', $params, 640, 320 );
        plotGraphs($graphs, 1);
    } else {
        $appValue = array();
        $selectedValues = preg_split("/,/", $selected);
        foreach ($selectedValues as $params) {
           $param = preg_split("/@/", $params);
           $appValue[] = $param[0];
        }
        $appNameId = array_unique($appValue);
        foreach ($appNameId as $appNameValue) {
            $app = array();
            $status = array();
            $graphs = array();
            foreach ($selectedValues as $selectedValue) {
                if (strpos($selectedValue, $appNameValue."@") !== false) {
                    $value = preg_split("/@/", $selectedValue);
                    $app[] = $value[0];
                    $status[] = $value[1];
                }
            }
            $appId = implode(",", $app);
            $statusCode = implode(",", $status);
            $appName = $statsDB->queryRow("SELECT name FROM k8s_pod_app_names WHERE id = $appNameValue");
            drawHeader($appName[0], 2, 'instance_traffic');
            $graphParam = array( 'appid' => $appId, 'statusCode' =>  $statusCode );
            $modelledGraph = new ModelledGraph('common/nginx_requests', 'import_ui_graph');
            $graphs[] = $modelledGraph->getImage($graphParam);
            plotGraphs($graphs);
        }
    }
}

function mainFlow() {
    $graphs = array();

    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( "TOR/platform/enm_controller_traffic", 'controller_traffic', $selfLink );
    echo $table->getTableWithHeader('Ingress Controller Traffic');
    echo addLineBreak();

    drawHeader('Current Client Connections', 1, 'client_connection');
    getGraphsFromSet( 'clientConnection', $graphs, 'TOR/platform/enm_client_connection', null, 640, 320 );
    plotGraphs($graphs);

    $table = new ModelledTable( "common/nginx_requests", 'instance_traffic', $selfLink );
    echo $table->getTableWithHeader('Ingress Instance traffic');
    echo addLineBreak();
}

$selected = requestValue('selected');
$plottype = requestValue('plot');

if ( $selected ) {
    plotStats($selected, $plottype);
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
