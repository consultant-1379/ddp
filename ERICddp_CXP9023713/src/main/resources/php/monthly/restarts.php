<?php
$pageTitle = "MC / Server Restarts";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>
<h1>Restarts</h1>

<h2>ManagedComponent Restarts</h2>
<p>The graph below shows the Managed Components (MC) which
restarted each day. The delta between a point and the point below
it is the number of times during the day that the MC restarted</p>
<?php
$filebase=tempnam($web_temp_dir,"month_mc");
$img=basename($filebase) . "_restarts.jpg";
$cmd= $ddp_dir . "/analysis/server/plotMonthlyRestarts $site $startdate $enddate $filebase";
if ($debug) { echo "<p>cmd=$cmd</p>\n"; }
exec($cmd);
echo "<img src=\"/temp/$img\" alt=\"\">\n";
?>

<p>The table below shows the total number of restarts and total downtime per MC</p>
<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <th>Managed Component</th> <th>Restarts</th> <th>Downtime (HH:MM:SS)</th> <th>Restart Counts By Reason</th> <tr>
<?php

$restartByType = array();
$statsDB->query("
SELECT mc_restarts.nameid, mc_restart_types.type, COUNT(mc_restarts.typeid)
FROM mc_restart_types, mc_restarts, sites
WHERE
 mc_restarts.typeid = mc_restart_types.id AND
 sites.name = \"$site\" AND
 sites.id = mc_restarts.siteid AND
 mc_restarts.time BETWEEN \"$startdate 00:00:00\" AND \"$enddate 23:59:59\" AND
 mc_restarts.ind_warm_cold = 'COLD' AND
 mc_restart_types.type != 'SYSTEM_SHUTDOWN'
GROUP BY mc_restarts.nameid, mc_restarts.typeid");
$currId = -1;
while($row = $statsDB->getNextRow()) {
  if ( $row[0] != $currId ) {
    $restartByType[$row[0]] = array();
    $currId = $row[0];
  }
  $restartByType[$row[0]][$row[1]] = $row[2];
 }


$statsDB->query("
SELECT mc_restarts.nameid, mc_names.name,
  COUNT(mc_restarts.nameid) AS restart_count, SEC_TO_TIME(SUM(mc_restarts.duration))
FROM mc_names, mc_restarts, sites, mc_restart_types
WHERE
 mc_restarts.nameid = mc_names.id AND
 sites.name = \"$site\" AND
 sites.id = mc_restarts.siteid AND
 mc_restarts.time BETWEEN \"$startdate 00:00:00\" AND \"$enddate 23:59:59\" AND
 mc_restarts.ind_warm_cold = 'COLD' AND
 mc_restarts.typeid = mc_restart_types.id AND
 mc_restart_types.type != 'SYSTEM_SHUTDOWN'
GROUP BY mc_restarts.nameid
ORDER BY restart_count DESC");
while($row = $statsDB->getNextRow()) {
  echo "<tr> <td>$row[1]</td> <td>$row[2]</td> <td>$row[3]</td> <td>";
  foreach ( $restartByType[$row[0]] as $type => $count ) {
    echo "$type($count) ";
  }
  echo "</td> </tr>\n";
 }

?>
  </tbody>
</table>



<h2>Server Reboots</h2>
<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <th>Time</th> <tr>
<?php
    $statsDB->query("
SELECT server_reboots.time FROM server_reboots,sites, servers
WHERE sites.name = '$site' AND sites.id = servers.siteid AND
      servers.type = 'MASTER' AND
      server_reboots.serverid = servers.id AND
      server_reboots.time BETWEEN \"$startdate 00:00:00\" AND \"$enddate 23:59:59\"
ORDER BY server_reboots.time");
while($row = $statsDB->getNextRow()) {
  echo "<tr> <td>$row[0]</td> <tr>\n";
}
?>
</tbody>
</table>

<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
