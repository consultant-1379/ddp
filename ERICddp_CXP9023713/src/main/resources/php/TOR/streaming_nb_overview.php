<?php
  $pageTitle = "Streaming NB Overview";

include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

$hostname=$_GET["server"];

?>

<H1>NorthBound Overview</H1>
<li><a href="#eventssent">Events Sent</a></li>
<li><a href="#eventslost">Events Lost</a></li>
<li><a href="#eventsfiltered">Events Filtered</a></li>

<?php
echo "<br><H2>NorthBound Datapaths</H2>\n";

/**
 * The following query builds up a list of the dapaths and then create a link for each datapath
 * which can call the streaming_soutbound.php file.
 *
 * ARGS Passed to streaming_soutbound.php:
 * @webargs      date,site
 * @server       hostname of the peer server
 * @datapath     The name of the datapath 
 * @datapath_id  The id of the datapath (e.g. g_2_c_101)
 */
$row = $statsDB->query("
    SELECT tor_streaming_datapath_names.name,  tor_stream_out_datapath_id.name 
    FROM stream_out_events_sent, sites, servers, tor_streaming_datapath_names, 
        tor_stream_out_datapath_id
    WHERE
        stream_out_events_sent.siteid = sites.id AND sites.name = '" . $site . "' AND
        stream_out_events_sent.serverid = servers.id AND servers.hostname ='$hostname' AND
        stream_out_events_sent.datapath = tor_streaming_datapath_names.id AND
        stream_out_events_sent.datapath_id = tor_stream_out_datapath_id.id AND
        stream_out_events_sent.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
    GROUP BY tor_streaming_datapath_names.name, tor_stream_out_datapath_id.name
    ");

echo "<ul>";
    while($row = $statsDB->getNextRow()) {
        echo "<li><a href=\"$php_webroot/TOR/streaming_northbound.php?$webargs&server=$hostname&datapath=$row[0]&id=$row[1]\">$row[0]-$row[1]</a></li>";
    }
echo "</ul><br>";


function getQPlot($title,$ylabel,$whatcol,$hostname,$table,$column,$fromDate,$toDate)
{
  global $debug;


  $colNames = array_keys($whatcol);

  $sqlParam =
    array( 'title'      => $title,
       'ylabel'     => $ylabel,
       'useragg'    => 'false',
       'presetagg'   => 'AVG:Per Minute',
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
                        AND $table.datapath_id = tor_stream_out_datapath_id.id 
                        ",
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
 * Overview of Streaming NB Events Sent. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<H2><a name="eventssent"></a>Events Sent'; drawHelpLink('eventsent'); echo "</H2>\n";
?>
<div id=eventsent class=helpbox>
<?php
    drawHelpTitle("Events Sent", "eventsent");
?>
<div class="helpbody">
<b>Events Sent:</b><br>
Overview of the number of Events Sent for every datapath.<br> 
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get and exact figure look at the specific datapth.
</div>
</div>
<?php

$minsenturl=getQPlot("Minute Rate Events Sent", "No.", array('min_rate' => 'Minute Rate'), $hostname, 'stream_out_events_sent','min_rate', $fromDate, $toDate);
echo "$minsenturl<br><br><br>";
$fiveminsenturl=getQPlot("5 Minute Rate Events Sent", "No.", array('five_min_rate' =>'Five Minute Rate'), $hostname, 'stream_out_events_sent', 'five_min_rate', $fromDate, $toDate);
echo "$fiveminsenturl<br><br><br>";
$fifminsenturl=getQPlot("15 Minute Rate Events Sent", "No.", array('fif_min_rate' =>'Fifteen Minute Rate'), $hostname, 'stream_out_events_sent', 'fif_min_rate', $fromDate, $toDate);
echo "$fifminsenturl<br><br><br>";
$meansenturl=getQPlot("Mean Rate Events Sent", "No.", array('mean_rate' =>'Mean Rate'), $hostname, 'stream_out_events_sent', 'mean_rate', $fromDate, $toDate);
echo "$meansenturl<br><br><br>";

/**
 * Overview of Streaming NB Events Lost. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<br><H2><a name="eventslost"></a>Events Lost'; drawHelpLink('eventlost'); echo "</H2>\n";
?>
<div id=eventlost class=helpbox>
<?php
    drawHelpTitle("Events Lost", "eventlost");
?>
<div class="helpbody">
<b>Events Lost:</b><br>
Overview of the number of Events Lost for every datapath.<br>             
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get and exact figure look at the specific datapth.
</div>
</div>
<?php

$minlosturl=getQPlot("Minute Rate Events Lost", "No.", array('min_rate' => 'Minute Rate'), $hostname, 'stream_out_events_lost', 'min_rate', $fromDate, $toDate);
echo "$minlosturl<br><br><br>";
$fiveminlosturl=getQPlot("5 Minute Rate Events Lost", "No.", array('five_min_rate' =>'Five Minute Rate'), $hostname, 'stream_out_events_lost', 'five_min_rate', $fromDate, $toDate);
echo "$fiveminlosturl<br><br><br>";
$fifminlosturl=getQPlot("15 Minute Rate Events Lost", "No.", array('fif_min_rate' =>'Fifteen Minute Rate'), $hostname, 'stream_out_events_lost', 'fif_min_rate', $fromDate, $toDate);
echo "$fifminlosturl<br><br><br>";
$meanlosturl=getQPlot("Mean Rate Events Lost", "No.", array('mean_rate' =>'Mean Rate'), $hostname, 'stream_out_events_lost', 'mean_rate', $fromDate, $toDate);
echo "$meanlosturl<br><br><br>";

/**
 * Overview of Streaming NB Events Filtered. Data entries are every 10 seconds.
 * The garpahs are presented per minte so the avgerage of each minute is presented.
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo '<br><H2><a name="eventsfiltered"></a>Events Filtered'; drawHelpLink('eventfilter'); echo "</H2>\n";
?>
<div id=eventfilter class=helpbox>
<?php
    drawHelpTitle("Events Filtered", "eventfilter");
?>
<div class="helpbody">
<b>Events Filtered:</b><br>
Overview of the number of Events Filtered for every datapath.<br>             
Graphs:<br>
<ul>
    <li>Minute Rate</li>
    <li>Five Minute Rate</li>
    <li>Fifteen Minute Rate</li>
    <li>Mean Rate</li>
</ul>
<b><font color="red">Note:</font></b> Data is presented on a per min average. To get and exact figure look at the specific datapth.
</div>
</div>
<?php

$minlosturl=getQPlot("Events Filtered Minute Rate", "No.", array('min_rate' => 'Minute Rate'), $hostname, 'stream_out_events_filtered', 'min_rate', $fromDate, $toDate);
echo "$minlosturl<br><br><br>";
$fiveminlosturl=getQPlot("Events Filtered 5 Minute Rate", "No.", array('five_min_rate' =>'Five Minute Rate'), $hostname, 'stream_out_events_filtered', 'five_min_rate',  $fromDate, $toDate);
echo "$fiveminlosturl<br><br><br>";
$fifminlosturl=getQPlot("Events Filtered 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Minute Rate'), $hostname, 'stream_out_events_filtered', 'fif_min_rate', $fromDate, $toDate);
echo "$fifminlosturl<br><br><br>";
$meanlosturl=getQPlot("Events Filetered Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, 'stream_out_events_filtered', 'mean_rate',  $fromDate, $toDate);
echo "$meanlosturl<br><br><br>";

include PHP_ROOT . "/common/finalise.php";
?>
