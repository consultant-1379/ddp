<?php
$pageTitle = "Software Handling";
include "./common/init.php";

require_once 'HTML/Table.php';

function getDayTable($statsDB,$site,$date)
{
  $request= $_SERVER['PHP_SELF'] . "?site=$site&date=$date";

  $ordercol=0;
  $orderdir=0;
  if ( isset($_GET["col"]) ) {
    $ordercol = $_GET["col"];
  }
  if ( isset($_GET["sortdir"]) ) {
    $orderdir = $_GET["sortdir"];
  }

  # changed range from starttime to endtime because of a problem with
  # starttimes not being recorded. See HL49863
  $sqlquery="
SELECT smo_execution.id, smo_execution.starttime AS mintime, smo_execution.stoptime AS maxtime,
       IFNULL(smo_job.name, 'UNKNOWN'),
       COUNT(DISTINCT smo_job_ne_detail.neid) as numne,
       smo_job.typeOfNe, smo_job.workflow, smo_job.comment
FROM smo_job, smo_job_ne_detail, smo_execution, sites
WHERE
  sites.name = '$site' AND sites.id = smo_job.siteid AND
  smo_job.id = smo_execution.jobid AND
  smo_job_ne_detail.exeid = smo_execution.id AND
  smo_execution.stoptime BETWEEN '$date 00:00:00' AND  '$date 23:59:59'
GROUP BY smo_job_ne_detail.exeid
";

  $orderStr = " ORDER BY ";
  switch ($ordercol) {
  case 0:
    $orderStr .= " mintime";
    break;
  case 1:
    $orderStr .= " maxtime";
    break;
  case 2:
    $orderStr .= " smo_job.name";
    break;
  case 3:
    $orderStr .= " numne";
    break;
}

  if ( $orderdir ) {
    $orderStr .= " DESC";
    $request .= '&' . "sortdir=0";
  }
  else {
    $request .= '&' . "sortdir=1";
  }
  $sqlquery .= $orderStr;

  $statsDB->query($sqlquery);

  $table = new HTML_Table('border=1');
  $table->setCaption("SMO Jobs $date on $site");
  $table->addRow( array( "<a href=\"" . $request . "&col=0\">Start Time</a>",
			 "<a href=\"" . $request . "&col=1\">End Time</a>",
			 "<a href=\"" . $request . "&col=2\">Name</a>",
			 "<a href=\"" . $request . "&col=3\">Num NE</a>",
			 "NE Type",
			 "WorkFlow",
			 "Comment"
			  ),
		  null, 'th' );

  while($row = $statsDB->getNextRow()) {
    $table->addRow( array( $row[1], $row[2],
			   "<a href=\"" . $request . "&jobid=$row[0]\">$row[3]</a>",
			   $row[4], $row[5], $row[6], $row[7]) );


  }

  return $table;
}

function getJobTable($statsDB,$site,$jobId)
{
  $request= $_SERVER['PHP_SELF'] . "?site=$site&jobid=$jobId";

  $ordercol=0;
  $orderdir=0;
  if ( isset($_GET["col"]) ) {
    $ordercol = $_GET["col"];
  }
  if ( isset($_GET["sortdir"]) ) {
    $orderdir = $_GET["sortdir"];
  }

  $orderStr = " ORDER BY ";
  switch ($ordercol) {
  case 0:
    $orderStr .= " smo_ne_name.name";
    break;
  case 1:
    $orderStr .= " smo_job_ne_detail.actTypeId";
    break;
  case 2:
    $orderStr .= " smo_job_ne_detail.starttime";
    break;
  case 3:
    $orderStr .= " smo_job_ne_detail.endtime";
    break;
  case 4:
    $orderStr .= " smo_activity_result.name";
    break;
  }


  $sqlquery = "
SELECT smo_ne_name.name , smo_activity_name.name, smo_job_ne_detail.starttime,
       smo_job_ne_detail.endtime, smo_activity_result.name
FROM smo_job_ne_detail, smo_ne_name, smo_activity_name, smo_activity_result
WHERE
 smo_job_ne_detail.actTypeId = smo_activity_name.id AND
 smo_job_ne_detail.neid = smo_ne_name.id AND
 smo_job_ne_detail.resultid = smo_activity_result.id AND
 smo_job_ne_detail.exeid = $jobId
";

  if ( $orderdir ) {
    $orderStr .= " DESC";
    $request .= '&' . "sortdir=0";
  }
  else {
    $request .= '&' . "sortdir=1";
  }
  $sqlquery .= $orderStr;


  $statsDB->query($sqlquery);

  $table = new HTML_Table('border=1');
  $table->setCaption("SMO Job Detail");
  $table->addRow( array( "<a href=\"" . $request . "&col=0\">NE</a>",
			 "<a href=\"" . $request . "&col=1\">Activity</a>",
			 "<a href=\"" . $request . "&col=2\">Start Time</a>",
			 "<a href=\"" . $request . "&col=3\">End Time</a>",
			 "<a href=\"" . $request . "&col=4\">Result</a></th>" ),
		  null, 'th' );

  while($row = $statsDB->getNextRow()) {
    $table->addRow($row);
  }

  return $table;
}


