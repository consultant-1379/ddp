<?php
$pageTitle = "SDM Stats";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>
<h1>SDM Statistics</h1>

<?php
$sqlquery = "SELECT SEC_TO_TIME(AVG(sdmu_perf.time_load)), SEC_TO_TIME(AVG(sdmu_perf.time_agg)), SEC_TO_TIME(AVG(sdmu_perf.time_del)) FROM sdmu_perf, sites WHERE sdmu_perf.siteid = sites.id AND sites.name = '$site' AND sdmu_perf.date >= \"$startdate\" AND sdmu_perf.date <= \"$enddate\"";
if ($debug) { echo "<p>$sqlquery</p>"; }
$statsDB->query($sqlquery);
$row = $statsDB->getNextRow();
?>

<H2>DB operations</H2>

<H3>Monthly Average</H3>
 <table border>
  <tr> <th>Operation</th> <th>Time</th> </tr>
  <tr> <td>Loading</th> <td><?=$row[0]?></td> </tr>
  <tr> <td>Aggregation</th> <td><?=$row[1]?></td> </tr>
  <tr> <td>Deletion</th> <td><?=$row[2]?></td> </tr>
 </table>

<?php

$sqlquery = "SELECT sdmu_perf.date, sdmu_perf.time_load/3600, sdmu_perf.time_agg/3600, sdmu_perf.time_del/3600 FROM sdmu_perf, sites WHERE sdmu_perf.siteid = sites.id AND sites.name = '$site' AND sdmu_perf.date >= \"$startdate\" AND sdmu_perf.date <= \"$enddate\" ORDER BY sdmu_perf.date";
if ($debug) { echo "<p>$sqlquery</p>"; }
$statsDB->query($sqlquery);

$filebase=tempnam($web_temp_dir,"sdmu_perf");
$handle=fopen($filebase,"w");
while($row = $statsDB->getNextRow()) {
  if ($debug) { echo "<pre> $row[0] $row[1] $row[2] $row[3]</pre>\n"; }
  fwrite($handle, "$row[0] $row[1] $row[2] $row[3]\n");
}
fclose($handle);

