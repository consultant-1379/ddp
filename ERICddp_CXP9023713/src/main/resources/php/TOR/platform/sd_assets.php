<?php

$pageTitle = "SD Assets Reuses";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function mainFlow() {

    drawHeader("ENM SD Assets Reuses", 2, sdassets);
    $table = new ModelledTable( "TOR/platform/sd_assets", sdassets);
    echo $table->getTable();
    echo addLineBreak();

}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
