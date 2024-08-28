<?php
$pageTitle = "PA Import Performance";

if ( isset($_GET['trend']) ) {
  $UI = false;
}

include "common/init.php";
require_once 'HTML/Table.php';

function getSingleTypeImports( $statsDB, $site, $startdate, $enddate ) {
  global $debug;

  $statsDB->query("
SELECT pa_import_details.importid, mo_names.name,
       pa_import_details.created,
       pa_import_details.deleted,
       pa_import_details.updated
 FROM pa_import, sites, pa_import_details, mo_names
 WHERE
  pa_import.siteid = sites.id AND sites.name = '$site' AND
  pa_import.start BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND
  pa_import.id = pa_import_details.importid AND
  pa_import_details.moid = mo_names.id
 ORDER BY mo_names.name");
  $importStats = array();
  while ( $row = $statsDB->getNextRow() ) {
    $importStats[$row[0]][$row[1]] = array( $row[2], $row[3], $row[4] );
  }

  $importIds = array();
  foreach ( $importStats as $importId => $moStats ) {
    if ( $debug > 2 ) {
      echo "<pre>id=$importId count=" . count($moStats) . "\n";
      print_r($moStats);
      echo "</pre>\n";
    }

    if ( count($moStats) == 1 ) {
      foreach ( $moStats as $moType => $counts ) {
	if ( $debug > 1 ) {
	  echo "<pre>Checking id=$importId moType=$moType\n";
	  print_r($moStats);
	  echo "</pre>\n";
	}


	if ( $counts[0] > 0 && $counts[1] == 0 && $counts[2] == 0 ) {
	  $importIds[$moType]['created'][$importId] = 1;
	} else if ( $counts[0] == 0 && $counts[1] > 0 && $counts[2] == 0 ) {
	  $importIds[$moType]['deleted'][$importId] = 1;
	} else if ( $counts[0] == 0 && $counts[1] == 0 && $counts[2] > 0 ) {
	  $importIds[$moType]['updated'][$importId] = 1;
	}
      }
    }
  }

  if ( $debug > 0 ) {
    echo "<pre>importIds\n";
    print_r($importIds);
    echo "</pre>\n";
  }

  return $importIds;
}

function chartTrend( $statsDB, $site, $startdate, $enddate, $moType, $op ) {
  global $debug;


  $dataSet =& Image_Graph::factory('dataset');

  $colNames = array( 'created', 'deleted', 'updated' );
  $colName = $colNames[$op];

  $importIds = getSingleTypeImports( $statsDB, $site, $startdate, $enddate );
  if ( $debug > 0 ) {
    echo "<pre>chartTrend moType=$moType importIds\n";
    print_r($importIds);
    echo "</pre>\n";
  }

  $idStr = implode( ",", array_keys($importIds[$moType][$colName]) );
  $statsDB->query("
SELECT UNIX_TIMESTAMP( DATE_FORMAT(pa_import.start,'%Y-%m-%d') ) AS thedate,
       AVG (pa_import_details.$colName / TIME_TO_SEC(TIMEDIFF(pa_import.end,pa_import.start)))
 FROM pa_import, pa_import_details
 WHERE
  pa_import_details.importid IN ( $idStr ) AND
  pa_import_details.importid = pa_import.id
 GROUP BY thedate
 ORDER BY thedate
");
  while ( $row = $statsDB->getNextRow() ) {
    $dataSet->addPoint( $row[0], $row[1] );
  }

  $graph =& Image_Graph::factory('graph', array(640, 240));
  $graph->addNew( 'title', array( "Daily Average MO/sec for $moType $colName") );
  $plotarea =& $graph->addNew('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis') );

  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($startdate));
  $xAxis->forceMaximum(strtotime($enddate));
  $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('jS M'));
  $xAxis->setDataPreProcessor($dateFormatter);


  //$plot =& $plotarea->addNew('Image_Graph_Plot_Step', array(&$dataSet));
  $plot =& $plotarea->addNew('bar', array(&$dataSet));
  $numDays = (strtotime($enddate) - strtotime($startdate)) / (24 * 60 * 60);
  $plot->setBarWidth( 100 / $numDays, '%');

  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
  $fill->addColor('red@0.2');
  $plot->setFillStyle($fill);

  $graph->done();
}

