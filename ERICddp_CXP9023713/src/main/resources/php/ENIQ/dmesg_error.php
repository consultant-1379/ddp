<?php

$pageTitle = 'Dmesg Hardware Error';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    global $statsDB, $site;
    $server = getInstances( "eniq_stats_dmesg", "date", " ", "siteId", "serverId" );
    drawHeader( 'Dmesg Hardware Error', 1, 'DmesgHardwareErrorTableHelp' );
    foreach ($server as $hostname) {
        $serverId = getServerId( $statsDB, $site, $hostname );
        $params = array( 'serverId' => "$serverId" );
        $table = new ModelledTable('ENIQ/eniq_stats_dmesg_error', "eniq_stats_dmesg_error_$serverId", $params);
        echo $table->getTableWithHeader("$hostname", 2);
        echo addLineBreak();
    }
}
main();
include_once PHP_ROOT . "/common/finalise.php";
