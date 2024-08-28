<?php
$pageTitle = "Cell Management";

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

require_once 'HTML/Table.php';
const PERSISTENT = 'persistent';

class CellManagementLog extends DDPObject{
    var $cols = array(
        array( 'key' => 'inst', 'label' => 'APServ Instance'),
        array( 'key' => 'Usecase', 'label' => 'Usecase'),
        array( 'key' => 'relation', 'label' => 'Relation'),
        array( 'key' => 'success', 'label' => 'Number of successful usecases','formatter' => 'ddpFormatNumber'),
        array( 'key' => 'failed', 'label' => 'Number of failed usecases','formatter' => 'ddpFormatNumber'),
        array( 'key' => 'partial', 'label' => 'Number of partially successful usecases','formatter' => 'ddpFormatNumber'),
        array( 'key' => 'avgexecutiontime', 'label' => 'Average execution time (seconds)'),
        array( 'key' => 'maxexecutiontime', 'label' => 'Maximum execution time (seconds)'),
        array( 'key' => 'minexecutiontime', 'label' => 'Miminum execution time (seconds)')
    );
    function __construct() {
        parent::__construct("CellManagementLog");
    }
    function getData()  {
        global $date,$site;
        $sql = "
SELECT
IFNULL(servers.hostname,'') AS inst,
useCaseType AS Usecase,
relationType AS relation,
SUM( CASE  WHEN status LIKE 'Success' OR status LIKE 'No Update Required' THEN  1 ELSE 0  END) AS success,
SUM( CASE  WHEN status LIKE 'Error' THEN  1 ELSE 0  END) AS failed,
SUM( CASE  WHEN status LIKE 'Partial Success' THEN  1 ELSE 0  END) AS partial,
ROUND(AVG(executiontime)/1000,2) AS avgexecutiontime,
ROUND(MAX(executiontime)/1000,2) AS maxexecutiontime,
ROUND(MIN(executiontime)/1000,2) AS minexecutiontime
FROM enm_apserv_metrics,sites,servers
WHERE
    enm_apserv_metrics.view NOT LIKE 'Metric' AND
    enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
    enm_apserv_metrics.serverid=servers.id AND
    enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname,useCaseType,relationType
";
    $this->populateData($sql);
    return $this->data;
    }
}

function getApservGraphCreate($title, $instances) {
  global $date, $site, $statsDB;
  $str = explode("_",$title);
  $string = explode(",",$str[1]);
  $graphTable = new HTML_Table("border=0");
  $sqlParamWriter = new SqlPlotParam();
  foreach ( $instances as $instance ) {
        $flag=0;
        $row_data = $statsDB->queryRow("
                    SELECT COUNT(*)
                    FROM enm_apserv_metrics, sites,servers
                    WHERE
                    enm_apserv_metrics.siteid = sites.id AND
                    sites.name = '$site' AND
                    enm_apserv_metrics.serverid = servers.id AND
                    servers.hostname='$instance' AND
                    enm_apserv_metrics.useCaseType in ('CREATE_RELATION','CREATE_EXTERNAL_RELATION') AND
                    enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                    enm_apserv_metrics.view NOT LIKE 'Metric' AND
                    enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row_data[0] > 0) {
            $flag=1;
        }
        if($flag==1){
            $header[] = $instance;
        }
  }
  $graphTable->addRow($header, null, 'th');
  $row = array();
  foreach ( $instances as $instance ) {
    $flag=0;
    $row_data = $statsDB->queryRow("
                SELECT COUNT(*)
                FROM enm_apserv_metrics, sites,servers
                WHERE
                enm_apserv_metrics.siteid = sites.id AND
                sites.name = '$site' AND
                enm_apserv_metrics.serverid = servers.id AND
                servers.hostname='$instance' AND
                enm_apserv_metrics.useCaseType in ('CREATE_RELATION','CREATE_EXTERNAL_RELATION') AND
                enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                enm_apserv_metrics.view NOT LIKE 'Metric' AND
                enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
                if ($row_data[0] > 0) {
                    $flag=1;
                }
    if($flag==1){
        $sqlParam =
         array(
                'ylabel'     => "Execution Time(seconds)",
                'type' => 'tsc',
                'useragg' => 'true',
                PERSISTENT => false,
                'forcelegend' => 'true',
                'querylist'  =>
                 array(
                       array(
                             'timecol' => 'time',
                             'multiseries'=> "enm_apserv_metrics.usecaseType",
                             'whatcol' => array ('executionTime/1000' => 'executionTime'),
                             'tables' => "enm_apserv_metrics,sites,servers",
                             SqlPlotParam::WHERE => "enm_apserv_metrics.siteid = sites.id AND sites.name = '%s' AND
                                                    enm_apserv_metrics.serverid = servers.id AND
                                                    servers.hostname='$instance' AND
                                                    enm_apserv_metrics.useCaseType in
                                                        ('CREATE_RELATION','CREATE_EXTERNAL_RELATION') AND
                                                    enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                                                    enm_apserv_metrics.view NOT LIKE 'Metric'",
                             'qargs' => array( 'site' )
                            )
                      )
              );
  $id = $sqlParamWriter->saveParams($sqlParam);
  $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 300);
  }
  }
  $graphTable->addRow($row);
  echo $graphTable->toHTML();
}

