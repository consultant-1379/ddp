<?php
$pageTitle = "CM Activation and History";
include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

const JOB_ID = 'jobid';
const RESULT = 'result';
const PROCESSED_CHANGES_PER_SEC = 'Processed Changes Per Second';

$jobid=requestValue(JOB_ID);

class CMActivation extends DDPObject {
    var $title = "Cmserv Activation History";

    var $cols = array(
        JOB_ID                   => "Activation Job Id ",
        'start'                   => "Activation Start Time",
        'end'                     => "Activation End Time",
        'duration'                => "Activation Duration",
        'successfulChanges'       => "Activation Successful Changes",
        'failedChanges'           => "Activation Failed Changes",
        RESULT                  => "Activation Result",
        'processedChangessPerSec' => "Activation Processed Changes Per Sec",
        'configName'              => "Activation Config Name",
        'statusDetail'            => "Status Detail",
        'start_time'              => "Historical Start Time",
        'end_time'                => "Historical End Time",
        'cm_history_duration'     => "Historical Duration",
        'history_percentage'      => "Historical Percentage",
        'totalMoToWrite'          => "Historical Total Mo To Write",
        'mib_root_created'        => "Historical mib_root_created",
        'mo_created'              => "Historical mo_created",
        'attribute_modification'  => "Historical attribute_modification",
        'mo_deleted'              => "Historical mo_deleted",
        'action_performed'        => "Historical action_performed",
        'size'                    => "Historical size" ,
        'total_written'           => "Historical Total Written",
        'average'                 => "Historical Average size",
        'DB_writes'               => "DB Writes",
        'Max_MO_per_write'        => "Max MO Per Write",
        'Min_MO_per_write'        => 'Min MO Per Write',
        'Avg_MO_per_write'        => 'Avg MO Per Write'
    );


    function __construct() {
        parent::__construct("cmservActivation");
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        global $php_webroot;

        $sql="
SELECT
    IFNULL(enm_cm_activation.jobid,'') AS jobid,
    enm_cm_activation.start AS start,
    enm_cm_activation.end AS end,
    TIMEDIFF(enm_cm_activation.end,enm_cm_activation.start) AS duration,
    enm_cm_activation.successfulChanges AS successfulChanges,
    enm_cm_activation.failedChanges AS failedChanges,
    enm_cm_activation.result AS result,
    enm_cm_activation.processedChangessPerSec AS processedChangessPerSec,
    enm_cm_activation.configName AS configName,
    enm_cm_activation.statusDetail AS statusDetail,
    IFNULL(MIN(enm_cm_history.start), 0) AS start_time,
    IFNULL(MAX(enm_cm_history.end), 0) AS end_time,
    IFNULL(SUM(TIMEDIFF(enm_cm_history.end, enm_cm_history.start)),0) AS cm_history_duration,
    IFNULL(ROUND(((SUM(TIMEDIFF(enm_cm_history.end, enm_cm_history.start)) /
          TIMEDIFF(enm_cm_activation.end,enm_cm_activation.start)) * 100),0), 0)
        AS history_percentage,
    IFNULL(SUM(enm_cm_history.totalMoToWrite), 0) AS totalMoToWrite,
    IFNULL(SUM(enm_cm_history.mib_root_created), 0) AS mib_root_created,
    IFNULL(SUM(enm_cm_history.mo_created), 0) AS mo_created,
    IFNULL(SUM(enm_cm_history.attribute_modification), 0) AS attribute_modification,
    IFNULL(SUM(enm_cm_history.mo_deleted), 0) AS mo_deleted,
    IFNULL(SUM(enm_cm_history.action_performed), 0) AS action_performed,
    IFNULL(SUM(enm_cm_history.size), 0) AS size,
    IFNULL(SUM(mib_root_created) + SUM(mo_created) + SUM(attribute_modification) +
          SUM(mo_deleted) + SUM(action_performed),0) AS total_written,
        CASE
        WHEN (SUM(mib_root_created) + SUM(mo_created) + SUM(attribute_modification) +
             SUM(mo_deleted) + SUM(action_performed)) IS NULL THEN ''
    ELSE IFNULL(ROUND(SUM(size) / (SUM(mib_root_created) + SUM(mo_created) +
               SUM(attribute_modification) + SUM(mo_deleted) + SUM(action_performed)),2),0)
    END AS average,
    COUNT(enm_cm_history.jobid) as DB_writes,
    IFNULL(MAX(mib_root_created + mo_created + attribute_modification +
          mo_deleted + action_performed),0) as Max_MO_per_write,
    IFNULL(MIN(mib_root_created + mo_created + attribute_modification +
          mo_deleted + action_performed),0) as Min_MO_per_write,
    IFNULL(ROUND(AVG(mib_root_created + mo_created + attribute_modification +
          mo_deleted + action_performed)),0) as Avg_MO_per_write
FROM
    sites,
    enm_cm_activation
        LEFT JOIN
    enm_cm_history ON (enm_cm_activation.jobid = enm_cm_history.jobid
        AND enm_cm_activation.siteid = enm_cm_history.siteid
        AND SUBSTRING_INDEX(enm_cm_history.start,' ',1) =  SUBSTRING_INDEX(enm_cm_activation.start,' ',1)
        )

WHERE
    enm_cm_activation.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        AND enm_cm_activation.end BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        AND sites.name = '$site'
        AND enm_cm_activation.siteid = sites.id
        GROUP BY enm_cm_activation.jobid
ORDER BY jobid ASC";

    $this->populateData($sql);
    foreach ($this->data as &$row) {
        $row[JOB_ID] = makeLink(
            '/TOR/cm/cm_activation.php',
            $row[JOB_ID],
            array('jobid' => $row[JOB_ID], 'historyDetails' => '1')
        );
    }
    return $this->data;
    }
}

