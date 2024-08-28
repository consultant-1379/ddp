<?php
$pageTitle = "OPS";

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const SERVERS_HOSTNAME_COL = 'servers.hostname';
const SERVER_LABEL = 'Server';

function winfiolSessionParams() {
    return array(
        SERVERS_HOSTNAME_COL => SERVER_LABEL,
        'state' => 'Session State',
        'SUM(n_sessions)' => 'Total'
    );
}

function winfiolCommandParams() {
    return array(
        SERVERS_HOSTNAME_COL => SERVER_LABEL,
        'SUM(n_commands)' => 'Total'
    );
}

function opsGraphParams() {
    return array(
        'successfulCliSession' => "Successful CLI Sessions",
        'failedCliSession' => "Failed CLI Sessions",
        'guiSessionsCompleted' => "GUI Sessions Completed"
    );

}

function activeGraphParams() {
    return array(
        'cliSessionsActive' => "CLI Sessions Active",
        'guiSessionsActive' => "GUI Sessions Active"
    );
}

function opsTable( $table ) {
    global $statsDB;

    drawHeader("OPS Sessions Completed", 2, "ops_compsessions");
    $where = $statsDB->where($table);
    $where .= " AND $table.serverid = servers.id";
    $tables = array($table, StatsDB::SITES, StatsDB::SERVERS);

    $table = SqlTableBuilder::init()
                    ->name("sessions")
                    ->tables($tables)
                    ->where($where)
                    ->groupBy(array(SERVERS_HOSTNAME_COL))
                    ->addSimpleColumn(SERVERS_HOSTNAME_COL, SERVER_LABEL)
                    ->addSimpleColumn("SUM(successfulCliSession)", 'Successful CLI Sessions')
                    ->addSimpleColumn("SUM(failedCliSession)", 'Failed CLI Sessions')
                    ->addSimpleColumn("SUM(guiSessionsCompleted)", 'GUI Sessions')
                    ->build();

    echo $table->getTable();
}

function drawGraphs( $params, $table ) {
    global $date;

    $where = "$table.siteid = sites.id AND sites.name = '%s' AND $table.serverid = servers.id";
    $dbTables = array($table, StatsDB::SITES, StatsDB::SERVERS);

    $graphs = array();
    $sqlParamWriter = new SqlPlotParam();

    foreach ( $params as $column => $title ) {
        $sqlParam = SqlPlotParamBuilder::init()
                        ->title('%s')
                        ->titleArgs( array('title') )
                        ->type(SqlPlotParam::STACKED_BAR)
                        ->barwidth(60)
                        ->yLabel("")
                        ->forceLegend()
                        ->makePersistent()
                        ->addQuery(
                            SqlPlotParam::DEFAULT_TIME_COL,
                            array( $column => $title ),
                            $dbTables,
                            $where,
                            array('site'),
                            SERVERS_HOSTNAME_COL
                        )
                        ->build();

        $extraArgs = "title=$title";
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320, $extraArgs);
    }
    plotGraphs($graphs);
}

function winfiolTable( $table, $extraWhere, $title, $params ) {
    global $statsDB;

    $dbTables = array( $table, StatsDB::SITES, StatsDB::SERVERS);
    $where = $statsDB->where($table);

    $winfiolTable = SqlTableBuilder::init()
              ->name($title)
              ->tables($dbTables)
              ->where($where . $extraWhere);
    foreach ( $params as $a => $b ) {
        $winfiolTable->addSimpleColumn($a, $b);
    }
    echo $winfiolTable->build()->getTable();
}

function winfiolGraphs( $table, $title, $params, $multiSeries ) {
    global $statsDB, $site, $date;

    $sqlParamWriter = new SqlPlotParam();
    $dbTables = array( $table, StatsDB::SITES, StatsDB::SERVERS);
    $where = "$table.siteid = sites.id AND sites.name = '%s' AND $table.serverid = servers.id";

    $sqlParam = SqlPlotParamBuilder::init()
              ->title($title)
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel('')
              ->forceLegend()
              ->makePersistent()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $params,
                  $dbTables,
                  $where,
                  array('site'),
                  $multiSeries
              )
              ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    $graph[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);
    plotGraphs($graph);
}

function mainFlow() {
    echo "<H1>OPS</H1>\n";
    $dbTable = 'enm_ops_server';
    opsTable($dbTable);
    echo addLineBreak();
    $params = opsGraphParams();
    drawGraphs( $params, $dbTable );

    drawHeader("Sessions Active", 2, "ops_actsessions");
    $params = activeGraphParams();
    drawGraphs( $params, $dbTable );

    echo addLineBreak();
    echo "<H1>Winfiol</H1>\n";

    drawHeader("Sessions", 2, "winfiol_sessions");
    $dbTable = 'enm_winfiol_sessions';
    $extraWhere = " AND $dbTable.serverid = servers.id GROUP BY servers.hostname, $dbTable.state";
    $params = winfiolSessionParams();
    winfiolTable( $dbTable, $extraWhere, 'winfiol_sessions', $params );
    echo addLineBreak();

    $params = array( 'n_sessions' => 'Sessions' );
    $multiSeries = 'CONCAT(servers.hostname,":",state)';
    winfiolGraphs( $dbTable, 'OPS Winfiol Sessions', $params, $multiSeries );

    drawHeader("Commands", 2, "winfiol_commands");
    $dbTable = 'enm_winfiol_commands';
    $extraWhere = " AND $dbTable.serverid = servers.id GROUP BY servers.hostname";
    $params = winfiolCommandParams();
    winfiolTable( $dbTable, $extraWhere, 'winfiol_commands', $params );
    echo addLineBreak();

    $params = array( 'n_commands' => 'Commands' );
    winfiolGraphs( $dbTable, 'OPS Winfiol Commands', $params, SERVERS_HOSTNAME_COL );
}

mainFlow();

require_once PHP_ROOT . "/common/finalise.php";

