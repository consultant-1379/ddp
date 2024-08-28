<?php
$pageTitle = "FM SNMP NBI";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/GenericJMX.php";

class ProcessingDailyTotalsTable extends DDPObject {
  var $cols = array(
                  array('key' => 'inst', 'label' => 'Instance'),
                  array('key' => 'totalNumberOfSubscriptions', 'label' => 'Total Number of subscriptions', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'eventsConsumedFromInputTopic', 'label' => 'Notifications consumed from FMAlarmOutBusTopic', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'eventsInDispatcherQueue', 'label' => 'Notifications present in Dispatcher_Queue', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'alarmsSentToNotifierQueues', 'label' => 'Alarms sent to Notifier Queues', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'alertsSentToNotifierQueues', 'label' => 'Alerts sent to Notifier Queues', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'alarmTrapsSentToNMS', 'label' => 'Alarm Traps sent to NMS', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'alertTrapsSentToNMS', 'label' => 'Alert Traps sent to NMS', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'numberOfAlarmsOnSnmpAgentMib', 'label' => 'Number of Alarms present in the SNMP Agent MIB', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'numberOfAlertsOnSnmpAgentMib', 'label' => 'Number of Alerts present in the SNMP Agent MIB', 'formatter' => 'ddpFormatNumber')
      );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("FmSnmpNbiDailyTotals");
    }

    function getData() {
        global $date, $site;
$sql = "
SELECT
 IFNULL(servers.hostname,'All Instances') as inst,
 SUM(efbi.totalNumberOfSubscriptions) as totalNumberOfSubscriptions,
 SUM(efbi.eventsConsumedFromInputTopic) as eventsConsumedFromInputTopic,
 SUM(efbi.eventsInDispatcherQueue) as eventsInDispatcherQueue,
 SUM(efbi.alarmsSentToNotifierQueues) as alarmsSentToNotifierQueues,
 SUM(efbi.alertsSentToNotifierQueues) as alertsSentToNotifierQueues,
 SUM(efbi.alarmTrapsSentToNMS) as alarmTrapsSentToNMS,
 SUM(efbi.alertTrapsSentToNMS) as alertTrapsSentToNMS,
 SUM(efbi.numberOfAlarmsOnSnmpAgentMib) as numberOfAlarmsOnSnmpAgentMib,
 SUM(efbi.numberOfAlertsOnSnmpAgentMib) as numberOfAlertsOnSnmpAgentMib
FROM
 enm_fmsnmpnbi_instr efbi, sites, servers
WHERE
 efbi.siteid = sites.id
 AND sites.name = '$site'
 AND efbi.serverid = servers.id
 AND efbi.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname WITH ROLLUP";
    $this->populateData($sql);
    return $this->data;
    }
}

