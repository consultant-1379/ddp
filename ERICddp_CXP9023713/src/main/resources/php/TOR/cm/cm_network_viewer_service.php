<?php
$pageTitle = 'Network Viewer Service';

include_once "../../common/init.php";

require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

function drawTables( $dbTable, $params ) {
    global $site, $date;

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '$site'
              AND $dbTable.serverid = servers.id
              AND $dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              GROUP BY servers.hostname WITH ROLLUP";

    $builder = SqlTableBuilder::init()
             ->name('DailyTotals')
             ->tables(array( $dbTable, StatsDB::SITES, StatsDB::SERVERS ))
             ->where($where)
             ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    foreach ($params as $key => $value) {
        $builder->addSimpleColumn("SUM($value)", $key);
    }
    drawHeaderWithHelp('Daily Totals', 2, 'DailyTotals');
    echo $builder->build()->getTable();
}

function tableParams() {
    return array('Successful Set Locations' => 'numberOfSuccessfulSetLocation',
                 'Failed Set Locations' => 'numberOfFailedSetLocation');
}


function mainFlow() {
    global $date, $statsDB;

    $dbTable = 'enm_netex_AddNodeInstr';

    if ( $statsDB->hasData($dbTable) ) {
        $tableParams =  tableParams();
        drawTables($dbTable, $tableParams);
    } else {
        echo "<H1>No Data Available In $dbTable For $date<H1>";
    }
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

