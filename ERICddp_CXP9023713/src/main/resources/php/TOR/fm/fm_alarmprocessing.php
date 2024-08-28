<?php
$pageTitle = "FM Alarm Processing Stats";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/ModelledTable.php";

const SITES = 'sites';
const FM_ALARMPROCESSING_INSTR = 'fm_alarmprocessing_instr';
const SERVERS = 'servers';
const HEIGHT = 'height';
const WIDTH = 'width';
const OPEN_LI_TAG = "<li>";
const CLOSE_LI_TAG = "</li>\n";
const FAILED_ALARM_COUNT = 'failedAlarmCountByAPSPerMinute';
const QUERY_STRING = 'QUERY_STRING';
const ALARM_OVERLOAD ='enm_fm_alarmoverload_protection';
const HOST_NAME = 'IFNULL(servers.hostname,"Totals")';
const SERVERS_HOSTNAME = 'servers.hostname';
const TITLE = 'title';
const SEV = 'severity';
const ROOT = 'root';

$fmalarmprocessing = requestValue('fmalarmprocessing');
$fmhistory = requestValue('fmhistory');

function getFmAlarmOverloadProtection() {
    global $statsDB;

    $where = $statsDB->where(ALARM_OVERLOAD);
    $where .= " AND enm_fm_alarmoverload_protection.serverid = servers.id";
    $reqBind = SqlTableBuilder::init()
              ->name(ALARM_OVERLOAD)
              ->tables(array(ALARM_OVERLOAD, StatsDB::SERVERS, StatsDB::SITES))
              ->where($where)
              ->addSimpleColumn('enm_fm_alarmoverload_protection.time', 'Date & Time')
              ->addSimpleColumn(HOST_NAME, 'Instance')
              ->addSimpleColumn('enm_fm_alarmoverload_protection.overload', 'OVERLOAD')
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader("Alarm Overload Protection", 1, '');
}

function getFmAlarmProcessingDailyTotals() {
    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable('TOR/fm/alarm_proc_dt', 'FMAlarmProcessing_DailyTotals', $selfLink);
    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader("FmAlarmProcessing DailyTotals");
    }
}

