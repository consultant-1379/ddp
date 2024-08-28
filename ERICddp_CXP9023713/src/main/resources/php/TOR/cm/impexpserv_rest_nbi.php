<?php

$pageTitle = "BULK CM REST NBI";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

class CMRestNbiDailyTotalsTable extends DDPObject {
    public $actionList;

    var $cols = array(
                  array('key' => 'action', 'label' => 'Action'),
                  array('key' => 'count', 'label' => 'Count', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'maxrestduration', 'label' => 'Max Time (RestDuration)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'avgrestduration', 'label' => 'Avg Time (RestDuration)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minrestduration', 'label' => 'Min Time (RestDuration)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'maxserviceduration', 'label' => 'Max Time (ServiceDuration)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'avgserviceduration', 'label' => 'Avg Time (ServiceDuration)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minserviceduration', 'label' => 'Min Time (ServiceDuration)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'maxrestdelay', 'label' => 'Max Time (RestDelay)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'avgrestdelay', 'label' => 'Avg Time (RestDelay)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minrestdelay', 'label' => 'Min Time (RestDelay)', 'formatter' => 'ddpFormatNumber')
    );

    public function __construct($actionList) {
        parent::__construct("cmRestNBIDailyTotals");
        $this->actionList = $actionList;
    }
    function getData() {
        global $php_webroot, $webargs, $date, $site;
        $sql = "";
        $actionList = $this->actionList;
        foreach($actionList as $action) {
            if(!empty($sql)){
                $sql .="\nUNION ";
            }
            $sql .="SELECT
 '$action' AS 'action',
 SUM(ta.${action}InstrumentCount) AS 'count',
 MAX(ta.${action}InstrumentRestDuration) AS 'maxrestduration',
 ROUND(AVG(ta.${action}InstrumentRestDuration),0) AS 'avgrestduration',
 MIN(ta.${action}InstrumentRestDuration) AS 'minrestduration',
 MAX(ta.${action}InstrumentServiceDuration) AS 'maxserviceduration',
 ROUND(AVG(ta.${action}InstrumentServiceDuration),0) AS 'avgserviceduration',
 MIN(ta.${action}InstrumentServiceDuration) AS 'minserviceduration',
 MAX(ta.${action}InstrumentRestDelay) AS 'maxrestdelay',
 ROUND(AVG(ta.${action}InstrumentRestDelay),0) AS 'avgrestdelay',
 MIN(ta.${action}InstrumentRestDelay) AS 'minrestdelay'
FROM
 enm_impexpserv_instr as ta,
 sites,
 servers
WHERE
 ta.siteid = sites.id AND
 sites.name = '$site' AND
 ta.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 ta.serverid = servers.id
 GROUP BY 'action' WITH ROLLUP";
 }
     $this->populateData($sql);
     foreach ($this->data as &$row) {
         $row['action'] = "<a href='$php_webroot/TOR/cm/impexpserv_rest_nbi.php?$webargs" . "&id=" . $row['action'] . "'>" . $row['action'] . "</a>";
     }

     return $this->data;
  }
}

class CMRestNbiDailySummaryTable extends DDPObject {
    public $mbean;
    public $isDataFromOldTable = false;

    var $cols = array(
                  array('key' => 'InstanceName', 'label' => 'InstanceName'),
                  array('key' => 'MaxCount', 'label' => 'Max Count', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MinCount', 'label' => 'Min Count', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MaxRestDuration', 'label' => 'Max RestDuration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MinRestDuration', 'label' => 'Min RestDuration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MaxServiceDuration', 'label' => 'Max ServiceDuration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MinServiceDuration', 'label' => 'Min ServiceDuration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MaxRestDelay', 'label' => 'Max RestDelay', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MinRestDelay', 'label' => 'Min RestDelay', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MaxRestDelayPercentage', 'label' => 'Max RestDelayPercentage', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'MinRestDelayPercentage', 'label' => 'Min RestDelayPercentage', 'formatter' => 'ddpFormatNumber')
    );

    var $title = "Summary Table for mBean";

    public function __construct($mBean) {
        parent::__construct("cmRestNBISummary");
        $this->mBean = $mBean;
    }

