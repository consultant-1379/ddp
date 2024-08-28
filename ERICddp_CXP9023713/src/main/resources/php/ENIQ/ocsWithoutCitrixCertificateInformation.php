<?php

$pageTitle = 'OCS Without Citrix Certificate Information';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    global $site, $date, $webargs;
    drawHeader( 'OCS Without Citrix Certificate Expiry Details', 1, 'ocsWithoutCitrixCertificateHelp' );
    $table = new ModelledTable('ENIQ/ocs_without_citrix_certificate', 'ocs_without_citrix_certificate');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";


