<?php

$pageTitle = "MEF Service LCM";

include_once "../../common/init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function mefServiceLcm() {

    $generalGraphs = array(
        'mef_config_messages',
        'mef_failed_config_messages'
    );
    $crossGraphs = array(
        'mef_create_crossConnections_success',
        'mef_create_crossConnections_failed',
        'mef_create_crossConnections_success_duration',
        'mef_create_crossConnections_failed_duration',
        'mef_delete_crossConnections_success',
        'mef_delete_crossConnections_failed',
        'mef_delete_crossConnections_success_duration',
        'mef_delete_crossConnections_failed_duration',
        'mef_modify_crossConnections_success',
        'mef_modify_crossConnections_failed',
        'mef_modify_crossConnections_success_duration',
        'mef_modify_crossConnections_failed_duration'
    );
    $setGraphs = array(
        'mef_setCesPort_success',
        'mef_setCesPort_failed',
        'mef_setCesPort_success_duration',
        'mef_setCesPort_failed_duration',
        'mef_setDataPort_success',
        'mef_setDataPort_failed',
        'mef_setDataPort_success_duration',
        'mef_setDataPort_failed_duration'
    );
    return array(
        array( 'General Information', $generalGraphs, 'generalInfo' ),
        array( 'Cross Connections', $crossGraphs, 'crossConnection' ),
        array( 'Set Messages', $setGraphs, 'setGraphs' )
    );
}

function mainFlow() {

    $links = array();
    $links[] = makeAnchorLink('generalInfo', 'General Information');
    $links[] = makeAnchorLink('crossConnection', 'Cross Connections');
    $links[] = makeAnchorLink('setGraphs', 'Set Messages');
    echo makeHTMLList($links);

    $table = new ModelledTable( "TOR/ncm/ncm_mef_service_lcm", 'mefservicelcm' );
    echo $table->getTableWithHeader("MEF Service LCM");

    $mefParams = mefServiceLcm();

    foreach ( $mefParams as $param ) {
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

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

