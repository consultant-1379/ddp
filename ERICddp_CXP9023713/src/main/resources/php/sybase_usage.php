<?php
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

$pageTitle = "Sybase Usage";
if ( isset($_GET['chart']) ) {
  $UI = false;
}
include "common/init.php";


function chartMda($statsDB,$site,$startdate,$enddate,$type)
{
  if ( $type == 'engutil' ) {
    $colStr = "( ( (sybase_mda.cpu_sys + sybase_mda.cpu_user) / (sybase_mda.cpu_sys + sybase_mda.cpu_user + sybase_mda.cpu_idle) ) * 100 )";
  } else {
    $colStr = "( (sybase_mda.cache_read / sybase_mda.cache_search) * 100 )";
  }
  $query = "
SELECT UNIX_TIMESTAMP( sybase_mda.time ) AS time, $colStr AS value
FROM sybase_mda, sites
 WHERE
  sybase_mda.siteid = sites.id AND sites.name = '$site' AND
  sybase_mda.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
 ORDER BY time
";

  $dataset =& Image_Graph::factory('dataset');

  $statsDB->query($query);
  while($row = $statsDB->getNextRow() ) {
    $dataset->addPoint($row[0],$row[1]);
  }

  $graph =& Image_Graph::factory('graph', array(640, 240));
  $plotarea =& $graph->addNew('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis') );
  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($startdate));
  $xAxis->forceMaximum(strtotime($enddate) + (24 * 60 * 60));
  if ( $startdate == $enddate ) {
    $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
  } else {
    $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('jS M'));
  }
  $xAxis->setDataPreProcessor($dateFormatter);

  $plot =& $plotarea->addNew('step', array(&$dataset));

  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
  $fill->addColor('red@0.2');
  $plot->setFillStyle($fill);

  $graph->done();

}

function chartUsage($statsDB,$site,$startdate,$enddate,$col)
{
  $query = "
SELECT UNIX_TIMESTAMP( sybase_usage_by_user.date ) AS time,
       sybase_users.name AS username, sybase_usage_by_user.$col AS value
FROM sybase_usage_by_user, sybase_users, sites
 WHERE
  sybase_usage_by_user.siteid = sites.id AND sites.name = '$site' AND
  sybase_usage_by_user.date BETWEEN '$startdate' AND '$enddate' AND
  sybase_usage_by_user.userid = sybase_users.id
 ORDER BY time
";
  chart($statsDB,$query,$startdate,$enddate);
}

function chartLogin($statsDB,$site,$startdate,$enddate)
{
  $query ="
SELECT UNIX_TIMESTAMP( sybase_logins.time ) AS time,
       sybase_users.name AS username, SUM(sybase_logins.num) AS numlogin
FROM sybase_logins, sybase_users, sites
 WHERE
  sybase_logins.siteid = sites.id AND sites.name = '$site' AND
  sybase_logins.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND
  sybase_logins.userid = sybase_users.id
 GROUP BY time, username
 ORDER BY time
";
  chart($statsDB,$query,$startdate,$enddate);
}

function chart($statsDB,$query,$startdate,$enddate)
{
  global $debug;

  $times = array();
  $data = array();

  $statsDB->query($query);

  while($row = $statsDB->getNextRow() ) {
    $times[$row[0]] = 1;
    $data[$row[1]][$row[0]] = $row[2];
  }
  $dataSets = array();
  foreach ( $data as $userid => $values ) {
    $dataSets[$userid] = Image_Graph::factory('dataset');
    $dataSets[$userid]->setName($userid);
    foreach ( $times as $time => $count) {
      if ( array_key_exists( $time, $data[$userid] ) ) {
	$dataSets[$userid]->addPoint( $time, $data[$userid][$time] );
      } else {
	// Fill in the blanks
	$dataSets[$userid]->addPoint( $time, 0 );
      }
    }
  }

  if ( $debug ) {
    echo "<pre>dataSets\n"; print_r($dataSets); echo "</pre>";
    return;
  }

  $graph =& Image_Graph::factory('graph', array(640, 600));

  $plotarea =& Image_Graph::factory('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis'));

  $legend =& Image_Graph::factory('legend');
  $legend->setPlotArea($plotarea);

  $graph->add( Image_Graph::vertical( $plotarea, $legend, 75 ) );

  // DateFormat X-Axis
  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($startdate));
  $xAxis->forceMaximum(strtotime($enddate) + (24 * 60 * 60));
  if ( $startdate == $enddate ) {
    $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
  } else {
    $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('jS M'));
  }
  $xAxis->setDataPreProcessor($dateFormatter);


  // Plot using stacked steps
  $plot =& $plotarea->addNew('Image_Graph_Plot_Step', array(array_values($dataSets), 'stacked'));

  // Setup fill colours
  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
  $colours = array( "aliceblue", "blanchedalmond", "burlywood", "coral", "darkgray", "darkmagenta", "darkred", "darkslategray", "deeppink", "dodgerblue", "goldenrod", "indigo", "lavenderblush", "lightcoral", "lightgreen", "lightseagreen", "lightsteelblue", "linen", "mediumblue", "mediumslateblue", "midnightblue", "navajowhite", "olivedrab", "palegoldenrod", "papayawhip", "plum", "rosybrown", "sandybrown", "slategrey", "tan", "turquoise", "whitesmoke" );
  foreach ( $colours as $colour ) {
    $fill->addColor($colour);
  }
  $plot->setFillStyle($fill);

  $graph->done();
}