class CMActivationDetails extends DDPObject {
    var $title = "CmservActivationDetails";
    var $cols = array(
        JOB_ID                   => "Activation Job Id ",
        'start'                   => "Activation Start Time",
        'end'                     => "Activation End Time",
        'duration'                => "Activation Duration",
        'successfulChanges'       => "Activation Successful Changes",
        'failedChanges'           => "Activation Failed Changes",
        RESULT                  => "Activation Result",
        'processedChangessPerSec' => "Activation Processed Changess Per Sec",
        'configName'              => "Activation Config Name",
        'statusDetail'            => "Status Detail",
        'start_time'              => "Historical Start Time",
        'end_time'                => "Historical End Time",
        'cm_history_duration'     => "Historical Duration",
        'totalMoToWrite'          => "Historical Total Mo To Write",
        'mib_root_created'        => "Historical mib_root_created",
        'mo_created'              => "Historical mo_created",
        'attribute_modification'  => "Historical attribute_modification",
        'mo_deleted'              => "Historical mo_deleted",
        'action_performed'        => "Historical action_performed",
        'size'                    => "Historical size" ,
        'total_written'           => "Historical Total Written",
        'average'                 => "Historical Average size"
    );
    function __construct() {
        parent::__construct("cmservActivationDetails");
    }
    function getData() {
        global $date;
        global $site;
        global $jobid;
        $sql="
SELECT
    enm_cm_activation.jobid AS jobid,
    SUBSTRING(enm_cm_activation.start, 12) AS start,
    SUBSTRING(enm_cm_activation.end, 12) AS end,
    TIMEDIFF(enm_cm_activation.end,
            enm_cm_activation.start) AS duration,
    enm_cm_activation.successfulChanges AS successfulChanges,
    enm_cm_activation.failedChanges AS failedChanges,
    enm_cm_activation.result AS result,
    enm_cm_activation.processedChangessPerSec AS processedChangessPerSec,
    enm_cm_activation.configName AS configName,
    enm_cm_activation.statusDetail AS statusDetail,
    IFNULL(SUBSTRING(enm_cm_history.start, 12), 0) AS start_time,
    IFNULL(SUBSTRING(enm_cm_history.end, 12), 0) AS end_time,
    IFNULL(TIMEDIFF(enm_cm_history.end, enm_cm_history.start),
            0) AS cm_history_duration,
    IFNULL(enm_cm_history.totalMoToWrite, 0) AS totalMoToWrite,
    IFNULL(enm_cm_history.mib_root_created, 0) AS mib_root_created,
    IFNULL(enm_cm_history.mo_created, 0) AS mo_created,
    IFNULL(enm_cm_history.attribute_modification, 0) AS attribute_modification,
    IFNULL(enm_cm_history.mo_deleted, 0) AS mo_deleted,
    IFNULL(enm_cm_history.action_performed, 0) AS action_performed,
    IFNULL(enm_cm_history.size, 0) AS size,
    IFNULL(mib_root_created + mo_created + attribute_modification + mo_deleted + action_performed,
            0) AS total_written,
    CASE
        WHEN (mib_root_created + mo_created + attribute_modification + mo_deleted + action_performed) IS NULL THEN ''
        ELSE ROUND(size / (mib_root_created + mo_created + attribute_modification + mo_deleted + action_performed),
                2)
    END AS average
FROM
    sites,
    enm_cm_activation
        LEFT JOIN
    enm_cm_history ON (enm_cm_activation.jobid = enm_cm_history.jobid
        AND enm_cm_activation.siteid = enm_cm_history.siteid)
        AND SUBSTRING_INDEX(enm_cm_history.start, ' ', 1) = SUBSTRING_INDEX(enm_cm_activation.start, ' ', 1)
WHERE
    enm_cm_activation.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        AND enm_cm_activation.end BETWEEN '$date 00:00:00' AND '$date  23:59:59'
        AND sites.name = '$site'
        AND enm_cm_activation.siteid = sites.id
        AND enm_cm_activation.jobid = '$jobid'
ORDER BY jobid ASC";

    $this->populateData($sql);
    return  $this->data;
    }
}

