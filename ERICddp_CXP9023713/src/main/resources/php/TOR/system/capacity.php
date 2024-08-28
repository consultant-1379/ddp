<?php
$pageTitle = "System Capacity";
$DISABLE_UI_PARAMS = array( "action" );

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

const TABLE_NAME = "enm_capacity";
const AREA_COL = "area";

function plot($action, $areas) {
    global $date;

    $dbTables = array(TABLE_NAME, StatsDB::SITES);
    $where = "enm_capacity.siteid = sites.id AND sites.name = '%s' AND enm_capacity.area IN (%s)";
    if ( $action === "plotused" ) {
        $title = "Area Used";
        $yLabel = "";
        $col = 'used';
    } else {
        $title = "Area Capacity %";
        $yLabel = "%";
        $col = 'IFNULL( (used*100)/available, 0)';
    }

    $builder = SqlPlotParamBuilder::init()
             ->title($title)
             ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
             ->yLabel($yLabel)
             ->addQuery(
                 'enm_capacity.date',
                 array($col => 'value'),
                 $dbTables,
                 $where,
                 array('site','areas'),
                 AREA_COL
             );

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($builder->build());
    $url = $sqlParamWriter->getURL(
        $id,
        date('Y-m-d', strtotime($date.'-1 month')) . " 00:00:00",
        "$date 23:59:59",
        "areas='" . implode("','", $areas) . "'"
    );
    header("Location:" . $url);
}

function main() {
    global $statsDB;

    $table = SqlTableBuilder::init()
           ->name(TABLE_NAME)
           ->tables(array(TABLE_NAME, StatsDB::SITES))
           ->where($statsDB->where(TABLE_NAME, 'date', true))
           ->addColumn(AREA_COL, AREA_COL, "Area")
           ->addColumn("used", "used", "Used" )
           ->addColumn("available", "available", "Capacity %", 'formatCapacity')
           ->ctxMenu(
               DDPTable::ACTION,
               true,
               array(
                   'plotused' => 'Plot Used',
                   'plotcap' => 'Plot Capacity %'
               ),
               makeSelfLink(),
               AREA_COL
           )
           ->build();

    echo <<<EOF

<script type="text/javascript">
function formatCapacity(elCell, oRecord, oColumn, oData) {
    elCell.innerHTML = "";
    if ( oData != null ) {
        var used = oRecord.getData("used");
        elCell.innerHTML = Number.parseFloat((used/oData)*100).toFixed(1);
    }
}
</script>

EOF;

    echo $table->getTableWithHeader("System Capacity");
}

$action = requestValue(DDPTable::ACTION);
if ( is_null($action) ) {
    main();
} else {
    plot($action, explode(",", requestValue("selected")));
}

include_once PHP_ROOT . "/common/finalise.php";

