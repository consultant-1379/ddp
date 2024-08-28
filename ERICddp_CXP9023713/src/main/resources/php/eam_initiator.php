<?php
$pageTitle = "EAM Initiator Statistics";
include "common/init.php";
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();
$rootdir = $rootdir;

echo "<h1>EAM Initiator Statistics</h1>\n";

function overView($site,$date,$statsDB) {
  echo "<H2>Daily Totals</H2>\n";

  $container = new HTML_Table('border=0');
  
  $numApps = $statsDB->queryRow("
SELECT DISTINCT(eam_cmd_time.appid) 
FROM eam_cmd_time,sites 
WHERE
 eam_cmd_time.siteid = sites.id AND sites.name = '$site' AND
 eam_cmd_time.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
");

  $columns = array( 'Initiator' );
  if ( $numApps[0] > 1 ) {
    $columns[] = 'Application';
  }


  $header = array();  
  foreach ( $columns as $colName ) {
    $header[] = "<H2>$colName</H2>\n";
  }
  $container->addRow($header);

  /* Daily totals */ 
  $tableRow = array();
  $tableMap = array( 'Initiator' => 'eam_initiator_names', 'Application' => 'eam_trimmed_app_names' );
  $colMap = array( 'Initiator' => 'initiatorid', 'Application' => 'appid' );
  foreach ( $columns as $colName ) {
    $initTable = new HTML_Table('border=1');
    $initTable->addRow( array( $colName, 'Sessions', 'Commands' ), null, 'th' );
    $table = $tableMap[$colName];
    $col = $colMap[$colName];
    $statsDB->query("
SELECT $table.name, SUM(eam_cmd_time.session), SUM(eam_cmd_time.cmd) 
FROM eam_cmd_time, $table, sites
WHERE
 eam_cmd_time.$col = $table.id AND
 eam_cmd_time.siteid = sites.id AND sites.name = '$site' AND
 eam_cmd_time.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eam_cmd_time.$col
ORDER BY $table.name");

  $initURL = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&init=";
  while ($row = $statsDB->getNextRow()) {
    if ( $colName == 'Initiator' ) {
      $row[0] = "<a href=\"" . $initURL . $row[0] . "\">". $row[0] . "</a>\n";
    }
    $initTable->addRow($row);
  }
  $tableRow[] = $initTable->toHTML();
  }
  $container->addRow($tableRow);


  /* Half hour graphs */
  $graphRow = array();
  foreach ( $columns as $colName ) {
    $table = $tableMap[$colName];
    $col = $colMap[$colName];

    $graphCell = "<H2>Half Hours Stats by $colName</H2>\n";
  
    $graphParam = array( "Sessions" => "session",
			 "Commands" => "cmd",
			 "NEs"      => "ne" );
    foreach ( $graphParam as $title => $column ) {
      $graphCell .=  "<H3>$tile</H3>\n";
      $sqlParam = 
	array( 'title'      => $title,
	       'ylabel'     => 'Count',
	       'useragg'    => 'false',
	       'persistent' => 'false',
	       'type'       => 'sb',
	       'sb.barwidth'=> 1800,
	       'querylist' => 
	       array(
		     array (
			    'timecol' => 'time',
			    'multiseries'=> "$table.name",
			    'whatcol' => array ( $column => $title ),
			    'tables'  => "eam_cmd_time, $table, sites",
			    'where'   => "eam_cmd_time.$col = $table.id AND eam_cmd_time.siteid = sites.id AND sites.name = '%s'",
			    'qargs'   => array( 'site' )
			    )
		     )
	       );
      $sqlParamWriter = new SqlPlotParam();
      $id = $sqlParamWriter->saveParams($sqlParam);
      $graphCell .= "<p>" . $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true ) . "</p>\n";
    }
    $graphRow[] = $graphCell;
  }
  $container->addRow($graphRow);
  echo $container->toHTML();
}

function initNeTotals($site,$date,$statsDB,$initName) {
  echo "<H2>NE Command totals for $initName</H2>\n";
  $table = new HTML_Table('border=1');
  $table->addRow(array("NE", "Total Commands"), null, 'th' );
  $statsDB->query("
SELECT eam_ne_names.name, SUM(eam_cmd_ne.cmdcount) AS cmdcount
FROM eam_cmd_ne, eam_ne_names, sites, eam_initiator_names
WHERE
 eam_cmd_ne.neid = eam_ne_names.id AND
 eam_cmd_ne.siteid = sites.id AND sites.name = '$site' AND
 eam_cmd_ne.date = '$date' AND
 eam_cmd_ne.initiatorid = eam_initiator_names.id AND eam_initiator_names.name = '$initName'
GROUP BY eam_cmd_ne.neid
ORDER BY cmdcount DESC");
  $neURL = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&ne=";
  while ($row = $statsDB->getNextRow()) {
    $row[0] = "<a href=\"" . $neURL . $row[0] . "\">". $row[0] . "</a>\n";
    $table->addRow($row);
  }
  echo $table->toHTML();
  
}

function neCmdTotals($site, $date, $statsDB, $ne, $initName) {
  echo "<H2>Command totals for $ne</H2>\n";
  $table = new HTML_Table('border=1');
  $table->addRow(array("Command", "Total"), null, 'th' );
  $statsDB->query("
SELECT eam_trimmed_cmd_names.name, eam_cmd_ne.cmdcount
FROM eam_cmd_ne, eam_ne_names, sites, eam_trimmed_cmd_names, eam_initiator_names
WHERE
 eam_cmd_ne.initiatorid = eam_initiator_names.id AND
 eam_initiator_names.name = '$initName' AND
 eam_cmd_ne.neid = eam_ne_names.id AND eam_ne_names.name = '$ne' AND
 eam_cmd_ne.siteid = sites.id AND sites.name = '$site' AND
 eam_cmd_ne.date = '$date' AND
 eam_cmd_ne.cmdid = eam_trimmed_cmd_names.id
 ORDER BY cmdcount DESC");
  while ($row = $statsDB->getNextRow()) {
    $table->addRow($row);
  }
  echo $table->toHTML();
}

if ( isset($_REQUEST['ne']) ) {
  neCmdTotals($site, $date, $statsDB, requestValue('ne'), requestValue('init'));
} else if ( isset($_REQUEST['init']) ) {
  initNeTotals($site,$date,$statsDB,$_REQUEST['init']);
} else {
  overView($site,$date,$statsDB);
}

$statsDB->disconnect();
include "common/finalise.php";
?>

