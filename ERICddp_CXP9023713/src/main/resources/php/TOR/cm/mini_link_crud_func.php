<?php
require_once PHP_ROOT . "/classes/DDPTable.php";

const LABEL = 'label';
const OPERATION = 'operation';

function getQueryCols( $operation, $tbl, $type ) {
    $skipped = 0;
    if ( $operation === 'Create' || $operation === 'Modify' || $operation === 'Delete' ) {
        $skipped = 1;
    }

    if ( $type === "DT" ) {
        $col = "'${operation}' AS 'operation',";
    } elseif ( $type === "SUM" ) {
        $col = "IFNULL(servers.hostname, 'All Instances') AS 'operation',";
    }

    $cols = "$col
        SUM(tbl.numberOfSuccess${operation}Operations) AS 'countSuccess',
        SUM(tbl.numberOfFailed${operation}Operations) AS 'countFailure',";

    if ( $tbl === 'enm_cmwriter_minilink_outdoor') {
        if ( $skipped === 1 ) {
            $cols .= "SUM(tbl.numberOfSkipped${operation}Operations) AS 'countSkipped',";
        } else {
            $cols .= "'N/A' AS 'countSkipped',";
        }
    }

    return $cols . "MAX(tbl.successfull${operation}OperationsDuration) AS 'maxDurationSuccess',
        MAX(tbl.failed${operation}OperationsDuration) AS 'maxDurationFailure'";
}

function addQuery( $operation, $tbl ) {
    global $site, $date;

    $cols = getQueryCols( $operation, $tbl, 'DT' );

    return "
SELECT
    $cols
FROM
    $tbl AS tbl,
    sites,
    servers
WHERE
    tbl.siteid = sites.id AND
    sites.name = '$site' AND
    tbl.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    tbl.serverid = servers.id
GROUP BY 'operation' WITH ROLLUP";
}

function getTableCols( $tbl ) {
    $cols = array(
        array('key' => OPERATION, LABEL => 'CRUD Operation'),
        array('key' => 'countSuccess', LABEL => 'Count (Success)'),
        array('key' => 'countFailure', LABEL => 'Count (Failure)')
    );

    if ( $tbl === 'enm_cmwriter_minilink_outdoor' ) {
        $cols[] = array('key' => 'countSkipped', LABEL => 'Count (Skipped)');
    }

    $cols[] = array('key' => 'maxDurationSuccess', LABEL => 'Max Success Duration');
    $cols[] = array('key' => 'maxDurationFailure', LABEL => 'Max Failure Duration');

    return $cols;
}

function mlCRUDDailyTotals( $operationList, $tbl ) {
    global $statsDB;

    $sql = "";
    $totalsRowData = array();

    foreach ( $operationList as $operation) {
        if ( !empty($sql) ) {
            $sql .="\nUNION ";
        }
        $sql .= addQuery( $operation, $tbl );
    }

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $totalsRowData[] = $row;
    }

    foreach ($totalsRowData as $key => $d) {
        $newLink = makeSelfLink() . '&id=' .  $d[OPERATION];
        $d[OPERATION] = makeLinkForURL($newLink, $d[OPERATION]);
        $totalsRowData[$key] = $d;
    }

    $tblCols = getTableCols( $tbl );

    $table = new DDPTable(
        "CRUDDailyTotals",
        $tblCols,
        array('data' => $totalsRowData)
    );

    echo $table->getTable();
}

function mlCRUDSummary( $op, $tbl ) {
    global $site, $date, $statsDB;

    $qCols = getQueryCols( $op, $tbl, 'SUM' );

    $summarySql =
"SELECT
    $qCols
FROM
    $tbl AS tbl,
    sites,
    servers
WHERE
    tbl.siteid = sites.id AND
    sites.name = '$site' AND
    tbl.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    tbl.serverid = servers.id
GROUP BY servers.hostname WITH ROLLUP";

    $statsDB->query($summarySql);
    while ( $summaryRow = $statsDB->getNextNamedRow() ) {
        $summaryRowData[] = $summaryRow;
    }

    $tCols = getTableCols( $tbl );

    $summaryTable = new DDPTable(
        "DailySummary",
        $tCols,
        array('data' => $summaryRowData)
    );

    echo $summaryTable->getTable();
}

function drawGraphGroup($group) {
    $graphs = array();
    foreach ( $group['graphs'] as $modelledGraph ) {
        $graphs[] = array( $modelledGraph->getImage(null, null, null, 640, 240) );
    }
    plotgraphs( $graphs );
}

