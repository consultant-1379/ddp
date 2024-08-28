<?php
$pageTitle = "EBSM Statistics";

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

const INST_DB_TABLE = "enm_ebsm_inst_stats";
const INSTANCE = 'Instance';

require_once 'HTML/Table.php';

const LTE = 'LTE';
const MME = 'MME';
const NR = 'NR';

const EVENTS_COL = 'eventsprocessed';
const CNTRS_COL = 'countersproduced';
const FILES_COL = 'numoffileswritten';


$TECH_TO_LABEL = array(
    LTE => 'EBS-L',
    MME => 'EBS-M',
    NR => 'EBS-N'
);

function getEbsmStatisticsTotals($hasCounterPerTech) {
    global $site, $date, $statsDB, $TECH_TO_LABEL;

  $where = <<<EOS
enm_ebsm_inst_stats.siteid = sites.id AND sites.name = '$site' AND
enm_ebsm_inst_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_ebsm_inst_stats.serverid = servers.id AND
enm_ebsm_inst_stats.EpsId = enm_ebsm_epsid.id
GROUP BY inst WITH ROLLUP
EOS;
  $builder = SqlTableBuilder::init()
      ->name("inst_totals")
      ->tables(array(INST_DB_TABLE, "enm_ebsm_epsid", StatsDB::SITES, StatsDB::SERVERS))
      ->where($where)
      ->paginate()
      ->addColumn('inst', 'CONCAT(servers.hostname,"-",enm_ebsm_epsid.EpsIdText)', INSTANCE, 'ddpFormatRollup');
  if ( $hasCounterPerTech ) {
      foreach ( array_keys($TECH_TO_LABEL) as $tech ) {
          $builder->addSimpleColumn("SUM(numoffileswritten$tech)", 'Files Written ' . $TECH_TO_LABEL[$tech])
                  ->addSimpleColumn("SUM(eventsprocessed$tech)", 'Events Processed ' . $TECH_TO_LABEL[$tech])
                  ->addSimpleColumn("SUM(countersproduced$tech)", 'Counters Produced ' . $TECH_TO_LABEL[$tech]);
      }
  } else {
      $builder->addSimpleColumn("SUM(numoffileswrittenLTE)", 'Files Written')
              ->addSimpleColumn("SUM(eventsprocessedLTE)", 'Events Processed')
              ->addSimpleColumn("SUM(countersproducedLTE)", 'Counters Produced');
  }
  $builder->addSimpleColumn("SUM(files_received)", "Files Received");

  echo $builder->build()->getTableWithHeader("Daily Totals", 1, "", "", "Daily_Totals");
}

function getEventRateGraph($hasCounterPerTech, $where) {
    global $TECH_TO_LABEL;

    $builder = SqlPlotParamBuilder::init()
        ->title("Events/sec")
        ->type(SqlPlotParam::STACKED_BAR)
        ->barwidth(3600)
        ->yLabel('')
        ->forceLegend()
        ->disableUserAgg()
        ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_HOURLY);
    if ( $hasCounterPerTech ) {
        foreach ( $TECH_TO_LABEL as $tech => $label ) {
            $dbCol = sprintf("%s%s/3600", EVENTS_COL, $tech);
            $builder->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $dbCol => $label ),
                array(INST_DB_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                $where,
                array('site')
            );
        }
    } else {
        $builder->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            array( 'eventsprocessedLTE' => 'Events/sec' ),
            array(INST_DB_TABLE, StatsDB::SITES, StatsDB::SERVERS),
            $where,
            array('site')
        );
    }
    return $builder->build();
}

