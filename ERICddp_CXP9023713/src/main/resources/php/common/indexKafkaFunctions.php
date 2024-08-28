<?php

const TOPIC = 'topic';

// Returns Kafka Topics
function kafkaTopics($serverIdToApp) {
    global $site, $date, $statsDB;

    // Group topic by app
    $statsDB->query("
    SELECT
     k8s_pod.serverid, k8s_pod_app_names.name
    FROM k8s_pod
     JOIN sites ON k8s_pod.siteid = sites.id
     JOIN k8s_pod_app_names ON k8s_pod.appid = k8s_pod_app_names.id
    WHERE
     sites.name = '$site' AND
     k8s_pod.date = '$date'
    ");
    while ($row = $statsDB->getNextRow()) {
        $serverIdToApp[$row[0]] = $row[1];
    }
    $appToTopics = array();
    $statsDB->query("
    SELECT
     DISTINCT kafka_topic_names.name AS topic, enm_kafka_topic.serverid AS serverid
    FROM enm_kafka_topic
     JOIN sites ON enm_kafka_topic.siteid = sites.id
     JOIN kafka_topic_names ON enm_kafka_topic.topicid = kafka_topic_names.id
    WHERE
     enm_kafka_topic.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
     sites.name = '$site'
    ");
    while ($row = $statsDB->getNextNamedRow()) {
        if ( array_key_exists($row['serverid'], $serverIdToApp)) {
            $app = $serverIdToApp[$row['serverid']];
            if ( ! array_key_exists($app, $appToTopics)) {
                $appToTopics[$app] = array();
            }
            $topicStartWith = substr( $row[TOPIC], 0, 1);
            if ( ($topicStartWith != "_") && (! in_array($row[TOPIC], $appToTopics[$app])) ) {
                $appToTopics[$app][] = $row[TOPIC];
            }
        }
    }

    return $appToTopics;
}
