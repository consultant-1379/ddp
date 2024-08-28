<?php
$pageTitle = 'Scheduling Information';
require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'Scheduling Information', 1, 'schedulingInfoHelp' );
    $table = new ModelledTable('ENIQ/bis_data_scheduling_info', 'bis_data_scheduling_info');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