function getApservGraphModify($title, $instances) {
  global $date, $site, $statsDB;
  $str = explode("_",$title);
  $string = explode(",",$str[1]);
  $graphTable = new HTML_Table("border=0");
  $sqlParamWriter = new SqlPlotParam();
  foreach ( $instances as $instance ) {
        $flag=0;
        $row_data = $statsDB->queryRow("
                    SELECT COUNT(*)
                    FROM enm_apserv_metrics, sites,servers
                    WHERE
                    enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND enm_apserv_metrics.serverid = servers.id AND servers.hostname='$instance' AND enm_apserv_metrics.useCaseType in ('MODIFY_CELL_FREQUENCY','MODIFY_EXTERNAL_CELL_FREQUENCY','MODIFY_CELL_PARAMETERS','MODIFY_EXTERNAL_CELL_PARAMETERS','MODIFY_FREQUENCY_GROUP','MODIFY_CELL_PARAMS_LOCK','MODIFY_CELL_PARAMS_UNLOCK','MODIFY_CELL_PARAMS_SOFTLOCK') AND enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND enm_apserv_metrics.view NOT LIKE 'Metric' AND enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row_data[0] > 0) {
            $flag=1;
        }
        if($flag==1){
            $header[] = $instance;
        }
  }
  $graphTable->addRow($header, null, 'th');
  $row = array();
  foreach ( $instances as $instance ) {
    $flag=0;
    $row_data = $statsDB->queryRow("
                SELECT COUNT(*)
                FROM enm_apserv_metrics, sites,servers
                WHERE
                enm_apserv_metrics.siteid = sites.id AND
                sites.name = '$site' AND
                enm_apserv_metrics.serverid = servers.id AND
                servers.hostname='$instance' AND
                enm_apserv_metrics.useCaseType in ('MODIFY_CELL_FREQUENCY','MODIFY_EXTERNAL_CELL_FREQUENCY','MODIFY_CELL_PARAMETERS','MODIFY_EXTERNAL_CELL_PARAMETERS','MODIFY_FREQUENCY_GROUP','MODIFY_CELL_PARAMS_LOCK','MODIFY_CELL_PARAMS_UNLOCK','MODIFY_CELL_PARAMS_SOFTLOCK') AND
                enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                enm_apserv_metrics.view NOT LIKE 'Metric' AND
                enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
                if ($row_data[0] > 0) {
                    $flag=1;
                }
    if($flag==1){
        $sqlParam =
         array( 'ylabel'     => "Execution Time(seconds)",
                'type' => 'tsc',
                'useragg' => 'true',
                PERSISTENT => false,
                'forcelegend' => 'true',
                'querylist'  =>
                 array(
                       array(
                             'timecol' => 'time',
                             'multiseries'=> "enm_apserv_metrics.usecaseType",
                             'whatcol' => array ('executionTime/1000' => 'executionTime' ),
                             'tables' => "enm_apserv_metrics,sites,servers",
                             SqlPlotParam::WHERE => "enm_apserv_metrics.siteid = sites.id AND sites.name = '%s' AND
                                         enm_apserv_metrics.serverid = servers.id AND servers.hostname='$instance' AND
                                         enm_apserv_metrics.useCaseType in
                                             ('MODIFY_CELL_FREQUENCY','MODIFY_EXTERNAL_CELL_FREQUENCY',
                                              'MODIFY_CELL_PARAMETERS','MODIFY_EXTERNAL_CELL_PARAMETERS',
                                              'MODIFY_FREQUENCY_GROUP','MODIFY_CELL_PARAMS_LOCK',
                                              'MODIFY_CELL_PARAMS_UNLOCK','MODIFY_CELL_PARAMS_SOFTLOCK') AND
                                         enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                                         enm_apserv_metrics.view NOT LIKE 'Metric'",
                             'qargs' => array( 'site' )
                            )

                      )
              );
  $id = $sqlParamWriter->saveParams($sqlParam);
  $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 300);
  }
  }
  $graphTable->addRow($row);
  echo $graphTable->toHTML();
}

