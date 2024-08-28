<?php

$pageTitle = "OpenDJ LDAP Stats";

include_once "./common/init.php";
require_once "./classes/ModelledGraphSet.php";
require_once "./common/graphFunctions.php";

function getPorts() {
    global $statsDB, $site, $date;
    $ports = array();

    $statsDB->query("
SELECT
    DISTINCT(opendj_ldap_stats.port) AS port
FROM
    opendj_ldap_stats,
    sites
WHERE
    opendj_ldap_stats.siteid = sites.id AND
    sites.name = '$site' AND
    opendj_ldap_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    ORDER BY port"
    );

    while ( $row = $statsDB->getNextRow() ) {
        $ports[] = $row[0];
    }

    return $ports;
}

function getServInfo( $type ) {
    global $statsDB, $site, $date;

    $srvInfo = enmGetServiceInstances($statsDB, $site, $date, $type);
    if ( !$srvInfo && $type == 'opendj' ) {
        $srvNames = getInstances('opendj_ldap_stats', 'time');
        foreach ( $srvNames as $name ) {
            $id = getServerId($statsDB, $site, $name);
            $srvInfo[$name] = $id;
        }
    }
    return $srvInfo;
}

function getTitle( $type ) {
    if ( $type == 'cts' ) {
        return "CTS";
    } else {
        return "OpenDJ";
    }
}

function setHeadings( &$headings, $srvInfo ) {
    foreach ( $srvInfo as $name => $id ) {
        $headings[] = "<H3>$name</H3>";
    }
}

function setHelpAndCols( &$help, &$sets ) {
    global $statsDB, $oss;

    if ( $statsDB->hasData('opendj_ldap_stats', 'time', false, 'bytes_read_total IS NOT NULL') ) {
        $sets = array('operComp', 'operResTime', 'MedResTime', 'avgBytes');
        $help = 'promLdap';
    } else {
        $sets = array('operComp', 'operResTime', 'MedResTime', 'operQueryRates', 'avgBytesOld');

        if ( $oss == "eo" ) {
            $help = 'eo_ldapsearch';
        } else {
            $help = 'ldapsearch';
        }
    }
}

function main() {
    $help = '';
    $sets = array();
    $headings = array();

    $type = requestValue('type');
    $ports = getPorts();
    $title = getTitle( $type );
    $srvInfo = getServInfo( $type );
    setHelpAndCols( $help, $sets );
    setHeadings( $headings, $srvInfo );
    $srvCnt = count($headings);

    drawHeader("$title LDAP Stats", 1, $help);

    foreach ($ports as $port) {
        drawHeader($port, 2, '');
        $graphs = array();
        foreach ($sets as $set) {
            foreach ( $srvInfo as $id ) {
                $params = array( 'serverId' => $id, 'port' => $port );
                getGraphsFromSet( $set, $graphs, 'common/open_dj_ldap', $params, 640, 320 );
            }
        }
        $result = array_merge($headings, $graphs);
        plotgraphs( $result, $srvCnt );
        echo addLineBreak(2);
    }
}

main();

include_once PHP_ROOT . "/common/finalise.php";

