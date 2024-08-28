<?php
$pageTitle = "Generic Measurements";
if ( isset($_GET["qplot"]) ) {
    $UI = false;
 }
include "common/init.php";

require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();

function qPlot($statsDB,$site,$date,$mid)
{
  $statsDB->query("SELECT grp, name FROM gen_meas_names WHERE id = $mid");
  $row = $statsDB->getNextRow();

  $sqlParam =
    array( 'title'      => $row[0] . ": " . $row[1],
	   'ylabel'     => $row[1],
	   'useragg'    => 'true',
	   'persistent' => 'true',
	   'querylist' => 
	   array(
		 array(
		       'timecol' => 'time',
		       'whatcol'    => array( 'value'=> $row[1] ),
		       'tables'  => "gen_measurements, sites",
		       'where'   => "gen_measurements.siteid = sites.id AND sites.name = '%s' AND mid = %d",
		       'qargs'   => array( 'site', 'mid' )
		       )
		 )
	   );

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);

  header("Location:" .  
	 $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") .
	 "&mid=$mid");
}

if ( isset($_GET["qplot"]) ) {
  qPlot($statsDB,$site,$date,$_GET["qplot"]);
  exit;
 }

$qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&qplot=";

$statsDB->query("
SELECT gen_meas_names.grp, gen_meas_names.name, 
 gen_measurements.mid,
 MIN(gen_measurements.value), ROUND( AVG(gen_measurements.value) ), 
 MAX(gen_measurements.value)
FROM gen_measurements, gen_meas_names, sites
WHERE
 gen_measurements.siteid = sites.id AND sites.name = '$site' AND 
 gen_measurements.mid = gen_meas_names.id AND
 gen_measurements.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY mid
ORDER BY gen_meas_names.grp, gen_meas_names.name");

$tables = array();
$currGrp = "";
while($row = $statsDB->getNextRow()) {
  if ( $row[0] != $currGrp ) { 
    $currGrp = $row[0];
    $tables[$currGrp] = new HTML_Table("border=1");
    $tables[$currGrp]->addRow( array ("Name","Min", "Avg", "Max"), null, 'th' );
  }

  $link = "<a href=\"" . $qplotBase . $row[2] . "\">$row[1]</a>";
  $tables[$currGrp]->addRow( array( $link, $row[3], $row[4], $row[5] ) );
 }


echo "<ul>\n";
foreach ($tables as $grp => $table) {
  echo "<li><a href=\"#" . $grp . "\">" . $grp . "</a></li>\n";
}
echo "</ul>\n";

foreach ($tables as $grp => $table) {
  echo "<H1><a name=\"" . $grp . "\"></a>" . $grp . "</H1>\n";
  echo $table->toHTML();
}

$statsDB->disconnect();

include "common/finalise.php";
?>
