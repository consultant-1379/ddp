<?php
$pageTitle = "Flow Automation";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

$flowAutomationTable = "enm_flow_automation_flows_log";
$flowExecutionTable = "enm_flow_automation_execution_log";
const EVENT_TYPE = "Event Type";
const FLOWAUTOMATION = "flowautomation";
const EXECUTION = "execution";
const TIME = "time";

function displayCountPerEventTypeTable($type) {
    global $flowAutomationTable, $flowExecutionTable, $statsDB;
    if ($type == "flow") {
        $name='flow_automation_table';
        $header="Flow count per " . EVENT_TYPE;
        $enmFlowTable = "$flowAutomationTable";
    }elseif ($type == EXECUTION) {
        $name='flow_execution_table';
        $header="Flow Execution count per " . EVENT_TYPE;
        $enmFlowTable = "$flowExecutionTable";
    }
    $where = $statsDB->where($enmFlowTable);
    $where .= "GROUP BY $enmFlowTable.EventType WITH ROLLUP";
    $reqBind = SqlTableBuilder::init()
              ->name($name)
              ->tables(array($enmFlowTable, StatsDB::SITES))
              ->where($where)
              ->addSimpleColumn("$enmFlowTable.EventType", EVENT_TYPE)
              ->addSimpleColumn('Count(*)', 'Total')
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader("$header", 2, "", "", "$name");
}

function params($type) {
    global $flowAutomationTable, $flowExecutionTable;
    $duration='Duration';
    if ($type == FLOWAUTOMATION) {
        return array(
                'importedFlowsCount' => 'Total Num Imported Flows',
                'enabledFlowsCount' => 'Total Num Enabled Flows',
                'activatedFlowsCount' => 'Total Num Activated Flows',
                'flowInstancesExecutedCount' => 'Total Num Flow Instances Executed',
                'currentlyRunningFlowsCount' => 'Total Num Flows Currently Executing'
        );
    }
    if ($type == 'flowautomationflowcols') {
        return array(
                'time(time)' => 'Import Time',
                "$flowAutomationTable.EventType" => EVENT_TYPE,
                "$flowAutomationTable.FlowId" => 'Flow Id',
                "$flowAutomationTable.FlowName" => 'Flow Name',
                "$flowAutomationTable.FlowType" => 'Flow Type',
                "$flowAutomationTable.FlowVersion" => 'Flow Version'
        );
    }
    if ($type == 'flowautomationexecols') {
        return array(
                "$flowExecutionTable.EventType" => EVENT_TYPE,
                "$flowExecutionTable.FlowId" => 'Flow Id',
                "$flowExecutionTable.FlowExecutionName" => 'Flow Execution Name',
                "TIMEDIFF(time(time), SEC_TO_TIME($duration))" => 'Start Time',
                'time(time)' => 'End Time',
                "SEC_TO_TIME($duration)" => "$duration"
        );
    }
}

function displayFlowDetailsTable($params, $type) {
    global $flowAutomationTable, $flowExecutionTable, $statsDB;
    if ($type == 'flow') {
        $table = "$flowAutomationTable";
        $name = "flow_automation_details_table";
        $header = "Flow Details";
    } elseif ($type == EXECUTION) {
        $table = "$flowExecutionTable";
        $name = "flow_execution_details_table";
        $header = "Flow Instance Details";
    }
    $where = $statsDB->where($table);
    $reqBind = SqlTableBuilder::init()
              ->name($name)
              ->tables(array($table, StatsDB::SITES))
              ->where($where);
              foreach ($params as $key => $value) {
              $reqBind->addSimpleColumn($key, $value);
              }
              $reqBind->paginate();
    echo $reqBind->build()->getTableWithHeader("$header", 2, "", "", "$name");
}

function drawTable( $params, $table ) {
    global $statsDB;

    $where = $statsDB->where($table);
    $where .= "AND $table.serverid = servers.id
               GROUP BY servers.hostname WITH ROLLUP";
    $builder = SqlTableBuilder::init()
             ->name($table)
             ->tables(array( $table, StatsDB::SITES, StatsDB::SERVERS ))
             ->where($where)
             ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    foreach ($params as $key => $value) {
        $builder->addSimpleColumn("SUM($key)", $value);
    }
    drawHeaderWithHelp('Daily Totals', 2, 'instr');
    echo $builder->build()->getTable();
}

function generateGraph( $table, $title, $col ) {
    global $date, $site;

    $dbTables = array( $table, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$table.siteid = sites.id
              AND sites.name = '%s'
              AND $table.serverid = servers.id";

    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
          ->title($title)
          ->type(SqlPlotParam::STACKED_BAR)
          ->barwidth(60)
          ->yLabel('Count')
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

function drawGraphs( $params, $table ) {
    $graphTable = new HTML_Table("border=0");

    drawHeaderWithHelp('Instrumentation Graphs', 2, 'instr');

    foreach ($params as $key => $value) {
        $graphs[] = generateGraph( $table, $key, $key );
    }

    while ($graphs) {
        $row[] = array_shift($graphs);
        $row[] = array_shift($graphs);
        $graphTable->addRow($row);
        unset($row);
    }

    echo $graphTable->toHTML();
}

$statsDB = new StatsDB();

function mainFlow() {
    global $date, $statsDB;

    $tableASU = 'enm_flow_asu_overallsummary';
    $hasData = $statsDB->hasData($tableASU, TIME, false, 'eventName = "ASU"');
    $page = '/TOR/platform/auto_software_upgrade.php';
    if ( $hasData ) {
        $asulink = makeLink($page, 'Automated Software Upgrade', array('eventName' => "ASU"));
        echo makeHTMLList(array($asulink));
    }

    $tableORAN = 'enm_flow_asu_overallsummary';
    $hasORANData = $statsDB->hasData($tableORAN, TIME, false, 'eventName = "ORAN"');
    if ( $hasORANData ) {
        $oranlink = makeLink($page, 'O-RAN Radio Unit Software Upgrade', array('eventName' => "ORAN"));
        echo makeHTMLList(array($oranlink));
    }

    $table = 'enm_flowautomation';
    $hasData = $statsDB->hasData($table);
    if ( $hasData ) {
        $params = params(FLOWAUTOMATION);
        echo addLineBreak();
        drawTable( $params, $table);
    }

    displayCountPerEventTypeTable('flow');
    displayCountPerEventTypeTable(EXECUTION);
    $params = params('flowautomationflowcols');
    displayFlowDetailsTable($params, "flow");
    $params = params('flowautomationexecols');
    displayFlowDetailsTable($params, EXECUTION);
    echo addLineBreak();
    if ( $hasData ) {
        $params = params(FLOWAUTOMATION);
        echo addLineBreak();
        drawGraphs( $params, $table );
    } else {
        echo "<H1>No Data Available In $table For $date<H1>";
    }
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
