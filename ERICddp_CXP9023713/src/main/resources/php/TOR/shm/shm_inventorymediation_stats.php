<?php
$pageTitle = "SHM Inventory Mediation";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const PROCESS_TIME_TAKEN_FOR_CONVERTING = 'processTimeTakenForConverting';
const PROCESS_TIME_TAKEN_FOR_NODE_RESPONSE = 'processTimeTakenForNodeResponse';
const PROCESS_TIME_TAKEN_FOR_ORDER_INVENTORY = 'processTimeTakenForOrderInventory';
const PROCESS_TIME_TAKEN_FOR_PARSING = 'processTimeTakenForParsing';
const PROCESS_TIME_TAKEN_FOR_PERSISTING_INTO_DPS = 'processTimeTakenForPersistingIntoDPS';
const PROCESS_TIME_TAKEN_FOR_RETRIEVE_INVENTORY_XML = 'processTimeTakenForRetrieveInventoryXml';
const TIME_MILLISEC = 'Time (MilliSec)';
const INVENTORY_MEDIATION_PARALLEL_INVOCATIONS = 'inventoryMediationParallelInvocations';
const INVENTORY_MEDIATION_TIME_TAKEN = 'inventoryMediationTimeTaken';
const INCREASE_INVENTORY_MEDIATION_INVOCATIONS = 'increaseInventoryMediationInvocations';
const CNT = 'Count';

function tableParams() {
    return array(
        'SUM(inventorySyncInvocations)' =>
        'Number of active Inventory Mediation Invocations',
        'SUM(inventoryUnsyncInvocations)' =>
        'Number of passive Inventory Mediation Invocations',
        'SUM(increaseInventoryMediationInvocations)' =>
        'Completed Inventory Mediation Invocations',
        'ROUND(AVG(processTimeTakenForOrderInventory / orderInventoryProcessSuccessCount))' =>
        'Average Time Taken For Order Inventory',
        'ROUND(AVG(processTimeTakenForNodeResponse / nodeResponseProcessSuccessCount))' =>
        'Average Time Taken For Node Response',
        'ROUND(AVG(processTimeTakenForParsing / xmlParsingProcessSuccessCount))' =>
        'Average Time Taken For Parsing',
        'ROUND(AVG(processTimeTakenForRetrieveInventoryXml / inventoryXmlProcessSuccessCount))' =>
        'Average Time Taken For Inventory XML Retrieval',
        'ROUND(AVG(processTimeTakenForConverting / conversionProcessSuccessCount))' =>
        'Average Time Taken For Conversion',
        'ROUND(AVG(processTimeTakenForPersistingIntoDPS / persistingIntoDPSProcessSuccessCount))' =>
        'Average Time Taken For Persisting into DPS'
    );
}

function graphParams() {
    return array(
        INCREASE_INVENTORY_MEDIATION_INVOCATIONS => array(
            SqlPlotParam::TITLE => 'Inventory Mediation Invocations',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                INCREASE_INVENTORY_MEDIATION_INVOCATIONS => 'Inventory Mediation Invocations'
            )
        ),
        INVENTORY_MEDIATION_TIME_TAKEN => array(
            SqlPlotParam::TITLE => 'Inventory Mediation Time Taken',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                INVENTORY_MEDIATION_TIME_TAKEN => 'Inventory Mediation Time Taken'
            )
        ),
        PROCESS_TIME_TAKEN_FOR_CONVERTING => array(
            SqlPlotParam::TITLE => 'Time Taken For Converting',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                PROCESS_TIME_TAKEN_FOR_CONVERTING => 'Time Taken For Converting'
            )
        ),
        PROCESS_TIME_TAKEN_FOR_NODE_RESPONSE => array(
            SqlPlotParam::TITLE => 'Time Taken For Node Response',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                PROCESS_TIME_TAKEN_FOR_NODE_RESPONSE => 'Time Taken For Node Response'
            )
        ),
        PROCESS_TIME_TAKEN_FOR_ORDER_INVENTORY => array(
            SqlPlotParam::TITLE => 'Time Taken For Order Inventory',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                PROCESS_TIME_TAKEN_FOR_ORDER_INVENTORY => 'Time Taken For Order Inventory'
            )
        ),
        PROCESS_TIME_TAKEN_FOR_PARSING => array(
            SqlPlotParam::TITLE => 'Time Taken For Parsing',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                PROCESS_TIME_TAKEN_FOR_PARSING => 'Time Taken For Parsing'
            )
        ),
        PROCESS_TIME_TAKEN_FOR_PERSISTING_INTO_DPS => array(
            SqlPlotParam::TITLE => 'Time Taken For Persisting Into DPS',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                PROCESS_TIME_TAKEN_FOR_PERSISTING_INTO_DPS => 'Time Taken ForPersisting Into DPS'
            )
        ),
        PROCESS_TIME_TAKEN_FOR_RETRIEVE_INVENTORY_XML => array(
            SqlPlotParam::TITLE => 'Time Taken For Retrieve Inventory Xml',
            SqlPlotParam::Y_LABEL => TIME_MILLISEC,
            'cols' => array(
                PROCESS_TIME_TAKEN_FOR_RETRIEVE_INVENTORY_XML => 'Time Taken For Retrieve Inventory Xml'
            )
        ),
        INVENTORY_MEDIATION_PARALLEL_INVOCATIONS => array(
            SqlPlotParam::TITLE => 'Inventory Mediation Parallel Invocations',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                INVENTORY_MEDIATION_PARALLEL_INVOCATIONS => 'Inventory Mediation Parallel Invocations'
            )
        ),
        'ongoingInventoryMediationSyncs' => array(
            SqlPlotParam::TITLE => 'Ongoing Inventory Mediation Syncs',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'ongoingInventoryMediationSyncs' => 'Ongoing Inventory Mediation Syncs'
            )
        ),
    );
}