function getApservGraphRead($title, $instances) {
  global $date, $site, $statsDB;
  $str = explode("_",$title);
  $string = explode(",",$str[1]);
  $graphTable = new HTML_Table("border=0");
  $sqlParamWriter = new SqlPlotParam();
  foreach ( $instances as $instance ) {
        $flag=0;
        $row_data = $statsDB->queryRow("
                    SELECT COUNT(*)
                    FROM enm_apserv_metrics, sites,servers
                    WHERE
                    enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND enm_apserv_metrics.serverid = servers.id AND servers.hostname='$instance' AND enm_apserv_metrics.useCaseType in ('READ_CELL_TOPOLOGY','READ_CELL_ATTRIBUTES') AND enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND enm_apserv_metrics.view NOT LIKE 'Metric' AND enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row_data[0] > 0) {
            $flag=1;
        }
        if($flag==1){
            $header[] = $instance;
        }
  }
  $graphTable->addRow($header, null, 'th');
  $row = array();
  foreach ( $instances as $instance ) {
    $flag=0;
    $row_data = $statsDB->queryRow("
                SELECT COUNT(*)
                FROM enm_apserv_metrics, sites,servers
                WHERE
                enm_apserv_metrics.siteid = sites.id AND
                sites.name = '$site' AND
                enm_apserv_metrics.serverid = servers.id AND
                servers.hostname='$instance' AND
                enm_apserv_metrics.useCaseType in ('READ_CELL_TOPOLOGY','READ_CELL_ATTRIBUTES') AND
                enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                enm_apserv_metrics.view NOT LIKE 'Metric' AND
                enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
                if ($row_data[0] > 0) {
                    $flag=1;
                }
    if($flag==1){
        $sqlParam =
         array( 'ylabel'     => "Execution Time(seconds)",
                'type' => 'tsc',
                'useragg' => 'true',
                PERSISTENT => false,
                'forcelegend' => 'true',
                'querylist'  =>
                 array(
                       array(
                             'timecol' => 'time',
                             'multiseries'=> "enm_apserv_metrics.usecaseType",
                             'whatcol' => array ('executionTime/1000' => 'executionTime' ),
                             'tables' => "enm_apserv_metrics,sites,servers",
                             SqlPlotParam::WHERE => "enm_apserv_metrics.siteid = sites.id AND sites.name = '%s' AND
                                                    enm_apserv_metrics.serverid = servers.id AND
                                                    servers.hostname='$instance' AND
                                                    enm_apserv_metrics.useCaseType in
                                                        ('READ_CELL_TOPOLOGY','READ_CELL_ATTRIBUTES') AND
                                                    enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                                                    enm_apserv_metrics.view NOT LIKE 'Metric'",
                             'qargs' => array( 'site' )
                            )

                      )
              );
  $id = $sqlParamWriter->saveParams($sqlParam);
  $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 300);
  }
  }
  $graphTable->addRow($row);
  echo $graphTable->toHTML();
}

