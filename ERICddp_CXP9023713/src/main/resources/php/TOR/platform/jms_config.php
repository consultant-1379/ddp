<?php
$pageTitle = "JMS Config";

$YUI_DATATABLE = true;

include "../../common/init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

/*Structure of JSON file
 {
     clients: {
         <client name>: [ { ctime: <connection creation time>, port: <source port number>, id: <ConnectionID>}, ... ]
     },
     destinations: {
         <type (topic/queue)>: {
             <name>: {
                 consumers: [
                     {
                         client: <client name>,
                         count: <num subscriptions>,
                         consumers: [
                             {
                                 ctime: <creation time>,
                                 connectionid: <Connectionid>,
                                 sessionid: <sessionid>
                             }
                         ]
                      }
                 ],
                 producers: [
                     {
                          client: <client name>,
                          producers: [
                              {
                                  msgsent:,
                                  connectionid:,
                                      sessionid:
                              }
                          ]
                      }
                 ]
        }
 }*/

$dataFile = $rootdir . "/jms/config.json";
if ( $debug ) { echo "<pre>dataFile=$dataFile</pre>\n"; }

function showDest($config,$type,$destinationName) {
    global $webargs,$debug;

    drawHeaderWithHelp("$destinationName", 1, "$destinationName", "DDP_Bubble_440_JMS_Config_Consumers_Producers");

    $destinationInfo = $config['destinations'][$type][$destinationName];
    if ( $debug ) { echo "<pre>showDest: destinationInfo\n"; print_r($destinationInfo); echo "</pre>\n"; }

    echo "<H2>Consumers</H2>\n";
    $showClientLink = $_SERVER['PHP_SELF'] . "?" . $webargs . "&action=showclient&client=";
    $showConsumersLink = $_SERVER['PHP_SELF'] . "?" . $webargs . "&action=showconsumers&type=$type&destination=$destinationName&index=";
    $consumerDataRows = array();
    $index = 0;
    foreach ( $destinationInfo['consumers'] as $consumer ) {
        $row = array( 'client' => sprintf('<a href="%s%s">%s</a>',$showClientLink,$consumer['client'],$consumer['client']),
                      'count' => sprintf('<a href="%s%s">%s</a>',$showConsumersLink,$index,$consumer['count']),
        );
        foreach ( array ( 'durable', 'filter' ) as $key ) {
            $row[$key] = $consumer[$key];
        }
        $consumerDataRows[] = $row;
        $index++;
    }
#Replacing "-" with "" as there is a limitation during json encoding
   $destinationName = str_replace("-","",$destinationName);
   $table = new DDPTable($destinationName . "_consumers",
                          array(
                              array('key' => 'client', 'label' => 'Client'),
                              array('key' => 'durable', 'label' => 'Durable'),
                              array('key' => 'filter', 'label' => 'Filter'),
                              array('key' => 'count', 'label' => 'Count')
                          ),
                          array('data' => $consumerDataRows)
    );
    echo $table->getTable();

    echo "<H2>Producers</H2>\n";
    $index = 0;
    $showProducersLink = $_SERVER['PHP_SELF'] . "?" . $webargs . "&action=showproducers&type=$type&destination=$destinationName&index=";
    $producerDataRows = array();
    foreach ( $destinationInfo['producers'] as $producer ) {
        $row = array( 'client' => sprintf('<a href="%s%s">%s</a>',$showClientLink,$producer['client'],$producer['client']),
                      'count' => sprintf('<a href="%s%s">%s</a>',$showProducersLink,$index,count($producer['producers'])),
        );
        $producerDataRows[] = $row;
        $index++;
    }
    $table = new DDPTable($destinationName . "_producers",
                          array(
                              array('key' => 'client', 'label' => 'Client'),
                              array('key' => 'count', 'label' => 'Count')
                          ),
                          array('data' => $producerDataRows)
    );
    echo $table->getTable();
}

function showConsumers($config,$type,$destinationName,$index) {
    global $debug;

    $consumerGrp = $config['destinations'][$type][$destinationName]['consumers'][$index];

    drawHeaderWithHelp("JMS Config Details", 2, "jmsConfigHelp", "DDP_Bubble_471_ENM_JMS");

    if ( $debug ) { echo "<pre>showConsumers: consumerGrp\n"; print_r($consumerGrp); echo "</pre>\n"; }

    $table = new DDPTable("client_list",
                          array(
                              array('key' => 'sessionid', 'label' => 'Session ID'),
                              array('key' => 'connectionid', 'label' => 'Connection ID'),
                              array('key' => 'ctime', 'label' => 'Creation Time' )
                          ),
                          array('data' => $consumerGrp['consumers'] )
    );
    echo $table->getTable();
}

function showProducers($config,$type,$destinationName,$index) {
    global $debug;

    $producerGrp = $config['destinations'][$type][$destinationName]['producers'][$index];
    if ( $debug ) { echo "<pre>showProducers: consumerGrp\n"; print_r($consumerGrp); echo "</pre>\n"; }

    echo "<H1>Producers for $destinationName in " . $producerGrp['client'] . "</H2>\n";
    $table = new DDPTable("producer_list",
                          array(
                              array('key' => 'sessionid', 'label' => 'Session ID'),
                              array('key' => 'connectionid', 'label' => 'Connection ID'),
                              array('key' => 'msgsent', 'label' => 'Messages Sent' )
                          ),
                          array('data' => $producerGrp['producers'] )
    );
    echo $table->getTable();
}

