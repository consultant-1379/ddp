<?php
$pageTitle = "Process Statistics";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>
<h1>Process Statistics</h1>
<h2>Monthly Average</h2>

<?php

$request= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

if ( isset($_GET["host"]) ) {
  $host = $_GET["$host"];
  $sqlquery = "
SELECT servers.id FROM servers,sites
 WHERE
  servers.siteid = sites.id AND
  servers.hostname = '$host' AND
  sites.name = '$site'";
 } else {
  $sqlquery = "
SELECT servers.id FROM servers,sites
 WHERE
  servers.siteid = sites.id AND
  servers.type = 'MASTER' AND
  sites.name = '$site'";
 }
$row = $statsDB->queryRow($sqlquery);
$serverId = $row[0];

//
// Table
//
if ( isset($_GET["procid"]) ) {
  $processid = $_GET["procid"];

  $filebase=tempnam($web_temp_dir,"month_process");
  $handle=fopen($filebase,"w");

  $sqlquery= "
SELECT DATE_FORMAT(proc_stats.time, '%Y-%m-%d:%H:%i:%s'), proc_stats.cpu, proc_stats.mem, proc_stats.thr, proc_stats.fd
 FROM proc_stats
 WHERE
  proc_stats.serverid = $serverId AND
  proc_stats.time BETWEEN \"$startdate 00:00:00\" AND \"$enddate 23:59:59\" AND
  proc_stats.procid = $processid
 ORDER BY proc_stats.time";
  if ($debug) { echo "<p>$sqlquery</p>"; }
  $statsDB->query($sqlquery);

  while($row = $statsDB->getNextRow()) {
    fwrite($handle, "$row[0] $row[1] $row[2] $row[3] $row[4]\n");
  }
  fclose($handle);
  $handle = popen($gnuPlot, "w");
  fwrite($handle, "set terminal jpeg\n");
  fwrite($handle, "set xdata time\n");
  fwrite($handle, "set timefmt \"%Y-%m-%d %H:%M:%S\"\n");
  fwrite($handle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
  fwrite($handle, "set format x \"%d\"\n");

  fwrite($handle, "set output \"" . $filebase . "_cpu.jpg\"\n");
  fwrite($handle, "set title \"CPU (mins)\"\n");
  fwrite($handle, "plot \"$filebase\" using 1:2 notitle with steps\n");

  fwrite($handle, "set output \"" . $filebase . "_mem.jpg\"\n");
  fwrite($handle, "set title \"Memory (MB)\"\n");
  fwrite($handle, "plot \"$filebase\" using 1:3 notitle with steps\n");

  fwrite($handle, "set output \"" . $filebase . "_thr.jpg\"\n");
  fwrite($handle, "set title \"Threads\"\n");
  fwrite($handle, "plot \"$filebase\" using 1:4 notitle with steps\n");

  fwrite($handle, "set output \"" . $filebase . "_fd.jpg\"\n");
  fwrite($handle, "set title \"File Descriptors\"\n");
  fwrite($handle, "plot \"$filebase\" using 1:5 notitle with steps\n");

  pclose($handle);

  $imgBase="/temp/" . basename($filebase);
  echo "<p><img src=\"$imgBase" . "_cpu.jpg\" alt=\"\"></p>\n";
  echo "<p><img src=\"$imgBase" . "_mem.jpg\" alt=\"\"></p>\n";
  echo "<p><img src=\"$imgBase" . "_thr.jpg\" alt=\"\"></p>\n";
  echo "<p><img src=\"$imgBase" . "_fd.jpg\" alt=\"\"></p>\n";
}
else
{
  echo "<table border><tr><th>Process</th> <th>CPU Time</th> <th>Memory (MB)</th> <th>Threads</th> <th>File Descriptors</th> </tr>\n";
  $sqlquery= "
SELECT process_names.name, proc_stats.procid,
  ROUND(AVG(proc_stats.cpu)) AS avg_cpu, ROUND(AVG(proc_stats.mem)),
  ROUND(AVG(proc_stats.thr)), ROUND(AVG(proc_stats.fd))
 FROM proc_stats,process_names
 WHERE
  proc_stats.serverid = $serverId AND
  proc_stats.procid = process_names.id AND
  proc_stats.time BETWEEN \"$startdate 00:00:00\" AND \"$enddate 23:59:59\"
 GROUP BY proc_stats.procid
 ORDER BY avg_cpu DESC";
  if ($debug) {
    echo "<p>$sqlquery</p>";
  }
  $statsDB->query($sqlquery);

  while($row = $statsDB->getNextRow()) {
    echo "<tr> <td><a href=\"" . $request . "&procid=$row[1]\">$row[0]</a></td> <td>$row[2]</td> <td>$row[3]</td> <td>$row[4]</td> <td>$row[5]</td> </tr>\n";
  }
  echo "</table>\n";
}
?>




<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
