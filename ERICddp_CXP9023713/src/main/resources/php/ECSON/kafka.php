<?php

$pageTitle = "Kafka Consumer/Producer";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/Kafka.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";

function kafkaParam() {
    return array(
        array(Kafka::KAFKA_CONSUMER, 'kafkaConsumed', 'Kafka Consumer Topics'),
        array(Kafka::KAFKA_PRODUCER, 'kafkaProduced', 'Kafka Produced Topics'),
        array(Kafka::KAFKA_PRODUCER_CLIENTS, 'kafkaProducerClient', 'Kafka Producer Clients')
    );
}

function kafkaSerParam() {
    return array(
        array(Kafka::KAFKA_CONSUMER_SERVICEBASED, 'kafkaConsumed', 'Kafka Consumer Topics'),
        array(Kafka::KAFKA_PRODUCER_SERVICEBASED, 'kafkaProduced', 'Kafka Produced Topics'),
        array(Kafka::KAFKA_PRODUCER_CLIENT_SERVICEBASED, 'kafkaProducerClient', 'Kafka Producer Clients')
    );
}
function mainFlow($serverIds, $serviceName) {
    global $site, $date, $statsDB;

    $kafka = new Kafka($statsDB, $site, $date, $serverIds);
    if ( ! is_null($serviceName) ) {
        drawHeader("All tables contain data from the below service groups:", 2, "");
        $listServ = array();
        foreach ( $serviceName as $serName ) {
            $listServ[] = $serName;
        }
        echo makeHTMLList($listServ);
    }

    if ( is_null($serverIds) ) {
        $kafkaTopicClient = kafkaParam();
        foreach ( $kafkaTopicClient as $kafkaFile ) {
            $consumerTable = $kafka->getTable($kafkaFile[0], $kafkaFile[1]);
            if ( $consumerTable->hasRows() ) {
                echo $consumerTable->getTableWithHeader($kafkaFile[2]);
            }
        }
    } else {
        $kafkaSerTopicClient = kafkaSerParam();
        foreach ( $kafkaSerTopicClient as $kafkaSerFile ) {
            $producerTable = $kafka->getTable($kafkaSerFile[0], $kafkaSerFile[1]);
            if ( $producerTable->hasRows() ) {
                echo $producerTable->getTableWithHeader($kafkaSerFile[2]);
            }
        }
    }
}

$selected = requestValue('selected');
if ( issetUrlParam('serverids') ) {
    $serverIds = explode(",", requestValue('serverids'));
    $serviceName = explode(",", requestValue('serviceName'));
} else {
    $serverIds = null;
    $serviceName = null;
}

if ( is_null($selected) ) {
    mainFlow($serverIds, $serviceName);
} else {
    $action = requestValue('plot');
    if ( $action == 'kconsumer' ) {
        plotKafka( $selected, Kafka::KAFKA_CONSUMER, $serverIds, '' );
    } elseif ( $action == 'kproducer' ) {
        plotKafka( $selected, Kafka::KAFKA_PRODUCER, $serverIds, '' );
    } elseif ( $action == 'kclientproducer' ) {
        plotKafka( $selected, Kafka::KAFKA_PRODUCER_CLIENTS, $serverIds, 'kafka_client_names' );
    }
}

include_once PHP_ROOT . "/common/finalise.php";