function getApservGraphDelete($title, $instances) {
  global $date, $site, $statsDB;
  $str = explode("_",$title);
  $string = explode(",",$str[1]);
  $graphTable = new HTML_Table("border=0");
  $sqlParamWriter = new SqlPlotParam();
  foreach ( $instances as $instance ) {
   $flag=0;
        $row_data = $statsDB->queryRow("
                    SELECT COUNT(*)
                    FROM enm_apserv_metrics, sites,servers
                    WHERE
                    enm_apserv_metrics.siteid = sites.id AND
                    sites.name = '$site' AND
                    enm_apserv_metrics.serverid = servers.id AND
                    servers.hostname='$instance' AND
                    enm_apserv_metrics.useCaseType in ('DELETE_RELATION','DELETE_EXTERNAL_CELL') AND
                    enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                    enm_apserv_metrics.view NOT LIKE 'Metric' AND
                    enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row_data[0] > 0) {
            $flag=1;
        }
        if($flag==1){
            $header[] = $instance;
        }
  }
  $graphTable->addRow($header, null, 'th');
  $row = array();
  foreach ( $instances as $instance ) {
  $flag=0;
    $row_data = $statsDB->queryRow("
                SELECT COUNT(*)
                FROM enm_apserv_metrics, sites,servers
                WHERE
                enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND enm_apserv_metrics.serverid = servers.id AND servers.hostname='$instance' AND enm_apserv_metrics.useCaseType in ('DELETE_RELATION','DELETE_EXTERNAL_CELL') AND enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND enm_apserv_metrics.view NOT LIKE 'Metric' AND enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    if ($row_data[0] > 0) {
        $flag=1;
    }
    if($flag==1){
        $sqlParam =
         array( 'ylabel'     => "Execution Time(seconds)",
                'type' => 'tsc',
                'useragg' => 'true',
                PERSISTENT => false,
                'forcelegend' => 'true',
                'querylist'  =>
                 array(
                       array(
                             'timecol' => 'time',
                             'multiseries'=> "enm_apserv_metrics.usecaseType",
                             'whatcol' => array ('executionTime/1000' => 'executionTime' ),
                             'tables' => "enm_apserv_metrics,sites,servers",
                             SqlPlotParam::WHERE => "enm_apserv_metrics.siteid = sites.id AND sites.name = '%s' AND
                                                    enm_apserv_metrics.serverid = servers.id AND
                                                    servers.hostname='$instance' AND
                                                    enm_apserv_metrics.useCaseType in
                                                        ('DELETE_RELATION','DELETE_EXTERNAL_CELL') AND
                                                    enm_apserv_metrics.status IN ('$string[0]','$string[1]') AND
                                                    enm_apserv_metrics.view NOT LIKE 'Metric'",
                             'qargs' => array( 'site' )
                            )

                      )
              );
  $id = $sqlParamWriter->saveParams($sqlParam);
  $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 300);
  }
  }
  $graphTable->addRow($row);
  echo $graphTable->toHTML();
}


