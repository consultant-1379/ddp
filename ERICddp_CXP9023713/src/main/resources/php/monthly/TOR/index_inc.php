<?php

echo "<H1>Statistics</H1>\n";

$args = preg_split("/&/", $args);
$argList = array();

foreach ( $args as $arg ) {
    $variableValue = preg_split("/=/", $arg );
    $argList[$variableValue[0]] = $variableValue[1];
}

// Link to 'Log Analysis' page
$linkList = array( makeLink("/monthly/TOR/log_analysis.php", "Log Analysis", $argList) );

// Link to either 'VCS Analysis' page [OR] 'Consul/SAM Analysis' page
$row = $statsDB->queryRow("
SELECT 1 FROM
    enm_vcs_events,
    sites
WHERE
    enm_vcs_events.siteid = sites.id AND
    sites.name = '$site' AND
    enm_vcs_events.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
LIMIT 1");

if ( ! empty($row) ) {
    array_push( $linkList, makeLink("/monthly/TOR/vcs_events.php", "VCS Analysis", $argList) );
} else {
    $row = $statsDB->queryRow("
SELECT 1 FROM
    enm_consul_n_sam_events,
    sites
WHERE
    enm_consul_n_sam_events.siteid = sites.id AND
    ( enm_consul_n_sam_events.event_type = 'Consul' OR enm_consul_n_sam_events.event_type = 'SAM' ) AND
    sites.name = '$site' AND
    enm_consul_n_sam_events.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
LIMIT 1");

    if ( ! empty($row) ) {
        array_push( $linkList, makeLink("/monthly/TOR/consul_n_sam_events.php", "Consul/SAM Analysis", $argList) );
    }
}

// Link to 'Queue Topic Analysis' page
$argList['getTopLimit'] = 10;
array_push( $linkList, makeLink("/monthly/TOR/queue_topic_analysis.php", "Queue and Topic Analysis", $argList) );

echo makeHTMLList( $linkList );