function getFmAckTotals($statsDB) {
  global $date, $site;

  $row = $statsDB->queryRow("
SELECT
 count(*)
FROM
 enm_fmack, sites
WHERE
 enm_fmack.siteid = sites.id AND sites.name = '$site' AND
 enm_fmack.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
  ");

  if ( $row[0] == 0 ) {
    return null;
  }

   $where = "enm_fmack.siteid = sites.id
             AND sites.name = '$site'
             AND enm_fmack.serverid = servers.id
             AND enm_fmack.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
             GROUP BY servers.hostname WITH ROLLUP";
    $table = SqlTableBuilder::init()
        ->name('FMHistory_DailyTotals')
        ->tables(array( 'enm_fmack', SITES, SERVERS))
        ->where($where)
        ->addSimpleColumn(HOST_NAME, 'FMHistory Instance')
        ->addSimpleColumn('SUM(ackAlarmCount)', 'Total Alarms Acked')
        ->build();
    echo $table->getTableWithHeader("FMHistory DailyTotals", 2, "", "", "FMHistory_DailyTotals");
}

function showAlarmProcessingGraphs() {
    drawHeaderWithHelp("FMAlarmProcessing Instrumentation", 2, "FMAlarmProcessing_Instrumentation");
    getGraphsFromSet( 'instr', $graphs, 'TOR/fm/alarm_proc_instr', null, 640, 320 );
    //empty element added to force two specific graphs to be side by side
    $graphs[] = '';
    getGraphsFromSet( SEV, $graphs, 'TOR/fm/alarm_proc_instr_instanceless', null, 640, 320 );
    getGraphsFromSet( ROOT, $graphs, 'TOR/fm/alarm_proc_instr_instanceless', null, 640, 320 );
    plotGraphs( $graphs );
}

function plotApsByInstance( $type ) {
    $instances = getInstances(FM_ALARMPROCESSING_INSTR);
    $graphs = array();

    foreach ( $instances as $inst ) {
        if ( $type == SEV ) {
            $params = array( 'server' => $inst, TITLE => "Alarms processed by APS per Severity: $inst" );
            $mg = new ModelledGraph('TOR/fm/fm_aps_by_severity_by_instance');
            $title = "Alarms processed by APS per Severity";
        } elseif ( $type == ROOT ) {
            $params = array( 'server' => $inst, TITLE => "Alarms processed by APS per Root Cause: $inst" );
            $mg = new ModelledGraph('TOR/fm/fm_aps_by_root_by_instance');
            $title = "Alarms processed by APS per Root Cause";
        }
        $graphs[] = $mg->getImage( $params  );
    }
    drawHeaderWithHelp($title, 2, "FMAlarmProcessing_Instrumentation");
    plotGraphs( $graphs );
}

function showAlarmsOpenGraph() {
    global $debug, $webargs, $php_webroot, $date, $site;

    $sqlParam = SqlPlotParamBuilder::init()
        ->title('Open Alarms')
        ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
        ->makePersistent()
        ->addQuery(
            'time',
            array( 'num' => '#Alarms' ),
            array('enm_openalarms', SITES),
            "enm_openalarms.siteid = sites.id AND sites.name = '%s'",
            array('site')
        )
        ->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
}

function showAckGraphs() {
    global $debug, $webargs, $php_webroot, $date, $site;

    echo "<H2>Alarm Acknowledgement</H2>\n";

    $instrGraphParams =
      array(
        array(
          'cols' => array('ackAlarmCount' => 'Alarms Acked'),
          WIDTH => 800,
          HEIGHT => 320,
          'span' => 2
          ),
        array(
          'cols' => array('unAckAlarmCount' => 'UnAck',
                  'manualClearAlarmCount' => 'Manual Clears'),
          WIDTH => 400,
          HEIGHT => 240,
          ),
        array(
          'cols' => array('failedAckAlarmCount' => 'Failed Acks',
                  'failedUnAckAlarmCount' => 'Failed UnAck'),
          WIDTH => 400,
          HEIGHT => 240,
          ),
        array(
          'cols' => array('failedClearAlarmCount' => 'Failed Clears'),
          WIDTH => 400,
          HEIGHT => 240,
          )
        );

    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $rowIndex = 0;
    $where = "enm_fmack.siteid = sites.id AND sites.name = '%s' AND enm_fmack.serverid = servers.id";
    foreach ( $instrGraphParams as $instrGraphRow ) {
        $row = array();
        foreach ( $instrGraphRow['cols'] as $col => $label ) {
            $sqlParam = SqlPlotParamBuilder::init()
                ->title($label)
                ->type(SqlPlotParam::STACKED_BAR)
                ->makePersistent()
                ->barWidth(60)
                ->addQuery(
                    'time',
                    array( $col => $label ),
                    array('enm_fmack', SITES, SERVERS),
                    $where,
                    array('site'),
                    SERVERS_HOSTNAME
                )
                ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $row[] = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                $instrGraphRow[WIDTH],
                $instrGraphRow[HEIGHT]
            );
        }
        $graphTable->addRow($row);
        if ( array_key_exists('span', $instrGraphRow) ) {
            $graphTable->setCellAttributes($rowIndex, 0, "colspan=" . $instrGraphRow['span']);
        }
        $rowIndex++;
    }

    echo $graphTable->toHTML();
}

function getFMBufferedParams() {
    return array(
            array(
                TITLE => 'Alarms Buffered Processed by ACDS (per min)',
                'cols' => array( "bufferedProcessedAlarmsCount" => 'bufferedProcessedAlarmsCount' )
            )
    );
}

function getAlarmOverloadedParams() {
    return array(
            array(
                TITLE => 'Number of Alarms discarded by APS during overload',
                'cols' => array( "alarmCountDiscardedByAPS" => 'Alarm Discarded By APS' )
            ),
            array(
                TITLE => 'Number of Alerts discarded by APS during overload',
                'cols' => array( "alertCountDiscardedByAPS" => 'Alert Discarded By APS' )
            ),
            array(
                TITLE => 'Number of Nodes suppressed by APS during overload',
                'cols' => array( "nodeCountSuppressedByAPS" => 'Node Suppressed' )
            )
    );
}

function showGraphs($type) {
    if ($type == 'fmbuffered') {
        $table = 'enm_fmservnetlog_instr';
        $params = getFMBufferedParams();
        drawHeader("FMServ Instrumentation", 1, "FMServ_Instrumentation");
    } elseif ($type == 'alaramoverloaded') {
        $table = FM_ALARMPROCESSING_INSTR;
        $params = getAlarmOverloadedParams();
        drawHeader("Alarm Overload Protection Instrumentation", 1, "AlarmOverload_Instrumentation");
    }
    global $date;

    $sqlParamWriter = new SqlPlotParam();

    $where = "$table.siteid = sites.id AND sites.name = '%s' AND
              $table.serverid = servers.id";

    $graphs = array();

    foreach ( $params as $param ) {
        $sqlParam = SqlPlotParamBuilder::init()
              ->title($param[TITLE])
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel('Count')
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $param['cols'],
                  array($table, StatsDB::SITES, StatsDB::SERVERS),
                  $where,
                  array('site'),
                  SERVERS_HOSTNAME
              )
              ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 300 );
    }

    plotGraphs($graphs);
}