function drawCMActivationDrillDownTable($status = '') {
    global $date, $site, $webargs;
    if ( $status != '' ) {
        echo "<p align='center'><a href='" . makeSelfLink() . "'>View All Statuses</a></p>";
    }
    $resultSelectWithURL = "CONCAT('<a href=\"" . makeSelfLink() .
                           "&status=', enm_cm_activation.result , '\">', enm_cm_activation.result, '</a>')";
    $where = "enm_cm_activation.siteid = sites.id AND sites.name = '$site'
              AND enm_cm_activation.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              GROUP BY enm_cm_activation.result";

$table = new SqlTable(
    "cm_activation_result_types",
    array(
         array( 'key' => RESULT, 'db' => $resultSelectWithURL, 'label' => 'Result' ),
         array( 'key' => 'count', 'db' => 'COUNT(enm_cm_activation.result)', 'label' => 'Count' )
    ),
    array( 'enm_cm_activation', 'sites' ),
    $where,
    true,
    array( 'order' => array( 'by' => RESULT, 'dir' => 'ASC') )
);
    echo $table->getTable();
}

function getCMActivationGraphParams($status = '') {
    $graphParams = array();

    $statuses=array('FAILURE', 'PARTIAL', 'SUCCESS');
    if ($status != '') {
        $statuses = array($status);
    }

    $baseParamSet = array(
        'timecol' => 'enm_cm_activation.start',
        'whatcol' => array( 'enm_cm_activation.processedChangessPerSec' => PROCESSED_CHANGES_PER_SEC),
        'tables' => "enm_cm_activation, sites",
        'where' => "enm_cm_activation.siteid = sites.id AND sites.name = '%s'",
        'qargs' => array( 'site')
    );

    foreach ($statuses as $status) {
        $statusParamSet = $baseParamSet;
        $statusParamSet['whatcol'] = array( 'enm_cm_activation.processedChangessPerSec' => $status);
        $statusParamSet['where'] .= " AND enm_cm_activation.result = '$status'";
        $graphParams[] = $statusParamSet;
    }

    return $graphParams;
}

function drawCMActivationGraph($status = '') {
    global $date;

    $graphParams = getCMActivationGraphParams($status);
    $sqlParam = array(
        'title' => PROCESSED_CHANGES_PER_SEC,
        'type' => 'xy',
        'ylabel' => PROCESSED_CHANGES_PER_SEC,
        'useragg' => 'true',
        'persistent' => 'true',
        'querylist' => $graphParams
   );
   $sqlParamWriter = new SqlPlotParam();
   $id = $sqlParamWriter->saveParams($sqlParam);
   echo "<p>" . $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400) . "</p>\n";
}

function getHistoryDetails() {

    if (issetURLParam(JOB_ID)) {
        $cmservActivationDetails = "DDP_Bubble_186_ENM_cmserv_Activation_Details";
        drawHeaderWithHelp("CM Activation And Historical Details", 2, "syncStatsHelp", $cmservActivationDetails);
        $cmservHistoryDetails =  new CMActivationDetails();
        echo $cmservHistoryDetails->getClientSortableTableStr(50, array(1000, 10000));
    }
}

function mainFlow() {
    $status = requestValue('status');

    drawHeaderWithHelp("CM Activation Processed Changes", 2, "CMActivationProcessedChangesHelp");
    echo '<table><tr><td valign="top">';
    echo '<div class="drill-down-table">';
    drawCMActivationDrillDownTable($status);
    echo '</div>';
    echo '</td><td valign="top">';
    drawCMActivationGraph($status);
    echo "</td></tr></table>";

    $cmActivationObj = new CMActivation();
    drawHeaderWithHelp(
        "CM Activation and Historical Summary",
        2,
        "activate",
        "DDP_Bubble_90_ENM_cmserv_Activation_History_Writer"
    );
    echo $cmActivationObj->getClientSortableTableStr(50, array(1000, 10000));
}

    $statsDB = new StatsDB();

    if (issetURLParam('historyDetails')) {
        getHistoryDetails($statsDB);
        }else {
        mainFlow($statsDB);
        }
include_once PHP_ROOT . "/common/finalise.php";
