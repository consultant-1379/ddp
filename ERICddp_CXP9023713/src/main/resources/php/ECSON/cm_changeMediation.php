<?php
$pageTitle = "CM Change Mediation";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";

function changeMediationParams() {
    return array(
        array('cm_statusUpdateHttpRequest', 'statusUpdate', 'Status Update HTTP Request'),
        array('cm_activationChangeHttpRequest', 'activationChange', 'Activation Change HTTP Request'),
        array('cm_succeededActivation', 'succeededActivation', 'Succeeded Activation'),
        array('cm_succeededChange', 'succeededChange', 'Succeeded Change')
    );
}

function changeMediationGraph($cmParams) {
    foreach ( $cmParams as $alg ) {
        drawHeader($alg[2], 2, $alg[1]);
        $modelledGraph = new ModelledGraph("ECSON/cm/$alg[0]");
        plotgraphs( array( $modelledGraph->getImage() ) );
    }

}

function showLinks($serverIdsArr, $sg) {

    $links = array();
    $links[] = makeAnchorLink('statusUpdate', 'Status Update HTTP Request');
    $links[] = makeAnchorLink('activationChange', 'Activation Change HTTP Request');
    $links[] = makeAnchorLink('succeededActivation', 'Succeeded Activation');
    $links[] = makeAnchorLink('succeededChange', 'Succeeded Change');

    $kafkalink = kafkaLinks($serverIdsArr, $sg);
    if ( $kafkalink ) {
        $links[] = $kafkalink;
    }

    echo makeHTMLList($links);

}

function mainFlow() {
    global $statsDB, $site, $date;

    $sg = array('eric-cm-change-mediator-er');
    $srv = k8sGetServiceInstances( $statsDB, $site, $date, $sg);
    $serverIdsArr = array_values($srv);

    showLinks( $serverIdsArr, $sg );

    $table = new ModelledTable( "ECSON/cm/cm_change_mediation", 'CMChangeMediation' );
    echo $table->getTableWithHeader("CM Change Mediation");
    echo addLineBreak();

    changeMediationGraph(changeMediationParams());

}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

