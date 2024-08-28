<?php

$pageTitle = 'IBS Error Loader Sets';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'IBS Error Loader Sets', 1, 'ibsErrorTableHelp' );
    $table = new ModelledTable('ENIQ/IBS_error_loader_set', 'IBS_error_loader_set');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
