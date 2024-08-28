<?php

$pageTitle = 'ENIQ Certificate Information';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    global $site, $date, $webargs;
    drawHeader( 'ENIQ Certificate Expiry Details', 1, 'certificateHelp' );
    $table = new ModelledTable('ENIQ/eniq_certificate', 'eniq_certificate');
    echo $table->getTable();

}

main();
include_once PHP_ROOT . "/common/finalise.php";
