<?php
  $pageTitle = "Common Explorer Statistics";

include "common/init.php";
require_once "SqlPlotParam.php";
require_once "StatsDB.php";
require_once 'HTML/Table.php';

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

function getQPlot($title,$ylabel,$whatcol,$name,$table,$fromDate,$toDate)
{
  global $debug;

  $colNames = array_keys($whatcol);
  
  $sqlParam = 
    array( 'title'      => $title,
	   'ylabel'     => $ylabel,
	   'useragg'    => 'true',
	   'persistent' => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'time',
			'whatcol' => $whatcol,
			'tables'  => "$table, jmx_names, sites",
			'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.nameid = jmx_names.id AND jmx_names.name ='%s' AND $colNames[0] IS NOT NULL",
			'qargs'   => array( 'site', 'name' )
			)
		 )
	   );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  $url =  $sqlParamWriter->getImgURL( $id, 
				     "$fromDate 00:00:00", "$toDate 23:59:59", 
				     true, 640, 240, 
				     "name=$name" );	      	      
  if ( $debug ) { echo "<pre>getQPlot url=$url</pre>\n"; }
  return $url;
}

function dataAvail($statsDB,$table,$columns,$site,$name,$fromDate,$toDate)
{
   $sql = "SELECT COUNT(*) FROM jmx_names, $table, sites
          WHERE $table.siteid = sites.id AND sites.name = '$site' AND
          $table.nameid = jmx_names.id AND jmx_names.name = '$name' AND
          $table.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";

   if ($columns) {
       $sql .= " AND (";
       foreach($columns AS $column) {
           $sql .= " $table.$column IS NOT NULL OR";
       }
       $sql = preg_replace('/OR$/', ' )', $sql);
   }
 
   $row = $statsDB->queryRow("$sql");
   return $row[0] > 0;
}

  if ( isset($_GET['start']) ) { 
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
  } else {
    $fromDate = $date;
    $toDate = $date;
  }
  
  # Generate the plots, if there is data to plot
  $graphTable = new HTML_Table('border=0');
  # CEX Tasks
  if (dataAvail($statsDB, 'cex_tasks_stats', array('requested_tasks_length', 'running_tasks_length', 'finished_tasks_length'), $site, 'CEx-tasks', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("Common Explorer Tasks", "No. of requests", array('requested_tasks_length' =>'Requested Tasks', 'running_tasks_length' => 'Running Tasks', 'finished_tasks_length' => 'Finished Tasks'), 'CEx-tasks', 'cex_tasks_stats', $fromDate, $toDate)) );
  }
  # CEX PM
  if (dataAvail($statsDB, 'cex_nsd_pm_stats', array('cell_requests', 'cluster_requests', 'rbs_requests', 'rnc_requests'), $site, 'CEx-NSD-pm', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("Common Explorer NSD Performance Management", "No. of requests", array('cell_requests' => 'Cell Requests', 'cluster_requests' => 'Cluster Requests', 'rbs_requests' => 'RBS Requests', 'rnc_requests' => 'RNC Requests'), 'CEx-NSD-pm', 'cex_nsd_pm_stats', $fromDate, $toDate)) );
  }
  # CEX FM
  if (dataAvail($statsDB, 'cex_nsd_fm_stats', array('ack_alarms', 'alarm_list_rebuild', 'cleared_alarms', 'delete_alarms', 'new_alarms', 'other_alarms', 'total_alarms'), $site, 'CEx-NSD-fm', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("Common Explorer NSD Fault Management", "No. of alarms", array('ack_alarms' => 'Ack Alarms (delta)', 'alarm_list_rebuild' => 'Alarm List Rebuild', 'cleared_alarms' => 'Cleared Alarms (delta)', 'delete_alarms' => 'Delete Alarms (delta)', 'new_alarms' => 'NewAlarms (delta)', 'other_alarms' => 'Other Alarms (delta)', 'total_alarms' => 'Total Alarms (delta)'), 'CEx-NSD-fm', 'cex_nsd_fm_stats', $fromDate, $toDate)) );
  }
  # ActiveMQ cexbroker Queues Temporary Queues, Topics, Total Consumer Count, Total Message Count
  if (dataAvail($statsDB, 'activemq_cexbroker_stats', array('temporary_queues','topics','total_consumer_count','total_message_count'), $site, 'ActiveMQ-cexbroker', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("ActiveMQ Common Explorer Broker", "No. of Queues", array('temporary_queues' => 'Temporary Queues', 'topics' => 'Topics', 'total_consumer_count' => 'Total Consumer Count', 'total_message_count' => 'Total Message Count'), 'ActiveMQ-cexbroker', 'activemq_cexbroker_stats', $fromDate, $toDate)) );
  }
  # ActiveMQ cexbroker Queues Enque Count Delta
  if (dataAvail($statsDB, 'activemq_cexbroker_stats', array('total_enqueue_count'), $site, 'ActiveMQ-cexbroker', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("ActiveMQ Common Explorer Broker Total Enqueue Count", "Count (delta)", array('total_enqueue_count' => 'Total Enqueue Count (delta)'), 'ActiveMQ-cexbroker', 'activemq_cexbroker_stats', $fromDate, $toDate)) );
  }
  # ActiveMQ cexbroker Queues Deque Count Delta
  if (dataAvail($statsDB, 'activemq_cexbroker_stats', array('total_dequeue_count'), $site, 'ActiveMQ-cexbroker', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("ActiveMQ Common Explorer Broker Total Dequeue Count", "Count (delta)", array('total_dequeue_count' => 'Total Dequeue Count (delta)'), 'ActiveMQ-cexbroker', 'activemq_cexbroker_stats', $fromDate, $toDate)) );
  }

  echo $graphTable->toHTML();

include "common/finalise.php";
?>
