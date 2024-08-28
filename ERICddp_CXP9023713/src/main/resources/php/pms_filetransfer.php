<?php
if (isset($_GET['chart'])) $UI = false;
require_once 'HTML/Table.php';

$pageTitle = "PMS File Transfers";
include "common/init.php";

require_once "classes/PmsFiletransfer.php";

$statsDB = new StatsDB();

if (isset($_GET['chart'])) {
    $chart = "totalkb";
    if ($_GET['chart'] == "totalkb" || $_GET['chart'] == "minthroughput" || $_GET['chart'] == "avgthroughput")
        $chart = $_GET['chart'];

    $type = "RNC";
    if ( isset($_GET['netype']) ) {
      $type = $_GET['netype'];
    }

    $headings = array(
        "totalkb" => "Total (Kb)", "avgthroughput" => "Avg. Throughput (Bytes)", "minthroughput" => "Min. Throughput (Bytes)"
    );

    $aggFunc = array ("totalkb"       => "SUM",
		      "minthroughput" => "MIN",
		      "avgthroughput" => "AVG");
		      
    $data = array($headings[$chart] => array());
    $aggFunction = $aggFunc[$chart];
    $statsDB->query("
SELECT time, $aggFunction($chart) AS $chart
FROM pms_filetransfer_rop, sites, ne_types WHERE
 pms_filetransfer_rop.siteid = sites.id AND sites.name = '$site' AND
 pms_filetransfer_rop.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 pms_filetransfer_rop.netypeid = ne_types.id AND ne_types.name = '$type'
GROUP BY time ORDER BY time");
    while ($row = $statsDB->getNextNamedRow()) {
        $data[$headings[$chart]][$row['time']] = $row[$chart];
    }
    include "classes/Graph.class.php";
    $g = new Graph($date . " 00:00:00", $date . " 23:59:59", "hour");
    //$g = new Graph(0,0,"min");
    //$g = new Graph();
    $g->barWidth = 2;
    $g->addData($data);
    $g->setType("bar");
    $g->display();
    //echo $sql . "<br/>\n";
    //$g->printData();
    exit;
}


?>

<h1>PMS File Transfer Statistics</h1>
<a href="<?=$php_webroot?>/pms.php?site=<?=$site?>&dir=<?=$dir?>&date=<?=$date?>&oss=oss">Back to PMS Statistics</a>
<h2><?php drawHelpLink("helpftxnode"); ?>File Transfer Stats Per Node</h2>
<?php
drawHelp("helpftxnode", "File Transfer Stats Per Node",
    "
File transfer stats per node are parsed from file transfer data collected by PMS.
This data is collected from
<p />
<code>/var/opt/ericsson/nms_umts_pms_seg/data/ftpDataOutput.txt</code>
<p />
");

$filter = "";
if (isset($_GET['filter_filetype']) && $_GET['filter_filetype'] != "") {
  $filter = $filter . " AND pms_filetransfer_node.filetype = '" . $statsDB->escape($_GET['filter_filetype']) . "'";
}

$pmsFtxTbl = new PmsFiletransfer($filter);
$pmsFtxTbl->getSortableHtmlTable();

echo "<form name=syncfilter action=\"" . $_SERVER['PHP_SELF'] . "\" method=get>\n";
foreach ($_GET as $key => $val) {
  if ($key == "filter_filetype" || $key == "submit") continue;
  echo "<input type=hidden name='" . $key . "' value='" . $val . "' />\n";
}
echo "<h3>Filter:</h3>\n";
echo "File Type: <input type=text name=filter_filetype value=\"" . $_GET['filter_filetype'] . "\" />\n";
echo "<input type=submit name=submit value=\"Submit ...\" />\n";
echo "</form>\n";

include "common/finalise.php";
?>
