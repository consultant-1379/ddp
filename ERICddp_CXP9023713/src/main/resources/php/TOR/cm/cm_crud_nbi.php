<?php

$pageTitle = 'CM CRUD NBI';

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function getGraphs($group, $srvIdsStr) {
    foreach ( $group as $set => $param ) {
        $graphs = array();
        drawHeader($param[0], 2, $set);
        getGraphsFromSet($set, $graphs, 'TOR/cm/cm_crud_nbi', array('serverid' => $srvIdsStr));
        plotgraphs( $graphs );
    }
}

function crudGraphParams() {
    return array(
        'get_count' => array( 'GET Count', 3 ),
        'get_total_time' => array( 'GET Total Time', 2 ),
        'get_exec_time' => array( 'GET Execution Time', 2 ),
        'delete_count' => array( 'DELETE Count', 2 ),
        'delete_total_time' => array( 'DELETE Total Time', 2 ),
        'delete_exec_time' => array( 'DELETE Execution Time', 3 ),
        'post_count' => array ( 'POST Count', 3 ),
        'post_total_time' => array ( 'POST Total Time', 2 ),
        'post_exec_time' => array( 'POST Execution Time', 3 ),
        'put_count' => array( 'PUT-Create Count', 2 ),
        'put_total_time' => array( 'PUT-Create Total Time', 2 ),
        'put_exec_time' => array( 'PUT-Create Execution Time', 2 ),
        'put_mod_count' => array( 'PUT-Modify Count', 2 ),
        'put_mod_total' => array( 'PUT-Modify Total Time', 3 ),
        'put_mod_exec' => array ( 'PUT-Modify Execution Time', 3 ),
        'patch_3gpp_count' => array ( 'PATCH 3gpp-json-patch+json Count', 2 ),
        'patch_3gpp_total' => array( 'PATCH 3gpp-json-patch+json Total Time', 2 ),
        'patch_3gpp_exec' => array( 'PATCH 3gpp-json-patch+json Execution Time', 2 ),
        'patch_json_count' => array( 'PATCH json-patch+json Count', 2 ),
        'patch_json_total' => array( 'PATCH json-patch+json Total Time', 3 ),
        'patch_json_exec' => array ( 'PATCH json-patch+json Execution Time', 3 )
    );
}

function mainFlow() {
    global $site, $date, $statsDB;

    $srvList = enmGetServiceInstances($statsDB, $site, $date, "cmservice");
    $srvIdsStr = implode(",", $srvList);
    $crudParams = crudGraphParams();
    $links = array();
    foreach ( $crudParams as $group => $title ) {
        $links[] = makeAnchorLink($group, $title[0]);
    }

    $table = new ModelledTable('TOR/cm/enm_cm_crud_nbi', 'enm_cm_crud_nbi');
    echo $table->getTableWithHeader("REST Daily Totals");
    echo addLineBreak();
    drawHeader('CM CRUD NBI Graphs', 2, 'cm_crud_nbi' );
    echo makeHTMLList($links);
    getGraphs( $crudParams, $srvIdsStr);
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
