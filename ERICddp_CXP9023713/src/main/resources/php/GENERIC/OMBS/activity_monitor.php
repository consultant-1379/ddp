<?php
$pageTitle = "NBU";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function params() {
    return array(
        array('policy_summary','Policy Summary'),
        array('client_summary','Client Summary'),
        array('activity_summary','Activity Summary'),
        array('schedule_summary','Schedule Summary'),
        array('storage_unit_summary','StorageUnit Summary'),
        array('job_return_code_summary','JobReturnCode Summary'),
        array('activity_monitor','Activity Monitor'),
    );
}

function mainFlow() {
    $params = params();
    $links = array();
    $tables = array();

    drawHeader('Backup/Restore Performance', 2, 'bkp_perf');
    $modelledGraph = new ModelledGraph("GENERIC/OMBS/activity_monitor");
    $graph = $modelledGraph->getImage();
    plotgraphs( array( $graph ) );

    foreach ($params as $param) {
        $table = new ModelledTable("GENERIC/OMBS/$param[0]", $param[0]);
        if ( $table->hasRows() ) {
            $links[] = makeAnchorLink($param[0], $param[1]);
            $tables[] = $table->getTableWithHeader($param[1]);
        }
    }

    echo makeHTMLList($links);
    foreach ( $tables as $tab ) {
        echo $tab;
    }
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
