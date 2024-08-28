<?php
$pageTitle = "AxeFMSnmp";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const INST = 'Instance';
const ENM_MSSNMPFM_INSTR = 'enm_mssnmpfm_instr';

function apgfmStatsAlarmsTotals($serverIds) {
   global $site, $date;
   $where = "enm_mssnmpfm_instr.siteid = sites.id AND sites.name = '$site' AND enm_mssnmpfm_instr.time BETWEEN '$date 00:00:00' AND
   '$date 23:59:59' AND enm_mssnmpfm_instr.serverid IN($serverIds) AND enm_mssnmpfm_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
   $table = SqlTableBuilder::init()
         ->name("apgfmalarmstotals")
         ->tables(array( ENM_MSSNMPFM_INSTR, StatsDB::SITES, StatsDB::SERVERS))
         ->where($where)
         ->addSimpleColumn("IFNULL(servers.hostname, 'Totals')", INST)
         ->addSimpleColumn('SUM(alarmProcessingPing)', 'Alarm Processing Ping')
         ->addSimpleColumn('SUM(alarmProcessingDiscarded)', 'Alarm Processing Discarded')
         ->addSimpleColumn('SUM(alarmProcessingInvalidRecordType)', 'Alarm Processing Invalid Record Type')
         ->addSimpleColumn('SUM(forwardedProcessedAlarmFailures)', 'Forwarded Processed Alarm Failures' )
         ->addSimpleColumn('SUM(alarmsReceived)', 'Alarms Received' )
         ->addSimpleColumn('SUM(alarmsProcessingNotSupported)', 'Alarms Processing Not Supported' )
         ->addSimpleColumn('SUM(alarmsProcessingFailures)', 'Alarms Processing Failures')
         ->addSimpleColumn('SUM(multiEventProcessed)', 'Multi Event Processed')
         ->addSimpleColumn('SUM(multiEventReordered)', 'Multi Event Reordered')
         ->addSimpleColumn('SUM(multiEventFailed)', 'Multi Event Failed')
         ->addSimpleColumn('SUM(alarmProcessingSuccess)', 'Alarm Processing Success')
         ->addSimpleColumn('SUM(alarmProcessingLossOfTrap)', 'Alarm Processing Loss Of Trap')
         ->addSimpleColumn('SUM(alarmsForwarded)', 'Forwarded')
         ->build();
   echo $table->getTableWithHeader("MSAPGFM Alarms Totals", 2, "", "", "APGFM_ALARMS_TOTALS");
}

function apgfmStatsTrapsTotals($serverIds) {
   global $site, $date;
   $where = "enm_mssnmpfm_instr.siteid = sites.id AND sites.name = '$site' AND enm_mssnmpfm_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND enm_mssnmpfm_instr.serverid IN($serverIds) AND enm_mssnmpfm_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
   $table = SqlTableBuilder::init()
         ->name("apgfmtrapstotals")
         ->tables(array( ENM_MSSNMPFM_INSTR, StatsDB::SITES, StatsDB::SERVERS))
         ->where($where)
         ->addSimpleColumn("IFNULL(servers.hostname, 'Totals')", INST)
         ->addSimpleColumn('SUM(trapsReceived)', 'Received Traps')
         ->addSimpleColumn('SUM(trapsDiscarded)', 'Discarded Traps')
         ->addSimpleColumn('SUM(trapsForwarded)', 'Forwarded Traps')
         ->addSimpleColumn('SUM(trapsForwardedFailures)', 'Failed Forward Traps')
         ->addSimpleColumn('SUM(numOfSupervisedNodes)', 'Supervised Nodes')
         ->addSimpleColumn('SUM(numOfSuspendedNodes)', 'Suspended Nodes')
         ->addSimpleColumn('SUM(numOfHBFailureNodes)', 'HB Failure Nodes')
         ->build();
    echo $table->getTableWithHeader("MSAPGFM Traps Totals", 2, "", "", "APGFM_TRAPS_TOTALS");
}

