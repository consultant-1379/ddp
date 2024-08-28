<?php
$pageTitle = "Event Based Statistics(Stream)";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

require_once 'HTML/Table.php';

const INSTANCE = 'Instance';
const EPS_ID = 'epsid';
const TOTAL_COUNTERS_PRODUCED = 'EBS-L Counters Produced';
const TOTAL_OUTPUT_ROP_FILES = 'EBS-L Output ROP Files';
const AVG_CNT_PER_FILE  = 'EBS-L Avg Counters Produced Per Output File';
const DAILY_TOTALS = 'Daily Totals';

const OUTPUT_COUNTER_VOLUME = 'Output Counter Volume';
const DROPPED_COUNTER_VOLUME = 'Dropped Counter Volume';
const OUTPUT_COUNTER_FILES = 'Output Counter Files';
const OUTPUT_COUNTER_FILE_REWRITES = 'Output Counter File Rewrites';
const INST_DB_TABLE = "enm_ebsl_inst_stats";
const INST_EBSM_STREAM_TABLE = "enm_ebsmstream_instr";
const PROC_COUNTER = "processedEventsCounter";
const LTE = "EBS-L";
const NR = "EBS-N";
const DROP_COUNTER = "droppedEventsCounter";
const COUNTER_PRODUCED = "countersProduced";

function hasData($statsDB,$query) {
    $row = $statsDB->queryRow($query);
    return $row[0] > 0;
}

class EbsStreamStatisticsTotals extends DDPObject {
    var $cols = array(
        array( DDPTable::KEY => 'inst', DDPTable::LABEL => INSTANCE),
        array( DDPTable::KEY => EPS_ID, DDPTable::LABEL => 'Eps ID'),
        array( DDPTable::KEY => 'no_output_rops', DDPTable::LABEL => TOTAL_OUTPUT_ROP_FILES),
        array( DDPTable::KEY => 'counters_produced', DDPTable::LABEL => TOTAL_COUNTERS_PRODUCED),
        array( DDPTable::KEY => 'counters_processed', DDPTable::LABEL => AVG_CNT_PER_FILE),
    );
    var $title = "EBS-L Stream Instrumentation Statistics";

    function __construct() {
        parent::__construct("EBSLStreamInstrumentationStatistics");
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        global $php_webroot;
        $sql = "
SELECT
 enm_ebsm_epsid.EpsIdText as epsid,
 IFNULL(servers.hostname,'All Instances') AS inst,
 IFNULL(SUM(numoffileswritten), 0) AS no_output_rops,
 IFNULL(SUM(countersproduced), 0) AS counters_produced,
 IFNULL(ROUND( SUM(countersproduced)/ SUM(numoffileswritten),0),0) counters_processed
FROM ebsm_stream_logs, sites, servers,enm_ebsm_epsid
WHERE
 ebsm_stream_logs.siteid = sites.id AND sites.name = '$site' AND
 ebsm_stream_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 ebsm_stream_logs.serverid = servers.id AND enm_ebsm_epsid.id = ebsm_stream_logs.EpsId
GROUP BY servers.hostname,enm_ebsm_epsid.EpsIdText with ROLLUP HAVING (inst LIKE 'All Instances' OR epsid IS NOT NULL)";
        $this->populateData($sql);
        foreach ($this->data as &$row) {
            $row[EPS_ID]  = "<a href='$php_webroot/TOR/pm/ebsm_stream_statistics.php?$webargs" . "&daily=" . $row['inst'] . "&epsid=" . $row[EPS_ID] . "'>" . $row[EPS_ID] . "</a>";
            if($row['inst'] != "All Instances") {
              $row['inst'] = "<a href='$php_webroot/TOR/pm/ebsm_stream_statistics.php?$webargs" . "&daily=" . $row['inst'] . "'>" . $row['inst'] . "</a>";
            }
        }
        return $this->data;
    }
}

class DailyTotals extends DDPObject {
    var $cols = array(
        array( DDPTable::KEY =>'instance', DDPTable::LABEL => INSTANCE),
        array( DDPTable::KEY =>EPS_ID, DDPTable::LABEL => 'Eps ID'),
        array( DDPTable::KEY =>'tm', DDPTable::LABEL => 'Time'),
        array( DDPTable::KEY =>'node_name', DDPTable::LABEL => 'Node'),
        array( DDPTable::KEY => 'counters_produced', DDPTable::LABEL => 'Counters Produced'),
    );
    var $title = DAILY_TOTALS;