function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;

    $instances = getInstances('enm_apserv_metrics', 'time', "AND enm_apserv_metrics.view NOT LIKE 'Metric'");
    $cellmgmntHelp = "DDP_Bubble_284_APSERV_CELL_MANAGEMENT";
    drawHeaderWithHelp("Cell Management",1, "entityManagementDailyTotalsHelp",$cellmgmntHelp);
    $cellManagementTotals = new CellManagementLog();
    echo $cellManagementTotals->getClientSortableTableStr();
    echo "<br/>";

    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('CREATE_RELATION','CREATE_EXTERNAL_RELATION') AND enm_apserv_metrics.status IN ('success','No Update Required') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("CREATE_SUCCESS", 1, "createCellManagementhelpSuccess",
                       "DDP_Bubble_285_Create_Cell_Management");
            getApservGraphCreate("CREATE_SUCCESS,No Update Required", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('CREATE_RELATION','CREATE_EXTERNAL_RELATION') AND enm_apserv_metrics.status IN ('Error') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
        drawHeaderWithHelp("CREATE_FAILED", 1, "createCellManagementhelpFailed",
                           "DDP_Bubble_286_Create_Cell_Management");
            getApservGraphCreate("CREATE_ERROR", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('CREATE_RELATION','CREATE_EXTERNAL_RELATION') AND enm_apserv_metrics.status IN ('partial success') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
        drawHeaderWithHelp("CREATE_PARTIAL SUCCESS", 1, "createCellManagementhelp_ps",
                       "DDP_Bubble_287_Create_Cell_Management");
        getApservGraphCreate("CREATE_PARTIAL SUCCESS", $instances);
        }
    echo "<br/>";
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('MODIFY_CELL_FREQUENCY','MODIFY_EXTERNAL_CELL_FREQUENCY','MODIFY_CELL_PARAMETERS','MODIFY_EXTERNAL_CELL_PARAMETERS','MODIFY_FREQUENCY_GROUP','MODIFY_CELL_PARAMS_LOCK','MODIFY_CELL_PARAMS_UNLOCK','MODIFY_CELL_PARAMS_SOFTLOCK') AND enm_apserv_metrics.status IN ('success','No Update Required') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("MODIFY_SUCCESS", 1, "modifyCellManagementhelp_success",
                       "DDP_Bubble_288_Modify_Cell_Management");
            getApservGraphModify("MODIFY_SUCCESS,No Update Required", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('MODIFY_CELL_FREQUENCY','MODIFY_EXTERNAL_CELL_FREQUENCY','MODIFY_CELL_PARAMETERS','MODIFY_EXTERNAL_CELL_PARAMETERS','MODIFY_FREQUENCY_GROUP','MODIFY_CELL_PARAMS_LOCK','MODIFY_CELL_PARAMS_UNLOCK','MODIFY_CELL_PARAMS_SOFTLOCK') AND enm_apserv_metrics.status IN ('Error') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
        drawHeaderWithHelp("MODIFY_FAILED", 1, "modifyCellManagementhelp_failed",
                       "DDP_Bubble_289_Modify_Cell_Management");
            getApservGraphModify("MODIFY_ERROR", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('MODIFY_CELL_FREQUENCY','MODIFY_EXTERNAL_CELL_FREQUENCY','MODIFY_CELL_PARAMETERS','MODIFY_EXTERNAL_CELL_PARAMETERS','MODIFY_FREQUENCY_GROUP','MODIFY_CELL_PARAMS_LOCK','MODIFY_CELL_PARAMS_UNLOCK','MODIFY_CELL_PARAMS_SOFTLOCK') AND enm_apserv_metrics.status IN ('Partial success') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("MODIFY_PARTIAL SUCCESS", 1, "modifyCellManagementhelp_ps",
                       "DDP_Bubble_290_Modify_Cell_Management");
            getApservGraphModify("MODIFY_PARTIAL SUCCESS", $instances);
        }

    echo "<br/>";
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('DELETE_RELATION','DELETE_EXTERNAL_CELL') AND enm_apserv_metrics.status IN ('success','No Update Required') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("DELETE_SUCCESS", 1, "deleteCellManagementhelp_success",
                           "DDP_Bubble_291_Delete_Cell_Management");
            getApservGraphDelete("DELETE_SUCCESS,No Update Required", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('DELETE_RELATION','DELETE_EXTERNAL_CELL') AND enm_apserv_metrics.status IN ('Error') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("DELETE_FAILED", 1, "deleteCellManagementhelp_failed",
                               "DDP_Bubble_292_Delete_Cell_Management");
            getApservGraphDelete("DELETE_ERROR", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('DELETE_RELATION','DELETE_EXTERNAL_CELL') AND enm_apserv_metrics.status IN ('Partial success') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("DELETE_PARTIAL SUCCESS", 1, "deleteCellManagementhelp_ps",
                               "DDP_Bubble_293_Delete_Cell_Management");
            getApservGraphDelete("DELETE_PARTIAL SUCCESS", $instances);
        }

    echo "<br/>";

    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('READ_CELL_TOPOLOGY','READ_CELL_ATTRIBUTES') AND enm_apserv_metrics.status IN ('success','No Update Required') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
        drawHeaderWithHelp("READ_SUCCESS", 1, "readCellManagementhelp_success",
                       "DDP_Bubble_363_Read_Cell_Management");
        getApservGraphRead("READ_SUCCESS,No Update Required", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('READ_CELL_TOPOLOGY','READ_CELL_ATTRIBUTES') AND enm_apserv_metrics.status IN ('Error') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
        drawHeaderWithHelp("READ_FAILED", 1, "readCellManagementhelp_failed",
                       "DDP_Bubble_364_Read_Cell_Management");
            getApservGraphRead("READ_ERROR", $instances);
        }
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM enm_apserv_metrics, sites
        WHERE
        enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
        enm_apserv_metrics.useCaseType in ('READ_CELL_TOPOLOGY','READ_CELL_ATTRIBUTES') AND enm_apserv_metrics.status IN ('Partial success') AND
        enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ($row[0] > 0) {
            drawHeaderWithHelp("READ_PARTIAL SUCCESS", 1, "readCellManagementhelp_ps",
                       "DDP_Bubble_365_Read_Cell_Management");
            getApservGraphRead("READ_PARTIAL SUCCESS", $instances);
        }

    echo "<br/>";
}
$statsDB = new StatsDB();
mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>