function getInstrParams() {
    return array(
        array('totalNumberOfSubscriptions' => array(
            'title' => 'Number of subscriptions',
            'type' => 'sb',
            'cols' => array('totalNumberOfSubscriptions' => 'Total Number of subscriptions')
                                 ),
            'eventsConsumedFromInputTopic' => array(
                'title' => 'Notifications consumed from FMAlarmOutBusTopic',
                'type' => 'sb',
                'cols' => array('eventsConsumedFromInputTopic' => 'Total Notifications consumed from FMAlarmOutBusTopic')
                                 )
        ),
        array('eventsInDispatcherQueue' => array(
            'title' => 'Notifications present in Dispatcher_Queue',
            'type' => 'sb',
            'cols' => array('eventsInDispatcherQueue' => 'Total Notifications present in Dispatcher_Queue')
                                   ),
            'alarmsSentToNotifierQueues' => array(
                'title' => 'Alarms sent to Notifier Queues',
                'type' => 'sb',
                'cols' => array('alarmsSentToNotifierQueues' => 'Total Alarms sent to Notifier Queues')
                                          )
        ),
        array('alertsSentToNotifierQueues' => array(
            'title' => 'Alerts sent to Notifier Queues',
            'type' => 'sb',
            'cols' => array('alertsSentToNotifierQueues' => 'Total Alerts sent to Notifier Queues')
                                   ),
            'alarmTrapsSentToNMS' => array(
                'title' => 'Alarm Traps sent to NMS',
                'type' => 'sb',
                'cols' => array('alarmTrapsSentToNMS' => 'Total Alarm Traps sent to NMS')
                                          )
        ),
        array('alertTrapsSentToNMS' => array(
                'title' => 'Alert Traps sent to NMS',
                'type' => 'sb',
                'cols' => array('alertTrapsSentToNMS' => 'Total Alert Traps sent to NMS')
                                           ),
            'numberOfAlarmsOnSnmpAgentMib' => array(
                'title' => 'Number of Alarms present in the SNMP Agent MIB',
                'type' => 'sb',
                'cols' => array('numberOfAlarmsOnSnmpAgentMib' => 'Total Number of Alarms present in the SNMP Agent MIB')
                                           )
        ),
        array('numberOfAlertsOnSnmpAgentMib' => array(
            'title' => 'Number of Alerts present in the SNMP Agent MIB',
            'type' => 'sb',
            'cols' => array('numberOfAlertsOnSnmpAgentMib' => 'Total Number of Alerts present in the SNMP Agent MIB')
                                           ),
        'numberOfSnmpGetOnAlarmTables'  => array(
            'title' => 'Number of synchronization requests (GET) per min on the Alarm table',
            'type' => 'sb',
            'cols' => array('numberOfSnmpGetOnAlarmTables' => 'Total Number of synchronization requests (GET) per min on the Alarm table')
                                           )
        ),
        array('numberOfSnmpGetOnAlertTables' => array(
            'title' => 'Number of synchronization requests (GET) per min on the Alert table',
            'type' => 'sb',
            'cols' => array('numberOfSnmpGetOnAlertTables' => 'Total Number of synchronization requests (GET) per min on the Alert table')
                                           ),
        'numberOfSnmpGetOnScalars' => array(
            'title' => 'Number of GET requests per min on any of the scalar objects',
            'type' => 'sb',
            'cols' => array('numberOfSnmpGetOnScalars' => 'Total Number of GET requests per min on any of the scalar objects')
                                           )
        ),
        array('overallAverageLatency' => array(
            'title' => 'Overall Average Latency',
            'type' => 'sb',
            'cols' => array('overallAverageLatency' => 'Total Overall Average Latency')
                                           ),
                'nbSnmpNbiAverageLatency' => array(
            'title' => 'FM SNMP NBI Average Latency (msec)',
            'type' => 'sb',
            'cols' => array('nbSnmpNbiAverageLatency' => 'Total FM SNMP NBI Average Latency (msec)')
                                           )
        )
    );
}

function plotInstrGraphs($instrParams) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                'title' => $instrGraphParamName['title'],
                'ylabel' => 'Count',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => $instrGraphParamName['type'],
                'sb.barwidth' => 60,
                'querylist' => array(
                    array (
                        'timecol' => 'time',
                        'whatcol' => $instrGraphParamName['cols'],
                        'tables' => "enm_fmsnmpnbi_instr, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_fmsnmpnbi_instr.siteid = sites.id AND sites.name = '%s'  AND enm_fmsnmpnbi_instr.serverid = servers.id",
                        'qargs' => array( 'site' )
                    )
                )
            );
           $id = $sqlParamWriter->saveParams($sqlParam);
           $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        }
    $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site;

    /* Daily Totals table */
    $FmSnmpNbiDailyTotals = "DDP_Bubble_421_ENM_FMSNMPNBI_Instr_DailyTotals_Help";
    drawHeaderWithHelp("Daily Totals", 1, "FmSnmpNbiDailyTotals", $FmSnmpNbiDailyTotals);

    $dailyTotalsTable = new ProcessingDailyTotalsTable();
    echo $dailyTotalsTable->getClientSortableTableStr()."<br/>";

    echo "<ul>\n";
    echo " <li>Generic JMX\n";
    echo "  <ul>\n";
    echo "   <li><a href=\"" . makeGenJmxLink("nbfmsnmp") . "\">NBFMSNMP</a></li>\n";
    echo "  </ul>\n";
    echo " </li>\n";
    echo "</ul>\n";

    $fmSnmpNbiInstrumentationHelp = "DDP_Bubble_422_ENM_FMSNMPNBI_Instr_Graphs_Help";
    drawHeaderWithHelp("FM SNMP NBI Instrumentation", 1, "fmSnmpNbiInstrumentationHelp", $fmSnmpNbiInstrumentationHelp);
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($instrGraphParams);
}

mainFlow();
include PHP_ROOT . "/common/finalise.php";

?>


