<?php
$pageTitle = "FMX Engine Metrics";
$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

const START_TIME = 'startTime';
const ENM_FMX_MONITOR = 'enm_fmx_monitor';
const SERVERS = 'servers';
const SITES = 'sites';
const COUNT = 'Count';
const ACTIVE_RULE_CONTEXT = 'Active Rule Context';
const ENM_FMX_MESSAGE_QUEUE = 'enm_fmx_message_queue';
const SQL_WHERE = "enm_fmx_monitor.siteid = sites.id
                   AND sites.name = '%s'
                   AND enm_fmx_monitor.serverid = servers.id
                   AND servers.hostname = '%s'";

function FmxMonitorMetrics($statsDB) {
  global $site, $date;
  $statsDB->query("
SELECT
  SUM(alarmCreated) AS alarmCreated,
  SUM(alarmDeleted) AS alarmDeleted,
  SUM(RuleContextCreated) AS RuleContextCreated,
  SUM(RuleContextDeleted) AS RuleContextDeleted
 FROM enm_fmx_monitor, sites
 WHERE
  enm_fmx_monitor.siteid = sites.id AND sites.name = '$site' AND
  enm_fmx_monitor.startTime BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  $table = new HTML_Table("border=1");
  $table->addRow(array('Item','Total'), null, 'th');
  while($row = $statsDB->getNextRow()) {
    $table->addRow(array('Received Alarms',$row[0]));
    $table->addRow(array('Closed Alarms',$row[1]));
    $table->addRow(array('Started Rules',$row[2]));
    $table->addRow(array('Finished Rules',$row[3]));
  }
  echo $table->toHTML();
}

function getFmxMetricStats($serverId, $blockType, $name) {
    global $date, $site, $statsDB;

    $cols = array(
        array( 'key' => 'module', 'db' => 'moduleName', 'label' => 'Module'),
        array( 'key' => 'rule', 'db' => 'ruleName', 'label' => 'Rule' ),
        array( 'key' => 'blockId', 'db' => 'blockID', 'label' => 'Block ID' ),
        array( 'key' => 'blockType', 'db' => 'blockType', 'label' => 'Block Type'),
        array( 'key' => 'blockName', 'db' => 'blockkName', 'label' => 'Block Name'),
        array( 'key' => 'totalCount', 'db' => 'SUM(count)', 'label' => 'Total Count'),
        array( 'key' => 'average', 'db' => 'ROUND(AVG(count),0)', 'label' => 'Average')
    );

    $where = $statsDB->where( 'enm_fmx_rule', 'startTime' );
    $where .= " AND enm_fmx_rule.serverid = servers.id AND enm_fmx_rule.blockType LIKE '%$blockType%'";
    $where .= " AND enm_fmx_rule.serverid = '$serverId'";
    $where .= " GROUP BY moduleName,ruleName,blockID ORDER BY SUM(enm_fmx_rule.count) DESC LIMIT 10";

    $table = new SqlTable("Most_Frequent_Rule_$name",
        $cols,
        array( 'enm_fmx_rule', SITES, SERVERS),
        $where,
        TRUE
    );
    echo $table->getTableWithHeader("Most Frequent Rule $name (Top 10)", 2, "", "");
}

function showProcessedAlarmGraphs($instances) {
    global  $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $row = array();
    $header = array();
    /* E2E Instrumentation Graphs */
    drawHeaderWithHelp("Processed Alarms", 1, "Processed_Alarms");
    foreach ( $instances as $instance ) {
        $header[] = $instance;
        $where = SQL_WHERE;
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('Processed Alarms')
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel(COUNT)
            ->barWidth(60)
            ->makePersistent()
            ->addQuery(
                START_TIME,
                array ('alarmCreated' => 'alarmCreated', 'alarmDeleted' => 'alarmDeleted'),
                array(ENM_FMX_MONITOR, SITES, SERVERS),
                $where,
                array( 'site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
    }
    $graphTable->addRow($header, null, 'th');
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showProcessedRuleContextGraphs($instances) {
    global $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $row = array();
    $header = array();
    /* E2E Instrumentation Graphs */
    drawHeaderWithHelp("Processed Rule Context", 1, "Processed_Rule_Context");
    foreach ( $instances as $instance ) {
        $header[] = $instance;
        $where = SQL_WHERE;
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('processed Rule Context')
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel(COUNT)
            ->barWidth(60)
            ->makePersistent()
            ->addQuery(
                START_TIME,
                array ('RuleContextCreated'=>'RuleContextCreated','RuleContextDeleted'=>'RuleContextDeleted'),
                array(ENM_FMX_MONITOR, SITES, SERVERS),
                $where,
                array( 'site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
    }
    $graphTable->addRow($header, null, 'th');
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showActiveAlarmGraphs($instances) {
    global $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $row = array();
    $header = array();
    /* E2E Instrumentation Graphs */
    drawHeaderWithHelp("Active Alarms", 1, "Active_Alarms");
    foreach ( $instances as $instance ) {
        $header[] = $instance;
        $where = SQL_WHERE;
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('Active Alarms')
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel(COUNT)
            ->forceLegend()
            ->barWidth(60)
            ->makePersistent()
            ->addQuery(
                START_TIME,
                array('activeAlarms' => 'Active Alarms' ),
                array(ENM_FMX_MONITOR, SITES, SERVERS),
                $where,
                array( 'site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
    }
    $graphTable->addRow($header, null, 'th');
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showActiveRuleContextGraphs($instances) {
    global $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $row = array();
    $header = array();
    /* E2E Instrumentation Graphs */
    drawHeaderWithHelp(ACTIVE_RULE_CONTEXT, 1, "Active_Rule_Context");
    foreach ( $instances as $instance ) {
        $header[] = $instance;
        $where = SQL_WHERE;
        $sqlParam = SqlPlotParamBuilder::init()
            ->title(ACTIVE_RULE_CONTEXT)
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel(COUNT)
            ->barWidth(60)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                START_TIME,
                array('activeRuleContext' => ACTIVE_RULE_CONTEXT),
                array(ENM_FMX_MONITOR, SITES, SERVERS),
                $where,
                array( 'site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
    }
    $graphTable->addRow($header, null, 'th');
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showQueuedMessagesGraphs($instances) {

    global $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $row = array();

    drawHeaderWithHelp("Queued Messages", 1, "Queued_Message");

    foreach ( $instances as $instance ) {
        $where = "enm_fmx_message_queue.siteid = sites.id
                  AND sites.name = '%s'
                  AND enm_fmx_message_queue.serverid = servers.id
                  AND servers.hostname = '%s'";
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('Queued Messages')
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel(COUNT)
            ->barWidth(60)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                START_TIME,
                array('AllQueueLength' => 'allQueueLength', 'ContextsQueueLength' => 'contextsQueueLength'),
                array(ENM_FMX_MESSAGE_QUEUE, SITES, SERVERS),
                $where,
                array( 'site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
        break;
    }
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showMessagesRateGraphs($instances) {

    global $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $row = array();

    drawHeaderWithHelp("Messages Rate", 1, "Message_Rate");

    foreach ( $instances as $instance ) {
        $where = "enm_fmx_message_queue.siteid = sites.id
                  AND sites.name = '%s'
                  AND enm_fmx_message_queue.serverid = servers.id
                  AND servers.hostname = '%s'";
        $sqlParam = SqlPlotParamBuilder::init()
            ->title('Messages Rate')
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel('messages/sec')
            ->barWidth(60)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                START_TIME,
                array('AllQueueRate' => 'allQueueRate', 'ContextsQueueRate' => 'contextsQueueRate'),
                array(ENM_FMX_MESSAGE_QUEUE, SITES, SERVERS),
                $where,
                array( 'site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
        break;
    }
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function getServerDetails($statsDB, $instance) {
   global $date, $site;
   $serverId = 0;
   $statsDB->query("
SELECT DISTINCT(enm_fmx_rule.serverid) AS serverId
FROM enm_fmx_rule,sites,servers
WHERE enm_fmx_rule.siteid = sites.id AND
 sites.name = '$site' AND
 servers.hostname = '$instance' AND
 enm_fmx_rule.startTime BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 servers.id = enm_fmx_rule.serverid");
   while ( $row = $statsDB->getNextRow() ) {
       $serverId = $row[0];
   }
   return $serverId;

}

function mainFlow($statsDB) {

    drawHeaderWithHelp("Processing Totals", 2, "Processing_Totals");
    FmxMonitorMetrics($statsDB);

    $instances = getInstances("enm_fmx_rule", START_TIME);
    if ( isset($instances[0]) ) {
        $serverId = getServerDetails($statsDB, $instances[0]);
        getFmxMetricStats($serverId, 'TRIGGER', 'Trigger');
        getFmxMetricStats($serverId, 'END-RULE', 'EndRule');
    }

    $instances = getInstances(ENM_FMX_MONITOR, START_TIME);
    showActiveAlarmGraphs($instances);
    showProcessedAlarmGraphs($instances);
    showActiveRuleContextGraphs($instances);
    showProcessedRuleContextGraphs($instances);
    showQueuedMessagesGraphs($instances);
    showMessagesRateGraphs($instances);
}
$statsDB = new StatsDB();
mainFlow($statsDB);
include_once PHP_ROOT . "/common/finalise.php";
