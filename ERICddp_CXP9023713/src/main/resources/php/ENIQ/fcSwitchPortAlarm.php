<?php

$pageTitle = 'FC SwitchPort Alarm';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    $table = new ModelledTable('ENIQ/fcSwitchPortAlarm', 'fcSwitchPortAlarm');
    echo $table->getTableWithHeader("FC Switch Port Alarm");
}

main();

include_once PHP_ROOT . "/common/finalise.php";
