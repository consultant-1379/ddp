<?php
$pageTitle = "NEAD Statistics";

if ( isset($_GET["qplot"]) ) {
    $UI = false;
 }
include "common/init.php";

$webroot = $webroot . "/cms";
$rootdir = $rootdir . "/cms";

$plotdir = $datadir . "/cms_plots";
$graphBase = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=cms_plots/";
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

require_once PHP_ROOT . "/classes/JsPlot.php";

#
# Redirect to qplot to display the request graph
#
function qPlot($site,$date,$plot)
{
   $qPlots = array();

   $qPlots["nodes"] = array( 'title'  => 'Total/Alive/Synced Nodes',
                 'ylabel' => 'Nodes',
                 'whatcol'=> array( 'total' => 'TOTAL_NODES',
                        'alive' => 'ALIVE_NODES',
                        'synced' => 'SYNCED_NODES')
                 );


   $qPlots["sync"] = array( 'title'  => 'Node Sync Status',
                'ylabel' => 'Nodes',
                'whatcol'   => array( 'unsynced' => 'UNSYNCED_NODES',
                          'topology' => 'TOPOLOGY_SYNC_NODES',
                          'attribute' => 'ATTRIBUTE_SYNC_NODES')
                );

   $qPlots["ongoing"] = array( 'title'  => 'Ongoing Node Syncs',
                   'ylabel' => 'Nodes',
                   'whatcol'   => array('sync_rbs' => 'SYNCHRONIZATION_ONGOING_RBS',
                            'sync_rxi' => 'SYNCHRONIZATION_ONGOING_RANAG',
                            'sync_rnc' => 'SYNCHRONIZATION_ONGOING_RNC',
                            'sync_erbs' => 'SYNCHRONIZATION_ONGOING_ERBS')
                   );

   $qPlots["notif_buf"] =
     array( 'title'  => 'Notification Buffer Size',
        'ylabel' => 'Notifications',
        'whatcol'   => array('n_buff' => 'NOTIFICATION_BUFFER') );

   $qPlots["notif_rec"] =
     array( 'title'  => 'Notifications Recevied',
        'ylabel' => 'Notifications',
        'whatcol'   => array('n_recv' => 'NOTIFICATION_RECEIVED_LAST_OUTPUT_PERIOD') );

   $qPlots["notiftime"] =
     array( 'title'  => 'Notification Processing Time',
        'ylabel' => 'MilliSec',
        'whatcol'   => array('n_proc_avg_t' => 'NOTIFICATION_AVERAGE_PROCESS_TIME',
                 'n_proc_max_t' => 'NOTIFICATION_MAX_PROCESS_TIME')
        );

   $qPlots["threadpool"] =
     array( 'title'  => 'ThreadPool',
        'ylabel' => 'Tasks',
        'whatcol'   => array('tp_wait' => 'THREADPOOL_WAITING',
                 'tp_exe' => 'THREADPOOL_EXECUTING')
        );

   $qPlots["threadpool2"] =
     array( 'title'  => 'ThreadPool2',
        'ylabel' => 'Tasks',
        'whatcol'   => array('tp2_wait' => 'THREADPOOL2_WAITING',
                 'tp2_exe' => 'THREADPOOL2_EXECUTING')
        );

   $qPlots["ping_time"] =
     array( 'title'  => 'Ping Time',
        'ylabel' => 'MilliSec',
        'whatcol'   => array('ping_avg_t' => 'AVERAGE_PING_TIME',
                 'ping_max_t' => 'MAX_PING_TIME')
        );

   $qPlots["ping_count"] =
     array( 'title'  => 'Ping Counts',
        'ylabel' => 'Counts',
        'whatcol'   => array('ping_okay' => 'NUM_SUCCESSFUL_PINGS',
                 'ping_fail' => 'NUM_FAILED_PINGS')
        );

   $qPlots["nesu"] =
     array( 'title'  => 'Ping Thread Pool',
        'ylabel' => 'Tasks',
        'whatcol'   => array('nesu_wait' => 'NESUPOOL_WAITING',
                 'nesu_exe' => 'NESUPOOL_EXECUTING')
        );

   $qPlots["notifnodes"] =
     array( 'title'  => 'Number Of Nodes which have sent notfications',
        'ylabel' => 'Nodes',
        'whatcol'   => array('notifnodes' => 'NOTIFICATIONNODESTATS')
        );

   # MeContext updates
   $qPlots["me_writes"] =
     array( 'title'  => 'Number Of MeContents Writes Performed',
        'ylabel' => 'Count',
        'whatcol'   => array('num_me_writes' => 'NUSUCCMECONTEXTWRITES')
        );
   $qPlots["me_maxq"] =
     array( 'title'  => 'Maximum Number of MeContents waiting to be written',
        'ylabel' => 'Count',
        'whatcol'   => array('max_me_q' => 'MAXMECONTEXTQUEUESIZE')
        );
   $qPlots["me_avg_delay"] =
     array( 'title'  => 'Average Delay of MeContent writes',
        'ylabel' => 'MilliSec',
        'whatcol'   => array('avg_me_write_delay' => 'AVEDELAYMECONTEXTWRITE')
        );


   if ( isset($qPlots[$plot]) ) {
       $sqlParam = array( 'title'      => $qPlots[$plot]['title'],
              'ylabel'     => $qPlots[$plot]['ylabel'],
              'useragg'    => 'true',
              'persistent' => 'true',
              'querylist' =>
              array(
                array(
                      'timecol' => 'time',
                      'whatcol'    => $qPlots[$plot]['whatcol'],
                      'tables'  => "hires_nead_stat, sites",
                      'where'   => "hires_nead_stat.siteid = sites.id AND sites.name = '%s'",
                      'qargs'   => array( 'site' )
                      )
                 )
              );
       $sqlParamWriter = new SqlPlotParam();
       $id = $sqlParamWriter->saveParams($sqlParam);

       header("Location:" .  $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") );
     }
}



#
# Notifications Recevied
#
function getNotifRecTable($statsDB,$site,$date)
{
  $notifRecTable = new HTML_Table("border=1");
  $statsDB->query("
SELECT
  nead_notifrec.eventtype AS eventtype, nead_notifrec.nodetype AS nodetype,
  mo_names.name AS mo, nead_attrib_names.name AS attribute,
  nead_notifrec.count AS count
 FROM nead_notifrec, mo_names, nead_attrib_names, sites
 WHERE
   nead_notifrec.date = '$date' AND
   nead_notifrec.siteid = sites.id AND sites.name = '$site' AND
   nead_notifrec.moid = mo_names.id AND
   nead_notifrec.attribid = nead_attrib_names.id
 ORDER BY count DESC");
  if ( $statsDB->getNumRows() > 0 ) {
    $notifRecTable->addRow( array ("Event Type","Node Type", "MO", "Attribute", "Count"), null, 'th' );

    while($row = $statsDB->getNextRow()) {
      $notifRecTable->addRow($row);
    }
  }

  return $notifRecTable;
}

#
# Top Notification Nodes
#
function getNotifTopTable($statsDB,$site,$date)
{
  $table = new HTML_Table("border=1");
  $statsDB->query("
SELECT
  ne.name, nead_notiftop.count
 FROM nead_notiftop, ne, sites
 WHERE
   nead_notiftop.date = '$date' AND
   nead_notiftop.siteid = sites.id AND sites.name = '$site' AND
   nead_notiftop.neid = ne.id AND
   nead_notiftop.count > 1000
 ORDER BY count DESC");
  if ( $statsDB->getNumRows() > 0 ) {
    $table->addRow( array ("Node","Count"), null, 'th' );

    while($row = $statsDB->getNextRow()) {
      $table->addRow($row);
    }
  }

  return $table;
}

#
# Network sync stuff
#
function getNetSyncTable($statsDB,$site,$date)
{
  $netSyncTable = new HTML_Table('border=1');

  $statsDB->query("
SELECT cms_net_sync.id
 FROM cms_net_sync, sites
 WHERE
  cms_net_sync.siteid = sites.id AND sites.name = '$site' AND
  cms_net_sync.starttime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
  ORDER BY cms_net_sync.starttime");
  $ids = array();
  while($row = $statsDB->getNextRow()) {
    $ids[] = $row[0];
  }
  if ( count($ids) > 0 ) {
    $colNames = array( 'Start Time', 'Duration', 'Total #Num Syncs', '#Node Sync', '#Nodes sync not required', '#MultiSync Nodes', '#Delta Syncs',
               'Total MOs', 'MOs Created', 'MOs Deleted',
               'Total NE Wait Time', 'Total CS Wait Time',
               'Raw MO/Sec', 'Adjusted MO/Sec' );
    $netSyncTable->addRow( $colNames, null, 'th' );

    foreach ($ids as $id) {
      $tableRow = array();

      $row = $statsDB->queryRow("SELECT siteid, starttime, endtime, TIMEDIFF(endtime, starttime), synced, TIME_TO_SEC(TIMEDIFF(endtime, starttime)) FROM cms_net_sync WHERE id = $id");
      $tableRow[] = $row[1];
      $tableRow[] = $row[3];

      $synced = $row[4];
      $siteid = $row[0];
      $starttime = $row[1];
      $endtime = $row[2];
      $durationSecs = $row[5];

      $row = $statsDB->queryRow("SELECT COUNT(*), COUNT(DISTINCT neid) FROM ne_sync_success WHERE siteid = $siteid AND endtime BETWEEN '$starttime' AND '$endtime'");
      $tableRow[] = $row[0];
      $tableRow[] = $row[1];
      $tableRow[] = $synced - $row[1];

      $row = $statsDB->query("SELECT neId, COUNT(*) AS numSyncs FROM ne_sync_success WHERE siteid = $siteid AND endtime BETWEEN '$starttime' AND '$endtime' GROUP BY (neid) HAVING numSyncs > 1");
      $tableRow[] = $statsDB->getNumRows();

      $row = $statsDB->queryRow("SELECT COUNT(*) AS deltaSyncs FROM ne_sync_success WHERE siteid = $siteid AND endtime BETWEEN '$starttime' AND '$endtime' AND isdelta = 1");
      $tableRow[] = $row[0];

      $row = $statsDB->queryRow("
SELECT SUM(numMoRead),
       SUM(numMoCreate),
       SUM(numMoDelete),
       SEC_TO_TIME(SUM(timeReadWaitQ)),
       SEC_TO_TIME(SUM(timeWriteWaitQ))
FROM ne_sync_success WHERE siteid = $siteid AND endtime BETWEEN '$starttime' AND '$endtime'
");
      $tableRow[] = $row[0];
      $tableRow[] = $row[1];
      $tableRow[] = $row[2];
      $tableRow[] = $row[3];
      $tableRow[] = $row[4];
      $totalMOs = $row[0];

      $row = $statsDB->queryRow("
SELECT SUM(timeTotal) / ( SUM(timeTotal - (timeReadMoNe + timeWriteWaitQ)) )
FROM ne_sync_success WHERE siteid = $siteid AND endtime BETWEEN '$starttime' AND '$endtime'");
      $ossFactor = $row[0];

      $rawRate = $totalMOs/$durationSecs;
      $ossRate = $rawRate * $ossFactor;
      $tableRow[] = sprintf("%d",$rawRate);
      $tableRow[] = sprintf("%d",$ossRate);

      $netSyncTable->addRow($tableRow);
    }
  }

  return $netSyncTable;
}

#
# Main Statistics Table
#
function getMainStatsTable($statsDB,$site,$date)
{
  global $rootdir;

  $mainStatTable = new HTML_Table('border=1');
  $mainStatTable->addRow( array( "Statistic", "Value" ), null, 'th' );

  $row = $statsDB->queryRow("
SELECT conn
 FROM nead_connections, sites
 WHERE
 nead_connections.siteid = sites.id AND sites.name = '$site' AND
 nead_connections.date = '$date'");
  $mainStatTable->addRow( array( "Number of connectionStatus Events", number_format($row[0]) ) );

  $row = $statsDB->queryNamedRow("
SELECT SUM(n_recv) AS notif, (SUM(n_recv) - SUM(n_discard)) AS persist
 FROM hires_nead_stat, sites
 WHERE
 hires_nead_stat.siteid = sites.id AND sites.name = '$site' AND
 hires_nead_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  $mainStatTable->addRow( array( "Number of notifications received", number_format($row["notif"]) ) );
  $mainStatTable->addRow( array( "Number of notifications for persistent attributes received", number_format($row["persist"]) ) );

  $row = $statsDB->queryNamedRow("
SELECT ROUND(AVG(n_proc_avg_t)) AS avgtime
 FROM hires_nead_stat, sites
 WHERE
 hires_nead_stat.siteid = sites.id AND sites.name = '$site' AND
 hires_nead_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 hires_nead_stat.n_proc_avg_t >= 0");
  $mainStatTable->addRow( array( "Average notfication processing time", number_format($row["avgtime"]) ) );

  if ( ! file_exists($rootdir . "/SyncTotal_table.html") ) {
    $row = $statsDB->queryRow("
SELECT COUNT(*)
 FROM ne_sync_success, sites
 WHERE
  ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_success.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    $mainStatTable->addRow( array( "Total Number of successful syncs", number_format($row[0]) ) );

    $numFail = 0;
    $row = $statsDB->queryRow("
SELECT SUM(ne_sync_failure.count)
 FROM ne_sync_failure, sites
 WHERE
  ne_sync_failure.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_failure.date = '$date'");
    if ( isset($row[0]) ) {
      $numFail = $row[0];
    }
    $mainStatTable->addRow( array( "Total Number of failed syncs", number_format($numFail) ) );
  }

  $statsDB->query("
SELECT ne_types.name, SEC_TO_TIME(ROUND( AVG(ne_sync_success.timeTotal), 0))
 FROM ne_sync_success, ne_types, ne, sites
 WHERE
  ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_success.neid = ne.id AND
  ne.netypeid = ne_types.id AND
  ne_sync_success.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
  GROUP BY ne_types.name");
  while($row = $statsDB->getNextRow()) {
    $mainStatTable->addRow( array( "Average $row[0] Sync Time" ,$row[1] ) );
  }

  return $mainStatTable;
}

#
# If the user has clicked on a graph
#
if ( isset($_GET["qplot"]) ) {
  qPlot($site,$date,$_GET["qplot"]);
  exit;
 }


#
# Main Flow
#
$qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&qplot=";

$calStatFile = $rootdir . "/NEAD_calculatedStatsTable.html";

$statsDB = new StatsDB();

?>

<?php

echo "<H1>";
drawHeaderWithHelp("NEAD Statistics", 1, "neadStatisticshelp", "DDP_Bubble_76_OSS_NEAD_Statistics");
echo "</H1>";

#
# Top Level Statistics Table
#
if ( file_exists($calStatFile) ) {
  echo "<table border=1>\n";
  echo "<tr> <th>Statistic</th> <th>Value</th> </tr>\n";
  include($calStatFile);
  echo "</table>\n";
 } else {
  echo getMainStatsTable($statsDB,$site,$date)->toHTML();
 }

$netSyncTable = getNetSyncTable($statsDB,$site,$date);
#Notifications Received table is moved to a new page - TORF-245990
#$notifRecTable = getNotifRecTable($statsDB,$site,$date);
$rnsTable = getRnsTable($statsDB,$site,$date);
#Top Notifications table is moved to a new page - TORF-245990
#$notifTopTable = getNotifTopTable($statsDB,$site,$date);

#
# Generate contents links
#

# Backwards compatible with old static page for sync info
echo "<ul>\n";

$sync_info = $rootdir . "/sync_info.html";
if ( file_exists($sync_info) ) {
  $syncURL = $webroot . "/sync_info.html";
 } else {
  $syncURL = $php_webroot . "/nead_sync_info.php?$webargs";
 }

echo "<li><a href=\"$syncURL\">List of Node Syncs Success/Failures</a></li>\n";

$row = $statsDB->queryRow("
SELECT COUNT(*)
 FROM nead_notifrec, sites
 WHERE
   nead_notifrec.date = '$date' AND
   nead_notifrec.siteid = sites.id AND sites.name = '$site'
");
if ( $row[0] > 0 ) {
$notifURL = $php_webroot . "/nead_notif.php?$webargs";
echo "<li><a href=\"$notifURL\">Notifications Received Details</a></li>\n";
}

# monitorRnc stuff
$monRncFile = $rootdir . "/rnc_eventrate.jpg";
if ( file_exists($monRncFile) ) {
  echo "<li><a href=\"" . $php_webroot . "/monitorRnc.php?" . $webargs . "\">RNC Events</a></li>\n";
 }

if ( $netSyncTable->getRowCount() > 0 ) {
  echo "<li><a href=\"#netsync\">Network Sync</a></li>\n";
 }

if ( $rnsTable->getRowCount() > 0 ) {
  echo "<li><a href=\"#rnslist\">RNS List</a></li>\n";
 }
?>

  <li><a href="#memory">NEAD Memory Usage</a></li>
  <li><a href="#nodes">Total/Alive/Synced Nodes</a></li>
  <li><a href="#connDisc">Connection Status Events</a></li>
  <li><a href="#notif">Notification Handling</a></li>
  <li><a href="#syncStatus">Node Sync Status</a></li>
  <li><a href="#syncs">Ongoing Node Syncs</a></li>
</ul>


<?php
# Network Syncs
if ( $netSyncTable->getRowCount() > 0 ) {
  echo "<H1>Network Sync"; drawHelpLink("netsynchelp"); echo "</H1>\n";

  drawHelp("netsynchelp", "Network Sync",
      "
Metrics are taken from the FULL/DELTA SYNC SUCCESS log entires
<ul>
<li>Total #Num Syncs is the total number of FULL/DELTA SYNC SYNC</li>
<li>#Node Sync is the number of DISTINCT nodes</li>
<li>#MultiSync Nodes is the number of nodes which had more then one SYNC sync</li>
<li>Total MOs is the sum of the W fields in CDRWF</li>
<li>Total NE Wait Time is the time NE Readers spent waiting for the CS Writer to consume data (R from RWT)
<li>Total CS Wait Time is the time CS Writers spent waiting for data from NE (W from RWT)
<li>Raw MO/Sec is Total MOs/Duration</li>
<li>Adjusted MO/Sec is the rate when time spent waiting for the NE is excluded. It is calculated as the Raw rate * ( SUM(SyncTime) / SUM(SyncTime - (Time to Read MO List from Node + Time CS Writer Spent waiting for the Readers))</li>
<ul>
");

  echo $netSyncTable->toHTML();
}

# Total/Alive/Sync
$tableURL = "site=$site&dir=$dir&oss=$oss&file=cms/conn_disc_table.html";

echo "<H1><a name=\"connDisc\"></a>Connection Status Events"; drawHelpLink("connstathelp");
echo "</H1>\n";
drawHelp("connstathelp", "Connection Status Events",
    "A connectionStatus event occurs when NEAD connects or disconnects from a Node, for example when the node restarts.<br>
    This is a plot of the connectionStatus Events. Each position on the vertical axis represents a unqiue node. <br>
    Each point represents a time when NEAD connected or disconnected from a node. <br>
    If the graph shows a vertical line of points, this represents when a batch of nodes all connect/disconnect at the same time. <br>
    If the graph shows a horizontal line of points, this represents a single node that NEAD is repeatedly loosing/regaining connection with.<br>
");

echo "<p>Click <a href=$php_webroot/conn_disc.php?$tableURL>here</a> to see the nodes corresponding to the position on vertical axis.</p>\n";

$plotJSON = $plotdir . "/conn_disc.json";
$plotFile = $plotdir . "/conn_disc.txt";
if ( file_exists($plotJSON) ) {
    echo '<div id="conndisc" style="width: 800px; height: 400px "></div>' . "\n";    
    $sqlParam = array(
        'title' => "Connect/Disconnect Events",
        'type' => 'xy',
        'ylabel' => "Node",
        'useragg' => 'false',
        'persistent' => 'false',
        'seriesfile' => $plotJSON
    );    
    $jsPlot = new JsPlot();
    $jsPlot->show($sqlParam,'conndisc',NULL);
} else if ( file_exists($rootdir . "/cms/conn_disc.jpg") ) {
    if (file_exists($plotFile)) {
        echo "<a href=\"" . $graphBase . "conn_disc.txt\"><img src=\"$webroot/conn_disc.jpg\" alt=\"\"></a>\n";
    } else {
        echo "<img src=\"$webroot/conn_disc.jpg\" alt=\"\">\n";
    }
}

?>

<table 'border=0'>
    <tr> <td colspan="2">
            <h1><a name="nodes"/>Total/Alive/Synced Nodes</h1>
            <a href="<?=$qplotBase?>nodes"><img src="<?=$webroot?>/nead_Nodes.jpg" alt=""></a>
    </td> </tr>
    <tr>
        <td>
            <h1><a name="syncStatus"/>Node Sync Status</h1>
            <a href="<?=$qplotBase?>sync"><img src="<?=$webroot?>/sync.jpg" alt=""></a>
        </td>

        <td>
            <h1><a name="syncs"/>Ongoing Node Syncs</h1>
            <a href="<?=$qplotBase?>ongoing"><img src="<?=$webroot?>/nead_NumNodeSync.jpg" alt=""></a>
        </td>
    </tr>
    <tr>
    <tr>
        <td colspan="2"><h1><a name="notif"/>Notification Handling</h1>
        </td>
    </tr>
    <tr>
        <td>
            <p>This is a plot of the number of nofications in the  notification buffer</p>
            <a href="<?=$qplotBase?>notif_buf"><img src="<?=$webroot?>/notif_buf.jpg" alt=""></a>
        </td>

        <td>
            <p>This is a plot of the number of notifications received from nodes by in the last 30 seconds.</p>
            <a href="<?=$qplotBase?>notif_rec"><img src="<?=$webroot?>/notif_rec.jpg" alt=""></a>
        </td>
    </tr>
    <tr>
        <td>
            <p>Average Time for NEAD process a notification in milli- seconds</p>
            <a href="<?=$qplotBase?>notiftime"><img src="<?=$webroot?>/avgtime.jpg" alt=""></a>
        </td>

        <td>
            <p>Max Time for NEAD process a notification in milli- seconds</p>
            <a href="<?=$qplotBase?>notiftime"><img src="<?=$webroot?>/maxtime.jpg" alt=""></a>
        </td>
    </tr>

<?php
  if (file_exists($rootdir . "/notifnodes.jpg") ) {
?>
    <tr>
        <td>
            <a href="<?=$qplotBase?>notifnodes"><img src="<?=$webroot?>/notifnodes.jpg" alt=""></a>
        </td>
    </tr>
<?php
  }
?>

    <tr>
        <td>
            <h1><a name="threadpool"></a>Thread Pool</h1>
            <a href="<?=$qplotBase?>threadpool"><img src="<?=$webroot?>/threadpool.jpg" alt=""></a>
        </td>

<?php
  if (file_exists($rootdir . "/threadpool2.jpg") ) {
?>
        <td>
            <h1><a name="threadpool2"></a>Thread Pool 2</h1>
            <a href="<?=$qplotBase?>threadpool2"><img src="<?=$webroot?>/threadpool2.jpg" alt=""></a>
        </td>
<?php
  }
?>
    </tr>
    <tr>
        <td>
            <h1><a name="pingtime"></a>Ping Time</h1>
            <a href="<?=$qplotBase?>ping_time"><img src="<?=$webroot?>/ping_time.jpg" alt=""></a>
        </td>

        <td>
            <h1><a name="pingcount"></a>Ping Count</h1>
            <a href="<?=$qplotBase?>ping_count"><img src="<?=$webroot?>/ping_count.jpg" alt=""></a>
        </td>
    </tr>

<?php
  if (file_exists($rootdir . "/nesu.jpg") ) {
?>
    <tr>
        <td>
            <h1><a name="nesu"></a>Ping Thread Pool</h1>
            <a href="<?=$qplotBase?>nesu"><img src="<?=$webroot?>/nesu.jpg" alt=""></a>
        </td>
    </tr>
<?php
  }

  if (file_exists($rootdir . "/me_writes.jpg") ) {
?>
    <tr>
        <td colspan="2">
            <h1>MeContext Writes</h1>
        </td>
    </tr>
    <tr>
        <td>
            <h1><a name="nesu"></a>Max Writes Waiting</h1>
            <a href="<?=$qplotBase?>me_maxq"><img src="<?=$webroot?>/me_maxq.jpg" alt=""></a>
        </td>
        <td>
            <h1><a name="nesu"></a>Average Write Delay</h1>
            <a href="<?=$qplotBase?>me_avg_delay"><img src="<?=$webroot?>/me_avg_delay.jpg" alt=""></a>
        </td>
    </tr>
    <tr>
        <td>
            <h1><a name="nesu"></a>Number Of Writes Performed</h1>
            <a href="<?=$qplotBase?>me_writes"><img src="<?=$webroot?>/me_writes.jpg" alt=""></a>
        </td>
    </tr>

<?php
      }
?>

</table>

<h1><a name="memory"></a>NEAD Memory Usage</h1>
<?php
$plotFile = $plotdir . "/heap.txt";
if (file_exists($plotFile)) {
  echo "<a href=\"" . $graphBase . "heap.txt\"><img src=\"$webroot/nead_memory.jpg\" alt=\"\"></a>\n";
 }
 else {
   echo "<img src=\"$webroot/nead_memory\" alt=\"\">\n";
 }

#
# Print table of nodes per RNS if available
#
 if ( $rnsTable->getRowCount() ) {
   echo "<H1><a name=\"rnslist\">RNS List</H1>\n";
   echo "<p>The following table lists the number of nodes in each RNS. RANAGs (RXIs) are counted in a <b>fake</b> RNS called RANAG. The <b>Number of Nodes</b> is the sum of the RNC plus the RBSs in the RNS, so the number of RBSs in an RNS is <b>Number of Nodes - 1</b></p>\n";
   echo $rnsTable->toHTML();
 }

?>

<?php
  $statsDB->disconnect();
include "common/finalise.php";
?>
