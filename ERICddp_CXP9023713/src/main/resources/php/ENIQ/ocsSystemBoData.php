<?php

$pageTitle = 'OCS System Bo';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function ocsSystemBoCpuUsageGraph() {
    return array(
        'cpu_usage_id'
    );
}

function ocsSystemBoCpuTotalGraph() {
    return array(
        'all_cpu_total_id'
    );
}

function ocsSystemBoMemoryUsageGraph() {
    return array(
        'memory_usage_id'
    );
}

function ocsSystemBoMemoryTotalGraph() {
    return array(
        'all_memory_total_id'
    );
}

function getCpuMemoryGraph($graphParams, $id) {
    drawHeader($title, 2, $id);
    foreach ( $graphParams as $column ) {
        $modelledGraph = new ModelledGraph('ENIQ/ocs_system_bo_' . $column );
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs( $graphs );
}

function main() {

    drawHeader("CPU Usage(BO Processes vs Total)", 2, "cpuUsageGraphHelp");
    getCpuMemoryGraph(ocsSystemBoCpuUsageGraph(), 'systemBoCpuUsage');
    getCpuMemoryGraph(ocsSystemBoCpuTotalGraph(), 'systemBoCpuTotal');

    $table = new ModelledTable('ENIQ/ocs_system_bo_cpu_table', 'cpuUsageTableHelp');
    echo $table->getTableWithHeader("Process Wise CPU Utilization(BO processes)");
    echo addLineBreak();

    drawHeader("Process Wise Memory Utilization(BO processes)", 2, "memoryUsageGraphHelp");
    getCpuMemoryGraph(ocsSystemBoMemoryUsageGraph(), 'systemBoMemoryUsage');
    getCpuMemoryGraph(ocsSystemBoMemoryTotalGraph(), 'systemBoMemoryTotal');

    $table = new ModelledTable('ENIQ/ocs_system_bo_memory_table', 'memoryUsageTableHelp');
    echo $table->getTableWithHeader("Process Wise Memory Utilization(BO processes)");
    echo addLineBreak();
}

main();

include_once PHP_ROOT . "/common/finalise.php";
