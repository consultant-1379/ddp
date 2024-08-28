<?php
$pageTitle = "NEAD Stats";
include "../common/init.php";

$startdate = $_GET['start'];
$enddate   = $_GET['end'];

require_once 'HTML/Table.php';

function getOverallSyncTable($site,$start,$end,$statsDB)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array( 'NE Type', 'Number of Successful Syncs', 'Average Sync Time (HH:MM:SS)', 'Average Number Of MOs', 'Average MOs/sec', 'Number of failed Syncs' ),
		  null, 'th' );

  $failCounts = array();
  $statsDB->query("
SELECT ne_types.name, SUM(ne_sync_failure.count)
 FROM ne_sync_failure, ne, ne_types, sites
 WHERE
  ne_sync_failure.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_failure.neid = ne.id AND
  ne_types.id = ne.netypeid AND
  ne_sync_failure.date BETWEEN '$start' AND '$end'
 GROUP BY ne_types.name
");
  while ( $row = $statsDB->getNextRow() ) {
    $failCounts[$row[0]] = $row[1];
  }

  $statsDB->query("
SELECT ne_types.name, COUNT(ne_sync_success.neid), SEC_TO_TIME(AVG(timeTotal)), ROUND(AVG(numMoRead)), ROUND(AVG(numMoRead/timeTotal))
 FROM ne_sync_success, ne, ne_types, sites
 WHERE
  ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_success.neid = ne.id AND
  ne_types.id = ne.netypeid AND
  ne_sync_success.endtime BETWEEN '$start 00:00:00' AND '$end 23:59:59'
 GROUP BY ne_types.name");
  while ( $row = $statsDB->getNextRow() ) {
    if ( isset($failCounts[$row[0]]) ) {
      array_push( $row, $failCounts[$row[0]] );
    } else {
      array_push( $row, "0" );
    }
    $table->addRow($row);
  }

  return $table;
}

function getRncSyncTable($site,$start,$end,$statsDB)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array( 'RNC', 'Number of Successful Syncs', 'Average Sync Time (HH:MM:SS)', 'Max Sync Time (HH:MM:SS)',
			 'Average Number Of MOs', 'Average MOs/sec', 'Number of failed Syncs' ),
		  null, 'th' );

  $failCounts = array();
  $statsDB->query("
SELECT ne.name, SUM(ne_sync_failure.count)
 FROM ne_sync_failure, ne, ne_types, sites
 WHERE
  ne_sync_failure.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_failure.neid = ne.id AND
  ne_sync_failure.date BETWEEN '$start' AND '$end' AND
  ne.netypeid = ne_types.id AND ne_types.name = 'RNC'
 GROUP BY ne.name
");
  while ( $row = $statsDB->getNextRow() ) {
    $failCounts[$row[0]] = $row[1];
  }

  $statsDB->query("
SELECT ne.name, COUNT(ne_sync_success.neid), SEC_TO_TIME(AVG(timeTotal)), SEC_TO_TIME(MAX(timeTotal)), ROUND(AVG(numMoRead)), ROUND(AVG(numMoRead/timeTotal))
 FROM ne_sync_success, ne, ne_types, sites
 WHERE
  ne_sync_success.siteid = sites.id AND sites.name = '$site' AND
  ne_sync_success.neid = ne.id AND
  ne_sync_success.endtime BETWEEN '$start 00:00:00' AND '$end 23:59:59' AND
  ne.netypeid = ne_types.id AND ne_types.name = 'RNC'
 GROUP BY ne.name");
  while ( $row = $statsDB->getNextRow() ) {
    if ( isset($failCounts[$row[0]]) ) {
      array_push( $row, $failCounts[$row[0]] );
    } else {
      array_push( $row, "0" );
    }
    $table->addRow($row);
  }

  return $table;
}

function plotSyncs($site,$startdate,$enddate,$statsDB)
{
  global $web_temp_dir, $gnuPlot;

  $filebase=tempnam($web_temp_dir,"month_ne_sync");
  $genFiles = array();

  foreach (array( "fail", "succ") as $type ) {
    if ( $type == "fail" ) {
      $statsDB->query("
SELECT date, SUM(count)
 FROM ne_sync_failure,sites
 WHERE
  sites.name = '$site' AND sites.id = ne_sync_failure.siteid AND
  ne_sync_failure.date BETWEEN '$startdate' AND '$enddate'
 GROUP BY date
 ORDER BY date");
    } else {
      $statsDB->query("
SELECT DATE_FORMAT(endtime,'%Y-%m-%d') AS date, COUNT(*)
 FROM ne_sync_success,sites
 WHERE
  sites.name = '$site' AND sites.id = ne_sync_success.siteid AND
  ne_sync_success.endtime BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
 GROUP BY date
 ORDER BY date");
    }

    $genFiles[$type] = $filebase . "_" . $type;

    $handle=fopen($genFiles[$type],"w");
    while($row = $statsDB->getNextRow()) {
      fwrite($handle, "$row[0] $row[1]\n");
    }
    fclose($handle);
  }

  $img=$filebase . ".jpg";

  $handle = popen($gnuPlot, "w");
  fwrite($handle, "set terminal jpeg\n");
  fwrite($handle, "set xdata time\n");
  fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
  fwrite($handle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
  fwrite($handle, "set yrange [ 0: ]\n");
  fwrite($handle, "set format x \"%d\"\n");
  fwrite($handle, "set output '" . $img . "'\n");
  fwrite($handle, "set title \"Number Of Syncs\"\n");
  fwrite($handle, "plot '". $genFiles["fail"] . "' using 1:2 title 'Failed' with steps, '" . $genFiles["succ"] . "' using 1:2 title 'Successfull' with steps\n");
  pclose($handle);

  return basename($img);
}

function plotConnYY($site,$startdate,$enddate,$statsDB)
{
  global $web_temp_dir, $gnuPlot, $debug;

  $filebase=tempnam($web_temp_dir,"month_conn_yy");

  #
  # Connection status
  #
  $statsDB->query("
  SELECT date, conn
 FROM nead_connections, sites
 WHERE
  nead_connections.siteid = sites.id AND sites.name = '$site' AND
  nead_connections.date BETWEEN '$startdate' AND '$enddate'
 ORDER BY date
");
  $handle=fopen($filebase . "_conn","w");
  while($row = $statsDB->getNextRow()) {
    fwrite($handle, "$row[0] $row[1]\n");
  }
  fclose($handle);

  #
  # Yin/Yang
  #
  $statsDB->query("
SELECT DATE_FORMAT(time,'%Y-%m-%d') AS date,
       SUM(n_recv), (SUM(n_recv) - SUM(n_discard)),
       ROUND(AVG(n_proc_avg_t)),
       ROUND(AVG(total)), ROUND(AVG(alive))
 FROM hires_nead_stat, sites
 WHERE
  hires_nead_stat.siteid = sites.id AND sites.name = '$site' AND
  hires_nead_stat.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
 GROUP BY date
 ORDER BY date
");
  $handle=fopen($filebase . "_yy","w");
  while($row = $statsDB->getNextRow()) {
    fwrite($handle, "$row[0] $row[1] $row[2] $row[3] $row[4] $row[5]\n");
  }
  fclose($handle);

  $imgList = array();
  $handle = popen($gnuPlot, "w");
  fwrite($handle, "set terminal jpeg\n");
  fwrite($handle, "set xdata time\n");
  fwrite($handle, "set timefmt \"%Y-%m-%d\"\n");
  fwrite($handle, "set xrange [ \"$startdate\":\"$enddate\" ]\n");
  fwrite($handle, "set yrange [ 0: ]\n");
  fwrite($handle, "set format x \"%d\"\n");

  $imgList["conn"] = $filebase . "_conn.jpg";
  fwrite($handle, "set output '" . $imgList["conn"] . "'\n");
  fwrite($handle, "set title \"Number Of Connection Status Events\"\n");
  fwrite($handle, "plot '" . $filebase . "_conn" . "' using 1:2 notitle with steps\n");

  $imgList["notif"] = $filebase . "_notif.jpg";
  fwrite($handle, "set output '" . $imgList["notif"] . "'\n");
  fwrite($handle, "set title \"Number Of Notifications Received\"\n");
  fwrite($handle, "plot '" . $filebase . '_yy'. "' using 1:2 title 'Total' with steps, '" . $filebase . '_yy'. "' using 1:3 title 'Persistent' with steps\n");

  $imgList["notif_time"] = $filebase . "_notif_time.jpg";
  fwrite($handle, "set output '" . $imgList["notif_time"] . "'\n");
  fwrite($handle, "set title \"Average Notification Processing Time\"\n");
  fwrite($handle, "plot '" . $filebase . '_yy'. "' using 1:4 notitle with steps\n");

  $imgList["nodes"] = $filebase . "_nodes.jpg";
  fwrite($handle, "set output '" . $imgList["nodes"] . "'\n");
  fwrite($handle, "set title \"Number Of Nodes\"\n");
  fwrite($handle, "plot '" . $filebase . '_yy'. "' using 1:5 title 'Total' with steps, '" . $filebase . '_yy'. "' using 1:6 title 'Alive' with steps\n");

  pclose($handle);

  $imgList2 = array();
  foreach ($imgList as $id => $file ) {
    $imgList2[$id]=basename($file);
  }

  if ( $debug ) {
    $str = print_r($imgList2, true);
    echo "<pre>plotConnYY imgList2=$str</pre>\n";
  }

  return $imgList2;
}

function getEventsTable($site,$startdate,$enddate,$statsDB)
{
  $table = new HTML_Table('border=1');
  $table->addRow( array( 'Connection Status events/day', 'Notifications Received/day', 'Persistent Notifications Received/day' ),
		  null, 'th' );

  $numDays = $statsDB->queryRow("
SELECT COUNT(DISTINCT(DATE_FORMAT(time,'%Y-%m-%d')))
 FROM hires_nead_stat, sites
 WHERE
  hires_nead_stat.siteid = sites.id AND sites.name = '$site' AND
  hires_nead_stat.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'");

  $rowEvent = $statsDB->queryRow("
SELECT ROUND(SUM(n_recv)/$numDays[0]),
       ROUND( (SUM(n_recv) - SUM(n_discard)) / $numDays[0] )
 FROM hires_nead_stat, sites
 WHERE
  hires_nead_stat.siteid = sites.id AND sites.name = '$site' AND
  hires_nead_stat.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'");

  $rowConn = $statsDB->queryRow("
SELECT ROUND(AVG(conn))
 FROM nead_connections, sites
 WHERE
  nead_connections.siteid = sites.id AND sites.name = '$site' AND
  nead_connections.date BETWEEN '$startdate' AND '$enddate'");

  $table->addRow( array( $rowConn[0], $rowEvent[0], $rowEvent[1] ) );

  return $table;
}

$statsDB = new StatsDB();

echo "<H4>Monthly Average</H4>\n";
echo getOverallSyncTable($site,$startdate,$enddate,$statsDB)->toHTML();

echo "<H4>Monthly Average Per RNC</H4>\n";
echo getRncSyncTable($site,$startdate,$enddate,$statsDB)->toHTML();

echo "<H4>Syncs per day</H4>\n";

$img=plotSyncs($site,$startdate,$enddate,$statsDB);
echo "<img src=\"/temp/$img\" alt=\"\">\n";

echo "<H1>Events</H1>";
echo "<H4>Monthly Average</H4>";

echo getEventsTable($site,$startdate,$enddate,$statsDB)->toHTML();

$imgList=plotConnYY($site,$startdate,$enddate,$statsDB);

echo "<p><img src=\"/temp/" . $imgList["conn"] . "\" alt=\"\"></p>\n";
echo "<p><img src=\"/temp/" . $imgList["notif"] . "\" alt=\"\"></p>\n";
echo "<p><img src=\"/temp/" . $imgList["notif_time"] . "\" alt=\"\"></p>\n";
echo "<p><img src=\"/temp/" . $imgList["nodes"] . "\" alt=\"\"></p>\n";
?>

<?php
$statsDB->disconnect();
include "../common/finalise.php";
?>
