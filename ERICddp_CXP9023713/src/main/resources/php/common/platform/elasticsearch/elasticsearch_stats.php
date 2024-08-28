<?php

const SERVICE_TYPE = 'servicetype';
const ELASTICSEARCH = 'elasticsearch';
const ESHISTORY = 'eshistory';

if ( $_REQUEST[SERVICE_TYPE] === ELASTICSEARCH ) { //NOSONAR
    $pageTitle = "Elasticsearch Stats";
} elseif ( $_REQUEST[SERVICE_TYPE] === ESHISTORY ) { //NOSONAR
    $pageTitle = "Eshistory Stats";
}


$YUI_DATATABLE = true;

include_once "../../../common/init.php";

require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

require_once 'HTML/Table.php';

const SERVER_ID = 'serverid';
const INDICES = 'indices';
const THREAD_POOL = 'threadpool';
const FILESYSTEM = 'filesystem';

function threadPool() {
    global $serverId, $selected, $servicetype;

    $graphs = array();
    if ( ! is_null($serverId) ) {
        $graphParam = array( 'name' => $selected, SERVICE_TYPE => $servicetype, SERVER_ID => $serverId);
        getGraphsFromSet( 'all', $graphs, 'common/platform/elasticsearch/cloud_native_threadpool_graphs', $graphParam);
    } else {
        $graphParam = array( 'name' => $selected, SERVICE_TYPE => $servicetype);
        getGraphsFromSet( 'all', $graphs, 'common/platform/elasticsearch/threadpool_graphs', $graphParam);
    }
    plotGraphs( $graphs );

}

function indiceGraphs() {
    global $serverId, $servicetype;
    $graph = array();
    $graphParam = array(SERVICE_TYPE => $servicetype);
    drawHeaderWithHelp("Indices", 1, $servicetype.INDICES);
    if ( ! is_null($serverId) ) {
        $graphParam = array(SERVICE_TYPE => $servicetype, SERVER_ID => $serverId);
        getGraphsFromSet( INDICES, $graph, 'common/platform/elasticsearch/cloud_native_indices', $graphParam);
    } else {
        getGraphsFromSet( INDICES, $graph, 'common/platform/elasticsearch/indices', $graphParam);
    }
    plotGraphs( $graph );
}

function filesystemGraphs() {
    global $serverId, $servicetype;

    if ( ! is_null($serverId) ) {
        $graph = array();
        drawHeaderWithHelp( "Filesystem", 1, $servicetype.FILESYSTEM );
        $graphParam = array( SERVICE_TYPE => $servicetype, SERVER_ID => $serverId );
        getGraphsFromSet( FILESYSTEM, $graph, 'common/platform/elasticsearch/cloud_native_filesystem', $graphParam );
        plotGraphs( $graph );
    }
}

function threadpoolTableAndGraph() {
    global $serverId, $servicetype;
    $graphs = array();
    $urlParams =  makeSelfLink() . "&servicetype=$servicetype";
    if ( ! is_null($serverId) ) {
        $graphParam = array(SERVICE_TYPE => $servicetype, SERVER_ID => $serverId);
        $params = array( ModelledTable::URL => $urlParams, SERVICE_TYPE => $servicetype, SERVER_ID => $serverId);
        $tbl = new ModelledTable(
            'common/platform/elasticsearch/cloud_native_threadpool',
            "Table_$servicetype",
            $params
        );

        getGraphsFromSet(
            'comp',
            $graphs,
            'common/platform/elasticsearch/cloud_native_threadpool_completed',
            $graphParam
        );
    } else {
        $graphParam = array(SERVICE_TYPE => $servicetype);
        $params = array( ModelledTable::URL => $urlParams, SERVICE_TYPE => $servicetype);
        $tbl = new ModelledTable( 'common/platform/elasticsearch/threadpool', "Table_$servicetype", $params );

        getGraphsFromSet( 'comp', $graphs, 'common/platform/elasticsearch/threadpool_completed', $graphParam);
    }

    drawHeaderWithHelp("Threadpools", 1, $servicetype.THREAD_POOL);
    echo $tbl->getTable();
    echo addLineBreak(2);
    plotGraphs( $graphs );
}

function indicesLogDataGraphs() {
    global $selected;
    $graphs = array();
    $graphParam = array( 'name' => $selected);

    getGraphsFromSet( 'indicesLog', $graphs, 'TOR/platform/enm_eshistory_index_data', $graphParam);
    plotGraphs( $graphs );
}

