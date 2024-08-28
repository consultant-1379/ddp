<?php
$pageTitle = "Caches";

require_once "../../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/Caches.php";

function plotCaches($cacheIdsStr) {
    global $site, $date;

    $statsDB = new StatsDB();
    $cachesByName = array();
    $statsDB->query("SELECT id, name FROM enm_cache_names WHERE id IN ($cacheIdsStr)");
    while ( $row = $statsDB->getNextRow() ) {
      $cachesByName[$row[1]] = $row[0];
    }

    $caches = new Caches($statsDB, $site, $date, null);
    foreach ( $cachesByName as $name => $id ) {
        echo "<H3 id='$id'>$name</H3>\n";
        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow($caches->getGraphsForCacheId($id));
        echo $graphTable->toHTML();
    }
}

function mainFlow() {
    global $site, $date;

    $statsDB = new StatsDB();
    $caches = new Caches($statsDB, $site, $date, null);
    $thisURL = $_SERVER['REQUEST_URI']; //NOSONAR
    echo $caches->getTable($thisURL)->getTableWithHeader("Caches");
}

$action = requestValue('action');
if (! is_null($action)) {
    plotCaches(requestValue('selected'));
} else {
    mainFlow();
}

