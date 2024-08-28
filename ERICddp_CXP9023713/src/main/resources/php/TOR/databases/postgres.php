<?php
$pageTitle = "Postgres";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";


const POSTGSRENAME = "enm_postgres_names";
const POSTGRESNAMEVALUE = "enm_postgres_names.name";
const POSTGRESTATS = "enm_postgres_stats_db";
const POSTLARGE = "enm_postgres_largest_table";
const STARTTIME = " 00:00:00";
const ENDTIME = " 23:59:59";
const SERVERID = "serverid";

function dbsize($serverid) {
    global $statsDB, $date, $site;

    $row = $statsDB->queryRow("
SELECT
 alloc_table_size AS allocTableSize
FROM enm_postgres_dbsize, sites
WHERE
 enm_postgres_dbsize.siteid = sites.id AND sites.name = '$site' AND
 enm_postgres_dbsize.date BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_postgres_dbsize.dbid LIMIT 1");
$rowCol= $row[0];

    $where = $statsDB->where('DBSIZE', 'date');
    $where .= " AND DBSIZE.dbid = enm_postgres_names.id";
    $table = StatsDB::SITES.", ". POSTGSRENAME;
    if ( $rowCol ) {
         $where .= " AND DBSIZE.largest_table_id = enm_postgres_largest_table.id";
         $table .= ", ". POSTLARGE;
    }


    $whereJoin = $statsDB->where('enm_postgres_dbsize', 'date');
    $whereJoin .= " AND enm_postgres_dbsize.dbid = enm_postgres_names.id";
    if (! is_null($serverid)) {
        $whereJoin .= " AND enm_postgres_dbsize.serverid = $serverid";
    }

    $join = " ( SELECT dbid, MAX(date) AS MaxDateTime FROM enm_postgres_dbsize, enm_postgres_names, sites
               WHERE $whereJoin GROUP BY dbid ) AS JOINQ";
    $reqBind = SqlTableBuilder::init()
              ->name('enm_postgres_dbsize')
              ->tables(array($table, 'enm_postgres_dbsize AS DBSIZE'))
              ->join($join, "DBSIZE.dbid = JOINQ.dbid AND DBSIZE.date = JOINQ.MaxDateTime")
              ->where($where);
    if ( $rowCol ) {
          $reqBind->addSimpleColumn('DBSIZE.id', 'ID');
    }
    $reqBind->addSimpleColumn(POSTGRESNAMEVALUE, 'Database')
            ->addSimpleColumn('DBSIZE.sizemb', 'Size (MB)');
    if ( $rowCol ) {
         $reqBind->addSimpleColumn('DBSIZE.allocSize', 'Allocated Size (MB)')
                 ->addSimpleColumn('enm_postgres_largest_table.name', 'Largest Table Name')
                 ->addSimpleColumn('DBSIZE.current_table_size', 'Table Size (MB)')
                 ->addSimpleColumn('DBSIZE.alloc_table_size', 'Allocated Table Size (MB)');
    }
    $reqBind->paginate();
    echo $reqBind->build()->getTableWithHeader("Database Sizes", 2, '');
}

function listPostgreParams() {
    return array(

                POSTGRESNAMEVALUE => 'Database',
                'MAX(enm_postgres_stats_db.numbackends)' => 'Backends',
                'SUM(enm_postgres_stats_db.tup_inserted)' => 'Rows inserted',
                'SUM(enm_postgres_stats_db.tup_fetched)' => 'Rows fetched',
                'SUM(enm_postgres_stats_db.tup_returned)' => 'Rows returned',
                'SUM(enm_postgres_stats_db.tup_deleted)' => 'Rows deleted',
                'SUM(enm_postgres_stats_db.tup_updated)' => 'Rows updated',
                'SUM(enm_postgres_stats_db.xact_commit)' => 'Tx commited',
                'SUM(enm_postgres_stats_db.xact_rollback)' => 'Tx rolled back',
                'SUM(enm_postgres_stats_db.blks_hit)' => 'Blocks Hit',
                'SUM(enm_postgres_stats_db.blks_read)' => 'Blocks Read',
                'SUM(enm_postgres_stats_db.conflicts)' => 'Queries Cancelled - Conflicts',
                'SUM(enm_postgres_stats_db.deadlocks)' => 'Deadlocks',
                'SUM(enm_postgres_stats_db.temp_bytes)' => 'Temp Bytes',
                'SUM(enm_postgres_stats_db.temp_files)' => 'Temp Files'
    );
}


function postgresStatistics($serverid, $cols) {
    global $statsDB;

    $where = $statsDB->where(POSTGRESTATS);
    if (! is_null($serverid)) {
        $where .= " AND enm_postgres_stats_db.serverid = $serverid";
    } else {
        $where .= " AND serverid IS NULL";
    }

    $where .= " AND enm_postgres_stats_db.dbid = enm_postgres_names.id
                GROUP BY enm_postgres_stats_db.dbid
                ORDER BY enm_postgres_stats_db.xact_commit DESC";

    $reqBind = SqlTableBuilder::init()
              ->name(POSTGRESTATS)
              ->tables(array(POSTGRESTATS, POSTGSRENAME, StatsDB::SITES))
              ->where($where);
    foreach ($cols as $key => $value) {
             $reqBind->addSimpleColumn($key, $value);
    }
    $reqBind->paginate();
    echo $reqBind->build()->getTableWithHeader("Postgres Statistics", 2, '');
}

