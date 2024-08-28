<?php
$pageTitle = "PMS File Collection";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>
<h1>PMS File Collection</h1>

<H3>Daily Average for Month</H3>
<table border>

<?php
$sqlquery = "CREATE TEMPORARY TABLE tmp.stats 
(
	date DATE NOT NULL,
	collected INT UNSIGNED NOT NULL,
	available INT UNSIGNED NOT NULL,
	avgroptime SMALLINT UNSIGNED NOT NULL,
	maxroptime SMALLINT UNSIGNED NOT NULL,
	uetr INT UNSIGNED NOT NULL,
	ctr INT  UNSIGNED NOT NULL,
	gpeh INT UNSIGNED NOT NULL,
	datavol INT UNSIGNED
)";
$statsDB->exec($sqlquery);

$sqlquery = "INSERT INTO tmp.stats SELECT date, 
   ( (IFNULL(pms_stats.rncavail,0) + pms_stats.rbsavail + IFNULL(pms_stats.rxiavail,0)) - (IFNULL(pms_stats.rncmiss,0) + pms_stats.rbsmiss + IFNULL(pms_stats.rximiss,0)) ) as collected, 
   ( IFNULL(pms_stats.rncavail,0) + pms_stats.rbsavail + IFNULL(pms_stats.rxiavail,0) ) as available, 
   avgroptime, maxroptime, uetr, ctr, gpeh, datavol 
FROM pms_stats,sites WHERE sites.name = \"$site\" AND sites.id = pms_stats.siteid AND pms_stats.date >= \"$startdate\" AND pms_stats.date <= \"$enddate\" ORDER BY date";
if ($debug) {
  echo "<p>$sqlquery</p>";
}

$statsDB->exec($sqlquery);

//
// Table
// 

$sqlquery="SELECT ROUND(AVG(collected)), ROUND(AVG(available)), ROUND(AVG((collected/available)*100)), ROUND(AVG(avgroptime)), ROUND(AVG(uetr)), ROUND(AVG(ctr)), ROUND(AVG(gpeh)) FROM tmp.stats";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

$row = $statsDB->getNextRow();
echo "<tr> <td>Number of stats files collected</td> <td>$row[0]</td> </tr>\n";
echo "<tr> <td>Number of stats files available</td> <td>$row[1]</td> </tr>\n";
echo "<tr> <td>Successful collection ratio (%)</td> <td>$row[2]</td> </tr>\n";

echo "<tr> <td>Number of UETR files collected</td> <td>$row[4]</td> </tr>\n";
echo "<tr> <td>Number of CTR files collected</td> <td>$row[5]</td> </tr>\n";
echo "<tr> <td>Number of GPEH files collected</td> <td>$row[6]</td> </tr>\n";

echo "<tr> <td>Time to collect files in a ROP (secs)</td> <td>$row[3]</td> </tr>\n";
echo "</table>\n";


//
// Graphs
//
$sqlquery="
SELECT date, collected, available,   ROUND((collected/available)*100), avgroptime, maxroptime, uetr, ctr, gpeh, datavol FROM tmp.stats ORDER BY date";
if ($debug) {
  echo "<p>$sqlquery</p>";
}

$filebase=tempnam($web_temp_dir,"month_pms");
$handle=fopen($filebase,"w");
$statsDB->query($sqlquery);

while($row = $statsDB->getNextRow()) {
  fwrite($handle, "$row[0] $row[1] $row[2] $row[3] $row[4] $row[5] $row[6] $row[7] $row[8] $row[9]\n");
}
fclose($handle);

$handle = popen($gnuPlot, "w");
#$handle = popen("cat > /tmp/log", "w");

fwrite($handle, "set terminal jpeg\n");
fwrite($handle, "set xdata time\n");
fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
fwrite($handle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
#fwrite($handle, "set yrange [ 0: ]\n");
fwrite($handle, "set format x \"%d\"\n");
fwrite($handle, "set key under\n");

fwrite($handle, "set output \"" . $filebase . "_files.jpg\"\n");
fwrite($handle, "set title \"Number of stats files\"\n");
fwrite($handle, "plot \"$filebase\" using 1:2 title 'Collected' with steps, \"$filebase\" using 1:3 title 'Available' with steps\n");

fwrite($handle, "set output \"" . $filebase . "_times.jpg\"\n");
fwrite($handle, "set title \"Time to collection files in a ROP(sec)\"\n");
fwrite($handle, "plot \"$filebase\" using 1:5 title 'Average' with steps, \"$filebase\" using 1:6 title 'Max' with steps\n");

fwrite($handle, "set output \"" . $filebase . "_binary.jpg\"\n");
fwrite($handle, "set title \"Number of Binary files collected\"\n");
fwrite($handle, "plot \"$filebase\" using 1:7 title 'UETR' with steps, \"$filebase\" using 1:8 title 'CTR' with steps, \"$filebase\" using 1:9 title 'GPEH' with steps\n");

fwrite($handle, "set output \"" . $filebase . "_datavol.jpg\"\n");
fwrite($handle, "set title \"Data volume collected\"\n");
fwrite($handle, "plot \"$filebase\" using 1:10 notitle with steps\n");


fwrite($handle, "set yrange [ :101 ]\n");
fwrite($handle, "set output \"" . $filebase . "_percentage.jpg\"\n");
fwrite($handle, "set title \"Success collection (%)\"\n");
fwrite($handle, "plot \"$filebase\" using 1:4 notitle with steps\n");


pclose($handle);

$imgBase="/temp/" . basename($filebase);

echo "<ul>\n";
echo "<li><a href=\"#statsfiles\">Number of stats files</a></li>\n";
echo "<li><a href=\"#statsper\">Successful collection ration for stats files</a></li>\n";
echo "<li><a href=\"#times\">File Collection Times</a></li>\n";
echo "<li><a href=\"#binaryfiles\">Number of binary files</a></li>\n";
echo "<li><a href=\"#datavol\">Data volume collected</a></li>\n";
echo "</ul>\n";

echo "<p><a name=\"statsfiles\"><img src=\"$imgBase" . "_files.jpg\" alt=\"\"></p>\n";
echo "<p><a name=\"statsper\"><img src=\"$imgBase" . "_percentage.jpg\" alt=\"\"></p>\n";
echo "<p><a name=\"times\"><img src=\"$imgBase" . "_times.jpg\" alt=\"\"></p>\n";
echo "<p><a name=\"binaryfiles\"><img src=\"$imgBase" . "_binary.jpg\" alt=\"\"></p>\n";
echo "<p><a name=\"datavol\"><img src=\"$imgBase" . "_datavol.jpg\" alt=\"\"></p>\n";
?>


<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
