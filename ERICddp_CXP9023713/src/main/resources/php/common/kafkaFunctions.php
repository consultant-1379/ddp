<?php

/**
 *Plots Kafka Consumer/Producer Graphs
 *
 *@param string $topicIdsStr Selected topics ids from table
 *@param string $table  The name of the table
 *@param string $serverIds ServicegroupIds of specific requested page
 *
**/
function plotKafka($idsStr, $table, $serverIds, $action) {
    global $site, $date, $statsDB;

     if ( $action != "" ) {
        $lookup = $action;
     } else {
         $lookup = "kafka_topic_names";
     }

    $topicClientByName = array();
    $statsDB->query("SELECT id, name FROM $lookup WHERE id IN ($idsStr)");
    while ( $row = $statsDB->getNextRow() ) {
      $topicClientByName[$row[1]] = $row[0];
    }
    $kafka = new Kafka($statsDB, $site, $date, $serverIds);
    foreach ( $topicClientByName as $name => $id ) {
        drawHeader($name, 3, '');
        plotgraphs( $kafka->getGraphsForKafka($id, $table) );
    }
}

/**
 *Returns Kafka Consumer/ ProducerLink
 *
 *@param string $serverIdsArr id's of requested ServiceGroup page
 *@param string $sg The Service Groupnames of the requested page
 *
**/
function kafkaLinks( $serverIdsArr, $sg ) {
    global $site, $date, $statsDB;

    $kafkaServerIds = implode(',', $serverIdsArr);
    foreach ( array( "kafka_consumer", "kafka_producer" ) as $table ) {
        $statsDB->query("
SELECT
 count(*)
FROM
 $table, sites, servers
WHERE
 $table.siteid = sites.id AND sites.name = '$site' AND
 $table.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 $table.serverid = servers.id AND
 $table.serverid IN ( $kafkaServerIds ) ");

        $row = $statsDB->getNextRow();
        if ( $row[0] > 0 ) {
            return makeLink(
                "/ECSON/kafka.php",
                "Kafka Consumer/Producer",
                array('serverids' => $kafkaServerIds, 'serviceName' => implode(",", $sg))
            );
        }
    }
}

