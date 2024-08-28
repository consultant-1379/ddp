<?php
$pageTitle = "SHM Axe Inventory";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const PARAMS = 'params';
const TABLE_NAME = 'TABLE_NAME';
const TOTAL_NO_OF_MEDIATION_INVOCATIONS = 'totalNoOfMediationInvocations';

function getParams($isMainTable) {
    $graphParams = array(
        array('Total Inventory Mediation Invocations' =>
              TOTAL_NO_OF_MEDIATION_INVOCATIONS,
              'Avg time taken to receive inventory response' =>
              'totalTimeTakenToReceiveInventoryResponse  / totalNoOfMediationInvocations'
        ),
        array('Avg time taken to Parse/Persist of HW Inv' =>
              'totalTimeTakenToParsePersistHwInventory / totalHwInventoryRequests',
              'Avg time taken to Parse/Persist of SW Inv' =>
              'totalTimeTakenToParsePersistSwInventory / totalSwInventoryRequests'
        ),
        array('Avg time taken to Parse/Persist of Backup Inv' =>
              'totalTimeTakenToParsePersistBackupInventory / totalBackupInventoryRequests',
              'Avg time taken to Parse/Persist of License Inv' =>
              'totalTimeTakenToParsePersistLicenseInventory / totalLicenseInventoryRequests')
        );

    if ($isMainTable) {
        $extraGraphParams =
            array(
                'Avg time taken to Inventory Sync' =>
                'totalTimeTakenForInventorySync / totalNoOfMediationInvocations',
                'Synchronized nodes' => 'synchronizedNodes',
                'Unsynchronized nodes' => 'unSynchronizedNodes')
        ;
    } else {
        $extraGraphParams =
            array('Avg time taken to Inventory Sync' =>
                'totalTimeTakenForInventorySync / totalNoOfMediationInvocations');
    }
    array_push($graphParams, $extraGraphParams);
    return array(TABLE_NAME => "CounterTotals", PARAMS => $graphParams);
}

function drawTables($dbTable, $params) {
    global $date, $site;

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '$site'
              AND $dbTable.serverid = servers.id
              AND $dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY hostname WITH ROLLUP";

    $builder = SqlTableBuilder::init()
        ->name($params[TABLE_NAME])
        ->tables(array( $dbTable, StatsDB::SITES, StatsDB::SERVERS ))
        ->where($where)
        ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    $prms = $params[PARAMS];

    $numeratorParams = array(
        'totalTimeTakenToReceiveInventoryResponse',
        'totalTimeTakenToParsePersistHwInventory',
        'totalTimeTakenToParsePersistSwInventory',
        'totalTimeTakenToParsePersistBackupInventory',
        'totalTimeTakenToParsePersistLicenseInventory',
        'totalTimeTakenForInventorySync'
    );
    $dividerParams = array(
            TOTAL_NO_OF_MEDIATION_INVOCATIONS,
            'totalHwInventoryRequests',
            'totalSwInventoryRequests',
            'totalBackupInventoryRequests',
            'totalLicenseInventoryRequests',
            TOTAL_NO_OF_MEDIATION_INVOCATIONS
    );

    $index = 0;
    foreach ($prms as $param) {
        foreach ($param as $key => $value) {
            if (strpos($key, 'Avg') !== false) {
                $builder->addSimpleColumn("ROUND(SUM($numeratorParams[$index]) / SUM($dividerParams[$index]))", $key);
                $index ++;
            } else {
                $builder->addSimpleColumn("SUM($value)", $key);
            }
        }
    }
    drawHeaderWithHelp('Counter Totals', 2, 'instr', '', '');
    echo $builder->build()->getTable();
    echo addLineBreak(1);
}

function plotGraph($graphParams, $dbTable) {
    drawHeaderWithHelp("SHM AXE Inventory Mediation", 2, 'instr', '', '');
    $params = $graphParams[PARAMS];
    $graphTable = new HTML_Table("border=0");
    foreach ($params as $graphParam) {
        $row = array();
        foreach ($graphParam as $key => $value) {
            if (strpos($key, 'Total') !== false) {
                $metric = 'Count';
            } else {
                $metric = 'Milliseconds';
            }
            $row[] = generateGraph($value, $key, $dbTable, $metric);
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
    echo addLineBreak(2);
}

function generateGraph($col, $title, $dbTable, $metric) {
    global $date;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '%s'
              AND $dbTable.serverid = servers.id";

    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
        ->title($title)
        ->type(SqlPlotParam::STACKED_BAR)
        ->barwidth(60)
        ->yLabel($metric)
        ->makePersistent()
        ->forceLegend()
        ->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            array ($col => $title),
            $dbTables,
            $where,
            array('site'),
            'servers.hostname'
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 550, 320);
}

function mainFlow($dbTable) {
    global $date, $statsDB;

    echo '<H1>Daily Totals</H1>';
    if ( $statsDB->hasData($dbTable) ) {
        $params = getParams(true);
        drawTables($dbTable, $params);
        $params = getParams(false);
        plotGraph($params, $dbTable);
    } else {
        echo "<H1>No Data Available In $dbTable For $date<H1>";
    }
}

mainFlow('enm_shm_axe_inventory');

include_once PHP_ROOT . "/common/finalise.php";
