<?php

$pageTitle = "FS Snapshot Utilization";
const FS_SNAPSHOT_UTILIZATION = "FS Snapshot Utilization";
const FS_SNAPSHOT_UTILIZATION_TABLE = 'eniq_fs_snapshot_utilization';

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function createTable() {
    global $host;
    $statsDB = new StatsDB();
    $colsArr = array(
                    'Time'        => 'time',
                    'File System' => 'fileSystem',
                    'Pool'        => 'pool',
                    'Attributes'  => 'attributes',
                    'Size (no unit(GB), g(GB), m(MB), t(TB), k(KB))' => 'size',
                    'Pool Origin' => 'poolOrigin',
                    'Data%'       => 'data_percent'
                );
    $name = 'fsSnapshotUtilizationHelp';
    $title = FS_SNAPSHOT_UTILIZATION;
    $table = array(FS_SNAPSHOT_UTILIZATION_TABLE, 'sites', 'servers');
    $where = $statsDB->where(FS_SNAPSHOT_UTILIZATION_TABLE, 'time');
    $where .= " AND servers.id = eniq_fs_snapshot_utilization.serverid
                AND servers.hostname = '$host'";

    drawHeader($title, 2, $name);

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables($table)
            ->where($where);

    foreach ($colsArr as $key => $value) {
        $table->addSimpleColumn($value, $key);
    }

    echo $table->paginate( array(20, 100, 1000, 10000) )
               ->build()
               ->getTable();
    echo addLineBreak(2);
}

function showsnapshotUtilizationStatGraph() {
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();
    $tables = array(FS_SNAPSHOT_UTILIZATION_TABLE, 'sites', 'servers');

    $where = "sites.name = '%s' AND
        servers.hostname = '%s' AND
        eniq_fs_snapshot_utilization.siteid = sites.id AND
        eniq_fs_snapshot_utilization.serverid = servers.id AND
        eniq_fs_snapshot_utilization.fileSystem NOT IN ('bkup_sw','connectd',
        'dwh_main','dwh_reader','dwh_temp_dbspace','installation','local_logs',
        'lv_root','lv_swap','lv_var','misc','rep_main','rep_temp','smf','swapvol',
        'dwh_main_dbspace')
    ";

    $sqlParam = SqlPlotParamBuilder::init()
        ->title(FS_SNAPSHOT_UTILIZATION)
        ->type('tsc')
        ->yLabel('Utilization (%)')
        ->makePersistent()
        ->forceLegend()
        ->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            array('data_percent' => FS_SNAPSHOT_UTILIZATION),
            $tables,
            $where,
            array( 'site', 'server'),
            'eniq_fs_snapshot_utilization.fileSystem'
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 840, 340);

    plotGraphs($graphs);
}

function main() {
    global $host;
    $host = requestValue('server');
    createTable();
    showsnapshotUtilizationStatGraph();
}

main();

include_once "../common/finalise.php";
