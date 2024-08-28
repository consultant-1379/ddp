<?php
  $pageTitle = "Streaming NB ";

include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$fromDate = $date;
$toDate = $date;
$statsDB = new StatsDB();

$hostname=$_GET["server"];
$datapath=$_GET["datapath"];
$id=$_GET["id"];

echo "<a href=\"$php_webroot/TOR/streaming_nb_overview.php?$webargs&server=$hostname\">Return To Northbound Overview</a>";

echo "<H1>Northbound Datapath $datapath-$id</H1>\n";
echo "<br>";

function getQPlot($title,$ylabel,$whatcol,$hostname,$datapath,$id,$table,$fromDate,$toDate)
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
            'tables'  => "$table, tor_streaming_datapath_names, tor_stream_out_datapath_id, servers, sites",
            'where'   => "$table.siteid = sites.id AND sites.name = '%s' AND $table.serverid = servers.id AND servers.hostname ='$hostname' 
                        AND $table.datapath = tor_streaming_datapath_names.id AND tor_streaming_datapath_names.name = '$datapath' 
                        AND $table.datapath_id = tor_stream_out_datapath_id.id AND tor_stream_out_datapath_id.name = '$id'",
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
 * Streaming NB Events Sent for specific datapath.
 * The garpahs are presented:
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo "<br>";
echo '<H2><a name="eventssent"></a>Events Sent'; drawHelpLink('eventsent'); echo "</H2>\n";
?>
<div id=eventsent class=helpbox>
<?php
    drawHelpTitle("Events Sent", "eventsent");
?>
<div class="helpbody">
<b>Events Sent:</b><br>
Number of Events Sent for datapath <?php echo "$datapath-$id" ?>.<br>
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

$graphSentTable = new HTML_Table('border=0');

$graphSentTable->addRow( array(getQPlot("Events Sent 1 Minute Rate", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath, $id, 'stream_out_events_sent', $fromDate, $toDate)) );
$graphSentTable->addRow( array(getQPlot("Events Sent 5 Minute Rate", "No.", array('five_min_rate' =>'Five Min Rate'), $hostname, $datapath, $id, 'stream_out_events_sent', $fromDate, $toDate)) );
$graphSentTable->addRow( array(getQPlot("Events Sent 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath, $id, 'stream_out_events_sent', $fromDate, $toDate)) );
$graphSentTable->addRow( array(getQPlot("Events Sent Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath, $id, 'stream_out_events_sent', $fromDate, $toDate)) );

echo $graphSentTable->toHTML();

/**
 * Streaming NB Events Lost for specific datapath.
 * The garpahs are presented:
 * Garphs:
 *      >minute rate
 *      >five minute rate
 *      >fifteen minute rate
 *      >mean rate
 */
echo "<br>";
echo '<br><H2><a name="eventslost"></a>Events Lost'; drawHelpLink('eventlost'); echo "</H2>\n";
?>
<div id=eventlost class=helpbox>
<?php
    drawHelpTitle("Events Lost", "eventlost");
?>
<div class="helpbody">
<b>Events Lost:</b><br>
Number of Events Lost for datapath <?php echo "$datapath-$id" ?>.<br>
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

$graphLostTable = new HTML_Table('border=0');

$graphLostTable->addRow( array(getQPlot("Events Lost", "No.", array('count' =>'Count'), $hostname, $datapath, $id, 'stream_out_events_lost', $fromDate, $toDate)) );
$graphLostTable->addRow( array(getQPlot("Events Lost 1 Minute Rate", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath, $id, 'stream_out_events_lost', $fromDate, $toDate)) );
$graphLostTable->addRow( array(getQPlot("Events Lost 5 Minute Rate", "No.", array('five_min_rate' =>'Fiive Min Rate'), $hostname, $datapath, $id, 'stream_out_events_lost', $fromDate, $toDate)) );
$graphLostTable->addRow( array(getQPlot("Events Lost 15 Minute Rate", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath, $id, 'stream_out_events_lost', $fromDate, $toDate)) );
$graphLostTable->addRow( array(getQPlot("Events Lost Mean Rate", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath, $id, 'stream_out_events_lost', $fromDate, $toDate)) );

echo $graphLostTable->toHTML();

/**
 * Streaming NB Events Filtered for specific datapath. 
 * The garpahs are presented:
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
Number of Events Filtered for datapath <?php echo "$datapath-$id" ?>.<br>
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

$graphTable = new HTML_Table('border=0');
$graphTable->addRow( array(getQPlot("Events Filtered 1 Minute Rate $datapath-$id", "No.", array('min_rate' =>'Min Rate'), $hostname, $datapath, $id, 'stream_out_events_filtered', $fromDate, $toDate)) );
$graphTable->addRow( array(getQPlot("Events Filtered 5 Minute Rate $datapath-$id", "No.", array('five_min_rate' =>'Five Min Rate'), $hostname, $datapath, $id, 'stream_out_events_filtered', $fromDate, $toDate)) );
$graphTable->addRow( array(getQPlot("Events Filtered 15 Minute Rate $datapath-$id", "No.", array('fif_min_rate' =>'Fifteen Min Rate'), $hostname, $datapath, $id, 'stream_out_events_filtered', $fromDate, $toDate)) );
$graphTable->addRow( array(getQPlot("Events Filtered Mean Rate $datapath-$id", "No.", array('mean_rate' =>'Mean Rate'), $hostname, $datapath, $id, 'stream_out_events_filtered', $fromDate, $toDate)) );

echo $graphTable->toHTML();

include PHP_ROOT . "/common/finalise.php";
?>
