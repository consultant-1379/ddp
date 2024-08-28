<?php
$pageTitle = "Spark Executor";

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const SERVER_IDS= 'serverids';
$serverIds = requestValue(SERVER_IDS);

function readWriteMBParams() {
    return array(
        'shuftotalmbread',
        'shufmbwritten',
        'shuflocalmbread',
        'shufremotembread',
        'shufremotembreadtodisk'
    );
}

function recordsParams() {
    return array(
        'shufrecordsread',
        'shufrecordswritten'
    );
}

function shuffleTimeParams() {
    return array(
        'shuffetchtime',
        'shufwritetime'
    );
}

function localBlocksParams() {
    return array(
        'shuflocalblocksfetched',
        'shufremoteblocksfetched'
    );
}

function threadpoolTaskParams() {
    return array(
        'tpactivetasks',
        'tpcompletetasks'
    );
}

function sparksGraph($sparkParams, $title, $id) {
    global $serverIds;

    drawHeader($title, 2, $id);
    $params = array( SERVER_IDS => $serverIds);
    foreach ( $sparkParams as $column ) {
        $modelledGraph = new ModelledGraph('common/spark_executor_' . $column);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs( $graphs );
}

function showLinks() {

    $links = array();
    $links[] = makeAnchorLink('readWriteShuffle', 'Read/Write');
    $links[] = makeAnchorLink('shuffleRecords', 'Shuffle Records');
    $links[] = makeAnchorLink('shuffleTime', 'Shuffle Time');
    $links[] = makeAnchorLink('localBlocks', 'Blocks');
    $links[] = makeAnchorLink('threadpoolTask', 'Threadpool Tasks');

    echo makeHTMLList($links);
}

function mainFlow() {
    global $serverIds;

    showLinks();
    $table = new ModelledTable('common/spark_executor', 'spark_executor', array(SERVER_IDS => $serverIds));
    echo $table->getTableWithHeader("Spark Executor Instances");
    echo addLineBreak();

    sparksGraph(readWriteMBParams(), 'Read/Write', 'readWriteShuffle');
    sparksGraph(recordsParams(), 'Shuffle Records', 'shuffleRecords');
    sparksGraph(shuffleTimeParams(), 'Shuffle Time', 'shuffleTime');
    sparksGraph(localBlocksParams(), 'Blocks', 'localBlocks');
    sparksGraph(threadpoolTaskParams(), 'Threadpool Tasks', 'threadpoolTask');
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
