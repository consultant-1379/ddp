<?php

require_once 'HTML/Table.php';

/**
 * Takes the array of graphs and plots two to a row.
 *
 * @author Patrick O Connor
 *
 * @param Array $graphs This is the array of graphs
 * @param int $graphsPerRow Number of graphs on each row, defaults to 2
 *
 */
function plotGraphs( $graphs, $graphsPerRow = 2 ) {
    $graphTable = new HTML_Table("border=0");
    $row = array();
    foreach ( $graphs as $graph ) {
        $row[] = $graph;
        if ( count($row) >= $graphsPerRow ) {
            $graphTable->addRow($row);
            $row = [];
        }
    }
    # Add last row if it's not empty
    if ( count($row) > 0 ) {
        $graphTable->addRow($row);
    }

    echo $graphTable->toHTML();
    echo addLineBreak();
}

/**
 * Gets a specified graph group from graphSet and adds them to an array
 *
 * @author Patrick O Connor
 *
 * @param String $set This is the set/group to be plotted
 * @param Array &$graphs This is a reference to the array of graphs
 * @param String $file This is the graphSet file to be used
 * @param Array $params This is the array of key/value params, Default null
 * @param Int $w This is the width of the graph, default 640
 * @param Int $h This is the height of the graph, default 320
 * @param String $from The start time of the graph, Default null
 * @param String $to The end time of the graph, Default null
 *
 */
function getGraphsFromSet( $set, &$graphs, $file, $params=null, $w=640, $h=320, $from=null, $to=null ) { // NOSONAR
    $mgs = new ModelledGraphSet( $file );
    $groupGraphs = $mgs->getGroup( $set );
    if ( $params == null ) {
        $params = array();
    }
    foreach ( $groupGraphs['graphs'] as $modelledGraph ) {
        $graphs[] = $modelledGraph->getImage($params, $from, $to, $w, $h);
    }
}

function drawGraphGroupByInstance($group, $instances) {
    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($instances, null, 'th' );
    foreach ( $group['graphs'] as $modelledGraph ) {
        $row = array();
        foreach ( $instances as $inst ) {
            $params = array( "inst" => $inst );
            $row[] = array( $modelledGraph->getImage($params, null, null, 640, 240) );
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}
