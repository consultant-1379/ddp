<?php
ob_start();
$pageTitle = "Streaming SB Overview";

include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

$hostname=$_GET["server"];
?>

<H1>Southbound Overview</H1>
<li><a href="#activeconnect">Active Connections</a></li>
<li><a href="#bytespro">Bytes Processed</a></li>
<li><a href="#createcon">Created Connections</a></li>
<li><a href="#droppedcon">Dropped Connections</a></li>
<li><a href="#eventpro">Events Processed</a></li>
<li><a href="#eventdsb">South Bound Dropped Events</a></li>


<br><br>
<H2>Southbound Datapaths</H2>
<?php

/**
 * The following query builds up a list of the dapaths and then create a link for each datapath
 * which can call the streaming_soutbound.php file.
 *
 * ARGS Passed to streaming_soutbound.php:
 * @webargs      date,site
 * @server       hostname of the peer server
 * @datapath     The name of the datapath 
 */
$row = $statsDB->query("
    SELECT tor_streaming_datapath_names.name 
    FROM stream_in_active_connections, tor_streaming_datapath_names, servers, sites WHERE
    stream_in_active_connections.siteid = sites.id AND sites.name = '" . $site . "' AND
    stream_in_active_connections.serverid = servers.id AND servers.hostname ='$hostname' AND
    stream_in_active_connections.datapath =  tor_streaming_datapath_names.id AND
    stream_in_active_connections.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
    GROUP BY tor_streaming_datapath_names.name
    ");

echo "<br><ul>";
    while($row = $statsDB->getNextRow()) {
        echo "<li><a href=\"$php_webroot/TOR/streaming_southbound.php?$webargs&server=$hostname&datapath=$row[0]\">$row[0]</a></li>";
    }
echo "</ul>";


function getQPlot($title,$ylabel,$whatcol,$hostname,$table,$column,$fromDate,$toDate)
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
            'multiseries'=> 'CONCAT(tor_streaming_datapath_names.name, "-", tor_stream_out_datapath_id.name)',
            'whatcol' => $whatcol,
            'tables'  => "$table, tor_streaming_datapath_names, tor_stream_out_datapath_id, servers, sites",
            'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.serverid = servers.id AND servers.hostname ='$hostname' 
                        AND $table.datapath = tor_streaming_datapath_names.id 
                        GROUP BY HOUR($table.time), MINUTE($table.time), $table.datapath",
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


/**
 * Overview of Streaming Southbound Total Active Connections.
 * Displays Total Events and Total Events for Fifteen Minute Period
 */
echo '<br><H2><a name="activeconnect"></a>Active Connections'; drawHelpLink('activeconnection'); echo "</H2>\n";
?>
<div id=activeconnection class=helpbox>
<?php
    drawHelpTitle("Active Connections", "activeconnection");
?>
<div class="helpbody">
Overview of the total number of Active Connections for every datapath.<br> 
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get an exact figure, look at the specific southbound datapath.
</div>
</div>
<?php

$activeConnectionsurl=getQPlot("Active Connections", "No.", array('AVG(count)' => 'Total'), $hostname, 'stream_in_active_connections', 'count', $fromDate, $toDate);
echo "$activeConnectionsurl<br><br><br>";


/**
 * Overview of Streaming Created Connections for every datapath. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >count
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<br><H2><a name="createcon"></a>Created Connections'; drawHelpLink('createdconnections'); echo "</H2>\n";
?>
<div id=createdconnections class=helpbox>
<?php
    drawHelpTitle("Created Connections", "createdconnections");
?>
<div class="helpbody">
Overview of the Created Connections for every datapath.<br> 
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
</ul>             
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get an exact figure, look at the specific southbound datapath.
</div>
</div>
<?php

$countCCurl=getQPlot("Created Connections", "No.", array('AVG(count)' =>'Total'), $hostname, 'stream_in_created_connections', 'count', $fromDate, $toDate);
echo "$countCCurl<br><br><br>";
$minCCurl=getQPlot("Created Connections 1 Minute Rate", "No.", array('AVG(min_rate)' =>'Min Rate'), $hostname, 'stream_in_created_connections', 'min_rate', $fromDate, $toDate);
echo "$minCCurl<br><br><br>";
$fiveCCurl=getQPlot(" Created Connections 5 Minute Rate", "No.", array('AVG(five_min_rate)' =>'Total'), $hostname, 'stream_in_created_connections', 'five_min_rate', $fromDate, $toDate);
echo "$fiveCCurl<br><br><br>";
$fifCCurl=getQPlot(" Created Connections 15 Minute Rate", "No.", array('AVG(fif_min_rate)' =>'Total'), $hostname, 'stream_in_created_connections', 'fif_min_rate', $fromDate, $toDate);
echo "$fifCCurl<br><br><br>";
$meanCCurl=getQPlot("Created Connections Mean Rate", "No.", array('AVG(mean_rate)' =>'Total'), $hostname, 'stream_in_created_connections', 'mean_rate', $fromDate, $toDate);
echo "$meanCCurl<br><br><br>";

/**
 * Overview of Streaming Connections Dropped for every datapath. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >count
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<br><H2><a name="droppedcon"></a>Dropped Connections'; drawHelpLink('droppedconnection'); echo "</H2>\n";
?>
<div id=droppedconnection class=helpbox>
<?php
    drawHelpTitle("Dropped Connections", "droppedconnection");
?>
<div class="helpbody">
Overview of the Dropped Connections for every datapath.<br> 
Graphs:<br>
<ul>
    <li>Total</li>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>            
    <li>Fifteen Minute Rate</li>         
    <li>Mean Rate</li>         
</ul>             
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get an exact figure, look at the specific southbound datapath.
</div>
</div>
<?php

$countDCurl=getQPlot("Dropped Connections", "No.", array('AVG(count)' =>'Min Rate'), $hostname, 'stream_in_dropped_connections', 'count', $fromDate, $toDate);
echo "$countDCurl<br><br><br>";
$minDCurl=getQPlot("Dropped Connections 1 Minute Rate", "No.", array('AVG(min_rate)' =>'Min Rate'), $hostname, 'stream_in_dropped_connections', 'min_rate', $fromDate, $toDate);
echo "$minDCurl<br><br><br>";
$fiveDCurl=getQPlot("Dropped Connections 5 Minute Rate", "No.", array('AVG(five_min_rate)' =>'Total'), $hostname, 'stream_in_dropped_connections', 'five_min_rate', $fromDate, $toDate);
echo "$fiveDCurl<br><br><br>";
$fifDCurl=getQPlot("Dropped Connections 15 Minute Rate", "No.", array('AVG(fif_min_rate)' =>'Total'), $hostname, 'stream_in_dropped_connections', 'fif_min_rate', $fromDate, $toDate);
echo "$fifDCurl<br><br><br>";
$meanDCurl=getQPlot("Dropped Connections Mean Rate", "No.", array('AVG(mean_rate)' =>'Total'), $hostname, 'stream_in_dropped_connections', 'mean_rate', $fromDate, $toDate);
echo "$meanDCurl<br><br><br>";

/**
 * Overview of Streaming Events Processed for every datapath. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<br><H2><a name="eventpro"></a>Events Processed'; drawHelpLink('eventprocessed'); echo "</H2>\n";
?>
<div id=eventprocessed class=helpbox>
<?php
    drawHelpTitle("Event Processed", "eventprocessed");
?>
<div class="helpbody">
Overview of the Events Processed for every datapath.<br>
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>          
</ul>
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get an exact figure, look at the specific southbound datapath.
</div>
</div>
<?php

$minEPurl=getQPlot("Events Processed 1 Minute Rate", "No.", array('AVG(min_rate)' =>'Min Rate'), $hostname, 'stream_in_events', 'min_rate', $fromDate, $toDate);
echo "$minEPurl<br><br><br>";
$fiveEPurl=getQPlot("Events Processed 5 Minute Rate", "No.", array('AVG(five_min_rate)' =>'Total'), $hostname, 'stream_in_events', 'five_min_rate', $fromDate, $toDate);
echo "$fiveEPurl<br><br><br>";
$fifEPurl=getQPlot("Events Processed 15 Minute Rate", "No.", array('AVG(fif_min_rate)' =>'Total'), $hostname, 'stream_in_events', 'fif_min_rate', $fromDate, $toDate);
echo "$fifEPurl<br><br><br>";
$meanEPurl=getQPlot("Events Processed Mean Rate", "No.", array('AVG(mean_rate)' =>'Total'), $hostname, 'stream_in_events', 'mean_rate', $fromDate, $toDate);
echo "$meanEPurl<br><br><br>";

/**
 * Overview of Streaming South Bound Dropped Events for every datapath. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >count
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<br><H2><a name="eventdsb"></a>South Bound Dropped Events'; drawHelpLink('sbdroppedevents'); echo "</H2>\n";
?>
<div id=sbdroppedevents class=helpbox>
<?php
    drawHelpTitle("South Bound Dropped Events", "sbdroppedevents");
?>
<div class="helpbody">
Overview of the Dropped South Bound Events for every datapath.<br>
Graphs:<br>
<ul>
    <li>Total</li>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>          
</ul>
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get an exact figure, look at the specific southbound datapath.
</div>
</div>
<?php

$countSBDEurl=getQPlot("SB Dropped Events", "No.", array('AVG(count)' =>'Min Rate'), $hostname, 'stream_in_south_bound_dropped_events', 'min_rate', $fromDate, $toDate);
echo "$countSBDEurl<br><br><br>";
$minSBDEurl=getQPlot("SB Dropped Events 1 Minute Rate", "No.", array('AVG(min_rate)' =>'Min Rate'), $hostname, 'stream_in_south_bound_dropped_events', 'min_rate', $fromDate, $toDate);
echo "$minSBDEurl<br><br><br>";
$fiveSBDEurl=getQPlot("SB Dropped Events 5 Minute Rate", "No.", array('AVG(five_min_rate)' =>'Total'), $hostname, 'stream_in_south_bound_dropped_events', 'five_min_rate', $fromDate, $toDate);
echo "$fiveSBDEurl<br><br><br>";
$fifSBDEurl=getQPlot("SB Dropped Events 15 Minute Rate", "No.", array('AVG(fif_min_rate)' =>'Total'), $hostname, 'stream_in_south_bound_dropped_events', 'fif_min_rate', $fromDate, $toDate);
echo "$fifSBDEurl<br><br><br>";
$meanSBDEurl=getQPlot("SB Dropped Events Mean Rate", "No.", array('AVG(mean_rate)' =>'Total'), $hostname, 'stream_in_south_bound_dropped_events', 'mean_rate', $fromDate, $toDate);
echo "$meanSBDEurl<br><br><br>";

include PHP_ROOT . "/common/finalise.php";
?>
