<?php
$type = $_GET['type'];

$pageTitle = "$type Workflow";
include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class DailyTotals extends DDPObject {
  var $cols = array(
                    'workflow' => 'Instance', 
                    'totaltime' => 'Duration',
                    'totalfiles' => 'Files',
                    'totalevents' => 'Events',
                    'totalmb' => 'MB',
                    'avgdelay' => 'Average Delay',
                    );

  var $title;
  var $type;

  function __construct($type) {
    $this->type = $type;
    $title = "$type Totals";
    if ( $type == "EFA" ) {
      $cols['totalcfa'] = "CFA Events";
      $cols['totalhfa'] = "HFA Events";
    }
    parent::__construct($this->type . "_DailyTotals");
  }

  function getData() {
    global $date;
    global $site;
        
    if ( $this->type == "EFA" ) { 
      $table = "eniq_wf_efa";
      $efaExtraCol = ", FORMAT(SUM(eniq_wf_efa.cfa),0) AS totalcfa, FORMAT(SUM(eniq_wf_efa.hfa),0) AS totalhfa";
    } else {
      $table = "eniq_wf_sgeh";
      $efaExtraCol = "";
    }

    $sql = "
SELECT 
 eniq_workflow_names.name AS workflow,
 SEC_TO_TIME( SUM( TIME_TO_SEC( TIMEDIFF( endtime, starttime ) ) ) ) AS totaltime,
 FORMAT(SUM($table.files),0) AS totalfiles,
 FORMAT(SUM($table.events),0) AS totalevents,
 FORMAT(SUM($table.bytes)/(1024*1024),0) AS totalmb,
 SEC_TO_TIME( AVG($table.delay) ) AS avgdelay $efaExtraCol
FROM
 $table, eniq_workflow_names, sites
WHERE
 $table.siteid = sites.id AND sites.name = '$site' AND
 $table.wfid = eniq_workflow_names.id AND
 $table.starttime BETWEEN '$date 00:00:00' AND '$date 23:59:59' 
GROUP BY $table.wfid
ORDER BY eniq_workflow_names.name
";
    $this->populateData($sql);
    return $this->data;
  }
}

$statsDB = new StatsDB();

echo "<H1>Hourly Totals</H1>\n";

if ( $type == "EFA" ) {
  $table = "eniq_wf_efa";
} else { 
  $table = "eniq_wf_sgeh";
}
if ($debug) { echo "<pre>table=$table</pre>\n"; }

$graphs = new HTML_Table("border=0");    
$sqlParamWriter = new SqlPlotParam();

$sqlParam = 
  array( 'title'      => 'Files Processed',
         'ylabel'     => 'Files',
         'type'        => 'sb',
         'sb.barwidth' => '3600',
         'presetagg'  => 'SUM:Hourly',
         'persistent' => 'true',
         'querylist' => 
         array(
               array (
                      'timecol' => 'starttime',
                      'whatcol' => array('files' => 'Files'),
                      'tables'  => "$table, sites",
                      'where'   => "$table.siteid = sites.id AND sites.name = '%s'",
                      'qargs'   => array( 'site' )
                      )
               )
         );      
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );

$sqlParam = 
  array( 'title'      => 'Events',
	 'ylabel'     => 'Events',
	 'type'        => 'sb',
	 'sb.barwidth' => '3600',
	 'presetagg'  => 'SUM:Hourly',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
	       array (
		      'timecol' => 'starttime',
		      'whatcol' => array('events' => 'Events' ),
		      'tables'  => "$table, sites",
		      'where'   => "$table.siteid = sites.id AND sites.name = '%s'",
		      'qargs'   => array( 'site' )
		      )
	       )
	 );    
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );

if ( $type == "EFA" ) {
  $sqlParam = 
    array( 'title'      => 'EFA Events',
           'ylabel'     => 'Events',
           'type'        => 'sb',
           'sb.barwidth' => '3600',
           'presetagg'  => 'SUM:Hourly',
           'persistent' => 'true',
           'querylist' => 
           array(
                 array (
                        'timecol' => 'starttime',
                        'whatcol' => array('cfa' => 'CFA', 'hfa' => 'HFA' ),
                        'tables'  => "eniq_wf_efa, sites",
                        'where'   => "eniq_wf_efa.siteid = sites.id AND sites.name = '%s'",
                        'qargs'   => array( 'site' )
                        )
                 )
           );    
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}

$sqlParam = 
  array( 'title'      => 'Processing Time',
         'ylabel'     => 'Seconds',
         'type'        => 'sb',
         'sb.barwidth' => '3600',
         'presetagg'  => 'SUM:Hourly',
         'persistent' => 'true',
         'querylist' => 
         array(
               array (
                      'timecol' => 'starttime',
                      'whatcol' => array('TIME_TO_SEC(TIMEDIFF(endtime,starttime))' => 'Duration'),
                      'tables'  => "$table, sites",
                      'where'   => "$table.siteid = sites.id AND sites.name = '%s'",
                      'qargs'   => array( 'site' )
                      )
               )
         );      
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );

$sqlParam =  
  array( 'title'      => 'Average Delay',
         'ylabel'     => 'Seconds',
         'type'        => 'sb',
         'sb.barwidth' => '3600',
         'presetagg'  => 'AVG:Hourly',
         'persistent' => 'true',
         'querylist' => 
         array(
               array (
                      'timecol' => 'starttime',
                      'whatcol' => array('delay' => 'Average Delay'),
                      'tables'  => "$table, sites",
                      'where'   => "$table.siteid = sites.id AND sites.name = '%s'",
                      'qargs'   => array( 'site' )
                      )
               )
         );      
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );

echo $graphs->toHTML();


echo "<H2>Workflow Instances</H2>\n";
$totalTable = new DailyTotals($type);
echo $totalTable->getHtmlTableStr();

include "../common/finalise.php";
?>
