<?php
include "../common/init.php";

echo "<H1>" . date("F", mktime(0,0,0,$month,1,$year)) . "</H1>\n";

$statsDB = new StatsDB();

$startdate = $start;
$enddate = $end;

$args = "site=$site&start=$startdate&end=$enddate&year=$year&month=$month&oss=$oss";
$argsArray = array('start' => $startdate, 'end' => $enddate, 'year' => $year, 'month' => $month);

$indexIncFile = PHP_ROOT . "/monthly/" . strtoupper($oss) . "/index_inc.php";
if ( file_exists($indexIncFile) ) {
  include $indexIncFile;
}

include PHP_ROOT . "/common/finalise.php";
?>