function getUsageTable($statsDB,$site,$startdate,$enddate)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array("User", "CPU", "IO"), null, 'th' );

  $statsDB->query("
SELECT sybase_users.name, SUM(sybase_usage_by_user.cpu) AS cpu,
       SUM(sybase_usage_by_user.io) AS io
 FROM sybase_users, sybase_usage_by_user, sites
 WHERE sybase_usage_by_user.userid = sybase_users.id AND
       sybase_usage_by_user.siteid = sites.id AND sites.name = '$site' AND
       sybase_usage_by_user.date BETWEEN '$startdate' AND '$enddate'
GROUP BY sybase_users.name
HAVING (cpu > 0 OR io > 0)
 ORDER BY cpu DESC, io DESC");
  while($row = $statsDB->getNextRow()) {
    $table->addRow($row);
  }

  return $table;
}

function getMdaDeviceIoTable($statsDB,$site,$startdate,$enddate)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array("Device", "Reads", "APF Reads", "Writes", "Average I/O Time" ), null, 'th' );

  $statsDB->query("
SELECT sybase_mda_device_name.name, SUM(io.nreads), SUM(io.apfReads),
 SUM(io.nwrites), ROUND( AVG(io.iotime/(io.nreads + io.nwrites)), 1)
 FROM sybase_mda_device_name, sybase_mda_device_io AS io, sites
 WHERE io.devid = sybase_mda_device_name.id  AND
       io.siteid = sites.id AND sites.name = '$site' AND
       io.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
 GROUP BY io.devid
 HAVING SUM(io.nreads + io.nwrites) > 0
 ORDER BY SUM(io.nreads + io.nwrites) DESC");
  while($row = $statsDB->getNextRow()) {
    $table->addRow($row);
  }

  return $table;
}

if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}

$statsDB = new StatsDB();

if ( isset($_GET['chart']) ) {
  $chartType = $_GET['chart'];
  if ( $chartType == 'login' ) {
    chartLogin($statsDB,$site,$fromDate,$toDate);
  } else if ( $chartType == 'sybase_usage' ) {
    chartUsage($statsDB,$site,$fromDate,$toDate,$_GET['col']);
  } else if ( $chartType == 'mda' ) {
    chartMda($statsDB,$site,$fromDate,$toDate,$_GET['type']);
  }
  exit;
}

$linkBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

if ( file_exists( $rootdir . "/sybase" ) ) {
    $sybaseGraphDir = "sybase";
} else {
    $sybaseGraphDir = "server";
}

