<?php
$pageTitle = "AMOS Commands";

$YUI_DATATABLE = true;

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const SUCCESS_COUNT = 'successcount';
const FAILURE_COUNT = 'failurecount';
const SITES = 'sites';
const ENM_AMOS_COMMANDS = 'enm_amos_commands';
const CMD_NAME = 'cmdName';
const CMD_COUNT = 'cmdCount';
const NO_BORDER = 'border=0';
const SERVERS_HOSTNAME = 'servers.hostname';
const ENM_AMOS_GENERALSCRIPTING_SESSIONSINSTR_TIME = 'enm_amos_generalscripting_sessionsinstr.time';
const ENM_AMOS_GEN_SITES_AND_SERVERS = "enm_amos_generalscripting_sessionsinstr, sites, servers";

function getDailyTotals() {
    global $date, $site, $statsDB;
    $data = array();

    $sql = "
SELECT a.hostname, a.successcount, a.failurecount, b.avg_per_minute, b.max_per_minute
FROM
   (SELECT IFNULL(servers.hostname, 'Totals') AS hostname,
   SUM(successcount) AS successcount,
   SUM(failurecount) AS failurecount
   FROM enm_amos_commands, sites, servers
   WHERE
    enm_amos_commands.siteid = sites.id AND sites.name = '$site' AND
    enm_amos_commands.serverid = servers.id AND
    enm_amos_commands.date = '$date'
   GROUP BY servers.hostname ASC with ROLLUP ) AS a,
   (SELECT IFNULL(servers.hostname, 'Totals') AS hostname,
   ROUND(AVG(enm_amos_clusters.commandCount), 0) AS avg_per_minute,
   MAX(enm_amos_clusters.commandCount) AS max_per_minute
   FROM enm_amos_clusters, sites, servers
   WHERE
    enm_amos_clusters.siteid = sites.id AND
    sites.name = '$site' AND
    enm_amos_clusters.serverid = servers.id AND
    enm_amos_clusters.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    GROUP BY servers.hostname ASC with ROLLUP ) AS b
WHERE a.hostname = b.hostname";

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $data[] = $row;
    }

     $cols = array(
        array( DDPTable::KEY => 'hostname', DDPTable::LABEL => 'Instance' ),
        array( DDPTable::KEY => 'successcount', DDPTable::LABEL => 'Successful Commands' ),
        array( DDPTable::KEY => 'failurecount', DDPTable::LABEL => 'Failed Commands' ),
        array( DDPTable::KEY => 'avg_per_minute', DDPTable::LABEL => 'Avg / min' ),
        array( DDPTable::KEY => 'max_per_minute', DDPTable::LABEL => 'Max / min' )
    );

    $table = new DDPTable(
        "data",
        $cols,
        array( 'data' => $data )
    );
    drawHeader("Daily Totals", 2, "Daily_Totals");
    echo $table->getTable();
}

function getSuccessfulAmosCommands() {
    global $site, $date;
    $cols = array(
                array(
                    'key' => CMD_NAME,
                    'db' => CMD_NAME,
                    DDPTABLE::LABEL => 'Command'
                ),
                array(
                    'key' => SUCCESS_COUNT,
                    'db' => 'SUM(successcount)',
                    DDPTABLE::LABEL => SqlPlotParam::COUNT_LABEL
                ),
            );

    $where = "enm_amos_commands.siteid = sites.id AND sites.name = '$site' AND enm_amos_commands.successcount > 0 AND
              enm_amos_commands.date = '$date' GROUP BY cmdName";

    $table = new SqlTable(
        "Successful_Amos_Commands",
        $cols,
        array( ENM_AMOS_COMMANDS, SITES),
        $where,
        true,
        array(
            DDPTABLE::ORDER => array( 'by' => SUCCESS_COUNT, 'dir' => 'DESC'),
            DDPTABLE::ROWS_PER_PAGE => 10,
            DDPTABLE::ROWS_PER_PAGE_OPTIONS => array(50, 500)
        )
    );
    echo $table->getTableWithHeader("Successful AMOS Commands", 2, "", "", "Successful_Amos_Commands");
}

function getFailedAmosCommands() {
    global $site, $date;
    $cols = array(
                array(
                    'key' => CMD_NAME,
                    'db' => CMD_NAME,
                    DDPTABLE::LABEL => 'Command'
                ),
                array(
                    'key' => FAILURE_COUNT,
                    'db' => 'SUM(failurecount)',
                    DDPTABLE::LABEL => SqlPlotParam::COUNT_LABEL
                ),
            );

    $where = "enm_amos_commands.siteid = sites.id AND sites.name = '$site' AND enm_amos_commands.failurecount > 0 AND
              enm_amos_commands.date = '$date' GROUP BY cmdName";

    $table = new SqlTable(
        "Failed_Amos_Commands",
        $cols,
        array( ENM_AMOS_COMMANDS, SITES),
        $where,
        true,
        array(
            DDPTABLE::ORDER => array( 'by' => FAILURE_COUNT, 'dir' => 'DESC'),
            DDPTABLE::ROWS_PER_PAGE => 10,
            DDPTABLE::ROWS_PER_PAGE_OPTIONS => array(50, 500)
        )
    );
    echo $table->getTableWithHeader("Failed AMOS Commands", 2, "", "", "Failed_Amos_Commands");
}

