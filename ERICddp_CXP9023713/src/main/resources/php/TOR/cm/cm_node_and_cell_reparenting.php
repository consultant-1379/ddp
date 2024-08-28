<?php

$pageTitle = "Node and Cell Reparenting";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function mainFlow() {
    $table = new ModelledTable( 'TOR/cm/enm_cm_resource_requests', 'resource_requests' );
    echo $table->getTableWithHeader('Resource requests');
    $table = new ModelledTable( 'TOR/cm/enm_cm_aggregated_resource_requests', 'aggregated_resource_requests' );
    echo $table->getTableWithHeader('Aggregated resource requests');
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
