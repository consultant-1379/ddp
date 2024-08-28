<?php
$pageTitle = "SDM Statistics";
include "common/init.php";

$statsDB = new StatsDB();

$sqlquery = "SELECT SEC_TO_TIME(sdmu_perf.time_load), SEC_TO_TIME(sdmu_perf.time_agg), SEC_TO_TIME(sdmu_perf.time_del) FROM sdmu_perf, sites WHERE sdmu_perf.siteid = sites.id AND sites.name = '$site' AND sdmu_perf.date = '$date'";
if ( $debug ) { echo "<p>sqlquery=$sqlquery</p>\n"; }

$statsDB->query($sqlquery);
$row = $statsDB->getNextRow();

if ( $debug ) { echo "<p>row=$row</p>\n"; }

?>
<h1>SDM Statistics</h1>
<H1>Totals</H1>
<table>
<tr>
<td>

 <table border>
  <tr> <th>Operation</th> <th>Time</th> </tr>
  <tr> <td>Loading</th> <td><?=$row[0]?></td> </tr>
  <tr> <td>Aggregation</th> <td><?=$row[1]?></td> </tr>
  <tr> <td>Deletion</th> <td><?=$row[2]?></td> </tr>
 </table>

</td>
<td>

<ul>
<li>The source of the data for Loading are the StatTimer/StatTimerSave tables. For each load cycle, the load of all the tables are started at the same time. When the load for a table has completed, there is a a <b>main</b> row in the StatTimer/StatTimerSave table giving the time taken to perform the load in msec, (<b>execInMs</b>). The duration of the load cycle is taken as duration of the slowest load. i.e. the largest <b>execInMs</b>.  The total duration for the day is the sum of the load cycle durations.</li>
<li>The source of the data for Aggregation and Deletion is the UTRAN_DB_PERFLOG table. In both cases, the analysis script calculates the total duration by totaling the number of seconds where any Aggregation/Deletion procedure was running</li>
</ul>

</td>
</tr>
</table>

<?php
$webroot = "/" . $oss . "/" . $site . "/analysis/" . $dir;
$plotdir = $datadir . "/sdm/plots";
$graphBase = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=sdm/plots/";

if (file_exists($rootdir . "/sdm/loaded_cells.jpg")) {
  echo "<h1>Cells Loaded</h1>\n";
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/loaded_cells.jpg\" alt=\"\" >\n";

  echo "<h1>Nodes Loaded</h1>\n";
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/loaded_nodes.jpg\" alt=\"\" >\n";
}

if (file_exists($rootdir . "/sdm/parser_queue.jpg")) {
  echo "<h1>Parser Queue</h1>\n";
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/parser_queue.jpg\" alt=\"\" >\n";
}

$waitingFile = $rootdir . "/sdm/rops_waiting.jpg";
if (file_exists($waitingFile)) {
  echo "<h2>ROPs waiting to be loaded</h2>\n";
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/rops_waiting.jpg\" alt=\"\" >\n";
}
?>

<a name="load"></a>
<h2>Loading</h2>
<p>The height of each bar indicates the number of nodes loaded. 
The width is the number of seconds taken to perform the load</p>
<?php
if (file_exists($plotdir . "/load.txt")) {
  echo "<a href=\"" . $graphBase . "load.txt\"><img src=\"$webroot/sdm/loadTime.jpg\" alt=\"\"></a>\n";
}
 else {
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/loadTime.jpg\" alt=\"\" >\n";
 }
?>

<a name="agg"></a>
<h2>Aggregation</h2>
<?php
if (file_exists($plotdir . "/aggdelay.txt")) {
  echo "<a href=\"" . $graphBase . "aggdelay.txt\"><img src=\"$webroot/sdm/aggTime.jpg\" alt=\"\"></a>\n";
}
 else {
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/aggTime.jpg\" alt=\"\" >\n";
 }
?>

<?php
if (file_exists($rootdir . "/sdm/aggCompleted.jpg")) {
  if (file_exists($plotdir . "/aggperf.txt")) {
    echo "<a href=\"" . $graphBase . "aggperf.txt\"><img src=\"$webroot/sdm/aggCompleted.jpg\" alt=\"\"></a>\n";
  }
  else {
    echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/aggCompleted.jpg\" alt=\"\" >\n";
  }
 }
?>

<a name="delete"></a>
<h2>Deletion</h2>
<img src="/<?=$oss?>/<?=$site?>/analysis/<?=$dir?>/sdm/deleteTime.jpg" alt="">


<?php
$optdiag = $rootdir . "/sdm/optdiag.html";
if (file_exists($optdiag)) {
  echo "<h2>Index Statistics</h2>\n";
  include($optdiag);
}

$table = $rootdir . "/sdm/load_table.html";
if (file_exists($table)) {
  echo "<h2>Loading By Table</h2>\n";
  include($table);
}

$table = $rootdir . "/sdm/proc_table.html";
if (file_exists($table)) {
  echo "<h2>Stored Procedure Executions</h2>\n";
  include($table);
}

if (file_exists($rootdir . "/sdm/parser_heap.jpg")) {
  echo "<h1>Parser Heap</h1>\n";
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdm/parser_heap.jpg\" alt=\"\" >\n";
}


?>



<?php
$statsDB->disconnect();
include "common/finalise.php";
?>
