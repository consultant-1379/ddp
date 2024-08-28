<?php
$pageTitle = "Find DDP Log File";

const RELPATH_PARAM = "relpath";
const GETFILE_PARAM = "getfile";
$DISABLE_UI_PARAMS = array(GETFILE_PARAM);

include "init.php";

require_once PHP_ROOT . "/classes/FindFile.php";

$findFile = new FindFile();
$findFile->processRequest(FindFile::DDP_MODE);

include "../php/common/finalise.php";
?>
