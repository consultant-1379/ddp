<?php

if ( isset($_GET["uas"]) ) {
    $pageTitle = "App. Server: " . $_GET["uas"];
} else {
  $pageTitle = "Application Servers";
}

if ( isset($_GET["qplot"]) || isset($_GET["chart"]) ) {
    $UI = false;
  }

include "common/init.php";

require_once "SqlPlotParam.php";
require_once 'HTML/Table.php';

function getAppIds($statsDB, $column) {
  global $debug;

  $appIds = array();
  $statsDB->query("
SELECT tmp.process_stats.procid, process_names.name,
  SUM(tmp.process_stats.$column) AS thetotal
 FROM tmp.process_stats, process_names
 WHERE
  tmp.process_stats.procid NOT IN
   (
    SELECT id FROM process_names
     WHERE name LIKE '%ctx%' OR
           name LIKE '/usr%' OR
           name LIKE '/lib%' OR
           name LIKE '/bin%' OR
           name LIKE 'dt%' OR
           name LIKE '%tcsh%' OR
           name LIKE '%bash%'  OR
           name = '-sh' OR
           name = '[AGGREGATED]' OR
           name = 'telnet' OR
           name = 'rlogin' OR
           name = 'ssh' OR
           name = '[ sdt_shell ]'
   ) AND
  tmp.process_stats.procid = process_names.id
 GROUP BY tmp.process_stats.procid
 ORDER BY thetotal DESC

");
  while ( $row = $statsDB->getNextRow() ) {
    if ( $debug > 0 ) { echo "<pre>chartApplications: $row[0] => $row[1]</pre>\n"; }
    $appIds[$row[1]] = $row[0];
  }

  return $appIds;
}

function getUasList($datadir,$statsDB,$site,$uas = NULL) {
  global $debug;

  $uasList = array();

  $serverId = array();
  $sql = "SELECT servers.hostname, servers.id FROM servers,sites
 WHERE
  servers.siteid = sites.id AND sites.name ='$site' AND
  servers.type = 'UAS'";
  if ( $uas != NULL ) {
    $uasStr = preg_replace("/:/","','",$uas);
    $sql .= " AND servers.hostname IN ('$uasStr')";
  }
  $statsDB->query($sql);
    while ( $row = $statsDB->getNextRow() ) {
    $serverId[$row[0]] = $row[1];
  }

  $appServDir=$datadir . "/remotehosts";
  if (is_dir($appServDir)) {
  if ( $debug ) { echo "<pre>getUasList: appServDir exists $appServDir</pre>\n"; }
    if ($dh = opendir($appServDir)) {
      while (($file = readdir($dh)) != false) {
	$entry = $appServDir . "/" . $file;
	if ( $debug ) { echo "<pre>getUasList: checking $entry</pre>\n"; }
	if ( is_dir($entry) && preg_match('/_UAS$/', $file) ) {
	  $hostname = preg_replace('/_UAS$/', '', $file);
	  if ( array_key_exists( $hostname, $serverId ) ) {
	    if ( $debug ) { echo "<pre>getUasList: adding hostname $hostname</pre>\n"; }
	    $uasList[$hostname] = $serverId[$hostname];
	  }
	}
      }
      closedir($dh);
    }
  }

  if ( $debug ) { echo "<pre>getUasList: uasList\n"; print_r($uasList); echo "</pre>\n"; }

  return $uasList;
}

function getUasTable($uasList) {
    global $webargs;
    $servTable = new HTML_Table('border=0');
    foreach ( $uasList as $hostname => $id ) {
        $appPage  = "<a href=\"" . $_SERVER['PHP_SELF'] . "?$webargs&uas=" . $hostname . "\">" . $hostname . "</a>";
       #$servpage = "<a href=\"" . PHP_WEBROOT . "/server.php?$webargs&serverdir=remotehosts/" . $hostname . "_UAS/server\">Server</a>";
        $servpage = "<a href=\"" . PHP_WEBROOT . "/server.php?$webargs&server=" . $hostname . "\">Server</a>";
        $procPage = "<a href=\"" . PHP_WEBROOT . "/topn.php?$webargs&server=$hostname&procdir=remotehosts/" . $hostname . "_UAS/process\">Process</a>";
        $servTable->addRow( array( $appPage, $servpage,$procPage ) );
    }
    return $servTable;
}

function qplotCtx($statsDB, $uasList) {
  global $date;

  $sqlParam =
    array(
        'title'  => 'Citrix Logins',
        'persistent' => 'true',
        'ylabel' => 'Count',
        'useragg'=> 'false',
        'presetagg' => 'SUM:Per Minute',
        'type' => 'sa',
        'querylist' => array(
            array(
                'timecol' => 'time',
                'multiseries'=> 'servers.hostname',
                'whatcol'    => array( 'nproc' => 'Logins' ),
                'tables'     => 'proc_stats, servers',
                'where'      => "proc_stats.serverid IN ( %s ) AND proc_stats.serverid = servers.id AND proc_stats.procid IN ( SELECT id FROM process_names WHERE name LIKE '%%ctxlogin%%' )",
                'qargs'  => array('srvids')
            )
        )
    );

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);

  $srvIdsStr = implode(",",array_values($uasList));
  header("Location:" .  $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59", "srvids=$srvIdsStr") );
}


function chartCtx($statsDB, $uasList, $fileName) {
  global $date;

  //
  // Plot the ctxlogins
  //
  $dataSets = array();

  foreach ( $uasList as $hostname => $id ) {
    $dataSets[$id] =& Image_Graph::factory('dataset');
    $dataSets[$id]->setName($hostname);
  }

  $timeSamples = array();
  $statsDB->query("
SELECT UNIX_TIMESTAMP(tmp.process_stats.time), tmp.process_stats.serverid,
       SUM(tmp.process_stats.nproc)
 FROM tmp.process_stats
 WHERE tmp.process_stats.procid IN ( SELECT id FROM process_names WHERE name LIKE '%ctxlogin%' )
 GROUP BY tmp.process_stats.time, tmp.process_stats.serverid
 ORDER BY tmp.process_stats.time
");
  $currTime = 0;
  while($row = $statsDB->getNextRow() ) {
    if ( $row[0] != $currTime ) {
      $timeSamples[$row[0]] = array();
      $currTime = $row[0];
    }
    $timeSamples[$row[0]][$row[1]] = $row[2];
  }
  foreach ( $timeSamples as $time => $values ) {
    foreach ( $uasList as $hostname => $id ) {
      if ( array_key_exists( $id, $values ) ) {
	$dataSets[$id]->addPoint( $time, $values[$id] );
      } else {
	// Fill in the blanks
	$dataSets[$id]->addPoint( $time, 0 );
      }
    }
  }


  $graph =& Image_Graph::factory('graph', array(640, 240));
  $graph->setBackgroundColor('white');  /* Explicitly set background to white */
  $graph->setBorderColor('white');

  $plotarea =& Image_Graph::factory('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis'));
  $legend =& Image_Graph::factory('legend');
  $legend->setPlotArea($plotarea);

  $graph->add( Image_Graph::vertical( $plotarea, $legend, 80 ) );

  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($date));
  $xAxis->forceMaximum(strtotime($date) + ( (24*60*60) - 1));

  $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
  $xAxis->setDataPreProcessor($dateFormatter);

  $plot =& $plotarea->addNew('Image_Graph_Plot_Step', array(array_values($dataSets), 'stacked'));
  $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
  $plotarea->setBorderColor('white');

  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
  $colours = array("red","orange","yellow","blue","green","indigo","violet");
  foreach ( $colours as $colour ) {
    $fill->addColor($colour . '@0.8');
  }
  $plot->setFillStyle($fill);

  $graph->displayErrors();
  $graph->done( array('filename' => $fileName));

}

