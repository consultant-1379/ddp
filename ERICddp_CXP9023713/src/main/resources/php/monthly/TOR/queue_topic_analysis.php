<?php

const ACTION = 'action';
const ENM_ERROR_MESSAGES = 'enm_vm_critical_error_messages';
const TOTAL_COUNT = 'Total Count';

require_once "../../common/functions.php";
if ( issetURLParam(ACTION) ) {
    $UI = false;
}

$pageTitle = "VCS Events";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

$startDate = getArgs('start');
$endDate   = getArgs('end');
$getTopLimit = getArgs('getTopLimit');
$webargs = $webargs . "&start=$startDate&end=$endDate";

function getErrorTable($errorType) {
    global $webargs, $site, $startDate, $endDate;

echo <<<EOS
<script type="text/javascript">
function esFormatMessage(elCell, oRecord, oColumn, oData) {
  elCell.innerHTML = oData.match(/\s[A-Z].*jms\..*|jms\..*/g);
}
</script>
EOS;

    $subQuery =
        "(SELECT
            enm_vm_critical_error_messages.id AS queueid,
            SUM(enm_vm_critical_errors.errorCount) AS TotalCount,
            MAX(enm_vm_critical_errors.errorCount) AS MaxCount,
            MIN(enm_vm_critical_errors.errorCount) AS MinCount
          FROM
            enm_vm_critical_errors, enm_vm_critical_error_messages, sites
          WHERE
            enm_vm_critical_errors.siteid = sites.id
              AND enm_vm_critical_errors.errorid = enm_vm_critical_error_messages.id
              AND sites.name = '$site'
              AND date BETWEEN '$startDate' AND '$endDate'
              AND enm_vm_critical_error_messages.message REGEXP '^$errorType:*'
          GROUP BY queueid) AS a";
    $where = "enm_vm_critical_error_messages.id = a.queueid";

    $tableName = str_replace(' ', '_', $errorType) . "_table";
    return SqlTableBuilder::init()
        ->name($tableName)
        ->where($where)
        ->tables(array(ENM_ERROR_MESSAGES, $subQuery))
        ->addColumn('mes', 'message', 'Message', "esFormatMessage")
        ->addSimpleColumn('TotalCount', TOTAL_COUNT)
        ->addColumn('mc', 'MaxCount', 'Max Count')
        ->addSimpleColumn('MinCount', 'Min Count')
        ->sortBy('mc', DDPTable::SORT_DESC)
        ->ctxMenu(
            ACTION,
            true,
            array('plotnotifrec' => 'Plot for last month'),
            fromServer('PHP_SELF') . "?" . $webargs . "&errorType=$errorType",
            'mes'
        )
        ->paginate()
        ->build();
}

function plotTopErrors($statsDB, $errorType) {
    global $site, $startDate, $endDate, $getTopLimit;

    $where =
        "enm_vm_critical_errors.siteid = sites.id
           AND enm_vm_critical_errors.errorid = enm_vm_critical_error_messages.id
           AND sites.name = '$site'
           AND date BETWEEN '$startDate' AND '$endDate'
           AND enm_vm_critical_error_messages.message REGEXP '$errorType'
         GROUP BY enm_vm_critical_error_messages.message";

    $sql = "SELECT enm_vm_critical_error_messages.message
            FROM enm_vm_critical_errors, enm_vm_critical_error_messages, sites
            WHERE " . $where .
          " ORDER BY SUM(enm_vm_critical_errors.errorCount) DESC
            LIMIT $getTopLimit";

    $statsDB->query($sql);
    if ( $statsDB->getNumRows() == 0 ) {
        return null;
    }
    $errors = array();
    while ( $row = $statsDB->getNextRow() ) {
        $errors[] = $row;
    }

    $plotParam = SqlPlotParamBuilder::init()
        ->title("Top $getTopLimit $errorType")
        ->type(SqlPlotParam::XY)
        ->ylabel(TOTAL_COUNT);
    foreach ( $errors as $error ) {
        $error = $error[0];
        preg_match("/(?<=\:\s).*, jms\..*|jms\..*/", $error, $matches);
        $where =
            "enm_vm_critical_errors.siteid = sites.id
               AND enm_vm_critical_errors.errorid = enm_vm_critical_error_messages.id
               AND sites.name = '$site'
               AND date BETWEEN '$startDate' AND '$endDate'
               AND enm_vm_critical_error_messages.message = '$error'
             GROUP BY enm_vm_critical_error_messages.message, date";
        $plotParam = $plotParam->addQuery(
            "date",
            array ( "SUM(enm_vm_critical_errors.errorCount)" => $matches[0]),
            array ( "enm_vm_critical_errors", ENM_ERROR_MESSAGES, "sites" ),
            $where,
            array()
        );
    }
    $plotParam = $plotParam->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($plotParam);
    echo $sqlParamWriter->getImgURL($id, "$startDate 00:00:00", "$endDate 23:59:59", true, 800, 500);

}

function plotSelectedErrors($title) {
    global $startDate, $endDate, $site;

    $selected = getArgs('selected');
    $selectedArray = preg_split("~,(?=[^ ])~", $selected);

    $plotParam = SqlPlotParamBuilder::init()
        ->title($title)
        ->type(SqlPlotParam::XY)
        ->ylabel(TOTAL_COUNT);
    foreach ( $selectedArray as $selected ) {
        preg_match("/(?<=\:\s).*, jms\..*|jms\..*/", $selected, $matches);
        $where =
            "enm_vm_critical_errors.siteid = sites.id
               AND enm_vm_critical_errors.errorid = enm_vm_critical_error_messages.id
               AND sites.name='$site'
               AND enm_vm_critical_errors.date between '$startDate' AND '$endDate'
               AND enm_vm_critical_error_messages.message = '$selected'
             GROUP BY date, enm_vm_critical_errors.errorid";

        $plotParam = $plotParam->addQuery(
            "date",
            array ( "SUM(enm_vm_critical_errors.errorCount)" => $matches[0]),
            array ( "enm_vm_critical_errors", ENM_ERROR_MESSAGES, "sites" ),
            $where,
            array()
        );
    }
    $plotParam = $plotParam->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($plotParam);
    $url = $sqlParamWriter->getURL($id, "$startDate 00:00:00", "$endDate 23:59:59");
    header("Location:" . $url);
}

if ( issetURLParam(ACTION) ) {
    plotSelectedErrors(getArgs('errorType'));
    exit;
}
else {
    $statsDB = new StatsDB();
    $helpText = "This graph shows the top $getTopLimit Queues/Topics ordered by total error count <br/>
                 The number of Queues/Topics shown is based on the 'getTopLimit' attribute in the URL.";

    $maxDeliveryTable = getErrorTable("Maximum delivery attempts exceeded");
    echo $maxDeliveryTable->getTableWithHeader("Maximum Delivery Attempts Exceeded", 2, "", "");
    echo "<br/>";
    drawHeaderWithHelp("Maximum Delivery Attempts Exceeded", 2, "MaxDeliveryGraph", $helpText);
    plotTopErrors($statsDB, "Maximum delivery attempts exceeded");

    $queueFullTable = getErrorTable("Queue is full");
    echo $queueFullTable->getTableWithHeader("Queue is Full Errors", 2, "", "");
    echo "<br/>";
    drawHeaderWithHelp("Queue is Full Errors", 2, "QueueFullGraph", $helpText);
    plotTopErrors($statsDB, "Queue is full");

    include_once PHP_ROOT . "/common/finalise.php";
}