//$handle = popen("cat > /tmp/log", "w");
$handle = popen($gnuPlot, "w");
fwrite($handle, "set terminal jpeg\n");
fwrite($handle, "set xdata time\n");
fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
fwrite($handle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
fwrite($handle, "set format x \"%d\"\n");
fwrite($handle, "set size 1,0.5\n");


fwrite($handle, "set output \"" . $filebase . "_load.jpg\"\n");
fwrite($handle, "set title \"Loading\"\n");
fwrite($handle, "plot \"$filebase\" using 1:2 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_agg.jpg\"\n");
fwrite($handle, "set title \"Aggregation\"\n");
fwrite($handle, "plot \"$filebase\" using 1:3 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_del.jpg\"\n");
fwrite($handle, "set title \"Deletion\"\n");
fwrite($handle, "plot \"$filebase\" using 1:4 notitle with steps\n");

pclose($handle);

$img=basename($filebase);
?>

<H3>Loading</H3>
<img src="/temp/<?=$img?>_load.jpg" alt="">

<H3>Aggregation</H3>
<img src="/temp/<?=$img?>_agg.jpg" alt="">

<H3>Deletion</H3>
<img src="/temp/<?=$img?>_del.jpg" alt="">

<H2>Parser</H2>

<?php
$sqlquery = "SELECT ROUND(AVG(sdmu_parser.xmlfiles)), ROUND(AVG(sdmu_parser.bcpfiles)), ROUND(AVG(sdmu_parser.bcpvolume)/1024), SEC_TO_TIME(AVG(sdmu_parser.time_parse)), SEC_TO_TIME(AVG(sdmu_parser.time_map)), SEC_TO_TIME(AVG(sdmu_parser.time_write)), SEC_TO_TIME(AVG(sdmu_parser.time_bat)) FROM sdmu_parser, sites WHERE sdmu_parser.siteid = sites.id AND sites.name = '$site' AND sdmu_parser.date >= \"$startdate\" AND sdmu_parser.date <= \"$enddate\"";
if ($debug) { echo "<p>$sqlquery</p>"; }
$statsDB->query($sqlquery);

$row = $statsDB->getNextRow();
?>

<H3>Monthly Average</H3>
 <table border>
  <tr> <td>XML files parsed</th> <td><?=$row[0]?></td> </tr>
  <tr> <td>BCP files writen</th> <td><?=$row[1]?></td> </tr>
<tr> <td>BCP data written (MB)</th> <td><?=$row[2]?></td> </tr>
  <tr> <td>Time Parsing</th> <td><?=$row[3]?></td> </tr>
  <tr> <td>Time Mapping</th> <td><?=$row[4]?></td> </tr>
  <tr> <td>Time Writing</th> <td><?=$row[5]?></td> </tr>
  <tr> <td>Time BAT</th> <td><?=$row[6]?></td> </tr>

 </table>

<?php
$sqlquery = "SELECT sdmu_parser.date, sdmu_parser.xmlfiles, sdmu_parser.bcpfiles, ROUND(sdmu_parser.bcpvolume/1024), (sdmu_parser.time_parse/3600), (sdmu_parser.time_map/3600), (sdmu_parser.time_write/3600), (sdmu_parser.time_bat/3600) FROM sdmu_parser, sites WHERE sdmu_parser.siteid = sites.id AND sites.name = '$site' AND sdmu_parser.date >= \"$startdate\" AND sdmu_parser.date <= \"$enddate\" order by date";
if ($debug) { echo "<p>$sqlquery</p>"; }
$statsDB->query($sqlquery);

$filebase=tempnam($web_temp_dir,"sdmu_parser");
$handle=fopen($filebase,"w");
while($row = $statsDB->getNextRow()) {
  if ($debug) { echo "<pre> $row[0] $row[1] $row[2] $row[3] $row[4] $row[5] $row[6] $row[7]/pre>\n"; }
  fwrite($handle, "$row[0] $row[1] $row[2] $row[3] $row[4] $row[5] $row[6] $row[7]\n");
}
fclose($handle);

//$handle = popen("cat > /tmp/log", "w");
$handle = popen($gnuPlot, "w");
fwrite($handle, "set terminal jpeg\n");
fwrite($handle, "set xdata time\n");
fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
fwrite($handle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
fwrite($handle, "set format x \"%d\"\n");
fwrite($handle, "set size 1,0.5\n");


fwrite($handle, "set output \"" . $filebase . "_xmlfiles.jpg\n");
fwrite($handle, "set title \"XML Files\"\n");
fwrite($handle, "plot \"$filebase\" using 1:2 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_bcpfiles.jpg\"\n");
fwrite($handle, "set title \"BCP Files\"\n");
fwrite($handle, "plot \"$filebase\" using 1:3 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_bcpvolume.jpg\"\n");
fwrite($handle, "set title \"BCP Data (MB)\"\n");
fwrite($handle, "plot \"$filebase\" using 1:4 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_timeparse.jpg\"\n");
fwrite($handle, "set title \"Parse Time\"\n");
fwrite($handle, "plot \"$filebase\" using 1:5 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_timemap.jpg\"\n");
fwrite($handle, "set title \"Map Time\"\n");
fwrite($handle, "plot \"$filebase\" using 1:6 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_timewrite.jpg\"\n");
fwrite($handle, "set title \"Write Time\"\n");
fwrite($handle, "plot \"$filebase\" using 1:7 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_timebat.jpg\"\n");
fwrite($handle, "set title \"BAT Time\"\n");
fwrite($handle, "plot \"$filebase\" using 1:8 notitle with steps\n");

pclose($handle);

$img=basename($filebase);
?>

<H3>XML Files</H3>
<img src="/temp/<?=$img?>_xmlfiles.jpg" alt="">

<H3>BCP Files</H3>
<img src="/temp/<?=$img?>_bcpfiles.jpg" alt="">

<H3>BCP Volume</H3>
<img src="/temp/<?=$img?>_bcpvolume.jpg" alt="">

<H3>Parse Time</H3>
<img src="/temp/<?=$img?>_timeparse.jpg" alt="">

<H3>Map Time</H3>
<img src="/temp/<?=$img?>_timemap.jpg" alt="">

<H3>Write Time</H3>
<img src="/temp/<?=$img?>_timewrite.jpg" alt="">

<H3>BAT Time</H3>
<img src="/temp/<?=$img?>_timebat.jpg" alt="">


<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
