<?php
$pageTitle = "Helm Updates";

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/K8SEvents.php";

const SELECTED = 'selected';

function showUpdate($id) {
    global $rootdir;

    $dataFile = $rootdir . "/k8s/helm-$id.json";
    debugMsg("showUpdate: dataFile $dataFile");
    $data = json_decode(file_get_contents($dataFile), true);

    $hookTable = new DDPTable(
        "hooks",
        array(
            array(DDPTable::KEY => 'name', DDPTable::LABEL => 'Name'),
            array(
                DDPTable::KEY => 'start',
                DDPTable::LABEL => 'Start',
                DDPTable::FORMATTER => DDPTable::FORMAT_TIME
            ),
            array(
                DDPTable::KEY => 'end',
                DDPTable::LABEL => 'Completed',
                DDPTable::FORMATTER => DDPTable::FORMAT_TIME
            )
        ),
        array('data' => $data['hooks'])
    );
    echo $hookTable->getTableWithHeader("Hooks");

    echo addLineBreak();

    $podTable = new DDPTable(
        "pods",
        array(
            array(DDPTable::KEY => 'podname', DDPTable::LABEL => 'Pod'),
            array(
                DDPTable::KEY => 'PodScheduled',
                DDPTable::LABEL => 'Scheduled',
                DDPTable::FORMATTER => DDPTable::FORMAT_TIME
            ),
            array(
                DDPTable::KEY => 'Initialized',
                DDPTable::LABEL => 'Initialized',
                DDPTable::FORMATTER => DDPTable::FORMAT_TIME
            ),
            array(
                DDPTable::KEY => 'Ready',
                DDPTable::LABEL => 'Ready',
                DDPTable::FORMATTER => DDPTable::FORMAT_TIME
            )
        ),
        array('data' => $data['pods']),
        array(
            DDPTable::CTX_MENU => array(
                DDPTable::KEY => 'show',
                DDPTable::MULTI => false,
                DDPTable::MENU => array( "pod" => 'Show Events'),
                DDPTable::URL  => makeSelfLink() . "&id=" . $id,
                DDPTable::COL => 'podname'
            )
        )
    );
    echo $podTable->getTableWithHeader("Pods");
}

function getChartTimes($id) {
    global $statsDB, $site, $date;

    $matches = array();
    if (!preg_match("/^(\S+)-(\d+)$/", $id, $matches)) {
        echo "<p>ERROR: Invalid id arg, \"$id\"</p>";
        return null;
    }
    debugMsg("showWarings: matches", $matches);

    $chartName = $matches[1];
    $chartEndTime = strftime("%F %T", $matches[2]);
    debugMsg("showWarnings chartName=$chartName, chartEndTime=$chartEndTime");

    $dbRow = $statsDB->queryRow("
SELECT UNIX_TIMESTAMP(k8s_helm_update.start), UNIX_TIMESTAMP(k8s_helm_update.end)
FROM k8s_helm_update
JOIN sites ON k8s_helm_update.siteid = sites.id
WHERE
 k8s_helm_update.name = '$chartName' AND
 k8s_helm_update.end = '$chartEndTime' AND
 sites.name = '$site'");
    debugMsg("showWarnings: dbRow", $dbRow);

    return $dbRow;
}

function showWarnings($id) {
    global $statsDB, $site, $date;

    list($startTime, $endTime) = getChartTimes($id);
    $k8sEvents = new K8SEvents(true, true, $startTime, $endTime);
    $k8sEventsTable = $k8sEvents->getTable();
    if ( is_null($k8sEventsTable) ) {
        echo "<p>No event data available<p>";
    } else {
        echo $k8sEventsTable->getTableWithHeader("Warning Events");
    }
}

function showEventsForPod($id, $podName) {
    list($startTime, $endTime) = getChartTimes($id);
    $involvedObject = "Pod/" . $podName;
    $k8sEvents = new K8SEvents(true, false, $startTime, $endTime, $involvedObject);
    $k8sEventsTable = $k8sEvents->getTable();
    if ( is_null($k8sEventsTable) ) {
        echo "<p>No event data available<p>";
    } else {
        echo $k8sEventsTable->getTableWithHeader("Events for " . $involvedObject);
    }
}

function main() {
    global $statsDB;

    $show = requestValue('show');
    if ( is_null($show) ) {
        $helmUpdatesTable = new ModelledTable(
            'common/k8s_helm_update',
            'helm_updates',
            array(ModelledTable::URL => makeURL('/k8s/helm.php'))
        );
        echo $helmUpdatesTable->getTableWithHeader("Helm Chart Operations");
    } else {
        if ($show === 'details') {
            showUpdate(requestValue(SELECTED));
        } elseif ( $show === 'warnings') {
            showWarnings(requestValue(SELECTED));
        } elseif ( $show === 'pod') {
            showEventsForPod(requestValue('id'), requestValue(SELECTED));
        }
    }
}

main();

require_once PHP_ROOT . "/common/finalise.php";
