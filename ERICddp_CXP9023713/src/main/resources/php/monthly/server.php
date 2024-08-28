<?php
$pageTitle = "Monthly Server Stats";
if (isset($_GET['chart'])) $UI = false;
include "../common/init.php";

$statsDB = new StatsDB();

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

$serverId= $_GET['serverid'];


function cpuChart($serverId, $startDate, $endDate) {
    global $site, $statsDB;
    $data = array(
        "iowait" => array(),
        "sys" => array(),
        "usr" => array()
    );

    $sql = "SELECT DATE_FORMAT(time,'%Y-%m-%d') AS date," .
        "ROUND(AVG(iowait), 0) AS iowait," .
        "ROUND(AVG(sys), 0) AS sys," .
        "ROUND(AVG(user), 0) AS usr " .
        "FROM hires_server_stat " .
        "WHERE hires_server_stat.serverid = " . $serverId .
        " AND time BETWEEN '" . $startDate . " 00:00:00' AND '" . $endDate . " 23:59:59' GROUP BY date ORDER BY date";

    $statsDB->query($sql);
    while ($row = $statsDB->getNextNamedRow()) {
        $xVal = $row['date'];
        $data['iowait'][$xVal] = $row['iowait'];
        $data['sys'][$xVal] = $row['sys'];
        $data['usr'][$xVal] = $row['usr'];
    }
    include "../classes/Graph.class.php";
    $g = new Graph($startDate, $endDate, "day");
    $g->addData($data);
    $g->setType("stacked");
    $g->setYAxisLimits(0,100,10);
    $g->setYAxisTitle("Percent");
    $g->display();
}

function memChart($serverId, $startDate, $endDate, $memType) {
    // TODO: Create a "double" graph which uses a single query
    // (two graphs in one image)
    global $site, $statsDB;
    $memName = $memType;
    switch ($memType) {
    case "freeram":
        $memName = "Free RAM";
        break;
    case "freeswap":
        $memName = "Free Swap";
        break;
    }
    $data = array($memName => array());
    $sql = "SELECT DATE_FORMAT(time,'%Y-%m-%d') AS date," .
        "ROUND(AVG(" . $memType . ")) AS " . $memType . " FROM hires_server_stat WHERE " .
        "hires_server_stat.serverid = $serverId AND " .
        "hires_server_stat.time BETWEEN '" . $startDate . " 00:00:00' AND '" . $endDate . " 23:59:59' " .
        "GROUP BY date ORDER BY date";
    $statsDB->query($sql);
    while ($row = $statsDB->getNextNamedRow()) {
        $xVal = $row['date'];
        $data[$memName][$xVal] = $row[$memType];
    }
    include "../classes/Graph.class.php";
    $g = new Graph($startDate, $endDate, "day");
    $g->addData($data);
    $g->setYAxisTitle($memName . " (Kb)");
    $g->display();
}

if (isset($_GET['chart'])) {
    if ($_GET['chart'] == "cpu") {
        cpuChart($serverId, $startdate, $enddate);
    } else if ($_GET['chart'] == "mem") {
        memChart($serverId, $startdate, $enddate, $_GET['memtype']);
    }
    exit;
}

?>

<ul>
  <li><a href="#cpu">CPU</a></li>
  <li><a href="#disks">Disks</a></li>
  <li><a href="#network">Network</a></li>
</ul>

<a name="cpu">
<H1>CPU</H1>
<img src="?<?=$_SERVER['QUERY_STRING']?>&chart=cpu" alt="CPU Usage">

<H3>Monthly Average</H3>
<table cellpadding="2" cellspacing="2" border="1">
  <tr> <th>Total</th> <th>User</th> <th>Sys</th> <th>IO Wait</th><tr>

<?php
$sqlquery = "SELECT " .
    "ROUND(AVG(user), 0) AS usr," .
    "ROUND(AVG(sys), 0) AS sys," .
    "ROUND(AVG(iowait), 0) AS iowait," .
    "ROUND(AVG(user+sys+iowait)) AS total " .
    "FROM hires_server_stat " .
    "WHERE hires_server_stat.serverid = " . $serverId .
    " AND time BETWEEN '" . $startdate . " 00:00:00' AND '" . $enddate . " 23:59:59'";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

