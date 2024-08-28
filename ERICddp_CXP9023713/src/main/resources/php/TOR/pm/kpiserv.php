<?php
$pageTitle = "NHM";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/tableFunctions.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

$kpiservice = requestValue('kpiserv');
$kpicalcserv = requestValue('kpicalcserv');

const CNT = 'count';
const KPI_INSTR = 'enm_kpiserv_instr';
const CALC_INSTR = 'enm_kpicalcserv_instr';
const ACTIVE_KPIS = 'enm_nhm_activekpis';
const SERVERS = 'servers';
const RO_NAME = 'enm_nhm_ro.roname';
const DT_CALC = 'DT_KPICalcServ';
const SERVERSHOST = 'servers.hostname';
const STARTTIME = " 00:00:00";
const ENDTIME = " 23:59:59";

function rtKPIParams() {
    return array(
                'Number of Mediation Events Received' => 'numberOfMediationEventsReceived',
                'Number of Discarded Mediation Events' => 'numberOfDiscardedMediationEvents',
                'Number of Realtime KPIs Successfully Generated' => 'numberOfRealTimeKpisSuccessfullyGenerated',
                'Number of Realtime PM Counters Used' => 'numberOfPmCountersUsed',
                'Number of Realtime WebPush Events' => 'numberOfRealTimeWebPushEvents',
                'Number of Failed Realtime WebPush Events' => 'numberOfFailedRealTimeWebPushEvents'
    );
}

function criteriaKPIParams() {
    return array(
                'numberOfExecutedQueries' => 'Total number of executed criteria based queries',
                'numberOfQueriesResolvedFromCache' => 'Total number of queries results resolved from cache',
                'numberOfNetworkElementsRetrievedFromQueriesExecution' =>
                'Total number of Network Elements retrieved from queries execution',
                'numberOfNetworkElementsRetrievedFromCachedQueriesResults' =>
                'Total number of Network Elements retrieved from cached queries results',
                'numberOfKpiActuallyUpdated' => 'Total number of KPIs updated',
                'numberOfAllKpisExamined' => 'Total number of KPIs examined'
    );
}