    function __construct() {
        parent::__construct("Daily_totals");
    }

    function getData() {
      global $debug, $webargs, $php_webroot, $date, $site, $ebsmStatsDB;
      if(isset($_GET[EPS_ID])){
        $filter ="AND enm_ebsm_epsid.EpsIdText = '" . $_GET[EPS_ID] . "'";
      } else {
        $filter = "";
      }

      $sql="
  SELECT
    DATE_FORMAT(time,'%H:%i:%s') as tm,
    IFNULL(servers.hostname,'') AS instance,
    enm_ebsm_epsid.EpsIdText as epsid,
    nodename AS node_name,
    countersproduced AS counters_produced
  FROM
    ebsm_stream_logs,enm_ebsm_epsid,
    sites,
    servers
  WHERE
    ebsm_stream_logs.siteid = sites.id
    AND ebsm_stream_logs.serverid = servers.id
    AND servers.hostname = '".$_GET['daily']."'
    AND sites.name = '".$_GET['site']."'
    AND ebsm_stream_logs.time BETWEEN '".$_GET['date']." 00:00:00' AND '".$_GET['date']." 23:59:59'
    AND ebsm_stream_logs.EpsId = enm_ebsm_epsid.id $filter ";

        $this->populateData($sql);
        return $this->data;
    }
}

class EbsmNodesTotals extends DDPObject {
    var $cols = array(
        'node_name' => 'Node',
        'no_output_rops' => TOTAL_OUTPUT_ROP_FILES,
        'counters_processed' => AVG_CNT_PER_FILE,
    );
    var $title = "EBSM Nodes Statistics";

    function __construct() {
        parent::__construct("EBSLStreamNodesStatistics");
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        global $php_webroot;
        $sql = "
SELECT
 nodename AS node_name,
 IFNULL(SUM(numoffileswritten), 0) AS no_output_rops,
 ROUND( SUM(countersproduced)/ SUM(numoffileswritten),0) AS counters_processed
FROM ebsm_stream_logs, sites, servers
WHERE
 ebsm_stream_logs.siteid = sites.id AND sites.name = '$site' AND
 ebsm_stream_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 ebsm_stream_logs.serverid = servers.id
GROUP BY node_name WITH ROLLUP";
        $this->populateData($sql);
        #foreach ($this->data as &$row) {
         #   $row['node_name'] = "<a href='$php_webroot/TOR/ebsm_statistics.php?$webargs" . "&node=" . $row['node_name'] . "'>" . $row['node_name'] . "</a>";
        #}
        return $this->data;
    }
}

function showTotalsTables($hasInstStats, $hasNeStats) {
    global $date, $site;

    if ( $hasInstStats ) {
        $instWhere = <<<EOS
enm_ebsl_inst_stats.siteid = sites.id AND sites.name = '$site' AND
enm_ebsl_inst_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_ebsl_inst_stats.serverid = servers.id AND
enm_ebsl_inst_stats.epsid = enm_ebsm_epsid.id
GROUP BY inst WITH ROLLUP
EOS;
        $table = SqlTableBuilder::init()
               ->name("inst_totals")
               ->tables(array(INST_DB_TABLE, "enm_ebsm_epsid", StatsDB::SITES, StatsDB::SERVERS))
               ->where($instWhere)
               ->paginate()
               ->addColumn('inst', 'CONCAT(servers.hostname,"-",enm_ebsm_epsid.EpsIdText)', INSTANCE,'ddpFormatRollup')
               ->addSimpleColumn("SUM(numoffileswritten)",TOTAL_OUTPUT_ROP_FILES)
               ->addSimpleColumn("SUM(countersproduced)", TOTAL_COUNTERS_PRODUCED)
               ->addSimpleColumn("ROUND(SUM(countersproduced)/ SUM(numoffileswritten),0)", AVG_CNT_PER_FILE)
               ->addSimpleColumn("SUM(numOfFilesWrittenNR)", "EBS-N Output ROP Files")
               ->addSimpleColumn("SUM(countersProducedNR)", "EBS-N Counters Produced")
               ->addSimpleColumn("ROUND(SUM(countersProducedNR)/ SUM(numOfFilesWrittenNR),0)", "EBS-N Avg Counters Produced Per Output File")
               ->build();
        echo $table->getTableWithHeader(DAILY_TOTALS, 2, "DDP_Bubble_400_ENM_EBSLSTREAM_Daily_Totals");
    }

    if ( $hasNeStats ) {
        $neWhere = <<<EOS
enm_ebsl_ne_stats.siteid = sites.id AND sites.name = '$site' AND
enm_ebsl_ne_stats.date = '$date' AND
enm_ebsl_ne_stats.neid = enm_ne.id
GROUP BY enm_ne.name
EOS;
        $table = SqlTableBuilder::init()
               ->name("ne_totals")
               ->tables(array("enm_ebsl_ne_stats", "enm_ne", StatsDB::SITES))
               ->where($neWhere)
               ->addSimpleColumn('enm_ne.name', 'Node')
               ->addSimpleColumn("SUM(numoffileswritten)",TOTAL_OUTPUT_ROP_FILES)
               ->addSimpleColumn("SUM(countersproduced)", TOTAL_COUNTERS_PRODUCED)
               ->addSimpleColumn("ROUND(SUM(countersproduced)/ SUM(numoffileswritten),0)", AVG_CNT_PER_FILE)
               ->paginate()
               ->dbScrolling()
               ->build();
        echo $table->getTableWithHeader("Nodes", 2, "DDP_Bubble_409_ENM_EBSLSTREAM_Node_Totals");
    }
}

