<?php
$pageTitle = "Federated Identity Synchronizer";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function getParams() {
    return array(
        'EXT_IDP_SYNCRONIZATION' => array(
            'searchRequestsSuccess' => 'Search Requests Success',
            'searchResultsSuccess' => 'Search Results Success',
            'searchRequestsError' => 'Search Requests Error',
            'ldapErrors' => 'Generic LDAP Errors'
        ),
        'MERGE' => array(
            'extIpdEntries' => 'Entries from Ext IdP',
            'opendjEntries' => 'Entries from Internal OpenDj',
            'userCreateSuccess' => 'Success on Create User',
            'userCreateError' => 'Errors on Create User',
            'userUpdateSuccess' => 'Success on Update User',
            'userUpdateError' => 'Errors on Update User',
            'userDeleteSuccess' => 'Success on Delete User',
            'userDeleteError' => 'Errors on Delete User'
        ),
        'INTERNAL_DS' => array(
            'federatedUsers' => 'ENM Federated Users'
        ),
        'FORCED_DELETE' => array(
            'userDeleteSuccess' => 'Success on Delete User',
            'userDeleteError' => 'Errors on Delete User'
        )
    );
}

function drawTable( $name, $cols, $where, $tables ) {
    $builder = SqlTableBuilder::init()
                ->name( $name )
                ->tables( $tables )
                ->where( $where )
                ->addSimpleColumn( "servers.hostname", 'Instance' )
                ->addColumn( 'time', "taskStartTime", 'Task Start Time', 'ddpFormatTime' )
                ->addSimpleColumn( "taskDuration", 'Task Duration(ms)' );

    foreach ($cols as $db => $lbl) {
        $builder->addSimpleColumn($db, $lbl);
    }
    $builder->sortBy('time', DDPTable::SORT_ASC);
    echo $builder->paginate()->build()->getTable();
}

function drawGraph( $where, $tables, $title ) {
    global $date;

    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
              ->title('%s')
              ->titleArgs(array('title'))
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel("Duration(ms)")
              ->makePersistent()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  array(
                      'taskDuration' => 'Duration(ms)',
                  ),
                  $tables,
                  $where,
                  array('site', 'name'),
                  'servers.hostname'
              )
              ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);

    $extraArgs = "title=$title&name=$title";
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 500, 320, $extraArgs);
}

function mainFlow() {
    global $statsDB;
    $params = getParams();
    $dbTable = 'enm_fidm_syncronizer';
    $tables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    $tableWhere = $statsDB->where($dbTable);
    $graphs = array();

    foreach ($params as $name => $cols ) {
        drawHeader( $name, 1, $name );
        $where = $tableWhere . " AND $dbTable.serverid = servers.id AND $dbTable.usecase = '$name'";
        drawTable( $name, $cols, $where, $tables );

        $graphWhere = "$dbTable.siteid = sites.id AND sites.name = '%s' AND
                       $dbTable.serverid = servers.id AND $dbTable.usecase = '%s'";
        $graphs[] = drawGraph( $graphWhere, $tables, $name );
    }
    echo addLineBreak();
    drawHeader( 'Duration Graphs', 1, '' );
    plotGraphs($graphs);
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