function kpiServiceParams() {
    return array(
        array(
            SqlPlotParam::TITLE => 'File Notifications Received',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'notificationHandler_NumberOfFileNotificationsReceived'  =>
                'notificationHandler_Number Of FileNotifications Received'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'Files Successfully Parsed',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'parserHandler_NumberOfFilesSuccessfullyParsed' =>
                'parserHandler_NumberOfFiles Successfully Parsed'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'Files UnSuccessfully Parsed',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'parserHandler_NumberOfFilesUnSuccessfullyParsed' =>
                'parserHandler_NumberOfFilesUnSuccessfullyParsed'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'Files To Parse',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'notificationHandler_NumberOfFilesFoundOnSystem' =>
                'notificationHandler_NumberOfFilesFoundOnSystem'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'Average File Processing Time',
            SqlPlotParam::Y_LABEL => "Millisec",
            'cols' => array(
                'averageKpiCalculationTime' =>
                'averageKpiCalculationTime'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'PM Counters Parsed',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'parserHandler_NumberOfPmCountersParsed' =>
                'parserHandler_NumberOf PmCounters Parsed'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'MOs Generated',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'moGeneratorHandler_NumberOfMOsGenerated' =>
                'numberOfMOs Generated'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'PM Counters Used',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'kpiRuleHandler_NumberOfPmCountersUsed' =>
                'kpiRuleHandler_NumberOfPmCounters Used'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'KPIs Successfully Generated',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'kpiRuleHandler_NumberOfKPIsSuccessfullyGenerated' =>
                'kpiRuleHandler_NumberOfKPIsSuccessfully Generated'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'KPI Values Write Succ',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'numberOfKpiValuesWriteSucc' =>
                'numberOfKpiValues WriteSucc'
            )
        ),
        array(
            SqlPlotParam::TITLE => 'KPI Values Write Fail',
            SqlPlotParam::Y_LABEL => CNT,
            'cols' => array(
                'numberOfKpiValuesWriteFail' =>
                'numberOfKpiValuesWriteFail'
            )
        )
    );
}
function activeKPICols() {
    return array(
                'numberOf_activeCellLevelKPI', 'avgNumberOfNodes_activeCellLevelKPI', 'numberOf_activeNodeLevelKPI',
                'avgNumberOfNodes_activeNodeLevelKPI', 'numberOfActiveKpis', 'avgNumberOfNodesOfActiveKpis',
                'numberOfActiveEUtranCellFDDKpis', 'avgNumberOfNodesOfActiveEUtranCellFDDKpis',
                'numberOfActiveEUtranCellTDDKpis', 'avgNumberOfNodesOfActiveEUtranCellTDDKpis',
                'numberOfActiveNbIotCellKpis', 'avgNumberOfNodesOfActiveNbIotCellKpis',
                'numberOfActiveENodeBFunctionKpis', 'avgNumberOfNodesOfActiveENodeBFunctionKpis',
                'numberOfActiveERBSKpis', 'avgNumberOfNodesOfActiveERBSKpis',
                'numberOfActiveRadioNodeKpis', 'avgNumberOfNodesOfActiveRadioNodeKpis', 'numberOfActivePicoKpis',
                'avgNumberOfNodesOfActivePicoKpis', 'numberOfActiveRBSKpis', 'avgNumberOfNodesOfActiveRBSKpis',
                'numberOfActiveRNCKpis', 'avgNumberOfNodesOfActiveRNCKpis', 'numberOfActiveRncFunctionKpis',
                'avgNumberOfNodesOfActiveRncFunctionKpis', 'numberOfActiveUtranCellKpis',
                'avgNumberOfNodesOfActiveUtranCellKpis', 'numberOfActiveNodeBFunctionKpis',
                'avgNumberOfNodesOfActiveNodeBFunctionKpis', 'numberOfActiveHsDschResourcesKpis',
                'avgNumberOfNodesOfActiveHsDschResourcesKpis', 'numberOfActivePmGroupUnitMeasKpis',
                'avgNumberOfNodesOfActivePmGroupUnitMeasKpis', 'numberOfActivePmGroupPortMeasKpis',
                'avgNumberOfNodesOfActivePmGroupPortMeasKpis', 'numberOfActiveFronthaulKpis',
                'avgNumberOfNodesOfActiveFronthaulKpis', 'numberOfActiveRouter6672Kpis',
                'avgNumberOfNodesOfActiveRouter6672Kpis', 'numberOfActiveRouter6675Kpis',
                'avgNumberOfNodesOfActiveRouter6675Kpis', 'numberOfActivePortHistoryKpis',
                'avgNumberOfNodesOfActivePortHistoryKpis', 'numberOfActiveGlobalKpis',
                'avgNumberOfNodesOfActiveGlobalKpis', 'numberOfActiveContextKpis',
                'avgNumberOfNodesOfActiveContextKpis', 'numberOfActiveDot1qHistoryKpis',
                'avgNumberOfNodesOfActiveDot1qHistoryKpis', 'numberOfActiveLinkGroupHistoryKpis',
                'avgNumberOfNodesOfActiveLinkGroupHistoryKpis', 'numberOfActiveRadioTNodeKpis',
                'avgNumberOfNodesOfActiveRadioTNodeKpis', 'numberOfActiveEthernetPortKpis',
                'avgNumberOfNodesOfActiveEthernetPortKpis',
                'numberOfActiveVlanPortKpis', 'avgNumberOfNodesOfActiveVlanPortKpis', 'numberOfActiveInterfaceIPV4Kpis',
                'avgNumberOfNodesOfActiveInterfaceIPV4Kpis', 'numberOfActiveInterfaceIPV6Kpis',
                'avgNumberOfNodesOfActiveInterfaceIPV6Kpis', 'numberOfActiveSuperChannelKpis',
                'avgNumberOfNodesOfActiveSuperChannelKpis', 'numberOfActiveTgTransportKpis',
                'avgNumberOfNodesOfActiveTgTransportKpis', 'numberOfActiveTwampTestSessionKpis',
                'avgNumberOfNodesOfActiveTwampTestSessionKpis', 'numberOfActiveSgsnMmeKpis',
                'avgNumberOfNodesOfActiveSgsnMmeKpis', 'numberOfActiveSgsnMmeROKpis',
                'avgNumberOfNodesOfActiveSgsnMmeROKpis', 'numberOfActiveMmeFunctionKpis',
                'avgNumberOfNodesOfActiveMmeFunctionKpis', 'numberOfActiveRAKpis', 'avgNumberOfNodesOfActiveRAKpis',
                'numberOfActiveQosClassIdentifierKpis', 'avgNumberOfNodesOfActiveQosClassIdentifierKpis',
                'numberOfActiveIpInterfacePmKpis', 'avgNumberOfNodesOfActiveIpInterfacePmKpis',
                'numberOfActiveEthPortKpis', 'avgNumberOfNodesOfActiveEthPortKpis',
                'numberOfActiveSgsnFunctionKpis', 'avgNumberOfNodesOfActiveSgsnFunctionKpis'
    );
}

