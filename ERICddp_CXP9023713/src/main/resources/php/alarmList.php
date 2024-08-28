<?php
$UI = false;
include "common/init.php";

if ( file_exists($rootdir . "/fm") ) {
  $rootdir = $rootdir . "/fm";
}

if (isset($_REQUEST['list'])) $file = $_REQUEST['list'] . ".html";
else $file = "alarmListTable.html";

if (isset($_GET['format']) && $_GET['format'] == "xls") {
    include "classes/alarmList.php";
    $almList = new AlarmList($rootdir . "/" . $file);
    $excel = new ExcelWorkbook();
    $excel->addObject($almList);
    $excel->write();
    exit;
}

?>

<table border=1>

<?php
if (file_exists($rootdir . "/" . $file )) {
//  include($rootdir . "/alarmListTable.html");
    if (isset($_REQUEST['cols'])) {
        echo "<tr>";
        $cols = explode(",", requestValue('cols'));
        foreach ($cols as $col) echo "<th>" . $col . "</th>";
        echo "</tr>\n";
    }
    $fp = @fopen($rootdir . "/" . $file, "r");
    if ($fp) {
        while (! feof($fp)) {
            $buffer = fgets($fp, 1024);
            echo $buffer . "\n";
        }
        fclose($fp);
    }
}
?>
</table>

<?php

include "common/finalise.php";
?>
