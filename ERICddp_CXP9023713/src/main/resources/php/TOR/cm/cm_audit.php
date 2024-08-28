<?php
$pageTitle = "CM Audit Service";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

function mainFlow() {
    $table = new ModelledTable( "TOR/cm/cm_cell_audit", 'cellAudit' );
    echo $table->getTableWithHeader("Cell Audit");
    echo addLineBreak(2);

    drawHeader('Audit Performance', 1, 'auditPerformance');
    $modelledGraph = new ModelledGraph( 'TOR/cm/cm_audit_performance');
    plotgraphs( array( $modelledGraph->getImage() ) );
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