function postgresStatsParm() {
    return array(
                'numbackends' => 'Backends',
                'tup_inserted' => 'Rows inserted',
                'tup_fetched' => 'Rows fetched',
                'tup_returned' => 'Rows returned',
                'tup_deleted' => 'Rows deleted',
                'tup_updated' => 'Rows updated',
                'xact_commit' => 'Tx commited',
                'xact_rollback' => 'Tx rolled back',
                'blks_hit' => 'Blocks Hit',
                'blks_read' => 'Blocks Read',
                'conflicts' => 'Queries Cancelled - Conflicts',
                'deadlocks' => 'Deadlocks',
                'temp_bytes' => 'Temp Bytes',
                'temp_files' => 'Temp Files'
    );
}

function showpostgresStatGraph($serverid) {
    global $date;
    $dbTables = array(POSTGRESTATS, POSTGSRENAME, StatsDB::SITES);
    $where = "enm_postgres_stats_db.siteid = sites.id AND sites.name = '%s' AND
              enm_postgres_stats_db.dbid = enm_postgres_names.id";
    $queryArgNames = array('site');
    $queryArgValues = "";

    if (! is_null($serverid)) {
        $where = $where . " AND enm_postgres_stats_db.serverid = %d";
        $queryArgNames[] = SERVERID;
        $queryArgValues = "serverid=$serverid";
    } else {
        $where .= " AND serverid IS NULL";
    }

    $sqlParamWriter = new SqlPlotParam();
    $graphParams = postgresStatsParm();
    $graphs = array();
    foreach ( $graphParams as $column => $label ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($label)
            ->barwidth(900)
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel('')
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $column => $label ),
                $dbTables,
                $where,
                $queryArgNames,
                POSTGRESNAMEVALUE
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL(
            $id,
            $date . STARTTIME,
            $date . ENDTIME,
            true,
            600,
            400,
            $queryArgValues
        );
    }
    plotGraphs($graphs);
}

function checkpointsParams() {
    return array(
        'checkpointsTimed',
        'checkpointsRequest',
        'checkpointsBuffer',
        'bufferClean',
        'bufferBackend'
    );
}

function locksParams() {
    return array(
       'locks'
    );
}

function dbSizeServerbasedParams() {
    return array(
       'dbSize_servicebased'
    );
}

function dbSizeParams() {
    return array(
       'dbSize'
    );
}

function dbGraph($dbParams, $serverid) {
    if ( ! is_null($serverid) ) {
        $params = array( SERVERID => $serverid );
    } else {
        $params = "";
    }
    foreach ( $dbParams as $db ) {
        $modelledGraph = new ModelledGraph("common/postgres_" . $db);
        if ( ! is_null($serverid) ) {
            $graphs[] = $modelledGraph->getImage($params);
        } else {
            $graphs[] = $modelledGraph->getImage();
        }
    }
    plotgraphs( $graphs );
}

function mainFlowWithServerId($serverid) {
    global $date, $site, $statsDB;

    dbsize($serverid);
    echo addLineBreak();
    if ( ! is_null($serverid) ) {
        dbGraph(dbSizeServerbasedParams(), $serverid);
    } else {
        dbGraph(dbSizeParams(), $serverid);
    }

    $row = $statsDB->queryRow("
SELECT
 volume_stats.size AS size, volume_stats.used AS used
FROM volumes, volume_stats, servers, sites
WHERE
 volume_stats.serverid = servers.id AND servers.siteid = sites.id AND sites.name = '$site' AND
 volumes.id = volume_stats.volid AND volumes.name IN ('postgres_fs','postgresvol', 'mapper/postgresvg-postgresvol') AND
 volume_stats.date = '$date'");
    if (isset($row) && $row) {
        $fsSizeTable = new HTML_Table('border=0');
        $fsSizeTable->addRow(array("Filesystem Size (MB)",$row[0]));
        $fsSizeTable->addRow(array("Filesystem Used (MB)",$row[1]));
        $fileSizeHelpBubble = "DDP_Bubble_257_ENM_Postgres_FileSystem_Usage";
        drawHeaderWithHelp("File System Usage", 2, "fileSizeHelp", $fileSizeHelpBubble);
        echo $fsSizeTable->toHTML();
    }

    postgresStatistics($serverid, listPostgreParams());
    showpostgresStatGraph($serverid);

    if ( $statsDB->hasData("postgres_locks") ) {
        $table = new ModelledTable( "common/postgres_locks", 'PostgreLocks', array(SERVERID => $serverid) );
        echo $table->getTableWithHeader("Postgres Locks");
        echo addLineBreak();
        dbGraph(locksParams(), $serverid);
    }

    if ( $statsDB->hasData("postgres_checkpoints_bufferwrites") ) {
        $table = new ModelledTable( "common/postgres_checkpoints", 'Checkpoints', array(SERVERID => $serverid) );
        echo $table->getTableWithHeader("Checkpoints");
        echo addLineBreak();
        dbGraph(checkpointsParams(), $serverid);
    }
}

function showPostgresServers() {
    $table = new ModelledTable(
        'common/enm_postgres_stats_db_multi',
        'multi_summary',
        array(ModelledTable::URL => makeSelfLink())
    );
    echo $table->getTableWithHeader("Daily Summary");
    echo addLineBreak();
}

function mainFlow() {
    if ( issetURLParam("action") ) {
        $serverId = requestValue('selected');
    } else {
        $serverId = requestValue(SERVERID);
    }
    if ( is_null($serverId) || $serverId != "multi" ) {
        mainFlowWithServerId($serverId);
    } else {
        showPostgresServers();
    }
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
