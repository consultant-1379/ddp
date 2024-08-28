<?php
$UI = false;
include "init.php";
$ddl = $php_root . "/../sql/statsdb.ddl";
header("Content-type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"statsdb.sql\"");
echo file_get_contents($ddl);
exit(0);
?>
