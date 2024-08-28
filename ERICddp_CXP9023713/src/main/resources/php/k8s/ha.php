<?php
$pageTitle = "K8S HA Events";


require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

$countsByContainer = new ModelledTable('common/k8s_ha_container_counts', 'container_counts');
echo $countsByContainer->getTableWithHeader("Counts Per Container Type");

$eventsGraph = new ModelledGraph('common/k8s_ha_events');
echo $eventsGraph->getImage();

$eventsTable = new ModelledTable('common/k8s_ha_events', 'k8s_ha_events');
echo $eventsTable->getTableWithHeader("Events");

require_once PHP_ROOT . "/common/finalise.php";
