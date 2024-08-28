<?php

$pageTitle = "NHM REST NBI";


include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const FILE_PATH = 'TOR/pm/fetch_kpi_values';

function showLinks() {
    $links = array();
    $links[] = makeAnchorLink( "fetch_kpi", 'NBI KPI latest ROP query' );
    $links[] = makeAnchorLink( "historical_kpi", 'NBI KPI historical ROP query' );
    $links[] = makeAnchorLink( "activation_kpi", 'NBI Activation Status KPI Request' );
    $links[] = makeAnchorLink( "capabilities_kpi", 'NBI Get KPI Instance Request' );
    $links[] = makeAnchorLink( "kpi_request", 'NBI Activate and Deactivate KPI Request' );
    $links[] = makeAnchorLink( "delete_kpi", 'NBI Delete KPI Request' );
    $links[] = makeAnchorLink( "list_kpi", 'NBI List Of All KPIs Request' );
    $links[] = makeAnchorLink( "create_kpi", 'NBI Create KPI Request' );
    $links[] = makeAnchorLink( "read_kpi", 'NBI Read KPI Definition Request' );
    $links[] = makeAnchorLink( "update_kpi", 'NBI Update KPI Request' );
    echo makeHTMLList($links);
}

function mainFlow() {

    showLinks();

    $graphs = array();
    drawHeader( 'NBI KPI latest ROP query', 1, 'fetch_kpi' );
    getGraphsFromSet( 'fetchkpi', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI KPI historical ROP query', 1, 'historical_kpi' );
    getGraphsFromSet( 'Historicalkpi', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Activation Status KPI Request', 1, 'activation_kpi' );
    getGraphsFromSet( 'activation', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Get KPI Instance Request', 1, 'capabilities_kpi' );
    getGraphsFromSet( 'capabilities', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Activate and Deactivate KPI Request', 1, 'kpi_request' );
    getGraphsFromSet( 'kpi', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Delete KPI Request', 1, 'delete_kpi' );
    getGraphsFromSet( 'delete', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI List Of All KPIs Request', 1, 'list_kpi' );
    getGraphsFromSet( 'list', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Create KPI Request', 1, 'create_kpi' );
    getGraphsFromSet( 'create', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Read KPI Definition Request', 1, 'read_kpi' );
    getGraphsFromSet( 'read', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'NBI Update KPI Request', 1, 'update_kpi' );
    getGraphsFromSet( 'update', $graphs, FILE_PATH, null, 550, 320 );
    plotGraphs($graphs);

}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
