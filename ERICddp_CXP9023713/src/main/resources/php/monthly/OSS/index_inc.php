<?php
$rootMo=$_GET["root"];

$args = "site=" . $site . "&start=" . $startdate . "&end=" . $enddate . "&year=" . $year . "&month=" . $month . "&oss=" . $oss;
?>

<H1>OSS Statistics</H1>
<ul>
 <li><a href="./restarts.php?<?=$args?>">Managed Component/Server restarts</a></li>
 <li><a href="./process.php?<?=$args?>">Process</a></li>
 <li><a href="./pms.php?<?=$args?>">PMS</a></li>
 <li><a href="./nead.php?<?=$args?>">NEAD</a></li>
 <li><a href="./export.php?<?=$args?>&root=<?=$rootMo?>">Bulk CM Exports</a></li>
 <li><a href="./alarm.php?<?=$args?>">Alarms</a></li>
 <li><a href="../sybase_usage.php?<?=$args?>">Sybase Usage</a></li>

<?php
/* Check for Command Log */
$sqlquery = "SELECT count(*) from cmds,sites WHERE cmds.siteid = sites.id AND sites.name = '$site' AND cmds.date >= \"$startdate\" AND cmds.date <= \"$enddate\"";

$statsDB->query($sqlquery);
$row = $statsDB->getNextRow();
if ($row[0] > 0) { 
  echo "<li><a href=\"../cmdlog.php?" . $args . "\">Command Log Summary</a></li>\n"; 
 }

/* Check for SDM */
$sqlquery = "SELECT count(*) from sdmu_perf,sites WHERE sdmu_perf.siteid = sites.id AND sites.name = '$site' AND sdmu_perf.date >= \"$startdate\" AND sdmu_perf.date <= \"$enddate\"";
$statsDB->query($sqlquery);
$row = $statsDB->getNextRow();
if ($row[0] > 0) { 
  echo "<li><a href=\"./sdm.php?" . $args . "\">SDM</a></li>\n"; 
 }

echo "<li><a href=\"../smo_jobs.php?" . $args . "\">SMO</a></li>\n";


# WRAN RBS Rehome
$row = $statsDB->queryRow("
SELECT COUNT(*) 
 FROM wran_rbs_reparent, sites 
 WHERE wran_rbs_reparent.siteid = sites.id AND sites.name = '$site' AND
       wran_rbs_reparent.date BETWEEN '$startdate' AND '$enddate'");
if ( $row[0] > 0 ) {
  echo "<li> <a href=\"./rehome.php?" . $args . "\">WRAN RBS Rehome</a> </li>\n";
 }

echo "</ul>\n";
?>
