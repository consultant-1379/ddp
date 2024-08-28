<?php
$pageTitle = "Bulk CM Exports";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>
<h1>Bulk CM Export Statistics</h1>
<h2>Monthly Average</h2>
<table border="1">
 <tr><th>Duration (HH:MM)</th> <th>Number Of MOs</th> </tr>

<?php

//
// Table
// 
$sqlquery = "SELECT sec_to_time(ROUND(AVG(unix_timestamp(end)-unix_timestamp(start)))), ROUND(AVG(export.numMo)) FROM export,sites where export.siteid = sites.id AND sites.name = '$site' AND export.root NOT LIKE '%,%' AND export.numMo > 0 AND export.start >= \"$startdate 00:00:00\" AND export.start <= \"$enddate 23:59:59\" GROUP BY export.siteid";

if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);
$row = $statsDB->getNextRow();
echo "<tr><td>$row[0]</td> <td>$row[1]</td></tr>\n";
?>

</table>

<?php
//
// Graph
//
$sqlquery = "SELECT start, ROUND((unix_timestamp(end)-unix_timestamp(start)) / 60), export.numMo FROM export,sites where export.siteid = sites.id AND sites.name = '$site' AND export.root NOT LIKE '%,%' AND export.numMo > 0 AND export.start >= \"$startdate 00:00:00\" AND export.start <= \"$enddate 23:59:59\" ORDER BY export.start";
if ($debug) {
  echo "<p>$sqlquery</p>";
}

$filebase=tempnam($web_temp_dir,"month_export");
$handle=fopen($filebase,"w");
$statsDB->query($sqlquery);

while($row = $statsDB->getNextRow()) {
  fwrite($handle, "$row[0] $row[1] $row[2]\n");
}
fclose($handle);

$handle = popen($gnuPlot, "w");
#$handle = popen("cat > /tmp/log", "w");

fwrite($handle, "set terminal jpeg\n");
fwrite($handle, "set xdata time\n");
fwrite($handle, "set timefmt \"%Y-%m-%d %H:%M:%S\"\n");
fwrite($handle, "set xrange [ \"$startdate 00:00:00\":\"$enddate 23:59:59\" ]\n");
fwrite($handle, "set format x \"%d\"\n");

fwrite($handle, "set output \"" . $filebase . "_time.jpg\"\n");
fwrite($handle, "set title \"Duration (mins)\"\n");
fwrite($handle, "plot \"$filebase\" using 1:3 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_nummo.jpg\"\n");
fwrite($handle, "set title \"Number of MOs exported\"\n");
fwrite($handle, "plot \"$filebase\" using 1:4 notitle with steps\n");

pclose($handle);

$imgBase="/temp/" . basename($filebase);
echo "<p><img src=\"$imgBase" . "_time.jpg\" alt=\"\"></p>\n";
echo "<p><img src=\"$imgBase" . "_nummo.jpg\" alt=\"\"></p>\n";

?>


<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