function showInstrGraphperTech($title, $dbCol, $dbTable, $help) {
    global $date, $site, $statsDB;

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, "ebsstream");
    $serverIds = implode(",", array_values($processingSrv));

    drawHeader($title, 2, $help);
    $dbTables = array($dbTable, StatsDB::SITES, StatsDB::SERVERS);
    $where = "$dbTable.siteid = sites.id AND
              sites.name = '%s' AND $dbTable.serverid = servers.id
              AND $dbTable.serverid IN(%s)";

    foreach ( $dbCol as $col => $name ) {
        $sqlParamWriter = new SqlPlotParam();
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('%s')
            ->titleArgs(array('title'))
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel("Count")
            ->presetAgg(SqlPlotParam::AGG_SUM,SqlPlotParam::AGG_MINUTE)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(SqlPlotParam::DEFAULT_TIME_COL,
                array( $col => $name ),
                $dbTables,
                $where,
                array('site', 'serverid'),
                SqlPlotParam::SERVERS_HOSTNAME
            )
            ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        echo $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            800,
            400,
            "serverid=$serverIds&title=$name"
        );
    }
}

function makeGraphs($title, $graphParams, $dbTable) {

    global $date;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id";
    $sqlParamWriter = new SqlPlotParam();
    $sqlParam = SqlPlotParamBuilder::init()
        ->title('%s')
        ->titleArgs(array('title'))
        ->type(SqlPlotParam::STACKED_BAR)
        ->yLabel($title)
        ->makePersistent()
        ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
        ->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            $graphParams,
            $dbTables,
            $where,
            array( 'site' )
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 800, 300, "title=$title");
}

function showStreamLogsGraph($title,$dbCol) {
    global $date;

    $sqlParam = SqlPlotParamBuilder::init()
              ->title($title)
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel('#')
              ->disableUserAgg()
              ->presetAgg(SqlPlotParam::AGG_SUM,SqlPlotParam::AGG_MINUTE)
              ->makePersistent()
              ->forceLegend()
              ->addQuery(SqlPlotParam::DEFAULT_TIME_COL,
                         array( $dbCol => $title ),
                         array("ebsm_stream_logs", StatsDB::SITES, StatsDB::SERVERS),
                         "ebsm_stream_logs.siteid = sites.id AND sites.name = '%s' AND ebsm_stream_logs.serverid = servers.id",
                         array('site'),
                         SqlPlotParam::SERVERS_HOSTNAME)
              ->build();

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
}

