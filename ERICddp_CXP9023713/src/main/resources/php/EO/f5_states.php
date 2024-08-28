<?php

$pageTitle = 'F5 States';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function plotStats($selected, $type) {
    $graphs = array();
    if ( $type == "poolstates" ) {
        $params = array( 'poolids' => $selected );
        $url = "EO/f5/pool_";
        $cols = array('state');
    } elseif ( $type == "nodestates" ) {
        $params = array( 'nodeids' => $selected );
        $url = "EO/f5/node_";
        $cols = array('state');
    } elseif ( $type == "virtualstates" ) {
        $params = array( 'virtids' => $selected );
        $url = "EO/f5/virtual_server_";
        $cols = array('state');
    }

    foreach ( $cols as $col ) {
        $modelledGraph = new ModelledGraph($url . $col);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs($graphs);
}

function params() {
    return array(
        array('EO/f5_pool_states', 'f5_pool_states', 'F5 Pool States'),
        array('EO/f5_node_states', 'f5_node_states', 'F5 Node States'),
        array('EO/f5_virtual_states', 'f5_virtual_states', 'F5 Virtual States')
    );
}

function main() {
    $f5StatesURLs = array();
    $f5URLs[] = makeAnchorLink("f5_pool_states", 'F5 Pool States');
    $f5URLs[] = makeAnchorLink("f5_node_states", 'F5 Node States');
    $f5URLs[] = makeAnchorLink("f5_virtual_states", 'F5 Virtual States');

    echo makeHTMLList($f5URLs);

    $params = params();
    foreach ($params as $param) {
        $table = new ModelledTable($param[0], $param[1],
            array(
              ModelledTable::URL => makeSelfLink()
            )
        );
        echo $table->getTableWithHeader($param[2]);
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