$row = $statsDB->getNextNamedRow();
echo "<tr> <td>" . $row['total'] . "</td> <td>" . $row['usr'] . "</td> <td>" . $row['sys'] . "</td> <td>" . $row['iowait'] . "</td></tr>\n";
echo "</table>\n";
?>

<a name="mem">
<H1>Memory</H1>
<img src="?<?=$_SERVER['QUERY_STRING']?>&chart=mem&memtype=freeram" alt="Free Memory">
<img src="?<?=$_SERVER['QUERY_STRING']?>&chart=mem&memtype=freeswap" alt="Free Swap">

<a name="disks">
<H1>Disks</H1>
<H4>Monthly Average</H4>
<table cellpadding="2" cellspacing="2" border="1">
  <tr> <th>Disk Name</th> <th>Busy (%)</th> <th>Read Writes/sec</th> <th>Blocks/sec</th> <th>Average Service Time(ms)</th> <tr>

<?php
$sqlquery="SELECT disks.name, disk_stats.diskid, ROUND(AVG(busy)), ROUND(AVG(rws)), ROUND(AVG(blks)), ROUND(AVG(avserv)) FROM disks,disk_stats WHERE disks.serverid = $serverId AND disks.id = disk_stats.diskid AND disk_stats.date >= \"$startdate\" AND disk_stats.date <= \"$enddate\" GROUP BY disks.id";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

$diskIds = array();
$diskNames = array();
$diskIndex = 0;
while($row = $statsDB->getNextRow()) {
  echo " <tr> <td>$row[0]</td>  <td>$row[2]</td> <td>$row[3]</td> <td>$row[4]</td> <td>$row[5]</td> </tr>\n";
  $diskIds[$diskIndex] = $row[1];
  $diskNames[$diskIndex] = $row[0];
  $diskIndex++;
}
echo "</table>\n";

