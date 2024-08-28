<?php
$pageTitle = "JMS Queue Metrics";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$fromDate = $date;
$toDate = $date;
$sqlParamWriter = new SqlPlotParam();
$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
      $fromDate = $_GET['start'];
      $toDate = $_GET['end'];
}

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}

$jmsQueueMetricsTitleHelp = <<<EOT
This is to analyze the JMS Queue Metrics of Glassfish 2nd domain on Eniq Events.
EOT;
drawHeaderWithHelp("JMS Queue Metrics", 1, 'jmsQueueMetricsTitleHelp', $jmsQueueMetricsTitleHelp);

$dailyTotalsHelp = <<<EOT
  This shows the daily table for total number of messages for 3g/3gRadio/4g/mss event queue.
EOT;
drawHeaderWithHelp("Daily Totals", 2, "dailyTotalsHelp", $dailyTotalsHelp);

$table = new HTML_Table("border=1");
$table->addRow( array('Queue Type', 'Total Events') );
$dailyQueueTotal = $statsDB->queryRow("
    SELECT
     sum(eventqueue_total_3g_events),
     sum(eventqueue_total_3g_radio_events),
     sum(eventqueue_total_4g_events),
     sum(eventqueue_total_mss_events)
    FROM
     event_notification_succ_aggr_jmx_stats, sites
    WHERE
     sites.name = '$site' AND
     sites.id = event_notification_succ_aggr_jmx_stats.siteid AND
     event_notification_succ_aggr_jmx_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    ");

$table->addRow( array('3G', $dailyQueueTotal[0]) );
$table->addRow( array('3G Radio', $dailyQueueTotal[1]) );
$table->addRow( array('4G', $dailyQueueTotal[2]) );
$table->addRow( array('MSS', $dailyQueueTotal[3]) );
$total_events=$dailyQueueTotal[0] + $dailyQueueTotal[1] + $dailyQueueTotal[2] + $dailyQueueTotal[3];
$table->addRow( array('Total', $total_events));
echo $table->toHTML();

$listGraph = array(
    'total_events' => array(
        'title'       => 'Events received',
        'graphType'   => 'sb',
        'yLabel'      => 'Number of Events',
        'columnNames' => array(
            'eventqueue_total_3g_events'       => '3G',
            'eventqueue_total_3g_radio_events' => '3G Radio',
            'eventqueue_total_4g_events'       => '4G',
            'eventqueue_total_mss_events'      => 'MSS'
        )
    ),
    'queue_size' => array(
        'title'       => 'Queue Size',
        'graphType'   => 'tsc',
        'yLabel'      => 'Number of messages',
        'columnNames' => array(
            'eventqueue_3g_queue_size'       => '3G',
            'eventqueue_3g_radio_queue_size' =>  '3G Radio',
            'eventqueue_4g_queue_size'       => '4G',
            'eventqueue_mss_queue_size'      => 'MSS'
        )
    ),
    'num_msgs_in'     => array(
        'title'       => 'Messages received',
        'graphType'   => 'sb',
        'yLabel'      => 'Number of messages',
        'columnNames' => array(
            '3g_jms_num_msgs_in'       => '3G',
            '3g_radio_jms_num_msgs_in' => '3G Radio' ,
            '4g_jms_num_msgs_in'       => '4G',
            'mss_num_msgs_in'          => 'MSS'
        )
    ),
    'msg_bytes_in' => array(
        'title'       => 'Messages Received (In Bytes)',
        'graphType'   => 'sb',
        'yLabel'      => 'Number of bytes',
        'columnNames' => array(
            '3g_jms_msg_bytes_in'       => '3G',
            '3g_radio_jms_msg_bytes_in' => '3G Radio',
            '4g_jms_msg_bytes_in'       => '4G',
            'mss_msg_bytes_in'          => 'MSS'
        )
    ),
    "num_msgs" => array(
        'title'       => 'Queue Size',
        'graphType'   => 'tsc',
        'yLabel'      => 'Number of messages',
        'columnNames' => array(
            '3g_jms_num_msgs'       => '3G',
            '3g_radio_jms_num_msgs' => '3G Radio',
            '4g_jms_num_msgs'       => '4G',
            'mss_num_msgs'          => 'MSS'
        )
    )
);

foreach ($listGraph as $metricGroup => $metricGroupInformation) {
    if ($metricGroup == "num_msgs_in"){
        $hourlyTotalsJMSQueueHelp = <<<EOT
        This shows the hourly graph for message bytes received, number of messages received and number of messages in queue.
EOT;
        drawHeaderWithHelp("Hourly Totals: JMS Queue", 2, 'hourlyTotalsJMSQueueHelp', $hourlyTotalsJMSQueueHelp);
    }
    elseif ($metricGroup == "total_events"){
        $hourlyTotalsEventQueueHelp = <<<EOT
        This shows the hourly graph for total number of messages and number of messages in queue.
EOT;
        drawHeaderWithHelp("Hourly Totals: Event Notification Queue", 2, 'hourlyTotalsEventQueueHelp', $hourlyTotalsEventQueueHelp);
    }
    drawGraph($metricGroupInformation);
}

function drawGraph($metricGroupInformation) {
    global $sqlParamWriter;
    global $site;
    global $date;

    $sqlParam = array(
        'title'       => $metricGroupInformation['title'],
        'ylabel'      => $metricGroupInformation['yLabel'],
        'type'        => $metricGroupInformation['graphType'],
        'sb.barwidth' => '3600',
        'presetagg'   => 'SUM:Hourly',
        'persistent'  => 'true',
        'useragg'     => 'true',
        'querylist'   =>
            array(
                array(
                    'timecol' => 'time',
                    'whatcol' => $metricGroupInformation['columnNames'],
                    'tables'  => 'event_notification_succ_aggr_jmx_stats,sites',
                    'where'   => "event_notification_succ_aggr_jmx_stats.siteid = sites.id AND sites.name = '%s'",
                    'qargs'   => array( 'site' )
                )
            )
    );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url =  $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240);
    echo "$url<br><br><br>";
}
include "../common/finalise.php";
?>
