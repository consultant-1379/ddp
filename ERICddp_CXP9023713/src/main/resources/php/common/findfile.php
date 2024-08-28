<?php
$pageTitle = "Find File";

const RELPATH_PARAM = "relpath";
const GETFILE_PARAM = "getfile";
$DISABLE_UI_PARAMS = array(GETFILE_PARAM);

require_once "./init.php";
require_once PHP_ROOT . "/classes/FindFile.php";

$findFile = new FindFile();
$findFile->processRequest(FindFile::SITE_MODE);

include PHP_ROOT . "/common/finalise.php";
