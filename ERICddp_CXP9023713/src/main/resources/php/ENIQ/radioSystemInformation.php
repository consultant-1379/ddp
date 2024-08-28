<?php
$pageTitle = "System Information";

include_once "../common/init.php";

require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

const SELF = '/ENIQ/radioSystemInformation.php';

function drawTable($data, $counter) {
    $columns = array();
    $nodeData = $data[0];
    foreach ( $nodeData as $key => $val ) {
        $columns[] = array( DDPTable::KEY => $key, DDPTable::LABEL => $key);
    }
    if ($counter == 1) {
        $table = new DDPTable(
            "Cell_Details",
            $columns,
            array('data' => $data)
        );
        echo $table->getTableWithHeader("Cell Details", 1, "", "", "Cell_Details");
        echo addLineBreak();
    } elseif ($counter == 2) {
        $table = new DDPTable(
            "Node_Details",
            $columns,
            array('data' => $data)
        );
        echo $table->getTableWithHeader("Node Details", 1, "", "", "Node_Details");
        echo addLineBreak();
    }
}

function conditionCheck(&$key, &$val) {
    if (($key == "nodeType") && ($val == "Totals")) {
        $val = "<b>Totals</b>";
    }
    if (($key == "key1_technology") && ($val == "Totals")) {
        $val = "<b>Totals</b>";
    }
    if ($key == "nodeType") {
        $key = "Node Type";
    } elseif ($key == "count") {
        $key = "Count";
    } elseif (($key == "technology") || ($key == "key1_technology")) {
        $key = "Technology";
    } elseif ($key == "key3_cellCount") {
        $key = "Cell Count";
    } elseif ($key == "key2_nodeCount") {
        $key = "Node Count";
    }
}

function getTableData($arrayOfNodeData, &$counter) {
    foreach ($arrayOfNodeData as &$arrayElement) {
        $json = json_decode($arrayElement, true);
        $data = array();
        foreach ( $json as $key => $value ) {
            $arr = array();
            foreach ($value as $key => $val) {
                conditionCheck($key, $val);
                $arr[$key] = $val;
            }
            $data[] = $arr;
        }
        $counter++;
        drawTable($data, $counter);
    }
}

function createEventBasedTable() {
    global  $site, $date, $statsDB;
    $rowData = array();

    $sql =
"SELECT
 '5G NR' AS technology,
 IFNULL(ceiling(sum(files_in_process)/96),0) as file_in_process
FROM
 backlog_monitoring_stats, sites, backlog_interface
WHERE
 backlog_monitoring_stats.siteid = sites.id
 AND sites.name = '$site'
 AND backlog_monitoring_stats.intf_id = backlog_interface.id
 AND (backlog_interface.backlog_intf Like 'INTF_DC_E_NR_EVENTS%')
 AND backlog_monitoring_stats.time between '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
 'LTE' AS technology,
 IFNULL(ceiling(SUM(eniq_stats_adaptor_totals.rows_sum)/96),0) AS file_in_process
FROM
 eniq_stats_adaptor_totals, eniq_stats_source, eniq_stats_types, sites
WHERE
 eniq_stats_adaptor_totals.siteid = sites.id
 AND sites.name = '$site'
 AND (eniq_stats_types.name = 'DC_E_ERBS_EVENTS_EUTRANCELLFDD' OR
 eniq_stats_types.name = 'DC_E_ERBS_EVENTS_EUTRANCELLTDD')
 AND eniq_stats_adaptor_totals.sourceid = eniq_stats_source.id
 AND eniq_stats_adaptor_totals.typeid = eniq_stats_types.id
 AND eniq_stats_adaptor_totals.day = '$date'
UNION
SELECT
 'GSM' AS technology,
 IFNULL(ceiling(sum(files_in_process)/96),0) as file_in_process
FROM
 backlog_monitoring_stats, sites, backlog_interface
WHERE
 backlog_monitoring_stats.siteid = sites.id
 AND sites.name = '$site'
 AND backlog_monitoring_stats.intf_id = backlog_interface.id
 AND (backlog_interface.backlog_intf Like 'INTF_DC_E_BSS_EVENTS%')
 AND backlog_monitoring_stats.time between '$date 00:00:00' AND '$date 23:59:59' ";

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }

    $table = new DDPTable(
        "EventBasedTable",
        array(
            array('key' => 'technology', 'label' => 'Technology'),
            array('key' => 'file_in_process', 'label' => 'Nodes with Events Data')
        ),
        array('data' => $rowData)
    );
    echo $table->getTable();
}

