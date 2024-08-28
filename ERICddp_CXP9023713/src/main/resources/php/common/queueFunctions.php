<?php

require_once PHP_ROOT . "/classes/Jms.php";

/**
 * Takes an array of queue names and plots a graph for each queue
 *
 * @author Patrick O Connor
 *
 * @param Array $names This is an array of queue names
 *
 * @param Boolean $complexData This should be set to true if $names is
 * more complicated than a simple key => value array
 * example in fmsnmp_engine_stats.php
 *
 */
function plotQueues( $names, $complexData = false ) {
    global $statsDB, $date, $site;

    drawHeaderWithHelp('Queues', 1, 'Queues');

    $jmsGraphTableRows = array( array(), array() );

    foreach ( $names as $q ) {
        $jms = new Jms($statsDB, $site, "queue", $q, $date, $date);
        $rowIndex = 0;
        foreach ( $jms->getMessageGraphs() as $graph ) {
            $jmsGraphTableRows[$rowIndex][] = $graph;
            $rowIndex++;
        }
    }

    $jmsGraphTable = new HTML_Table('border=1');

    if ( $complexData ) {
        $names = array_keys($names);
    }
    $jmsGraphTable->addRow( $names, null, 'th' );
    foreach ( $jmsGraphTableRows as $row ) {
        $jmsGraphTable->addRow($row);
    }

    echo $jmsGraphTable->toHTML();
}
