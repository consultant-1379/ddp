<?php
$pageTitle = "Netlog Statitics";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

require_once 'HTML/Table.php';
const AVAILABLE_DISK_SPACE = 'Available Disk Space';
const TOTAL_DISK_SPACE = 'Total Disk Space';

function getNetlogMediationHandler()
{
  global $date, $site;
  $cols = array(
               array( 'key' => 'hostname', 'db' => 'IFNULL(servers.hostname,"All Instances")', 'label' => 'MSNETLOG Instance'),
               array( 'key' => 'numMedTaskRequestReceived', 'db' => 'SUM(msnet.numMedTaskRequestReceived)', 'label' => 'Mediation Task Request received' ),
               array( 'key' => 'numCollectionStarted', 'db' => 'SUM(msnet.numCollectionStarted)', 'label' => 'Upload started' ),
               array( 'key' => 'executionTime', 'db' => 'AVG(msnet.executionTime)', 'label' => 'Average Execution time' )
           );

  $where = "msnet.siteid = sites.id AND sites.name = '$site' AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

  $table = new SqlTable("Netlog_Mediation_Handler",
                        $cols,
                        array( 'enm_msnetlog_instr msnet', 'sites', 'servers'),
                        $where,
                        TRUE
           );
   echo $table->getTableWithHeader("Netlog Mediation Handler", 2, "", "", "Netlog_Mediation_Handler");
}

