<?php

$pageTitle = 'Prompt Info Details';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    $table = new ModelledTable('ENIQ/promptDetails', 'promptDetailsHelp');
    echo $table->getTableWithHeader("Prompt Info Details");
}

main();
include_once PHP_ROOT . "/common/finalise.php";