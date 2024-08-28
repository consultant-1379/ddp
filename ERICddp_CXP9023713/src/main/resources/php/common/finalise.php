<?php
$DDP_TIME_END = microtime(true);
$DDP_EXEC_TIME = sprintf("%9.3f", $DDP_TIME_END - $DDP_TIME_START);
if (isset($perfLog) && is_writeable($perfLog)) {
  $referer = "-";
  if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
  }

  $user = "-";
  if (isset($auth_user) ){
    $user = $auth_user;
  }

  error_log(sprintf("%-20s%-30s%-8.3f%s %s %s\n", date("Y-m-d H:i:s"), $_SERVER['SCRIPT_NAME'], $DDP_EXEC_TIME, $user, $_SERVER['REQUEST_URI'], $referer), 3, $perfLog);
}
if (! isset($UI) || $UI != false) include $php_common . "/bottom.php";
?>