function getSharedNetlogMediationHandler($statsDB)
{
  global $date, $site;

  $row = $statsDB->queryRow("
SELECT
 count(*)
FROM
 enm_shared_netlog_mediation_handler_instr msnet, sites, servers
WHERE
 msnet.siteid = sites.id AND sites.name = '$site'
 AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

  if ( $row[0] == 0 ) {
    return NULL;
  }

  $cols = array(
               array( 'key' => 'hostname', 'db' => 'IFNULL(servers.hostname,"All Instances")', 'label' => 'MSNETLOG Instance'),
               array( 'key' => 'numMedTaskRequestReceived', 'db' => 'SUM(msnet.numMedTaskRequestReceived)', 'label' => 'Longest time duration of file upload (sec)' ),
               array( 'key' => 'numCollectionStarted', 'db' => 'SUM(msnet.numCollectionStarted)', 'label' => 'Shortest time duration of file upload (sec)' ),
               array( 'key' => 'executionTime', 'db' => 'AVG(msnet.executionTime)', 'label' => 'Dimension of greatest file (Bytes)')
           );

  $where = "msnet.siteid = sites.id AND sites.name = '$site' AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

  $table = new SqlTable("Shared_Netlog_Mediation_Handler",
                        $cols,
                        array( 'enm_shared_netlog_mediation_handler_instr msnet', 'sites', 'servers'),
                        $where,
                        TRUE
           );
   echo $table->getTableWithHeader("Shared Netlog Mediation Handler", 2, "","", "Shared_Netlog_Mediation_Handler");
}

function getNetlogUploadExecution()
{
  global $date, $site;
  $cols = array(
               array( 'key' => 'hostname', 'db' => 'IFNULL(servers.hostname,"All Instances")', 'label' => 'FMSERV Instance'),
               array( 'key' => 'numOfCollectionStarted', 'db' => 'SUM(msnet.numOfCollectionStarted)', 'label' => 'Number of upload started' ),
               array( 'key' => 'numOfCollectionFailed', 'db' => 'SUM(msnet.numOfCollectionFailed)', 'label' => 'Number of upload failed' ),
               array( 'key' => 'numOfReadyForExported', 'db' => 'SUM(msnet.numOfReadyForExported)', 'label' => 'Number of logs ready for download' ),
               array( 'key' => 'numOfCollectionRescheduled', 'db' => 'SUM(msnet.numOfCollectionRescheduled)', 'label' => 'Number of upload rescheduled' )
           );

  $where = "msnet.siteid = sites.id AND sites.name = '$site' AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

  $table = new SqlTable("Netlog_Upload_Execution",
                        $cols,
                        array( 'enm_fmservnetlog_instr msnet', 'sites', 'servers'),
                        $where,
                        TRUE
           );
   echo $table->getTableWithHeader("Netlog Upload Execution", 2, "", "", "Netlog_Upload_Execution");
}

function getNetlogCommandTotals()
{
  global $date, $site;
  $cols = array(
               array( 'key' => 'hostname', 'db' => 'IFNULL(servers.hostname,"All Instances")', 'label' => 'FMSERV Instance'),
               array( 'key' => 'numOfDescribeCommands', 'db' => 'SUM(msnet.numOfDescribeCommands)', 'label' => 'Describe commands received' ),
               array( 'key' => 'numOfUploadCommands', 'db' => 'SUM(msnet.numOfUploadCommands) ', 'label' => 'Upload commands received' ),
               array( 'key' => 'numOfStatusCommands', 'db' => 'SUM(msnet.numOfStatusCommands)', 'label' => 'Status commands received'),
               array( 'key' => 'numOfDownloadCommands', 'db' => 'SUM(msnet.numOfDownloadCommands)', 'label' => 'Download commands received' ),
               array( 'key' => 'numOfDeleteCommands', 'db' => 'SUM(msnet.numOfDeleteCommands)', 'label' => 'Delete commands received' )
           );

  $where = "msnet.siteid = sites.id AND sites.name = '$site' AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

  $table = new SqlTable("Netlog_Commands_Totals",
                        $cols,
                        array( 'enm_fmservnetlog_instr msnet', 'sites', 'servers'),
                        $where,
                        TRUE
           );
   echo $table->getTableWithHeader("Netlog Commands Totals", 2, "", "","Netlog_Commands_Totals");
}

function getNetlogStatisticData()
{
  global $date, $site;
  $cols = array(
               array( 'key' => 'hostname', 'db' => 'IFNULL(servers.hostname,"All Instances")', 'label' => 'FMSERV Instance'),
               array( 'key' => 'longestTimeOfUpload', 'db' => 'MAX(msnet.longestTimeOfUpload)', 'label' => 'Longest time duration of file upload (sec)' ),
               array( 'key' => 'shortestTimeOfUpload', 'db' => 'MIN(msnet.shortestTimeOfUpload)', 'label' => 'Shortest time duration of file upload (sec)' ),
               array( 'key' => 'greatestFileDimension', 'db' => 'SUM(msnet.greatestFileDimension)', 'label' => 'Dimension of greatest file (Bytes)'),
               array( 'key' => 'availableDiskSpace', 'db' => 'SUM(msnet.availableDiskSpace)', 'label' => 'Available disk space (MB)' ),
               array( 'key' => 'totalDiskSpace', 'db' => 'SUM(msnet.totalDiskSpace)', 'label' => 'Total disk space (MB)' ),
               array( 'key' => 'numOfRetentionTimerRun', 'db' => 'SUM(msnet.numOfRetentionTimerRun)', 'label' => 'Retention timer run' ),
               array( 'key' => 'numOfObjectInCache', 'db' => 'SUM(msnet.numOfObjectInCache)', 'label' => 'Object in cache' )
           );

  $where = "msnet.siteid = sites.id AND sites.name = '$site' AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

  $table = new SqlTable("Netlog_Statistic_Data",
                        $cols,
                        array( 'enm_fmservnetlog_instr msnet', 'sites', 'servers'),
                        $where,
                        TRUE
           );
   echo $table->getTableWithHeader("Netlog Statistic Data", 2, "", "","Netlog_Statistic_Data");
}

function plotFmservNetlogGraphs() {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    drawHeaderWithHelp("Fmserv Netlog Statistics", 1, "Fmserv_Netlog_Statistics");
    $graphTable = new HTML_Table("border=0");

    $instrGraphParamsArray = getInstrGraphParamsArray();

    foreach ( $instrGraphParamsArray as $instrGraphParams ) {
      $row = array();
      foreach ($instrGraphParams as $instrGraphParam) {
               $sqlParam = array(
                  SqlPlotParam::TITLE => $instrGraphParam[SqlPlotParam::TITLE],
                  SqlPlotParam::Y_LABEL => $instrGraphParam[SqlPlotParam::Y_LABEL],
                  'useragg' => 'true',
                  'persistent' => 'true',
                  'type' => $instrGraphParam['type'],
                  'sb.barwidth' => 60,
                  'querylist' => array(
                      array (
                          'timecol' => 'time',
                          'whatcol' => $instrGraphParam['cols'],
                          'tables' => "enm_fmservnetlog_instr, sites, servers",
                          'multiseries' => 'servers.hostname',
                          'where' => "enm_fmservnetlog_instr.siteid = sites.id AND sites.name = '%s'  AND enm_fmservnetlog_instr.serverid = servers.id",
                          'qargs' => array( 'site' )
                      )
                  )
              );
             $id = $sqlParamWriter->saveParams($sqlParam);
             $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
      }
      $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function getInstrGraphParamsArray() {
    return array(
        array('numOfUploadCommands' => array(
          SqlPlotParam::TITLE => 'Upload Commands',
          'type' => 'sb',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'cols' => array(
              'numOfUploadCommands'  => 'Upload Commands'
              )
          ),
          'numOfDownloadCommands' => array(
          SqlPlotParam::TITLE => 'Download Commands',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfDownloadCommands '  => 'Download Commands'
              )
          )
         ),
         array('numOfDescribeCommands' => array(
          SqlPlotParam::TITLE => 'Describe Commands',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfDescribeCommands'  => 'Describe Commands'
              )
         ),
         'numOfStatusCommands' => array(
          SqlPlotParam::TITLE => 'Status Commands',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfStatusCommands '  => 'Status commands'
              )
           )

         ),
         array('numOfDeleteCommands' => array(
           SqlPlotParam::TITLE => 'Delete Commands',
           SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
           'type' => 'sb',
           'cols' => array(
              'numOfDeleteCommands' => 'Delete Commands'
              )
            )
          ),
          array('numOfCollectionFailed' => array(
            SqlPlotParam::TITLE => 'Upload Failed',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(
                'numOfCollectionFailed'  => 'Upload Failed'
                )
          ),
          'numOfCollectionStarted' => array(
          SqlPlotParam::TITLE => 'Upload Started',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfCollectionStarted'  => 'Upload Started'
              )
            )
          ),
          array(   'numOfReadyForExported' => array(
            SqlPlotParam::TITLE => 'Ready For download',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(
                'numOfReadyForExported'  => 'Ready For download'
                )
          ),
          'numOfCollectionRescheduled' => array(
          SqlPlotParam::TITLE => 'Upload Rescheduled',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfCollectionRescheduled '  => 'No of collection rescheduled'
              )
            )
          ),
          array(   'greatestFileDimension' => array(
            SqlPlotParam::TITLE => 'Greatest File Size',
            SqlPlotParam::Y_LABEL => 'Bytes',
            'type' => 'sb',
            'cols' => array(
                'greatestFileDimension'  => 'Greatest file size'
                )
          ),
          'numOfRetentionTimerRun ' => array(
          SqlPlotParam::TITLE => 'Retention Time Run',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfRetentionTimerRun '  => 'No of retention time run'
              )
            )
          ),
          array(
            'availableDiskSpace' => array(
            SqlPlotParam::TITLE => AVAILABLE_DISK_SPACE,
            SqlPlotParam::Y_LABEL => 'MB',
            'type' => 'sb',
            'cols' => array(
                'availableDiskSpace'  => AVAILABLE_DISK_SPACE
                )
          ),
          'totalDiskSpace' => array(
          SqlPlotParam::TITLE => TOTAL_DISK_SPACE,
          SqlPlotParam::Y_LABEL => 'MB',
          'type' => 'sb',
          'cols' => array(
              'totalDiskSpace'  => TOTAL_DISK_SPACE
              )
            )
          ),

          array('numOfObjectInCache ' => array(
          SqlPlotParam::TITLE => 'Objects in Cache',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'type' => 'sb',
          'cols' => array(
              'numOfObjectInCache '  => 'No of objects in cache'
              )
           )

        )
    );
}