function qplotApplications($statsDB, $uasList,$column) {
  global $date;

  createTmpTable($statsDB,$uasList);
  $appIds = getAppIds($statsDB,$column);
  $uasIdStr = implode(",", $uasList);

  $queryList = array();
  foreach ( $appIds as $name => $id ) {
    $query = array(
		   'timecol' => 'time',
		   'whatcol'    => array( "SUM($column)" => $statsDB->escape($name) ),
		   'tables'  => "proc_stats",
		   'where'   => "proc_stats.serverid IN ( $uasIdStr ) AND proc_stats.procid = $id AND $column IS NOT NULL GROUP BY time, procid"
		   );
    $queryList[] = $query;
  }

  $columnLabels = array
    (
     'nproc' => 'Count',
     'rss' => 'RSS Memory (MB)',
     'cpu' => 'CPU Minutes'
     );

  $sqlParam =
    array( 'title'  => 'Total Application ' . $columnLabels[$column],
           'ylabel' => $columnLabels[$column],
           'useragg'=> 'false',
           'querylist' => $queryList,
	   'type' => 'sa'
           );

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);

  header("Location:" .  $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") );
}


function chartApplications($statsDB, $uasList, $fileName,$column) {
  global $date,$debug;

  //
  // Plot the applications
  //
  $dataSets = array();
  $appIds = getAppIds($statsDB,$column);
  if ( count($appIds) == 0 ) {
    if ( $debug > 0 ) {
      echo "chartApplications: No appIds found for $column";
    }
    return;
  }

  foreach ( $appIds as $name => $id ) {
    $dataSets[$id] =& Image_Graph::factory('dataset');
    $dataSets[$id]->setName($name);
  }


  //
  // Pull out the counts by procid
  //
  $timeSamples = array();
  $appIdsStr = implode(",", $appIds);
  $statsDB->query("
SELECT UNIX_TIMESTAMP(tmp.process_stats.time), tmp.process_stats.procid,
       SUM(tmp.process_stats.$column)
 FROM tmp.process_stats
 WHERE tmp.process_stats.procid IN ( $appIdsStr )
 GROUP BY tmp.process_stats.time, tmp.process_stats.procid
 ORDER BY tmp.process_stats.time
");
  $currTime = 0;
  while($row = $statsDB->getNextRow() ) {
    if ( $row[0] != $currTime ) {
      $timeSamples[$row[0]] = array();
      $currTime = $row[0];
    }
    $timeSamples[$row[0]][$row[1]] = $row[2];
  }

  foreach ( $timeSamples as $time => $values ) {
    foreach ( $appIds as $id ) {
      if ( array_key_exists( $id, $values ) ) {
	$dataSets[$id]->addPoint( $time, $values[$id] );
      } else {
	// Fill in the blanks
	$dataSets[$id]->addPoint( $time, 0 );
      }
    }
  }
  $graph =& Image_Graph::factory('graph', array(640, 600));
  $graph->setBackgroundColor('white');  /* Explicitly set background to white */
  $graph->setBorderColor('white');

  $plotarea =& Image_Graph::factory('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis'));
  $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
  $plotarea->setBorderColor('white');

  $legend =& Image_Graph::factory('legend');
  $legend->setPlotArea($plotarea);

  $graph->add( Image_Graph::vertical( $plotarea, $legend, 75 ) );

  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($date));
  $xAxis->forceMaximum(strtotime($date) + ( (24*60*60) - 1));

  $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
  $xAxis->setDataPreProcessor($dateFormatter);

  $plot =& $plotarea->addNew('Image_Graph_Plot_Step', array(array_values($dataSets), 'stacked'));

  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');


  $colours = array( "aliceblue", "blanchedalmond", "burlywood", "coral", "darkgray", "darkmagenta", "darkred", "darkslategray", "deeppink", "dodgerblue", "goldenrod", "indigo", "lavenderblush", "lightcoral", "lightgreen", "lightseagreen", "lightsteelblue", "linen", "mediumblue", "mediumslateblue", "midnightblue", "navajowhite", "olivedrab", "palegoldenrod", "papayawhip", "plum", "rosybrown", "sandybrown", "slategrey", "tan", "turquoise", "whitesmoke" );
  foreach ( $colours as $colour ) {
    $fill->addColor($colour);
  }
  $plot->setFillStyle($fill);

  $graph->done( array('filename' => $fileName));
}

