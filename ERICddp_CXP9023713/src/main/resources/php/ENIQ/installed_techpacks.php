<?php

$pageTitle = 'Installed Tech packs';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    $table = new ModelledTable('ENIQ/eniq_techpacks', 'eniqTechpacksHelp');
    echo $table->getTableWithHeader("Active Tech Packs");
}

main();
include_once PHP_ROOT . "/common/finalise.php";