function showGraphs($title, $col, $hasCounterPerTech) {
    global $date, $TECH_TO_LABEL;

    debugMsg("showGraphs: title=$title, col=$col, hasCounterPerTech=$hasCounterPerTech ");

    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();

    $where = <<<EOS
enm_ebsm_inst_stats.siteid = sites.id AND sites.name = '%s' AND enm_ebsm_inst_stats.serverid = servers.id
EOS;

    if ( $col === EVENTS_COL ) {
        $id = $sqlParamWriter->saveParams(getEventRateGraph($hasCounterPerTech, $where));
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
    }

    $graphParams = array();
    if ( $hasCounterPerTech ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($title)
            ->type(SqlPlotParam::STACKED_BAR)
            ->barwidth(60)
            ->yLabel('')
            ->forceLegend()
            ->disableUserAgg()
            ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $col . LTE => $TECH_TO_LABEL[LTE] ),
                array(INST_DB_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                $where,
                array('site')
            )
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $col . MME => $TECH_TO_LABEL[MME] ),
                array(INST_DB_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                $where,
                array('site')
            )
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $col . NR => $TECH_TO_LABEL[NR] ),
                array(INST_DB_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                $where,
                array('site')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
        foreach ( array_keys($TECH_TO_LABEL) as $tech ) {
            $graphParams[] = array(
                SqlPlotParam::TITLE => $title . " " . $TECH_TO_LABEL[$tech],
                'col' => $col . $tech
            );
        }
    } else {
        $graphParams[] = array(
            SqlPlotParam::TITLE => $title,
            'col' => $col . LTE
        );
    }

    foreach ($graphParams as $graphParam) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($graphParam[SqlPlotParam::TITLE])
            ->type(SqlPlotParam::STACKED_BAR)
            ->barwidth(60)
            ->yLabel('')
            ->disableUserAgg()
            ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $graphParam['col'] => $graphParam[SqlPlotParam::TITLE] ),
                array(INST_DB_TABLE, StatsDB::SITES, StatsDB::SERVERS),
                $where,
                array('site'),
                SqlPlotParam::SERVERS_HOSTNAME
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
    }

    drawHeader($title, 2, $col);
    plotGraphs($graphs);
}

function ebsIgnoredParams() {
    return array(
        "enm_noise_events_hourly",
        "enm_noise_events_minute",
        "enm_noise_events_ebsl",
        "enm_noise_events_ebsn"
    );
}

function noiseEvents() {
    drawHeader("Noise Events", 2, "noiseEvents");
    $ebsParams = ebsIgnoredParams();
    $graphs = array();

    foreach ( $ebsParams as $graphParam ) {
        $modelledGraph = new ModelledGraph( 'TOR/pm/streaming/' . $graphParam);
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs($graphs);
}

function fdnMosCreated() {
    global $date, $site, $statsDB;
    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, "ebsm");
    $serverIds = implode(",", array_values($processingSrv));
    drawHeader("FDN Mos Created", 2, "fdnmoscreated");
    $graphs = array();
    getGraphsFromSet('fdnMos', $graphs, 'TOR/pm/enm_ebsn_fdn_mos', array('serverid' => $serverIds));
    plotGraphs($graphs);
}

function pmicNotification() {
    drawHeader('Celltrace PMIC Notifications Table', 2, "celltraceTable");
    $table = new ModelledTable('TOR/pm/pmic_celltrace_notification', 'celltraceTable');
    echo $table->getTable();

    /* Get the graphs */
    $graphs = array();
    drawHeader('Celltrace PMIC Notifications', 2, 'celltrace');
    getGraphsFromSet('pmic', $graphs, 'TOR/pm/pmic_celltrace_notification');
    plotGraphs($graphs);
}

function throughputCounters() {
    $graphs = array();
    drawHeader('Session Aggregation Indexes', 2, "throughputcounters");
    getGraphsFromSet('throughput', $graphs, 'TOR/pm/throughput_counters');
    plotGraphs($graphs);
}

function main() {
    global $statsDB;

    echo "<H1>Event Based Statistics (File)</H1>\n";
    $links = array(
        makeAnchorLink(EVENTS_COL, 'Events Processed/sec'),
        makeAnchorLink("noiseEvents", 'Noise Events'),
        makeAnchorLink(CNTRS_COL, 'Output Counter Volume'),
        makeAnchorLink(FILES_COL, 'Output Counter Files'),
        makeAnchorLink("throughputcounters", 'Session Aggregation Indexes'),
        makeAnchorLink("fdnmoscreated", 'FDN Mos Created')
    );
    $pmicNotifAvailable = $statsDB->hasData( 'enm_pmic_notification' );
    if ( $pmicNotifAvailable ) {
        $links[] = makeAnchorLink("celltraceTable", 'Celltrace PMIC Notifications Table');
    }
    echo makeHTMLList($links);

    $hasCounterPerTech = $statsDB->hasData(INST_DB_TABLE, "time", false, "countersproducedMME IS NOT NULL");

    getEbsmStatisticsTotals($hasCounterPerTech);
    showGraphs('Events Processed', EVENTS_COL, $hasCounterPerTech);
    noiseEvents();
    showGraphs('Output Counter Volume', CNTRS_COL, $hasCounterPerTech);
    showGraphs("Output Counter Files", FILES_COL, $hasCounterPerTech);
    throughputCounters();
    fdnMosCreated();
    if ( $pmicNotifAvailable ) {
        pmicNotification();
    }
}

main();

include PHP_ROOT . "/common/finalise.php";
