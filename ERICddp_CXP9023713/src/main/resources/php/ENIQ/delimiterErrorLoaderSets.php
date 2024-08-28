<?php

$pageTitle = 'Delimiter Error Loader Sets';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'Delimiter Error Loader Sets', 1, 'delimiterErrorTableHelp' );
    $table = new ModelledTable('ENIQ/delimiter_error_loader_set', 'delimiter_error_loader_set');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
