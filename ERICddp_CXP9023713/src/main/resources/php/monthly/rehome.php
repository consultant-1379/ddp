<?php
$pageTitle = "WRAN RBS Rehomes";
if ( isset($_GET["chart"]) ) {
  $UI = false;
 }
include "../common/init.php";

require_once 'HTML/Table.php';

function getRehomeGraph($statsDB,$site,$startdate,$enddate) {
  $dataset =& Image_Graph::factory('dataset'); 
  $statsDB->query("
SELECT UNIX_TIMESTAMP(wran_rbs_reparent.date), SUM(wran_rbs_reparent.count)
 FROM wran_rbs_reparent, sites 
WHERE wran_rbs_reparent.siteid = sites.id AND sites.name = '$site' AND
      wran_rbs_reparent.date BETWEEN '$startdate' AND '$enddate'
GROUP BY wran_rbs_reparent.date");
  while($row = $statsDB->getNextRow() ) {
    $dataset->addPoint($row[0],$row[1]);
  }

  $graph =& Image_Graph::factory('graph', array(640, 240)); 
  $plotarea =& $graph->addNew('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis') ); 

  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($startdate));
  $xAxis->forceMaximum(strtotime($enddate) + ( (24*60*60) - 1));
  $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('d'));
  $xAxis->setDataPreProcessor($dateFormatter);


  $plot =& $plotarea->addNew('bar', array(&$dataset));   
  $plot->setBarWidth(3, '%');

  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
  $fill->addColor('red@0.2');
  $plot->setFillStyle($fill);

  $graph->done();
}

function getRehomeTable($statsDB,$site,$startdate,$enddate) {
  global $debug;

  $counts = array();

  $fromIds = array();
  $toIds = array();
  $allIds = array();

  $statsDB->query("
SELECT wran_rbs_reparent.src_rns, wran_rbs_reparent.dest_rns, wran_rbs_reparent.count
 FROM wran_rbs_reparent, sites 
WHERE wran_rbs_reparent.siteid = sites.id AND sites.name = '$site' AND
      wran_rbs_reparent.date BETWEEN '$startdate' AND '$enddate'");
  while ( $row = $statsDB->getNextRow() ) {
    $fromIds[$row[0]]++;
    $toIds[$row[1]]++;
    $allIds[$row[0]]++;
    $allIds[$row[1]]++;

    $counts[$row[0]][$row[1]] += $row[2];
  }

  $fromNames = array();
  $toNames = array();
  $statsDB->query("SELECT name,id FROM rns WHERE id IN (" . implode("," , array_keys($allIds)) . ") ORDER BY name");
  while ( $row = $statsDB->getNextRow() ) {
    if ( array_key_exists( $row[1], $fromIds ) ) {
      $fromNames[$row[0]] = $row[1];
    }
    if ( array_key_exists( $row[1], $toIds ) ) {
      $toNames[$row[0]] = $row[1];
    }
  }
  if ( $debug > 0 ) { 
    echo "<pre>";
    print_r($fromNames);
    print_r($toNames);
    echo "</pre>";
  }

  $table = new HTML_Table('border=1');
  $table->addRow( array_merge( array('From / To'), array_keys($toNames)) );

  foreach ( $fromNames as $fromName => $fromId ) { 
    $tableRow = array();
    $tableRow[] = $fromName;
    foreach ( $toNames as $toName => $toId ) {
	$tableRow[] = $counts[$fromId][$toId];
    }
    $table->addRow($tableRow);
  }

  return $table;
}

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

if ( isset($_GET["chart"]) ) {
  getRehomeGraph($statsDB,$site,$startdate,$enddate);
  exit;
 }

echo "<H2>Reparent Counts By RNS</H2>\n";
$table = getRehomeTable($statsDB,$site,$startdate,$enddate);
echo $table->toHTML();


echo "<H2>Reparent Total Counts By Date</H2>\n";
echo '<img src="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&chart=rehome\"></a>\n";

include "../common/finalise.php";
?>
