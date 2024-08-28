<?php

$pageTitle = "CLI Messages";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

const SELF_LINK = "/TOR/ncm/ncm_cliMessages.php";

function cliMessages() {

    $cliRecGraphs = array(
        'cli_received_exec',
        'cli_received_invoke',
        'cli_received_autoCommit',
        'cli_received_commit',
        'cli_received_abort'
    );
    $cliFailGraphs = array(
        'cli_failed_exec',
        'cli_failed_invoke',
        'cli_failed_autoCommit',
        'cli_failed_commit',
        'cli_failed_abort'
    );
    return array(
        array( 'Received CLI Messages', $cliRecGraphs, 'receivedCli' ),
        array( 'Failed CLI Messages', $cliFailGraphs, 'failedCli' ),
    );
}

function r6kMessagesParams() {

    $r6kRecGraphs = array(
        'router_received_exec',
        'router_received_invoke',
        'router_received_autoCommit',
        'router_received_commit',
        'router_received_abort'
    );
    $r6kFailGraphs = array(
        'router_failed_exec',
        'router_failed_invoke',
        'router_failed_autoCommit',
        'router_failed_commit',
        'router_failed_abort'
    );
    $r6kRecDurGraphs = array(
        'router_successDuration_autoCommit',
        'router_successDuration_commit',
        'router_successDuration_abort',
        'router_successDuration_exec',
        'router_successDuration_invoke'
    );
    $r6kFailDurGraphs = array(
        'router_failedDuration_autoCommit',
        'router_failedDuration_commit',
        'router_failedDuration_abort',
        'router_failedDuration_exec',
        'router_failedDuration_invoke'
    );
    return array(
        array( 'Received Router6000 Messages', $r6kRecGraphs, 'receivedRouter' ),
        array( 'Failed Router6000 Messages', $r6kFailGraphs, 'failedRouter' ),
        array( 'Duration Router6000 Received CLI Messages', $r6kRecDurGraphs, 'successDurRouter' ),
        array( 'Duration Router6000 Failed CLI Messages', $r6kFailDurGraphs, 'failedDurRouter' ),
    );
}

function miniLinkMessagesParams() {

    $miniLinkRecGraphs = array(
        'minilink_received_exec',
        'minilink_received_invoke',
        'minilink_received_autoCommit',
        'minilink_received_commit',
        'minilink_received_abort'
    );
    $miniLinkFailGraphs = array(
        'minilink_failed_exec',
        'minilink_failed_invoke',
        'minilink_failed_autoCommit',
        'minilink_failed_commit',
        'minilink_failed_abort'
    );
    $miniLinkRecDurGraphs = array(
        'minilink_successDuration_autoCommit',
        'minilink_successDuration_commit',
        'minilink_successDuration_abort',
        'minilink_successDuration_exec',
        'minilink_successDuration_invoke'
    );
    $miniLinkFailDurGraphs = array(
        'minilink_failedDuration_autoCommit',
        'minilink_failedDuration_commit',
        'minilink_failedDuration_abort',
        'minilink_failedDuration_exec',
        'minilink_failedDuration_invoke'
    );
    return array(
        array( 'Received MiniLink Messages', $miniLinkRecGraphs, 'receivedMiniLink' ),
        array( 'Failed MiniLink Messages', $miniLinkFailGraphs, 'failedMiniLink' ),
        array( 'Duration MiniLink Received CLI Messages', $miniLinkRecDurGraphs, 'receivedDurMiniLink' ),
        array( 'Duration Mini-Link Indoor Failed CLI Messages', $miniLinkFailDurGraphs, 'failedDurMiniLink' ),
    );
}

function routerMessages() {

    echo makeLink( SELF_LINK, "Return to CLI Messages page" );

    $links = array();
    $links[] = makeAnchorLink('receivedRouter', 'Received Router6000 CLI Messages');
    $links[] = makeAnchorLink('failedRouter', 'Failed Router6000 CLI Messages');
    $links[] = makeAnchorLink("successDurRouter", "Duration Router6000 Received CLI Messages");
    $links[] = makeAnchorLink("failedDurRouter", "Duration Router6000 Received CLI Messages");
    echo makeHTMLList($links);

    $r6kparams = r6kMessagesParams();
    makeGraphs($r6kparams);
}

function miniLinkMessages() {

    echo makeLink( SELF_LINK, "Return to CLI Messages page" );

    $links = array();
    $links[] = makeAnchorLink('receivedMiniLink', 'Received MiniLink CLI Messages');
    $links[] = makeAnchorLink('failedMiniLink', 'Failed MiniLink CLI Messages');
    $links[] = makeAnchorLink("receivedDurMiniLink", "Duration MiniLink Received CLI Messages");
    $links[] = makeAnchorLink("failedDurMiniLink", "Duration MiniLink Failed CLI Messages");
    echo makeHTMLList($links);

    $miniLinkparams = miniLinkMessagesParams();
    makeGraphs($miniLinkparams);
}

function makeGraphs($params) {

    foreach ( $params as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 1, $help);

        $graphParams = $param[1];

        foreach ( $graphParams as $graphParam ) {
            $modelledGraph = new ModelledGraph( 'TOR/ncm/' . $graphParam);
            $graphs[] = $modelledGraph->getImage();
        }
        plotgraphs( $graphs );
        echo addLineBreak(2);
    }
}

function mainFlow() {

    $links = array();
    $links[] = makeAnchorLink('receivedCli', 'Received CLI Messages');
    $links[] = makeAnchorLink('failedCli', 'Failed CLI Messages');
    $links[] = makeAnchorLink('cliCommands', 'CLI Commands');
    $links[] = makeLink(SELF_LINK, "Router6000", array('routerMessages'=> '1'));
    $links[] = makeLink(SELF_LINK, "Mini-Link Indoor", array('miniLinkMessages'=> '1'));
    echo makeHTMLList($links);

    $cliParams = cliMessages();
    makeGraphs($cliParams);

    drawHeader('Cli Commands', 2, 'cliCommands');
    $table = new ModelledTable( "TOR/ncm/ncm_cli_command", 'cliCommands' );
    echo $table->getTable("CLI Commands");
}

if (issetURLParam('routerMessages')) {
    routerMessages();
} elseif (issetURLParam('miniLinkMessages')) {
    miniLinkMessages();
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

