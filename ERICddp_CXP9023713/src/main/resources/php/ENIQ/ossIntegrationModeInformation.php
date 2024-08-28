<?php
$pageTitle = "OSS Integration Information";

include_once "../common/init.php";
require_once PHP_ROOT . "/ENIQ/tableFunctions.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function conditionHeaderCheck( &$key ) {
    if ( $key == "ossIdentifier" ) {
        $key = "OSS Identifier";
    } elseif ( $key == "ossType" ) {
        $key = "OSS Type";
    } elseif ( $key == "integrationMode" ) {
        $key = "Integration Mode";
    }
}

function main() {
    global $datadir;
    $flsIntegrationModeFile = $datadir . "/plugin_data/file_lookup_service/fls_oss_integration_mode.json";
    $flsIntegrationModeString = file_get_contents($flsIntegrationModeFile); //NOSONAR
    $arrayOfFLSIntegrationData = array($flsIntegrationModeString);
    // See function in php/ENIQ/tableFunctions.php
    getTableFromData( $arrayOfFLSIntegrationData, "OSS_Integration_Mode_Details", "Integration Mode Details", 1 );
}

main();

include_once PHP_ROOT . "/common/finalise.php";
