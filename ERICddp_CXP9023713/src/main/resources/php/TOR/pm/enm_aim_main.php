<?php
$pageTitle = "AIM";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

const IM_FM = 'imfmalarmtransformer';
const IM_GR = 'imgroupingservice';
const IM_KN = 'imknowledgebaseservice';
const IM_AN = 'imkpianomalydetection';
const IM_LCS = 'imlifecycleservice';
const FM_TBL = 'enm_aim_fm_instr';
const GRP_TBL = 'enm_aim_grouping_instr';
const KW_TBL = 'enm_aim_knowledge_instr';
const ANO_TBL = 'enm_aim_anomaly_instr';
const LFCY_TBL = 'enm_aim_lifecycle_instr';
const FMNBI_TBL = 'enm_fm_nbi_lifecycle_instr';
const AIM_DIR = 'TOR/pm/aim/';
const TECH_TABLE = 'enm_aim_kpi_training_status';

$sg = requestValue('SG');
$sgToDbTableMap = array(
    IM_FM => FM_TBL,
    IM_GR => GRP_TBL,
    IM_KN => KW_TBL,
    IM_AN => ANO_TBL,
    IM_LCS => LFCY_TBL
);
$dbTblToGraphSetMap = array(
    FM_TBL => 'fm',
    GRP_TBL => 'gr',
    KW_TBL => 'kn',
    ANO_TBL => 'an',
    LFCY_TBL => 'lc'
);

//Params for the graphs on the main page.
function mainGraphParams() {
    return array(
        'totalIncidentsCreatedWithFMAndPMDataSource' => LFCY_TBL,
        'totalActiveIncidents' => LFCY_TBL,
        'totalInactiveIncidents' => LFCY_TBL,
        'totalKpiResultsCollected' => ANO_TBL,
        'totalOpenKpiEventsCreated' => ANO_TBL,
        'totalAlarmsReceived' => FM_TBL,
        'totalCombinedDimensionKeysCreated' => KW_TBL,
        'totalRequestsToGetCombinedDimensionKeysInBatch' => KW_TBL
    );
}

function enrichmentEvents() {
    drawHeaderWithHelp("Enrichment Events Summary", 1, "Enrichment_Events");
    drawTable('eev');
    $graphparams = enrichmentEventsGraphs();
    makeGraphs($graphparams);
}

function enrichmentEventsGraphs() {
    $troubleTickets = array ( 'troubletickets' );
    $workOrders = array ( 'workOrders' );
    $otherEnrichmentEvents = array ( 'otherEnrichmentEvents' );
    return array (
        array ('Trouble Tickets', $troubleTickets, 'trouble_tickets'),
        array ('Work Orders', $workOrders, 'work_orders'),
        array ('Other Enrichment Events', $otherEnrichmentEvents, 'other_enrichment_Events')
    );
}

function makeGraphs($params) {
    foreach ( $params as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 1, $help);
        $graphParams = $param[1];
        foreach ( $graphParams as $graphParam ) {
            $modelledGraph = new ModelledGraph( AIM_DIR . $graphParam );
            $graphs[] = $modelledGraph->getImage(null, null, null, 640, 240);
        }
        plotgraphs( $graphs );
    }
}

function mainLinks($sgs) {
    $links = array();
    foreach ($sgs as $sg) {
       $links[] = makeLink('/TOR/pm/enm_aim_main.php', $sg, array( 'SG' => $sg ) );
    }
    return $links;
}

function nbiInfo() {
    drawHeader('AIM NBI', 1, 'nbi');
    $params = array(
        'AIM NBI Event Processed' => 'nbi_proc',
        'AIM NBI Event Published' => 'nbi_push',
        'AIM NBI Event Published On Demand' => 'nbi_pull'
    );
    foreach ( $params as $title => $file ) {
        drawHeader($title, 1, $file);
        drawTable($file);
    }
    drawHeader('AIM NBI Graphs', 1, 'nbi_info');
    $graphs = array();
    $modelledGraph = new ModelledGraph( AIM_DIR . 'totalEventsProcessed' );
    $graphs[] = $modelledGraph->getImage();
    getGraphsFromSet('nbi', $graphs, AIM_DIR . 'nbi_info');
    plotGraphs($graphs);
}

