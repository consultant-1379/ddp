<?php

const EVENT_NAME = 'eventName';

if ( $_REQUEST[ EVENT_NAME ] === 'ORAN' ) { //NOSONAR
    $pageTitle = "O-RAN Radio Unit Software Upgrade";
} elseif ( $_REQUEST[ EVENT_NAME ] === 'ASU' ) { //NOSONAR
    $pageTitle = "Auto Software Upgrade";
}

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function mainFlow( $eventName ) {
    if ( $eventName === 'ASU' ) {
        $phaseTable = 'asu_phasesummary';
        $overallTable = 'asu_overallsummary';
        $headerMsg = 'Auto Software Upgrade Flow';
        $groupTable = 'asu_group_summary';
    } elseif ( $eventName === 'ORAN' ) {
        $phaseTable = 'oran_phasesummary';
        $overallTable = 'oran_overallsummary';
        $headerMsg = 'O-RAN Radio Unit Software Upgrade Flow';
        $groupTable = '';
    } else {
        die("Invalid Event Name.");
    }
    flowAutomation($phaseTable, $overallTable, $headerMsg, $eventName, $groupTable);
}

function flowAutomation($phaseTable, $overallTable, $headerMsg, $eventName, $groupTable) {
    $selfLink = array( ModelledTable::URL => makeSelfLink() . "&eventName=$eventName" );

    $table = new ModelledTable( "TOR/platform/$overallTable", $overallTable, $selfLink );
    echo $table->getTableWithHeader("$headerMsg Overall Details");
    echo addLineBreak();

    $action = requestValue('Details');

    if ( $action === 'flowExecutionName' ) {
        $nameList = explode(',', requestValue('selected'));
        $tbls = array();
        getValidTables( $nameList, $tbls, '_phase', $phaseTable );
        if ( ! empty( $tbls ) ) {
            drawHeader("$headerMsg Phase Details", 2, $phaseTable);
            drawTables( $tbls );
        }
        $tbls = array();
        if ( $groupTable != '' ) {
            getValidTables( $nameList, $tbls, '_group', $groupTable );
            if ( ! empty( $tbls ) ) {
                echo addLineBreak();
                drawHeader("$headerMsg Group Details", 2, $groupTable);
                drawTables( $tbls );
            }
        }
    } else {
        $modelledGraph = new ModelledGraph("TOR/platform/$overallTable");
        plotgraphs( array( $modelledGraph->getImage() ) );
    }
}

function getValidTables( $nameList, &$tbls, $type, $file ) {
    foreach ($nameList as $flowExec) {
        $arrayInst = array('instanceName' => $flowExec);
        $tableInstanceName = str_replace('-', '_', $flowExec);
        $table = new ModelledTable( "TOR/platform/$file", "$tableInstanceName$type", $arrayInst );
        if ( $table->hasRows() ) {
            $tbls[] = $table;
        }
    }
}

function drawTables( $tbls ) {
    foreach ( $tbls as $table ) {
        echo $table->getTable();
        echo addLineBreak();
    }
}

if ( issetURLParam( EVENT_NAME ) ) {
    $eventName = requestValue( EVENT_NAME );
    mainFlow( $eventName );
} else {
    echo "Error eventName must be set.";
}

include_once PHP_ROOT . "/common/finalise.php";

