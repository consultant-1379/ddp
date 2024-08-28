<?php

require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

class Kafka {
    public $site;
    public $date;
    public $servIdList;
    const KAFKA_CONSUMER = "kafka_consumer";
    const KAFKA_PRODUCER = "kafka_producer";
    const KAFKA_PRODUCER_CLIENTS = "kafka_producer_clients";
    const KAFKA_CONSUMER_SERVICEBASED = "kafka_consumer_servicebased";
    const KAFKA_PRODUCER_SERVICEBASED = "kafka_producer_servicebased";
    const KAFKA_PRODUCER_CLIENT_SERVICEBASED = "kafka_producer_clients_servicebased";

    public function __construct($statsDB, $site, $date, $servIdList) {
        $this->statsdb = $statsDB;
        $this->site = $site;
        $this->date = $date;
        $this->servIdList = $servIdList;
    }

    public function consumerSerParams() {
        return array(
            'records_consumed_servicebased',
            'fetch_size_servicebased'
        );
    }

    public function producerSerParams() {
        return array(
            'records_send_servicebased'
        );
    }

    public function consumerParams() {
        return array(
            'records_consumed',
            'fetch_size'
        );
    }

    public function producerParams() {
        return array(
            'records_send'
        );
    }

    public function producerClientParam() {
        return array(
            'errorTotal',
            'lantancyMax'
        );
    }

    public function producerSerClientParams() {
        return array(
            'errorTotal_servicebased',
            'lantancyMax_servicebased'
        );
    }

    public function getGraphsForKafka($ids, $table) {

        $param = "topicid";
        if ( ! is_null($this->servIdList) ) {
            $servIdStr = implode(",", $this->servIdList);
            if ( $table == self::KAFKA_CONSUMER ) {
                $graphParams = $this->consumerSerParams();
            } elseif ( $table == self::KAFKA_PRODUCER ) {
                $graphParams = $this->producerSerParams();
            } elseif ( $table == self::KAFKA_PRODUCER_CLIENTS ) {
                $graphParams = $this->producerSerClientParams();
                $param = 'clientid';
            }
            $params = array( 'serverids' => $servIdStr, $param => $ids );
        } else {
            if ( $table == self::KAFKA_CONSUMER ) {
                $graphParams = $this->consumerParams();
            } elseif ( $table == self::KAFKA_PRODUCER ) {
                $graphParams = $this->producerParams();
            } elseif ( $table == self::KAFKA_PRODUCER_CLIENTS ) {
                $graphParams =  $this->producerClientParam();
                $param = 'clientid';
            }
            $params = array( $param => $ids );
       }

        $graphs = array();
        foreach ( $graphParams as $graph ) {
            $modelledGraph = new ModelledGraph("ECSON/kafka/$graph");
            $graphs[] = $modelledGraph->getImage($params);
        }
        return $graphs;
    }

    public function getTable($file, $id) {

        if ( ! is_null($this->servIdList) ) {
            $servIdStr = implode(",", $this->servIdList);
            $callbackURL = makeSelfLink()."&serverids=$servIdStr";
            $passParams = array('serverids' => $servIdStr, ModelledTable::URL => $callbackURL);
        } else {
            $callbackURL = makeSelfLink();
            $passParams =  array( ModelledTable::URL => $callbackURL );
        }

        return new ModelledTable(
            "ECSON/kafka/$file",
            "$id",
            $passParams
        );
    }

}

