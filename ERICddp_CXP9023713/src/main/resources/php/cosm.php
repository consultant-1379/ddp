<?php
  $pageTitle = "COSM Statistics";

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
  # COSM MX
  if (dataAvail($statsDB, 'cosm_mx_stats', array('total_tasks_created', 'total_requests_dispatched', 'registered_callbacks', 'received_callbacks', 'processed_callbacks', 'failed_callbacks'), $site, 'COSM-MX', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("COSM MX", "No.", array('total_tasks_created' =>'Total Tasks Created (delta)', 'total_requests_dispatched' => 'Total Requests Dispatched (delta)', 'registered_callbacks' => 'Registered Callbacks (delta)', 'received_callbacks' => 'Received Callbacks (delta)', 'processed_callbacks' => 'Processed Callbacks (delta)', 'failed_callbacks' => 'Failed Callbacks (delta)'), 'COSM-MX', 'cosm_mx_stats', $fromDate, $toDate)) );
  }
  # COSM File Auditor
  if (dataAvail($statsDB, 'cosm_fileauditor_stats', array('total_files_deleted', 'total_files_processed', 'total_space_recovered_in_bytes'), $site, 'COSM-FileAuditor', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("COSM File Auditor", "No.", array('total_files_deleted' => 'Total Files Deleted (delta)', 'total_files_processed' => 'Total Files Processed (delta)', 'total_space_recovered_in_bytes' => 'Total Space Recovered (Bytes) (delta)'), 'COSM-FileAuditor', 'cosm_fileauditor_stats', $fromDate, $toDate)) );
  }
  # COSM OS
  if (dataAvail($statsDB, 'cosm_os_stats', array('open_file_descriptor_count'), $site, 'COSM-OS', $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("COSM OS", "No.", array('open_file_descriptor_count' => 'Open File Descriptor Count'), 'COSM-OS', 'cosm_os_stats', $fromDate, $toDate)) );
  }

  echo $graphTable->toHTML();

include "common/finalise.php";
?>
