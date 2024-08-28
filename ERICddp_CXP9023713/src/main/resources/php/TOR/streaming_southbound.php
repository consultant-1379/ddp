<?php
  $pageTitle = "Streaming SB Datapath";

include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

$hostname=$_GET["server"];
$datapath=$_GET["datapath"];

echo "<a href=\"$php_webroot/TOR/streaming_sb_overview.php?$webargs&server=$hostname\">Return To Southbound Overview</a>";

function getQPlot($title,$ylabel,$whatcol,$hostname,$datapath,$table,$fromDate,$toDate)
{
  global $debug;


  $colNames = array_keys($whatcol);

  $sqlParam =
    array( 'title'      => $title,
       'ylabel'     => $ylabel,
       'useragg'    => 'true',
       'persistent' => 'false',
       'type'       => 'tsc',
       'querylist' =>
       array(
         array (
            'timecol' => 'time',
            'whatcol' => $whatcol,
            'tables'  => "$table, tor_streaming_datapath_names, servers, sites",
            'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.serverid = servers.id AND servers.hostname ='$hostname' 
                        AND $table.datapath = tor_streaming_datapath_names.id AND tor_streaming_datapath_names.name = '$datapath'",
            'qargs'   => array( 'site' )
            )
         )
       );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  $url =  $sqlParamWriter->getImgURL( $id,
                     "$fromDate 00:00:00", "$toDate 23:59:59",
                     true, 640, 240);
  if ( $debug ) { echo "<pre>getQPlot url=$url</pre>\n"; }
  return $url;
}



echo "<H1>Southbound Datapath: $datapath</H1>\n";
echo "<br>";

/**
 * Streaming SB Active Connection for specific datapath. 
 * The garpahs are presented:
 * Garphs:
 *      >count
 */
echo '<br><H2><a name="activeconnect"></a>Active Connections'; drawHelpLink('activeconnection'); echo "</H2>\n";
?>
<div id=activeconnection class=helpbox>
<?php
    drawHelpTitle("Active Connections", "activeconnection");
?>
<div class="helpbody">
Number of Active Connections for datapath: <?php echo $datapath ?><br>
</div>
</div>
<?php

$sincurl=getQPlot("Active Connections", "No.", array('count' =>'Count'), $hostname, $datapath, 'stream_in_active_connections', $fromDate, $toDate);
echo "$sincurl";