function getImportPerf( $statsDB, $site, $startdate, $enddate )
{
  global $debug;

  $statsDB->query("
SELECT pa_import_details.importid, mo_names.name,
       pa_import_details.created,
       pa_import_details.deleted,
       pa_import_details.updated
 FROM pa_import, sites, pa_import_details, mo_names
 WHERE
  pa_import.siteid = sites.id AND sites.name = '$site' AND
  pa_import.start BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND
  pa_import.id = pa_import_details.importid AND
  pa_import_details.moid = mo_names.id
 ORDER BY mo_names.name");
  $importStats = array();
  while ( $row = $statsDB->getNextRow() ) {
    $importStats[$row[0]][$row[1]] = array( $row[2], $row[3], $row[4] );
  }

  $importIds = array();
  foreach ( $importStats as $importId => $moStats ) {
    if ( $debug > 2 ) {
      echo "<pre>id=$importId count=" . count($moStats) . "\n";
      print_r($moStats);
      echo "</pre>\n";
    }

    if ( count($moStats) == 1 ) {
      foreach ( $moStats as $moType => $counts ) {
	if ( $debug > 1 ) {
	  echo "<pre>Checking id=$importId moType=$moType\n";
	  print_r($moStats);
	  echo "</pre>\n";
	}


	if ( $counts[0] > 0 && $counts[1] == 0 && $counts[2] == 0 ) {
	  $importIds[$moType]['created'][$importId] = 1;
	} else if ( $counts[0] == 0 && $counts[1] > 0 && $counts[2] == 0 ) {
	  $importIds[$moType]['deleted'][$importId] = 1;
	} else if ( $counts[0] == 0 && $counts[1] == 0 && $counts[2] > 0 ) {
	  $importIds[$moType]['updated'][$importId] = 1;
	}
      }
    }
  }

  if ( $debug > 0 ) {
    echo "<pre>importIds\n";
    print_r($importIds);
    echo "</pre>\n";
  }

  $table = new HTML_Table('border=1');
  $table->addRow( array('MO Type',
			'Created AVG MO/sec', 'Created MAX MO/sec', 'Created Total',
			'Deleted AVG MO/sec', 'Deleted MAX MO/sec', 'Deleted Total',
			'Updated AVG MO/sec', 'Updated MAX MO/sec', 'Updated Total'),
		  null, 'th' );

  foreach ( $importIds as $moType => $importIdsByOpType ) {
    $tableRow = array();
    $tableRow[] = $moType;

    if ( $debug > 0 ) {
      echo "<pre>moType=$moType importIdsByOpType\n";
      print_r($importIdsByOpType);
      echo "</pre>\n";
    }

    foreach ( array( 'created', 'deleted', 'updated' ) as $op ) {
      if ( $debug > 0 ) { echo "<pre>op=$op</pre>\n"; }
      if ( array_key_exists( $op, $importIdsByOpType ) ) {
	$idStr = implode( ",", array_keys($importIdsByOpType[$op]) );
	if ( $debug > 0 ) { echo "<pre>idStr=$idStr</pre>\n"; }

	$row = $statsDB->queryRow("
SELECT
 ROUND(AVG( pa_import_details.$op / TIME_TO_SEC(TIMEDIFF(pa_import.end,pa_import.start)) ),
       2) AS avgrate,
 ROUND(MAX( pa_import_details.$op / TIME_TO_SEC(TIMEDIFF(pa_import.end,pa_import.start)) ),
       2) AS maxrate,
 SUM( pa_import_details.$op ) AS totalnum
 FROM pa_import, pa_import_details
 WHERE
  pa_import_details.importid IN ( $idStr ) AND
  pa_import_details.importid = pa_import.id
");
	$tableRow = array_merge( $tableRow, $row );
      } else {
	$tableRow = array_merge( $tableRow, array('', '', '') );
      }
    }
    $table->addRow($tableRow);
  }

  return $table;
}

$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}

if ( isset($_GET['trend']) ) {
    chartTrend( $statsDB, $site, $fromDate, $toDate, $_GET['trend'], $_GET['op'] );
    exit;
  }

$table = getImportPerf( $statsDB, $site, $fromDate, $toDate );
if ( $fromDate != $toDate ) {
  $linkBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

  $numRows = $table->getRowCount();
  for ( $rowIndex = 1; $rowIndex < $numRows; $rowIndex++ ) {
    $moType = $table->getCellContents( $rowIndex, 0 );
    for ( $opIndex = 0; $opIndex < 3; $opIndex++ ) {
      $colIndex = 1 + ($opIndex * 3);
      $value = $table->getCellContents( $rowIndex, $colIndex );
      if ( strlen($value) > 0 ) {
	$newValue = "<a href=\"" . $linkBase . "&trend=" . $moType .
	  "&op=" . $opIndex . "\" target=\"_blank\">$value</a>";
	$table->setCellContents( $rowIndex, $colIndex, $newValue );
      }
    }
  }
}

?>

<p>This table shows the import performance of each operation (Create/Delete/Update) per MO type. Only imports which contain a single operation type on a single MO type are included.</p>

<?php
echo $table->toHTML();
include "common/finalise.php";
?>
