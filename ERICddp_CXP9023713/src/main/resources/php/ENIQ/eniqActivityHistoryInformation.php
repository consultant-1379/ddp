<?php
$pageTitle = "ENIQ Activity History";

include_once "../common/init.php";
require_once PHP_ROOT . "/ENIQ/tableFunctions.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function conditionHeaderCheck( &$key ) {
    if ( $key == "name" ) {
        $key = "Activity Name";
    } elseif ( $key == "completionTime" ) {
        $key = "Completion Time";
    } elseif ( $key == "description" ) {
        $key = "Additional Information";
    }
}

function main() {
    global $datadir;
    $host = requestValue('server');
    $hostType = requestValue('serverType');
    $eniqActivityHistoryFile = $datadir . "/plugin_data/eniq_activity_history/eniq_activity_history-$host-$hostType.json";
    $eniqActivityHistoryString = file_get_contents( $eniqActivityHistoryFile ); //NOSONAR
    $arrayOfEniqActivityHistoryData = array( $eniqActivityHistoryString );
    // See function in php/ENIQ/tableFunctions.php
    drawHeader( "ENIQ Activity History", 1, 'eniqActivityHistoryHelp' );
    getTableFromData( $arrayOfEniqActivityHistoryData, "eniq_activity_history_details", " ", 1 );

}

main();

include_once PHP_ROOT . "/common/finalise.php";