function createTmpTable($statsDB,$uasList) {
  global $date;

  $uasIdStr = implode(",", $uasList);

  // Error in MySQL 5.1.49 "Cannot create temporary table with partitions"
  // Don't use tmp.xxx syntax as this breaks replication
  $row = $statsDB->queryRow("select database()");
  $statsDBname = $row[0];

  $statsDB->exec("use tmp");
  $statsDB->exec("CREATE TEMPORARY TABLE process_stats SELECT * FROM $statsDBname.proc_stats WHERE serverid < 0");

  $statsDB->exec("
INSERT INTO process_stats
 SELECT * FROM $statsDBname.proc_stats WHERE
  $statsDBname.proc_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  $statsDBname.proc_stats.serverid IN ( $uasIdStr )
");

  $statsDB->exec("use $statsDBname");
}

function plotProcGraphs($statsDB,$uasList) {
  global $rootdir, $site, $date, $web_temp_dir;

  $plotFiles = array();
  $filebase=tempnam($web_temp_dir,"appserv");

  createTmpTable($statsDB,$uasList);

  $plotFiles["ctx"] = $filebase . '_ctx.png';
  chartCtx($statsDB,$uasList,$plotFiles["ctx"]);

  $plotFiles["appcount"] = $filebase . '_appcount.png';
  chartApplications($statsDB,$uasList,$plotFiles["appcount"], 'nproc');

  $plotFiles["appcpu"] = $filebase . '_appcpu.png';
  chartApplications($statsDB,$uasList,$plotFiles["appcpu"], 'cpu');

  $plotFiles["apprss"] = $filebase . '_apprss.png';
  chartApplications($statsDB,$uasList,$plotFiles["apprss"], 'rss');

  $plotFiles["appthr"] = $filebase . '_appthr.png';
  chartApplications($statsDB,$uasList,$plotFiles["appthr"], 'thr');

  return $plotFiles;
}

function chartServCpu($statsDB,$uid) {
  global $date;

    $dataSet =& Image_Graph::factory('dataset');

  $statsDB->query("
SELECT UNIX_TIMESTAMP(time), (user + sys + iowait)
 FROM hires_server_stat
 WHERE
  time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  serverid = $uid
");
  while($row = $statsDB->getNextRow() ) {
    if (is_numeric($row[2])) $dataSet->addPoint( $row[0], $row[1] );
  }

  $graph =& Image_Graph::factory('graph', array(400, 100));
  $graph->setBackgroundColor('white');  /* Explicitly set background to white */
  $graph->setBorderColor('white');

  $plotarea =& $graph->addNew('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis') );
  $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
  $plotarea->setBorderColor('white');

  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($date));
  $xAxis->forceMaximum(strtotime($date) + ( (24*60*60) - 1));
  $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
  $xAxis->setDataPreProcessor($dateFormatter);

  $yAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_Y);
  $yAxis->forceMinimum(0);
  $yAxis->forceMaximum(100);


  $plot =& $plotarea->addNew('Image_Graph_Plot_Area', array(&$dataSet));

  $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
  $fill->addColor('red@0.2');
  $plot->setFillStyle($fill);

  $graph->done();
}