function fmNbiInfo() {
    drawHeader('FM NBI', 1, 'fmnbi_info');
    $params = array(
        'FM NBI Incidents Sent Daily Totals' => 'fmnbi_totals'
    );
    foreach ( $params as $title => $file ) {
        drawHeader($title, 1, $file);
        drawTable($file);
    }
    $instances = getInstances(FMNBI_TBL);
    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow( array_values($instances), null, 'th' );
    drawHeader('FM NBI Graphs', 1, 'fmnbi');
    foreach ( $instances as $inst ) {
        $params = array( "inst" => $inst );
        $modelledGraph = new ModelledGraph( 'TOR/pm/aim/fmnbi' );
        $graphs[] = $modelledGraph->getImage($params, null, null, 640, 320);
    }
    $graphTable->addRow($graphs);
    echo $graphTable->toHTML();
}

function kpiTraining() {
    global $statsDB, $date, $site;
    $sql = $statsDB->queryNamedRow("
SELECT
    MAX(time) AS time
FROM
    enm_aim_kpi_training_status,sites
WHERE
    sites.id = enm_aim_kpi_training_status.siteid AND sites.name = '$site'
    AND time BETWEEN '$date 00:00:00' and '$date 23:59:59'");
    $kpiMaxtime = $sql['time'];
    $selfLink = array( ModelledTable::URL => makeSelfLink(), 'maxtime' => $kpiMaxtime );
    $table = new ModelledTable(
        AIM_DIR . 'kpi_training',
        'KPI_Training_Graph',
        $selfLink
    );
    echo $table->getTableWithHeader("KPI Training");
    echo addLineBreak();
}

function plotKPIGraph($selected) {
    $graphs = array();
    $params = array( 'kpiName' => $selected );
    getGraphsFromSet( 'all', $graphs, AIM_DIR . 'kpi', $params );
    plotGraphs($graphs);
}

function plotMeasurementTable() {
    global $statsDB, $date, $site;
    $sql = $statsDB->queryNamedRow("
SELECT
    MAX(time) AS time
FROM
    enm_aim_measurement_training,sites
WHERE
    sites.id = enm_aim_measurement_training.siteid AND sites.name = '$site'
    AND time BETWEEN '$date 00:00:00' and '$date 23:59:59'");
    $maxTime = $sql['time'];
    $selfLink = array( ModelledTable::URL => makeSelfLink(), 'maxtime' => $maxTime );
    $table = new ModelledTable(
        AIM_DIR . 'measurement_object_training',
        'measurement_objects',
        $selfLink
    );
    echo $table->getTableWithHeader("Measurement Object Training");
    echo addLineBreak();
}

function plotMeasurementGraph($selected) {
    $graphs = array();
    $param = preg_split("/@/", $selected);
    $params = array( 'nodetype' => $param[0], 'measurementType' =>  $param[1] );
    getGraphsFromSet( 'training', $graphs, AIM_DIR . 'measurement_object', $params );
    plotGraphs($graphs);
}

function showLinks() {
    global $statsDB;
    $links = array();
    $links[] = makeAnchorLink("imlifecycleservice_instrumentation_anchor", 'Imlifecycleservice Instrumentation');
    $links[] = makeAnchorLink("ActiveIncidentsDataSource_anchor", 'Active Incidents by Data Source');
    if ( $statsDB->hasData('enm_aim_measurement_training') ) {
        $links[] = makeAnchorLink("measurement_objects", 'Measurement Object Training');
    }
    $links[] = makeAnchorLink("Enrichment_Events", "Enrichment Event");
    $links[] = makeAnchorLink("nbi", "AIM NBI");
    if ( $statsDB->hasData(FMNBI_TBL) ) {
        $links[] = makeAnchorLink("fmnbi_info", "FM NBI");
    }
    $links[] = makeAnchorLink("Node_Training_Graph", 'Node Training');
    if ( $statsDB->hasData(TECH_TABLE) ) {
        $links[] = makeAnchorLink("KPI_Training_Graph", "KPI Training");
    }
    echo makeHTMLList($links);
}

function plotMainGraphs() {
    global $sgToDbTableMap, $dbTblToGraphSetMap;

    $graphParams = mainGraphParams();
    $mainGraphTables = array(
        LFCY_TBL,
        ANO_TBL,
        FM_TBL,
        KW_TBL
    );
    $graphs = array();

    foreach ( $mainGraphTables as $tbl ) {
        $sg = array_search($tbl, $sgToDbTableMap);
        $sids = makeSrvList($sg, true);
        $file = $dbTblToGraphSetMap[$tbl];
        $sets = array_keys($graphParams, $tbl);
        $params = array( 'sids' => $sids );
        foreach ( $sets as $set ) {
            getGraphsFromSet( $set, $graphs, AIM_DIR . $file, $params );
        }
    }

    plotGraphs($graphs);
}

function drawTable($tbl) {
    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable(
        AIM_DIR . $tbl,
        $tbl,
        $selfLink
    );
    echo $table->getTable();
    echo addLineBreak();
}

function altFlow($sg) {
    if ( $sg == IM_LCS ) {
        showLinks();
    }

    drawHeaderWithHelp(ucfirst($sg) . " Instrumentation", 1, $sg . "_instrumentation");
    $graphs = array();

    if ( $sg == IM_FM ) {
        $set = 'fmFlow';
        $file = 'fm';
    } elseif ( $sg == IM_GR ) {
        $set = 'grNonMain';
        $file = 'gr';
        $tbl = 'non_main_gr';
    } elseif ( $sg == IM_KN ) {
        $set = 'knNonMain';
        $file = 'kn';
        $tbl = 'non_main_kn';
    } elseif ( $sg == IM_AN ) {
        $set = 'anNonMain';
        $file = 'an';
        $tbl = 'non_main_an';
    } elseif ( $sg == IM_LCS ) {
        $set = 'lcNonMain';
        $file = 'lc';
        $tbl = 'non_main_lc';
    }

    $sids = makeSrvList($sg, true);
    $params = array( 'sids' => $sids );
    getGraphsFromSet( $set, $graphs, AIM_DIR . $file, $params );
    plotGraphs($graphs);

    if ( $sg != IM_FM ) {
        drawTable( $tbl );
    }
    if ( $sg == IM_LCS ) {
        lcFlow();
    }
}

function lcFlow() {
    global $statsDB;

    drawHeader('Active Incidents by Data Source', HEADER_1, 'ActiveIncidentsDataSource');
    $graphs = array();
    $instances = getInstances( LFCY_TBL );
    foreach ( $instances as $inst ) {
        $params = array( "hostname" => $inst );
        $modelledGraph = new ModelledGraph( AIM_DIR . 'aibds' );
        $graphs[] = $modelledGraph->getImage($params, null, null, 640, 320);
    }
    plotGraphs($graphs);

    if ( $statsDB->hasData(LFCY_TBL) ) {
        enrichmentEvents();
        nbiInfo();
    }
    if ( $statsDB->hasData(FMNBI_TBL) ) {
        fmNbiInfo();
    }
    if ( $statsDB->hasData('enm_aim_measurement_training') ) {
        plotMeasurementTable();
    }
    if ( $statsDB->hasData('enm_aim_node_training_status') ) {
        drawHeader( 'Node Training', 1, "Node_Training_Graph");
        $graphs = array();
        getGraphsFromSet( 'all', $graphs, AIM_DIR . 'nodes');
        plotGraphs($graphs);
    }
    if ( $statsDB->hasData(TECH_TABLE) ) {
        kpiTraining();
    }
}

function mainFlow() {
    global $sgToDbTableMap;

    $sgs = array_keys($sgToDbTableMap);
    $links = mainLinks( $sgs );
    echo makeHTMLList($links);

    drawHeaderWithHelp("Autonomic Incident Management Instrumentation", 1, "main_instrumentation");

    //Draw Graphs
    plotMainGraphs();
    echo addLineBreak(2);

    //Draw Tables
    $tables = array(
        'main_lc',
        'main_gr',
        'main_fm'
    );
    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    foreach ( $tables as $tbl ) {
        $table = new ModelledTable(
            AIM_DIR . $tbl,
            $tbl,
            $selfLink
        );
        echo $table->getTable();
        echo addLineBreak();
    }
}

$plot = requestValue('plot');
$selected = requestValue('selected');
$action = requestValue('action');
if ( $action ) {
    $stringParts = explode("-", $action);
    $sids = requestValue('selected');
    $params = array( "sids" => $sids );
    $file = AIM_DIR . $stringParts[0];
    $set = $stringParts[1];
    getGraphsFromSet( $set, $graphs, $file, $params );
    plotGraphs($graphs);
} elseif ( isset($sg) ) {
    altFlow($sg);
} elseif ( $plot === 'measurementGraph' ) {
    plotMeasurementGraph($selected);
} elseif ( $plot === 'kpiGraphs' ) {
    plotKPIGraph($selected);
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
