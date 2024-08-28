<?php
$pageTitle = "Data Task Information";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/ENIQ/functions.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
const SETTYPE = 'setType';

function getLinks() {
    global $statsDB, $site, $date;

    $statsDB->query("
SELECT
    DISTINCT eniq_settype_names.name AS setType
FROM
    eniq_settype_names,
    eniq_meta_transfer_batches,
    sites
WHERE
    sites.name = '$site' AND
    sites.id = eniq_meta_transfer_batches.siteid AND
    eniq_meta_transfer_batches.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    eniq_meta_transfer_batches.settype = eniq_settype_names.id");

    $links = array();

    while ( $row = $statsDB->getNextNamedRow() ) {
        $type = $row[SETTYPE];
        $params = array( SETTYPE => $type );
        $links[] = makeLink('/ENIQ/data_task_info.php', $type, $params);
    }

    echo makeHtmlList( $links );
}

function getTable( $setType ) {
    $url = makeSelfLink();
    $params = array( ModelledTable::URL => $url, SETTYPE => $setType);
    $table = new ModelledTable('ENIQ/data_task_info_table', '', $params);
    echo $table->getTableWithHeader( $setType, 1, '' );
    echo addLineBreak();
}

function getTaskName( $tId ) {
    global $statsDB;

    $query = "SELECT name FROM eniq_task_names WHERE id = '$tId'";
    $statsDB->query($query);

    return  $statsDB->getNextRow()[0];
}

function getGraph( $id ) {
    $ids = explode(':', $id);
    $taskName = getTaskName( $ids[0] );
    $modelledGraph = new ModelledGraph('ENIQ/data_task_info_tasknames');
    $params = array( 'taskId' => $ids[0], 'taskName' => $taskName, 'setTypeId' => $ids[1] );
    plotgraphs( array( $modelledGraph->getImage($params) ) );
}

function mainflow() {
    getLinks();

    $setType = requestValue(SETTYPE);

    if ($setType) {
        getTable( $setType );
    }
}

$plot = requestValue('plot');
$sel = requestValue('selected');

if ( $plot == 'plotTask' ) {
    getGraph( $sel );
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
