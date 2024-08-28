<?php
$pageTitle = "Analysis Statistics";
include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

const SRID = 'serviceId';
const INSTANCE = 'instance';
const NETANSERVER_AUDITLOG_DETAILS = 'netanserver_auditlog_details';
const NETANSERVER_OPENFILE_DETAILS = 'netanserver_open_file_statistics_details';

$additionalWhereClause = "";
$aggregatedView = "";
$site = requestValue('site');
$date = requestValue('date');

$serviceId = requestValue(INSTANCE);
$additionalWhereClause = "AND serviceId = '$serviceId'";
drawHeader("Web Player Instance ID - $serviceId", 1, ' ' );

function plotTableWithId() {
    global $serviceId;
    $table = new ModelledTable('ENIQ/initiateOpenAnalysisWithId', 'initiateActionHelp', array(SRID => $serviceId));
    echo $table->getTableWithHeader("Initiate Open Action Per Analysis");
    echo addLineBreak();
}
function plotTableforSuccessAndFailureWithId() {
    global $serviceId;
    $table = new ModelledTable('ENIQ/openAnalysisWithId', 'openActionHelp', array(SRID => $serviceId));
    echo $table->getTableWithHeader("Open Action Per Analysis");
    echo addLineBreak();
}
function dailyUsersParamsWithId() {
    return array(
            'dailyUsersPerAnalysisWithId'
    );
}
function openFileInstanceParamsWithId() {
    return array(
        'openFileInstancePerAnalysisWithId'
    );
}
function initiateOpenSuccessParamsWithId() {
    return array(
        'initiateOpenActionSuccessWithId'
    );
}
function initiateOpenFailureParamsWithId() {
    return array(
        'initiateOpenActionFailureWithId'
    );
}
function openActionSuccessParamWithId() {
    return array(
        'openActionSuccessWithId'
    );
}
function openActionFailureParamWithId() {
    return array(
        'openActionFailureWithId'
    );
}
function getAnalysisGraph($graphParams, $table, $operation, $status, $title, $id) {
    global $date, $site, $statsDB, $serviceId, $additionalWhereClause;

    if ($operation == "" && $status == "") {
        $mysql = <<<EOT
SELECT
 COUNT(*)
FROM $table, sites
WHERE
 $table.siteid = sites.id AND
 sites.name = '$site' AND $table.time between '$date 00:00:00' AND '$date 23:59:59' $additionalWhereClause
EOT;
        $row = $statsDB->queryRow($mysql);
        if ($row[0] > 0) {
            drawHeader($title, 2, $id);
            foreach ( $graphParams as $column ) {
                $modelledGraph = new ModelledGraph('ENIQ/analysisId_Stats_' . $column );
                 $params = array( SRID => $serviceId );
                $graphs[] = $modelledGraph->getImage($params);
            }
            plotgraphs( $graphs );
        }
    } else {
            $mysql = <<<EOT
SELECT
 COUNT(*)
FROM $table, sites
WHERE
 $table.siteid = sites.id AND sites.name = '$site' AND $table.time between '$date 00:00:00' AND
 '$date 23:59:59' AND operation_name = '$operation' AND status = '$status' $additionalWhereClause
EOT;
            $row = $statsDB->queryRow($mysql);
            if ($row[0] > 0) {
                drawHeader($title, 2, $id);
                foreach ( $graphParams as $column ) {
                    $modelledGraph = new ModelledGraph('ENIQ/analysisId_' . $column);
                    $params = array( SRID => $serviceId );
                    $graphs[] = $modelledGraph->getImage($params);
                }
                plotgraphs( $graphs );
            }
        }
}

function showLinks() {

    $links = array();
    $links[] = makeAnchorLink('dailyUserHelp', 'Daily Users per Analysis');
    $links[] = makeAnchorLink('openFileInstanceHelp', 'Open File Instance Per Analysis');
    $links[] = makeAnchorLink('initiateActionHelp', 'Initiate Open Action per Analysis');
    $links[] = makeAnchorLink('openActionHelp', 'Open Action per Analysis');

    echo makeHTMLList($links);
}

function mainFlow() {

    drawHeader("Analysis Statistics", 1, "analysisStatisticsHelp");
    showLinks();
    getAnalysisGraph(
        dailyUsersParamsWithId(),
        NETANSERVER_AUDITLOG_DETAILS,
        "",
        "",
        'Daily Users Per Analysis',
        'dailyUserHelp'
    );

    getAnalysisGraph(
        openFileInstanceParamsWithId(),
        NETANSERVER_OPENFILE_DETAILS,
        "",
        "",
        'Open File Instance Per Analysis',
        'openFileInstanceHelp'
    );

    plotTableWithId();
    getAnalysisGraph(
        initiateOpenSuccessParamsWithId(),
        NETANSERVER_AUDITLOG_DETAILS,
        "Initiate Open Analysis",
        "Success",
        'Initiate Open Action-Success',
        'initiateSuccessHelp'
    );

    getAnalysisGraph(
        initiateOpenFailureParamsWithId(),
        NETANSERVER_AUDITLOG_DETAILS,
        "Initiate Open Analysis",
        "Failure",
        'Initiate Open Action-Failure',
        'initiateFailHelp'
    );

    plotTableforSuccessAndFailureWithId();
    getAnalysisGraph(
        openActionSuccessParamWithId(),
        NETANSERVER_AUDITLOG_DETAILS,
        'Open Analysis',
        'Success',
        'Open Action-Success',
        'openSuccessHelp'
    );

    getAnalysisGraph(
        openActionFailureParamWithId(),
        NETANSERVER_AUDITLOG_DETAILS,
        'Open Analysis',
        'Failure',
        'Open Action-Failure',
        'openFailHelp'
    );
}

mainFlow();
include_once "../common/finalise.php";
