<?php
$pageTitle = "EBA Stream Termination and Parsing";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once 'HTML/Table.php';

const LABEL = 'label';
const EVENT_FILES_WRITTEN = "Number Of Event Files Written";
const EVENT_FILES_REWRITTEN = "Number Of Event Files Rewritten";
const OUTPUT_EVENTS = "Total number of output events";
const EVENT_RATE = "Total Number of Processed Events";
const TOTAL_COUNT_TABLE = "enm_eba_eventcounts";
const SELF_LINK = "/TOR/pm/eba_streaming.php";
$eba_rpmoflow = "enm_eba_rpmoflow";

function showMsstr() {
    $dbTable = "enm_eba_msstr";
    $cols = array(
        '3Events' => 'SUM(events3)',
        '3 MBytes processed' => 'SUM(MbytesProcessed3)',
        '3Dropped Connections' => 'SUM(droppedConnections3)'
    );

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    echo makeTable($dbTables, $cols);
    echo addLineBreak();

    $params = array(
        array( 'db'  => 'events3',
               LABEL => '3Events'
        ),
        array( 'db'  => 'MbytesProcessed3',
               LABEL => '3 MBytes processed'
        ),
        array( 'db' =>  'droppedConnections3',
               LABEL => '3Dropped Connections'
        ),
        array( 'db' => 'activeConnections3',
               LABEL => '3Active Connections'
        ),
        array( 'db' => 'createdConnections3',
               LABEL => '3Created Connections'
        )
    );

    $dbTables = array( $dbTable, StatsDB::SITES);
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s'";
    echo makeGraphs(
        $dbTables,
        $params,
        $where,
        SqlPlotParam::TIME_SERIES_COLLECTION
    );
}

function showRpmoflow() {
    global $eba_rpmoflow;
    $cols = array(
        EVENT_FILES_WRITTEN => 'SUM(Number_Of_Event_Files_Written)',
        'Number Of Ctrl Files Written' => 'SUM(Number_Of_Ctrl_Files_Written)',
        'Number Of Ctrl Files ReWritten' => 'SUM(Number_Of_Ctrl_Files_Rewritten)',
        EVENT_FILES_REWRITTEN => 'SUM(Number_Of_Event_Files_Rewritten)',
        OUTPUT_EVENTS => 'SUM(Total_number_of_output_events)',
        EVENT_RATE => 'SUM(Processed_Event_Rate_per_Second)'
    );

    $dbTables = array( $eba_rpmoflow, StatsDB::SITES, StatsDB::SERVERS );
    echo makeTable($dbTables, $cols);
    echo addLineBreak();

    $params = array(
        array( 'db'  => 'Number_Of_Event_Files_Written',
               LABEL => EVENT_FILES_WRITTEN
        ),
        array( 'db'  => 'Number_Of_Ctrl_Files_Written',
               LABEL => 'Number Of Ctrl Files Written'
        ),
        array( 'db' =>  'Number_Of_Ctrl_Files_Rewritten',
               LABEL => 'Number Of Ctrl Files Rewritten'
        ),
        array( 'db' =>  'Number_Of_Event_Files_Rewritten',
               LABEL => EVENT_FILES_REWRITTEN
        ),
        array( 'db' =>  'Processed_Event_Rate_per_Second',
               LABEL => EVENT_RATE
        ),
        array( 'db' =>  'Total_number_of_output_events',
               LABEL => OUTPUT_EVENTS
        )

    );

    $where = "$eba_rpmoflow.siteid = sites.id AND sites.name = '%s' AND $eba_rpmoflow.serverid = servers.id";
    echo makeGraphs(
        $dbTables,
        $params,
        $where,
        SqlPlotParam::STACKED_BAR
    );
}

function showRttflow() {
    $dbTable = 'enm_eba_rttflow';
    $cols = array(
        EVENT_FILES_WRITTEN => 'SUM(numberOfEventFilesWritten)',
        EVENT_FILES_REWRITTEN => 'SUM(numberOfEventFilesRewritten)',
        'Total Number of Output Events' => 'SUM(totalNumberOfOutputEvents)',
        'Total Number of Processed Events' => 'SUM(processedEventratePerSecond)'
    );
    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    echo makeTable($dbTables, $cols);
    echo addLineBreak();

    $params = array(
        array( 'db'  => 'numberOfEventFilesWritten',
               LABEL => EVENT_FILES_WRITTEN
        ),
        array( 'db' =>  'numberOfEventFilesRewritten',
               LABEL => EVENT_FILES_REWRITTEN
        ),
        array( 'db' =>  'totalNumberOfOutputEvents',
               LABEL => OUTPUT_EVENTS
        ),
        array( 'db' =>  'processedEventratePerSecond',
               LABEL => EVENT_RATE
      )

    );

    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id";
    echo makeGraphs(
        $dbTables,
        $params,
        $where,
        SqlPlotParam::STACKED_BAR,
        '',
        'servers.hostname'
    );
}

