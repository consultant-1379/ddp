<?php
$pageTitle = "Event Streaming";
include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class EventsStreamed extends DDPObject {
  var $cols = array(
		    'type' => 'Type', 
		    'peaknodes' => 'Peak Nodes',
		    'totalvol' => 'Total Volume (GB)',
		    'totalevents' => 'Total Events',
		    'peakvol' => 'Peak Volume (KB)from a Node',
		    'peakevents' => 'Peak Events from a Node'
		    );

    var $title = "Events Streamed";

    function __construct() {
        parent::__construct("eventsStreamed");
	$this->defaultOrderBy = "type";
    }

    function getData() {
        global $date;
	global $site;
	$sql = "
SELECT 
 eniq_streaming.type AS type,
 MAX(eniq_streaming.nodes) AS peaknodes,
 ROUND(SUM(eniq_streaming.totalvolmb)/1024,1) AS totalvol,
 SUM(eniq_streaming.totalevents) AS totalevents,
 MAX(eniq_streaming.peakvolkb) AS peakvol,
 MAX(eniq_streaming.peakevents) AS peakevents
FROM
 eniq_streaming, sites
WHERE
 eniq_streaming.siteid = sites.id AND sites.name = '$site' AND
 eniq_streaming.rop BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eniq_streaming.type
";
	$this->populateData($sql);
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

echo "<H2>Events Streamed Per Hour</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table("border=0");    

$sqlParam = 
  array( 'title'      => 'Events Streamed',
	 'ylabel'     => 'Events',
	 'type'       => 'sb',
	 'sb.barwidth'=> 3600,
	 'useragg'    => 'true',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
		 array (
			'timecol' => 'rop',
			'multiseries' => 'eniq_streaming.type',
			'whatcol' => array( 'totalevents' => 'Events' ),
			'tables'  => "eniq_streaming, sites",
			'where'   => "eniq_streaming.siteid = sites.id AND sites.name = '%s'",
			'qargs'   => array( 'site' )
			)
		 )
	 );
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array($sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 )) );

$sqlParam = 
  array( 'title'      => 'Volume Streamed',
	 'ylabel'     => 'MB',
	 'type'       => 'sb',
	 'sb.barwidth'=> 3600,
	 'useragg'    => 'true',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
		 array (
			'timecol' => 'rop',
			'multiseries' => 'eniq_streaming.type',
			'whatcol' => array( 'totalvolmb' => 'MB' ),
			'tables'  => "eniq_streaming, sites",
			'where'   => "eniq_streaming.siteid = sites.id AND sites.name = '%s'",
			'qargs'   => array( 'site' )
			)
		 )
	 );
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array($sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 )) );

echo $graphs->toHTML();

drawHeaderWithHelp( "Event Rate", 2, "eventrate", "This is the events loaded per hour / 3600" );
$sqlParam = 
  array( 'title'      => 'Events Streamed/Second',
	 'ylabel'     => 'Events',
	 'type'       => 'sb',
	 'sb.barwidth'=> 3600,
	 'useragg'    => 'true',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
		 array (
			'timecol' => 'rop',
			'multiseries' => 'eniq_streaming.type',
			'whatcol' => array( 'totalevents/3600' => 'Events' ),
			'tables'  => "eniq_streaming, sites",
			'where'   => "eniq_streaming.siteid = sites.id AND sites.name = '%s'",
			'qargs'   => array( 'site' )
			)
		 )
	 );
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );

$dailyStatsHelp = <<<EOT
<ul>
  <li>Peak Nodes is the maximum number of nodes seen in an hour</li>
  <li>Peak Volume is the maximum volume seen from one node in an hour</li>
  <li>Peak Events is the maximum number of events seen from one node in an hour</li>
</ul>
EOT;
drawHeaderWithHelp("Daily Statistics", 2, 'dailystat', $dailyStatsHelp );
$totalTable = new EventsStreamed();
echo $totalTable->getHtmlTableStr();

include "../common/finalise.php";
?>
