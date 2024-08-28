<?php
$pageTitle = "RET Custom Services";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";
require_once PHP_ROOT . "/classes/JBossThreadPool.php";

function retAlgParams() {
    return array(
        array('cmChangeCount', 'Proposed CM Changes')
    );
}

function retGraph($cmParams) {
    foreach ( $cmParams as $alg ) {
        drawHeader($alg[1], 2, $alg[0]);
        $modelledGraph = new ModelledGraph("ECSON/ret/" . $alg[0]);
        plotgraphs( array( $modelledGraph->getImage() ) );
    }
}

function showLinks($serverIdsArr, $sg, $hasJBossTP) {

    $links = array();
    $links[] = makeAnchorLink('cmChangeCount', 'Proposed CM Changes');

    $kafkalink = kafkaLinks($serverIdsArr, $sg);
    if ( $kafkalink ) {
        $links[] = $kafkalink;
    }

    if ( $hasJBossTP ) {
        $links[] = makeAnchorLink('jboss_tp', 'JBoss Threadpool');
    }

    echo makeHTMLList($links);
}

function mainFlow() {
    global $statsDB, $site, $date;

    $sg = array('eric-ret-algorithm');
    $srv = k8sGetServiceInstances( $statsDB, $site, $date, $sg);
    $serverIdsArr = array_values($srv);

    $hasJBossTP = $statsDB->hasData("enm_sg_specific_threadpool");

    showLinks( $serverIdsArr, $sg, $hasJBossTP );

    $table = new ModelledTable( "ECSON/ret/ret_custom_service", 'retCustomService' );
    echo $table->getTableWithHeader("RET Custom Service");
    echo addLineBreak();

    retGraph(retAlgParams());
    if ( $hasJBossTP ) {
        drawHeader("JBoss Default Threadpool Usage", HEADER_1, "jboss_tp");
        foreach ( array('eric-ret-algorithm') as $app) {
            drawHeader($app, HEADER_2, "jboss_tp_$app");
            $tp = new JBossThreadPool($app, 'default');
            echo $tp->getGraphsAsTable();
        }
    }

}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

