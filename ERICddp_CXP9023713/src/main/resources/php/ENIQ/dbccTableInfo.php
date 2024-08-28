<?php

$pageTitle = 'DBCC Table Information';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'DBCC Table Information', 1, 'DBCCCheckTableHelp' );
    $table = new ModelledTable('ENIQ/dbcc_table_info', 'dbcc_table_info');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
