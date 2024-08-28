<?php
$pageTitle = "Network Elements";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

require_once 'HTML/Table.php';

$CELLS = "'EUtranCellFDD','EUtranCellTDD','NbIotCell','GeranCell','NodeBLocalCell','RbsLocalCell','NRCellDU'";

const NE_DETAILS_TABLE = "enm_network_element_details";
const COUNT = 'count';
const NE_UP_TABLE = 'ne_up';
const NE_TYPE_NAME = 'ne_types.name';

function getCellsInVersant($statsDB,$site,$date,&$hasPlanned) {
    global $CELLS;
    $row = $statsDB->queryRow("
   SELECT COUNT(*)
FROM mo, sites, vdb_names
WHERE
 mo.date = '$date' AND
 sites.name = '$site' AND mo.siteid = sites.id AND
 mo.vdbid = vdb_names.id AND vdb_names.name = 'dps_integration'");
    $hasVersant = $row[0] > 0;

    if ( ! $hasVersant ) {
        return NULL;
    }

    # Check if we have the non-live counts being stored
    $row = $statsDB->queryRow("
   SELECT COUNT(*)
FROM mo, sites, vdb_names
WHERE
 mo.date = '$date' AND
 sites.name = '$site' AND mo.siteid = sites.id AND
 mo.vdbid = vdb_names.id AND vdb_names.name = 'dps_integration' AND
 mo.planned IS NOT NULL");
    $hasPlanned = $row[0] > 0;

    if($hasPlanned){
        $totalCellCount = "mo.count - mo.planned";
    } else {
        $totalCellCount = "mo.count";
    }

    $dataRows = array();
    $statsDB->query("
SELECT mo_names.name AS mo, model_names.name AS namespace, $totalCellCount AS count
FROM mo, sites, mo_names, model_names, vdb_names
WHERE
 mo.date = '$date' AND
 mo.siteid = sites.id AND sites.name = '$site' AND
 mo.moid = mo_names.id AND
 mo_names.name IN ($CELLS) AND
 mo.modelid = model_names.id AND
 mo.vdbid = vdb_names.id AND vdb_names.name = 'dps_integration'");

    while ( $row = $statsDB->getNextNamedRow() ) {
        $dataRows[] = $row;
    }

    return $dataRows;
}

function getCellsInNeo4j($statsDB,$site,$date,&$hasPlanned) {
    global $CELLS;
    $hasPlanned = TRUE;
    $dataRows = array();
    $statsDB->query("
SELECT
 mo_names.name AS mo,
 model_names.name AS namespace,
 (enm_neo4j_mocounts.total - enm_neo4j_mocounts.nonlive) AS count
FROM
    enm_neo4j_mocounts, sites, mo_names, model_names
WHERE
 enm_neo4j_mocounts.siteid = sites.id AND sites.name = '$site' AND
 enm_neo4j_mocounts.date = '$date' AND
 enm_neo4j_mocounts.motypeid = mo_names.id AND
 enm_neo4j_mocounts.namespaceid = model_names.id AND
 mo_names.id AND
 mo_names.name IN ($CELLS)
");
    while ( $row = $statsDB->getNextNamedRow() ) {
        $dataRows[] = $row;
    }
    return $dataRows;
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site, $statsDB;

    if ( $statsDB->hasData(NE_DETAILS_TABLE, "date", true, "releaseid IS NOT NULL") ) {
        $summaryModel = 'TOR/system/nodesummary';
    } else {
        $summaryModel = 'TOR/system/nodesummary_modelid';
    }
    $summaryTable = new ModelledTable($summaryModel, 'enm_network_element_details');
    echo $summaryTable->getTableWithHeader("Nodes Summary");

    $hasNonLiveCounts = TRUE;
    $dataRows = getCellsInVersant($statsDB,$site,$date,$hasNonLiveCounts);
    if ( is_null($dataRows) ) {
        $dataRows = getCellsInNeo4j($statsDB,$site,$date,$hasNonLiveCounts);
    }

    if($hasNonLiveCounts){
        $count_header = "Live Count";
    } else {
        $count_header = "Total Count(Live + Non-Live)";
    }

    # If we have one the non-live counts, we can display the cell counts

    # Add total Row
    $totalCount = 0;
    foreach ( $dataRows as $dataRow ) {
        if ($dataRow['mo'] == "NbIotCell") {
          $totalCount += ceil($dataRow[COUNT]/13);
        } else {
          $totalCount += $dataRow[COUNT];
        }
    }
    $dataRows[] = array('mo' => 'Total', 'namespace' => '', COUNT => $totalCount);

    $cellTable = new DDPTable("cellcounts",
                              array(
                                  array( DDPTable::KEY => 'mo', DDPTable::LABEL => 'MO' ),
                                  array( DDPTable::KEY => 'namespace', DDPTable::LABEL => 'Namespace' ),
                                  array( DDPTable::KEY => COUNT, DDPTable::LABEL => $count_header ),
                              ),
                              array('data' => $dataRows),
                              array('rowsPerPage' => 10, 'rowsPerPageOptions' => array( 25, 50, 100, ))
    );
    echo $cellTable->getTableWithHeader("Cell Counts", 1, "DDP_Bubble_279_ENM_Cell_Counts_Help", "");

    $neProdVerTable = new ModelledTable('TOR/system/neprodver', 'ne_up');
    if ( $neProdVerTable->hasRows() ) {
        echo $neProdVerTable->getTableWithHeader("NE Product Versions");
    }
}

mainFlow();

include PHP_ROOT . "/common/finalise.php";