function dailyTotalsKPICalcServ() {
    return array(
                'SUM(notificationHandler_NumberOfFileNotificationsReceived)' => 'File Notification Recieved',
                'SUM(parserHandler_NumberOfFilesSuccessfullyParsed)' => 'Files Successfully Parsed',
                'SUM(parserHandler_NumberOfFilesUnSuccessfullyParsed)' => 'Files UnSuccessfully Parsed',
                'SUM(parserHandler_NumberOfPmCountersParsed)' => 'PM Counters Parsed',
                'SUM(moGeneratorHandler_NumberOfMOsGenerated)' => 'MOs Generated',
                'SUM(kpiRuleHandler_NumberOfPmCountersUsed)' => 'PM Counters Used',
                'SUM(kpiRuleHandler_NumberOfKPIsSuccessfullyGenerated)' => 'KPIs Successfully Generated',
                'SUM(numberOfKpiValuesWriteSucc)' => 'KPI Values Write Succ',
                'SUM(numberOfKpiValuesWriteFail)' => 'KPI Values Write Fail'
    );
}

function dailyTotalsKPIServ() {
    return array(
               'SUM(numberOfKpiQueries)' => 'KPI Requests'
    );
}

function dailyTotalsKPICriteriaBased() {
    return array(
                'IFNULL(SUM(numberOfExecutedQueries), 0)' => 'Number Of Queries Executed',
                'IFNULL(SUM(numberOfNetworkElementsRetrievedFromQueriesExecution), 0)' =>
                'Number Of Nodes Retrieved From All Queries',
                'IFNULL(SUM(numberOfKpiActuallyUpdated), 0)' => 'Number Of KPIs Actually Updated',
                'IFNULL(SUM(numberOfAllKpisExamined), 0)' => 'Number Of All KPIs Examined'
    );
}

function showDailyTotals( $table, $cols, $title, $tableName ) {
    global $site, $date;

    $where = "$table.siteid = sites.id AND
              sites.name = '$site' AND
              $table.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
              $table.serverid = servers.id
              GROUP BY servers.hostname WITH ROLLUP";

    $tables = array( $table, StatsDB::SITES, StatsDB::SERVERS );

    $table = SqlTableBuilder::init()
        ->name($tableName)
        ->tables($tables)
        ->where($where)
        ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    foreach ($cols as $key => $value) {
        $table->addSimpleColumn($key, $value);
    }

    echo drawHeader($title, 1, $tableName);
    echo $table->build()->getTable();
}

function showActiveKpis() {
    /* Graphs  */
    drawHeaderWithHelp("Active KPIs", 1, "Active KPIs", "DDP_Bubble_228_ENM_Kpiserv_NHM_ActiveKPIs_Help");
    $aggregations = array('SUM');
    $cols = activeKPICols();
    $data = getAggTableData( KPI_INSTR, $aggregations, $cols );

    if ( isset($data) ) {
        $structuredData = reStructureAggTableData($data, $aggregations, $cols);
        $instances = getInstances(KPI_INSTR, 'time');
        $filteredData = filterSpecificInst( $instances[0], $structuredData, $aggregations );
        $validData = removeEmptyGraphs( $filteredData, $aggregations );
        $sortedData = sortDDPTableData( $validData, ATT, SORT_ASC );
        drawAggTable($sortedData, $aggregations, "Active_KPI_Table", '', true);
        echo addLineBreak(2);
    }
}

