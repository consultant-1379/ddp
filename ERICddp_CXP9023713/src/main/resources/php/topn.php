<?php
$pageTitle = "Top Processes";

/* Disable the UI for non-main flow */
if ( isset($_GET["plot"]) || isset($_GET["format"]) || isset($_GET["getdata"]) ) {
    $UI = false;
}

$YUI_DATATABLE = true;

include "common/init.php";

require_once "SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

const MEMORY = 'Memory (MB)';
const RSS = 'RSS (MB)';
$titles = array( 'cpu' => 'CPU', 'cpurate' => 'Average CPU minutes per minute',
         'mem' => MEMORY, 'rss' => RSS,
         'thr' => 'Threads', 'fd' => 'File Descriptors' );

$yAxisLabel = array( 'cpu' => 'CPU minutes per 15 min interval', 'cpurate' => 'Average CPU minutes per minute');

function getData($statsDB) {
  global $date;

  $serverId = $_GET["serverid"];

  $statsDB->query("
SELECT process_names.name AS procname,
 SUM(proc_stats.cpu) AS cpu,
 TRUNCATE(AVG(proc_stats.cpu * 60 / proc_stats.sample_interval), 2) AS cpurate,
 MAX(proc_stats.mem) AS mem,
 MAX(proc_stats.rss) AS rss,
 MAX(proc_stats.thr) AS thr,
 MAX(proc_stats.fd) AS fd,
 process_names.id AS procid
FROM process_names, proc_stats
WHERE
 proc_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 proc_stats.serverid = $serverId AND
 proc_stats.procid = process_names.id AND
 proc_stats.cpu IS NOT NULL
GROUP BY proc_stats.procid
ORDER BY cpu DESC
");

  $results = array();
  while ( $row = $statsDB->getNextNamedRow() ) {
    $results[] = $row;
  }

  $response = array();
  $response['result'] = $results;

  echo json_encode($response);
}


function getPlotURL($statsDB,$plotType,$serverId,$procIds,$imgPlot) {
  global $date, $titles, $debug, $yAxisLabel;

  if ($plotType == "cpurate"){
      $databaseColumn = "TRUNCATE((proc_stats.cpu * 60 / proc_stats.sample_interval), 2)";
  } else {
      $databaseColumn = $plotType;
  }

  $currentYAxisLabel = '';
  if ( array_key_exists($plotType, $yAxisLabel) ) {
      $currentYAxisLabel = $yAxisLabel[$plotType];
  }

  $sqlParam =
    array( 'title'  => $titles[$plotType],
       'ylabel'     => $currentYAxisLabel,
       'useragg'    => 'true',
       'persistent' => 'true',
       'forcelegend' => 'true',
       'querylist' =>
       array(
         array (
            'timecol' => 'time',
            'multiseries' => 'process_names.name',
            'whatcol' => array( $databaseColumn => $titles[$plotType] ),
            'tables'  => "proc_stats FORCE INDEX(serverTimeIdx), process_names",
            'where'   => "process_names.id = proc_stats.procid AND proc_stats.serverid = %d AND $databaseColumn IS NOT NULL AND proc_stats.procid IN ( %s )",
            'qargs'   => array( 'serverid', 'procids' )
            )
         )
       );

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);

  if ( $imgPlot == TRUE ) {
    $qplotURL = $sqlParamWriter->getImgURL( $id,
                        "$date 00:00:00", "$date 23:59:59",
                        true, 640, 320,
                        "procids=" . $procIds ."&serverid=" . $serverId );
  } else {
    $qplotURL = $sqlParamWriter->getURL( $id,
                     "$date 00:00:00", "$date 23:59:59",
                     "procids=" . $procIds ."&serverid=" . $serverId );
  }

  if ( $debug ) { echo "<pre>qploturl: $qplotURL</pre>\n"; }

  return $qplotURL;
}

