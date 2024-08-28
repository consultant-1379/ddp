<?php

$pageTitle = 'Unity/UnityXT Certificate Information';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    global $site, $date, $webargs;
    drawHeader( 'Unity/UnityXT Certificate Expiry Details', 1, 'unityCertificateHelp' );
    $table = new ModelledTable('ENIQ/unity_certificate', 'unity_certificate');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
