<?php

$pageTitle = "KPI Calculator Service";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";

function showLinks($serverIdsArr, $sg) {

    $links = array();

    $kafkalink = kafkaLinks($serverIdsArr, $sg);
    if ( $kafkalink ) {
        $links[] = $kafkalink;
    }

    echo makeHTMLList($links);

}

function mainFlow() {
    global $statsDB, $site, $date;

    $sg = array('eric-pm-kpi-calculator');
    $srv = k8sGetServiceInstances($statsDB, $site, $date, $sg);
    $serverIdsArr = array_values($srv);

    showLinks( $serverIdsArr, $sg );

    $table = new ModelledTable( "ECSON/kpi/kpi_service", 'CalculationTime' );
    echo $table->getTableWithHeader("KPI Calculation Time");
    echo addLineBreak();

    $kpi_graph = new ModelledGraph('ECSON/kpi/kpi_calculation_time');
    plotgraphs( array( $kpi_graph->getImage() ) );

}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