function showLinks( $hasIndicesStats ) {
    global $statsDB, $servicetype;

    if ( $hasIndicesStats ) {
        $hoverOverText = 'Click here to go to the "Indices" stats in this page.';
        $links[] = makeAnchorLink($servicetype.INDICES, "Indices", $hoverOverText);
    }

    $hoverOverText = 'Click here to go to the "Threadpools" stats in this page.';
    $links[] = makeAnchorLink($servicetype.THREAD_POOL, "Threadpools", $hoverOverText);
    if ( $servicetype === ESHISTORY ) {
        $links[] = makeAnchorLink("eshistory_indices_data", "Indices Log Data");
    }
    if ( $servicetype === ELASTICSEARCH && $statsDB->hasData( 'elasticsearch_filesystem' ) ) {
        $hoverOverText = 'Click here to go to the "Filesystem" stats in this page.';
        $links[] = makeAnchorLink($servicetype.FILESYSTEM, "Filesystem", $hoverOverText);
    }
    echo makeHTMLList($links);
}

function mainFlow() {
    global $date, $site, $statsDB, $serverId, $serverName, $servicetype, $heading, $datadir;

    echo "<H1>$heading  Stats";
    if ( ! is_null($serverName)) {
        echo ": $serverName";
    }
    echo "</H1>\n";

    // In cENM only the data nodes have indices stats
    $hasIndicesStats = true;
    if (! is_null($serverId) ) {
        $hasIndicesStats = $statsDB->hasData(
            "elasticsearch_indices",
            "time",
            false,
            "elasticsearch_indices.serverid = $serverId"
        );
    }
    showLinks( $hasIndicesStats );

    echo "<ul>";
    if ( $servicetype === ELASTICSEARCH ) {
        $hoverOverText = 'Click here to go to the "JVM Data" stats in this page.';
        echo " <li><a title='$hoverOverText' href='#genericJmxHelp_anchor'>JVM Data</a></li>\n";
    }

    foreach (array("TOR/clustered_data/$servicetype", $servicetype) as $subDir) {
        if ( $servicetype === ELASTICSEARCH ) {
            $esIndicesFilePath = $datadir . "/" . $subDir . "/ES_indices.log";
        } else {
            $esIndicesFilePath = $datadir . "/" . $subDir . "/es_history_indices.log";
        }
        if ( file_exists($esIndicesFilePath) ) {
            $hoverOverText = 'Click here to view the output of' . $servicetype . '"indices" command collected by DDC.';
            $href = getUrlForFile($esIndicesFilePath);
            echo "<li><a title='$hoverOverText' href='$href'\>View $heading Indices Log</a></li>\n";
        }
    }

    echo "</ul>\n";

    if ( $hasIndicesStats ) {
        indiceGraphs();
    }

    threadpoolTableAndGraph();
    if ( $statsDB->hasData( 'elasticsearch_filesystem' ) ) {
        filesystemGraphs();
    }

    if ($servicetype === ELASTICSEARCH) {
        drawHeaderWithHelp("JVM Data", 2, "genericJmxHelp", "DDP_Bubble_194_Generic_JMX_Help");
        $genJMX = new GenericJMX($statsDB, $site, $serverName, ELASTICSEARCH, $date, $date);
        echo $genJMX->getGraphTable()->toHTML();
    } elseif ($servicetype === ESHISTORY) {
        drawHeader('Indices Log Data', 1, 'eshistory_indices_data');
        $params = array( ModelledTable::URL => makeSelfLink());

        $table = new ModelledTable( 'TOR/platform/enm_eshistory_index_data', "table", $params );
        echo $table->getTable();
        echo addLineBreak();
    }
}

$serverId = requestValue( SERVER_ID );
if ( ! is_null($serverId) ) {
    $serverName = $statsDB->queryRow("SELECT hostname FROM servers WHERE id = $serverId")[0];
} else {
    $serverName = null;
}

$action = requestValue('plot');
$selected = requestValue('selected');
$servicetype = requestValue(SERVICE_TYPE);
$heading = ucfirst($servicetype);

$indexName = requestValue('index_name');

if ( is_null($selected) ) {
    // If 'index_name' is mentioned then redirect to 'Elasticsearch Stats'
    // page of the given index date, only if required
    if ( isset($indexName) && preg_match('/(\d\d)(\d\d).(\d\d).(\d\d)/', $indexName, $matches) ) {
        $index_date = $matches[1] . $matches[2]  . '-' . $matches[3] . '-' . $matches[4];
        $index_dir = $matches[4] . $matches[3] . $matches[2];
        if ( $index_date != $date ) {
            $webargs = preg_replace('/date=([^&]*)/', "date={$index_date}", $webargs);
            $webargs = preg_replace('/dir=([^&]*)/', "dir={$index_dir}", $webargs);
            $redirectionUrl = $makeSelfLink() . "?" . $webargs;
            echo "<script>location.href='$redirectionUrl';</script>";
        }
    }
    mainFlow();
} elseif ($action === THREAD_POOL) {
    threadPool();
} elseif ($action === 'indexdata') {
    indicesLogDataGraphs();
}

include_once PHP_ROOT . "/common/finalise.php";