function drawTables() {
    global $date, $statsDB;

    drawHeader("Daily Totals", 1, "InstrHelp");

    $params = tableParams();

    $where = $statsDB->where('shm_inventorymediation_instr');
    $where .= " AND shm_inventorymediation_instr.serverid = servers.id
                GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
            ->name("totals")
            ->tables(array('shm_inventorymediation_instr', StatsDB::SITES,StatsDB::SERVERS))
            ->where($where)
            ->addSimpleColumn( "IFNULL(servers.hostname, 'Totals')", 'Instance');

    foreach ( $params as $col => $title ) {
        $table->addSimpleColumn($col, $title);
    }
    echo $table->build()->getTable();
}

function shmInventoryMediationGraphs() {
    global $date;

    $sqlParamWriter = new SqlPlotParam();

    $instrGraphParams = graphParams();
    $graphs = array();

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = array(
            SqlPlotParam::TITLE => $instrGraphParam[SqlPlotParam::TITLE],
            'type' => 'tsc',
            'sb.barwidth' => 60,
            SqlPlotParam::Y_LABEL => $instrGraphParam[SqlPlotParam::Y_LABEL],
            'useragg' => 'true',
            'persistent' => 'false',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => $instrGraphParam['cols'],
                    'tables' => "shm_inventorymediation_instr, sites, servers",
                    "multiseries"=> "servers.hostname",
                    'where' => "shm_inventorymediation_instr.siteid = sites.id AND sites.name = '%s' AND
                               shm_inventorymediation_instr.serverid = servers.id",
                    'qargs' => array('site')
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320));
    }
    plotGraphs($graphs);
}

function displaylrflogDetails() {
    global $statsDB;
    $where =  $statsDB->where("enm_shm_lrf_logs");
    $queryTable = SqlTableBuilder::init()
           ->name("node_lrf_generation")
           ->tables(array("enm_shm_lrf_logs", StatsDB::SITES))
           ->where($where)
           ->addSimpleColumn('TIMEDIFF(time(time), SEC_TO_TIME(ROUND(totalTimeTaken/1000)))', 'Start Time')
           ->addSimpleColumn('time(time)', 'End Time')
           ->addSimpleColumn('noOfNodes', 'Number Of Nodes')
           ->addSimpleColumn('noOfQuantityUpdates', 'Number Of Quantity Update Requests')
           ->addSimpleColumn('SEC_TO_TIME(ROUND(totalTimeTaken/1000))', 'Duration')
           ->addSimpleColumn('status', 'Status')
           ->addSimpleColumn('fileSize', 'FileSize')
           ->paginate()
           ->build();

    if ( $queryTable->hasRows() ) {
        drawHeader("Node LRF Generation", 1, "node_lrf_generation");
        echo $queryTable->getTable();
    }
}

$statsDB = new StatsDB();

function mainFlow() {

    drawTables();

    displaylrflogDetails();

    shmInventoryMediationGraphs();
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
