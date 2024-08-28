<?php
$pageTitle = "NEAD Syncs";
include "common/init.php";

require_once 'HTML/Table.php';

function getSyncFailureTable($statsDB,$site,$date)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array('Node','Failure Type', 'Error', 'Number of failures'), null, 'th');

  $statsDB->query("
SELECT ne.name, ne_sync_failure_type.name, ne_sync_failure_error.name, ne_sync_failure.count
 FROM ne, ne_sync_failure_type, ne_sync_failure_error, ne_sync_failure, sites
 WHERE
  ne_sync_failure.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_failure.date = '$date' AND
  ne_sync_failure.neid = ne.id AND
  ne_sync_failure.typeid = ne_sync_failure_type.id AND
  ne_sync_failure.errorid = ne_sync_failure_error.id");
  while ( $row = $statsDB->getNextRow() ) {
    $table->addRow($row);
  }

  return $table;
}

function getSyncCountsTable($statsDB,$site,$date)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array('RNS,MeContext', 'Successful Syncs', 'Failed Syncs'), null, 'th');

  $statsDB->exec("
CREATE TEMPORARY TABLE tmp.sync_success
 SELECT ne_sync_success.neid as neid, COUNT(*) as total
  FROM ne_sync_success, sites
  WHERE ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
        ne_sync_success.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
  GROUP BY ne_sync_success.neid");
# Total up failures
  $statsDB->exec("
CREATE TEMPORARY TABLE tmp.sync_failure
 SELECT ne_sync_failure.neid as neid, SUM(ne_sync_failure.count) as total
  FROM ne_sync_failure, sites
  WHERE ne_sync_failure.siteid = sites.id AND sites.name = '$site' AND
        ne_sync_failure.date = '$date'
  GROUP BY ne_sync_failure.neid");
# Join success and failure together
  $statsDB->exec("
CREATE TEMPORARY TABLE tmp.all_syncs
 SELECT tmp.sync_success.neid as neid, tmp.sync_success.total as success, tmp.sync_failure.total as failed
  FROM tmp.sync_success
  LEFT JOIN tmp.sync_failure ON (tmp.sync_success.neid = tmp.sync_failure.neid)");
  $statsDB->exec("
INSERT INTO tmp.all_syncs
 SELECT  tmp.sync_failure.neid as neid, tmp.sync_success.total as success, tmp.sync_failure.total as failed
 FROM tmp.sync_failure
 LEFT JOIN tmp.sync_success ON (tmp.sync_failure.neid = tmp.sync_success.neid)
 WHERE tmp.sync_success.neid IS NULL
");

  $statsDB->query("
SELECT ne.name, tmp.all_syncs.success, tmp.all_syncs.failed
 FROM ne, tmp.all_syncs
 WHERE tmp.all_syncs.neid = ne.id
 ORDER BY ne.name");
  while ( $row = $statsDB->getNextRow() ) {
    $table->addRow($row);
  }

  return $table;
}

require_once PHP_ROOT . "/classes/DDPObject.class.php";
class SyncSuccessTbl extends DDPObject {
    var $cols = array(
        "time" => "Time",
        "rns" => 'RNS',
        "node" => 'Node',
        "total_time" => 'Total Time',
        'synctype' => 'Sync Type',
        "numMoCreate" => 'Num MOs Created',
        "numMoDelete" => 'Num MOs Deleted',
        "numMoRead" => 'Num MOs Attribute Read',
        "numMoWrite" => 'Num MOs Attribute Write',
        "timeMoCreate" => 'Time MOs Created',
        "timeMoDelete" => 'Time MOs Deleted',
        "timeMoRead" => 'Time MOs Attribute Read',
        "timeMoWrite" => 'Time MOs Attribute Write',
        "timeFind" => 'Time MOs Find',
        "timeOther" => 'Other Time',
        "timeReadMoMirror" => 'Time Read MOs from Mirror',
        "timeReadMoNe" => 'Time Read MOs from NE',
        "timeReadWaitQ" => 'Time Reader Waited for Q',
        "timeWriteWaitQ" => 'Time Writer Waited For Q',
        "numTx" => 'Num Tx Used',
        "gcDelta" => 'GC Delta',
	'ngc' => 'Number of GC Changes',
	'restarted' => 'Node Restarted'
    );

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");
    var $filter = "";

    function __construct($filter = "") {
        parent::__construct("sync_succ");
        $this->filter = $filter;
    }

    function getData() {
        global $site, $date;
        $sql = "SELECT DATE_FORMAT(endtime, '%H:%i:%s') AS time,
            rns.name AS rns, ne.name AS node, timeTotal AS total_time,
            IF(isdelta,'DELTA', 'FULL') AS synctype,
            numMoCreate, numMoDelete, numMoRead, numMoWrite,
            timeMoCreate, timeMoDelete, timeMoRead, timeMoWrite, timeFind, timeOther,
            timeReadMoMirror, timeReadMoNe,
            timeReadWaitQ, timeWriteWaitQ,
            numTx, gcDelta, ngc,
            IF(restart,'TRUE','FALSE') AS restarted
            FROM
            ne_sync_success, ne, rns, sites
            WHERE
            ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
            ne_sync_success.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ne_sync_success.neid = ne.id AND ne.rnsid = rns.id " . $this->filter;
        $this->populateData($sql);
        return $this->data;
    }
}

function getSyncSuccessTable($statsDB,$site,$date)
{
  $table = new HTML_Table('border=1');
  $statsDB->query("
SELECT
  DATE_FORMAT(endtime, '%H:%i:%s'), rns.name, ne.name, timeTotal,
  numMoCreate, numMoDelete, numMoRead, numMoWrite,
  timeMoCreate, timeMoDelete, timeMoRead, timeMoWrite,
  timeOther,
  timeReadMoMirror, timeReadMoNe,
  timeReadWaitQ, timeWriteWaitQ,
  numTx, gcDelta
 FROM
  ne_sync_success, ne, rns, sites
 WHERE
  ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_success.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  ne_sync_success.neid = ne.id AND ne.rnsid = rns.id");

  while($row = $statsDB->getNextRow() ) {
    $table->addRow($row);
  }

  return $table;
}


$rootdir = $rootdir . "/cms";
$statsDB = new StatsDB();

if (isset($_GET['format']) && $_GET['format'] == "xls") {
    $tbl = new SyncSuccessTbl();
    $excel = new ExcelWorkbook();
    $excel->addObject($tbl);
    $excel->write();
    exit;
}

?>

<ul>
<li><a href="?<?=$webargs?>&tbl=syncFaults#syncFaults">Sync Failures due to Node Faults</a></li>
<li><a href="?<?=$webargs?>&tbl=syncResults#syncResults">Sync Results by Node</a></li>
<li><a href="?<?=$webargs?>&tbl=syncStats#syncStats">Sync Success Stats</a></li>
</ul>

<?php
if (isset($_GET['tbl'])) {
    if ($_GET['tbl'] == "syncFaults" || $_GET['tbl'] == "syncResults" || $_GET['tbl'] == "syncStats") {
        $doTbl = $_GET['tbl'];
    } else {
        echo "<h1 class=error>Invalid table type</h1>\n";
    }
} else $doTbl = "syncFaults";

if ($doTbl == "syncFaults") {
?>
<h1><a name="syncFaults"></a>Sync Failures due to Node Faults</h1>
<p>This is a list of nodes that NEAD could not sync with due a problem on the node</p>

<?php
  if ( file_exists($rootdir . "/NodeSyncFailures_table.html") ) {
?>
<table border>
 <tr> <td><b>Node<b></td> <td><b>Failure Type</b></td> <td><b>Error</b></td> <td><b>Number of failures</b></td> </tr>
<?php
  include($rootdir . "/NodeSyncFailures_table.html");
  echo "</table>\n";
} else {
    echo getSyncFailureTable($statsDB,$site,$date)->toHTML();
  }

} // end if ($doTbl == "syncFaults")
else if ($doTbl == "syncResults") {
echo '<h1><a name="syncResults"></a>Sync Results by Node</h1>';
if ( file_exists($rootdir . "/SyncTotal_table.html") ) {
?>
<table border>
 <tr> <td><b>RNS,MeContext<b></td> <td><b>Successful Syncs</b></td> <td><b>Failed Syncs</b></td> </tr>
<?php
  include($rootdir . "/SyncTotal_table.html");
  echo "</table>\n";
} else {
  echo getSyncCountsTable($statsDB,$site,$date)->toHTML();
 }

} // end if ($doTbl == "syncResults")

else if ($doTbl == "syncStats") {
echo '<h1><a name="syncStats"></a>Sync Success Stats</h1>';
if ( file_exists($rootdir . "/SyncStats_table.html") ) {
?>
<table border="1">
 <tr> <td><b>Time<b></td> <td><b>Node</b></td>
      <td><b>Num MOs Created</b></td> <td><b>Num MOs Deleted</b></td> <td><b>Num MOs Attribute Read</b></td>
      <td><b>Num MOs Attribute Write</b></td> <td><b>Time MOs Created</b></td> <td><b>Time MOs Deleted</b></td>
      <td><b>Time MOs Attribute Read</b></td> <td><b>Time MOs Attribute Write</b></td>
      <td><b>Other Time</b></td> <td><b>Total Time</b></td>
      <td><b>Time Read MOs from Mirror</b></td> <td><b>Time Read MOs from NE</b></td>
      <td><b>Time Waiting Tx</b></td> <td><b>Num Tx Used</b></td>
      <td><b>Num Tx Rolled Back</b></td> <td><b>Num Deadlocks</b></td>
      <td><b>Num SNAD Calls</b></td> <td><b>Time SNAD Calls</b></td>
      <td><b>GC Delta</b></td>
 </tr>
<?php
 include($rootdir . "/SyncStats_table.html");
 echo "</table>\n";
} else {
  //echo getSyncSuccessTable($statsDB,$site,$date)->toHTML();
    $filter = "";
    if (isset($_GET['filter_node']) && $_GET['filter_node'] != "")
        $filter = $filter . " AND ne.name = '" . $statsDB->escape($_GET['filter_node']) . "'";
    if (isset($_GET['filter_rns']) && $_GET['filter_rns'] != "")
        $filter = $filter . " AND rns.name = '" . $statsDB->escape($_GET['filter_rns']) . "'";

    $tbl = new SyncSuccessTbl($filter);
    $tbl->getSortableHtmlTable();
   ?>
       <form name=syncfilter action="?<?=$_SERVER['PHP_SELF']?>" method=get>
<?php
    foreach ($_GET as $key => $val) {
        if ($key == "filter_node" || $key == "filter_rns" || $key == "submit") continue;
        echo "<input type=hidden name='" . $key . "' value='" . $val . "' />\n";
    }
?>
<h3>Filter:</h3>
Node: <input type=text name=filter_node value="<?=$_GET['filter_node']?>" /><br />
RNS: <input type=text name=filter_rns value="<?=$_GET['filter_rns']?>" /><br />
<input type=submit name=submit value="Submit ..." />
</form>
<?php
 }
} // end if ($doTbl == "syncStats")
$statsDB->disconnect();
include "common/finalise.php";

?>
