<?php
if ( isset($_GET["chart"]) ) {
    $UI = false;
}

$pageTitle = "SEMA Statistics";
include "common/init.php";
include "common/init.php";
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();

#
# Redirect to qplot to display the request graph
#
function qPlot($title,$ylabel,$start,$end,$whatCol)
{
    global $site;
    $sqlParam = array( 'title'      => $title,
        'ylabel'     => $ylabel,
        'useragg'    => 'true',
        'persistent' => 'true',
        'querylist' =>
        array(
             array(
                'timecol' => 'time',
                'whatcol' => $whatCol,
                'tables'  => "sema_stats, sites",
                'where'   => "sema_stats.siteid = sites.id AND sites.name = '%s'",
                'qargs'   => array( 'site' )
            )
        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url =  $sqlParamWriter->getImgURL( $id,
                                    "$start", "$end",
                                    true, 640, 240);

    return $url;
}

#
# If the user has clicked on a graph
#
$args = "date=" . $date . "&dir=" . $dir . "&oss=" . $oss . "&site=" . $site;
$start = $date . " 00:00:00";
$end = $date . " 23:59:59";

if (isset($_GET['start']) && isset($_GET['end'])) {
     $args .= "&start=" . $_GET['start'] . "&end=" . $_GET['end'];
     $start = $_GET['start'];
     $end = $_GET['end'];
}
?>

<h1>SEMA Statistics <?=$date?></h1>
<form name=range method=get">
Start: <input type=text name=start value="<?=$start?>" />
End: <input type=text name=end value="<?=$end?>" />
<input type=hidden name="date" value="<?=$date?>" />
<input type=hidden name="dir" value="<?=$dir?>" />
<input type=hidden name="oss" value="<?=$oss?>" />
<input type=hidden name="site" value="<?=$site?>" />
<input type=submit name=submit value="update" />
</form>


<?php
echo "<h3>Total/Unsynced/Unconnected Nodes"; drawHelpLink("nodeListHelp"); echo "</h3>\n";
drawHelp("nodeListHelp", "Total/Unsynced/Unconnected Nodes",
        "
Metrics are taken from the sema_status logfile
<ul>
<li>Total num of nodes managed by SMA.</li>
<li>Num of SMARTEDGE nodes for which MeContext.mirrorMibSynchStatus UNSYNCHRONISED.</li>
<li>Num of SMARTEDGE nodes for which MeContext.connectionStatus == NEVER_CONNECTED.</li>
</ul>
");

echo qplot('Total/Unsynced/Unconnected Nodes','Count',$start,$end,
            array   (
                     'total_nodes' => 'total',
                     'unsynced_nodes' => 'unsynched',
                     'neverconnected_nodes' => 'unconnected'
                    )
            );
?>

<?php
echo "<h3>Threadpool Total/Executing/Waiting in JVM"; drawHelpLink("threadPoolHelp"); echo "</h3>\n";
drawHelp("threadPoolHelp", "Threadpool Total/Executing/Waiting in JVM",
        "
Metrics are taken from the sema_status logfile
<ul>
<li>Total: Total number of running threads in the JVM.</li>
<li>Executing: Total number of running threads in the JVM.</li>
<li>Waiting: Total number of waiting threads in the JVM.</li>
</ul>
");

echo qplot('Threadpool Total/Executing/Waiting','Count',$start,$end,
            array   (
                     'number_of_threads_system' => 'total',
                     'threadpool_executing' => 'executing',
                     'threadpool_waiting' => 'waiting'
                    )
            );
?>

<?php
echo "<h3>Threadpool2 Queued & Completed"; drawHelpLink("threadPool2Help"); echo "</h3>\n";
drawHelp("threadPool2Help", "Threadpool2 Queued & Completed",
        "
Metrics are taken from the sema_status logfile
<ul>
<li>Queued: Number of threads waiting in the queue.</li>
<li>Completed: Number of tasks completed during the last output period.</li>
</ul>
");

echo qplot('Threadpool2 Queued/Completed','Count',$start,$end,
            array   (
                     'threadpool2_completed' => 'completed',
                     'threadpool2_queued' => 'queued'
                    )
            );
?>

<?php
echo "<h3>NETOP Relpy Time Max/Min/Avg"; drawHelpLink("netopReplyHelp"); echo "</h3>\n";
drawHelp("netopReplyHelp", "NETOP Relpy Time Max/Min/Avg",
        "
Metrics are taken from the sema_status logfile
<ul>
<li>Maximum/Minimum/Average time needed for 
Netop to reply to a netconf request.</li>
</ul>
");

echo qplot('Netop Reply Time Max/Min/Avg','Time',$start,$end,
            array   (
                     'max_reply_time_netop' => 'max',
                     'min_reply_time_netop' => 'min',
                     'average_reply_time_netop' => 'avg'
                    )
            );
?>

<?php
echo "<h3>XSLT Mapping Duration Max/Min/Avg"; drawHelpLink("xsltMapHelp"); echo "</h3>\n";
drawHelp("xsltMapHelp", "XSLT Mapping Duration Max/Min/Avg",
        "
Metrics are taken from the sema_status logfile
<ul>
<li>Maximum/Minimum/Average duration of XSLT mapping.</li>
</ul>
");

echo qplot('XLST Mapping Duration Max/Min/Avg','Time',$start,$end,
            array   (
                     'max_xslt_map_time_smartedge' => 'max',
                     'min_xslt_map_time_smartedge' => 'min',
                     'average_xslt_map_time_smartedge' => 'avg'
                    )
            );
?>
<h3>ME Context Write Fail/Success/Remove/Avg Delay</h3>
<?php
echo qplot('ME Context Write Fail/Success/Remove/Avg Delay','Count',$start,$end,
            array   (
                     'nufailedmecontextwrites' => 'fail',
                     'nusuccmecontextwrites' => 'success',
                     'nuremovemedcontext' => 'remove',
                     'avedelaymecontextwrite' => 'avg delay'
                    )
            );
?>

<?php
echo "<h3>SMARTEDGE Node Syncs Started/Finished"; drawHelpLink("semaSyncHelp"); echo "</h3>\n";
drawHelp("semaSyncHelp", "SMARTEDGE Node Syncs Started/Finished",
        "
Metrics are taken from the sema_status logfile
<ul>
<li>Number of SMARTEDGE node synchronizations started during the last
output period.</li>
<li>Number of SMARTEDGE node synchronizations finished during the last
output period.</li>
<li>Number of adjustValid calls received in the last output period.</li>
</ul>
");

echo qplot('SMARTEDGE Node Syncs Started/Finished & adjustValid Calls','Count',$start,$end,
            array   (
                     'num_smartedge_syncs_finished_last_output_period' => 'finished',
                     'num_smartedge_syncs_started_last_output_period' => 'started',
                     'adjust_numcalls' => 'recieved adjustValid'
                    )
            );

include "common/finalise.php";
?>
