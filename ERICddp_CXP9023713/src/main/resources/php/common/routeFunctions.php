<?php
require_once 'HTML/Table.php';

function getRouteInstrTable($serverIds) {
  global $date, $site, $statsDB;

  $routes = new Routes($statsDB, $site, $date, $serverIds);
  $thisURL = makeSelfLink();
  echo $routes->getTable($thisURL)->getTableWithHeader("Routes");
}

function plotRoutes($routeIdsStr) {
    global $site, $date, $statsDB;

    $routesByName = array();

    $statsDB->query("SELECT id, name FROM enm_route_names WHERE id IN ($routeIdsStr)");
    while ( $row = $statsDB->getNextRow() ) {
      $routesByName[$row[1]] = $row[0];
    }

    $routes = new Routes($statsDB, $site, $date, null);

    foreach ( $routesByName as $name => $id ) {
        echo "<H3 id='$id'>$name</H3>\n";
        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow($routes->getGraphsForRouteId($id));
        echo $graphTable->toHTML();
    }
}