function chartAllServerCPU($statsDB,$uasList,$fileName) {
  global $date;

  //
  // Plot the ctxlogins
  //
  $dataSets = array();

  foreach ( $uasList as $hostname => $id ) {
    $dataSets[$id] =& Image_Graph::factory('dataset');
    $dataSets[$id]->setName($hostname);
  }

  $timeSamples = array();
  $uasIdStr = implode(",", $uasList);
  $statsDB->query("
SELECT UNIX_TIMESTAMP(time), serverid, (user + sys + iowait)
 FROM hires_server_stat
 WHERE
  time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  serverid IN ( $uasIdStr )
");
  while($row = $statsDB->getNextRow() ) {
      if (is_numeric($row[2])) $dataSets[$row[1]]->addPoint( $row[0], $row[2] );
  }


  $graph =& Image_Graph::factory('graph', array(640, 480));
  $graph->setBackgroundColor('white');  /* Explicitly set background to white */
  $graph->setBorderColor('white');

  $plotarea =& Image_Graph::factory('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis'));
  $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
  $plotarea->setBorderColor('white');

  $legend =& Image_Graph::factory('legend');
  $legend->setPlotArea($plotarea);

  $graph->add( Image_Graph::vertical( $plotarea, $legend, 80 ) );

  $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
  $xAxis->forceMinimum(strtotime($date));
  $xAxis->forceMaximum(strtotime($date) + ( (24*60*60) - 1));

  $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
  $xAxis->setDataPreProcessor($dateFormatter);

  $plot =& $plotarea->addNew('Image_Graph_Plot_Smoothed_Line', array(array_values($dataSets), 'normal'));
  $plot->setBackgroundColor('gray@0.2');

  $lineArr =& Image_Graph::factory('Image_Graph_Line_Array');
  $colours = array("red","yellow","blue","green","orange");
  foreach ( $colours as $colour ) {
    $lineArr->addColor($colour . '@0.8');
  }
  $plot->setLineStyle($lineArr);

  $graph->displayErrors();
  $graph->done( array('filename' => $fileName));
}

