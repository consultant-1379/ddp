<?php
$pageTitle = "Aggregation";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function mainflow() {

    drawHeader( 'Aggregation', '1', '' );
    drawHeader( 'Running Aggregation Sessions', '2', 'statsAggregationHelp' );
    $modelledGraph = new ModelledGraph('ENIQ/stats_aggregation');
    plotgraphs( array( $modelledGraph->getImage() ) );
    echo addLineBreak();
    $table = new ModelledTable('ENIQ/stats_aggregation_session', 'statsAggregationSessionHelp');
    echo $table->getTableWithHeader("Aggregation Session Details");

}
mainflow();
include_once "../common/finalise.php";
