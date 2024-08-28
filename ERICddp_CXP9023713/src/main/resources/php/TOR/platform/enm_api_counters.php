<?php

$pageTitle = "Openstack API Count";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

function apiGraphParams() {
    return array (
        'cinderv2_count',
        'cinderv3_count',
        'glance_count',
        'heat_count',
        'keystone_count',
        'neutron_count',
        'nova_count'
    );
}

function mainFlow() {

    $table = new ModelledTable( "TOR/platform/api_counters", 'openStack' );
    echo $table->getTableWithHeader("Open Stack API Counter");
    echo addLineBreak();

    $graphParams = apiGraphParams();

    foreach ( $graphParams as $param ) {
        $modelledGraph = new ModelledGraph("/TOR/platform/$param");
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs( $graphs );
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