function topPlot($file,$date,$serverId,$dbColumn,$title,$ylabel,$statsDB) {
  global $webroot;
  global $rootdir;

  #
  # If we already plotted the graph during processing then use that
  #
  if ( file_exists($rootdir . "" . $file) ) {
   return '<img src="' . $webroot . '/' . $file . '" alt="" width="640" height="480">';
  }

  #
  # Otherwise get the data from proc_stats and use qplot to plot it
  #

  if ( $dbColumn == "cpu" ) {
      $keyType = "SUM";
  } else {
      $keyType = "MAX";
  }

  $procIds = array();
  $statsDB->query("
SELECT process_names.id
 FROM process_names, proc_stats
 WHERE
  process_names.id = proc_stats.procid AND
  proc_stats.serverid = $serverId AND
  proc_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
 GROUP BY process_names.id
 ORDER BY $keyType($dbColumn) DESC
 LIMIT 8");
  while ( $row = $statsDB->getNextRow() ) {
    $procIds[] = $row[0];
  }

  $sqlParam =
    array( 'title'      => $title,
       'ylabel'     => $ylabel,
       'useragg'    => 'true',
       'persistent' => 'true',
       'querylist' =>
       array(
         array (
            'timecol' => 'time',
                        'multiseries' => 'process_names.name',
            'whatcol' => array( $dbColumn => $dbColumn ),
            'tables'  => "proc_stats FORCE INDEX(serverTimeIdx), process_names",
            'where'   => "process_names.id = proc_stats.procid AND proc_stats.serverid = %d AND $dbColumn IS NOT NULL AND proc_stats.procid IN ( %s )",
            'qargs'   => array( 'serverid', 'procids' )
            )
         )
       );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  return $sqlParamWriter->getImgURL( $id,
                     "$date 00:00:00", "$date 23:59:59",
                     true, 640, 480,
                     "procids=" . implode(",", $procIds) . "&serverid=" . $serverId );
}

function mainFlow($statsDB) {
  global $date, $rootdir, $webroot, $site, $php_webroot, $webargs, $debug, $dir, $oss;

  $server=$_GET["server"];

  $serverPageLink = "$php_webroot/server.php?site=$site&dir=$dir&date=$date&oss=$oss&server=$server";

  echo <<<EOT
<p><a href="$serverPageLink">Return to Server Stats</a><p>

<h1>Top Processes: $server</h1>
 <ul>
  <li><a href="#Memoryhelp_anchor">Memory</a></li>
  <li><a href="#CPUhelp_anchor">CPU</a></li>
  <li><a href="#Threadshelp_anchor">Threads</a>
  <li><a href="#FDhelp_anchor">File Descriptors</a>
 </li>
</ul>

<p>Note: The measurements for all dataserver processes are combined into one</p>
EOT;




  $procdir="";
  if ( isset($_GET["procdir"]) ) {
    $procdir= "/" . $_GET["procdir"];
  } else if ( file_exists($rootdir . "/process") ) {
    $procdir = "/process";
  }

  $rootdir = $rootdir . $procdir;
  $webroot = $webroot . $procdir;


  $row = $statsDB->queryRow("
  SELECT servers.id
  FROM servers, sites
  WHERE
  sites.name = \"$site\" AND sites.id = servers.siteid AND
  servers.hostname = '$server'");
  $serverId = $row[0];

  $downloadLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&serverid=' . $serverId . '"\> [Download Excel]</a>';
 drawHeaderWithHelp( "Processes List", 2, "processeslisthelp", "DDP_Bubble_44_Common_Topn_Processes_List", '', $downloadLink);
 # echo "<a href=\"" . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] .
  #  "&format=xls&serverid=" . $serverId . "\">Export to Excel</a>\n";

  $selfURL = $_SERVER['PHP_SELF'] . "?" . $webargs . "&serverid=$serverId";
  $debugEnabled = "false";
  if ( $debug ) {
    $debugEnabled = "true";
  }

  $ossType = $_GET["oss"];

  print <<<END
<script type="text/javascript">
var debugEnabled = $debugEnabled;
var ossTypeVar = "$ossType";
var myLogReader;
if ( debugEnabled == true ) {
  myLogReader = new YAHOO.widget.LogReader("myLogReader");
}
var selfURL = "$selfURL";
</script>

<div id="myLogger" class="yui-skin-sam"></div>

<script type="text/javascript" src="$php_webroot/topn.js"></script>
<script type="text/javascript">
    YAHOO.util.Event.addListener(window, "load", makeProcTable);
</script>

<div id="procdiv" class="yui-skin-sam"></div>

END;

  if ( file_exists($rootdir . "/cpu.jpg") ) {
    $cpugraph="cpu.jpg";
  } else {
    $cpugraph="cpuusage.jpg";
  }

  drawHeaderWithHelp( "Memory", 2, "Memoryhelp", "DDP_Bubble_141_Memory_Topn_Graph_List" );

  echo topPlot("memusage.jpg",$date,$serverId,"rss","Memory(RSS)","MB", $statsDB) . "\n";

  drawHeaderWithHelp( "CPU", 2, "CPUhelp", "DDP_Bubble_142_CPU_Topn_Graph_List" );

  echo topPlot($cpugraph,$date,$serverId,"cpu","CPU","Minutes", $statsDB) . "\n";

  drawHeaderWithHelp( "Threads", 2, "Threadshelp", "DDP_Bubble_143_Thread_Topn_Graph_List" );

  echo topPlot("thrusage.jpg",$date,$serverId,"thr","Threads","Threads", $statsDB) . "\n";


  drawHeaderWithHelp( "File Descriptors", 2, "FDhelp", "DDP_Bubble_144_FD_Topn_Graph_List" );

  echo topPlot("fdusage.jpg",$date,$serverId,"fd","File Descriptors","FD", $statsDB) . "\n";

}

class ProcessList extends DDPObject {
    var $cols = array (
        "procname" => 'Process Name',
        "cpu"      => 'CPU Time',
        "cpurate"  => 'Avg. CPU / Minute',
        "mem"      => MEMORY,
        "rss"      => RSS,
        "thr"      => 'Threads',
        "fd"       => 'File Descriptors'
    );

    var $title = "Process Statistics";
    var $defaultOrderBy = "cpu";
    var $defaultOrderDir = "DESC";
    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");

    var $date;
    var $serverId;
    var $addLinks;

    function __construct($serverId,$addLinks) {
        parent::__construct("processlist");
        $this->serverId = $serverId;
    $this->addLinks = $addLinks;
    }

    function getData() {
        global $date;
        $qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&serverid=" . $this->serverId . "&qplot=";
        $sql = "SELECT process_names.name AS procname,
            SUM(proc_stats.cpu) AS cpu,
            TRUNCATE(AVG(proc_stats.cpu * 60 / proc_stats.sample_interval), 2) AS cpurate,
            MAX(proc_stats.mem) AS mem,
            MAX(proc_stats.rss) AS rss,
            MAX(proc_stats.thr) AS thr,
            MAX(proc_stats.fd) AS fd,
            process_names.id AS procid
            FROM process_names, proc_stats WHERE
            proc_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            proc_stats.serverid = " . $this->serverId . " AND
            proc_stats.procid = process_names.id AND
            proc_stats.cpu IS NOT NULL
            GROUP BY proc_stats.procid";
        $this->populateData($sql);
        # each value needs to be surrounded in a link to qplot
        foreach ($this->data as $key => $d) {
            $columns = array("cpu" => "CPU Time","mem" => MEMORY,"rss" => RSS,"thr" => "Threads","fd" => "File Descriptors",
                "cpurate" => "CPU Seconds / Sec");
            foreach (array_keys($columns) as $c) {
                if ( $this->addLinks == TRUE ) {
                    if ($c == "cpu" || $c == "cpurate") $filter = "AND cpu IS NOT NULL";
                    else $filter = "";
                    if ($c == "cpurate") $thiscol = "TRUNCATE((proc_stats.cpu * 60 / proc_stats.sample_interval), 2)";
                    else $thiscol = $c;
                    $d[$c] = '<a href="' . qPlotURL($date,$this->serverId,$d['procid'],$thiscol, $columns[$c] . " for %s", $d['procname'], $columns[$c], $filter) .
                        '">' . $d[$c] . '</a>';
                }
            }
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}


$statsDB = new StatsDB();

if ( isset($_GET["format"]) ) {
  $excel = new ExcelWorkbook();
  $excel->addObject(new ProcessList($_GET["serverid"],FALSE));
  $excel->write();
  exit;
} else if ( isset($_GET["getdata"]) ) {
  getData($statsDB);
  exit;
} else if ( isset($_GET["plot"]) ) {
  $plotType = $_GET['plot'];
  $serverId = $_GET['serverid'];
  $procIds = $_GET['procids'];
  $qplotURL = getPlotURL($statsDB,$plotType,$serverId,$procIds,FALSE);
  header("Location:" . $qplotURL );
  exit;
} else if ( isset($_GET["plotall"]) ) {
  $serverId = $_GET['serverid'];
  $procIds = $_GET['procids'];
  $table = new HTML_Table("border=0");
  drawHeaderWithHelp( "Plot All Graph Guide", 2, "plotallgraphguide", "DDP_Bubble_43_Common_Topn_Plot_All_Graph_Guide" );
  foreach ( array_keys($titles) as $key ) {

  $table->addRow(array(getPlotURL($statsDB,$key,$serverId,$procIds,TRUE)));
   $table->addRow($key);
  }
  echo $table->toHTML();
} else {
  mainFlow($statsDB);
}

include "common/finalise.php";
?>

