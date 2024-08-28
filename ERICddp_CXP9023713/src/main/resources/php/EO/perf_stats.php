<?php

$pageTitle = 'EO Performance Stats';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
const SUMMARY = 'Summary';

function plotPerfStats($selected) {
    $params = array( 'serviceids' => $selected );
    $graphs = array();
    foreach ( array('count', 'total', 'max', 'min') as $col ) {
        $modelledGraph = new ModelledGraph("EO/perf/service_perf_stats_" . $col);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs($graphs, 1);
}

function restApiParams() {
    return array(
        array('service_restapi_summary', 'restapi_summary', SUMMARY),
        array('service_perf_restapi', 'perf_service_restapi', 'REST API Performance')
    );
}

function orderCareParams() {
    return array(
        array('service_ordercare_summary', 'ordercare_summary', SUMMARY),
        array('service_perf_ordercare', 'perf_ordercare', 'OrderCare Performance')
    );
}

function nsoParams() {
    return array(
        array('service_nso_summary', 'nso_summary', SUMMARY),
        array('service_perf_nso', 'serviceperf_nso', 'NSO Performance')
    );
}

function displayTable( $params ) {
    foreach ($params as $param) {
        $table = new ModelledTable(
            "EO/perf/$param[0]",
            $param[1],
            array(
                ModelledTable::URL => makeSelfLink(),
            )
        );
        echo $table->getTableWithHeader($param[2]);
    }
}

function main() {
    if ( issetURLParam('servicePerf') ) {
        $params = restApiParams();
    } elseif (issetURLParam('orderCare')) {
        $params = orderCareParams();
    } else {
        $params = nsoParams();
    }
    displayTable( $params );
}

$selected = requestValue('selected');
if ( is_null($selected) ) {
    main();
} else {
    plotPerfStats($selected);
}

require_once PHP_ROOT . "/common/finalise.php";

