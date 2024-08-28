<?php
$pageTitle = "Installed Features";

include_once "../common/init.php";
require_once PHP_ROOT . "/ENIQ/tableFunctions.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function conditionHeaderCheck( &$key ) {
    if ( $key == "featuresName" ) {
        $key = "Features Name";
    } elseif ( $key == "CXCNumber" ) {
        $key = "CXC Number";
    } elseif ( $key == "CXPNumber" ) {
        $key = "CXP Number";
    } elseif ( $key == "buildNumber" ) {
        $key = "Build Number";
    }
}

function main() {
    global $datadir;
    $installedFeaturesFile = $datadir . "/plugin_data/installed_features/installed_feature.json";
    $installedFeaturesString = file_get_contents( $installedFeaturesFile ); //NOSONAR
    $arrayOfInstalledFeaturesData = array( $installedFeaturesString );
    // See function in php/ENIQ/tableFunctions.php
    drawHeader( "Installed Features ", 1, 'InstalledHelp' );
    getTableFromData( $arrayOfInstalledFeaturesData, "Installed_Features_Details", " ", 1 );

}

main();

include_once PHP_ROOT . "/common/finalise.php";