//
// Main
//
$statsDB = new StatsDB();

if ( isset($_GET["uas"]) ) {
    $uasList = getUasList($datadir,$statsDB,$site,$_GET["uas"]);
    $globalUasList = getUasList($datadir,$statsDB,$site);
  $singleUASview = TRUE;
} else {
  $uasList = getUasList($datadir,$statsDB,$site);
  $singleUASview = FALSE;
}

if ( isset($_GET["qplot"]) ) {
  $qplot = $_GET["qplot"];
  if ( $qplot == 'ctx' ) {
    qplotCtx($statsDB,$uasList);
  } else if ( $qplot == 'app' ) {
    qplotApplications($statsDB,$uasList, $_GET['col']);
  }
  exit;
 } else if ( isset($_GET["chart"]) ) {
  $chart = $_GET["chart"];
  if ( $chart == 'servcpu' ) {
    $uasId = $_GET["uasid"];
    chartServCpu($statsDB,$uasId);
  }
 }

if ( count($uasList) == 0 ) {
  echo "<b>No UASs found</b>\n";
  include "common/finalise.php";
  exit;
}

$qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&qplot=";

$procGraphs = plotProcGraphs($statsDB,$uasList);

if ( $singleUASview == FALSE ) {
  echo "<H1>UAS List</H1>\n";
  $servTable = getuasTable($uasList);
  echo $servTable->toHTML();
  echo "<H1>Application Server Stats for All Application Servers</H1>\n";
} else {
    echo "<H1>UAS List</H1>\n";
    $servTable = getuasTable($globalUasList);
    echo $servTable->toHTML();
    echo "<br/><a href=\"" . $_SERVER['PHP_SELF'] . "?" . $webargs . "\">Back to Application Server Overview</a><br/>\n";
  $hostnames = array_keys($uasList);
  echo "<H1>Application Server Stats for " . implode(",", $hostnames) . "</H1>\n";
}

echo "<H3>Citrix Logins";
drawHelpLink("ctx"); echo "</H3>\n";
drawHelp("ctx", "Citrix Logins",
	 "<p>This is number of ctxlogin processes running on each UAS</p>
<p>Click on the graph to zoom in or change the time period</p>");

echo "<a href=\"" . $qplotBase . "ctx\"><img src=\"/temp/" . basename($procGraphs['ctx']) . "\" alt=\"\"></a>\n";

echo "<H2>Applications</H2>\n";

echo "<H3>Number of Application Instances";
drawHelpLink("app"); echo "</H3>\n";
drawHelp("app", "Application Counts",
	 "<p>This is number of application instances running on each UAS</p>
<p>Click on the graph to zoom in or change the time period</p>");

echo "<a href=\"" . $qplotBase . "app&col=nproc\"><img src=\"/temp/" . basename($procGraphs['appcount']) . "\" alt=\"\"></a>\n";

echo "<H3>CPU Usage Per Application Type";
drawHelpLink("appcount"); echo "</H3>\n";
drawHelp("appcount", "Application CPU",
"<p>This is amount of CPU time taken by the application totalled across all UASs</p>
<p>Click on the graph to zoom in or change the time period</p>");
echo "<a href=\"" . $qplotBase . "app&col=cpu\"><img src=\"/temp/" . basename($procGraphs['appcpu']) . "\" alt=\"\"></a>\n";

echo "<H3>Memory Usage Per Application Type";
drawHelpLink("appmem"); echo "</H3>\n";
drawHelp("appmem", "Application Memory",
	 "<p>This is amount of memory (RSS in MB) taken by the application totalled across all UASs</p>
<p>Click on the graph to zoom in or change the time period</p>");
echo "<a href=\"" . $qplotBase . "app&col=rss\"><img src=\"/temp/" . basename($procGraphs['apprss']) . "\" alt=\"\"></a>\n";

echo "<H3>Thread Usage Per Application Type";
drawHelpLink("appthr"); echo "</H3>\n";
drawHelp("appthr","Application Thread Count",
    "<p />This is the number of threads (LWPs) used by the application, totalled across all UASs
    <p />Click on the graph to zoom in or change the time period");
echo "<a href=\"" . $qplotBase . "app&col=thr\"><img src=\"/temp/" . basename($procGraphs['appthr']) . "\" alt=\"\"></a>\n";

include "common/finalise.php";
?>
