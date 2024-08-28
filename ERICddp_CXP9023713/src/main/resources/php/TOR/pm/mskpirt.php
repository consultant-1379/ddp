<?php

$pageTitle = "MSKPIRT";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

const BORDER_0 = "border=0";
const SITEANDSERV = ' sites, servers';
const CNT = 'count';
const QUERYLIST = 'querylist';
const ATTRIBUTE = 'attribute';
const KPISERVICE = 'kpiservice';

function showRealtimeCounterMediationMetrics() {
    global $date;
    /* Graphs  */
    $dbTable = "enm_mskpirt_instr";
    drawHeaderWithHelp("Realtime Counter Mediation", 1, "RealtimeCounterMediation");
    $graphParams = array(
       array( 'Number of Collected Counters' => 'numberOfCollectedCounters',
              'Number of Nodes Collected' => 'numberOfNodesCollected'),
       array( 'Number of Requests for All Nodes' => 'numberOfRequestsForAllNodes',
              'Number of Failed Collection Flows' => 'numberOfFailedCollectionFlows'),
       array( 'Average Flow Execution Time(ms)' => 'accumulatedFlowsProcessingTime/totalFlowsRanCount')
    );
    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id ";
    $graphTable = new HTML_Table('border=0');
    $sqlParamWriter = new SqlPlotParam();
    foreach ( $graphParams as $graphRow) {
        $row = array();
        foreach ( $graphRow as $title => $column ) {
            $sqlParam = SqlPlotParamBuilder::init()
                ->title($title)
                ->type(SqlPlotParam::STACKED_BAR)
                ->makePersistent()
                ->forceLegend()
                ->addQuery(
                    SqlPlotParam::DEFAULT_TIME_COL,
                    array( $column => $title ),
                    $dbTables,
                    $where,
                    array( 'site' ),
                    "servers.hostname"
                )
                ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $row[] =  $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 500, 320);
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site, $mskpirtservice;
    $genJmxLinks = array( AHREF . makeGenJmxLink("mskpirt") . "\">Generic JMX MSKPIRT</a>" );
    foreach ($genJmxLinks as $link) {
        $links[] = $link;
    }
    echo makeHTMLList($links);

    showRealtimeCounterMediationMetrics();
}

$statsDB = new StatsDB();
mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
