<?php

$pageTitle = 'NAS File System Status';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'NAS File System Status', 1, 'nasFileSystemStatusTableHelp' );
    $table = new ModelledTable('ENIQ/nas_file_system_status', 'nas_file_system_status');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
