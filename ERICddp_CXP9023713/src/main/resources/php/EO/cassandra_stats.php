<?php

$pageTitle = 'EDA Cassandra';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
const GRAPH_PATH = 'EO/cassandra';

function params() {
    return array(
        array('client_requests', 'Client Requests'),
        array('cas_client_requests', 'CAS Client Requests'),
        array('connection', 'Connection'),
        array('threadpools','Threadpools'),
        array('commitlog','Commit Log'),
        array('droppedmessage','Dropped Message'),
        array('storage', 'Storage'),
        array('client', 'Client'),
        array('columnfamily', 'Column Family'),
    );
}

function drawGraphGroup($group, $params) {
    $row = array();
    foreach ( $group['graphs'] as $modelledGraph ) {
        $row[] = array( $modelledGraph->getImage($params, null, null, 640, 240) );
    }
    plotgraphs( $row, 1 );
}

function plotStats($selected, $type) {
    $params = array( 'Instance' => $selected );
    $graphSet = new ModelledGraphSet(GRAPH_PATH);
    drawGraphGroup($graphSet->getGroup($type), $params);
}

function main() {

    $urls = array();
    $urls[] = makeAnchorLink('client_requests', 'Client Requests');
    $urls[] = makeAnchorLink('cas_client_requests', 'CAS Client Requests');
    $urls[] = makeAnchorLink('connection', 'Connection');
    $urls[] = makeAnchorLink('threadpools', 'Threadpools');
    $urls[] = makeAnchorLink('commitlog', 'Commit Log');
    $urls[] = makeAnchorLink('droppedmessage', 'Dropped Message');
    $urls[] = makeAnchorLink('storage', 'Storage');
    $urls[] = makeAnchorLink('client', 'Client');
    $urls[] = makeAnchorLink('columnfamily', 'Column Family');

    echo makeHTMLList($urls);

    $params = params();
    foreach ($params as $param) {
        $table = new ModelledTable("EO/cassandra/".$param[0], $param[0], array(ModelledTable::URL => makeSelfLink()));
        echo $table->getTableWithHeader($param[1]);
    }
}

$selected = requestValue('selected');
$plottype = requestValue('plot');

if ( is_null($selected) ) {
    main();
} else {
    plotStats($selected, $plottype);
}

include_once PHP_ROOT . "/common/finalise.php";
