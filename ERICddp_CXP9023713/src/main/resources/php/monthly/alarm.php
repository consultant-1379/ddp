<?php
$pageTitle = "Alarm Stats";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$statsDB = new StatsDB();

?>

<H1>Alarm List</H1>
<H4>Monthly Average</H4>
<table cellpadding="2" cellspacing="2" border="1">
  <tr> <th>Number of Alarms</th> <th>Time Taken to read Alarms</th> <tr>

<?php
$sqlquery="SELECT ROUND(AVG(size)), ROUND(AVG(duration)) FROM getalarmlist,sites WHERE sites.name = '$site' AND sites.id = getalarmlist.siteid AND getalarmlist.date >= \"$startdate\" AND getalarmlist.date <= \"$enddate\"";
if ($debug) { echo "<p>$sqlquery</p>"; }

$statsDB->query($sqlquery);
$row = $statsDB->getNextRow();

echo " <tr> <td>$row[0]</td>  <td>$row[1]</td>  </tr>\n";
echo "</table>\n";

# Graphs
$sqlquery="SELECT date, size, duration FROM getalarmlist,sites WHERE sites.name = '$site' AND sites.id = getalarmlist.siteid AND getalarmlist.date >= \"$startdate\" AND getalarmlist.date <= \"$enddate\" ORDER BY date";
if ($debug) {echo "<p>$sqlquery</p>";}
$filebase=tempnam($web_temp_dir,"month_getalarmlist");
$handle=fopen($filebase,"w");
$statsDB->query($sqlquery);

while($row = $statsDB->getNextRow()) {
  fwrite($handle, "$row[0] $row[1] $row[2]\n");
}
fclose($handle);

//$handle = popen("cat > /tmp/log", "w");
$handle = popen($gnuPlot, "w");
fwrite($handle, "set terminal jpeg\n");
fwrite($handle, "set xdata time\n");
fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
fwrite($handle, "set xrange [ \"${year}-${month}-01\":\"${year}-${month}-${lastday}\" ]\n");
fwrite($handle, "set yrange [ 0: ]\n");
fwrite($handle, "set format x \"%d\"\n");

fwrite($handle, "set output \"" . $filebase . "_size.jpg\"\n");
fwrite($handle, "set title \"Number Of Alarms in List\"\n");
fwrite($handle, "plot \"$filebase\" using 1:2 notitle with steps\n");

fwrite($handle, "set output \"" . $filebase . "_duration.jpg\"\n");
fwrite($handle, "set title \"Time Taken to read alarm list (sec)\"\n");
fwrite($handle, "plot \"$filebase\" using 1:3 notitle with steps\n");

pclose($handle);

$img=basename($filebase);
echo "<p><img src=\"/temp/$img" . "_size.jpg\" alt=\"\"></p>\n";
echo "<p><img src=\"/temp/$img" . "_duration.jpg\" alt=\"\"></p>\n";

?>


<H1>Alarm Events</H1>
<H4>Monthly Average</H4>
<p>An alarm event corresponds to one notification send by FM/FMS on the OSS. For a single alarm you will have two or more events (1 new alarm event, 1 cleared alarm event and maybe an ack state change event)</p>
</p>Note: An active node is defined as a node that sent one or more alarm events.</p>
<table cellpadding="2" cellspacing="2" border="1">
 <tr><th>Node Type</th> 	<th>Total #Alarm Events Per day</th> <th>Average #Alarms Events per day per active node</th> 	<th>Average #Alarms Events per day Alive Nodes</th> </tr>
<?php
  $sqlquery = "
SELECT me_types.name,
       ROUND(AVG(alarmevents_by_metype.event_total)) AS total,
       ROUND(AVG(alarmevents_by_metype.event_total / alarmevents_by_metype.active)) AS per_active_node,
       ROUND(AVG(alarmevents_by_metype.event_total / alarmevents_by_metype.total)) AS per_alive_node
FROM alarmevents_by_metype, me_types, sites
WHERE alarmevents_by_metype.me_typeid = me_types.id AND
      alarmevents_by_metype.siteid = sites.id AND sites.name = '$site'
      AND alarmevents_by_metype.date >= '$startdate' AND alarmevents_by_metype.date <= '$enddate'
GROUP BY alarmevents_by_metype.me_typeid";

#$sqlquery="SELECT ROUND(AVG(ranagevent)), ROUND(AVG(ranagact)), ROUND(AVG(rncevent)), ROUND(AVG(rncact)), ROUND(AVG(rbsevent)), ROUND(AVG(rbsact)) FROM alarmevents,sites WHERE sites.name = '$site' AND sites.id = alarmevents.siteid AND alarmevents.date >= \"$startdate\" AND alarmevents.date <= \"$enddate\"";
if ($debug) { echo "<p>$sqlquery</p>"; }
$statsDB->query($sqlquery);

$meTypes = array();
$index = 0;

while($row = $statsDB->getNextRow()) {
  echo "<tr> <td>$row[0]</td> <td>$row[1]</td> <td>$row[2]</td> <td>$row[3]</td> ";

  $meTypes[$index] = $row[0];
  $index++;
 }

echo "</table>\n";

#
# Create a data file containing the counts per node type per day
#
$filebase=tempnam($web_temp_dir,"month_alarmevent");
$handle=fopen($filebase,"w");

foreach ($meTypes as $meType) {
  $sqlquery="
SELECT alarmevents_by_metype.date,
       alarmevents_by_metype.event_total
FROM alarmevents_by_metype, me_types, sites
WHERE sites.name = '$site' AND sites.id = alarmevents_by_metype.siteid AND
      alarmevents_by_metype.date >= \"$startdate\" AND alarmevents_by_metype.date <= \"$enddate\" AND
      alarmevents_by_metype.me_typeid = me_types.id AND me_types.name = \"$meType\"
ORDER BY date";
  if ($debug) {echo "<p>$sqlquery</p>";}
  $statsDB->query($sqlquery);

  while($row = $statsDB->getNextRow()) {
    fwrite($handle, "$row[0] $row[1]\n");
  }
  fwrite($handle, "\n");
  fwrite($handle, "\n");
}
fclose($handle);

#
# Plot the above data
#
$img=basename($filebase);
$index = 0;
$handle = popen($gnuPlot, "w");
fwrite($handle, "set terminal jpeg\n");
fwrite($handle, "set xdata time\n");
fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
fwrite($handle, "set xrange [ \"${startdate}\":\"${enddate}\" ]\n");
fwrite($handle, "set yrange [ 0: ]\n");
fwrite($handle, "set format x \"%d\"\n");

foreach ($meTypes as $meType) {
  fwrite($handle, "set output \"" . $filebase . "_" . $meType . ".jpg\"\n");
  fwrite($handle, "set title \"Total Alarm Events from " . $meType . " per day\"\n");
  fwrite($handle, "plot \"$filebase\" index " . $index . " using 1:2 notitle with steps\n");


  $index++;
}
pclose($handle);

#
# Include the links to the graphs
#
$img=basename($filebase);
echo "<H4>Alarm Event Totals</H4>\n";
foreach ($meTypes as $meType) {
  echo "<p><img src=\"/temp/$img" . "_" . $meType . ".jpg\" alt=\"\"></p>\n";
}


?>


<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
