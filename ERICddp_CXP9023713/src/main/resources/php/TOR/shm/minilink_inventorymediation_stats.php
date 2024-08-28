<?php
$pageTitle = "MINI-LINK Inventory";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const LABEL = 'ylabel';
const TITLE = 'title';
const MILLISEC = 'Time (MilliSec)';

$MINILINK_TABLE = "minilink_inventorymediation_instr";

function minilinkInventoryMediationTotals($statsDB) {
    global $MINILINK_TABLE;
    $where = $statsDB->where($MINILINK_TABLE) . 'AND
             minilink_inventorymediation_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP';

    $table = SqlTableBuilder::init()
           ->name("minilink_dailyTotals")
           ->tables(array($MINILINK_TABLE, StatsDB::SITES, StatsDB::SERVERS))
           ->where($where)
           ->addSimpleColumn( "IFNULL(servers.hostname, 'Totals')", 'Instance')
           ->addSimpleColumn('SUM(increaseInventoryMediationInvocations)', 'Total Inventory Mediation Invocations')
           ->addSimpleColumn(
               'ROUND(SUM(processTimeTakenForNodeResponse)/SUM(increaseInventoryMediationInvocations), 0)',
               'Average Time Taken for NodeResponse (millisec)'
             )
           ->addSimpleColumn(
               'ROUND(SUM(processTimeTakenForParsing)/SUM(increaseInventoryMediationInvocations), 0)',
               'Average Time Taken for Parsing (millisec)'
             )
           ->addSimpleColumn(
               'ROUND(SUM(processTimeTakenForPersistingIntoDPS)/SUM(increaseInventoryMediationInvocations), 0)',
               'Average Time Taken for PersistingIntoDPS (millisec)'
             )
           ->build();
    echo $table->getTableWithHeader("Daily Totals", 2, "", "", "minilink_dailyTotals");
}

function getInstrParams() {
    return array(
        'increaseInventoryMediationInvocations' => array(
            TITLE => 'Inventory Mediation Invocations',
            LABEL => 'Count'
        ),
        'processTimeTakenForNodeResponse' => array(
            TITLE => 'Time Taken For Node Response',
            LABEL => MILLISEC
        ),
        'processTimeTakenForParsing' => array(
            TITLE => 'Time Taken For Parsing',
            LABEL => MILLISEC
        ),
        'processTimeTakenForPersistingIntoDPS' => array(
            TITLE => 'Time Taken For Persisting Into DPS',
            LABEL => MILLISEC
        )
    );
}

function plotInstrGraphs($instrGraphParam) {
    global $date, $MINILINK_TABLE;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    drawHeaderWithHelp("MINI-LINK Inventory Mediation", 2, "minilinkInventoryMediationHelp");

    $where = " $MINILINK_TABLE.siteid = sites.id AND sites.name = '%s' AND  $MINILINK_TABLE.serverid = servers.id";

    $dbTables = array( $MINILINK_TABLE, StatsDB::SITES, StatsDB::SERVERS );

    foreach ( $instrGraphParam as $key => $instrGraphParamName ) {
        $row = array();
        $title = $instrGraphParamName[TITLE];
        $sqlParam = SqlPlotParamBuilder::init()
             ->title($title)
             ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
             ->yLabel($instrGraphParamName[LABEL])
             ->forceLegend()
             ->makePersistent()
             ->addQuery(
                 SqlPlotParam::DEFAULT_TIME_COL,
                 array ($key => $title),
                 $dbTables,
                 $where,
                 array('site'),
                 SqlPlotParam::SERVERS_HOSTNAME
               )
             ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    $statsDB = new StatsDB();
    minilinkInventoryMediationTotals($statsDB);
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($instrGraphParams);
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
