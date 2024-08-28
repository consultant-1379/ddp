<?php
$pageTitle = "FNT / Push Service";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function plotServices() {
    global $selected;

    $types = explode(",", $selected);

    foreach ( $types as $type ) {
        $graphs = array();
        if ( $type == 'PM_STATS' ) {
            $head = "PM Files Transfer Statistics";
            $helpbubble = 'pmstatsgraph';
        }else if ( $type == 'CM' ) {
            $head = "CM Files Transfer Statistics";
            $helpbubble = 'cmgraph';
        } else {
            $head = "Product Data Transfer Statistics";
            $helpbubble = 'productdatagraph';
        }

        drawHeader( $head, 2, $helpbubble );
        $graphParam = array( 'type' => $type );
        getGraphsFromSet( 'all', $graphs, 'TOR/pm/fnt_push_service', $graphParam );
        plotGraphs( $graphs );
    }
}

function mainFlow() {
    drawHeader( "Overview of Files Transfer", 1, 'overview_transfer' );
    $params = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( 'TOR/pm/fnt_push_service', 'overview_transfer', $params );
    echo $table->getTable();
    echo addLineBreak();

    drawHeader( "Product Data Configuration", 1, "product_data" );
    $table = new ModelledTable( 'TOR/pm/fnt_product_data', 'product_data' );
    echo $table->getTable();
    echo addLineBreak();
}

$action = requestValue('plot');
$selected = requestValue('selected');

if ( is_null($selected) ) {
    mainFlow();
} elseif ( $action === 'plotServices' ) {
    plotServices();
}

include PHP_ROOT . "/common/finalise.php";
