<?php
$pageTitle = "ftp connections";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const SPN_CNT = 'sftpSpawnCount';
const ACT_CNT = 'activeSftpCount';

function addGraphs( $dbTable, $graphParams, $yLabel = '' ) {
    global $date;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id ";
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();

    foreach ( $graphParams as $label => $column ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($label)
            ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
            ->makePersistent()
            ->forceLegend()
            ->yLabel($yLabel)
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $column => $label ),
                $dbTables,
                $where,
                array( 'site' ),
                "servers.hostname"
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] =  $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 500, 320);
    }
    plotGraphs($graphs);
}

function getParams() {
    return array(
              'Pull Successful File Transfer Connections' => 'successfulFtpConnectionCounter',
              'Pull Failed File Transfer Connections' => 'failureFtpConnectionCounter',
              'Pull Successful File Transfer Connection Minimum Duration' => 'successfulMinimumFtpConnectionDuration',
              'Pull Successful File Transfer Connection Maximum Duration' => 'successfulMaximumFtpConnectionDuration',
              'Pull Failed File Transfer Connection Minimum Duration' => 'failureMinimumFtpConnectionDuration',
              'Pull Failed File Transfer Connection Maximum Duration' => 'failureMaximumFtpConnectionDuration'
    );
}

function hasSpawnCount( $table ) {
    global $statsDB;

    $where = $statsDB->where($table);

    $sql = "
SELECT
    SUM(sftpSpawnCount)
FROM
    enm_smrs_log_stats,
    sites
WHERE
    $where";

    $statsDB->query($sql);

    return $statsDB->getNextRow()[0];
}

function mainFlow() {
    echo '<H1>FTP Connections</H1>';

    $dbTable = 'enm_smrs_log_stats';
    $hasSpawnCount = hasSpawnCount($dbTable);

    if ( $hasSpawnCount ) {
        $params = array( ACT_CNT => ACT_CNT, SPN_CNT => SPN_CNT );
        drawHeader('SFTP connections to SMRS', HEADER_2, 'esCounts');
        addGraphs( $dbTable, $params );
    } else {
        drawHeader("PUSH SFTP Connections per service group", HEADER_2, "sftpConnectionsHelp");
        $params = array( 'sftp connections' => ACT_CNT );
        addGraphs( $dbTable, $params, 'sftp connections' );
    }

    $dbTable = 'enm_mspmsftp_instr';
    $params = getParams();
    drawHeader('PULL SFTP Connections per service group', HEADER_2, 'ftpconnections');
    addGraphs( $dbTable, $params );
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
