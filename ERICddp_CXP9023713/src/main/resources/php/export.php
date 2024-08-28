<?php
$pageTitle = "Bulk CM Export Stats";
include "common/init.php";
$webroot = $webroot . "/export";
$rootdir = $rootdir . "/export";

include "classes/export.php";
$export = new Export();

if (isset($_GET['format']) && $_GET['format'] == "xls") {
    $excel = new ExcelWorkbook();
    $excel->addObject($export);
    $excel->write();
    exit;
}

?>
<a href="?<?=$_SERVER['QUERY_STRING']?>&format=xls">Export to Excel</a>
<h2><?=$export->title?></h2>
<?php
if ( file_exists($rootdir . "/exporttable.html") ) {
    echo "<table border=1>\n";
    include($rootdir . "/exporttable.html");
    echo "</table\n";
} else {
    $export->getHtmlTable(true);

    while ($row = $export->getNext()) {
        echo "<a name=\"" . $export->arrayPointer . "\"></a>\n";
	ereg("^([^ \/]*)\.xml", $row["file"], $file);
	echo "<h2>" . $row['start'] . " " . $file[1] . "</h2>\n";
        ereg("^([^ ]*) ([0-9]*):([0-9]*):([0-9]*)$", $row["start"], $start);
	$startTs = $start[1] . ":" . $start[2] . ":" . $start[3];
	$tiV1 = $rootdir . "/ti_" . $startTs . ".html";
	$tiV2 = $rootdir . "/ti_" . $startTs . ":" . $start[4] . 
	  "_" . $file[1] . ".html";
	
        if ( file_exists($tiV2) ) {
            include($tiV2);
        } else if ( file_exists($tiV1) ) {
            include($tiV1);
        } else if ( file_exists($rootdir . "/rate_" . $startTs . ".jpg") ) {
            echo "<h3>Nodes/Min</h3>\n";
            echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/export/rate_" . $startTs . ".jpg\" alt=\"\">\n";
            echo "<h3>Nodes Processed</h3>\n";
            echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/export/processed_" . $startTs . ".jpg\" alt=\"\">\n";
        }
    }
}
?>

<h2>JVM Heap Usage</h2>
<img src="/<?=$oss?>/<?=$site?>/analysis/<?=$dir?>/export/mem.jpg" alt="">

<?php
include "common/finalise.php";
?>
