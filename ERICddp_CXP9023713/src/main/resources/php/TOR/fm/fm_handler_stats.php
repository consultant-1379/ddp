<?php

$pageTitle = "FM O1";

include_once "../../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const ACT = 'action';

function showLinks() {
    global $statsDB;

    $links = array();
    $links[] = makeAnchorLink( 'fmhandlerdt', 'O1 Daily Totals' );
    $links[] = makeAnchorLink( 'fmnodestatus', 'Nodes Status' );
    $links[] = makeAnchorLink( 'fmalarmsreceived', 'Alarms Received' );
    $links[] = makeAnchorLink( 'fmalarmstransformed', 'Alarms Transformed' );
    $links[] = makeAnchorLink( 'fmforwadedevents', 'Forwarded Event Notifications' );
    $links[] = queueLink();
    $links[] = routesLink();

    echo makeHTMLList($links);
}

function fmHandlerGraphs() {
    $hostnames = getInstances( 'enm_fm_handler_statistics' );
    $servIds = getServIdsFromArray($hostnames);
    $path = 'TOR/fm/fm_handler_statistics';
    if ($servIds) {
        $graphs = array();
        $params = array( 'serverid' => $servIds[0] );
        drawHeader( 'Nodes Status', 2, 'fmnodestatus' );
        getGraphsFromSet( 'nodesStatus', $graphs, 'TOR/fm/fm_handler_statistics_single_instance', $params );
        plotGraphs($graphs);
    }

    $graphs = array();
    drawHeader( 'Alarms Received', 2, 'fmalarmsreceived' );
    getGraphsFromSet( 'alarmsReceived', $graphs, $path );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'Alarms Transformed', 2, 'fmalarmstransformed' );
    getGraphsFromSet( 'alarmsTransformed', $graphs, $path );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'Forwarded Event Notifications', 2, 'fmforwadedevents' );
    getGraphsFromSet( 'forwardedEventNotifications', $graphs, $path );
    plotGraphs($graphs);
}

function mainFlow() {
    global $statsDB, $site, $date;

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, 'mssnmpfm');
    $serverIdsArr = array_values($processingSrv);

    showLinks();

    drawHeader( 'O1 Daily Totals', 1, 'fmhandlerdt' );
    $table = new ModelledTable( 'TOR/fm/fm_handler_statistics', 'fmhandlerdt' );
    echo $table->getTable();
    echo addLineBreak();

    fmHandlerGraphs();

    $queueNames = array('O1FmNotifications' => 'O1FmNotifications');
    plotQueues( $queueNames, true );

    getRouteInstrTable( $serverIdsArr );
}

if ( issetUrlParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
