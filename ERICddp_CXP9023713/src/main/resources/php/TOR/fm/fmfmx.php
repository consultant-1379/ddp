<?php
$pageTitle = "FM FMX ADAPTOR";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

class ProcessingDailyTotalsTable extends DDPObject {
  var $cols = array(
                  'fmServInstance' => 'Instance',
                  'acksSentToFMX' => 'Total Acknowledgements Sent to FMX',
                  'unAcksSentToFMX' => 'Total UnAcknowledgements Sent to FMX',
                  'clearsSentToFMX' => 'Total Clears Sent to FMX',
                  'activeSubscriptionsCount' => 'Total Active FMX Subscriptions',
                  'numberOfShowAlarmRequests' => 'Total Show Alarm Requests Received',
                  'numberOfHideAlarmRequests' => 'Total Hide Alarm Requests Received',
                  'numberOfAlarmsSyncRequests' => 'Total Sync Requests Received From FMX',
                  'numberOfUpdateAlarmRequests' => 'Total Update Alarm Requests Received',
                  'newAlarmsFromFMX' => 'Total New Alarms Received From FMX',
                  'totalNumberOfAlarmsSentToFMX' => 'Total Number of Alarms Sent to FMX'
      );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("FmFmxDailyTotals");
    }

    function getData() {
        global $date, $site;
$sql = "
SELECT
 IFNULL(servers.hostname,'Totals') as fmServInstance,
 SUM(efi.acksSentToFMX) as acksSentToFMX,
 SUM(efi.unAcksSentToFMX) as unAcksSentToFMX,
 SUM(efi.clearsSentToFMX) as clearsSentToFMX,
 SUM(efi.activeSubscriptionsCount) as activeSubscriptionsCount,
 SUM(efi.numberOfShowAlarmRequests) as numberOfShowAlarmRequests,
 SUM(efi.numberOfHideAlarmRequests) as numberOfHideAlarmRequests,
 SUM(efi.numberOfAlarmsSyncRequests) as numberOfAlarmsSyncRequests,
 SUM(efi.numberOfUpdateAlarmRequests) as numberOfUpdateAlarmRequests,
 SUM(efi.newAlarmsFromFMX) as newAlarmsFromFMX,
 SUM(efi.totalNumberOfAlarmsSentToFMX) as totalNumberOfAlarmsSentToFMX
FROM
 enm_fmfmx_instr efi, sites, servers
WHERE
 efi.siteid = sites.id
 AND sites.name = '$site'
 AND efi.serverid = servers.id
 AND efi.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname WITH ROLLUP";
    $this->populateData($sql);
    return $this->data;
    }
}

function getInstrParams() {
    return array(
        array('acksSentToFMX' => array(
            'title' => 'Acknowledgements Sent to FMX',
            'type' => 'sb',
            'cols' => array('acksSentToFMX' => 'Total Acknowledgements Sent to FMX')
                                 ),
            'unAcksSentToFMX' => array(
                'title' => 'UnAcknowledgements Sent to FMX',
                'type' => 'sb',
                'cols' => array('unAcksSentToFMX' => 'Total UnAcknowledgements Sent to FMX')
                                 )
        ),
        array('clearsSentToFMX' => array(
            'title' => 'Clears Sent to FMX',
            'type' => 'sb',
            'cols' => array('clearsSentToFMX' => 'Total Clears Sent to FMX')
                                   ),
            'activeSubscriptionsCount' => array(
                'title' => 'Active FMX Subscriptions',
                'type' => 'tsc',
                'cols' => array('activeSubscriptionsCount' => 'Total Active FMX Subscriptions')
                                          )
        ),
        array('numberOfShowAlarmRequests' => array(
            'title' => 'Show Alarm Requests Received',
            'type' => 'sb',
            'cols' => array('numberOfShowAlarmRequests' => 'Total Show Alarm Requests Received')
                                             ),
            'numberOfHideAlarmRequests' => array(
                'title' => 'Hide Alarm Requests Received',
                'type' => 'sb',
                'cols' => array('numberOfHideAlarmRequests' => 'Total Hide Alarm Requests Received')
                                           )
        ),
        array('numberOfAlarmsSyncRequests' => array(
            'title' => 'Sync Requests Received From FMX',
            'type' => 'sb',
            'cols' => array('numberOfAlarmsSyncRequests' => 'Total Sync Requests Received From FMX')
                                              ),
        'numberOfUpdateAlarmRequests' => array(
                'title' => 'Update Alarm Requests Received',
                'type' => 'sb',
                'cols' => array('numberOfUpdateAlarmRequests' => 'Total Update Alarm Requests Received')
                                             )
        ),
        array('newAlarmsFromFMX' => array(
            'title' => 'New Alarms Received From FMX',
            'type' => 'sb',
            'cols' => array('newAlarmsFromFMX' => 'Total New Alarms Received From FMX')
                                            ),
        'totalNumberOfAlarmsSentToFMX' => array(
                'title' => 'Number of Alarms Sent to FMX',
                'type' => 'sb',
                'cols' => array('totalNumberOfAlarmsSentToFMX' => 'Total Number of Alarms Sent to FMX')
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
                        'tables' => "enm_fmfmx_instr, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_fmfmx_instr.siteid = sites.id AND sites.name = '%s'  AND enm_fmfmx_instr.serverid = servers.id",
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
    global $debug, $webargs, $php_webroot;

    /* Daily Totals table */
    $fmFmxDailyTotals = "DDP_Bubble_81_ENM_FmFmxServ_Node_Totals";
    drawHeaderWithHelp("Daily Totals", 1, "FmFmxDailyTotals", $fmFmxDailyTotals);

    $dailyTotalsTable = new ProcessingDailyTotalsTable();
    echo $dailyTotalsTable->getClientSortableTableStr();

    $fmFmxAdaptorInstrumentationHelp = "DDP_Bubble_82_ENM_FmFmx_Stats";
    drawHeaderWithHelp("Fm Fmx Adaptor Instrumentation", 1, "fmFmxAdaptorInstrumentationHelp", $fmFmxAdaptorInstrumentationHelp);
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($instrGraphParams);
}

mainFlow();
include PHP_ROOT . "/common/finalise.php";

?>
