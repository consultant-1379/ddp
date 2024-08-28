<?php

$pageTitle = 'OM & Patch Media Information';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {

    $table = new ModelledTable('ENIQ/om_media_info', 'omMediaHelp');
    drawHeader( 'OM Media Current Information', 2, 'omHelp' );
    echo $table->getTable();
    echo addLineBreak();
    $table = new ModelledTable('ENIQ/patch_media_info', 'patchMediaHelp');
    drawHeader( 'Patch Media Current Information', 2, 'patchHelp' );
    echo $table->getTable();
    echo addLineBreak();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
