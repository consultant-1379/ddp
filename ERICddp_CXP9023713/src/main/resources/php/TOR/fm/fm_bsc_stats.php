<?php
$pageTitle = "FM BSC";

$YUI_DATATABLE = true;
const SERVICE_GROUP = 'msapgfm';

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/GenericJMX.php";

class ProcessingDailyTotalsTable extends DDPObject {
  var $cols = array(
                  array('key' => 'inst', 'label' => 'Instance'),
                  array('key' => 'axeAlarmsReceived', 'label' => 'Number of alarms received in MDB', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'axeAlarmsDiscarded', 'label' => 'Number of alarms discarded in MDB', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'spontAlarmsReceived', 'label' => 'Number of Spontaneous alarms received', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'heartBeatPingsReceived', 'label' => 'Number of HB pings received', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'alarmsProcessingSuccess', 'label' => 'Number of processing success alarms', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'alarmsProcessingFailure', 'label' => 'Number of processing failure alarms', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'processedAlarmsForwarded', 'label' => 'Number of alarms forwarded', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'processedAlarmsForwardedFailure', 'label' => 'Number of alarms failed to forward', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'syncAlarmsReceived', 'label' => 'Number of sync alarms received', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'processedSyncAlarmsForwarded', 'label' => 'Number of sync alarms forwarded', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'processedSyncAlarmsForwardedFailures', 'label' => 'Number of sync alarms failed to forward', 'formatter' => 'ddpFormatNumber')
      );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("FmBscDailyTotals");
    }

    function getData() {
        global $date, $site;
$sql = "
SELECT
 IFNULL(servers.hostname,'All Instances') as inst,
 SUM(efbi.axeAlarmsReceived) as axeAlarmsReceived,
 SUM(efbi.axeAlarmsDiscarded) as axeAlarmsDiscarded,
 SUM(efbi.spontAlarmsReceived) as spontAlarmsReceived,
 SUM(efbi.heartBeatPingsReceived) as heartBeatPingsReceived,
 SUM(efbi.alarmsProcessingSuccess) as alarmsProcessingSuccess,
 SUM(efbi.alarmsProcessingFailure) as alarmsProcessingFailure,
 SUM(efbi.processedAlarmsForwarded) as processedAlarmsForwarded,
 SUM(efbi.processedAlarmsForwardedFailure) as processedAlarmsForwardedFailure,
 SUM(efbi.syncAlarmsReceived) as syncAlarmsReceived,
 SUM(efbi.processedSyncAlarmsForwarded) AS processedSyncAlarmsForwarded,
 SUM(efbi.processedSyncAlarmsForwardedFailures) AS processedSyncAlarmsForwardedFailures
FROM
 enm_fm_bsc_instr efbi, sites, servers
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
    return  array(
        array('numOfSupervisedNodes' => array(
            'title' => 'Number of supervised nodes',
            'type' => 'sb',
            'cols' => array('numOfSupervisedNodes' => 'Total Number of supervised nodes')
                                 ),
            'numOfHBFailureNodes' => array(
                'title' => 'Number of HeartBeat Failure nodes',
                'type' => 'sb',
                'cols' => array('numOfHBFailureNodes' => 'Total Number of HeartBeat Failure nodes')
                                 )
        ),
        array('axeAlarmsReceived' => array(
            'title' => 'Number of alarms received in MDB',
            'type' => 'sb',
            'cols' => array('axeAlarmsReceived' => 'Total Number of alarms received in MDB')
                                   ),
            'axeAlarmsDiscarded' => array(
                'title' => 'Number of alarms discarded in MDB',
                'type' => 'sb',
                'cols' => array('axeAlarmsDiscarded' => 'Total Number of alarms discarded in MDB')
                                          )
        ),
        array('spontAlarmsReceived' => array(
            'title' => 'Number of Spontaneous alarms received',
            'type' => 'sb',
            'cols' => array('spontAlarmsReceived' => 'Total Number of Spontaneous alarms received')
                                   ),
            'heartBeatPingsReceived' => array(
                'title' => 'Number of HB pings received',
                'type' => 'sb',
                'cols' => array('heartBeatPingsReceived' => 'Total Number of HB pings received')
                                          )
        ),
        array('alarmsProcessingSuccess' => array(
                'title' => 'Number of processing success alarms',
                'type' => 'sb',
                'cols' => array('alarmsProcessingSuccess' => 'Total Number of processing success alarms')
                                           ),
            'alarmsProcessingFailure' => array(
                'title' => 'Number of processing failure alarms',
                'type' => 'sb',
                'cols' => array('alarmsProcessingFailure' => 'Total Number of processing failure alarms')
                                           )
        ),
        array('processedAlarmsForwarded' => array(
            'title' => 'Number of alarms forwarded',
            'type' => 'sb',
            'cols' => array('processedAlarmsForwarded' => 'Total Number of alarms forwarded')
                                           ),
        'processedAlarmsForwardedFailure'  => array(
            'title' => 'Number of alarms failed to forward',
            'type' => 'sb',
            'cols' => array('processedAlarmsForwardedFailure' => 'Number of alarms failed to forward')
                                           )
        ),
        array('syncAlarmsReceived' => array(
            'title' => 'Number of sync alarms received',
            'type' => 'sb',
            'cols' => array('syncAlarmsReceived' => 'Total Number of sync alarms received')
                                           ),
        'processedSyncAlarmsForwarded' => array(
            'title' => 'Number of sync alarms forwarded',
            'type' => 'sb',
            'cols' => array('processedSyncAlarmsForwarded' => 'Number of sync alarms forwarded')
                                           )
        ),
        array('processedSyncAlarmsForwardedFailures' => array(
            'title' => 'Number of sync alarms failed to forward',
            'type' => 'sb',
            'cols' => array('processedSyncAlarmsForwardedFailures' => 'Total Number of sync alarms failed to forward')
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
                        'tables' => "enm_fm_bsc_instr, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_fm_bsc_instr.siteid = sites.id AND sites.name = '%s'  AND enm_fm_bsc_instr.serverid = servers.id",
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

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;
    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, SERVICE_GROUP);
    $serverIdStr =implode(",", array_values($processingSrv));
    if ($serverIdStr != '') {
    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_mssnmpfm_instr, sites
WHERE
 enm_mssnmpfm_instr.siteid = sites.id AND sites.name = '$site' AND enm_mssnmpfm_instr.serverid IN($serverIdStr) AND
 enm_mssnmpfm_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
 if ($row[0] > 0) {
   echo makeLink('/TOR/fm/fm_snmp_axe.php', 'AXE FM SNMP', array('servicegroup' => SERVICE_GROUP ));
 }
}

    /* Daily Totals table */
    $FmBscDailyTotals = "DDP_Bubble_407_ENM_FMBSC_Instr_DailyTotals_Help";
    drawHeaderWithHelp("Daily Totals", 1, "FmBscDailyTotals", $FmBscDailyTotals);

    $dailyTotalsTable = new ProcessingDailyTotalsTable();
    echo $dailyTotalsTable->getClientSortableTableStr()."<br/>";

    echo "<ul>\n";
    echo " <li>Generic JMX\n";
    echo "  <ul>\n";
    echo "   <li><a href=\"" . makeGenJmxLink(SERVICE_GROUP) . "\">MSAPGFM</a></li>\n";
    echo "  </ul>\n";
    echo " </li>\n";
    echo "</ul>\n";

    $fmbscInstrumentationHelp = "DDP_Bubble_408_ENM_FMBSC_Instr_Graphs_Help";
    drawHeaderWithHelp("FM BSC Instrumentation", 1, "fmbscInstrumentationHelp", $fmbscInstrumentationHelp);
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($instrGraphParams);
}

$statsDB = new StatsDB();
mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>

