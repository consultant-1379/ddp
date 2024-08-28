<?php
$pageTitle = "SNAD";

include "common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';

$webroot = $webroot . "/cms";
$rootdir = $rootdir . "/cms";

function graphUrl($title,$whatCols,$date) {
  $sqlParamWriter = new SqlPlotParam();
  $sqlParam =
      array( 'title'      => $title,
	     'ylabel'     => "",
	     'useragg'    => 'true',
	     'persistent' => 'false',
	     'querylist' =>
	     array(
		   array(
			 'timecol' => 'time',
			 'whatcol'    => $whatCols,
			 'tables'  => "snad_instr, sites",
			 'where'   => "snad_instr.siteid = sites.id AND sites.name = '%s'",
			 'qargs'   => array( 'site', )
			 )
		    )
	     );
  $id = $sqlParamWriter->saveParams($sqlParam);
  return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640,240);
}


function oneColGraph($col,$date) {
  return graphUrl($col,array($col=>$col),$date) ;
}

function newGraphs($statsDB,$site,$date) {
  echo "<H1 id=\"cache\">Cache Counters</H1>\n";
  $graphTable = new HTML_Table('border=0');
  $graphTable->addRow(array(oneColGraph("mastersInCache",$date)));
  $graphTable->addRow(array(oneColGraph("proxiesInCache",$date)));
  $graphTable->addRow(array(oneColGraph("mosInTempArea",$date)));
  $graphTable->addRow(array(oneColGraph("snRecoveryMoCount",$date)));
  $graphTable->addRow(array(oneColGraph("mosCheckedSoFar",$date)));
  echo $graphTable->toHTML();

  echo "<H1 id=\"recovery\">Recovery</H1>\n";
  echo graphUrl("Node Recovery", array('discoveredRncs' => 'Discovered', 'recoveringRncs' => 'Recovering' ), $date) . "\n";

  echo "<H1 id=\"notif\">Notifications</H1>\n";
  $graphTable = new HTML_Table('border=0');
  $graphTable->addRow(array(oneColGraph("totalNotificationsReceived",$date)));
  $sqlParamWriter = new SqlPlotParam();
  $sqlParam =
      array( 'title'      => "Notification Queuing",
	     'ylabel'     => "",
	     'type'       => 'sa',
	     'useragg'    => 'true',
	     'persistent' => 'false',
	     'querylist' =>
	     array(
		   array(
			 'timecol' => 'time',
			 'whatcol'    => array(
					       "sleepyNotificationQueueSize" => "Buffered",
					       "sleepyNotificationQueueInactiveSize" => "Inactive",
					       "sleepyNotificationQueueActiveSize" => "Active"
						),
			 'tables'  => "snad_instr, sites",
			 'where'   => "snad_instr.siteid = sites.id AND sites.name = '%s'",
			 'qargs'   => array( 'site', )
			 )
		    )
	     );
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true)));
  echo $graphTable->toHTML();
}

function oldGraphs() {
  global $webroot, $rootdir;

echo <<<EOS
<a name="mocounters"></a>
<h1>Cache Counters</h1>

<table>
<tr><td><img src="$webroot/snad_mastersInCache.jpg" alt=""></td></tr>
<tr><td><img src="$webroot/snad_proxiesInCache.jpg" alt=""></td></tr>
<tr><td><img src="$webroot/snad_mosInTempArea.jpg" alt=""></td></tr>
<tr><td><img src="$webroot/snad_mosToConsistencyCheck.jpg" alt=""></td></tr>
<tr><td><img src="$webroot/snad_snRecoveryMoCount.jpg" alt=""></td></tr>

</table>

<h1><a name="rnccounters"></a>RNC Recovery</h1>
<img src="$webroot/snad_rncCounters.jpg" alt="">

EOS;

  if ( file_exists($rootdir . "/snad_totalNotificationsReceived.jpg") ) {
      echo <<<EOT
<a name="notif"></a>
<h1>Notifications</h1>
<img src="$webroot/snad_totalNotificationsReceived.jpg" alt="">
<img src="$webroot/snad_sleepyNotificationQueueSize.jpg" alt="">
EOT;
  }

  if ( file_exists($rootdir . "/snad_state.html") ) {
    echo <<<EOS
<h1><a name="snrec"></a>State Change</h1>
<table border>
 <tr> <th>Time</th> <th>snRecoveryPhase</th> <th>isConCheckerRunning</th> </tr>
EOS;
    include($rootdir . "/snad_state.html");
    echo "</table>\n";
  } # End of file_exists($rootdir . "/snad_state.html" )
}

