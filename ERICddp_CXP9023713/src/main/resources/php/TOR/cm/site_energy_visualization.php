<?php
$pageTitle = "Site Energy Visualization";

include_once "../../common/init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

const DT = 'Daily Totals';
const INSTR_FILE = 'TOR/cm/site_energy_visualization_instr';

function getGraphsByInst( $instances, &$graphs, $file, $type='' ) {
    global $statsDB, $site;

    foreach ( $instances as $inst ) {
        $serverid = getServerId( $statsDB, $site, $inst );
        $params = array( 'serverid' => $serverid, 'hostname' => $inst );
        if ( $type != '' ) {
            $params['type'] = $type;
        }
        $modelledGraph = new ModelledGraph("TOR/cm/$file");
        $graphs[] = $modelledGraph->getImage($params);
    }
}

function energyFlow($instances) {
    drawHeader( 'Energy Flows', 1, '' );

    $table = new ModelledTable( "TOR/cm/energy_flow", 'energyFlowTab' );
    echo $table->getTableWithHeader(DT);
    echo addLineBreak(2);

    drawHeader( 'Energy Flow Requests', 2, 'energyFlowGra' );
    getGraphsFromSet( 'energyFlowRequestsA', $graphs, INSTR_FILE, '', 800, 400 );
    getGraphsByInst( $instances, $graphs, 'energy_flow' );
    getGraphsFromSet( 'energyFlowRequestsB', $graphs, INSTR_FILE, '', 800, 400 );
    getGraphsByInst( $instances, $graphs, 'energy_flow_task' );
    getGraphsByInst( $instances, $graphs, 'sev_parallel_user_sessions', 'ENERGY_FLOW' );
    plotGraphs($graphs);
}

function energyReport($instances) {
    drawHeader( 'Energy Reports', 1, '' );

    $table = new ModelledTable( "TOR/cm/energy_report", 'energyReportTab' );
    echo $table->getTableWithHeader(DT);
    echo addLineBreak(2);

    drawHeader( 'PM File Parsing Stats', 2, 'energyReportGraA' );
    getGraphsFromSet( 'pmStatsA', $graphs, INSTR_FILE, '', 800, 400 );
    getGraphsByInst( $instances, $graphs, 'energy_report' );
    getGraphsFromSet( 'pmStatsB', $graphs, INSTR_FILE, '', 800, 400 );
    getGraphsByInst( $instances, $graphs, 'sev_parallel_user_sessions', 'ENERGY_REPORT' );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'Energy Report Requests', 2, 'energyReportGraB' );
    getGraphsFromSet( 'energyReportRequests', $graphs, INSTR_FILE, '', 800, 400 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'Energy Report House Keeping', 2, 'energyReportGraC' );
    getGraphsFromSet( 'energyReportHouse', $graphs, INSTR_FILE, '', 800, 400 );
    plotGraphs($graphs);
}

function userSettings() {
    drawHeader( 'User Settings', 1, '' );

    $table = new ModelledTable( "TOR/cm/user_settings", 'userSettingsTab' );
    echo $table->getTableWithHeader(DT);
    echo addLineBreak(2);

    drawHeader( 'User Settings Requests', 2, 'userSettingsGraA' );
    getGraphsFromSet( 'userSettingsRequests', $graphs, INSTR_FILE, '', 800, 400 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'User Settings Updates', 2, 'userSettingsGraB' );
    getGraphsFromSet( 'userSettingsUpdates', $graphs, INSTR_FILE, '', 800, 400 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'User Settings House Keeping', 2, 'userSettingsGraC' );
    getGraphsFromSet( 'userSettingsHouse', $graphs, INSTR_FILE, '', 800, 400 );
    plotGraphs($graphs);
}

function mainFlow() {
    $instA = getInstances( 'enm_cm_site_energy_visualization_instr', 'time', '' );
    $instB = getInstances( 'enm_cm_energy_flow_tasks', 'time', '' );
    $instances = array_unique( array_merge($instA, $instB) );

    energyFlow($instances);
    energyReport($instances);
    userSettings();
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