function showInstrGraph($title, $dbCol) {
    global $date;

    $sqlParam = SqlPlotParamBuilder::init()
              ->title($title)
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel("Events/s")
              ->disableUserAgg()
              ->presetAgg(SqlPlotParam::AGG_SUM,SqlPlotParam::AGG_MINUTE)
              ->makePersistent()
              ->forceLegend()
              ->addQuery(SqlPlotParam::DEFAULT_TIME_COL,
                         array( $dbCol => $title ),
                         array(INST_EBSM_STREAM_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                         "enm_ebsmstream_instr.siteid = sites.id AND sites.name = '%s' AND enm_ebsmstream_instr.serverid = servers.id",
                         array('site'),
                         SqlPlotParam::SERVERS_HOSTNAME)
              ->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
}

function showQSizes($statsDB) {
    global $date, $site;

    drawHeaderWithHelp("Queue Sizes", 2, "qsizes");

    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();

    foreach ( array('ebs', 'esi' ) as $q ) {
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title(strtoupper($q) . ' Queue Size')
                  ->type(SqlPlotParam::STACKED_BAR)
                  ->barWidth(60)
                  ->yLabel('Size')
                  ->addQuery(SqlPlotParam::DEFAULT_TIME_COL,
                             array( $q . "_qsize" => 'Queue Size' ),
                             array(INST_EBSM_STREAM_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                             "enm_ebsmstream_instr.siteid = sites.id AND sites.name = '%s' AND enm_ebsmstream_instr.serverid = servers.id",
                             array('site'),
                             SqlPlotParam::SERVERS_HOSTNAME)
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400)));
    }
    echo $graphTable->toHTML();
}

function ebslStream() {

    drawHeader(OUTPUT_COUNTER_VOLUME, 2, "ebslStreamCountersProduced");
    makeGraphs(OUTPUT_COUNTER_VOLUME, array(COUNTER_PRODUCED => LTE, 'countersProducedNR' => NR), INST_DB_TABLE);
    showInstrGraphperTech(
        "Output Counter Volume per technology",
        array(
            COUNTER_PRODUCED => 'Output Counter Volume EBS-L',
            'countersProducedNR' => 'Output Counter Volume EBS-N'
        ),
        INST_DB_TABLE,
        "countersperTech"
    );

    drawHeader(DROPPED_COUNTER_VOLUME, 2, "ebslStreamCountersDropped");
    makeGraphs(
        DROPPED_COUNTER_VOLUME,
        array(
            'numberOfLTEcountersDropped' => 'Number_Of_LTE_Counters_Dropped',
            'numberOfNRcountersDropped' => 'Number_Of_NR_Counters_Dropped',
            'numberOfNRcountersDroppedDueToMissingParameter' => 'Number_Of_NR_Counters_Dropped_Due_To_Missing_Parameter'
        ),
        INST_DB_TABLE
    );
    showInstrGraphperTech(
        "Dropped Counter Volume per technology",
        array(
            'numberOfLTEcountersDropped' => 'Dropped Counter Volume EBS-L',
            'numberOfNRcountersDropped' => 'Dropped Counter Volume EBS-N',
            'numberOfNRcountersDroppedDueToMissingParameter' => 'Dropped Counter Volume EBS-N Due To Missing Parameter'
        ),
        INST_DB_TABLE,
        "DroppedperTech"
    );

    drawHeader(OUTPUT_COUNTER_FILES, 2, "ebslStreamNumberOfFilesWritten");
    makeGraphs(OUTPUT_COUNTER_FILES, array('numOfFilesWritten' => LTE, 'numOfFilesWrittenNR' => NR), INST_DB_TABLE);
    showInstrGraphperTech(
        "Output Counter Files per technology",
        array(
            'numOfFilesWritten' => 'Output Counter Files EBS-L',
            'numOfFilesWrittenNR' => 'Output Counter Files EBS-N'
        ),
        INST_DB_TABLE,
        "FilesperTech"
    );

    drawHeader(OUTPUT_COUNTER_FILE_REWRITES, 2, "ebslStreamNumberOfFilesRewrites");
    makeGraphs(
        OUTPUT_COUNTER_FILE_REWRITES,
        array(
            'numOfFilesReWritten' => LTE,
            'numOfFilesReWrittenNR' => NR
        ),
        INST_DB_TABLE
        );
    showInstrGraphperTech(
        "Output Counter File Rewrites per technology",
        array(
            'numOfFilesReWritten' => 'Output Counter File Rewrites EBS-L',
            'numOfFilesReWrittenNR' => 'Output Counter File Rewrites EBS-N'
        ),
        INST_DB_TABLE,
        "FilesrewritesperTech"
    );
}

