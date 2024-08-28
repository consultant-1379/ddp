<?php

$pageTitle = 'EO Jboss Connection Pool';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function plotByInstance( $group, $instances, $selected ) {
    global $statsDB, $site;

    $out = array();
    $instCount = 0;

    foreach ( $instances as $inst ) {
        $out[] = "<div style=\"text-align: center; font-weight: bold;\">$inst</div>";
        $instCount++;
    }

    foreach ( $group['graphs'] as $graph ) {
        foreach ( $instances as $inst ) {
            $serverid = getServerId($statsDB, $site, $inst );
            $params = array( 'poolid' => $selected, 'serverid' => $serverid);
            $out[] = $graph->getImage($params, null, null, 640, 240);
        }
    }
    plotGraphs($out, $instCount);
}

function poolGraph($selected) {
    global $statsDB, $date, $site;
    $instances = getInstances(
        'eo_jboss_connection_pool',
        'time',
        "AND eo_jboss_connection_pool.poolid = '$selected'"
    );

    $poolname = $statsDB->queryNamedRow("
SELECT
    eo_jboss_connection_pool_names.name
FROM
    eo_jboss_connection_pool
JOIN sites
    ON eo_jboss_connection_pool.siteid = sites.id
JOIN eo_jboss_connection_pool_names
    ON eo_jboss_connection_pool.poolid = eo_jboss_connection_pool_names.id
WHERE
    sites.name = '$site' AND eo_jboss_connection_pool.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    AND eo_jboss_connection_pool.poolid = '$selected'
GROUP BY eo_jboss_connection_pool_names.name");

    drawHeader($poolname['name'], 1, 'jbossConnection');
    $jbossGraphSet = new ModelledGraphSet('EO/jboss_connection_graphs');
    plotByInstance( $jbossGraphSet->getGroup("pool"), $instances, $selected );
}

function main() {
    $table = new ModelledTable(
        'EO/jboss_connection_pool',
        'jbossConnection',
        array(
            ModelledTable::URL => makeSelfLink(),
        )
    );
    echo $table->getTableWithHeader('Jboss Connection Pool');
}

$selected = requestValue('selected');
if ( is_null($selected) ) {
    main();
} else {
    poolGraph($selected);
}

require_once PHP_ROOT . "/common/finalise.php";