    function getData() {
        global $date, $site;

        $mbean = $this->mBean . "Instrument";
        $sql =
"SELECT
IFNULL(servers.hostname,'All Instances') AS 'InstanceName',
 MAX(ta.${mbean}Count) AS 'MaxCount',
 MIN(ta.${mbean}Count) AS 'MinCount',
 MAX(ta.${mbean}RestDuration) AS 'MaxRestDuration',
 MIN(ta.${mbean}RestDuration) AS 'MinRestDuration',
 MAX(ta.${mbean}ServiceDuration) AS 'MaxServiceDuration',
 MIN(ta.${mbean}ServiceDuration) AS 'MinServiceDuration',
 MAX(ta.${mbean}RestDelay) AS 'MaxRestDelay',
 MIN(ta.${mbean}RestDelay) AS 'MinRestDelay',
 MAX(ta.${mbean}RestDelayPercentage) AS 'MaxRestDelayPercentage',
 MIN(ta.${mbean}RestDelayPercentage) AS 'MinRestDelayPercentage'
FROM
 enm_impexpserv_instr as ta,
 sites,
 servers
WHERE
 ta.siteid = sites.id AND
 sites.name = '$site' AND
 ta.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 ta.serverid = servers.id
 GROUP BY servers.hostname WITH ROLLUP";
        $this->populateData($sql);

        // [DEPRACATED:TORF-142714] - Below 'if' statement to be removed. Also remove all references to $isDataFromOldTable
        // If there are no results check if there are any results in the old tables
        if (empty($this->data)) {
            $this->isDataFromOldTable = true;
            $table = "enm_impexpserv_". $this->mBean ."Instrument_instr";
            $sql =
"SELECT
IFNULL(servers.hostname,'All Instances') AS 'InstanceName',
 MAX(ta.count) AS 'MaxCount',
 MIN(ta.count) AS 'MinCount',
 MAX(ta.restDuration) AS 'MaxRestDuration',
 MIN(ta.restDuration) AS 'MinRestDuration',
 MAX(ta.serviceDuration) AS 'MaxServiceDuration',
 MIN(ta.serviceDuration) AS 'MinServiceDuration',
 MAX(ta.restDelay) AS 'MaxRestDelay',
 MIN(ta.restDelay) AS 'MinRestDelay',
 MAX(ta.restDelayPercentage) AS 'MaxRestDelayPercentage',
 MIN(ta.restDelayPercentage) AS 'MinRestDelayPercentage'
FROM
 $table as ta,
 sites,
 servers
WHERE
 ta.siteid = sites.id AND
 sites.name = '$site' AND
 ta.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 ta.serverid = servers.id
 GROUP BY servers.hostname WITH ROLLUP";
         $this->populateData($sql);
     }
     return $this->data;
  }
}

function showInstrGraphs($mBean, $isDataFromOldTable) {

    global $date, $site;

    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();

    $table = "enm_impexpserv_instr";
    $serviceDuration = "${mBean}InstrumentServiceDuration";
    $restDelay = "${mBean}InstrumentRestDelay";

    if ($isDataFromOldTable) {
        $table="enm_impexpserv_".$mBean."Instrument_instr";
        $serviceDuration = "ServiceDuration";
        $restDelay = "RestDelay";
    }

    $instances = getInstances($table);

    $row = array();
    foreach ( $instances as $instance ) {
        $sqlParam = array(
            'title' => '%s:'.$mBean,
            'type' => 'sb',
            'targs' => array('inst'),
            'sb.barwidth' => '60',
            'ylabel' => 'Time(in MilliSecs)',
            'useragg'    => 'true',
            'persistent' => 'true',
            'forcelegend' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => array("$serviceDuration"=>"ServiceDuration","$restDelay"=>"RestDelay"),
                    'tables'  => "$table,sites,servers",
                    'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND
                                 $table.serverid= servers.id AND
                                 servers.hostname = '%s'",
                    'qargs'   => array( 'site', 'inst')
                )
            )
        );
        $extraArgs = "inst=$instance";
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, $extraArgs);
    }
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function mainFlow() {
  global $webargs;

  $actionList=array('ExportStart','ExportStatus','ExportStatusList','ExportReport','ExportFilters','ExportDownload','ImportDoUpload','ImportJobDetails','ImportAllJobDetails','ImportAllOperation');

  drawHeaderWithHelp("Daily Totals", 1, "dailyTotalsHelp", "DDP_Bubble_219_ENM_impexpserv_CM_REST_NBI_API_Daily_Summary");
  $dailyTotalsTable = new CMRestNbiDailyTotalsTable($actionList);
  echo $dailyTotalsTable->getClientSortableTableStr()."<br/>";

  /* Daily Summary table */
    if( isset($_GET['id']) ) {
       $mbean=$_GET['id'];
       drawHeaderWithHelp("Daily Summary Table for $mbean", 2, "dailySummaryHelp", "DDP_Bubble_192_ENM_impexpserv_CM_REST_NBI_API_Daily_Summary");
       $DailyTotalsTable = new CMRestNbiDailySummaryTable("$mbean");
       echo $DailyTotalsTable->getClientSortableTableStr();

  /* Instrumentation Stacked Graphs */
  drawHeaderWithHelp("Instrumentation Graphs for $mbean", 2, "instrumentationHelp","DDP_Bubble_193_ENM_impexpserv_CM_REST_NBI_API_Instr_Help");
  showInstrGraphs($mbean, $DailyTotalsTable->isDataFromOldTable);
  }
}

mainFlow();
include PHP_ROOT . "/common/finalise.php";

?>
