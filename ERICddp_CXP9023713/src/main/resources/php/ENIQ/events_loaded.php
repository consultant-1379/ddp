<?php
$pageTitle = "Events Loaded";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class EventsLoadedDailyTotal extends DDPObject {
  var $cols = array(
		    'tablename' => 'Table', 
		    'numloads' => 'Number Of Loads',
		    'rows_avg' => 'Average',
		    'rows_max' => 'Max',
		    'rows_sum' => 'Total'
		    );

    var $title = "Events Loaded";

    function __construct() {
        parent::__construct("eventsLoadedDailyTotal");
	$this->defaultOrderBy = "rows_sum";
	$this->defaultOrderDir = "DESC";
    }

    function getData() {
        global $date;
	global $site;
	$sql = "
SELECT 
 eniq_events_table_names.name AS tablename,
 COUNT(*) AS numloads,
 ROUND(AVG(eniq_events_loaded.numrows),0) AS rows_avg, MAX(eniq_events_loaded.numrows) AS rows_max, SUM(eniq_events_loaded.numrows) AS rows_sum
FROM
 eniq_events_loaded, eniq_events_table_names, sites
WHERE
 eniq_events_loaded.siteid = sites.id AND sites.name = '$site' AND
 eniq_events_loaded.tableid = eniq_events_table_names.id AND
 eniq_events_loaded.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eniq_events_loaded.tableid
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

echo "<H2>Events Loaded</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table("border=0");    

$sqlParam = 
  array( 'title'      => 'Error Events Loaded',
	 'ylabel'     => 'Rows',
	 'type'       => 'sb',
	 'useragg'    => 'true',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
		 array (
			'timecol' => 'time',
			'multiseries' => 'eniq_events_table_names.name',
			'whatcol' => array( 'numrows' => 'Rows' ),
			'tables'  => "eniq_events_table_names, eniq_events_loaded, sites",
			'where'   => "eniq_events_loaded.tableid = eniq_events_table_names.id AND eniq_events_table_names.name LIKE '%%_ERR' AND eniq_events_loaded.siteid = sites.id AND sites.name = '%s'",
			'qargs'   => array( 'site' )
			)
		 )
	 );
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array($sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 )) );


$sqlParam = 
  array( 'title'      => 'Success Events Loaded',
	 'ylabel'     => 'Rows',
	 'type'       => 'sb',
	 'useragg'    => 'true',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
		 array (
			'timecol' => 'time',
			'multiseries' => 'eniq_events_table_names.name',
			'whatcol' => array( 'numrows' => 'Rows' ),
			'tables'  => "eniq_events_table_names, eniq_events_loaded, sites",
			'where'   => "eniq_events_loaded.tableid = eniq_events_table_names.id AND eniq_events_table_names.name LIKE '%%_SUC' AND eniq_events_loaded.siteid = sites.id AND sites.name = '%s'",
			'qargs'   => array( 'site' )
			)
		 )
	 );
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array($sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 )) );

echo $graphs->toHTML();

echo "<H2>Events Rate"; drawHelpLink('eventrate'); echo "</H2>\n";
?>
<div id=eventrate class=helpbox>
<?php
    drawHelpTitle("Event Rate", "eventrate");
?>
<div class="helpbody">
This is the total events loaded per hour / 3600
</div>
</div>
<?php
$sqlParam = 
  array( 'title'      => 'Events Loaded Per Second (Hourly Average)',
	 'ylabel'     => 'Events',
	 'type'       => 'sb',
	 'sb.barwidth'=> 3600,
	 'useragg'    => 'false',
	 'presetagg'  => 'SUM:Hourly',
	 'persistent' => 'true',
	 'querylist' => 
	 array(
		 array (
			'timecol' => 'time',
			'multiseries' => 'eniq_events_table_names.name',
			'whatcol' => array( 'numrows/3600' => 'Events/Sec' ),
			'tables'  => "eniq_events_table_names, eniq_events_loaded, sites",
			'where'   => "eniq_events_loaded.tableid = eniq_events_table_names.id AND eniq_events_loaded.siteid = sites.id AND sites.name = '%s'",
			'qargs'   => array( 'site' )
			)
		 )
	 );
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );


echo "<H2>Daily Totals</H2>\n";
$totalTable = new EventsLoadedDailyTotal();
echo $totalTable->getClientSortableTableStr();

include "../common/finalise.php";
?>
