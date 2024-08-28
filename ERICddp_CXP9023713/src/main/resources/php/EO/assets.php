<?php

const ASSETS = 'Assets';
$pageTitle = ASSETS;

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    global $site, $date, $webargs;

    $table = new ModelledTable('EO/assets', 'assets');
    echo $table->getTableWithHeader(ASSETS);
}

main();
