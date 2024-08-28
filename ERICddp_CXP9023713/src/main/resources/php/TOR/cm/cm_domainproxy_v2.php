<?php
$pageTitle = "DomainProxy";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

function dpsParams() {
    return array(
        'dp_dps_perf',
        'dp_dps_frq_chng'
    );
}

function sasParams() {
    return array(
        'dp_sas_reg_req',
        'dp_sas_reg_res',
        'dp_sas_dereg_req',
        'dp_sas_dereg_res',
        'dp_sas_grnt_req',
        'dp_sas_grnt_res',
        'dp_sas_hb_req',
        'dp_sas_hb_res',
        'dp_sas_si_req',
        'dp_sas_si_res',
        'dp_sas_rel_req',
        'dp_sas_rel_res',
        'dp_sas_fail',
        'dp_sas_ren_grnt',
        'dp_sas_sus_grnt',
        'dp_sas_ter_grnt'
    );
}

function sasPerfParams() {
    return array(
        'dp_sas_perf',
        'dp_sas_hb_resp_perf',
        'dp_sas_slw_hb_resp'
    );
}

function nodeParams() {
    return array(
        'dp_node_perf'
    );
}

function trnsCellParams() {
    return array(
        'dp_trns_exp_cell_perf',
        'dp_min_trns_exp_time'
    );
}

function trnsSasParams() {
    return array(
        'dp_trns_exp_sas_perf'
    );
}

function displayAbsoluteTable() {
    $params = array( ModelledTable::URL => makeSelfLink() );
    $tbl = new ModelledTable( 'TOR/cm/dp_absolute_counts', "tbl", $params );
    if ( $tbl->hasRows() ) {
        echo drawHeader("Domain Proxy - Daily Total", 1, 'absCnts');
        echo $tbl->getTable();
    }
}

function plotAbsoluteCounts($selected) {
    $graphs = array();
    $cols = array(
        'valid_grants',
        'maintained_grants',
        'active_inactive_cells',
        'registered_cbsds',
        'transmitting_cells'
    );

    $params = array( 'sids' =>  $selected );
    drawHeader('Absolute Counts', 1, 'absCnts');
    foreach ( $cols as $col ) {
        $modelledGraph = new ModelledGraph('TOR/cm/domainproxy/dp_absolute_counts_' . $col);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs( $graphs );
}

function displayGraphs( $params ) {
    $graphs = array();
    foreach ( $params as $param ) {
        $modelledGraph = new ModelledGraph('TOR/cm/domainproxy/' . $param);
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs( $graphs );
}

function addLinks() {
    $links = array();

    $links[] = makeAnchorLink('sas', 'SAS Interactions');
    $links[] = makeAnchorLink('dps', 'DPS Interactions');
    $links[] = makeAnchorLink('sasPerf', 'SAS Performance');
    $links[] = makeAnchorLink('nodePerf', 'Node Performance');
    $links[] = makeAnchorLink('trnsCell', 'TransmitExpiry Set on the Cells');
    $links[] = makeAnchorLink('trnsSAS', 'TransmitExpiry from SAS');

    echo makeHTMLList($links);
}

function mainFlow() {
    displayAbsoluteTable();

    addLinks();

    $params = sasParams();
    drawHeader('SAS Interactions', 1, 'sas');
    displayGraphs( $params );

    $params = dpsParams();
    drawHeader('DPS Interactions', 1, 'dps');
    displayGraphs( $params );

    $params = sasPerfParams();
    drawHeader('SAS Performance', 1, 'sasPerf');
    displayGraphs( $params );

    $params = nodeParams();
    drawHeader('Node Performance', 1, 'nodePerf');
    displayGraphs( $params );

    $params = trnsCellParams();
    drawHeader('TransmitExpiry Set on the Cells', 1, 'trnsCell');
    displayGraphs( $params );

    $params = trnsSasParams();
    drawHeader('TransmitExpiry from SAS', 1, 'trnsSAS');
    displayGraphs( $params );
}

if ( issetURLParam('action') ) {
    if ( requestValue('action') === 'plotAbsoluteCounts') {
        $selected = requestValue('selected');
        plotAbsoluteCounts($selected);
    } else {
        echo "Error";
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