function msnetStatisticsGraphs($handler) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $where = "";
    $tables = "";
    if($handler == "netlogMediationHandler"){
        $where = "enm_msnetlog_instr.siteid = sites.id AND sites.name = '%s'  AND enm_msnetlog_instr.serverid = servers.id";
        $tables = "enm_msnetlog_instr, sites, servers";
    }
    elseif($handler == "sharedNetlogMediationHandler"){
        $where = "enm_shared_netlog_mediation_handler_instr.siteid = sites.id AND sites.name = '%s'  AND enm_shared_netlog_mediation_handler_instr.serverid = servers.id";
        $tables = "enm_shared_netlog_mediation_handler_instr, sites, servers";
    }
    $instrGraphParamsArray = array(
        array('numMedTaskRequestReceived' => array(
          SqlPlotParam::TITLE => 'Mediation Task Request received',
          'type' => 'sb',
          SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
          'cols' => array(
              'numMedTaskRequestReceived'  => 'Mediation Task Request received'
              )
          ),
          'executionTime' => array(
          SqlPlotParam::TITLE => 'Execution Time',
          'type' => 'sb',
           SqlPlotParam::Y_LABEL => 'sec',
          'cols' => array(
              'executionTime'  => 'Execution Time'
              )
           )
         )
    );
   foreach ( $instrGraphParamsArray as $instrGraphParams ) {
      $row = array();
      foreach ($instrGraphParams as $instrGraphParam) {
          $sqlParam = array(
              SqlPlotParam::TITLE => $instrGraphParam[SqlPlotParam::TITLE],
              SqlPlotParam::Y_LABEL => $instrGraphParam[SqlPlotParam::Y_LABEL],
              'useragg' => 'true',
              'persistent' => 'true',
              'type' => 'sb',
              'sb.barwidth' => 60,
              'querylist' => array(
                  array (
                     'timecol' => 'time',
                     'whatcol' => $instrGraphParam['cols'],
                     'tables' => $tables,
                     'multiseries' => 'servers.hostname',
                     'where' => $where,
                     'qargs' => array( 'site' )
                  )
              )
          );
          $id = $sqlParamWriter->saveParams($sqlParam);
          $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
      }
      $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function getNetlogCountersSummary()
{
  global $date, $site, $vdbId;
  $cols = array(
               array( 'key' => 'hostname', 'db' => 'IFNULL(servers.hostname,"All Instances")',
                      'label' => 'FMSERV Instance'),
               array( 'key' => 'numOfObjectInCache', 'db' => 'msnet.numOfObjectInCache',
                      'label' => 'Number Of Objects In Cache' ),
               array( 'key' => 'availableDiskSpace', 'db' => 'msnet.availableDiskSpace',
                      'label' => AVAILABLE_DISK_SPACE ),
               array( 'key' => 'totalDiskSpace', 'db' => 'msnet.totalDiskSpace',
                      'label' => TOTAL_DISK_SPACE),
               array( 'key' => 'numOfRetentionTimeRun', 'db' => 'msnet.numOfRetentionTimerRun',
                      'label' => 'Number Of Retention Time Run' )
           );

  $where = "msnet.siteid = sites.id AND sites.name = '$site' AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

  $table = new SqlTable("Netlog_Counters_Summary",
                        $cols,
                        array( 'enm_fmservnetlog_instr msnet', 'sites', 'servers'),
                        $where,
                        TRUE
           );
   echo $table->getTableWithHeader("Netlog Counters Summary", 2, "", "", "Netlog_Counters_Summary");
}


function mainFlow($statsDB) {

    global $date, $site;

    echo "<ul>";
    echo "<li><a href=\"#Fmserv_Netlog_Statistics_anchor\">Fmserv Netlog Statistics</a></li>";
    echo "<li><a href=\"#Netlog_Counters_Summary_anchor\">Netlog Counters Summary</a></li>";
    echo "<li><a href=\"#Netlog_Mediation_Handler_anchor\">Netlog Mediation Handler</a></li>";
    echo "<li><a href=\"#Shared_Netlog_Mediation_Handler_anchor\">Shared Netlog Mediation Handler</a></li>";
    echo "</ul>";

    $row = $statsDB->queryRow("
SELECT
 count(*)
FROM
 enm_msnetlog_instr msnet, sites, servers
WHERE
 msnet.siteid = sites.id AND sites.name = '$site'
 AND msnet.serverid = servers.id AND msnet.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ( $row[0] == 0 ) {
        return NULL;
    }else{
      echo "<br></br>";
      getNetlogCommandTotals();
      echo "<br></br>";
      getNetlogUploadExecution();
      echo "<br></br>";
      getNetlogStatisticData();
      echo "<br></br>";
      plotFmservNetlogGraphs();
      echo "<br></br>";
      getNetlogCountersSummary();
      echo "<br></br>";
      getNetlogMediationHandler();
      msnetStatisticsGraphs("netlogMediationHandler");
    }
    echo "<br></br>";
    getSharedNetlogMediationHandler($statsDB);
    msnetStatisticsGraphs("sharedNetlogMediationHandler");

}

    $statsDB = new StatsDB();
    mainFlow($statsDB);
    include PHP_ROOT . "/common/finalise.php";

?>

