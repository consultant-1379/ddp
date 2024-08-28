<?php
$pageTitle = "K8S Events";


require_once "../common/init.php";
require_once PHP_ROOT . "/classes/K8SEvents.php";

const FILTER_PARAM = "filter";
const NON_JOB_EVENTS = 'nonjob';
const ALL_EVENTS = 'all';

$filter = requestValue(FILTER_PARAM);
if ( is_null($filter) ) {
    $filter = NON_JOB_EVENTS;
}
if ( $filter === ALL_EVENTS ) {
    $dropJobEvents = false;
} else {
    $dropJobEvents = true;
}

$k8sEvents = new K8SEvents($dropJobEvents, false, 0, 0);
$k8sEventsTable = $k8sEvents->getTable();
if ( is_null($k8sEventsTable) ) {
    echo "ERROR: No event data available";
} else {
    if ( $filter === NON_JOB_EVENTS ) {
        echo makeLink("/k8s/events.php", "All", array(FILTER_PARAM => ALL_EVENTS));
    } elseif ( $filter === ALL_EVENTS ) {
        echo makeLink("/k8s/events.php", "Filtered", array(FILTER_PARAM => NON_JOB_EVENTS));
    }
    echo $k8sEventsTable->getTableWithHeader("K8S Events");
}

require_once PHP_ROOT . "/common/finalise.php";
