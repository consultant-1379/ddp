<?php
$pageTitle = "Bulk Node CLI";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

require_once 'HTML/Table.php';
$TABLE_BULKNODE_CLI_LOGS = "enm_bulknode_cli_logs";

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site,$TABLE_BULKNODE_CLI_LOGS;
    $where =  $statsDB->where($TABLE_BULKNODE_CLI_LOGS);
    $queryTable = SqlTableBuilder::init()
           ->name("bulknode_cli")
           ->tables(array($TABLE_BULKNODE_CLI_LOGS, StatsDB::SITES))
           ->where($where)
           ->addSimpleColumn('job_id', 'JobId')
           ->addSimpleColumn('job_status', 'Status')
           ->addSimpleColumn('TIMEDIFF(time(time), SEC_TO_TIME(ROUND(duration/1000)))', 'Start Time')
           ->addSimpleColumn('time(time)', 'End Time')
           ->addColumn('time', 'duration', 'Duration', DDPTable::FORMAT_MSEC)
           ->addSimpleColumn('no_of_commands', 'No Of Commands')
           ->addSimpleColumn('collection_type', 'Collection Type')
           ->addSimpleColumn('collection_name', 'Collection Name')
           ->addSimpleColumn('total_sessions', 'Total Sessions')
           ->addSimpleColumn('sessions_completed', 'Sessions Completed')
           ->addSimpleColumn('sessions_skipped', 'Sessions Skipped')
           ->addSimpleColumn('sessions_not_supported', 'Sessions Not Supported')
           ->paginate()
           ->build();

   if ( $queryTable->hasRows() ) {
       echo $queryTable->getTableWithHeader("Bulk Node CLI", 1, "", "", "node_lrf_generation");
   }
}

$statsDB = new StatsDB();
mainFlow($statsDB);

include_once PHP_ROOT . "/common/finalise.php";

