<?php
$pageTitle = "PMIC CRUD NBI";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function pmicgraphs() {

    $requestTypes = array (
        'List Subscriptions Rest NBI',
        'Get Subscription Rest NBI',
        'Create Subscription Rest NBI',
        'Activate Subscription Rest NBI',
        'Deactivate Subscription Rest NBI',
        'Edit Subscription Rest NBI',
        'Delete Subscription Rest NBI'
    );

    foreach ( $requestTypes as $requestType ) {

        $graphs = array();
        drawHeader( $requestType, 2, ' ' );
        $graphParams = array( 'requestType' => $requestType );
        getGraphsFromSet( 'pmicrestnbi', $graphs, 'TOR/pm/pmic_rest_nbi', $graphParams );
        plotgraphs( $graphs );
    }

}

function mainFlow() {
    drawHeader('PMIC CRUD NBI', 1, 'pmicrestnbi');
    $table = new ModelledTable( "TOR/pm/pmic_rest_nbi", 'pmicrestnbi' );
    echo $table->getTable();
    echo addLineBreak();

    drawHeader('PMIC CRUD NBI Graphs', 1, 'pmicrestnbi');
    pmicgraphs();

}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
