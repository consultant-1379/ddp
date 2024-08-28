<?php
$pageTitle = "Event Streaming Instrumentation";
$YUI_DATATABLE = true;
include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$EVENT_NAMES = array();

function getEventName($eventId) {
  global $EVENT_NAMES;

  if (array_key_exists($eventId,$EVENT_NAMES)) {
    return $EVENT_NAMES[$eventId];
  } else {
    return $eventId;
  }
}

class DvtpStreamed extends DDPObject {
  var $cols = array(
		    'wfName' => 'Workflow',
		    'Files' => 'Files', 
		    'Events' => 'Events',
		    'gb' => 'Volume (GB)',
		    );

    var $title = "DVTP Events Streamed";

    function __construct() {
        parent::__construct("eventsStreamedDVTP");
    }

    function getData() {
        global $date;
	global $site;
	$sql = "
SELECT 
 IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
 FORMAT(SUM(eniq_streaming_dvtp_collector.Files),0) AS Files,
 FORMAT(SUM(eniq_streaming_dvtp_collector.Events),0) AS Events,
 ROUND(SUM(eniq_streaming_dvtp_collector.Bytes)/(1024*1024*1024),2) AS gb
FROM
 eniq_streaming_dvtp_collector, eniq_workflow_names, sites
WHERE
 eniq_streaming_dvtp_collector.siteid = sites.id AND sites.name = '$site' AND
 eniq_streaming_dvtp_collector.wfid = eniq_workflow_names.id AND 
 eniq_streaming_dvtp_collector.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eniq_workflow_names.name WITH ROLLUP
";
	$this->populateData($sql);
	return $this->data;
    }
}

class DvtpEventDistrib extends DDPObject {
  var $cols = array(
		    'eventType' => 'Event Type',
		    'eventCount' => 'Event Count',
		    'percent' => 'Percentage'
		    );

    var $title = " Event Distribution";
    var $defaultOrderBy = "eventCount";
    var $defaultOrderDir = "DESC";

    function __construct($total) {
        parent::__construct("dvtpEventDistrib");
	$this->total = $total;
    }

    function getData() {
        global $date;
	global $site;
	global $debug;
	
	$sql = "
SELECT 
 eventId AS eventType, 
 SUM(count) AS eventCount,
 ROUND( (SUM(count) * 100) / $this->total, 2) AS percent
FROM
 eniq_dvtp_eventdistrib, sites
WHERE
 eniq_dvtp_eventdistrib.siteid = sites.id AND sites.name = '$site' AND
 eniq_dvtp_eventdistrib.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eventId
";
	$this->populateData($sql);
	$this->columnTypes['eventType'] = 'string';


	foreach ($this->data as &$row) {
	  $row['eventType' ] = getEventName($row['eventType']);
	  if ( $debug ) { echo "<pre>"; print_r($row); echo "</pre>\n"; }
	}

	return $this->data;
    }
}
  
if ( isset($_GET['start']) ) { 
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}

echo "<H1></H1>\n";

echo "<H2> Daily Totals</H2>\n";
$totalTable = new DvtpStreamed();
echo $totalTable->getHtmlTableStr();

echo "<H2> Hourly Totals</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table('border=0');
foreach ( array('Files','Events','Bytes') as $counterType ) {
    $ylabel = $counterType;
    $title = $counterType;
    $counterMod = "";
    if ( strcmp($counterType,'Bytes') == 0 ) {
      $ylabel = 'GB';
      $title = 'Volume';
      $counterMod = "/(1024*1024*1024)";
    }
	
    $sqlParam =  
      array( 'title'      => $title,
	     'ylabel'     => $ylabel,
	     'type'        => 'sb',
	     'sb.barwidth' => '3600',
	     'presetagg'  => 'SUM:Hourly',
	     'persistent' => 'false',
	     'useragg'    => 'false',
	     'querylist' => 
	     array(
		   array (
			  'timecol' => 'time',
			  'whatcol' => array( $counterType . '' . $counterMod => $counterType ),
			  'tables'  => "eniq_streaming_dvtp_collector, sites",
			  'where'   => "eniq_streaming_dvtp_collector.siteid = sites.id AND sites.name = '%s'",
			  'qargs'   => array( 'site' )
			  )
		   )
	     );      
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}
echo $graphs->toHTML();

echo "<H2> Event Distribution</H2>\n";
$statsDB = new StatsDB();
$row = $statsDB->query("
SELECT eventId, SUM(count) AS eventCount
FROM eniq_dvtp_eventdistrib,sites
WHERE
 eniq_dvtp_eventdistrib.siteid = sites.id AND sites.name = '$site' AND
 eniq_dvtp_eventdistrib.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eventId
ORDER BY eventCount DESC");
$totalEvents = 0;
$countByEventId = array();
while ( $row = $statsDB->getNextRow() ) {
  $countByEventId[$row[0]] = $row[1];
  $totalEvents += $row[1];
}

$dvtpDistribTable = new DvtpEventDistrib($totalEvents);
echo $dvtpDistribTable->getClientSortableTableStr();

echo "<br>\n";
if ( $debug ) { echo "<pre>"; print_r($countByEventId); echo "</pre>\n"; }
$sqlParam =  
  array( 'title'      => "Event Distribution Per Hour",
	 'ylabel'     => "%",
	 'type'        => 'sb',
	 'sb.barwidth' => '3600',
	 'presetagg'  => 'SUM:Hourly',
	 'persistent' => 'false',
	 'useragg'    => 'false',
	 'querylist' => array()
	 );      
$otherIds = array();
$countGraphs = 0;
foreach ($countByEventId as $eventId => $eventCount) {
  if ( $countGraphs < 7 ) {
    $sqlParam['querylist'][] = 
      array (
	     'timecol' => 'time',
	     'whatcol' => array( "precent" => getEventName($eventId) ), 
	     'tables'  => "eniq_dvtp_eventdistrib, sites",
	     'where'   => "eniq_dvtp_eventdistrib.siteid = sites.id AND sites.name = '%s' AND eniq_dvtp_eventdistrib.eventid = $eventId",
	     'qargs'   => array( 'site' )
	     );
  } else {
    $otherIds[] = $eventId;
  }
  $countGraphs++;
}
if ( $debug ) { echo "<pre>otherids"; print_r($otherIds); echo "</pre>\n"; }

if ( count($otherIds) > 0 ) {
    $sqlParam['querylist'][] = 
      array (
	     'timecol' => 'time',
	     'whatcol' => array( "precent" => "Other" ),
	     'tables'  => "eniq_dvtp_eventdistrib, sites",
	     'where'   => "eniq_dvtp_eventdistrib.siteid = sites.id AND sites.name = '%s' AND eniq_dvtp_eventdistrib.eventid IN (" . implode(",",$otherIds) . ")",
	     'qargs'   => array( 'site' )
	     );  
}
if ( $debug ) { echo "<pre>querylist"; print_r($sqlParam['querylist']); echo "</pre>\n"; }

$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 600 );


include "../common/finalise.php";
?>

