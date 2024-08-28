<?php
$pageTitle = "ENM HA Log Analysis";

include_once "../../common/init.php";
include_once "../../common/ha_log_analysis_functions.php";

require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const CONSULEVNT = 'consulEvents';
const CONSULTITLE = 'Consul Events';
const MUIPATH = 'TOR/platform/';

function showLinks() {
    global $site, $date, $oss;
    $links = array();
    $consulSubLinks = array();
    $links[] = makeAnchorLink('SAMHC', 'SAM Agent Failures');
    $links[] = makeAnchorLink('SamServerFailureReport', 'SAM Server Failure Report');
    $links[] = makeAnchorLink('workflowExecution', 'Workflow Executions');

    $consulTitle = makeAnchorLink(CONSULEVNT, CONSULTITLE);
    $consulSubLinks[] = makeAnchorLink(CONSULEVNT, CONSULTITLE);
    $startDate = date( 'Y-m-d', strtotime($date . '-31 days') );
    $consulSubLinks[] = makeLink(
        "/monthly/TOR/consul_n_sam_events.php?site=$site&start=$startDate&end=$date&oss=$oss&eventName=Consul&",
        "Last 31 Day Summary"
    );

    $consulSubLinks[] = makeAnchorLink("enm_server_failure_summary", "Consul Events Summary");
    $links[] = $consulTitle.makeHTMLList($consulSubLinks);

    echo makeHTMLList($links);
}

function healthCheckStatus() {
    drawHeader("SAM Agent Failures per Instance", 2, "SAMHC");
    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( MUIPATH."enm_health_check_failures", "enm_health_check_failures", $selfLink );
    echo $table->getTable();
    echo addLineBreak();
    $action = requestValue('action');
    if ($action === 'healthCheckFailures') {
        $serverId=requestValue('selected');
        $arrayInst = array(serverId=>$serverId);
        drawHeader("SAM Agent Failures Summary", 2, "SAMHC_Summary");
        $table = new ModelledTable(
            MUIPATH."enm_health_check_failure_summary",
            "enm_health_check_failure_summary",
            $arrayInst
        );
        echo $table->getTable();
        echo addLineBreak();
    }
}

function workflowExecution() {
    drawHeader("Workflow Executions", 2, "workflowExecution");
    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( MUIPATH."enm_workflowexecutions", "enm_workflowExecution", $selfLink );
    echo $table->getTable();
    echo addLineBreak();
    $logDetails = requestValue('action');
    if ($logDetails === 'instanceIdStartTime') {
        $instanceId=requestValue('selected');
        showWfLog($instanceId);
    }
}

function samFailureReport() {
    drawHeader("SAM Server Failure Report", 2, "SamServerFailureReport");
    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( MUIPATH."enm_sam_server_failure_report", "enm_sam_server_failure_report", $selfLink );
    echo $table->getTable();
    echo addLineBreak();
}

function consulEvents() {
    // Display 'Consul Events' table
    drawHeader(CONSULTITLE, 2, CONSULEVNT);
    $table = new ModelledTable( MUIPATH."enm_consul", "enm_consul" );
    echo $table->getTable();
    echo addLineBreak();
    $table = new ModelledTable( MUIPATH."enm_server_failure_summary_count", "enm_server_failure_summary" );
    echo $table->getTableWithHeader("Consul Events Summary");
    echo addLineBreak();
}

function mainFlow() {
    showLinks();
    healthCheckStatus();
    samFailureReport();
    workflowExecution();
    consulEvents();
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

