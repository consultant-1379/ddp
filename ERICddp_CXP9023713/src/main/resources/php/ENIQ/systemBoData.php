<?php
$pageTitle = "System Bo Performance";

include_once "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

const MEMORY = "Memory Usage (MB)";
const ENIQ_SYSTEM_BO = "eniq_system_bo";

function prepareGraph($title, $ylabel, $whatcol, $tableName) {
    global $date;
    $tables = $tableName . ', sites';
    $sqlParam =
        array(  'title'  => $title,
                'ylabel'     => $ylabel,
                'type'       => 'tsc',
                'presetagg'  => 'AVG:Hourly',
                'useragg'    => 'true',
                'persistent' => 'true',
                'querylist'  =>
                array(
                    array(
                        'timecol' => 'time',
                        'whatcol' => $whatcol,
                        'tables'  => $tables,
                        'where'   => "$tableName.siteid = sites.id AND sites.name = '%s'",
                        'qargs'   => array( 'site' )
            )
        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 540, 240);
}


function createTable( $params, $name, $title ) {
    global $statsDB;

    drawHeaderWithHelp($title, 2, $name);

    $where = $statsDB->where(ENIQ_SYSTEM_BO);
    $where .= " AND servers.id = eniq_system_bo.serverid
                GROUP BY eniq_system_bo.pid";

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables(array(ENIQ_SYSTEM_BO, 'sites', 'servers'))
            ->where($where);

    foreach ($params as $key => $value) {
        $table->addSimpleColumn($value, $key);
    }

    echo $table->paginate( array(20, 100, 1000, 10000) )
               ->build()
               ->getTable();
}

function positionGraphs( $graphs ) {

    $graphTable = new HTML_Table("border=0");
    foreach ( $graphs as $graph ) {
        $row[] = $graph;
    }
    $graphTable->addRow($row);

    echo $graphTable->toHTML();
}

function mainFlow() {

    drawHeaderWithHelp("CPU Usage(BO Processes vs Total)", 2, "SystemBOHelp");
    $cpuBOGraph = prepareGraph(
        "CPU Usage(BO Process)",
        "CPU Usage (%)",
        array('eniq_system_bo.cpu' => 'BO Process'),
        ENIQ_SYSTEM_BO
    );
    $cpuTotalGraph = prepareGraph(
        "CPU Usage(Total)",
        "CPU Usage (%)",
        array('eniq_system_bo_all.cpu' => 'Total'),
        "eniq_system_bo_all"
    );

    positionGraphs( array($cpuBOGraph, $cpuTotalGraph) );

    $cpuTableParams = array(
                        'Process Name' => 'eniq_system_bo.name',
                        'Description' => 'eniq_system_bo.description',
                        'Process Start Time' => 'eniq_system_bo.processTime',
                        'CPU Utilization (%)' => 'CAST(eniq_system_bo.cpu AS decimal(15,2))'
                      );
    $name = 'Process_Wise_CPU';
    $title = 'Process Wise CPU Utilization(BO processes)';
    createTable( $cpuTableParams, $name, $title );

    drawHeaderWithHelp("Memory Usage(BO processes vs Total)", 2, "SystemBOGraphHelp");
    $memoryBOGraph = prepareGraph(
        "Memory Usage(BO Process)",
        MEMORY,
        array('ws' => 'Memory Usage(MB)'),
        ENIQ_SYSTEM_BO
    );
    $memoryTotalGraph = prepareGraph(
        "Memory Usage(Total)",
        MEMORY,
        array('ws' => 'Memory Usage(MB)'),
        "eniq_system_bo_all"
    );

    positionGraphs( array( $memoryBOGraph, $memoryTotalGraph) );

    $memoryTableParams = array(
                        'Process Name' => 'eniq_system_bo.name',
                        'Description' => 'eniq_system_bo.description',
                        'Process Start Time' => 'eniq_system_bo.processTime',
                        MEMORY  => 'CAST(eniq_system_bo.ws AS decimal(15,2))'
                         );
    $name = 'ProcessWiseMemory';
    $title = 'Process Wise Memory Utilization(BO processes)';
    createTable( $memoryTableParams, $name, $title );
}

$statsDB = new StatsDB();
mainFlow();
include_once "../common/finalise.php";
