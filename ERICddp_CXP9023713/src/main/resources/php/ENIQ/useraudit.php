<?php
$pageTitle = "User Statistics";
include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

const NETANSERVER_USERAUDITLOG_DETAILS = 'netanserver_userauditlog_details';
const NETANSERVER_USER_SESSION_STATISTICS_DETAILS = 'netanserver_user_session_statistics_details';
const ANALYST = 'Analyst';
const CONSUMER = 'Consumer';
const TOTAL = 'Total';
const AUTHOR = 'Author';
const OTHER = 'Other';
const SERVICEID = 'serviceId';

function getUserAuditGraph($sectionTitle, $graphParams, $yaxis, $userid) {
    global $date;
    $dbTables = array( NETANSERVER_USERAUDITLOG_DETAILS, StatsDB::SITES);
    $where = "netanserver_userauditlog_details.siteid = sites.id AND sites.name = '%s'
              AND netanserver_userauditlog_details.typeid = '%s'";
    $sqlParamWriter = new SqlPlotParam();
    $sqlParam = SqlPlotParamBuilder::init()
                ->title($sectionTitle)
                ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                ->yLabel($yaxis)
                ->makePersistent()
                ->disableUserAgg()
                ->addQuery(
                    SqlPlotParam::DEFAULT_TIME_COL,
                    $graphParams,
                    $dbTables,
                    $where,
                    array('site', 'uid')
                 )
                ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    echo addLineBreak();
    $extraArgs = "&uid=$userid";
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320, $extraArgs);
}

function createTable( $params, $name, $title, $table, $where ) {

    drawHeader($title, 2, $name);

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables($table)
            ->where($where);

    foreach ($params as $key => $value) {
        $table->addSimpleColumn($value, $key);
    }

    echo $table->paginate( array(10, 100, 1000, 10000) )
               ->build()
               ->getTable();
    echo addLineBreak(2);
}

function main() {
    $statsDB = new StatsDB();
    $concurrentUserTypeid = 1;
    $definedUserTypeid = 2;
    drawHeader("User Statistics", 2, "concurrentuserHelp");
    $colsArr = array(
        'Reported Max Concurrent Users' => 'MAX(netanserver_userauditlog_details.totalUser)'
    );
    $name = 'concurrenttableHelp';
    $title = 'Concurrent Users';
    $table = array(NETANSERVER_USERAUDITLOG_DETAILS, 'sites');
    $where = $statsDB->where(NETANSERVER_USERAUDITLOG_DETAILS, 'time');
    $where .= " AND netanserver_userauditlog_details.typeid = $concurrentUserTypeid";
    createTable( $colsArr, $name, $title, $table, $where );

    $userauditGraphParam = array(
                'totalUser' => TOTAL,
                'noOfAnalystUser' => ANALYST,
                'noOfAuthorUser' => AUTHOR,
                'noOfConsumerUser' => CONSUMER,
                'noOfOtherUser' => OTHER
        );
    getUserAuditGraph(
        'Concurrent Users Statistics',
        $userauditGraphParam,
        'Number of Users',
        $concurrentUserTypeid
    );
    $colsArr = array(
        ANALYST => 'netanserver_userauditlog_details.noOfAnalystUser',
        AUTHOR => 'netanserver_userauditlog_details.noOfAuthorUser',
        CONSUMER => 'netanserver_userauditlog_details.noOfConsumerUser',
        OTHER => 'netanserver_userauditlog_details.noOfOtherUser',
        TOTAL => 'netanserver_userauditlog_details.totalUser'
    );
    $name = 'definedtableHelp';
    $title = 'Registered Users';
    $table = array(NETANSERVER_USERAUDITLOG_DETAILS, 'sites');
    $where = $statsDB->where(NETANSERVER_USERAUDITLOG_DETAILS, 'time');
    $where .= " AND netanserver_userauditlog_details.typeid = $definedUserTypeid
                order by time desc limit 1";
    createTable( $colsArr, $name, $title, $table, $where );

    getUserAuditGraph(
        'Registered Users',
        $userauditGraphParam,
        'Number of Users',
        $definedUserTypeid
    );
    $where = $statsDB->where(NETANSERVER_USER_SESSION_STATISTICS_DETAILS, 'time');
    $where .= "AND netanserver_user_session_statistics_details.userName NOT IN
           ('ScheduleUpdate', 'monitoring@SPOTFIRESYSTEM')";
    $mysql = "
SELECT
    COUNT(*)
FROM
    netanserver_user_session_statistics_details, sites
WHERE
    $where
    ";

    $row = $statsDB->queryRow($mysql);
    $where = $statsDB->where(NETANSERVER_USER_SESSION_STATISTICS_DETAILS, 'time');
    $where .= "AND netanserver_user_session_statistics_details.userName
        NOT IN ('ScheduleUpdate', 'monitoring@SPOTFIRESYSTEM')
        AND netanserver_user_session_statistics_details.serviceId!=''";
    if($row[0] > 0) {
        $serviceIdResultSet = $statsDB->query("
SELECT
    DISTINCT(serviceId)
FROM
    netanserver_user_session_statistics_details, sites
WHERE
    $where
    ");
        $noOfSessionID = $statsDB->getNumRows($serviceIdResultSet);
        drawHeader("User Login Frequency", 2, "definedGraphHelp");
        if($noOfSessionID > 0) {
            echo "<H2>Aggregated View</H2>";
        }
        $userName = "'ScheduleUpdate', 'scheduledupdates@SPOTFIRESYSTEM', 'monitoring@SPOTFIRESYSTEM',
                    'automationservices@SPOTFIRESYSTEM'";
        $modelledGraph = new ModelledGraph('ENIQ/useraudit_graphSession');
        $params = array( 'userName' => $userName );
        $graph = $modelledGraph->getImage($params);
        plotgraphs( array($graph) );
        $helpLabelCounter = 0;
        $webPlayerInstanceIdHelp = "";
        while( $serviceIdResultSet = $statsDB->getNextNamedRow() ) {
            $helpLabelCounter;
            $playerInstanceId = $serviceIdResultSet[SERVICEID];
            drawHeader(
                "Web Player Instance ID - {$playerInstanceId}",
                2,
                "webPlayerInstanceId_$helpLabelCounter",
                $webPlayerInstanceIdHelp
            );
            $modelledGraph = new ModelledGraph('ENIQ/useraudit_graphSessionId');
            $params = array( 'userName' => $userName, SERVICEID => $serviceIdResultSet[SERVICEID]);
            plotgraphs( array( $modelledGraph->getImage($params) ) );
            unset($playerInstanceId);
            $helpLabelCounter++;
        }
    }
}
main();
include_once "../common/finalise.php";