function showStateEvents() {
    global $rootdir, $debug, $date;

    $seriesFile = $rootdir . "/fm/currentServiceState.json";
    if ( $debug > 0 ) {
        echo "<pre>showStateEvents seriesFile=$seriesFile</pre>\n";
    }
    if ( file_exists($seriesFile) ) {
        drawHeaderWithHelp('currentServiceState Events', 2, 'currentServiceStateEvents');
        echo '<p>Click <a href="'
                  . fromServer(PHP_SELF)
                  . '?' . fromServer(QUERY_STRING)
                  . '&shownodeindex=1">here</a> to see the nodes corresponding to the position on vertical axis.</p>';
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('currentServiceState Events')
            ->type(SqlPlotParam::XY)
            ->yLabel('Node')
            ->seriesFromFile($seriesFile)
            ->build();

        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        echo "<p>" . $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400) . "</p>\n";
    }
}

function showNodeIndex() {
    global $rootdir, $debug;

    $rowDataFile = $rootdir . "/fm/index.json";
    $rowData = json_decode(file_get_contents($rowDataFile), true);

    $table = new DDPTable(
        "nodeindex",
        array(
            array( 'key' => 'ne', 'label' => 'NE' ),
            array( 'key' => 'index', 'label' => 'Number', 'type' => 'int' )
        ),
        array( 'data' => $rowData )
    );
    echo $table->getTable();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site, $fmhistory, $fmalarmprocessing, $statsDB;

    /* Daily Summary table */
    getFmAlarmProcessingDailyTotals();

    $countRow = $statsDB->queryRow("
SELECT COUNT(*) FROM enm_fmack,sites
WHERE
 enm_fmack.siteid = sites.id AND sites.name = '$site' AND
 enm_fmack.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    $hasFmAck = $countRow[0] > 0;

    $countRow = $statsDB->queryRow("
SELECT COUNT(*) FROM enm_openalarms,sites
WHERE
 enm_openalarms.siteid = sites.id AND sites.name = '$site' AND
 enm_openalarms.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    $hasOpenAlarms = $countRow[0] > 0;


    getFmAckTotals($statsDB);

    if ( $hasOpenAlarms ) {
        drawHeaderWithHelp('Open Alarms', 2, 'OpenAlarms');
        showAlarmsOpenGraph();
    }

    showStateEvents();

    $jmxLinks = array(
        "<a href=\"" . makeGenJmxLink($fmalarmprocessing) . "\">Alarm Processing</a>",
        "<a href=\"" . makeGenJmxLink($fmhistory) . "\">Alarm History</a>"
    );
    $dpsLinks = array(
        makeLink("/TOR/dps.php", "Alarm Processing", array(SERVERS => makeSrvList($fmalarmprocessing))),
        makeLink("/TOR/dps.php", "Alarm History", array(SERVERS => makeSrvList($fmhistory)))
    );

    $linkList = array(
        "Generic JMX" . makeHTMLList( $jmxLinks ),
        "DPS Instrumentation" . makeHTMLList( $dpsLinks )
    );

$row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_fmfmx_instr, sites
WHERE
 enm_fmfmx_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_fmfmx_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    if ($row[0] > 0) {
        array_push($linkList, makeLink("/TOR/fm/fmfmx.php", "FM FMX ADAPTOR"));
    }

    echo makeHTMLList( $linkList );

    showAlarmProcessingGraphs();

    if ( $hasFmAck ) {
      showAckGraphs();
    }

    if ( $statsDB->hasData( "enm_fmservnetlog_instr" ) ) {
        showGraphs('fmbuffered');
    }

    getFmAlarmOverloadProtection();

    if ( $statsDB->hasData( FM_ALARMPROCESSING_INSTR )) {
        showGraphs('alaramoverloaded');
    }
}

if (issetURLParam('shownodeindex')) {
    showNodeIndex();
} elseif ( issetURLParam('action') ) {
    $val = requestValue('action');
    if ( $val == SEV || $val == ROOT ) {
        plotApsByInstance( $val );
    }
}else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

