<?php
$pageTitle = "FM CORBA NBI";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once 'HTML/Table.php';

class FmNbiTotal extends DDPObject {

    var $cols = array(
         array( 'key' => 'inst', 'label' => 'FM NBI Instance'),
         array( 'key' => 'eventsPresentInNotificationsList', 'label' => 'Events Present In NotificationsList', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'alarmsPresentInNotificationsList', 'label' => 'Alarms Present In NotificationsList', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'eventsSentToNotificationService', 'label' => 'Events Sent To NotificationService', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'alarmsSentToNotificationService', 'label' => 'Alarms Sent To NotificationService', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'eventsReceivedfromCorbaserverQueue', 'label' => 'Events Received From CORBA Server Queue', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'eventsReceivedfromFmNorthBoundQueue', 'label' => 'Events Received From FM North Bound Queue', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'alarmsReceivedfromCorbaserverQueue', 'label' => 'Alarms Received From CORBA Server Queue', 'formatter' => 'ddpFormatNumber'),
         array( 'key' => 'alarmsReceivedfromFmNorthBoundQueue', 'label' => 'Alarms Received From FM North Bound Queue', 'formatter' => 'ddpFormatNumber')
    );

    var $title = "FM NBI File Collection";

    function __construct() {
        parent::__construct("FMNBIFileCollection");
    }

