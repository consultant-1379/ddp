<?php
$pageTitle = "Modelling";

require_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

require_once 'HTML/Table.php';

const MODEL_COUNT = 'modelcount';
const MODELING_CACHE_USAGE = "Modeling Cache Usage";

function showClientStats($statsDB, $srvGrpsStr) {
    global $site, $date;

    $quotedStr = implode("','", explode(",", $srvGrpsStr));
    $statsDB->query("
SELECT enm_servicegroup_instances.serverid
FROM enm_servicegroup_instances, sites, enm_servicegroup_names
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serviceid = enm_servicegroup_names.id AND enm_servicegroup_names.name IN ('$quotedStr')");
    $srvIds = array();
    while ( $row = $statsDB->getNextRow() ) {
        $srvIds[] = $row[0];
    }

    $idStrs = implode(",", $srvIds);
    drawHeader(MODELING_CACHE_USAGE, 2, "cacheUsage");

    $dbTables = array( "enm_shmmodeling_instr", StatsDB::SITES, StatsDB::SERVERS );
    $cols = array(
        'cacheSize' => 'CACHE SIZE',
        'cachemisses' => 'CACHE MISSES',
        'cacheRequests' => 'CACHE REQUESTS', MODEL_COUNT => 'MODEL COUNT',
        '((cacheRequests - cacheMisses)/cacheRequests * 100)' => 'Cache Hit %',
        'cacheDescriptionTexts' => 'CACHE DESCRIPTION TEXTS',
        'maxIdleTimeInCache' => 'MAX CACHE IDLE TIME (sec)',
        'maxCacheSize' => 'MAX CACHE SIZE',
        'cacheEvictions' => 'CACHE EVICTIONS',
        'readWriteRatio' => 'READWRITE RATIO'
    );
    $where = <<<EOQ
enm_shmmodeling_instr.siteid = sites.id AND sites.name = '%s' AND
enm_shmmodeling_instr.serverid = servers.id AND enm_shmmodeling_instr.serverid IN (%s)
EOQ;
    $graphTable = new HTML_Table('border=0');
    $sqlParamWriter = new SqlPlotParam();
    foreach ( $cols as $dbCol => $label ) {
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title($label)
                  ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                  ->yLabel('')
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array( $dbCol => $label ),
                      $dbTables,
                      $where,
                      array( 'site', 'srvids' ),
                      'servers.hostname'
                  )
                  ->makePersistent()
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $link = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            800,
            320,
            "srvids=$idStrs"
        );
        $graphTable->addRow(array($link));
    }
    echo $graphTable->toHTML();
}

function mdtExecutionGraphs() {
    return array (
        'time_metrics',
        'model_metrics',
        'model_jar_metrics'
    );
}

function makeGraphs($params) {
    $graphs = array();
    foreach ( $params as $graphParam ) {
        $modelledGraph = new ModelledGraph( 'TOR/platform/' . $graphParam);
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs( $graphs );
    echo addLineBreak(2);
}

function plotModelFileReadMetrics($selected) {
    $graphs = array();
    $graphParam = array('serverids' => $selected);

    getGraphsFromSet('fileRead', $graphs, 'TOR/platform/enm_modeling_fileread', $graphParam);
    plotGraphs( $graphs );
}

function showLinks() {
    global $statsDB;

    $modelURLs = array();
    $modelURLs[] = makeAnchorLink("tmi", "NED Topology Service TMI Operations");
    $modelURLs[] = makeAnchorLink("swsync", 'NED Topology Service Software Syncs');
    $modelURLs[] = makeLink( '/TOR/platform/modelling.php', MODELING_CACHE_USAGE, array( 'plot' => 'MCU' ) );
    if ( $statsDB->hasData("enm_modeling_fileread_instr") ) {
        $modelURLs[] = makeAnchorLink("modelingFileRead", 'Modeling File Read Metrics');
    }
    echo makeHTMLList($modelURLs);
}

function plotModellingCacheUsage() {
    $params = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( "TOR/platform/modelling_cache_usage", 'modeling_stats', $params );
    echo $table->getTableWithHeader(MODELING_CACHE_USAGE);
}

function mainFlow() {
    showLinks();

    $table = new ModelledTable( "TOR/platform/enm_mdt_execution", 'mdtexecutions' );
    echo $table->getTableWithHeader("MDT Executions");
    echo addLineBreak(2);

    $graphparams = mdtExecutionGraphs();
    makeGraphs($graphparams);

    $table = new ModelledTable( "TOR/platform/enm_ned_tmi", 'tmi' );
    echo $table->getTableWithHeader("NED Topology Service TMI Operations");

    $table = new ModelledTable( "TOR/platform/enm_ned_swsync", 'swsync' );
    echo $table->getTableWithHeader("NED Topology Service Software Syncs");

    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable("TOR/platform/enm_modeling_fileread", "modelingFileRead", $selfLink);
    echo $table->getTableWithHeader("Modeling File Read Metrics");
}

$statsDB = new StatsDB();
$action = requestValue('plot');

if ( isset($_REQUEST['client'] ) ) {
    showClientStats($statsDB, $_REQUEST['selected']);
} elseif ( $action ) {
    if ( $action === 'modelFile' ) {
        $selected = requestValue('selected');
        plotModelFileReadMetrics($selected);
    } elseif ( $action === 'MCU' ) {
        plotModellingCacheUsage();
    } else {
        echo "Error: Action $action is unknown";
    }
} else {
    mainFlow();
}

$statsDB->disconnect();

require_once PHP_ROOT . "/common/finalise.php";
