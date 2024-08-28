<?php
$pageTitle = "Lun Mpath Mapping";

include_once "../common/init.php";
require_once PHP_ROOT . "/ENIQ/tableFunctions.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function conditionHeaderCheck( &$key ) {
    if ( $key == "disk" ) {
        $key = "Disk";
    } elseif ( $key == "mapping" ) {
        $key = "Mapping";
    } elseif ( $key == "rawDeviceMapping" ) {
        $key = "Raw Device Mapping";
    } elseif ( $key == "iqHeaderId" ) {
        $key = "IQ Header ID";
    }
}

function main() {
    global $datadir;

    $host = requestValue('server');
    $hostType = requestValue('serverType');
    $lunMpathMappingFile = $datadir . "/plugin_data/lun_mpath_iq_header_mapping/lun_mpath_mapping-$host-$hostType.json";
    $lumMpathMappingString = file_get_contents( $lunMpathMappingFile ); //NOSONAR
    $arrayOfLunMpathMappingData = array( $lumMpathMappingString );
    // See function in php/ENIQ/tableFunctions.php
    drawHeader( "LUNs MPath IQ Header Mapping", 1, 'lunMpathIqHeaderHelp' );
    getTableFromData( $arrayOfLunMpathMappingData, "lun_mpath_mapping_details", " ", 1 );
}

main();

include_once PHP_ROOT . "/common/finalise.php";