function showParser() {

    global $date, $site, $statsDB;
    $getserverIds = enmGetServiceInstances($statsDB, $site, $date, "ebaeventparser");
    $serverIds = implode(",", array_values($getserverIds));
    $dbTable = "enm_str_apeps";

    $cols = array(
        'JVM' => 'IFNULL( IF(enm_str_jvm_names.jvm_name = "", "NA", enm_str_jvm_names.jvm_name), "All" )',
        'Events Received' => 'SUM(eventsIn)',
        'Events Processed' => 'SUM(eventsProcessed)',
        'RPMO OutputAdapter Events Received' => 'SUM(rpmAvroEventsIn)',
        'RTT OutputAdapter Events Received' => 'SUM(rttAvroEventsIn)'

    );

    $where = "AND $dbTable.jvmid = enm_str_jvm_names.id AND $dbTable.serverid IN($serverIds)";
    $dbTables = array( $dbTable, "enm_str_jvm_names", StatsDB::SITES, StatsDB::SERVERS );

    echo makeTable($dbTables, $cols, $where);
    echo addLineBreak();

    $params = array(
        array( 'db'  => 'eventsIn',
               LABEL => 'Events Received'
        ),
        array( 'db'  => 'eventsProcessed',
               LABEL => 'Events Processed'
        ),
        array( 'db'  => 'rpmAvroEventsIn',
               LABEL => 'RPMO OutputAdapter Events Received'
        ),
        array( 'db'  => 'rttAvroEventsIn',
               LABEL => 'RTT OutputAdapter Events Received'
        )
    );


    $where = "$dbTable.siteid = sites.id AND
              sites.name = '%s' AND
              $dbTable.serverid = servers.id AND
              $dbTable.jvmid = enm_str_jvm_names.id AND
              $dbTable.serverid IN(%s)";

    echo makeGraphs(
        $dbTables,
        $params,
        $where,
        SqlPlotParam::TIME_SERIES_COLLECTION,
        $serverIds,
        'servers.hostname'
    );
}

function makeTable($dbTables, $columns, $extraWhere = "") {
    global $date, $site;

    $dbTable = $dbTables[0];
    $where = "$dbTable.siteid = sites.id AND sites.name = '$site' AND
              $dbTable.serverid = servers.id AND
              $dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              $extraWhere
              GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
              ->name($dbTable)
              ->tables($dbTables)
              ->where($where)
              ->addColumn('server', "IFNULL(servers.hostname,'Totals')", 'Instance');
    foreach ($columns as $colName => $sql) {
              $table->addSimpleColumn($sql, $colName);
    }
    $table = $table->build();

    return $table->getTableWithHeader("Daily Totals", 2, '', '');
}

function makeGraphs($dbTables, $params, $where, $graphType, $ids=null, $multiSeries=null ) {
    global $date, $site;
    $qar = array('site');
    $extraArgs = '';
    if (strlen($ids)>1) {
        $extraArgs = "server='$ids' ";
        array_push($qar, 'server');
    }
    $graphTable = new HTML_Table('border=0');
    foreach ( $params as $column ) {
        $dbCol = $column['db'];
        $label = $column[LABEL];

        $sqlParamWriter = new SqlPlotParam();

        $sqlParam = SqlPlotParamBuilder::init()
            ->title($label)
            ->type($graphType)
            ->yLabel($label)
            ->makePersistent()
            ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $dbCol => $label ),
                $dbTables,
                $where,
                $qar,
                $multiSeries
            )
            ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $url =  $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 800, 300, $extraArgs);
        $graphTable->addRow( array( $url ) );
    }

    return $graphTable->toHTML();

}

