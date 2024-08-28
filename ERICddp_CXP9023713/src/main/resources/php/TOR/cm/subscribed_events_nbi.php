<?php

$pageTitle = "CM Subscribed Events NBI";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function subscriptionsGraphs() {
    $graphs = array();
    drawHeader( "Subscription Handling", 1, 'subscriptionHandling' );
    getGraphsFromSet( 'Subscription', $graphs, 'TOR/cm/subscription_nbi', null, 640, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'Event Handling', 1, 'eventHandling' );
    getGraphsFromSet( 'EventHandling', $graphs, 'TOR/cm/subscription_nbi', null, 640, 320 );
    plotGraphs($graphs);

    $graphs = array();
    drawHeader( 'Heartbeat Handling', 1, 'heartbeatHandling' );
    getGraphsFromSet( 'Heartbeat', $graphs, 'TOR/cm/subscription_nbi', null, 640, 320 );
    plotGraphs($graphs);
}

function subscriptionTables() {
    drawHeader('Subscription Handling Table', 1, "subscriptionHandling");
    $table = new ModelledTable('TOR/cm/subscription', 'subscriptionHandling');
    echo $table->getTable();
    echo addLineBreak();

    drawHeader( 'Event Handling  Table', 1, 'eventHandling' );
    $table = new ModelledTable( 'TOR/cm/subscribed_events_handling', 'eventHandling' );
    echo $table->getTable();
    echo addLineBreak();

    drawHeader( 'Heartbeat Handling Table', 1, 'heartbeatHandling' );
    $table = new ModelledTable( 'TOR/cm/subscribed_heartbeat', 'heartbeatHandling' );
    echo $table->getTable();
    echo addLineBreak();
}

function eventsNbi() {
   drawHeader('Subscriptions', 1, "subscriptions");
   $table = new ModelledTable('TOR/cm/subscribed_events', 'subscriptions');
   echo $table->getTable();
   echo addLineBreak();

   drawHeader('Subscriptions created/deleted today', 1, "created_deleted");
   $table = new ModelledTable('TOR/cm/subscribed_events_nbi', 'created_deleted');
   echo $table->getTable();
   echo addLineBreak();
}

function mainFlow() {
    subscriptionTables();
    subscriptionsGraphs();
    eventsNbi();
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

