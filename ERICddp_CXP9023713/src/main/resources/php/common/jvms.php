<?php
$pageTitle = "JVMs";

$DISABLE_UI_PARAMS = array('plot');

require_once "./init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

const NAMEID_PARAM = 'nameid';

function hasJBoss( $tbl, $serverIdsStr ) {
    global $statsDB;

    $extraWhere = "$tbl.serverid IN ( $serverIdsStr )";
    return $statsDB->hasData( $tbl, 'time', false, $extraWhere );
}

function plot($nameId) {
    global $statsDB, $site, $date, $oss, $debug;

    $name = $statsDB->queryRow("SELECT name FROM jmx_names WHERE id = $nameId")[0];

    $statsDB->query("
SELECT
 DISTINCT servers.id AS id, servers.hostname AS server
FROM
 sum_generic_jmx_stats, sites, servers
WHERE
 sum_generic_jmx_stats.siteid = sites.id AND sites.name = '$site' AND
 sum_generic_jmx_stats.date =  '$date' AND
 sum_generic_jmx_stats.serverid = servers.id AND
 sum_generic_jmx_stats.nameid = $nameId
ORDER BY servers.hostname
");
    $names = array();
    $serverIds = array();
    while ( $row = $statsDB->getNextRow() ) {
        $serverIds[] = $row[0];
        $names[] = $row[1] . "," . $name;
    }

    $url  = makeURL("/genjmx.php", array( 'names' => implode(";", $names)));
    if ( $oss === 'tor' ) {
        $serverIdsStr = implode(",", $serverIds);

        // Is there a JBoss server deployed on this server
        $hasJBoss = hasJBoss( 'enm_jboss_threadpools', $serverIdsStr ) ||
                    hasJBoss( 'enm_jboss_threadpools_nonstandard', $serverIdsStr );

        // Do we have one and only one service on these hosts
        $statsDB->query("
SELECT DISTINCT enm_servicegroup_names.name
FROM enm_servicegroup_instances
JOIN sites ON enm_servicegroup_instances.siteid = sites.id
JOIN enm_servicegroup_names ON enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
WHERE
 sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serverid IN ( $serverIdsStr )");
        $serviceGroupNames = array();
        while ( $row = $statsDB->getNextRow() ) {
            $serviceGroupNames[] = $row[0];
        }

        // Is there one and only one JVM on these hosts
        $onlyOneJvmName =  $statsDB->queryRow("
SELECT COUNT(DISTINCT nameid)
FROM sum_generic_jmx_stats
JOIN sites ON sum_generic_jmx_stats.siteid = sites.id
WHERE
 sites.name = '$site' AND
 sum_generic_jmx_stats.date = '$date' AND
 sum_generic_jmx_stats.serverid IN ( $serverIdsStr )")[0] == 1;

        if ( $hasJBoss && count($serviceGroupNames) == 1 && $onlyOneJvmName ) {
            $url = makeURL("/TOR/jboss.php", array( 'servicegroup' => $serviceGroupNames[0]));
        }
    }

    header("Location: $url");
}

function main() {
    $jvmTable = new ModelledTable(
        'common/sum_jvms',
        'jvms',
        array(ModelledTable::URL => makeSelfLink())
    );
    if (! $jvmTable->hasRows()) {
        $jvmTable = new ModelledTable(
            'common/jvms',
            'jvms',
            array(ModelledTable::URL => makeSelfLink())
        );
    }
    echo $jvmTable->getTableWithHeader("JVM Stats");
}

$nameId = requestValue('selected');
$statsDB = new StatsDB();

if ( is_null($nameId) ) {
    main();
} else {
    plot($nameId);
}

require_once PHP_ROOT . "/common/finalise.php";
