<?php

$pageTitle = 'Failed Set Information';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'Failed Set Information', 1, 'failedSetTableHelp' );
    $table = new ModelledTable('ENIQ/loader_aggregator_failedset_details', 'loader_aggregator_failedset_details');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