function showNHMKPIService() {
    global $date;
    /* Graphs  */
    $sqlParamWriter = new SqlPlotParam();
    drawHeaderWithHelp("NHM KPI Service", 1, DT_CALC);
    $instrGraphParams = kpiServiceParams();
    $graphs = array();
    $dbTable = CALC_INSTR;
    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '%s'
              AND $dbTable.serverid = servers.id";

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = SqlPlotParamBuilder::init()
              ->title($instrGraphParam['title'])
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel($instrGraphParam[SqlPlotParam::Y_LABEL])
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $instrGraphParam['cols'],
                  $dbTables,
                  $where,
                  array('site'),
                  SERVERSHOST
              )
              ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, $date . STARTTIME, $date . ENDTIME, true, 640, 320);
    }
    plotGraphs($graphs);
}

function showNhmActiveKpiTotals() {
    global $statsDB;

    $where = $statsDB->where(ACTIVE_KPIS);
    $where .= " AND enm_nhm_activekpis.serverid = servers.id AND
              enm_nhm_activekpis.roid = enm_nhm_ro.id";

    $tables = array( 'enm_nhm_ro', ACTIVE_KPIS, StatsDB::SITES, StatsDB::SERVERS );

    $table = SqlTableBuilder::init()
        ->name("nhm_activekpi_stats")
        ->tables($tables)
        ->where($where)
        ->addHiddenColumn('roid', 'enm_nhm_ro.id')
        ->addSimpleColumn(RO_NAME, 'ReportingObjects')
        ->addColumn(CNT, CNT, 'Node Count')
        ->addSimpleColumn('kpiname', 'Name')
        ->ctxMenu(
            'client',
            true,
            array('plot' => 'Plot'),
            makeSelfLink(),
            'roid'
        )
        ->sortBy(CNT, 'ASC');

    echo drawHeader("KPIs Created", 2, "ActiveKpiTotals");
    echo $table->build()->getTable();
}

function showClientStats($selectedStr) {
    global $date;

    $list = array();
    $cols = array( CNT => CNT );

    $sqlParamWriter = new SqlPlotParam();

    $where = "enm_nhm_activekpis.siteid = sites.id AND
             sites.name = '%s' AND
             enm_nhm_activekpis.serverid = servers.id AND
             enm_nhm_activekpis.roid = enm_nhm_ro.id AND
             enm_nhm_activekpis.roid IN (%s)
             GROUP BY xaxis, seriesid";
    $tables = array('enm_nhm_ro', ACTIVE_KPIS, 'sites', SERVERS);

    foreach ( $cols as $dbCol => $label ) {
        $sqlParam = SqlPlotParamBuilder::init()
              ->title($label)
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel()
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  array( $dbCol => $label ),
                  $tables,
                  $where,
                  array('site', 'ids'),
                  RO_NAME
              )
              ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $extraArgs = "&ids=$selectedStr";
        $list[] = $sqlParamWriter->getImgURL($id, $date . STARTTIME, $date . ENDTIME, true, 800, 320, $extraArgs);
    }
    plotGraphs($list);
}

function showNHMRealtimeKPIService() {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();
    drawHeaderWithHelp("NHM Realtime KPI Service", 1, DT_CALC);
    $graphParams = rtKPIParams();

    $dbTable = CALC_INSTR;
    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id ";

    foreach ( $graphParams as $title => $column ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($title)
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel("Count")
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $column => $title ),
                $dbTables,
                $where,
                array( 'site' ),
                SERVERSHOST
            )
            ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $graphs[] = $sqlParamWriter->getImgURL($id, $date . STARTTIME, $date . ENDTIME, true, 640, 320);
    }
    plotGraphs($graphs);
}

