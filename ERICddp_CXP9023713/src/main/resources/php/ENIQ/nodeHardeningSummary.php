<?php
$pageTitle = "Node Hardening Summary";

include_once "../common/init.php";
require_once PHP_ROOT . "/ENIQ/tableFunctions.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function conditionHeaderCheck( &$key ) {
    if ( $key == "featureName" ) {
        $key = "Feature Name";
    } elseif ( $key == "featureDescription" ) {
        $key = "Feature Description";
    } elseif ( $key == "status" ) {
        $key = "Compliance Status";
    }
}

function main() {
    global $datadir;
    $hardeningType = requestValue('hardeningType');
    $host = requestValue('server');
    $hostType = requestValue('serverType');
    $nodeHardeningFile = $datadir . "/plugin_data/node_hardening/node_hardening_$hardeningType-$host-$hostType.json";
    $nodeHardeningString = file_get_contents( $nodeHardeningFile ); //NOSONAR
    $arrayOfNodeHardeningData = array( $nodeHardeningString );
    // See function in php/ENIQ/tableFunctions.php
    drawHeader( "$hardeningType Node Hardening Summary", 1, 'nodeHardeningHelp' );
    getTableFromData( $arrayOfNodeHardeningData, "node_hardening_details", " ", 1 );

}

main();

include_once PHP_ROOT . "/common/finalise.php";
