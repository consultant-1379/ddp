<?php
$pageTitle = "Analysis Statistics";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
const SRID = 'serviceId';
const NETANSERVER_AUDITLOG_DETAILS = 'netanserver_auditlog_details';

$additionalWhereClause = "";
$statsDB = new StatsDB();
$site = requestValue('site');
$date = requestValue('date');

if ( isset($_GET['instance']) && $_GET['instance'] != "" ) {
    $serviceId = $_GET['instance'];
    $additionalWhereClause = "AND serviceId = '$serviceId'";
    echo "<H1>Web Player Instance ID - $serviceId</H1>";
} else {
        $serviceIdResultSet = $statsDB->query("
        SELECT
         DISTINCT(serviceId)
        FROM netanserver_auditlog_details, sites
        WHERE
         netanserver_auditlog_details.siteid = sites.id AND
         sites.name = '$site' AND netanserver_auditlog_details.time between '$date 00:00:00' AND '$date 23:59:59'
         AND netanserver_auditlog_details.serviceId!=''
        UNION
        SELECT
         DISTINCT(serviceId)
        FROM netanserver_open_file_statistics_details, sites
        WHERE
         netanserver_open_file_statistics_details.siteid = sites.id AND
         sites.name = '$site' AND netanserver_open_file_statistics_details.time between '$date 00:00:00' AND '$date 23:59:59'
         AND netanserver_open_file_statistics_details.serviceId!=''
        ");

        if ($serviceIdResultSet = $statsDB->getNumRows() > 0) {
            drawHeader("Web Player Instance IDs", 1, "webplayerInstanceHelp");
            while ($serviceIdResultSet = $statsDB->getNextNamedRow()) {
                echo '<li><a href=analysisStatsId.php?' . $webargs ."&instance=" . $serviceIdResultSet[SRID] . ">" .
                $serviceIdResultSet[SRID] . "</a></li>\n";
            }
        }
    }

unset($_GET['instance']);
function plotTable() {
    $table = new ModelledTable('ENIQ/initiateopenAnalysis', 'initiateActionHelp');
    echo $table->getTableWithHeader("Initiate Open Action Per Analysis");
    echo addLineBreak();
}

function plotTableforSuccessAndFailure() {
    $table = new ModelledTable('ENIQ/openAnalysis', 'openActionHelp');
    echo $table->getTableWithHeader("Open Action Per Analysis");
    echo addLineBreak();
}

function dailyUsersParams() {
    return array(
        'dailyUsersPerAnalysis'
    );
}

function openFileInstanceParams() {
    return array(
        'openFileInstancePerAnalysis'
    );
}

function initiateOpenSuccessParams() {
    return array(
        'initiateOpenActionSuccess'
    );
}

function initiateOpenFailureParams() {
    return array(
        'initiateOpenActionFailure'
    );
}

function openActionSuccessParam() {
    return array(
        'openActionSuccess'
    );
}

function openActionFailureParam() {
    return array(
        'openActionFailure'
    );
}

function getAnalysisGraph($graphParams, $table, $operation, $status, $title, $id) {
    global $date, $site,$statsDB, $additionalWhereClause, $serviceId;
    if ($operation == "" && $status == "") {
        $mysql = "SELECT
                  COUNT(*)
                 FROM $table, sites
                 WHERE
                 $table.siteid = sites.id AND
                 sites.name = '$site' AND $table.time between '$date 00:00:00' AND '$date 23:59:59'";
        $row = $statsDB->queryRow($mysql);
        if ($row[0] > 0) {
            drawHeader($title, 2, $id);
            foreach ( $graphParams as $column ) {
                $modelledGraph = new ModelledGraph('ENIQ/analysis_Stats_' . $column);
                $graphs[] = $modelledGraph->getImage();
            }
            plotgraphs( $graphs );
        }
    }
    else {
         $mysql = "SELECT
                    COUNT(*)
                   FROM $table, sites
                   WHERE
                    $table.siteid = sites.id AND sites.name = '$site' AND $table.time between '$date 00:00:00' AND '$date 23:59:59' AND operation_name = '$operation'
                    AND status = '$status'";
        $row = $statsDB->queryRow($mysql);
        if ($row[0] > 0) {
            drawHeader($title, 2, $id);
            foreach ( $graphParams as $column ) {
                $modelledGraph = new ModelledGraph('ENIQ/analysis_' . $column);
                $graphs[] = $modelledGraph->getImage();
            }
            plotgraphs( $graphs );
        }
    }
}

function mainFlow() {

    drawHeader("Analysis Statistics", 1, "analysisStatisticsHelp");
    print <<<END
        <ul>
        <li><a href = "#dailyUsers">Daily Users per Analysis</a></li>
        <li><a href = "#openFileInstance">Open File Instance Per Analysis</a></li>
        <li><a href = "#initiateOpenAction">Initiate Open Action per Analysis</a></li>
        <li><a href = "#openAction">Open Action per Analysis</a></li>
        </ul>
END;

    echo '<a name = "dailyUsers"/>';
    getAnalysisGraph(
        dailyUsersParams(),
        NETANSERVER_AUDITLOG_DETAILS,
        "",
        "",
        'Daily Users Per Analysis',
        'dailyUserHelp'
    );

    echo '<a name = "openFileInstance"/>';
    getAnalysisGraph(
        openFileInstanceParams(),
        'netanserver_open_file_statistics_details',
        "",
        "",
        'Open File Instance Per Analysis',
        'openFileInstanceHelp'
    );

    echo '<a name = "initiateOpenAction"/>';
    plotTable();

    getAnalysisGraph(
        initiateOpenSuccessParams(),
        NETANSERVER_AUDITLOG_DETAILS,
        "Initiate Open Analysis",
        "Success",
        'Initiate Open Action-Success',
        'initiateSuccessHelp'
    );

    getAnalysisGraph(
        initiateOpenFailureParams(),
        NETANSERVER_AUDITLOG_DETAILS,
        "Initiate Open Analysis",
        "Failure",
        'Initiate Open Action-Failure',
        'initiateFailHelp'
    );

    echo '<a name = "openAction"/>';
    plotTableforSuccessAndFailure();

    getAnalysisGraph(
        openActionSuccessParam(),
        NETANSERVER_AUDITLOG_DETAILS,
        'Open Analysis',
        'Success',
        'Open Action-Success',
        'openSuccessHelp'
    );

    getAnalysisGraph(
        openActionFailureParam(),
        NETANSERVER_AUDITLOG_DETAILS,
        'Open Analysis',
        'Failure',
        'Open Action-Failure',
        'openFailHelp'
    );
}

mainFlow();
include "../common/finalise.php";
