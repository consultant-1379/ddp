<?php
$pageTitle = "WINFIOL SERVICE";

include_once "../../common/init.php";

require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function mainFlow() {
    global $site, $date, $statsDB;

    $services = array();
    $cmEditURLs = array();
    $models = array();

    $servicequery = "
SELECT
    DISTINCT(service)
FROM
    enm_winfiol_services,
    sites
WHERE
    time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_winfiol_services.siteid = sites.id AND
    sites.name = '$site'
    ";

    $statsDB->query($servicequery);

    while ( $row = $statsDB->getNextRow() ) {
        $services[] = $row[0];
        $cmEditURLs[] = makeAnchorLink("$row[0]", "$row[0]");
    }

    echo makeHTMLList($cmEditURLs);

    $models = array(
        'TOR/cm/winfiol_connection',
        'TOR/cm/winfiol_disconnection',
        'TOR/cm/winfiol_failedconnection',
        'TOR/cm/winfiol_commands',
        'TOR/cm/winfiol_opensession'
     );

    foreach ( $services as $service ) {
        $dailyTotals = new ModelledTable( 'TOR/cm/winfiol_service', "$service", array('service' => $service) );
        echo $dailyTotals->getTableWithHeader("$service Daily Totals");
        drawHeader($service." winfiol service", 2, $service."_winfiol_service");
        $graphs = array();
        foreach ( $models as $modGraph ) {
            $graph = new ModelledGraph($modGraph);
            $graphs[] = $graph->getImage(array('service' => $service));
        }
        plotgraphs( $graphs );
    }

}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

