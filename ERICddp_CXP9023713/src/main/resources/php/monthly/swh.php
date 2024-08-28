<?php
$pageTitle = "Software Handling";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>
<h1>Software Handling Stats</h1>

<H2>Activity Totals</H2>
<table cellpadding="2" cellspacing="2" border="1">
  <tr> <th>Type</th> <th>Count</th> <th>Successful</th> <th>Failed</th> <th>Indeterminate</th> <th>Total Nodes</th> <th>Successful Nodes</th> <th>Failed Nodes</th> <th>Indeterminate Nodes</th><tr>

<?php
$sqlquery="SELECT type, SUM(total), SUM(success), SUM(failed), SUM(indeterminate), SUM(netotal), SUM(nesuccess), SUM(nefailed), SUM(neindeterminate) FROM swh_activities,sites WHERE sites.name = '$site' AND sites.id = swh_activities.siteid AND swh_activities.date >= \"$startdate\" AND swh_activities.date <= \"$enddate\" GROUP BY type";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

while($row = $statsDB->getNextRow()) {
  echo " <tr> <td>$row[0]</td>  <td>$row[1]</td> <td>$row[2]</td> <td>$row[3]</td>  <td>$row[4]</td> <td>$row[5]</td> <td>$row[6]</td>  <td>$row[7]</td> <td>$row[8]</td> </tr>\n";
}
echo "</table>\n";

?>


<H2>Failure Causes</H2>
<?php
$currType="";
$sqlquery="SELECT SUM(count) as counttotal, acttype, swh_failreason.name FROM swh_nefailures,swh_failreason,sites WHERE sites.name = '$site' AND sites.id = swh_nefailures.siteid AND date > \"$startdate\" AND date <= \"$enddate\" AND swh_nefailures.failreason = swh_failreason.id GROUP BY failreason, acttype ORDER BY acttype,counttotal DESC";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

while($row = $statsDB->getNextRow()) {
  if ( $row[1] != $currType ) {
    if ( $currType != "" ) {
      echo "</table>\n";
    }

    $currType = $row[1];    
    echo "<H3>$currType</H3>\n";
    echo "<table cellpadding=\"2\" cellspacing=\"2\" border=\"1\">\n";
    echo "<tr> <th>Count</th> <th>Reason</th> <tr>\n";
  }

  $reason = $row[2];
  if ( $reason == "" ) {
    $reason = "Unknown";
  }
   
  echo " <tr> <td>$row[0]</td>  <td>$reason</td>  </tr>\n";
}

if ( $currType != "" ) {
  echo "</table>\n";
}

?>


<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