function showLinks() {

    global  $statsDB;
    $links = array();
    if ($statsDB->hasData('eniq_radio_cell_count_details', 'date')) {
        $links[] = makeLink(SELF, 'Cell Count for Radio Nodes', array('showRadioCell' => '1'));
    }
    if ($statsDB->hasData('eniq_pico_rnc_cell_count_details', 'date')) {
        $links[] = makeLink(SELF, 'Cell Count for Pico and RNC nodes', array('showPicoRncCell' => '2'));
    }
    if ($statsDB->hasData('eniq_radio_node_count_details', 'date')) {
        $links[] = makeLink(SELF, 'Node Count for Radio Nodes', array('showRadioNode' => '3'));
    }
    if ($statsDB->hasData('eniq_pico_rnc_node_count_details', 'date')) {
        $links[] = makeLink(SELF, 'Node Count for Pico and RNC Nodes', array('showPicoRncNode' => '4'));
    }
    $links[] = makeLink(SELF, 'Events Based Node Count', array('showEventNode' => '5'));
    if ($statsDB->hasData('eniq_transport_ims_core_node_details', 'date')) {
        $links[] = makeLink(SELF, 'Node Count for IMS, transport and Core Nodes', array('showIMSTransportCore' => '6'));
    }
    echo makeHTMLList($links);
}


function checkFiles() {

    global $datadir;
    $counter = 0;
    $nodeCellCountFile = $datadir . "/plugin_data/radioNode/node_count_cell_count.json";
    $nodeNameTypeFile = $datadir . "/plugin_data/radioNode/node_name_node_type.json";
    $picoNodeCountFile = $datadir . "/plugin_data/radioNode/radio_pico_rnc_node_count.txt";
    $picoCellCountFile = $datadir . "/plugin_data/radioNode/radio_pico_rnc_cell_count.txt";
    $mixedCellCountFile = $datadir . "/plugin_data/radioNode/radio_G1_G2_mixed_cell_count.txt";
    $mixedNodeCountFile = $datadir . "/plugin_data/radioNode/radio_G1_G2_mixed_node_count.txt";

    $newTextFiles = (file_exists($picoNodeCountFile) || file_exists($picoCellCountFile) ||
                     file_exists($mixedCellCountFile )|| file_exists($mixedNodeCountFile));

    if (file_exists($nodeCellCountFile)) {
        $nodeCellCountString = file_get_contents($nodeCellCountFile); //NOSONAR
    }
    if (file_exists($nodeNameTypeFile)) {
        $nodeNameTypeString = file_get_contents($nodeNameTypeFile); //NOSONAR
    }
    if (!$newTextFiles) {
        $arrayOfNodeData = array($nodeCellCountString,  $nodeNameTypeString);
        getTableData($arrayOfNodeData, $counter);
    } else {
        showLinks();
    }
}

function main() {

    checkFiles();
    if ( issetURLParam('showRadioCell') ) {
        $table = new ModelledTable('ENIQ/radioCellCountTable', 'radioCellCountHelp');
        echo $table->getTableWithHeader("Cell Count for Radio Nodes");
    } elseif ( issetURLParam('showPicoRncCell') ) {
        $table = new ModelledTable('ENIQ/radioPicoRncCellTable', 'radioPicoCellHelp');
        echo $table->getTableWithHeader("Cell Count for Pico and RNC Nodes");
    } elseif ( issetURLParam('showRadioNode') ) {
        $table = new ModelledTable('ENIQ/radioNodeCountTable', 'radioNodeCountHelp');
        echo $table->getTableWithHeader("Node Count for Radio Nodes");
    }  elseif ( issetURLParam('showPicoRncNode') ) {
        $table = new ModelledTable('ENIQ/radioPicoRncNodeTable', 'radioPicoNodeHelp');
        echo $table->getTableWithHeader("Node Count for Pico and Rnc Nodes");
    } elseif ( issetURLParam('showEventNode') ) {
        drawHeader("Events Based Node Count", 2, "eventHelp");
        createEventBasedTable();
    } elseif ( issetURLParam('showIMSTransportCore') ) {
        $table = new ModelledTable('ENIQ/transport_ims_core_node', 'radioTransportTableHelp');
        echo $table->getTableWithHeader("Node Count for IMS, Transport and Core Nodes");
    }
}

main();

include_once PHP_ROOT . "/common/finalise.php";