function getTypeTable($statsDB,$site,$startdate,$enddate,$name,$typeOfNe)
{
  $successIds = array();
  $statsDB->query("SELECT id FROM smo_activity_result WHERE name LIKE 'Activity completed%'");
  while($row = $statsDB->getNextRow()) {
    $successIds[] = $row[0];
  }

  $statsDB->query("
SELECT smo_execution.id
FROM smo_job, smo_execution, sites
WHERE
  sites.name = '$site' AND
  sites.id = smo_job.siteid AND
  smo_job.id = smo_execution.jobid AND
  smo_execution.starttime BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND
  smo_job.name = '$name' AND
  smo_job.typeOfNe = '$typeOfNe'
");
  $exeIds = array();
  while($row = $statsDB->getNextRow()) {
    $exeIds[] = $row[0];
  }

  $table = new HTML_Table('border=1');
  $table->setCaption("Details for $name $typeOfNe");
  $table->addRow( array( 'Start Time', 'Duration', 'Number of Nodes',
			 'Number of Successfully completed Nodes'),
		  null, 'th' );

  $request= $_SERVER['PHP_SELF'] . "?site=$site&jobid=";

  foreach ( $exeIds as $exeId ) {
    $row = $statsDB->queryRow("
SELECT
 MIN(starttime), TIMEDIFF(MAX(endtime), MIN(starttime)),
 COUNT(DISTINCT(smo_job_ne_detail.neid))
FROM smo_job_ne_detail
WHERE smo_job_ne_detail.exeid = $exeId
GROUP BY smo_job_ne_detail.exeid
");

    $table->addRow( array( "<a href=\"" . $request . "$exeId\">$row[0]</a>",
			   $row[1], $row[2] ) );
    $numNodes = $row[2];

    $row = $statsDB->queryRow("
SELECT COUNT(DISTINCT(neid))
FROM smo_job_ne_detail
WHERE
 exeid = $exeId AND
 resultid NOT IN (" . join( ",", $successIds ) . ")");
    $table->setCellContents( $table->getRowCount() - 1, 3, $numNodes - $row[0] );
  }

  return $table;
}


function toHMS($sec)
{
  global $debug;

  $h = floor($sec / 3600);
  $m = floor( ($sec % 3600) / 60 );
  $s = $sec % 60;

  if ($debug) { echo "<p>toHMS sec=$sec h=$h m=$m s=$s</p>\n"; }

  return sprintf("%02d:%02d:%02d", $h, $m, $s );
}

function getSummaryTable($statsDB,$site,$startdate,$enddate)
{
  $statsDB->query("
SELECT smo_job.name, smo_job.typeOfNe, COUNT(smo_execution.id)
FROM smo_job, smo_execution, sites
WHERE
  sites.name = '$site' AND
  sites.id = smo_job.siteid AND
  smo_job.id = smo_execution.jobid AND
  smo_execution.starttime BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND
  smo_job.name IS NOT NULL
GROUP BY smo_job.name, smo_job.typeOfNe
");

  $table = new HTML_Table('border=1');

  # Really messy code to deal with column spanning for Number of Executions and Execution Time
  $table->getHeader()->addRow( array( 'Name', 'Ne Type', 'Number of Executions', 'Number of Nodes' ),
			       null, 'th' );
  $table->getHeader()->setCellAttributes( 0, 0, 'rowspan="2"' );
  $table->getHeader()->setCellAttributes( 0, 1, 'rowspan="2"' );
  $table->getHeader()->setCellAttributes( 0, 2, 'rowspan="2"' );
  $table->getHeader()->setCellAttributes( 0, 3, 'colspan="3"' );

  $table->getHeader()->setCellAttributes( 0, 6,'colspan="3"' );
  $table->getHeader()->setCellContents( 0, 6, 'Execution Time', 'th');

  $table->getHeader()->setCellContents( 1, 3, 'Min', 'th');
  $table->getHeader()->setCellContents( 1, 4, 'Max', 'th');
  $table->getHeader()->setCellContents( 1, 5, 'Avg', 'th');
  $table->getHeader()->setCellContents( 1, 6, 'Min', 'th');
  $table->getHeader()->setCellContents( 1, 7, 'Max', 'th');
  $table->getHeader()->setCellContents( 1, 8, 'Avg', 'th');




  $request = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

  while($row = $statsDB->getNextRow()) {
    $table->addRow( array( $row[0], $row[1],
			   '<a href="' . $request . "&name=" . urlencode($row[0]) .
			   "&netype=$row[1]" . '">' . $row[2] . '</a>')
		    );
  }

  for ( $i = 0; $i < $table->getRowCount(); $i++ ) {
    $statsDB->query("
SELECT COUNT(DISTINCT(smo_job_ne_detail.neid)), TIME_TO_SEC(TIMEDIFF(smo_execution.stoptime,smo_execution.starttime))
FROM smo_job, smo_execution, sites, smo_job_ne_detail
WHERE
  sites.name = '$site' AND
  sites.id = smo_job.siteid AND
  smo_job.id = smo_execution.jobid AND
  smo_job_ne_detail.exeid = smo_execution.id AND
  smo_execution.starttime BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59' AND
  smo_job.name = '" . $table->getCellContents( $i, 0 ) . "' AND
  smo_job.typeOfNe = '" . $table->getCellContents( $i, 1 ) . "'
GROUP BY smo_execution.id
");
    $nodesMin = 1000000;
    $nodesMax = 0;
    $nodesTotal = 0;
    $timeMin = 60 * 60 * 48;
    $timeMax = 0;
    $timeTotal = 0;

    $count = 0;
    while($row = $statsDB->getNextRow()) {
      if ( $row[0] < $nodesMin ) { $nodesMin = $row[0]; }
      if ( $row[0] > $nodesMax ) { $nodesMax = $row[0]; }
      $nodesTotal += $row[0];

      if ( $row[1] < $timeMin ) { $timeMin = $row[1]; }
      if ( $row[1] > $timeMax ) { $timeMax = $row[1]; }
      $timeTotal += $row[1];


      $count++;
    }

    $nodesAvg = "";
    $timeAvg = "";
    if ( $count > 0 ) {
      $nodesAvg = ( $nodesTotal / $count );
      $timeAvg = ( $timeTotal / $count );
    }

    $table->setCellContents( $i, 3, $nodesMin );
    $table->setCellContents( $i, 4, $nodesMax );
    $table->setCellContents( $i, 5, round($nodesAvg) );

    $table->setCellContents( $i, 6, toHMS($timeMin) );
    $table->setCellContents( $i, 7, toHMS($timeMax) );
    $table->setCellContents( $i, 8, toHMS(round($timeAvg)) );

  }

  return $table;
}

$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
  # Month view
  $startdate = $_GET['start'];
  $enddate   = $_GET['end'];

  if ( isset($_GET['name']) ) {
    $table = getTypeTable($statsDB,$site,$startdate,$enddate,urldecode($_GET['name']),$_GET['netype']);
    echo $table->toHTML();
  } else {
    $table = getSummaryTable($statsDB,$site,$startdate,$enddate);
    echo $table->toHTML();
  }
 } else if ( isset($_GET["jobid"]) ) {
  # Single Job view
  $table = getJobTable($statsDB,$site,$_GET["jobid"]);
  echo $table->toHTML();
 } else {
  # Day view
  $table = getDayTable($statsDB,$site,$date);
  echo $table->toHTML();
 }




include "common/finalise.php";
?>
