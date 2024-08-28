<?php
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

$pageTitle = "Alarm Statistics";
if (isset($_GET['chart']) ) $UI = false;
include "common/init.php";

function formatRow($row)
{
    global $debug;
    if ($debug) {
    $rowStr = print_r($row, true);
    echo "<p>formatRow: rowStr=$rowStr</p>\n";
}

$tableRow = array( $row[0], $row[1], $row[2], $row[3] );
if ( $row[4] ) {
    $avgPerNode = sprintf( "%.0f", ($row[1] / $row[4]));
    $tableRow[] = $row[4];
    $tableRow[] = $avgPerNode;
} else {
    $tableRow[] = '';
    $tableRow[] = '';
}

if ( $row[5] ) {
    $tableRow[] = $row[5];
    $tableRow[] = $row[6];
    $tableRow[] = $row[7];
    $tableRow[] = $row[8];
    $tableRow[] = $row[9];
} else {
    $tableRow[] = '';
    $tableRow[] = '';
    $tableRow[] = '';
    $tableRow[] = '';
    $tableRow[] = '';
}

return $tableRow;
}

function getSummaryTable($statsDB,$site,$date)
{
    $statsDB->query("
        SELECT
          me_types.name, aebm.event_total, aebm.active, ROUND(aebm.event_total / aebm.active), aebm.total,
          aebm.event_x1, aebm.event_x2, aebm.event_x3, aebm.event_x4, aebm.event_x5
        FROM
          alarmevents_by_metype aebm, me_types, sites
        WHERE
          aebm.siteid = sites.id AND
          sites.name = '$site' AND
          aebm.date = '$date' AND
          aebm.me_typeid = me_types.id
        ORDER BY me_types.name
    ");

    $table = new HTML_Table('border=1');
    $table->addRow( array( 'Node Type', 'Total Alarm Events', 'Active Nodes',
             'Average Num Alarms Events/Active Nodes', 'Total Nodes',
             'Average Num Alarm Events/Total Nodes', 'New',
             'Changed', 'Ack State Changed', 'Cleared', 'Alarm List Rebuilt' ),
             null, 'th' );

    while($row = $statsDB->getNextRow() ) {
        $table->addRow( formatRow($row) );
    }

    $row = $statsDB->queryRow("
    SELECT '<B>Total</B>', SUM(aebm.event_total), SUM(aebm.active), ROUND(SUM(aebm.event_total) / SUM(aebm.active)), SUM(aebm.total),
    SUM(aebm.event_x1), SUM(aebm.event_x2), SUM(aebm.event_x3), SUM(aebm.event_x4), SUM(aebm.event_x5)
    FROM alarmevents_by_metype aebm, me_types, sites
    WHERE aebm.siteid = sites.id AND
    sites.name = '$site' AND
    aebm.date = '$date' AND
    aebm.me_typeid = me_types.id
    ");
    $table->addRow( formatRow($row) );

    return $table;
}

function getSyncTable($statsDB,$site,$date)
{
    $table = new HTML_Table('border=1');
    $table->getHeader()->addRow( array( 'Sync Result', 'Count' ), null, 'th' );

    $statsDB->query("
    SELECT fm_sync.result, COUNT(*)
    FROM fm_sync, sites
    WHERE fm_sync.siteid = sites.id AND sites.name = '$site' AND
    fm_sync.starttime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    GROUP BY result ");
    while($row = $statsDB->getNextRow() ) {
        $table->addRow( $row );
    }

    return $table;
}


#
# Main
#
$statsDB = new StatsDB();

if ( file_exists($rootdir . "/fm") ) {
    $rootdir = $rootdir . "/fm";
}
if ( $debug ) { echo "<p>rootdir=$rootdir</p>\n"; }

$plotdir = $stats_dir . $oss . "/" . $site . "/data/" . $dir . "/fm_plots";
$graphBase = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=fm_plots/";
$webroot = $webroot . "/fm";

$listLink = $php_webroot . "/alarmList.php?site=$site&dir=$dir&oss=$oss&list=";

$hasAlarmVerification = file_exists($rootdir . "/alarmVerification.html");

?>

<head>
    <title>Alarm Statistics</title>
</head>
<body>

<h1>Alarm Statistics</h1>
<ul>
<li><a href ="<?=$listLink?>alarmStatsNETable&cols=Event Count,Node,Top 5 Specific Problems">Alarm Events by Network Element</a></li>
<li><a href ="<?=$listLink?>alarmStatsSpecProblemTable&cols=Event Count,Specific Problem,Top 5 Nodes">Alarm Events by Specific Problem</a></li>

<?php
if ( file_exists($rootdir . "/1z1StatsNETable.html") ) {
    echo "<li><a href =\"" . $listLink . "1z1StatsNETable&cols=Event Count,Node,Top 5 Specific Problems\">1z1 Events by Network Element</a></li>\n";
    echo "<li><a href =\"" . $listLink . "1z1StatsSpecProblemTable&cols=Event Count,Specific Problem,Top 5 Nodes\">1z1 Events by Specific Problem</a></li>\n";
}

if ( file_exists($rootdir . "/fmdist_heap.jpg") ) {
    echo "<li><a href \"fmdist_heap\">Distribution Server Heap</a></li>\n";
}

if ( $hasAlarmVerification ) {
    echo "<li><a href =\"#verify\">Alarm List Verfication</a></li>\n";
}

echo "</ul>\n";

drawHeaderWithHelp("Alarm Event Stats",2,"Alarm Event Statistics","DDP_Bubble_35_ENM_FM_Alarm_Event_Stats");
//
// Display counters about the alarm events
//

// Previously we stored the calculated values on alarmCounters.html
if (file_exists($rootdir . "/alarmCounters.html" )) {
    ?>
    <p>Note: Average Num Alarm Events/Alive Nodes for RBS is calculated as ( (Num Rbs Alarm Events) / (Total Alive Nodes - (Active Rnc + Active Ranag)) )</p>
    <table border>
    <tr> <td><b>Node Type</b></td> <td><b>Total Alarm Events</b></td> <td><b>Total Active Nodes</b></td> <td><b>Average Num Alarms Events/Active Nodes</b></td> <td><b>Average Num Alarm Events/Alive Nodes</b></td></tr>
    <?php include($rootdir . "/alarmCounters.html"); ?>
    </table>

    <?php
    if (file_exists($rootdir . "/alarmCountersByEventType.html" )) {
        echo "<p>Counts by Alarm Event Type</p>\n";
        include($rootdir . "/alarmCountersByEventType.html");
    }
} else {
    // Now they are stored in alarmevents_by_metype in MySQL
    $table = getSummaryTable($statsDB,$site,$date);
    echo $table->toHTML();
} // end of file_exists alarmCounters


//
// Heartbeat failures
//
if (file_exists($rootdir . "/hb.jpg")) {
    $tableURL = "site=$site&dir=$dir&oss=$oss&file=fm/hb.html";
    echo "<H2>"; drawHelpLink("hb_failures"); echo "Heartbeat Failures</H2>\n";
    drawHelp("hb_failures", "Heatbeat Failures","
        A Heartbeat Failure alarm event occurs when FM connects or disconnects from a node,
        for example when the node restarts.
        <p />
        This is a plot of the Heartbeat Failure Events. Each position on the vertical axis
        represents a unique node. Each point represents a time when FM connected or disconnected
        from a node. If the graph shows a vertical line of points, this represents when a
        batch of nodes all connect/disconnect at the same time. If the graph shows a horizontal
        line of points, this represents a single node that FM is repeatly losing/regaining
        connection with.
");
    echo <<<EOT
    <img src="$webroot/hb.jpg" alt="">
    <p>Click <a href="$php_webroot/conn_disc.php?$tableURL">here</a> to see the nodes corresponding to the position on the vertical axis.</p>
EOT;
} // End of file_exists($rootdir . "/hb.jpg")


$syncTable = getSyncTable($statsDB,$site,$date);
if ( $syncTable->getRowCount() > 0 ) {
    echo "<H2>FM - Network Element Alarm List Synchronization</H2>\n";
    echo "<p>This information is extracted from the FM Alarm Log</p>\n";
    echo $syncTable->toHTML();
    echo "<p>The graph below shows the number of syncs performed per hour</p>\n";


    $sqlParam =
        array( SqlPlotParam::TITLE => 'FM Node Syncs/Hour' ,
            SqlPlotParam::Y_LABEL => 'Syncs',
            'type' => 'sb',
            'sb.barwidth' => '3600',
            'presetagg' => 'COUNT:Hourly',
            'persistent' => 'true',
            'querylist' =>
            array(
                array (
                    SqlPlotParam::TIME_COL => 'starttime',
                    SqlPlotParam::WHAT_COL => array ( '*' => 'Count' ),
                    SqlPlotParam::TABLES => "fm_sync, sites",
                    SqlPlotParam::WHERE => "fm_sync.siteid = sites.id AND sites.name = '%s'",
                    'qargs' => array( 'site' )
                )
            )
        );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) . "\n";

    echo "<br/>\n";
    $sqlParam['title'] = 'FM Node Syncs/Minute';
    $sqlParam['presetagg'] = 'COUNT:Per Minute';
    $sqlParam['sb.barwidth'] = '60';
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) . "\n";
}

$row = $statsDB->queryRow("SELECT COUNT(*) FROM hires_fm,sites
WHERE hires_fm.siteid = sites.id AND sites.name = '$site'
 AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
if ( $row[0] > 0 ) {
    $alarm_events_content ="
        This information is for alarms sent on the NB CORBA FM IRP which is extracted northbound NotificationIRP.
        <p>
            <li>X1 NOTIFY_FM_NEW_ALARM </li>
            <li>X2 NOTIFY_FM_CHANGED_ALARM</li>
            <li>X3 NOTIFY_FM_ACK_STATE_CHANGED</li>
            <li>X4 NOTIFY_FM_CLEARED_ALARM</li>
            <li>X5 NOTIFY_FM_ALARM_LIST_REBUILT</li>
        </p>
        For more details see:    <a href=\"http://www.etsi.org/deliver/etsi_TS/132100_132199/13211103/03.02.00_60/ts_13211103v030200p.pdf\">ETSI TS 132 111-3 V3.2.0.</a>";
    drawHeaderWithHelp( "FM - Alarm Events Per Minute", 2, "alarm_events", $alarm_events_content );

    $sqlParam =
    array( SqlPlotParam::TITLE => 'FM Alarm Events/Minute' ,
        SqlPlotParam::Y_LABEL => 'Events',
        'type' => 'sb',
        'useragg' => 'true',
        'sb.barwidth' => '60',
        'persistent' => 'false',
        'querylist' =>
        array(
            array(
                SqlPlotParam::TIME_COL => 'time',
                SqlPlotParam::WHAT_COL => array ( 'x1' => 'X1', 'x2' => 'X2', 'x3' => 'X3', 'x4' => 'X4', 'x5' => 'X5' ),
                SqlPlotParam::TABLES => "hires_fm, sites",
                SqlPlotParam::WHERE => "hires_fm.siteid = sites.id AND sites.name = '$site'",
                'qargs' => array( 'site' )
                )
            )
        );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) . "\n";

echo "<br>\n";
$sqlParam =
    array( SqlPlotParam::TITLE => 'FM Latency' ,
        SqlPlotParam::Y_LABEL => 'Seconds',
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' =>
        array(
            array(
                SqlPlotParam::TIME_COL => 'time',
                SqlPlotParam::WHAT_COL => array ( 'avgdelay' => "Average", "maxdelay" => "Max" ),
                SqlPlotParam::TABLES => "hires_fm, sites",
                SqlPlotParam::WHERE => "hires_fm.siteid = sites.id AND sites.name = '$site'",
                'qargs' => array( 'site' )
                )
            )
        );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) . "\n";

}


# Alarm List Statistics
# Smalte, so include it here
if ( file_exists($rootdir . "/alarmListCounters.html") ) {
    ?>
    <H2><a name="#liststat"></a>Alarm List Stats</H2>
    <table border>
    <tr> <th>Node Type</th> <th>Alarm Count</th> </tr>
    <?php
    include($rootdir . "/alarmListCounters.html");
    echo "</table>\n";
}

if (file_exists($rootdir . "/alarmStatsNETableByEventType.html" )) {
    echo "<p><a href=\"" . $listLink . "alarmStatsNETableByEventType\">Counts by Alarm Event Type</a></p>\n";
}

if ( $hasAlarmVerification ) {
    echo "<H2><a name=\"verify\"></a>Alarm List Verification</H2>\n";
    include($rootdir . "/alarmVerification.html");
}

if ( file_exists($rootdir . "/fmdist_heap.jpg") ) {
    echo "<a name=\"fmdist_heap\"></a><H2>Distribution Server Heap</H2>\n";
    echo "<img src=\"" . $webroot . "/fmdist_heap.jpg\" alt=\"\" >\n";
}

include "common/finalise.php";
?>
