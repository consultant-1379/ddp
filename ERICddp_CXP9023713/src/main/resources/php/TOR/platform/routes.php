<?php
$pageTitle = "Routes";

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/common/routeFunctions.php";

function mainFlow() {
    global $site, $date, $statsDB;

    $routes = new Routes($statsDB, $site, $date, null);
    $thisURL = makeSelfLink();

    echo $routes->getTable($thisURL)->getTableWithHeader("Routes");
}

if ( requestValue('action') === 'plotRouteGraphs' ) {
    $selected = requestValue('selected');
    plotRoutes( $selected );
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