$sqlParamWriter = new SqlPlotParam();
$row = $statsDB->queryRow("
SELECT COUNT(*) FROM sybase_mda, sites WHERE
 sybase_mda.siteid = sites.id AND sites.name = '$site' AND
 sybase_mda.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
if ( $row[0] > 0 ) {
  echo "<H2>Sybase MDA stats</H2>";
  echo "<h3>Engine Utilization</h3>\n";
  $totalCpu = "(sybase_mda.cpu_sys + sybase_mda.cpu_user + sybase_mda.cpu_idle + sybase_mda.cpu_io)";
  $whatCols = array();
  foreach ( array("user","sys","io") as $col) {
    $whatCols[sprintf("IFNULL((sybase_mda.cpu_%s / $totalCpu)*100, 0)",$col)] = $col;
  }
  $sqlParam = array( 'title'      => 'Engine Utilization',
		     'ylabel'     => '%',
		     'useragg'    => 'true',
		     'persistent' => 'false',
                     'type'       => 'sb',
                     'sb.barwidth'=> '900',
		     'querylist' =>
		     array(
			   array(
				 'timecol' => 'time',
				 'whatcol'    => $whatCols,
				 'tables'  => "sybase_mda, sites",
				 'where'   => "sybase_mda.siteid = sites.id AND sites.name = '%s'",
				 'qargs'   => array( 'site' )
				 )
			    )
		     );
  $id = $sqlParamWriter->saveParams($sqlParam);
  echo $sqlParamWriter->getImgURL($id, "$fromDate 00:00:00", "$toDate 23:59:59",true,640,320);

  echo "<h3>Cache Miss Rate</h3>\n";
  $sqlParam = array( 'title'      => 'Cache Miss Rate',
		     'ylabel'     => '%',
		     'useragg'    => 'true',
		     'persistent' => 'false',
		     'querylist' =>
		     array(
			   array(
				 'timecol' => 'time',
				 'whatcol'    => array( '( (sybase_mda.cache_read / sybase_mda.cache_search) * 100 )' => 'Miss Rate'),
				 'tables'  => "sybase_mda, sites",
				 'where'   => "sybase_mda.siteid = sites.id AND sites.name = '%s'",
				 'qargs'   => array( 'site' )
				 )
			    )
		     );
  $id = $sqlParamWriter->saveParams($sqlParam);
  echo $sqlParamWriter->getImgURL($id, "$fromDate 00:00:00", "$toDate 23:59:59",true,640,240);

  $row = $statsDB->queryRow("
SELECT COUNT(*) FROM sybase_mda_device_io, sites WHERE
 sybase_mda_device_io.siteid = sites.id AND sites.name = '$site' AND
 sybase_mda_device_io.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
  if ( $row[0] > 0 ) {
    echo "<H3>Device IO</H2>\n";
    $sqlParam = array( 'title'      => 'Device IO',
		     'ylabel'     => 'Requests/s',
		     'useragg'    => 'false',
		     'persistent' => 'false',
                     'type'       => 'sb',
                     'sb.barwidth'=> '900',
		     'presetagg'  => 'SUM:Per Minute',
		     'querylist' =>
		     array(
			   array(
				 'timecol' => 'time',
				 'whatcol'    => array( 'nreads/900' => 'Reads', 'apfReads/900' => 'Prefetch Reads', 'nwrites/900' => 'Writes' ),
				 'tables'  => "sybase_mda_device_io, sites",
				 'where'   => "sybase_mda_device_io.siteid = sites.id AND sites.name = '%s'",
				 'qargs'   => array( 'site' )
				 )
			    )
		     );
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$fromDate 00:00:00", "$toDate 23:59:59",true,640,320);

    $table = getMdaDeviceIoTable($statsDB,$site,$fromDate,$toDate);
    echo $table->toHTML();
  }
} else {
  if ( $fromDate == $toDate ) {
    $util = $rootdir . "/$sybaseGraphDir/sybase_mda_eng_busy.jpg";
    if (file_exists($util)) {
      echo "<h2>Engine Utilization</h2>\n";
      echo "<img src=\"/$oss/$site/analysis/$dir/$sybaseGraphDir/sybase_mda_eng_busy.jpg\" alt=\"\">\n";
    }

    $cachePer = $rootdir . "/$sybaseGraphDir/sybase_mda_cache_miss.jpg";
    if (file_exists($cachePer)) {
      echo "<h2>Cache Miss %</h2>\n";
      echo "<img src=\"/$oss/$site/analysis/$dir/$sybaseGraphDir/sybase_mda_cache_miss.jpg\" alt=\"\">\n";
    }

    $cachePhyIO = $rootdir . "/$sybaseGraphDir/sybase_mda_cache_phyio.jpg";
    if (file_exists($cachePhyIO)) {
      echo "<h2>Cache Phyiscal IO</h2>\n";
      echo "<img src=\"/$oss/$site/analysis/$dir/$sybaseGraphDir/sybase_mda_cache_phyio.jpg\" alt=\"\">\n";
    }
  }
}

?>

<H1>Usage By User</H1>
<ul>
 <li><a  href="#cpu">CPU</a></li>
 <li><a  href="#io">IO</a></li>
 <li><a  href="#totals">Totals</a></li>
</ul>

<h2>CPU usage</h2>
<a name="cpu"></a>
<?php
if ( $fromDate == $toDate ) {
  echo '<img src="' . $webroot .'/' . $sybaseGraphDir . '/sybase_CPU.jpg">' . "\n";
} else {
  echo '<img src="' . $linkBase . '&chart=sybase_usage&col=cpu"/>' . "\n";
}

echo '<h2>IO usage</h2><a name="io"></a>' . "\n";
if ( $fromDate == $toDate ) {
  echo '<img src="' . $webroot .'/' . $sybaseGraphDir . '/sybase_CPU.jpg">' . "\n";
} else {
  echo '<img src="' . $linkBase . '&chart=sybase_usage&col=io"/>' . "\n";
}

echo "<h2>Totals</h2>\n";
$totalsTable = getUsageTable($statsDB,$site,$fromDate,$toDate);
echo $totalsTable->toHTML();

$row = $statsDB->queryRow("
SELECT COUNT(*) FROM sybase_logins, sites WHERE
 sybase_logins.siteid = sites.id AND sites.name = '$site' AND
 sybase_logins.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
if ( $row[0] > 0 ) {
  echo "<H1>Login Counts</H1>\n";
  echo '<img src="' . $linkBase . '&chart=login"/>';
}

include "common/finalise.php";
?>
