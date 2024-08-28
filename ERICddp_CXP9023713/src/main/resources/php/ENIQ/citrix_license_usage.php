<?php
$pageTitle = "Citrix License Usage Statistics";

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function showLicUsageGraph() {

    $modelledGraph = new ModelledGraph('ENIQ/citrix_license_usage');
    plotgraphs( array( $modelledGraph->getImage() ) );
}

showLicUsageGraph();

include_once "../common/finalise.php";
