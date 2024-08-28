<?php

require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";
$pageTitle = "SMRS";
include "common/init.php";

function getGetTarTable($statsDB,$starttime,$endtime,$site,&$types) {
  global $debug;
  $table = new HTML_Table("border=1");

  $statsDB->query("
SELECT smrs_master_gettar.type AS type,
       smrs_slave.hostname AS slave,
       ROUND(AVG(duration), 0) AS duration, ROUND(AVG(copytime),0) AS copytime,
       ROUND(AVG(extracttime),0) AS extracttime
FROM smrs_master_gettar, smrs_slave, sites
WHERE
 smrs_master_gettar.slaveid = smrs_slave.id AND
 smrs_master_gettar.siteid = sites.id AND sites.name = '$site' AND
 time BETWEEN '$starttime' AND '$endtime'
GROUP BY smrs_master_gettar.type, smrs_master_gettar.slaveid");

  if ( $statsDB->getNumRows() > 0 ) {
    $table->addRow( array ("Type", "Slave", "Average Duration",
			   "Average Copy Time", "Average Extracttime"),
		    null, 'th' );

    while($row = $statsDB->getNextRow()) {
      $table->addRow($row);
      $types[$row[0]] = 1;
    }

  }

  if ( $debug ) { echo "<pre>\n"; print_r($types); echo "</pre>\n"; }

  return $table;
}

function getCreateTarTable($statsDB,$starttime,$endtime,$slaveId) {
  $table = new HTML_Table("border=1");

  $statsDB->query("
SELECT smrs_slave_createtar.type AS type,
  ROUND(AVG(duration),0) AS duration, ROUND(AVG(findtime),0) AS findtime,
  ROUND(AVG(tartime),0) AS tartime,
  SUM(numfiles), ROUND( (SUM(tarsizekb)/1024), 0)
FROM smrs_slave_createtar
WHERE
 smrs_slave_createtar.slaveid = $slaveId AND
 time BETWEEN '$starttime' AND '$endtime'
GROUP BY smrs_slave_createtar.type");

  if ( $statsDB->getNumRows() > 0 ) {
    $table->addRow( array ("Type","Average Duration", "Average Find Time", "Average Tar Time",
			   "Total Number Of Files", "Total Tar File Size(MB)"), null, 'th' );

    while($row = $statsDB->getNextRow()) {
      $table->addRow($row);
    }
  }

  return $table;
}

function getServers($rootdir,$statsDB,$site,$type) {
  global $debug;

  $results = array();

  if ( $type == 'slave' ) {
    $pattern = '/_NEDSS$/';
  } else {
    $pattern = '/_NESS$/';
  }

  $serverId = array();
  $sql = "SELECT smrs_slave.hostname, smrs_slave.id FROM smrs_slave,sites
 WHERE smrs_slave.siteid = sites.id AND sites.name ='$site'";
  $statsDB->query($sql);
  while ( $row = $statsDB->getNextRow() ) {
    $slaveId[$row[0]] = $row[1];
  }

  $appServDir=$rootdir . "/remotehosts";
  if ( is_dir($appServDir) && ($dh = opendir($appServDir)) ) {
      while (($file = readdir($dh)) != false) {
	$entry = $appServDir . "/" . $file;

	if ( is_dir($entry) && preg_match($pattern, $file) ) {
	  $hostname = preg_replace( $pattern, '', $file);
	  if ( $type == 'slave' ) {
	    if ( array_key_exists( $hostname, $slaveId ) ) {
	      $results[$hostname] = $slaveId[$hostname];
	    }
	  } else {
	      $results[$hostname] = '';
	  }
	}
      }
      closedir($dh);
  }

  if ( $debug ) { echo "<pre>getServers: results\n"; print_r($results); echo "</pre>\n"; }
  return $results;
}

function printServProcLinks( $hostname, $type ) {
  global $webargs;

  $servpage = "<a href=\"" . PHP_WEBROOT . "/server.php?$webargs&serverdir=remotehosts/" .
    $hostname . "_" . $type . "/server\">Server</a>";
  $procPage = "<a href=\"" . PHP_WEBROOT . "/topn.php?$webargs&server=$hostname&procdir=remotehosts/" .
    $hostname . "_" . $type . "/process\">Process</a>";

  echo "<ul>\n";
  echo " <li>$servpage</li>\n";
  echo " <li>$procPage</li>\n";
  echo "</ul>\n";
}

//
// Main
//
if ( isset($_GET['start']) ) {
  $from = $_GET['start'] . " 00:00:00";
  $to = $_GET['end'] . " 23:59:59";
} else {
  $from = "$date 00:00:00";
  $to = "$date 23:59:59";
}


$statsDB = new StatsDB();

$masters = getServers($rootdir,$statsDB,$site,"master");
foreach ( $masters as $hostname => $servid ) {
  echo "<H1>Master - $hostname</H1>\n";
  $types = array();
  $masterTable = getGetTarTable($statsDB,$from,$to,$site,$types);
  if ( $masterTable->getRowCount() > 0 ) {
    //printServProcLinks( $hostname, "NEDSS" );
    echo $masterTable->toHTML();
  }

  $masterGraphs = new HTML_Table('border=1');

  if ( $debug ) { echo "<pre>\n"; print_r(array_keys($types)); echo "</pre>\n"; }

  $masterGraphs->addRow( array_keys($types), null, 'th' );
  $colIndex = 0;
  foreach ( $types as $type => $dummy ) {
    $links[$type] = array();

    $extraArgs = "&type=$type";

    $sqlParam =
      array( 'title'      => 'Duration' ,
	     'ylabel'     => 'Seconds',
	     'type'       => 'tb',
	     'useragg'    => 'true',
	     'persistent' => 'false',
	     'querylist' =>
	     array(
		   array (
			  'timecol' => 'time',
			  'whatcol' => array ( 'duration' => 'Duration', 'smrs_master_gettar.duration' => 'barwidth' ),
			  'tables'  => "smrs_master_gettar, sites",
			  'where'   => "sites.name = '%s' AND smrs_master_gettar.siteid = sites.id AND smrs_master_gettar.type = '%s'",
			  'qargs'   => array( 'site', 'type' )
			  )
		     )
	       );
      $sqlParamWriter = new SqlPlotParam();
      $id = $sqlParamWriter->saveParams($sqlParam);

      $masterGraphs->setCellContents( 1, $colIndex, $sqlParamWriter->getImgURL( $id, $from, $to, true, 320, 200, $extraArgs ) );

      $colIndex++;
  }

  echo $masterGraphs->toHTML();
}


$slaves = getServers($rootdir,$statsDB,$site,"slave");
foreach ( $slaves as $hostname => $slaveId ) {
  $table = getCreateTarTable($statsDB,$from,$to,$slaveId);
  if ( $table->getRowCount() > 0 ) {
    echo "<H1>Slave - $hostname</H2>\n";
    //printServProcLinks( $hostname, "NEDSS" );
    echo $table->toHTML();
    echo "<br>\n";

    $graphs = new HTML_Table('border=1');
    $colTitles = array();
    for ( $i = 1; $i < $table->getRowCount(); $i++ ) {
      $type = $table->getCellContents($i,0);
      $colTitles[] = $type;
    }
    $graphs->addRow( $colTitles, null, 'th' );
    $graphs->setAutoGrow(true);

    for ( $i = 1; $i < $table->getRowCount(); $i++ ) {
      $type = $table->getCellContents($i,0);
      if ( $debug ) { echo "<pre>"; print_r($type); echo "</pre>\n"; }
      $extraArgs = "slaveid=$slaveId&type=$type";

      $sqlParam =
	array( 'title'      => 'Duration' ,
	       'ylabel'     => 'Seconds',
	       'type'       => 'sb',
	       'sb.barwidth'=> '1',
	       'useragg'    => 'true',
	       'persistent' => 'false',
	       'querylist' =>
	       array(
		     array (
			    'timecol' => 'time',
			    'whatcol' => array ( 'duration' => 'Duration' ),
			    'tables'  => "smrs_slave_createtar",
			    'where'   => "slaveid = %d AND type = '%s'",
			    'qargs'   => array( 'slaveid', 'type' )
			    )
		     )
	       );
      $sqlParamWriter = new SqlPlotParam();
      $id = $sqlParamWriter->saveParams($sqlParam);
      $graphs->setCellContents( 1, $i - 1, $sqlParamWriter->getImgURL( $id, $from, $to, true, 320, 200, $extraArgs ) );

      $sqlParam =
	array( 'title'      => 'File Count' ,
	       'ylabel'     => 'Files',
	       'type'       => 'tb',
	       'useragg'    => 'true',
	       'persistent' => 'false',
	       'querylist' =>
	       array(
		     array (
			    'timecol' => 'time',
			    'whatcol' => array ( 'numfiles' => 'Files', 'duration' => 'barwidth' ),
			    'tables'  => "smrs_slave_createtar",
			    'where'   => "slaveid = %d AND type = '%s'",
			    'qargs'   => array( 'slaveid', 'type' )
			    )
		     )
	       );
      $id = $sqlParamWriter->saveParams($sqlParam);
      $graphs->setCellContents( 2, $i - 1, $sqlParamWriter->getImgURL( $id, $from, $to, true, 320, 200, $extraArgs ) );

      $sqlParam =
	array( 'title'      => 'Data Size' ,
	       'ylabel'     => 'KB',
	       'type'       => 'tb',
	       'useragg'    => 'true',
	       'persistent' => 'false',
	       'querylist' =>
	       array(
		     array (
			    'timecol' => 'time',
			    'whatcol' => array ( 'tarsizekb' => 'KB', 'duration' => 'barwidth'  ),
			    'tables'  => "smrs_slave_createtar",
			    'where'   => "slaveid = %d AND type = '%s'",
			    'qargs'   => array( 'slaveid', 'type' )
			    )
		     )
	       );
      $id = $sqlParamWriter->saveParams($sqlParam);
      $graphs->setCellContents( 3, $i - 1, $sqlParamWriter->getImgURL( $id, $from, $to, true, 320, 200, $extraArgs ) );
    }
      echo $graphs->toHTML();
  }
}


?>
