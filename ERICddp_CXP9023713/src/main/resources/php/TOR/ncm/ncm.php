<?php

if ( $_REQUEST['flow'] === 'MEFServiceDiscovery' ) { //NOSONAR
    $pageTitle = "NCM MEF Service Discovery";
} elseif ( $_REQUEST['flow'] === 'EventsProcessing' ) { //NOSONAR
    $pageTitle = "NCM Events Processing";
}

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

const NCM_ROOT = 'TOR/ncm/';

function mefServiceDiscoveryParams() {
    $nlrGraphs = array(
        'nlr_success',
        'nlr_failed',
        'nlr_success_dur',
        'nlr_failed_dur',
        'nlr'
    );
    $nrGraphs = array(
        'nr_start',
        'nr_end',
        'nr_success_dur',
        'nr_failed_dur',
        'nr_get_messages',
        'nr_realignments_Failed'
    );
    $lrGraphs = array(
        'lr_success',
        'lr_failed',
        'lr_success_dur',
        'lr_failed_dur',
        'lr',
        'lr_valid_links',
        'lr_invalid_links',
        'lr_links_discovered'
    );
    $frGraphs = array(
        'fr_success',
        'fr_failed',
        'fr_success_dur',
        'fr_failed_dur',
        'fr'
    );
    $cGraphs = array(
        'c_opened',
        'c_closed',
        'c_ping_failed',
        'c_hearthbeat_failed'
    );

    $nlrTbl = 'TOR/ncm/ncm_nodes_list_realignment';
    $nrTbl = 'TOR/ncm/ncm_node_realignment';
    $iiTbl = 'TOR/ncm/ncm_ignored_interfaces';
    $lrTbl = 'TOR/ncm/ncm_links_realignment';
    $cTbl = 'TOR/ncm/ncm_sessions';

    return array(
        array( 'Nodes List Realignment', $nlrTbl, $nlrGraphs, 'nodesListReal' ),
        array( 'Node Realignment', $nrTbl, $nrGraphs, 'nodeReal' ),
        array( 'Ignored Interfaces', $iiTbl, '', 'ignoredInterfaces' ),
        array( 'Links Realignment', $lrTbl, $lrGraphs, 'linksReal' ),
        array( 'Full Realignment', '', $frGraphs, 'fullReal' ),
        array( 'Connectivity', $cTbl, $cGraphs, 'conn' )
    );
}

function eventsProcessingParams() {
    $leGraphs = array(
        'le_add_recieved',
        'le_add_processed',
        'le_delete_recieved',
        'le_delete_processed',
        'le_update_recieved',
        'le_update_processed'
    );

    $gesGraphs = array(
        'ges_recieved',
        'ges_sent',
        'ges_dps_delay'
    );

    $aeGraphs = array(
        'ae_queue_full_discarded',
        'ae_queue_full',
        'ae_extra_man',
        'ae_agent_not_connected',
        'ae_not_sent',
        'ae_already_man',
        'ae_agent_da_received',
        'ae_agent_da_sent',
        'ae_events_skipped'
    );

    $rrGraphs = array(
        'rr_fail_em_request',
        'rr_fail_node_request',
        'rr_ord_em_request',
        'rr_ord_node_request'
    );

    return array(
        array('Link Events', $leGraphs, 'leGraphs'),
        array('Generic Events Summary', $gesGraphs, 'gesGraphs'),
        array('Anomalies on Events', $aeGraphs, 'aeGraphs'),
        array('Realignment Requests', $rrGraphs, 'rrGraphs')
    );
}

function nodeEventGraphs() {
    $graphs = array();
    drawHeader('Node Events', 1, 'neGraphs');
    getGraphsFromSet( 'nodeEvents', $graphs, 'TOR/ncm/enm_ncmagent_instr', null, 640, 320);
    plotgraphs( $graphs );
}

function eventCounterTablesParams() {
    $ne = array('Network Element Events', 'neEvents', 'ncm_ne_events', 'enm_ncm_node_events_recieved');
    $nl = array('Network Link Events', 'nlEvents', 'ncm_nl_events', 'enm_ncm_link_events_recieved');

    return array( $ne, $nl );
}

function mefServiceDiscoveryFlow() {
    global $site;

    $params = mefServiceDiscoveryParams();
    $links = array();

    drawHeader('MEF Service Discovery', 1, '');

    $eventsProcessing = makeSelfLink() . "&flow=EventsProcessing";
    $links[] = makeLinkForURL($eventsProcessing, 'Events Processing');

    foreach ( $params as $param ) {
        $links[] = makeAnchorLink($param[3], $param[0]);
    }
    echo makeHTMLList( $links );

    foreach ( $params as $param ) {
        $graphs = array();

        $secTitle = $param[0];
        $help = $param[3];
        drawHeader($secTitle, 2, $help);

        $tableParam = $param[1];
        $graphParams = $param[2];

        if ( $tableParam ) {
            $details = new ModelledTable( $tableParam, $help );
            echo $details->getTable();
            echo addLineBreak();
        }

        if ( $graphParams ) {
            foreach ( $graphParams as $graphParam ) {
                $modelledGraph = new ModelledGraph( NCM_ROOT . $graphParam);
                $graphs[] = $modelledGraph->getImage();
            }
            drawHeader( $secTitle . ' Instr Graphs', 3, $help . 'Graphs' );
            plotgraphs( $graphs );
            echo addLineBreak(2);
        }
    }
}

function eventsProcessingFlow() {
    global $site, $statsDB;

    drawHeader('Events Processing', 1, '');
    $epParams = eventsProcessingParams();
    $links[] = makeAnchorLink('neGraphs', 'Node Events');
    foreach ( $epParams as $param ) {
        $links[] = makeAnchorLink($param[2], $param[0]);
    }
    echo makeHTMLList( $links );

    $evCntTabParams = eventCounterTablesParams();
    foreach ( $evCntTabParams as $param ) {
        $dbTab = $param[3];
        if ( $statsDB->hasData($dbTab, 'date', true) ) {
            $secTitle = $param[0];
            $help = $param[1];
            $tableParam = $param[2];

            if ( $tableParam ) {
                drawHeader($secTitle, 2, $help);
                $details = new ModelledTable( NCM_ROOT . $tableParam, $help );
                echo $details->getTable();
                echo addLineBreak();
            }
        }
    }

    nodeEventGraphs();

    foreach ( $epParams as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 2, $help);

        $graphParams = $param[1];
        if ( $graphParams ) {
            foreach ( $graphParams as $graphParam ) {
                $modelledGraph = new ModelledGraph( NCM_ROOT . $graphParam);
                $graphs[] = $modelledGraph->getImage();
            }
        }
        plotgraphs( $graphs );
        echo addLineBreak(2);
    }
}

if ( requestValue('flow') === 'MEFServiceDiscovery' ) {
    mefServiceDiscoveryFlow();
} elseif ( requestValue('flow') === 'EventsProcessing' ) {
    eventsProcessingFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

