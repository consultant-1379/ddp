<?php
$pageTitle = "Topology Relation Service";

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

const TIME_COL = 'time';

function mainFlow() {
    global $site, $date, $debug;

    drawHeaderWithHelp("Relationship Requests", 2, "relreq");
    $where = "
enm_trs_relreq.siteid = sites.id AND sites.name = '$site' AND
enm_trs_relreq.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    $reqTable = SqlTableBuilder::init()
              ->name("relreq")
              ->tables(array('enm_trs_relreq', StatsDB::SITES))
              ->where($where)
              ->addColumn(TIME_COL, TIME_COL, 'Time', DDPTable::FORMAT_TIME)
              ->addSimpleColumn('reltypes', 'Relation Types')
              ->addSimpleColumn('n_poid', 'POIDs Count')
              ->addSimpleColumn('relfound', 'Relations Found')
              ->addSimpleColumn('nodetypes', 'Node Types (namespace:type)')
              ->addSimpleColumn('t_response', 'Response Time')
              ->addSimpleColumn('app', 'Application ID')
              ->paginate()
              ->sortBy(TIME_COL, DDPTable::SORT_ASC)
              ->build();
    echo $reqTable->getTable();

    drawHeaderWithHelp("View Switches", 2, "switchview");
    $where = "
enm_trs_switchview.siteid = sites.id AND sites.name = '$site' AND
enm_trs_switchview.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    $viewTable = SqlTableBuilder::init()
               ->name("switchview")
               ->tables(array('enm_trs_switchview', StatsDB::SITES))
               ->where($where)
               ->addColumn( TIME_COL, TIME_COL, 'Time', DDPTable::FORMAT_TIME)
               ->addSimpleColumn('view', 'View')
               ->paginate()
               ->build();
    echo $viewTable->getTable();
}

mainFlow();

require_once PHP_ROOT . "/common/finalise.php";

