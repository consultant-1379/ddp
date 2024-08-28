<?php
  $pageTitle = "RTTFI Statistics";

include "common/init.php";
require_once "SqlPlotParam.php";
require_once "StatsDB.php";
require_once 'HTML/Table.php';

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

function getQPlot($title,$ylabel,$whatcol,$table,$name,$fromDate,$toDate)
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
			'timecol' => 'starttime',
			'whatcol' => $whatcol,
			'tables'  => "$table, sites",
#'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.starttime BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:50' AND $table.op = '$name'",
			'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.op = '$name'",
			'qargs'   => array( 'site' )
			)
		 )
	   );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  if ( $debug ) { echo "<pre>getQPlot id=$id</pre>\n"; }
  $url =  $sqlParamWriter->getImgURL( $id, 
				     "$fromDate 00:00:00", "$toDate 23:59:59", 
				     true, 640, 240 );	      	      
  if ( $debug ) { echo "<pre>getQPlot url=$url</pre>\n"; }
  return $url;
}

function dataAvail($statsDB,$table,$columns,$site,$fromDate,$toDate)
{
   $sql = "SELECT COUNT(*) FROM $table, sites
          WHERE $table.siteid = sites.id AND sites.name = '$site' AND
          $table.starttime BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";

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

  # RTTFI Stats - Duration of RTTFI Operations[startRTTFI, startRttfiWithExport] is collected & displayed
  if (dataAvail($statsDB, 'rttfi_ops', array('duration'), $site, $fromDate, $toDate ) ) {
    echo "<H2>RTTFI Operations</H2>\n";
    $graphTable->addRow( array(getQPlot("startRTTFI Metrics", "Duration - Seconds", array('duration' => 'Duration'), 'rttfi_ops', 'startRTTFI', $fromDate, $toDate)) );
    $graphTable->addRow( array(getQPlot("startRttfiWithExport Metrics", "Duration - Seconds", array('duration' => 'Duration'), 'rttfi_ops', 'startRttfiWithExport', $fromDate, $toDate)) );
  }

  echo $graphTable->toHTML();

include "common/finalise.php";
?>
