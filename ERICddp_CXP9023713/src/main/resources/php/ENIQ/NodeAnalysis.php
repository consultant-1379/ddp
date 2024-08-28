<?php
$pageTitle = "Node Analysis";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";

if ( isset($_GET['node']) && $_GET['node'] != "" ) {
   $node = $_GET['node'];
}

function getNodeGraph($title, $ylabel, $whatCol) {
    $sqlParamWriter = new SqlPlotParam();
    global $site;
    global $node;
    global $date;
    $sqlParam = array(
        'title'      => $title,
        'ylabel'     => $ylabel,
        'persistent' => 'true',
        'useragg'    => 'true',
        'type'       => 'sb',
        'querylist'  => array(
                            array(
                                'timecol' => 'start_time',
                                'whatcol' => $whatCol,
                                'tables'  => "sim_stats, sim_node, sites",
                                'where'   => "sim_node.node = '$node' and sim_stats.nodeid = sim_node.id and sim_stats.siteid = sites.id AND
                                             sites.name= '%s'",
                                'qargs'   => array('site')
                            )
                        )
    );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240);
    echo "$url<br><br><br>";
}

$pageHelp = <<<EOT
This page displays graphs representing the following information:
<p>
<ol>
    <li><b>Number of files collected per ROP.</b></li>
    <li><b>Processing latency per ROP.</b></li>
</ol>
EOT;
drawHeaderWithHelp($node, 1, "pageHelp", $pageHelp);

getNodeGraph('Files Collected', 'Files Collected', array('no_of_files' => 'Files Collected'));
echo "<p>";
getNodeGraph('Processing Latency', 'Time(sec)', array('TIME_TO_SEC(TIMEDIFF(stop_time,start_time))' => 'Seconds'));
include "../common/finalise.php";
?>