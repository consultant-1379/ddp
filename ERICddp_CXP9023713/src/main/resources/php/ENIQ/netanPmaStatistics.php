<?php

$pageTitle = 'NetAN PMA Details';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function main() {
    drawHeader( 'PM Alarming Statistics', 1, 'pmAlarmingHelp');
    drawHeader( 'Alarm Metrics', 2, 'AlarmMetricsHelp');
    $modelledGraph = new ModelledGraph('ENIQ/netanAlarmRulesPerAlarmState');
    plotgraphs( array( $modelledGraph->getImage() ) );
    echo addLineBreak();
    $modelledGraph = new ModelledGraph('ENIQ/netanAlarmRulesPerAlarmType');
    plotgraphs( array( $modelledGraph->getImage() ) );
    echo addLineBreak();
    drawHeader( 'Alarm Rule Summary', 3, 'AlarmRuleHelp' );
    $table = new ModelledTable('ENIQ/netanAlarmRuleSummary', 'netanAlarmRuleSummary');
    echo $table->getTable();
    echo addLineBreak();
    $modelledGraph = new ModelledGraph('ENIQ/netanNumberOfAlarmSentToENM');
    plotgraphs( array( $modelledGraph->getImage() ) );
    echo addLineBreak();
    drawHeader( 'Alarm Rule Execution Time', 3, 'AlarmExecutionTimeHelp' );
    $table = new ModelledTable('ENIQ/netanAlarmRuleExecutionTime', 'netanAlarmRuleExecutionTime');
    echo $table->getTable();
    echo addLineBreak();
    drawHeader( 'Most Frequently Queried MOs', 2, 'netanFrequencyMOHelp' );
    $table = new ModelledTable('ENIQ/netanFrequencyMO', 'netanFrequencyMO');
    echo $table->getTable();
    echo addLineBreak();
    drawHeader( 'Custom KPI Count', 2, 'CustomKPIHelp' );
    $modelledGraph = new ModelledGraph('ENIQ/netanCustomKPICount');
    plotgraphs( array( $modelledGraph->getImage() ) );
}

main();
include_once PHP_ROOT . "/common/finalise.php";
