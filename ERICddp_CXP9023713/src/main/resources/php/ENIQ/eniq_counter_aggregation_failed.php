<?php

$pageTitle = 'Counter Tool Aggregation Failed Date';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    $table = new ModelledTable('ENIQ/eniq_failed_data', 'eniq_failed_data');
    echo $table->getTableWithHeader("Aggregation Failure Date");
}

main();

include_once PHP_ROOT . "/common/finalise.php";
