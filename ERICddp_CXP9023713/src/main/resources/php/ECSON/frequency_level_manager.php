<?php
$pageTitle = "Frequency Layer Manager Service";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";

function flmALGparams() {
   return array(
       'alg_execution',
       'alg_execution_time'
   );
}

function flmKPIParams() {
   return array(
       'kpi_on_demand_calculation_requests',
       'kpi_calculation_time',
       'kpi_on_demand_calculation_time'
   );
}

function flmConfigurationParams() {
   return array(
       'configuration_get_request',
       'configuration_get_time',
       'configuration_update_requests',
       'configuration_update_time'
   );

}

function flmGraph($flmParams, $title, $id) {

    drawHeader($title, 2, $id);
    foreach ( $flmParams as $alg ) {
        $modelledGraph = new ModelledGraph("ECSON/flm/flm_" . $alg);
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs( $graphs );

}

function showLinks($serverIdsArr, $sg) {

    $links = array();
    $links[] = makeAnchorLink('FLM_Algorithm', 'FLM Algorithm');
    $links[] = makeAnchorLink('FLM_kpi_cal', 'FLM KPI Calculation');
    $links[] = makeAnchorLink('FLM_configuration', 'FLM Configuration');

    $kafkalink = kafkaLinks($serverIdsArr, $sg);
    if ( $kafkalink ) {
        $links[] = $kafkalink;
    }

    echo makeHTMLList($links);
}

function mainFlow() {
    global $statsDB, $site, $date;

    $sg = array('eric-son-frequency-layer-manager');
    $srv = k8sGetServiceInstances( $statsDB, $site, $date, $sg);
    $serverIdsArr = array_values($srv);

    showLinks( $serverIdsArr, $sg );

    $table = new ModelledTable( "ECSON/flm/frequency_layer_manager", 'FrequencyLayerManager' );
    echo $table->getTableWithHeader("Frequency Layer Manager");
    echo addLineBreak();

    flmGraph(flmALGparams(), 'FLM Algorithm', 'FLM_Algorithm');
    flmGraph(flmKPIParams(), 'FLM KPI calculation', 'FLM_kpi_cal');
    flmGraph(flmConfigurationParams(), 'FLM Configuration', 'FLM_configuration');

}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
