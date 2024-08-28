<?php

$pageTitle = 'RHEL OS And Patch Version';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {

    $table = new ModelledTable('ENIQ/rhelVersion', 'phelVersionHelp');
    drawHeader( 'OS Version', 2, 'osHelp' );
    echo $table->getTable();
    echo addLineBreak();
    $table = new ModelledTable('ENIQ/patchVersion', 'patchVersionHelp');
    drawHeader( 'Patch Version', 2, 'patchHelp' );
    echo $table->getTable();
    echo addLineBreak();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