function getCCTable($statsDB,$site,$date) {
  $table = new HTML_Table('border=1');

  $headerCols = array( "Start Time", "Duration", "Status");

////
// BG 2011-12-15: OSS-RC 12 WP00558: CR 862/109 18-FCP 103 8147/13 A
// Check whether the status is given as true/false or as a percentage and display accordingly.
// Note: There is no 'MOs to Check' displayed when the status is presented as a percentage.
////
  $row = $statsDB->queryRow("
 SELECT COUNT(*)
  FROM snad_cc, sites
  WHERE snad_cc.siteid = sites.id AND sites.name = '$site' AND
   start BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
   snad_cc.pc_status IS NOT NULL");
  if ($row[0] > 0) {
    $sql = "
SELECT
  DATE_FORMAT(snad_cc.start,'%H:%i:%s') AS time, TIMEDIFF(snad_cc.end,snad_cc.start) as duration,
  snad_cc.pc_status, snad_cc.numchecked,
  snad_cc.consist, snad_cc.inconsist, snad_cc.missing, snad_cc.multiple,
  snad_cc.findmo_num, snad_cc.findmo_time, snad_cc.modifymo_num, snad_cc.modifymo_time,
  snad_cc.createmo_num, snad_cc.createmo_time, snad_cc.deletemo_num, snad_cc.deletemo_time,
  snad_cc.starttx_num, snad_cc.starttx_time, snad_cc.rollbacktx_num, snad_cc.rollbacktx_time,
  snad_cc.committx_num, snad_cc.committx_time
 FROM snad_cc, sites
 WHERE snad_cc.siteid = sites.id AND sites.name = '$site' AND
  snad_cc.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
  } else {
    $sql = "SELECT
   DATE_FORMAT(snad_cc.start,'%H:%i:%s') AS time, TIMEDIFF(snad_cc.end,snad_cc.start) as duration,
   snad_cc.status, snad_cc.numcheck, snad_cc.numchecked,
   snad_cc.consist, snad_cc.inconsist, snad_cc.missing, snad_cc.multiple,
   snad_cc.findmo_num, snad_cc.findmo_time, snad_cc.modifymo_num, snad_cc.modifymo_time,
   snad_cc.createmo_num, snad_cc.createmo_time, snad_cc.deletemo_num, snad_cc.deletemo_time,
   snad_cc.starttx_num, snad_cc.starttx_time, snad_cc.rollbacktx_num, snad_cc.rollbacktx_time,
   snad_cc.committx_num, snad_cc.committx_time
  FROM snad_cc, sites
  WHERE snad_cc.siteid = sites.id AND sites.name = '$site' AND
   snad_cc.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $headerCols[] = "MOs To Check";
  }

  $CS_OPS = array("FindMO","ModifyMO","CreateMO", "DeleteMO", "StartTX", "RollbackTX", "CommitTX");
  $headerCols = array_merge( $headerCols, array( "MOs Checked", "Consistent", "Inconsistent", "Missing Masters", "Multiple Masters") );
  foreach ($CS_OPS as $cs_op ) {
    $headerCols[] = "#" . $cs_op;
    $headerCols[] = $cs_op . " Time";
  }

  $table->addRow( $headerCols, null, "th" );

  $statsDB->query($sql);
  if ( $statsDB->getNumRows() > 0 ) {
    while($row = $statsDB->getNextRow()) {
      $table->addRow($row);
    }
  }

  return $table;
}


$statsDB = new StatsDB();

$row = $statsDB->queryRow("
SELECT COUNT(*) FROM snad_instr,sites
WHERE
 snad_instr.siteid = sites.id AND sites.name = '$site' AND
 snad_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
if ( $row[0] > 0 ) {
echo <<<EOS
<ul>
 <li><a href="#cache">Cache Counters</a></li>
 <li><a href="#recovery">Recovery</a></li>
 <li><a href="#notif">Notifications</a></li>
 <li><a href="#mem">Memory</a></li>
 <li><a href="#cc">Consistency Checks</a></li>
<ul>
EOS;
  newGraphs($statsDB,$site,$date);
} else {
  $notifLink = "";
  if ( file_exists($rootdir . "/snad_totalNotificationsReceived.jpg") ) {
    $notifLink = "<li><a href=\"#notif\">Notifications</a></li>\n";
  }
  echo <<<EOS
<ul>
 <li><a href="#mocounters">Cache Counters</a></li>
 <li><a href="#rnccounters">RNC Recovery</a></li>
 <li><a href="#mem">Memory Usage</a></li>
 $notifLink
 <li><a href="#snrec">State Change</a></li>
 <li><a href="#cc">Consistency Checks</a></li>
</ul>
EOS;
  oldGraphs($statsDB,$site,$date);
}

if ( file_exists($rootdir . "/snad_heap.jpg") ) {
  echo "<h1 id=\"mem\">Memory Usage</h1>\n";
  echo "<img src=\"$webroot/snad_heap.jpg\">\n";
}

$ccTable = getCCTable($statsDB,$site,$date);
if ( $ccTable->getRowCount() > 1 ) {
  echo "<H1 id=\"cc\">Consistency Checks</H1>\n";
  echo $ccTable->toHTML();
}

$statsDB->disconnect();

include "common/finalise.php";
?>