function showNhmDailyTotalsCriteriaBasedKPIs() {
    global $statsDB;

    $where = $statsDB->where( CALC_INSTR );
    $where .= " AND enm_kpicalcserv_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $tables = array( CALC_INSTR, StatsDB::SITES, StatsDB::SERVERS );
    $cols = dailyTotalsKPICriteriaBased();

    $table = SqlTableBuilder::init()
        ->name("nhm_criteriabasedkpis_stats")
        ->tables($tables)
        ->where($where)
        ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

        foreach ($cols as $key => $value) {
        $table->addSimpleColumn($key, $value);
        }
        $table = $table->build();

    if ( $table->hasRows() ) {
        echo drawHeader("Daily Totals (Criteria Based KPIs)", HEADER_2, "DailyTotalsCriteriaBasedKPIs");
        echo $table->getTable();
    }
}

function showNhmCriteriaBasedKPIs( $dbTable ) {
    global $date;
    /* Graphs  */
    $sqlParamWriter = new SqlPlotParam();
    drawHeader("NHM Criteria Based KPIs", HEADER_2, "NHMCriteriaBasedKPIs");
    $graphParams = criteriaKPIParams();
    $dbTables = array( CALC_INSTR, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id ";

    foreach ( $graphParams as $column => $lable ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($lable)
            ->type(SqlPlotParam::STACKED_BAR)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $column => $lable ),
                $dbTables,
                $where,
                array( 'site' ),
                SERVERSHOST
            )
            ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $graphs[] = $sqlParamWriter->getImgURL($id, $date . STARTTIME, $date . ENDTIME, true, 500, 320);
    }
    plotGraphs($graphs);
}

function showLinks() {
    global $kpiservice, $kpicalcserv;

    if (  $kpiservice != $kpicalcserv) {
        $genJmxLinks = array(
                            AHREF . makeGenJmxLink($kpiservice) . "\">Generic JMX $kpiservice</a>",
                            AHREF . makeGenJmxLink($kpicalcserv) . "\">Generic JMX $kpicalcserv</a>"
        );
    } else {
        $genJmxLinks = array( AHREF . makeGenJmxLink($kpiservice) . "\">Generic JMX $kpiservice</a>" );
    }
    $links = array(
        makeLink('/TOR/pm/restqueries.php', 'REST Queries', array( SERVERS => makeSrvList($kpiservice))),
        makeLink('/TOR/dps.php', 'DPS', array( SERVERS => makeSrvList($kpiservice))),
        makeAnchorLink('NHMRealtimeKPIService', 'NHM Realtime KPI Service'),
        makeAnchorLink('ActiveKpiTotals', 'KPIs Created'),
        makeAnchorLink('DailyTotalsCriteriaBasedKPIs', 'NHM Criteria Based KPIs')
    );
    foreach ($genJmxLinks as $link) {
        $links[] = $link;
    }
    echo makeHTMLList($links);
}

function mainFlow() {
    showDailyTotals(CALC_INSTR, dailyTotalsKPICalcServ(), 'Daily Totals (KPICalcServ)', DT_CALC);
    showDailyTotals(KPI_INSTR, dailyTotalsKPIServ(), 'Daily Totals (KPIServ)', 'DT_KPIServ');
    showLinks();
    showNHMKPIService();
    showNHMRealtimeKPIService();
    showActiveKpis();
    showNhmActiveKpiTotals();
    showNhmDailyTotalsCriteriaBasedKPIs();
    showNhmCriteriaBasedKPIs( CALC_INSTR );
}

$statsDB = new StatsDB();

if ( issetURLParam('selected') ) {
    $selectedStr = requestValue('selected');
    if ( requestValue('plot') == 'true' ) {
        $selectedArr = explode(',', $selectedStr);
        $graphs = buildGraphsFromSelectedMetrics( $selectedArr, KPI_INSTR );
        plotGraphs($graphs);
    } elseif ( issetURLParam('client') ) {
        showClientStats($selectedStr);
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
