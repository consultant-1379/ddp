<?php
$pageTitle = "SDM-G Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";

$plotdir = $rootdir . "/../../data/" . $dir . "/sdmg/plots";
$graphBase = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=sdmg/plots/";

function doGraph($index, $source, $desc) {
    if (! file_exists($source)) return;
    $input = file_get_contents($source);
    $input = explode("\n", $input);
    $data = array($desc => array());
    $startDate = "";
    $endDate = "";
    foreach ($input as $row) {
        // first characters are the year
        if (preg_match("/^20/", $row) != 1) continue;
        $row = explode(" ", $row);
        $endDate = join(" ", explode(":", $row[0], 2));
        if ($startDate == "") $startDate = $endDate;
        $data[$desc][$endDate] = $row[$index];
    }
    include "classes/Graph.class.php";
    $g = new Graph($startDate, $endDate, "hour");
    $g->addData($data);
    $g->type = "bar";
    //echo "<pre>\n";
    //print_r(array($startDate, $endDate, $data));
    //echo "</pre>\n";
    $g->display();
}

if (isset($_GET['chart'])) {
    if ($_GET['chart'] == "duration") {
        doGraph(2, $plotdir . "/sdmg_loading.txt", "Duration");
    } else if ($_GET['chart'] == "rows") {
        doGraph(1, $plotdir . "/sdmg_loading.txt", "Number Of Rows");
    }
    exit;
}

if (file_exists($rootdir . "/sdmg/rops_stored.html")) {
echo "<h2>ROPs Stored</h2>\n";
 include($rootdir . "/sdmg/rops_stored.html");
}
?>



<h2>Nodes Loaded Today</h2>
<img src="<?=$webroot?>/sdmg/nodes_loaded.jpg" alt="" >


<h2>Data waiting to be loaded</h2>

<p><img src="<?=$webroot?>/sdmg/rops_waiting.jpg" alt="" ></p>
<p><img src="<?=$webroot?>/sdmg/objects_waiting.jpg" alt="" ></p>

<H2>Loading time</H2>

<?php
$graph="loadtime.jpg";
if (file_exists($rootdir . "/sdmg/loadtime_hr.jpg") ) {
  $graph="loadtime_hr.jpg";
} else if (file_exists($rootdir . "/sdmg/loadtime.jpg")) {
    $graph = "loadtime.jpg";
  echo "<p>The height of each bar indicates the number of rows BCPed into the bufferdb. The width is the number of seconds taken to perform the load</p>\n";
}

echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdmg/$graph\" alt=\"\" >\n";
if (file_exists($plotdir . "/sdmg_loading.txt")) {
    echo "<img src=\"?" . $_SERVER['QUERY_STRING'] . "&chart=rows\" alt=\"Number of Rows\">\n";
    echo "<img src=\"?" . $_SERVER['QUERY_STRING'] . "&chart=duration\" alt=\"Duration\">\n";
    echo "<p><b>Click <a href=\"" . $graphBase . "sdmg_loading.txt\">here</a> to get a more detailed view (Java Plugin required)</b></p>";
}

if (file_exists($rootdir . "/sdmg/loadrow_hr.jpg") ) {
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdmg/loadrow_hr.jpg\" alt=\"\" >\n";
 }
?>

<p>The source for the graph above is the SDM_GRAN_dispatcher.log. If the poller id (from the line <b>Start  db_poller id</b>) is the same as the previous load cycle, then the start time of the current load cycle is taken as the same time as the end of the Loading from previous cycle. For example<p>
<pre>
Start  db_poller 507,CSD
Wed Nov 15 08:04:29 MET 2006. SDM BCP Started...
Wed Nov 15 08:06:08 MET 2006. SDM BCP Finished.
Wed Nov 15 08:06:09 MET 2006. SDM Loading Started... - for GSM 
Wed Nov 15 08:06:09 MET 2006. SDM sdm_csdLoad Executing...
Wed Nov 15 08:29:00 MET 2006. SDM Loading Finished.
Start  db_poller 507,CSD
Wed Nov 15 08:31:21 MET 2006. SDM BCP Started...
Wed Nov 15 08:32:58 MET 2006. SDM BCP Finished.
Wed Nov 15 08:32:59 MET 2006. SDM Loading Started... - for GSM 
Wed Nov 15 08:32:59 MET 2006. SDM sdm_csdLoad Executing...
Wed Nov 15 08:49:46 MET 2006. SDM Loading Finished.
Start  db_poller 507,CSD
</pre>
<p>The duration of the second load cycle is considered to be from <i>Wed Nov 15 08:29:00 MET 2006</i> to <i>Wed Nov 15 08:49:46 MET 2006</i></p>

<p>This a work in progress, there are some problem with missing lines in the dispatcher log which are causing invalid measurements</p>

<?php
if (file_exists($rootdir . "/sdmg/CELL_agg.jpg") ) {
  echo "<H2>Aggregation</H2>\n";
  echo "<p>Number of un-aggregated ROPs, i.e. the number of RES 0 rops newer the the latest RES 1 rop</p>\n";
  echo "<img src=\"/" . $oss . "/" . $site . "/analysis/" . $dir . "/sdmg/CELL_agg.jpg\" alt=\"\" >\n";
 }

if (file_exists($rootdir . "/sdmg/delete.html")) {
echo "<H2>Delete</H2>\n";
include($rootdir . "/sdmg/delete.html");
}
include "common/finalise.php";
?>