function getAmosUsers($statsDB) {
    global $date, $site;

    $row = $statsDB->queryRow("
    SELECT
        count(*)
    FROM
        enm_amos_users, sites
    WHERE
        enm_amos_users.siteid = sites.id AND sites.name = '$site' AND
        enm_amos_users.date = '$date'");

    if ( $row[0] == 0 ) {
        return null;
    }

    $cols = array(
                array( 'key' => 'userName', 'db' => "userName", DDPTABLE::LABEL => 'User'),
                array( 'key' => CMD_COUNT, 'db' => CMD_COUNT, DDPTABLE::LABEL => SqlPlotParam::COUNT_LABEL ),
    );

    $where = "enm_amos_users.siteid = sites.id AND sites.name = '$site' AND enm_amos_users.cmdCount > 0 AND
              enm_amos_users.date = '$date'";

    $table = new SqlTable(
        "Amos_Users",
        $cols,
        array( 'enm_amos_users', SITES),
        $where,
        true,
        array(DDPTABLE::ORDER => array( 'by' => CMD_COUNT, 'dir' => 'DESC'),
        DDPTABLE::ROWS_PER_PAGE => 10,
        DDPTABLE::ROWS_PER_PAGE_OPTIONS => array(50, 500)
        )
    );
    echo $table->getTableWithHeader("AMOS Users", 2, "", "", "Amos_Users");
}


function showAmosTables($statsDB) {
    global $date, $site;

    $row = $statsDB->queryRow("
    SELECT
        count(*)
    FROM
        enm_amos_commands eac, sites
    WHERE
        eac.siteid = sites.id AND sites.name = '$site' AND
        eac.date = '$date'");

    if ( $row[0] != 0 ) {
        /* Successfull Amos Command Table */
        getSuccessfulAmosCommands($statsDB);
        /* Failed Amos Command Table */
        getFailedAmosCommands($statsDB);
    }
    /*Amos Users table */
    getAmosUsers($statsDB);
}

function showAmosCommandGraph() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();

    drawHeaderWithHelp("Number of AMOS Commands", 1, "Number_of_AMOS_Commands");
    $queryList = array(
                    array(
                        SqlPlotParam::TIME_COL => 'enm_amos_clusters.time',
                        SqlPlotParam::MULTI_SERIES => SERVERS_HOSTNAME,
                        SqlPlotParam::WHAT_COL => array('enm_amos_clusters.commandCount' => 'count'),
                        SqlPlotParam::TABLES => "enm_amos_clusters, sites, servers",
                        SqlPlotParam::WHERE => "enm_amos_clusters.siteid = sites.id AND sites.name = '%s' AND
                                                enm_amos_clusters.serverid = servers.id",
                        SqlPlotParam::Q_ARGS => array( 'site' )
                    )
    );

    $sqlParam = array(
        SqlPlotParam::TITLE => 'Number of Amos Commands',
        SqlPlotParam::Y_LABEL => 'Number of Commands',
        SqlPlotParam::USER_AGG => 'true',
        SqlPlotParam::PERSISTENT => 'true',
        SqlPlotParam::TYPE => 'sb',
        SqlPlotParam::SB_BARWIDTH => 60,
        SqlPlotParam::FORCE_LEGEND => 'true',
        SqlPlotParam::QUERY_LIST => $queryList
    );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showAmosProcessesGraphs() {
    global $statsDB;

    if ( $statsDB->hasData( 'enm_amos_generalscripting_sessionsinstr', 'time', false, 'processes IS NOT NULL' ) ) {
        $param = 'enm_amos_generalscripting';
        $amosHelpBubble = 'amos_generalscripting';
    } else {
        $param = 'enm_amos_processes';
        $amosHelpBubble = 'AMOS_Processes';
    }

    drawHeader('AMOS Processes', 1, $amosHelpBubble);
    $modelledGraph = new ModelledGraph( 'TOR/cm/' . $param);
    plotgraphs( array( $modelledGraph->getImage() ) );
}

function showAmosSessionsGraphs() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();

    drawHeaderWithHelp("AMOS Sessions", 1, "AMOS_Sessions");
    $queryList = array(
                    array(
                        SqlPlotParam::TIME_COL => ENM_AMOS_GENERALSCRIPTING_SESSIONSINSTR_TIME,
                        SqlPlotParam::MULTI_SERIES => SERVERS_HOSTNAME,
                        SqlPlotParam::WHAT_COL => array(
                            'enm_amos_generalscripting_sessionsinstr.numCurrentSessions' => 'Sessions'
                        ),
                        SqlPlotParam::TABLES => ENM_AMOS_GEN_SITES_AND_SERVERS,
                        SqlPlotParam::WHERE => "enm_amos_generalscripting_sessionsinstr.siteid = sites.id AND
                                                sites.name = '%s' AND
                                                enm_amos_generalscripting_sessionsinstr.serverid = servers.id",
                        SqlPlotParam::Q_ARGS => array( 'site' )
                    )
    );

    $sqlParam = array(
        SqlPlotParam::TITLE => 'AMOS Sessions',
        SqlPlotParam::Y_LABEL => 'Sessions',
        SqlPlotParam::USER_AGG => 'true',
        SqlPlotParam::PERSISTENT => 'true',
        SqlPlotParam::TYPE => 'sb',
        SqlPlotParam::SB_BARWIDTH => 60,
        SqlPlotParam::FORCE_LEGEND => 'true',
        SqlPlotParam::QUERY_LIST => $queryList
         );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showAmosCPUUsageGraph() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();

    drawHeaderWithHelp("AMOS CPU Usage", 1, "AMOS_CPU_Usage");
    $queryList = array(
                    array(
                        SqlPlotParam::TIME_COL => ENM_AMOS_GENERALSCRIPTING_SESSIONSINSTR_TIME,
                        SqlPlotParam::MULTI_SERIES => SERVERS_HOSTNAME,
                        SqlPlotParam::WHAT_COL => array('enm_amos_generalscripting_sessionsinstr.cpuUsed' => 'CPU(%)'),
                        SqlPlotParam::TABLES => ENM_AMOS_GEN_SITES_AND_SERVERS,
                        SqlPlotParam::WHERE => "enm_amos_generalscripting_sessionsinstr.siteid = sites.id AND
                                                sites.name = '%s' AND
                                                enm_amos_generalscripting_sessionsinstr.serverid = servers.id AND
                                                enm_amos_generalscripting_sessionsinstr.cpuUsed IS NOT NULL",
                        SqlPlotParam::Q_ARGS => array( 'site' )
                    )
    );

    $sqlParam = array(
        SqlPlotParam::TITLE => 'AMOS CPU Usage',
        SqlPlotParam::Y_LABEL => 'CPU(%)',
        SqlPlotParam::USER_AGG => 'true',
        SqlPlotParam::PERSISTENT => 'true',
        SqlPlotParam::TYPE => 'sb',
        SqlPlotParam::SB_BARWIDTH => 60,
        SqlPlotParam::FORCE_LEGEND => 'true',
        SqlPlotParam::QUERY_LIST => $queryList
    );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showAmosMemoryUsageGraph() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();

    drawHeaderWithHelp("AMOS Memory Usage", 1, "AMOS_Memory_Usage");
    $queryList = array(
                    array(
                        SqlPlotParam::TIME_COL => ENM_AMOS_GENERALSCRIPTING_SESSIONSINSTR_TIME,
                        SqlPlotParam::MULTI_SERIES => SERVERS_HOSTNAME,
                        SqlPlotParam::WHAT_COL => array('enm_amos_generalscripting_sessionsinstr.memoryUsed' =>
                                                        'Memory(%)'
                                                  ),
                        SqlPlotParam::TABLES => ENM_AMOS_GEN_SITES_AND_SERVERS,
                        SqlPlotParam::WHERE => "enm_amos_generalscripting_sessionsinstr.siteid = sites.id AND
                                                sites.name = '%s' AND
                                                enm_amos_generalscripting_sessionsinstr.serverid = servers.id AND
                                                enm_amos_generalscripting_sessionsinstr.memoryUsed IS NOT NULL",
                        SqlPlotParam::Q_ARGS => array( 'site' )
                    )
    );

    $sqlParam = array(
        SqlPlotParam::TITLE => 'AMOS Memory Usage',
        SqlPlotParam::Y_LABEL => 'Memory(%)',
        SqlPlotParam::USER_AGG => 'true',
        SqlPlotParam::PERSISTENT => 'true',
        SqlPlotParam::TYPE => 'sb',
        SqlPlotParam::SB_BARWIDTH => 60,
        SqlPlotParam::FORCE_LEGEND => 'true',
        SqlPlotParam::QUERY_LIST => $queryList
    );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $statsDB = new StatsDB();

    /* Amos Daily Totals Table */
    getDailyTotals($statsDB);

    echo "<ul>";
    echo "<li><a href=\"#Successful_Amos_Commands_anchor\">AMOS Tables</a></li>";
    echo "</ul>";

    showAmosProcessesGraphs();
    showAmosSessionsGraphs();
    showAmosCommandGraph();
    showAmosCPUUsageGraph();
    showAmosMemoryUsageGraph();
    showAmosTables($statsDB);
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

