<?php
$UI = false;
$NOREDIR = true;

require_once "init.php";


$fullPath = getPathFromArgs();

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . basename($fullPath));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length:' . filesize($fullPath));

ob_end_clean();
flush();
readfile($fullPath);

exit;
?>