/**
 * Streaming SB Created Connections for specific datapath. 
 * The garpahs are presented:
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo "<br><br>";
echo '<H2><a name="createcon"></a>Created Connections'; drawHelpLink('createdconnections'); echo "</H2>\n";
?>
<div id=createdconnections class=helpbox>
<?php
    drawHelpTitle("Created Connections", "createdconnections");
?>
<div class="helpbody">
Number of the Created Connections for datapath: <?php echo $datapath ?><br>
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
</div>
</div>
<?php

$graphCCTable = new HTML_Table('border=0');

#$graphCCTable->addRow( array(getQPlot("Created Connections", "No.", array('count' =>'Total', 'AVG(count)' => 'AVG'), $hostname, $datapath,  'stream_in_created_connections', $fromDate, $toDate)) );
$graphCCTable->addRow( array(getQPlot("Created Connections 1 Minute Rate", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath,  'stream_in_created_connections', $fromDate, $toDate)) );
$graphCCTable->addRow( array(getQPlot("Created Connections 5 Minute Rate", "No.", array('five_min_rate' =>'Five Min Rate'), $hostname, $datapath,  'stream_in_created_connections', $fromDate, $toDate)) );
$graphCCTable->addRow( array(getQPlot("Created Connections 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath,  'stream_in_created_connections', $fromDate, $toDate)) );
$graphCCTable->addRow( array(getQPlot("Created Connections Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath,  'stream_in_created_connections', $fromDate, $toDate)) );

echo $graphCCTable->toHTML();

/**
 * Streaming SB Dropped Connections for specific datapath. 
 * The garpahs are presented:
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo "<br><br>";
echo '<H2><a name="droppedcon"></a>Dropped Connections'; drawHelpLink('droppedconnection'); echo "</H2>\n";
?>
<div id=droppedconnection class=helpbox>
<?php
    drawHelpTitle("Dropped Connections", "droppedconnection");
?>
<div class="helpbody">
Number of the Dropped Connections for datapath: <?php echo $datapath ?><br>
Graphs:<br>
<ul>
    <li>Total</li>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
</div>
</div>
<?php
$graphDCTable = new HTML_Table('border=0');

$graphDCTable->addRow( array(getQPlot("Dropped Connections", "No.", array('count' =>'Count'), $hostname, $datapath,  'stream_in_dropped_connections', $fromDate, $toDate)) );
$graphDCTable->addRow( array(getQPlot("Dropped Connections 1 Minute Rate", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath,  'stream_in_dropped_connections', $fromDate, $toDate)) );
$graphDCTable->addRow( array(getQPlot("Dropped Connections 5 Minute Rate", "No.", array('five_min_rate' =>'Five Min Rate'), $hostname, $datapath,  'stream_in_dropped_connections', $fromDate, $toDate)) );
$graphDCTable->addRow( array(getQPlot("Dropped Connections 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath,  'stream_in_dropped_connections', $fromDate, $toDate)) );
$graphDCTable->addRow( array(getQPlot("Dropped Connections Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath,  'stream_in_dropped_connections', $fromDate, $toDate)) );

echo $graphDCTable->toHTML();

/**
 * Streaming NB Events Processed for specific datapath. 
 * The garpahs are presented:
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo "<br><br>";
echo '<H2><a name="eventpro"></a>Events Processed'; drawHelpLink('eventprocessed'); echo "</H2>\n";
?>
<div id=eventprocessed class=helpbox>
<?php
    drawHelpTitle("Event Processed", "eventprocessed");
?>
<div class="helpbody">
Number of the Events Processed for datapath: <?php echo $datapath ?><br>
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
</div>
</div>
<?php

$graphEPTable = new HTML_Table('border=0');

$graphEPTable->addRow( array(getQPlot("Events Processed 1 Minute Rate", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath,  'stream_in_events', $fromDate, $toDate)) );
$graphEPTable->addRow( array(getQPlot("Events Processed 5 Minute Rate", "No.", array('five_min_rate' =>'Five Min Rate'), $hostname, $datapath,  'stream_in_events', $fromDate, $toDate)) );
$graphEPTable->addRow( array(getQPlot("Events Processed 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath,  'stream_in_events', $fromDate, $toDate)) );
$graphEPTable->addRow( array(getQPlot("Events Processed Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath,  'stream_in_events', $fromDate, $toDate)) );

echo $graphEPTable->toHTML();

/**
 * Streaming NB South Bound Dropped Events for specific datapath. 
 * The garpahs are presented:
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo "<br><br>";
echo '<H2><a name="eventdsb"></a>South Bound Dropped Events'; drawHelpLink('sbdroppedevents'); echo "</H2>\n";
?>
<div id=sbdroppedevents class=helpbox>
<?php
    drawHelpTitle("South Bound Dropped Events", "sbdroppedevents");
?>
<div class="helpbody">
Number of the Dropped South Bound Events for datapath: <?php echo $datapath ?><br>
Graphs:<br>
<ul>
    <li>Total</li>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
</div>
</div>
<?php

$graphSBDETable = new HTML_Table('border=0');

$graphSBDETable->addRow( array(getQPlot("SB Dropped Events", "No.", array('count' =>'Min Rate'), $hostname, $datapath,  'stream_in_south_bound_dropped_events', $fromDate, $toDate)) );
$graphSBDETable->addRow( array(getQPlot("SB Dropped Events 1 Minute Rate", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath,  'stream_in_south_bound_dropped_events', $fromDate, $toDate)) );
$graphSBDETable->addRow( array(getQPlot("SB Dropped Events 5 Minute Rate", "No.", array('five_min_rate' =>'Five Min Rate'), $hostname, $datapath,  'stream_in_south_bound_dropped_events', $fromDate, $toDate)) );
$graphSBDETable->addRow( array(getQPlot("SB Dropped Events 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath,  'stream_in_south_bound_dropped_events', $fromDate, $toDate)) );
$graphSBDETable->addRow( array(getQPlot("SB Dropped Events Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath,  'stream_in_south_bound_dropped_events', $fromDate, $toDate)) );

echo $graphSBDETable->toHTML();

include PHP_ROOT . "/common/finalise.php";
?>
