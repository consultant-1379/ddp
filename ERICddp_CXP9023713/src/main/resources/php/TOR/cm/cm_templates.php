<?php
$pageTitle = "CM Templates";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const PARAMS = 'params';
const TABLE_NAME = 'TABLE_NAME';
const TITLE = 'title';


function getCounterParams() {
    $graphParams = array(
        array('Created' => 'numTemplatesCreated',
              'Listed' => 'numTemplatesListed'
        ),
        array('Details' => 'numTemplatesRetrieved',
              'Deleted' => 'numTemplatesDeleted'
        )
    );
    return array( TITLE => 'Counter Totals', TABLE_NAME => "CounterTotals", PARAMS => $graphParams);
}

function getTimingParams() {
    $graphParams = array(
        array('Create' => 'createTWCExecutionTimeTotalMillis',
              'Delete' => 'deleteTExecutionTimeTotalMillis'
        ),
        array('Get' => 'getTExecutionTimeTotalMillis',
              'GetByName' => 'getTBNExecutionTimeTotalMillis'
        ),
        array('GetAll' => 'getTSExecutionTimeTotalMillis')
    );
    return array( TITLE => 'Timing Totals', TABLE_NAME => "TimingTotals", PARAMS => $graphParams);
}

function drawTables($dbTable, $params, $help) {
    global $date, $site;

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '$site'
              AND $dbTable.serverid = servers.id
              AND $dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY hostname WITH ROLLUP";

    $builder = SqlTableBuilder::init()
        ->name($params[TABLE_NAME])
        ->tables(array( $dbTable, StatsDB::SITES, StatsDB::SERVERS ))
        ->where($where)
        ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    $prms = $params[PARAMS];

    foreach ($prms as $param) {
        foreach ($param as $key => $value) {
            $builder->addSimpleColumn("SUM($value)", $key);
        }
    }
    drawHeaderWithHelp($params[TITLE], 2, $help, '', '');
    echo $builder->build()->getTable();
    echo addLineBreak(1);
}

function plotGraph($graphParams, $dbTable, $ylabel) {
    $params = $graphParams[PARAMS];
    $graphTable = new HTML_Table("border=0");
    foreach ($params as $graphParam) {
        $row = array();
        foreach ($graphParam as $key => $value) {
            $row[] = generateGraph($value, $key, $dbTable, $ylabel);
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
    echo addLineBreak(2);
}

function generateGraph($col, $title, $dbTable, $ylabel) {
    global $date;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '%s'
              AND $dbTable.serverid = servers.id";

    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
        ->title($title)
        ->type(SqlPlotParam::STACKED_BAR)
        ->barwidth(60)
        ->yLabel($ylabel)
        ->makePersistent()
        ->forceLegend()
        ->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            array ($col => $title),
            $dbTables,
            $where,
            array('site'),
            'servers.hostname'
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 550, 320);
}

function mainFlow($dbTable) {
    global $date, $statsDB;

    echo '<H1>CM Templates</H1>';
    if ( $statsDB->hasData($dbTable) ) {
        $params = getCounterParams($dbTable);
        drawTables($dbTable, $params, 'instr');
        plotGraph($params, $dbTable, 'Template Number');

        $params = getTimingParams();
        drawTables($dbTable, $params, 'timing');
        plotGraph($params, $dbTable, 'Execution Time(msec)' );
    } else {
        echo "<H1>No Data Available In $dbTable For $date<H1>";
    }
}

mainFlow('enm_cmutilities');

include_once PHP_ROOT . "/common/finalise.php";

