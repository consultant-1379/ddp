<?php
$pageTitle = "Web Push";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . '/classes/ModelledTable.php';
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

class ProcessingDailyTotalsTable extends DDPObject {
  var $cols = array(
                array( 'key' => 'wpServInstance', 'label' => 'WPServ Instance'),
                array( 'key' => 'totalIncomingEvents', 'label' => 'Total Incoming Events', 'formatter' => 'ddpFormatNumber'),
                array( 'key' => 'totalOutgoingEvents', 'label' => 'Total Outgoing Events', 'formatter' => 'ddpFormatNumber'),
                array( 'key' => 'totalLoss', 'label' => 'Total Loss', 'formatter' => 'ddpFormatNumber')
      );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("WebPushDailyTotals");
    }

    function getData() {
        global $date, $site;
$sql = "
SELECT
 IFNULL(servers.hostname,'Totals') as wpServInstance,
 SUM(ewi.totalIncomingEvents) as totalIncomingEvents,
 SUM(ewi.totalPushedEvents) as totalOutgoingEvents,
 SUM(ewi.totalLoss) as totalLoss
FROM
 enm_wpserv_instr ewi, sites, servers
WHERE
 ewi.siteid = sites.id
 AND sites.name = '$site'
 AND ewi.serverid = servers.id
 AND ewi.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname WITH ROLLUP";
    $this->populateData($sql);
    return $this->data;
    }
}

function getInstrParams($graphTitle, $metricName) {
    return array(
        'title' => $graphTitle,
        'cols' => array (
            $metricName => $graphTitle
        )
    );
}

function wpservGraphs($instances, $instrParams) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    foreach ( $instances as $instance ) {
        $header[] = $instance;
    }
    $graphTable->addRow($header, null, 'th');

    $row = array();
    foreach ( $instances as $instance ) {
        $sqlParam = array(
            'title' => $instrParams['title'],
            'ylabel' => 'Count',
            'type' => 'sb',
            'useragg' => 'true',
            'sb.barwidth' => '100',
            'persistent' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => $instrParams['cols'],
                    'tables' => "enm_wpserv_instr, sites, servers",
                    'where' => "enm_wpserv_instr.siteid = sites.id AND  sites.name = '%s' AND enm_wpserv_instr.serverid = servers.id AND servers.hostname = '%s'",
                    'qargs' => array( 'site' , 'inst')
                )
            )
        );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);

    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
    }
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function plotActiveChannels($selected) {
    $host = array();
    $selectedValue = preg_split("/,/", $selected);
    foreach ($selectedValue as $params) {
       $param = preg_split("/@/", $params);
       $host[] = $param[0];
    }
    $hostid = array_unique($host);
    foreach ($hostid as $instance) {
        $inst = array();
        $channel = array();
        $graphs = array();
        $instances = array();
        foreach ($selectedValue as $paramserver) {
            if (strpos($paramserver, $instance."@") !== false) {
                $paramnst = preg_split("/@/", $paramserver);
                $inst[] = $paramnst[0];
                $channel[] = $paramnst[1];
            }
        }
        $serverId = implode(",", $inst);
        $channelId = implode(",", $channel);
        $instances = getInstances(
            'enm_webpush_active_channels',
            'time',
            "AND enm_webpush_active_channels.serverid = $instance"
        );
        drawHeader($instances[0], 2, 'active_channel_graphs');
        $graphParam = array( 'sids' =>  $serverId, 'channelid' => $channelId );
        getGraphsFromSet( 'all', $graphs, 'TOR/platform/active_channel_events', $graphParam);
        plotGraphs( $graphs );
    }
}

function mainFlow() {
    global $debug, $webargs, $php_webroot;

    /* Daily Totals table */
    $webPushDailyTotals = "DDP_Bubble_73_ENM_WpServ_Node_Totals";
    drawHeaderWithHelp("Daily Totals", 1, "WebPushDailyTotals", $webPushDailyTotals);

    $dailyTotalsTable = new ProcessingDailyTotalsTable();
    echo $dailyTotalsTable->getClientSortableTableStr();

    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $tbl = new ModelledTable( 'TOR/platform/enm_webpush_active_channels', 'Table', $selfLink );
    if ( $tbl->hasRows() ) {
        echo drawHeader('Active Channels', 1, 'active_channels_table');
        echo $tbl->getTable();
    }

    $wpInstrumentationHelp = "DDP_Bubble_74_ENM_WebPush_Stats";
    drawHeaderWithHelp("Web Push Instrumentation", 1, "wpInstrumentationHelp", $wpInstrumentationHelp);
    $instances = getInstances('enm_wpserv_instr');

    $instrGraphParams = getInstrParams('Total Incoming Events (per min)', 'totalIncomingEvents');
    wpservGraphs($instances, $instrGraphParams);

    $instrGraphParams = getInstrParams('Total Outgoing Events (per min)', 'totalPushedEvents');
    wpservGraphs($instances, $instrGraphParams);

    $instrGraphParams = getInstrParams('Total Subscriber (per min)', 'totalSubscriber');
    wpservGraphs($instances, $instrGraphParams);

    $instrGraphParams = getInstrParams('Total Loss (per min)', 'totalLoss');
    wpservGraphs($instances, $instrGraphParams);
}

$action = requestValue('action');
if ( $action ) {
    if ( $action === 'plotActiveChannels') {
        $selected = requestValue('selected');
        plotActiveChannels($selected);
    } else {
        echo "Error: Action $action is unknown";
    }
} else {
    mainFlow();
}
include PHP_ROOT . "/common/finalise.php";

?>
