<?php
$pageTitle = "ActiveMQ Queues / Topics";

include "common/init.php";
require_once "SqlPlotParam.php";
require_once "StatsDB.php";
require_once 'HTML/Table.php';

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

function getQPlot($title,$ylabel,$whatcol,$name,$table,$fromDate,$toDate)
{
    global $debug;

    $colNames = array_keys($whatcol);

    $sqlParam = 
        array( 'title'      => $title,
            'ylabel'     => $ylabel,
            'useragg'    => 'true',
            'persistent' => 'false',
            'querylist' => 
            array(
                array (
                    'timecol' => 'time',
                    'whatcol' => $whatcol,
                    'tables'  => "$table, jmx_names, sites",
                    'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.nameid = jmx_names.id AND jmx_names.name ='%s' AND $colNames[0] IS NOT NULL",
                    'qargs'   => array( 'site', 'name' )
                )
            )
        );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url =  $sqlParamWriter->getImgURL( $id, 
        "$fromDate 00:00:00", "$toDate 23:59:59", 
        true, 640, 240, 
        "name=$name" );	      	      
    if ( $debug ) { echo "<pre>getQPlot url=$url</pre>\n"; }
    return $url;
}

function getAvailableNames($statsDB,$table,$site,$fromDate,$toDate) {
    $sql = "SELECT DISTINCT(jmx_names.name) FROM jmx_names, $table, sites
        WHERE $table.siteid = sites.id AND sites.name = '$site' AND
        $table.nameid = jmx_names.id AND
        $table.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";

    $statsDB->query($sql);
    $names = array();
    while($row = $statsDB->getNextRow()) {
        $names[] = $row[0];
    }
    return $names;
}

if ( isset($_GET['start']) ) { 
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
} else {
    $fromDate = $date;
    $toDate = $date;
}

if ( isset($_GET['name']) ) {
  $names = array( $_GET['name'] );
} else {
 $names = getAvailableNames($statsDB,"activemq_queue_stats",$site,$fromDate,$toDate);
}

# Generate the plots, if there is data to plot
if (sizeof($names) == 0) {
    echo "<h3>No Queues or Topics defined for this site on this date</h3>\n";
    include "common/finalise.php";
    return;
}

# Menu
if ( count($names) > 1 ) {
  echo "<h1>ActiveMQ Queues and Topics</h1>\n";
  echo "<ul>\n";
  foreach ($names as $name) {
    echo "<li><a href=#" . $name . ">" . $name . "</a></li>\n";
  }
  echo "</ul>\n\n";
}

foreach ($names as $jmxName) {
    $graphTable = new HTML_Table('border=0');
    $graphTable->addRow( array(
        getQPlot($jmxName . ": Consumer Count",
                 "",
                 array('ConsumerCount' => "# Consumers"),
                 $jmxName,
                 "activemq_queue_stats",
                 $fromDate, $toDate)) );
    $graphTable->addRow( array(
        getQPlot($jmxName . ": Enqueue / Dequeue Count",
                 "",
                 array('EnqueueCount' => "# Enqueued", 'DequeueCount' => "# Dequeued"),
                 $jmxName,
                 "activemq_queue_stats",
                 $fromDate, $toDate)) );
    $graphTable->addRow( array(
        getQPlot($jmxName . ": Dispatch Count",
                 "",
                 array('DispatchCount' => "# Dispatched"),
                 $jmxName,
                 "activemq_queue_stats",
                 $fromDate, $toDate)) );
    $graphTable->addRow( array(
        getQPlot($jmxName . ": Queue Size",
                 "",
                 array('QueueSize' => "Queue Size"),
                 $jmxName,
                 "activemq_queue_stats",
                 $fromDate, $toDate)) );
    echo "<h2><a name=" . $jmxName . ">" . $jmxName . "</h2>\n";
    echo $graphTable->toHTML();
}

include "common/finalise.php";
?>
