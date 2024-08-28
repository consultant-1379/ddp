<?php
$pageTitle = "Export Table";

$UI = false;
include "./init.php";

require_once PHP_ROOT . "/classes/ExcelWorkbook.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

function main($tableParams) {
    $excel = new ExcelWorkbook();
    $excel->addObject($tableParams);
    $excel->write();
}

$tableParams = requestValue("tableParams");
if ( is_null($tableParams) ) {
    echo "<p><b>The file could not be generated.</b></p>";
} else {
    main($tableParams);
}

include PHP_ROOT . "/common/finalise.php";