function createHTMLLinks($totalEventCount) {
    $links = array();
    $links[] = makeAnchorLink("enm_eba_msstr_anchor", "EBA Stream Termination");
    $links[] = makeAnchorLink("parser_anchor", "Parser");
    $links[] = makeLink('/common/kafka.php', 'Kafka', array('topics' => 'raw,decoded,rpmo_decoded,rtt_decoded'));
    $links[] = makeAnchorLink("enm_eba_rpmoflow_FileHandling_anchor", "EBA RPMO File Handling");
    $links[] = makeAnchorLink("enm_eba_rttflow_anchor", " EBA RTT File Handling");
    $links[] = makeLink(SELF_LINK, "EBA EBS-G", array('ebsgFileHandling'=> '1'));
    $links[] = makeLink(SELF_LINK, "EBA RPMO Monitor File Handling", array('ebsgFileHandling'=> '1'.'#eba_monitor' ));
    $links[] = makeLink(
        SELF_LINK,
        "EBA RPMO Binary File Handling",
        array('ebaRpmoBinaryFileHandling'=> '1')
    );
    if ( $totalEventCount > 0) {
        $links[] = makeAnchorLink("event_totals_anchor", "Event Totals");
    }
    echo makeHTMLList($links);
}

function ebsgTableParams() {
    return array(
        'Input Event Rate' => 'SUM(inputEventRatePerSecond)',
        'Filtered Event Rate' => 'SUM(filteredEventRatePerSecond)',
        'Processed Event Rate' => 'SUM(processedEventRatePerSecond)',
        'Processed Counter Volume' => 'SUM(processedCounterVolume)',
        'Dropped Counter Volume' => 'SUM(droppedCounterVolume)',
        'Output Counter Volume' => 'SUM(outputCounterVolume)',
        'Number Of Counter Files Written' => 'SUM(numberOfCounterFilesWritten)',
        'Number Of Counter Files Rewritten' => 'SUM(numberOfCounterFilesRewritten)'
    );
}

function ebaRpmoBinaryTableParams() {
    return array(
         EVENT_FILES_WRITTEN => 'SUM(binaryFilesWritten)',
         EVENT_FILES_REWRITTEN => 'SUM(binaryFilesRewritten)',
        'Total Number Of Processed Events' => 'SUM(binaryEventRate)',
        'Total Number Of Output Events' => 'SUM(binaryOutputEvents)'
    );
}
function ebsgGraphParams() {
    return array(
        array( 'db'  => 'inputEventRatePerSecond',
               LABEL => 'Input Event Rate'
        ),
        array( 'db' =>  'filteredEventRatePerSecond',
               LABEL => 'Filtered Event Rate'
        ),
        array( 'db' =>  'processedEventRatePerSecond',
               LABEL => 'Processed Event Rate'
        ),
        array( 'db' =>  'processedCounterVolume',
               LABEL => 'Processed Counter Volume'
        ),
        array( 'db' =>  'droppedCounterVolume',
               LABEL => 'Dropped Counter Volume'
        ),
        array( 'db' =>  'outputCounterVolume',
                LABEL => 'Output Counter Volume'
        ),
        array( 'db' =>  'numberOfCounterFilesWritten',
               LABEL => 'Number Of Counter Files Written'
        ),
        array( 'db' =>  'numberOfCounterFilesRewritten',
               LABEL => 'Number Of Counter Files Rewritten'
        )
    );
}

function ebsgmonitorGraphParams() {
    return array(
        array( 'db'  => 'numberOfMonitorFilesWritten',
               LABEL => "Number Of Monitor Files Written"
        ),
        array( 'db'  => 'numberOfMonitorFilesRewritten',
               LABEL => "Number Of Monitor Files Rewritten"
        ),
        array( 'db'  => 'outputMonitorVolume',
               LABEL => "Output Monitor Volume"
        )
    );
}

function ebaRpmoBinaryGraphParams() {

   return array(
        array( 'db'  => 'binaryFilesWritten',
               LABEL => EVENT_FILES_WRITTEN
        ),
        array( 'db' =>  'binaryFilesRewritten',
               LABEL => EVENT_FILES_REWRITTEN
        ),
        array( 'db' =>  'binaryEventRate',
               LABEL => 'Total Number Of Processed Events'
        ),
        array( 'db' =>  'binaryOutputEvents',
               LABEL => 'Total Number Of Output Events'
        )
    );
}

