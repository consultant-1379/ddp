<?php
$pageTitle = "Notification Analysis";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

class ProcessingDailyTotalsTable extends DDPObject {
  var $cols = array(
                    array('key' => 'inst', 'label' => 'Instance'),
                    array('key' => 'totalnotificationsreceived', 'label' => 'Received', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'totalnotificationsprocessed', 'label' => 'Processed', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'totalnotificationsdiscarded', 'label' => 'Discarded', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'leadtimemax', 'label' => 'Lead Time Max', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'validationhandlertimemax', 'label' => 'Validation Handler Max', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'writehandlertimemax', 'label' => 'Write Handler Max', 'formatter' => 'ddpFormatNumber')
                    );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("NotifDailyTotals");
    }

    function getData() {
        global $date, $site;

        $sql = "
SELECT
    IFNULL(servers.hostname, 'All Instances') as inst,
    IFNULL( SUM(esi.totalnotificationsreceived), 0 ) as totalnotificationsreceived,
    IFNULL( SUM(esi.totalnotificationsprocessed), 0 ) as totalnotificationsprocessed,
    IFNULL( SUM(esi.totalnotificationsdiscarded), 0 ) as totalnotificationsdiscarded,
    IFNULL( MAX(esi.leadtimemax), 'NA' ) as leadtimemax,
    IFNULL( MAX(esi.validationhandlertimemax), 'NA' ) as validationhandlertimemax,
    IFNULL( MAX(esi.writehandlertimemax), 'NA' ) as writehandlertimemax
FROM
    enm_mscmipnotification_logs esi, sites, servers
WHERE
    esi.siteid = sites.id
    AND sites.name = '$site'
    AND esi.serverid = servers.id
    AND esi.endtime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname WITH ROLLUP";

        $this->populateData($sql);
        return $this->data;
    }
}

function getInstrParams() {
    $instrGraphParams = array(
        array(
            'totalnotificationsreceived' => array(
                'title' => 'Received',
                'type' => 'sb',
                'cols' => array('totalnotificationsreceived' => 'Received')
            ),
            'totalnotificationsprocessed' => array(
                'title' => 'Processed',
                'type' => 'sb',
                'cols' => array('totalnotificationsprocessed' => 'Processed')
            )
        ),
        array(
            'totalnotificationsdiscarded' => array(
                'title' => 'Discarded',
                'type' => 'sb',
                'cols' => array('totalnotificationsdiscarded' => 'Discarded')
            ),
                'leadtimemax' => array(
                'title' => 'Lead Time Max',
                'type' => 'sb',
                'cols' => array('leadtimemax' => 'Lead Time Max')
            )
        ),
        array(
            'validationhandlertimemax' => array(
                'title' => 'Validation Handler Max',
                'type' => 'sb',
                'cols' => array('validationhandlertimemax' => 'Validation Handler Max')
            ),
            'writehandlertimemax' => array(
                'title' => 'Write Handler Max',
                'type' => 'sb',
                'cols' => array('writehandlertimemax' => 'Write Handler Max')
            )
        )
    );

    return $instrGraphParams;
}

function plotInstrGraphs($statsDB, $instrParams) {
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
                        'timecol' => 'endtime',
                        'whatcol' => $instrGraphParamName['cols'],
                        'tables' => "enm_mscmipnotification_logs, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_mscmipnotification_logs.siteid = sites.id AND sites.name = '%s'  AND enm_mscmipnotification_logs.serverid = servers.id",
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

if (isset($_GET['format']) && $_GET['format'] == "xls" && isset($_GET['table'])) {
    $table;
    if ( $_GET['table'] == "iptransport_notif_analysis_daily_totals" ) {
        $table = new ProcessingDailyTotalsTable();
        $table->title = "Daily Totals";
    } else {
        echo "Invalid table name: " . $_GET['table'];
        exit;
    }

    ob_clean();
    $excel = new ExcelWorkbook();
    $excel->addObject($table);
    $excel->write();
    exit;
}

function mainFlow($statsDB) {
    global $date, $site;

    /* Daily Totals table */
    $NotifDailyTotals = "DDP_Bubble_354_ENM_MSCMIP_Notifications_DailyTotals_Help";
    $dailyTotalsExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=iptransport_notif_analysis_daily_totals">[Download Excel]</a>';
    drawHeaderWithHelp("Daily Totals", 1, "NotifDailyTotals", $NotifDailyTotals, "", $dailyTotalsExcelLink);
    $dailyTotalsTable = new ProcessingDailyTotalsTable();
    echo $dailyTotalsTable->getClientSortableTableStr();
    echo "<br/>\n";

    $notificationInstrumentationHelp = "DDP_Bubble_355_ENM_MSCMIP_Notification_Instrumentation_Help";
    drawHeaderWithHelp("IP Transport Notification Instrumentation", 1, "notificationInstrumentationHelp", $notificationInstrumentationHelp);
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($statsDB, $instrGraphParams);
}

$statsDB = new StatsDB();
mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>

