<?php

$pageTitle = 'Error Analysis';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'Error Analysis', 1, 'ErrorAnalysisTableHelp' );
    $table = new ModelledTable('ENIQ/error_analysis_table_info', 'error_analysis_table_info');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
