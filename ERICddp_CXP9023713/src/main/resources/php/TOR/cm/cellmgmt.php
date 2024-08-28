<?php
$pageTitle = "Cell Management";

require_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

$TABLE_CELL_MANAGEMENT = "enm_cm_cell_management";
$TABLE_USECASE = "enm_cm_cell_management_uc";

const MO_TYPEID_DB = 'motypeid';
const MO_TYPE_LABEL = 'MO Type';
const RAT_TYPES_DB = 'rattypes';
const RAT_TYPES_LABEL = 'Rat Types';
const REL_TYPEID_DB = 'reltypeid';
const REL_TYPE_LABEL = 'Relation Type';
const RESCOUNT_DB = 'rescount';
const NUM_PIODS = '# POIDs';
const NUM_FDNS = '# FDNs';
const DIRECTION_DB = 'direction';
const DIRECTION_LABEL = 'Direction';

function showUseCase($useCase) {
    global $site, $date, $TABLE_CELL_MANAGEMENT, $TABLE_USECASE;

    $useCaseParam = array(
        'CELL_LOCK' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'CELL_SOFTLOCK' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'CELL_UNLOCK' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'CREATE_CELL' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'CREATE_EXTERNAL_RELATION' => array(REL_TYPEID_DB => REL_TYPE_LABEL),
        'CREATE_RELATION' => array(REL_TYPEID_DB => REL_TYPE_LABEL),
        'DELETE_CELL' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'DELETE_EXTERNAL_CELL' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'DELETE_RELATION' => array(REL_TYPEID_DB => REL_TYPE_LABEL),
        'EXPORT_CELLS_DATA_INTERNAL' => array(),
        'MODIFY_CELL' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'MODIFY_CELL_FREQUENCY' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'MODIFY_EXTERNAL_CELL' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'MODIFY_EXTERNAL_CELL_FREQUENCY' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'MODIFY_FREQUENCY_GROUP' => array(MO_TYPEID_DB => MO_TYPE_LABEL),
        'READ_CELL_DATA' => array(RAT_TYPES_DB => RAT_TYPES_LABEL),
        'READ_CELL_DATA_INTERNAL' => array(RAT_TYPES_DB => RAT_TYPES_LABEL, RESCOUNT_DB => NUM_FDNS),
        'READ_CELLS' => array(RAT_TYPES_DB => RAT_TYPES_LABEL),
        'READ_CELLS_INTERNAL' => array(RAT_TYPES_DB => RAT_TYPES_LABEL, RESCOUNT_DB => NUM_PIODS),
        'READ_LOCAL_CELLS_INTERNAL' => array(RESCOUNT_DB => NUM_FDNS),
        'READ_RELATIONS' => array(
            RAT_TYPES_DB => RAT_TYPES_LABEL,
            REL_TYPEID_DB => REL_TYPE_LABEL,
            DIRECTION_DB => DIRECTION_LABEL
        ),
        'READ_RELATIONS_DATA_INTERNAL' => array(RESCOUNT_DB => NUM_FDNS),
        'READ_RELATIONS_INTERNAL' => array(
            RAT_TYPES_DB => RAT_TYPES_LABEL,
            REL_TYPEID_DB => REL_TYPE_LABEL,
            DIRECTION_DB => DIRECTION_LABEL,
            RESCOUNT_DB => NUM_FDNS
        )
    );

    $where = <<<EOQ
sites.name = '$site' AND
$TABLE_CELL_MANAGEMENT.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
$TABLE_USECASE.name = '$useCase'
EOQ;
    $builder = SqlTableBuilder::init()
             ->name("usecase_execs")
             ->tables( array($TABLE_CELL_MANAGEMENT) )
             ->join($TABLE_USECASE, "$TABLE_CELL_MANAGEMENT.usecaseid = $TABLE_USECASE.id")
             ->join(StatsDB::SITES, "$TABLE_CELL_MANAGEMENT.siteid = sites.id")
             ->where($where)
             ->addColumn("time", "time", "Time", DDPTable::FORMAT_TIME)
             ->addSimpleColumn("$TABLE_CELL_MANAGEMENT.result", "Result")
             ->addSimpleColumn("t_execution", "Execution Time");

    foreach ( $useCaseParam[$useCase] as $db => $label ) {
        if ( $db === REL_TYPEID_DB ) {
            $builder->addSimpleColumn("rel_names.name", $label);
            $builder->join("mo_names", "$TABLE_CELL_MANAGEMENT.reltypeid = rel_names.id", SqlTable::JOIN, "rel_names");
        } elseif ( $db === MO_TYPEID_DB ) {
            $builder->addSimpleColumn("mo_names.name", $label);
            $builder->join("mo_names", "$TABLE_CELL_MANAGEMENT.motypeid = mo_names.id");
        } else {
            $builder->addSimpleColumn($db, $label);
        }
    }

    echo "<H1>$useCase Executions</H1>\n";
    echo $builder->paginate()->build()->getTable();
}


function mainFlow() {
    global $site, $date, $debug, $rootdir, $webargs, $TABLE_CELL_MANAGEMENT, $TABLE_USECASE;

    $where = <<<EOQ
sites.name = '$site' AND
$TABLE_CELL_MANAGEMENT.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
EOQ;
    $table = SqlTableBuilder::init()
           ->name("usecases")
           ->tables( array($TABLE_CELL_MANAGEMENT) )
           ->join($TABLE_USECASE, "$TABLE_CELL_MANAGEMENT.usecaseid = $TABLE_USECASE.id")
           ->join(StatsDB::SITES, "$TABLE_CELL_MANAGEMENT.siteid = sites.id")
           ->where($where)
           ->groupBy(array("$TABLE_CELL_MANAGEMENT.usecaseid", "$TABLE_CELL_MANAGEMENT.result"))
           ->addColumn("usecase", "$TABLE_USECASE.name", "Usecase")
           ->addSimpleColumn("$TABLE_CELL_MANAGEMENT.result", "Result")
           ->addSimpleColumn(StatsDB::ROW_COUNT, "Count")
           ->addSimpleColumn("ROUND(AVG($TABLE_CELL_MANAGEMENT.t_execution))", "Average Execution Time")
           ->addSimpleColumn("MIN($TABLE_CELL_MANAGEMENT.t_execution)", "Min Execution Time")
           ->addSimpleColumn("MAX($TABLE_CELL_MANAGEMENT.t_execution)", "Max Execution Time")
           ->ctxMenu("show", false, array("exec" => "Executions"), makeSelfLink(), "usecase")
           ->build();
    echo $table->getTableWithHeader("Cell Management Usecase Execution", 2);

    echo "<br>\n";

    $dbTables = array( "enm_cm_cell_management", "enm_cm_cell_management_uc", StatsDB::SITES );
    $where = <<<EOQ
$TABLE_CELL_MANAGEMENT.siteid = sites.id AND sites.name = '$site' AND
$TABLE_CELL_MANAGEMENT.usecaseid = $TABLE_USECASE.id
EOQ;
    $sqlParam = SqlPlotParamBuilder::init()
              ->title("Cell Management Usecases")
              ->type(SqlPlotParam::XY)
              ->yLabel("Execution Time(msec)")
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  array( 't_execution' => "Duration" ),
                  $dbTables,
                  $where,
                  array('site'),
                  "enm_cm_cell_management_uc.name"
              )
              ->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 1024, 600);
}

$usecase = requestValue("selected");
if ( is_null($usecase) ) {
    mainFlow();
} else {
    showUseCase($usecase);
}

require_once PHP_ROOT . "/common/finalise.php";

