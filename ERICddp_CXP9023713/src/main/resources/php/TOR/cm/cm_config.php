<?php
$pageTitle = "CM Config Copy";


include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
const WHERE = 'where';

function configTable(){
  global $date, $site;

  $where = "
  siteid = sites.id
  AND sites.name = '$site'
  AND startTime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
  AND batchstatus = 'COMPLETED'";
  $table =
    new SqlTable("core_table",
             array(
               array('key' => 'jobId', 'db' => 'jobId', 'label' => 'Job Id'),
               array('key' => 'status', 'db' => 'IFNULL(batchstatus, "NA")', 'label' => 'Status'),
               array('key' => 'startdate', 'db' => 'IFNULL(startTime, "NA")', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
               array('key' => 'enddate', 'db' => 'IFNULL(endTime, "NA")', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
               array('key' => 'totalelapsedtime', 'db' => 'IFNULL(TIMEDIFF(endTime, startTime), "NA")', 'label' => 'Total Elapsed Time', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
               array('key' => 'expectednodescopied', 'db' => 'IFNULL(expectedNodesCopied, "NA")', 'label' => 'Expected Nodes Copied', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
               array('key' => 'nodescopied', 'db' => 'IFNULL(nodesCopied, "NA")', 'label' => 'Nodes Copied', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
               array('key' => 'nodesnotcopied', 'db' => 'IFNULL(nodesNotCopied, "NA")', 'label' => 'Nodes Not Copied', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
               array('key' => 'nodesnomatchfound', 'db' => 'IFNULL(nodesNoMatchFound, "NA")', 'label' => 'Nodes No Match Found', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
               array('key' => 'sourceconfig', 'db' => 'IFNULL(sourceConfig, "NA")', 'label' => 'Source Config'),
               array('key' => 'targetconfig', 'db' => 'IFNULL(targetConfig, "NA")', 'label' => 'Target Config')
            ),
            array( 'enm_cmconfig_logs', 'sites' ),
            $where,
            TRUE,
            array(
              'order' => array( 'by' => 'startdate', 'dir' => 'ASC'),
              'rowsPerPage' => 25,
              'rowsPerPageOptions' => array(50, 100, 1000, 10000)
            )
          );
 drawHeaderWithHelp("CM Config Copy", 2, "cmConfigHelp", "DDP_Bubble_220_ENM_CM_Config_InstrumentationHelp");
 echo $table->getTable();
}

function getGraphParams() {
    global $date, $site;

    $graphParams = array(
        'sets' => array(
            array(
                'timecol' => 'startTime',
                'whatcol' => array('TIME_TO_SEC(TIMEDIFF(endTime, startTime))' => '1-50'),
                'tables' => "enm_cmconfig_logs, sites",
                WHERE => "enm_cmconfig_logs.siteid = sites.id AND sites.name = '%s' AND
                           batchstatus = 'COMPLETED' AND expectedNodesCopied <= '50'",
                'qargs' => array( 'site' )
            ),
            array(
                'timecol' => 'startTime',
                'whatcol' => array('TIME_TO_SEC(TIMEDIFF(endTime, startTime))' => '51-100'),
                'tables' => "enm_cmconfig_logs, sites",
                WHERE => "enm_cmconfig_logs.siteid = sites.id AND sites.name = '%s' AND
                           batchstatus = 'COMPLETED' AND expectedNodesCopied  <= '100' AND expectedNodesCopied  > '50'",
                'qargs' => array( 'site' )
            ),
            array(
                'timecol' => 'startTime',
                'whatcol' => array('TIME_TO_SEC(TIMEDIFF(endTime, startTime))' => '101-500'),
                'tables' => "enm_cmconfig_logs, sites",
                WHERE => "enm_cmconfig_logs.siteid = sites.id AND sites.name = '%s' AND
                           batchstatus = 'COMPLETED' AND expectedNodesCopied  <= '500' AND
                           expectedNodesCopied  > '100'",
                'qargs' => array( 'site' )
            ),
            array(
                'timecol' => 'startTime',
                'whatcol' => array('TIME_TO_SEC(TIMEDIFF(endTime, startTime))' => '501-1000'),
                'tables' => "enm_cmconfig_logs, sites",
                WHERE => "enm_cmconfig_logs.siteid = sites.id AND sites.name = '%s' AND
                           batchstatus = 'COMPLETED' AND expectedNodesCopied  <= '1000' AND
                           expectedNodesCopied  > '500'",
                'qargs' => array( 'site' )
            ),
            array(
                'timecol' => 'startTime',
                'whatcol' => array('TIME_TO_SEC(TIMEDIFF(endTime, startTime))' => '1001+'),
                'tables' => "enm_cmconfig_logs, sites",
                WHERE => "enm_cmconfig_logs.siteid = sites.id AND sites.name = '%s' AND
                           batchstatus = 'COMPLETED' AND expectedNodesCopied  > '1000'",
                'qargs' => array( 'site' )
            )
        )
    );
    return $graphParams;
}

function showGraph($graphParams) {
    global $date, $sites;
    drawHeaderWithHelp("CM Config Copy Elapsed Time", 2, "cmConfigElapsedTimeHelp", "");
    $sqlParam = array(
        'title' => "expectedNodesCopied",
        'type' => 'xy',
        'ylabel' => "Total Time Elapsed (Seconds)",
        'useragg' => 'true',
        'persistent' => 'true',
        'querylist' => $graphParams
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo "<p>" . $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800,400) . "</p>\n";
}

function mainFlow() {
    global $debug, $date, $site;

    /* CM Config table */
    $graphParams = getGraphParams();
    configTable();
    showGraph($graphParams['sets']);
}
mainFlow();
include PHP_ROOT . "/common/finalise.php";
?>

