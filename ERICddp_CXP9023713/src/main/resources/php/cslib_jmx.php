<?php
  $pageTitle = "CSLib JMX Statistics";

include "common/init.php";
require_once "SqlPlotParam.php";
require_once "StatsDB.php";
require_once 'HTML/Table.php';
$cs = $_GET['cs'];

echo "<h1>$cs</h1>";
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
  # CSLib ConfigHome
  if (dataAvail($statsDB, 'cslib_confighome_stats', array('ConfigurationManagerCount', 'PersistenceManagerCount', 'OngoingCsTransactionCount'), $site, "$cs-ConfigHome", $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("CSLib ConfigHome", "Count", array('ConfigurationManagerCount' =>'Configuration Manager', 'PersistenceManagerCount' => 'Persistence Manager', 'OngoingCsTransactionCount' => 'Ongoing Cs Transaction'), "$cs-ConfigHome", 'cslib_confighome_stats', $fromDate, $toDate)) );
  }
  # CSLib VDB PM
  if (dataAvail($statsDB, 'cslib_vdb_stats', array('PmClosedDuringTx', 'PmCreated', 'PmIdleInPool', 'PmOpen'), $site, "$cs-VDB", $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("CSLib Versant DB PM", "Count", array('PmClosedDuringTx/1000' => 'Pm Closed During Tx (x1000)', 'PmCreated/1000' => 'PM Created', 'PmIdleInPool' => 'PM Idle In Pool', 'PmOpen' => 'PM Open'), "$cs-VDB", 'cslib_vdb_stats', $fromDate, $toDate)) );
  }
  # CSLib VDB Tx
  if (dataAvail($statsDB, 'cslib_vdb_stats', array('TxCommitted', 'TxRolledBack', 'TxStarted'), $site, "$cs-VDB", $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("CSLib Versant DB Tx", "Count", array('TxCommitted' => 'Tx Committed', 'TxRolledBack' => 'Tx Rolled Back', 'TxStarted' => 'Tx Started'), "$cs-VDB", 'cslib_vdb_stats', $fromDate, $toDate)) );
  }

  # CSLib VDB Tx
  if (dataAvail($statsDB, 'cslib_vdb_stats', array('TotalOpenedConnections'), $site, "$cs-VDB", $fromDate, $toDate ) ) {
      $graphTable->addRow( array(getQPlot("CSLib Versant Open Connections", "Connections", array('TotalOpenedConnections' => 'Connections'), "$cs-VDB", 'cslib_vdb_stats', $fromDate, $toDate)) );
  }

  echo $graphTable->toHTML();

include "common/finalise.php";
?>