function fdnMosCreated() {
    global $date, $site, $statsDB;
    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, "ebsstream");
    $serverIds = implode(",", array_values($processingSrv));
    drawHeader("FDN Mos Created", 2, "fdnmoscreated");
    $graphs = array();
    getGraphsFromSet('fdnMos', $graphs, 'TOR/pm/enm_ebsn_fdn_mos', array('serverid' => $serverIds));
    plotGraphs($graphs);
}

function sessionAgg() {
    drawHeader("Session Aggregation Indexes", 2, "sessionaggregation");
    $graphs = array();
    getGraphsFromSet('session', $graphs, 'TOR/pm/session_aggregation');
    plotGraphs($graphs);

    drawHeader("Number of Suspect Cells Per Rop", 2, "suspectcells");
    $graphs = array();
    getGraphsFromSet('suspect', $graphs, 'TOR/pm/session_aggregation');
    plotGraphs($graphs);
}

$statsDB = new StatsDB();

function mainFlow($statsDB) {
    global $site, $date;

    $links = array();
    echo "<H1>Event Based Statistics (Stream)</H1>\n";

    $hasNeStats = $statsDB->hasData("enm_ebsl_ne_stats", 'date', true);
    $hasInstStats = $statsDB->hasData(INST_DB_TABLE);

    if ( $hasNeStats ) {
        $hasStreamLogs = FALSE;
    } else {
        $hasStreamLogs = hasData($statsDB,"SELECT COUNT(*)
FROM ebsm_stream_logs, sites
WHERE
 ebsm_stream_logs.siteid = sites.id AND sites.name = '$site' AND
 ebsm_stream_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    }

    $hasInstr = hasData($statsDB,"SELECT COUNT(*)
FROM enm_ebsmstream_instr, sites
WHERE
 enm_ebsmstream_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_ebsmstream_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    $hasQSize = hasData($statsDB,"SELECT COUNT(*)
FROM enm_ebsmstream_instr, sites
WHERE
 enm_ebsmstream_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_ebsmstream_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_ebsmstream_instr.ebs_qsize IS NOT NULL");

    echo "<ul>\n";
    if ( $hasStreamLogs || $hasNeStats ) {
        echo " <li><a href=\"#nodes\">Nodes</a></li>\n";
    }

    if( $hasInstr ) {
        echo "<li><a href=\"#ebslStreamEventRate_anchor\">Event Rate per Second</a></li>\n";
        echo "<li><a href=\"#processedEventsCounter_anchor\">Processed Event Rate per Second</a></li>\n";
        echo "<li><a href=\"#droppedEventsCounter_anchor\">Filtered Event Rate per Second</a></li>\n";
    }
    if ( $hasQSize ) {
        echo "<li><a href=\"#qsizes_anchor\">Queue Sizes</a></li>\n";
    }

    if ( $hasStreamLogs || $hasNeStats || $hasInstStats ) {
        echo "<li><a href=\"#ebslStreamCountersProduced_anchor\">Output Counter Volume</a></li>\n";
        echo "<li><a href=\"#ebslStreamCountersDropped_anchor\">Dropped Counter Volume</a></li>\n";
        echo "<li><a href=\"#ebslStreamNumberOfFilesWritten_anchor\">Output Counter Files</a></li>\n";
        echo "<li><a href=\"#ebslStreamNumberOfFilesRewrites_anchor\">Output Counter File Rewrites</a></li>\n";
    }
    echo "</ul>\n";
    $links[] = makeLink('/common/kafka.php', 'Kafka', array('topics' => 'ebscounterdata'));
    $links[] = makeAnchorLink("sessionaggregation", 'Session Aggregation Indexes');
    $links[] = makeAnchorLink("suspectcells", 'Number of Suspect Cells Per Rop');
    $links[] = makeAnchorLink("fdnmoscreated", 'FDN Mos Created');
    echo makeHTMLList($links);

    if ( $hasInstStats || $hasNeStats ) {
        showTotalsTables($hasInstStats, $hasNeStats);
    } else if ( $hasStreamLogs ) {
        /* Daily Summary table */
        drawHeaderWithHelp(DAILY_TOTALS, 1, "dailyTotalHelp", "DDP_Bubble_400_ENM_EBSLSTREAM_Daily_Totals");
        $EBSMTable = new EbsStreamStatisticsTotals();
        echo $EBSMTable->getClientSortableTableStr(100, array(10, 25, 50));

        drawHeaderWithHelp("Nodes", 1, "nodeTotalsHelp", "DDP_Bubble_409_ENM_EBSLSTREAM_Node_Totals");
        $EbsmNodesTable = new EbsmNodesTotals();
        echo $EbsmNodesTable->getClientSortableTableStr(10, array(25, 50, 100, 250, 500, 1000));
    }

    if ( $hasInstr ) {
        drawHeaderWithHelp("Event Rate per Second", 2, "ebslStreamEventRate",
                           "DDP_Bubble_411_ENM_EBSLSTREAM_OverAllEventRateHelp");
        showInstrGraph('Event Rate per Second', 'count/60');

        drawHeader("Processed Event Rate per Second", 2, PROC_COUNTER);
        makeGraphs(
            'Events Processed',
            array(
                PROC_COUNTER => LTE,
                'processedEventsCounter5G' => NR
            ),
            INST_EBSM_STREAM_TABLE
        );
        showInstrGraphperTech(
            "Events Processed Per Technology",
            array(
                PROC_COUNTER => 'Events Processed EBS-L',
                'processedEventsCounter5G' => 'Events Processed EBS-N'
            ),
            INST_EBSM_STREAM_TABLE,
            "processedEventsperTech"
        );

        drawHeader("Filtered Event Rate per Second", 2, DROP_COUNTER);
        makeGraphs(
            'Filtered Events',
            array(
                DROP_COUNTER => LTE,
                'droppedEventsCounter5G' => NR
            ),
            INST_EBSM_STREAM_TABLE
        );
        showInstrGraphperTech(
            "Filtered Events Per Technology",
            array(
                DROP_COUNTER => 'Filtered Events Per EBS-L',
                'droppedEventsCounter5G' => 'Filtered Events EBS-N'
            ),
            INST_EBSM_STREAM_TABLE,
            "droppedEventsperTech"
        );
    }
    if ( $hasQSize ) {
        showQSizes($statsDB);
    }
    if ( $hasInstStats ) {
        ebslStream();

    } else if ( $hasStreamLogs ) {
        drawHeaderWithHelp(OUTPUT_COUNTER_VOLUME, 2, "ebslStreamCountersProduced",
                           "DDP_Bubble_410_ENM_EBSLSTREAM_CountersProducedHelp");
        showStreamLogsGraph(OUTPUT_COUNTER_VOLUME, COUNTER_PRODUCED);

        drawHeaderWithHelp(OUTPUT_COUNTER_FILES, 2, "ebslStreamNumberOfFilesWritten",
                           "DDP_Bubble_443_ENM_EBSLSTREAM_NUMBER_OF_FILES_WRITTEN");
        showStreamLogsGraph(OUTPUT_COUNTER_FILES, 'numoffileswritten');

        drawHeaderWithHelp(OUTPUT_COUNTER_FILE_REWRITES, 2, "ebslStreamNumberOfFilesRewrites",
                           "DDP_Bubble_470_ENM_EBSLSTREAM_NUMBER_OF_FILE_REWRITES");
        showStreamLogsGraph(OUTPUT_COUNTER_FILE_REWRITES, 'numOfFileReWritten');
    }
    sessionAgg();
    fdnMosCreated();
}

if (isset($_GET['daily'])) {
    /* Daily Totals Help Bubble */
    $DailyTotal = "DDP_Bubble_401_ENM_EBSLSTREAM_Daily_Totals";
    drawHeaderWithHelp("EBS-L Stream Statistics" , 1, "DailyTotals", $DailyTotal);

    /* Gets the name of the instance, then displays it */
    $daily = $_GET['daily'];
    echo "<a name=top><h1>Instance name: " . $daily . "</h1>\n";

    if(isset($_GET[EPS_ID])) {
      echo "<a name=top><h1>JVM: ". $_GET[EPS_ID] . "</h1>\n";
    }
    /* Daily Totals table */
    $DailyTable = new DailyTotals();
    echo $DailyTable->getClientSortableTableStr(50,array(100,1000));
} else {
    mainFlow($statsDB);
}

include PHP_ROOT . "/common/finalise.php";