function ebsgFileHandling() {
    global $webargs;
    $serverLink = fromServer('PHP_SELF');
    $msgsURL = "$serverLink?$webargs&servicegroup=ebsgflow";
    echo "<a href=\"$msgsURL\">Return to EBA Streaming page</a>\n";
    $ebsgTable = "enm_ebsgflow";
    $where = "$ebsgTable.siteid = sites.id AND sites.name = '%s' AND $ebsgTable.serverid = servers.id";
    $dbTables = array( "enm_ebsgflow", StatsDB::SITES, StatsDB::SERVERS );

    drawHeader("BSC Performance Event Statistics", 1, "eba_bsc");
    $table = new ModelledTable( "/TOR/pm/enm_ebsgFlow_bsc", "file_daily");
    echo $table->getTableWithHeader("Daily Totals");

    $instrGraphParams = ebsgGraphParams();
    echo makeGraphs( $dbTables, $instrGraphParams, $where, SqlPlotParam::STACKED_BAR );
    echo addLineBreak();

    drawHeader("EBA RPMO Monitor File Handling", 1, "eba_monitor");
    $table = new ModelledTable( "/TOR/pm/enm_ebsgFlow_monitor", "monitor_daily" );
    echo $table->getTableWithHeader("Daily Totals");

    $instrMonitorgraph = ebsgmonitorGraphParams();
    echo makeGraphs( $dbTables, $instrMonitorgraph, $where, SqlPlotParam::STACKED_BAR );

}

function ebaRpmoBinaryFileHandling() {
    global $eba_rpmoflow;

    echo makeLink( SELF_LINK, "Return to EBA Streaming page" );
    drawHeader("EBA RPMO BINARY FILE HANDLING", 1, "enm_eba_rpmoflow_BinaryFileHandling");
    $instrTableParams = ebaRpmoBinaryTableParams();
    $dbTables = array( $eba_rpmoflow, StatsDB::SITES, StatsDB::SERVERS );
    echo makeTable($dbTables, $instrTableParams);
    echo addLineBreak();

    $instrGraphParams = ebaRpmoBinaryGraphParams();
    $dbTables = array( $eba_rpmoflow, StatsDB::SITES, StatsDB::SERVERS );
    $where = "$eba_rpmoflow.siteid = sites.id AND sites.name = '%s' AND $eba_rpmoflow.serverid = servers.id";
    echo makeGraphs( $dbTables, $instrGraphParams, $where, SqlPlotParam::STACKED_BAR );
}

function mainFlow() {
    global $statsDB, $site, $date;

    $row = $statsDB->queryRow("
SELECT SUM(eventcount)
FROM
 enm_eba_eventcounts, sites
WHERE
 enm_eba_eventcounts.siteid = sites.id AND sites.name = '$site' AND
 enm_eba_eventcounts.date = '$date'");
    $totalEventCount = $row[0];

    createHTMLLinks($totalEventCount);
    drawHeaderWithHelp("EBA Stream Termination", 1, "enm_eba_msstr");
    showMsstr();
    drawHeaderWithHelp("Parser", 1, "parser");
    showParser();
    drawHeaderWithHelp("EBA RPMO File Handling", 1, "enm_eba_rpmoflow_FileHandling");
    showRpmoflow();
    drawHeaderWithHelp("EBA RTT File Handling", 1, "enm_eba_rttflow");
    showRttflow();
    if ( $totalEventCount > 0 ) {
        $eventCountTable = SqlTableBuilder::init()
                         ->name("event_totals")
                         ->tables(array(TOTAL_COUNT_TABLE, StatsDB::SITES))
                         ->where($statsDB->where(TOTAL_COUNT_TABLE, "date", true))
                         ->addSimpleColumn("eventid", "Event ID")
                         ->addSimpleColumn("eventcount", "Event Count")
                         ->addSimpleColumn("ROUND( (eventcount*100/$totalEventCount),2)", "Percent")
                         ->paginate()
                         ->build();
        echo $eventCountTable->getTableWithHeader("Event Totals", 2, "", "", "event_totals");
    }
}

if (issetURLParam('ebsgFileHandling')) {
    if ( $statsDB->hasData("enm_ebsgflow") ) {
        ebsgFileHandling();
    }
} elseif (issetURLParam('ebaRpmoBinaryFileHandling') && $statsDB->hasData($eba_rpmoflow)) {
    ebaRpmoBinaryFileHandling();
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
