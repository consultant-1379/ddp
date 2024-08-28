<?php
$pageTitle = "CM Services";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/JBossThreadPool.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";

const NODES = "Nodes";
const MOS = "MOs";

function params() {
    return array(
        array('cm_logicalHierarchy_topology', 'LogicalHierarchy', 'Logical Hierarchy'),
        array('cm_changeHierarchy_topology', 'ChangeHierarchy', 'Changes Hierarchy'),
        array('cm_loader_er', NODES, NODES),
        array('cm_loader_mos', MOS, MOS)
    );
}

function parsedNodeparams() {
    return array(
        'loader_parsedNodes',
        'loader_mos_invalidParsedNodes'
    );
}

function transformationParams() {
    return array(
        'parsing_and_transforming_process_time',
        'data_loader'
    );
}

function cmDataGraph($transformationParams) {
    $graphs = array();
    foreach ( $transformationParams as $transparam ) {
        $modelledGraph = new ModelledGraph("ECSON/cm/cm_".$transparam);
        $graphs[] = $modelledGraph->getImage();
    }
    plotgraphs( $graphs );
}

function mainFlow() {
    global $statsDB, $site, $date;

    $hasJBossTP = $statsDB->hasData("enm_sg_specific_threadpool");
    $hasDataLoader = $statsDB->hasData("ecson_cm_data_loader");

    $sg = array('eric-cm-loader-er', 'eric-cm-topology-model-sn');
    $srv = k8sGetServiceInstances( $statsDB, $site, $date, $sg);
    $serverIdsArr = array_values($srv);

    $params = params();
    $links = array();
    $tableGraph = array();

    foreach ($params as $param) {
        $table = new ModelledTable( "ECSON/cm/$param[0]", $param[1] );
        if ( $table->hasRows() ) {
            $links[] = makeAnchorLink($param[1], $param[2]);
            $tables = $table->getTableWithHeader($param[2]);
            $graphs = new ModelledGraph("ECSON/cm/$param[0]");
            $tableGraph[] = array('table' => $tables, 'graph' => $graphs);
        }
    }

    if ( $hasDataLoader ) {
        $links[] = makeAnchorLink('cm_data_loader', 'CM Data Loading');
    }

    $kafkalink = kafkaLinks($serverIdsArr, $sg);
    if ( $kafkalink ) {
        $links[] = $kafkalink;
    }

    if ( $hasJBossTP ) {
        $links[] = makeAnchorLink('jboss_tp', 'JBoss Threadpool');
    }

    echo makeHTMLList($links);

    foreach ( $tableGraph as $tab ) {
           echo $tab['table'];
           echo addLineBreak();
           plotgraphs( array( $tab['graph']->getImage() ) );
    }

    cmDataGraph(parsedNodeparams());
    if ( $hasDataLoader ) {
        $table = new ModelledTable( "ECSON/cm/cm_data_loader", 'cm_data_loader' );
        echo $table->getTableWithHeader('CM Data Loading');
        echo addLineBreak();

        cmDataGraph(transformationParams());
    }

    if ( $hasJBossTP ) {
        drawHeader("JBoss Default threadpool Usage", HEADER_1, "jboss_tp");
        foreach ( array('eric-cm-loader-er', 'eric-cm-topology-model-sn') as $app) {
            drawHeader($app, HEADER_2, "jboss_tp_$app");
            $tp = new JBossThreadPool($app,'default');
            echo $tp->getGraphsAsTable();
        }
    }
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