function showClient($config,$client) {
    echo "<H2>Connections for $client</H2>\n";

    $connectionDisplayRows = array();
    foreach ( $config['clients'][$client] as $connection ) {
        $connectionReferences = array();
        foreach ( $config['destinations'] as $type => $destinations ) {
            foreach ( $destinations as $destinationName => $destinationInfo ) {
                foreach ( $destinationInfo['consumers'] as $clientInfo ) {
                    if ( $clientInfo['client'] == $client ) {
                        foreach ( $clientInfo['consumers'] as $consumer ) {
                            if ( $consumer['connectionid'] == $connection['id'] ) {
                                $connectionReferences[] = "Cons " . $destinationName;
                            }
                        }
                    }
                }

                foreach ( $destinationInfo['producers'] as $producerInfo ) {
                    if ( $producerInfo['client'] == $client ) {
                        foreach ( $producerInfo['producers'] as $producer ) {
                            if ( isset($producerInfo['connectionid']) && $producerInfo['connectionid'] == $connection['id'] ) {
                                $connectionReferences[] = "Pro " . $destinationName;
                            }
                        }
                    }
                }
            }
        }
        $connectionDisplayRows[] = array( 'port' => $connection['port'],
                                          'ctime' => $connection['ctime'],
                                          'id' => $connection['id'],
                                          'usedby' => implode(",", $connectionReferences)
        );
    }
    $table = new DDPTable("client_list",
                          array(
                              array('key' => 'port', 'label' => 'Port'),
                              array('key' => 'ctime', 'label' => 'Creation Time'),
                              array('key' => 'id', 'label' => 'Connection ID' ),
                              array('key' => 'usedby', 'label' => 'Used By' )
                          ),
                          array('data' => $connectionDisplayRows)
    );
    echo $table->getTable();
}

if ( file_exists($dataFile) ) {
    $config = json_decode(file_get_contents($dataFile),TRUE);
    if ( $debug > 1 ) { echo "<pre>config:\n"; print_r($config); echo "</pre>\n"; }

    if ( isset($_GET['action']) ) {
        $action = $_GET['action'];
        if ( $action == 'showdest' ) {
            showDest($config,$_GET['type'],$_GET['destination']);
        } else if ( $action == 'showclient' ) {
            showClient($config,$_GET['client']);
        } else if ( $action == 'showconsumers' ) {
            showConsumers($config,$_GET['type'],$_GET['destination'],$_GET['index']);
        } else if ( $action == 'showproducers' ) {
            showProducers($config,$_GET['type'],$_GET['destination'],$_GET['index']);
        }
    } else {
        foreach ( $config['destinations'] as $type => $destinations ) {
            drawHeaderWithHelp("$type", 2, "$type", "DDP_Bubble_441_JMS_Config_Topic_queue_details");
            $summaryData = array();
            $baseLink = $_SERVER['PHP_SELF'] . "?" . $webargs . "&action=showdest&type=$type&destination=";

            foreach ( $destinations as $destinationName => $destinationInfo ) {
                $consumerCount = 0;
                foreach ( $destinationInfo['consumers'] as $consumer ) {
                    $consumerCount = $consumerCount + $consumer['count'];
                }
                $destinationLink = sprintf('<a href="%s%s">%s</a>',$baseLink,$destinationName,$destinationName);

                $producerCount = 0;
                foreach ( $destinationInfo['producers'] as $producerClient ) {
                    $producerCount = $producerCount + count($producerClient['producers']);
                }

                $summaryData[] = array( 'destination' => $destinationLink,
                                        'consumercount' => $consumerCount,
                                        'producercount' => $producerCount);

            }

            $table = new DDPTable($type . "_summary",
                                  array(
                                      array('key' => 'destination', 'label' => 'Destination'),
                                      array('key' => 'consumercount', 'label' => '#Consumers'),
                                      array('key' => 'producercount', 'label' => '#Producers')
                                  ),
                                  array('data' => $summaryData)
            );
            echo $table->getTable();
        }

        drawHeaderWithHelp("Client Connections", 1, "Client Connections", "DDP_Bubble_442_JMS_Config_Client_Connection_details");

        $countByClient = array();
        $baseLink = $_SERVER['PHP_SELF'] . "?" . $webargs . "&action=showclient&client=";
        foreach ( $config['clients'] as $client => $connections ) {
            $clientLink = sprintf('<a href="%s%s">%s</a>',$baseLink,$client,$client);
            $countByClient[] = array( 'client' => $clientLink, 'connectioncount' => count($connections) );
        }
        $table = new DDPTable('client_summary',
                              array(
                                  array('key' => 'client', 'label' => 'Client'),
                                  array('key' => 'connectioncount', 'label' => '#Connections')
                                  ),
                              array('data' => $countByClient)
        );
        echo $table->getTable();

    }
}

include PHP_ROOT . "/common/finalise.php";
?>