function showGraphs($sectionTitle, $serverIds, $graphParams, $colCount, $helpBubbleName) {
  global $date;

  if ( $colCount == 2 ) {
    $width = 600;
  } else if ( $colCount == 3 ) {
    $width = 400;
  } else {
    $width = 300;
  }

  drawHeaderWithHelp($sectionTitle, 2, $helpBubbleName, "");

  $sqlParamWriter = new SqlPlotParam();

  $graphTable = new HTML_Table("border=0");
  $where = "enm_mssnmpfm_instr.siteid = sites.id AND sites.name = '%s' AND enm_mssnmpfm_instr.serverid = servers.id AND enm_mssnmpfm_instr.serverid IN ($serverIds)";
  $dbTables = array( ENM_MSSNMPFM_INSTR, "sites", "servers" );

  foreach ( $graphParams as $graphRow ) {
    $row = array();
    foreach ( $graphRow as $title => $column ) {
      $sqlParam = SqlPlotParamBuilder::init()
                ->title($title)
                ->type(SqlPlotParam::STACKED_BAR)
                ->barwidth(60)
                ->yLabel("")
                ->addQuery(
                    SqlPlotParam::DEFAULT_TIME_COL,
                    array( $column => $title ),
                    $dbTables,
                    $where,
                    array('site'),
                    "servers.hostname"
                 )
                ->build();
      $id = $sqlParamWriter->saveParams($sqlParam);

      $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, $width, 320);
    }
    $graphTable->addRow($row);
  }
  echo $graphTable->toHTML();
}

function msapgfmGraphs($serverIds) {
    global $debug, $webargs, $php_webroot, $date, $site;
    echo "<H1 id=\"MSSNMPFM_anchor\">MSAPGFM Engine Stats Instrumentation</H1>\n";

   showGraphs(
       'Nodes Operation',
       $serverIds,
       array(
       array('Supervised IS Blades' => 'numOfSupervisedNodes',
             'Suspended IS Blades'  => 'numOfSuspendedNodes',
             'HB Failure IS Blades' => 'numOfHBFailureNodes')
       ),
       3,
       'msSnmpFm_Engine_Stats_Nodes_Operation'
   );

   showGraphs(
       'Received',
       $serverIds,
       array(
       array('Traps' => 'trapsReceived', 'Alarms' => 'alarmsReceived'),
       ),
       2,
       'msSnmpFm_Engine_Stats_Nodes_Received'
   );
   showGraphs(
       'Forwarded',
       $serverIds,
       array(
       array('Traps' => 'trapsForwarded', 'Alarms' => 'alarmsForwarded'),
       ),
       2,
       'msSnmpFm_Engine_Stats_Nodes_Forwarded'
   );
   showGraphs(
       'Alarms Processed',
       $serverIds,
       array(
       array('Success' => 'alarmProcessingSuccess', 'Ping' => 'alarmProcessingPing')
       ),
       2,
       'msSnmpFm_Engine_Stats_Nodes_Alarms_Processed'
   );
   showGraphs(
       'Multi Event Processed',
       $serverIds,
       array(
       array('Processed' => 'multiEventProcessed',
             'Reordered' => 'multiEventReordered',
             'Failed' => 'multiEventFailed')
       ),
       3,
       'msSnmpFm.Engine.StatsHelp'
   );
   showGraphs(
       'Alarm Processing Failures',
       $serverIds,
       array(
       array('Processing' => 'alarmsProcessingFailures',
             'Loss Of Trap' => 'alarmProcessingLossOfTrap',
             'Discarded' => 'alarmProcessingDiscarded'),
       array('Invalid Record Type' => 'alarmProcessingInvalidRecordType',
             'Not Supported' => 'alarmsProcessingNotSupported',
             'Forwarded Processed' => 'forwardedProcessedAlarmFailures')
       ),
       3,
       'msSnmpFm_Engine_Stats_Nodes_Alarm_Processing_Failures'
   );
   showGraphs(
       'Trap Issues',
       $serverIds,
       array(
       array('Discarded' => 'trapsDiscarded',
             'Forwarded Failures' => 'trapsForwardedFailures')
       ),
       2,
       'msSnmpFm_Engine_Stats_Nodes_Trap_Issues'
   );
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site,$rootdir;
    $servicegroup = requestValue('servicegroup');
    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, $servicegroup);
    $serverIds=implode(",", array_values($processingSrv));
    apgfmStatsAlarmsTotals($serverIds);
    apgfmStatsTrapsTotals($serverIds);
    msapgfmGraphs($serverIds);
}
$statsDB = new StatsDB();
mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>