    function getData() {
        global $date;
        global $site;
        $sql = "
            SELECT
                IFNULL(servers.hostname,'Totals') AS inst,
                MAX(eventsPresentInNotificationsList) AS eventsPresentInNotificationsList,
                SUM(alarmsSentToNotificationService) AS alarmsSentToNotificationService,
                SUM(alarmsReceivedfromCorbaserverQueue) AS alarmsReceivedfromCorbaserverQueue,
                SUM(eventsReceivedfromCorbaserverQueue) AS eventsReceivedfromCorbaserverQueue,
                MAX(alarmsPresentInNotificationsList) AS alarmsPresentInNotificationsList,
                Sum(eventsSentToNotificationService) AS eventsSentToNotificationService,
                SUM(eventsReceivedfromFmNorthBoundQueue) AS eventsReceivedfromFmNorthBoundQueue,
                SUM(alarmsReceivedfromFmNorthBoundQueue) AS alarmsReceivedfromFmNorthBoundQueue
            FROM
                enm_fmnbalarm_instr, sites, servers
            WHERE
                enm_fmnbalarm_instr.siteid = sites.id AND sites.name = '$site' AND
                enm_fmnbalarm_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                enm_fmnbalarm_instr.serverid = servers.id
            GROUP BY servers.hostname WITH ROLLUP
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site, $rootdir;

    if ( file_exists($rootdir . "/fm/alarmStatsNETable.html") ) {
        echo "<ul>\n";
        echo ' <li><a href="' . $php_webroot . "/alarmStats.php?" . $webargs . '">Alarm Statistics</a></ul>' . "\n";
        echo "</ul>\n";
    }

    $dailyTotalHelp = "DDP_Bubble_307_FM_NBI_Notifications";
    /* Daily Summary table */
    drawHeaderWithHelp( "Daily Totals", 1, "dailyTotalHelp", $dailyTotalHelp );
    $fmnbiTable = new FmNbiTotal();
    echo $fmnbiTable->getClientSortableTableStr();

    /* E2E Instrumentation Graphs */
    $e2eInstrumentationHelp = "DDP_Bubble_307_FM_NBI_Notifications";
    drawHeaderWithHelp( "FM NBI Notifications", 1, "e2eInstrumentationHelp", $e2eInstrumentationHelp );

    $graphTable = new HTML_Table("border=0");

    $instrGraphParams = array(
        'eventsPresentInNotificationsList' => array(
            'title' => 'Events Present In Notifications List(s)',
            'cols' => array('eventsPresentInNotificationsList'  => 'Events Present In Notifications List',
            ),
            'yaxis' => 'count'
        ),
        'alarmsPresentInNotificationsList' => array(
            'title' => 'Alarms Present In Notifications List',
            'cols' => array('alarmsPresentInNotificationsList' => 'Alarms Present In Notifications List',
            ),
            'yaxis' => 'count'
        ),
        'eventsSentToNotificationService' => array(
            'title' => 'Events Sent To Notification Service',
            'cols' => array('eventsSentToNotificationService' => 'Event Sent To Notification Service',
            ),
            'yaxis' => 'count'
        ),
        'alarmsSentToNotificationService' => array(
            'title' => 'Alarms Sent To Notification Service',
            'cols' => array('alarmsSentToNotificationService' => 'Alarms Sent To Notification Service',
            ),
            'yaxis' => 'count'
        ),
        'eventsReceivedfromCorbaserverQueue' => array(
            'title' => 'Events Received From CORBA server Queue',
            'cols' => array('eventsReceivedfromCorbaserverQueue' => 'Events Received From CORBA Server Queue',
            ),
            'yaxis' => 'count'
        ),
        'alarmsReceivedfromCorbaserverQueue' => array(
            'title' => 'Alarms Received From CORBA Server Queue',
            'cols' => array('alarmsReceivedfromCorbaserverQueue' => 'Alarms Received From CORBA Server Queue',
            ),
            'yaxis' => 'count'
        ),

        'eventsReceivedfromFmNorthBoundQueue' => array(
            'title' => 'Events Received From FM Northbound Queue',
            'cols' => array('eventsReceivedfromFmNorthBoundQueue' => 'Events Received From FM Northbound Queue',
            ),
            'yaxis' => 'count'
        ),
        'alarmsReceivedfromFmNorthBoundQueue' => array(
            'title' => 'Alarms Received From FM Northbound Queue',
            'cols' => array('alarmsReceivedfromFmNorthBoundQueue' => 'Alarms Received From FM Northbound Queue',
            ),
            'yaxis' => 'count'
        ),
        'failedAlarmsCount' => array(
            'title' => 'Failed Alarms Count',
            'cols' => array('failedAlarmsCount' => 'Failed Alarms Count',
            ),
            'yaxis' => 'count'
        ),


        'activeNMSSubscriptionsCount' => array(
            'title' => 'Total number of NMS subscriptions',
            'cols' => array('activeNMSSubscriptionsCount' => 'Total number of NMS subscriptions',
            ),
            'yaxis' => 'count'
        ),
        'averageLatency' => array(
            'title' => 'Average Latency',
            'cols' => array('alarmLatency DIV latencyAlarmCount' => 'Average Latency',
            ),
            'yaxis' => 'Time(Seconds)'
        )
    );

    $sqlParamWriter = new SqlPlotParam();
    foreach ( $instrGraphParams as $instrGraphParam ) {
        $row = array();
        $sqlParam = array(
            'title'  => $instrGraphParam['title'],
            'ylabel'     => $instrGraphParam['yaxis'],
            'useragg'    => 'true',
            'persistent' => 'false',
            'forcelegend' => 'true',
            'querylist' => array(
                               array (
                'timecol' => 'time',
                'multiseries'=> 'servers.hostname',
                'whatcol' => $instrGraphParam['cols'],
                'tables'  => "enm_fmnbalarm_instr, sites, servers",
                'where'   => "enm_fmnbalarm_instr.siteid = sites.id AND sites.name = '%s' AND enm_fmnbalarm_instr.serverid = servers.id",
                'qargs'   => array( 'site' )
                )
            )
        );
        if ( array_key_exists('type',$instrGraphParam) ) {
            $sqlParam['type'] = $instrGraphParam['type'];
        } else {
            $sqlParam['type'] = 'sb';
            $sqlParam['presetagg'] = 'SUM:Per Minute';
        }

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();

    /* Generic JMX */
    drawHeaderWithHelp("Generic JMX", 2, "GenericJMX", "DDP_Bubble_194_Generic_JMX_Help");
    foreach ( enmGetServiceInstances($statsDB,$site,$date,"nbalarmirpagentcorba") as $server => $id ) {
        $jmxObject = new GenericJMX($statsDB, $site, $server, "nbalarmirpagentcorba", $date, $date, 240, 480);
        $header[] = $server;
        $jmxGraphArray[] = $jmxObject->getGraphArray();
    }

    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($header, null, 'th');

    /* For each graph type (specified by index), add all JMX's graphs to row and add row to table */
    for($i = 0; $i < count($jmxGraphArray[0]); $i++) {
        $row = array();
        foreach ( $jmxGraphArray as $graphSet ) {
            $row[] = $graphSet[$i];
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();

}

$statsDB = new StatsDB();
mainFlow($statsDB);

include PHP_ROOT . "/common/finalise.php";

?>