if ( $diskIndex > 0 ) {
  $filebase=tempnam($web_temp_dir,"month_disk");
  $imgBase="/temp/" . basename($filebase);
  $dataFile=$filebase . "_data.txt";
  $cmdFile=$filebase . "_cmd.txt";
  
  $dataHandle=fopen($dataFile,"w");
  $cmdHandle=fopen($cmdFile,"w");

  fwrite($cmdHandle, "set terminal jpeg\n");
  fwrite($cmdHandle, "set xdata time\n");
  fwrite($cmdHandle, "set timefmt \"%Y-%m-%d\"\n");
  fwrite($cmdHandle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
  fwrite($cmdHandle, "set format x \"%d\"\n");
  fwrite($cmdHandle, "set size 0.5, 0.5\n");

  for ($i = 0; $i < $diskIndex; $i++) {
    $diskId = $diskIds[$i];

    $sqlquery="SELECT disk_stats.date, disk_stats.busy, disk_stats.rws, disk_stats.blks, disk_stats.avserv FROM disk_stats WHERE disk_stats.diskid = $diskId AND disk_stats.date >= \"$startdate\" AND disk_stats.date <= \"$enddate\" ORDER BY disk_stats.date";
    $statsDB->query($sqlquery);

    while($row = $statsDB->getNextRow()) {
      fwrite($dataHandle, "$row[0] $row[1] $row[2] $row[3] $row[4]\n");
    }
    fwrite($dataHandle, "\n\n");

    fwrite($cmdHandle, "set output \"" . $filebase . $diskNames[$i] . "_avserv.jpg\"\n");
    fwrite($cmdHandle, "set title \"Average Service (ms)\"\n");
    fwrite($cmdHandle, "plot \"$dataFile\" index $i using 1:5 notitle with steps\n");

    fwrite($cmdHandle, "set output \"" . $filebase . $diskNames[$i] . "_rws.jpg\"\n");
    fwrite($cmdHandle, "set title \"Read Writes/sec\"\n");
    fwrite($cmdHandle, "plot \"$dataFile\" index $i using 1:3 notitle with steps\n");

    fwrite($cmdHandle, "set output \"" . $filebase . $diskNames[$i] . "_busy.jpg\"\n");
    fwrite($cmdHandle, "set title \"Busy %\"\n");
    fwrite($cmdHandle, "plot \"$dataFile\" index $i using 1:2 notitle with steps\n");

    fwrite($cmdHandle, "set output \"" . $filebase . $diskNames[$i] . "_blks.jpg\"\n");
    fwrite($cmdHandle, "set title \"Blocks/sec\"\n");
    fwrite($cmdHandle, "plot \"$dataFile\" index $i using 1:4 notitle with steps\n");
  }

  fclose($dataHandle);
  fclose($cmdHandle);

  exec("$gnuPlot $cmdFile");

  for ($i = 0; $i < $diskIndex; $i++) {
    echo "<H2>$diskNames[$i]</H2>\n";
    echo "<table><tr>";
    echo "<td><img src=\"$imgBase" . $diskNames[$i] . "_busy.jpg\" alt=\"\"></td>";
    echo "<td><img src=\"$imgBase" . $diskNames[$i] . "_rws.jpg\" alt=\"\"></td>";
    echo "<td><img src=\"$imgBase" . $diskNames[$i] . "_blks.jpg\" alt=\"\"></td>";
    echo "<td><img src=\"$imgBase" . $diskNames[$i] . "_avserv.jpg\" alt=\"\"></td>";
    echo "</tr></table>";
  }
} 

?>

<a name="volumes">
<H2>Volumes</H2>
<H4>Monthly Average</H4>
<table cellpadding="2" cellspacing="2" border="1">
  <tr> <th>Name</th> <th>Size</th> <th>Used</th> <tr>
<?php
$sqlquery="SELECT volumes.name, volume_stats.volid, ROUND(AVG(volume_stats.size)) as size, ROUND(AVG(volume_stats.used)) as used FROM volumes,volume_stats WHERE volume_stats.serverid = $serverId AND volumes.id = volume_stats.volid AND volume_stats.date >= \"$startdate\" AND volume_stats.date <= \"$enddate\" GROUP BY volumes.id order by size desc";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

$volIds = array();
$volNames = array();
$volIndex = 0;
while($row = $statsDB->getNextRow()) {
  echo " <tr> <td>$row[0]</td>  <td>$row[2]</td> <td>$row[3]</td>\n";
  $volIds[$volIndex] = $row[1];
  $volNames[$volIndex] = $row[0];
  $volIndex++;
}
echo "</table>\n";

if ( $volIndex > 0 ) {
  $filebase=tempnam($web_temp_dir,"vol_disk");
  $imgBase="/temp/" . basename($filebase);
  $dataFile=$filebase . "_data.txt";
  $cmdFile=$filebase . "_cmd.txt";
  
  $dataHandle=fopen($dataFile,"w");
  $cmdHandle=fopen($cmdFile,"w");

  fwrite($cmdHandle, "set terminal jpeg\n");
  fwrite($cmdHandle, "set xdata time\n");
  fwrite($cmdHandle, "set timefmt \"%Y-%m-%d\"\n");
  fwrite($cmdHandle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
  fwrite($cmdHandle, "set format x \"%d\"\n");
  fwrite($cmdHandle, "set size 1, 0.5\n");

  for ($i = 0; $i < $volIndex; $i++) {
    $volId = $volIds[$i];

    $sqlquery="SELECT volume_stats.date, volume_stats.used FROM volume_stats WHERE volume_stats.volid = $volId AND volume_stats.date >= \"$startdate\" AND volume_stats.date <= \"$enddate\" AND volume_stats.serverid = $serverId ORDER BY volume_stats.date";
    $statsDB->query($sqlquery);

    while($row = $statsDB->getNextRow()) {
      fwrite($dataHandle, "$row[0] $row[1]\n");
    }
    fwrite($dataHandle, "\n\n");

    fwrite($cmdHandle, "set output \"" . $filebase . $volNames[$i] . "_used.jpg\"\n");
    fwrite($cmdHandle, "set title \"" . $volNames[$i] . "(MB)\"\n");
    fwrite($cmdHandle, "plot \"$dataFile\" index $i using 1:2 notitle with steps\n");
  }

  fclose($dataHandle);
  fclose($cmdHandle);

  exec("$gnuPlot $cmdFile");

  for ($i = 0; $i < $volIndex; $i++) {
    echo "<p><img src=\"$imgBase" . $volNames[$i] . "_used.jpg\" alt=\"\"></p>";
  }
} 

  
?>

<a name="network">
<H1>Network</H1>
<H4>Monthly Average</H4>
<table cellpadding="2" cellspacing="2" border="1">
  <tr> <th>Interace Name</th> <th>Input Pkts/day</th> <th>Output Pkts/day</th> <tr>

<?php
$sqlquery="SELECT network_interfaces.name, network_interface_stats.ifid, ROUND(AVG(network_interface_stats.inpkts)), ROUND(AVG(network_interface_stats.outpkts)) FROM network_interfaces, network_interface_stats, sites WHERE network_interfaces.serverid = $serverId AND network_interfaces.id = network_interface_stats.ifid AND network_interface_stats.date >= \"$startdate\" AND network_interface_stats.date <= \"$enddate\" GROUP BY network_interface_stats.ifid";
if ($debug) {
  echo "<p>$sqlquery</p>";
}
$statsDB->query($sqlquery);

$ifIds = array();
$ifNames = array();
$ifIndex = 0;
while($row = $statsDB->getNextRow()) {
  echo " <tr> <td>$row[0]</td>  <td>$row[2]</td> <td>$row[3]</td> </tr> \n";
  $ifIds[$ifIndex] = $row[1];
  $ifNames[$ifIndex] = $row[0];
  $ifIndex++;
}
echo "</table>\n";

if ( $ifIndex > 0 ) {
  $filebase=tempnam($web_temp_dir,"month_if");
  $imgBase="/temp/" . basename($filebase);
  $dataFile=$filebase . "_data.txt";
  $cmdFile=$filebase . "_cmd.txt";
  
  $dataHandle=fopen($dataFile,"w");
  $cmdHandle=fopen($cmdFile,"w");

  fwrite($cmdHandle, "set terminal jpeg\n");
  fwrite($cmdHandle, "set xdata time\n");
  fwrite($cmdHandle, "set timefmt \"%Y-%m-%d\"\n");
  fwrite($cmdHandle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
  fwrite($cmdHandle, "set format x \"%d\"\n");
  fwrite($cmdHandle, "set size 1, 0.5\n");

  for ($i = 0; $i < $ifIndex; $i++) {
    $ifId = $ifIds[$i];

    $sqlquery="SELECT network_interface_stats.date, network_interface_stats.inpkts + network_interface_stats.outpkts FROM network_interface_stats WHERE network_interface_stats.ifid = $ifId AND network_interface_stats.date >= \"$startdate\" AND network_interface_stats.date <= \"$enddate\" ORDER BY network_interface_stats.date";
    $statsDB->query($sqlquery);

    while($row = $statsDB->getNextRow()) {
      fwrite($dataHandle, "$row[0] $row[1]\n");
    }
    fwrite($dataHandle, "\n\n");

    fwrite($cmdHandle, "set output \"" . $filebase . $ifNames[$i] . ".jpg\"\n");
    fwrite($cmdHandle, "set title \"Packets/day\"\n");
    fwrite($cmdHandle, "plot \"$dataFile\" index $i using 1:2 notitle with steps\n");
  }

  fclose($dataHandle);
  fclose($cmdHandle);

  exec("$gnuPlot $cmdFile");

  for ($i = 0; $i < $ifIndex; $i++) {
    echo "<H2>$ifNames[$i]</H2>\n";
    echo "<img src=\"$imgBase" . $ifNames[$i] . ".jpg\" alt=\"\"></td>";
  }
} 

$statsDB->disconnect();
include "../common/finalise.php";
?>
