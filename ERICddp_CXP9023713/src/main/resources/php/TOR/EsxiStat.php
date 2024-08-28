<?php

$YUI_DATATABLE = true;
include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
function getESXIStatGraph($title,$whatcol)
{
    global $debug, $webargs,$date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");
    $row = array();
     $sqlParam =
               array( 'title' => $title,
                      'ylabel' => 'ms',
                      'type' => 'sb',
                      'useragg' => 'true',
                      'sb.barwidth' => '60',
                      'persistent' => 'true',
                      'forcelegend' => 'true',
                      'querylist' =>
                        array(
                             array(
                                   'timecol' => 'time',
                                   'multiseries' => 'servers.hostname',
                                   'whatcol' => $whatcol,
                                   'tables' => "enm_esxi_metrics,sites,servers",
                                   'where' => "enm_esxi_metrics.siteid = sites.id AND sites.name = '%s' AND enm_esxi_metrics.serverid=servers.id",
                                   'qargs' => array( 'site' )
                                  )
                            )
                     );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300);
        $graphTable->addRow($row);
        echo $graphTable->toHTML();
}

function mainFlow($statsDB,$webargs,$date) {
  global $debug;
  global $site;
  global $dir;
  global $date;
  getESXIStatGraph('CPU READY SUMMATION',array('cpu_ready_summation' => 'CPU Ready Summation'));
  getESXIStatGraph('CPU COSTOP SUMMATION',array('cpu_costop_summation' => 'CPU Costop Summation'));
}

$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
  $fromDate = $_GET['start'];
  $toDate = $_GET['end'];
} else {
  $fromDate = $date;
  $toDate = $date;
}

$webargs = "site=$site&dir=$dir&date=$fromDate&oss=$oss";

$row = $statsDB->query("SELECT DISTINCT servers.hostname FROM servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 servers.type = 'ESXI'");
if ($statsDB->getNumRows() > 0) {
    while($row = $statsDB->getNextRow())
    {
        $serverPageLink = "$php_webroot/server.php?site=$site&dir=$dir&date=$date&oss=$oss&server=$row[0]";
print <<<END
<p><a href="$serverPageLink">Server Stats - $row[0]</a><p>
END;
    }
}
drawHeaderWithHelp("ESXI", 1, "storageContentHelp");
mainFlow($statsDB,$webargs,$date);
$statsDB->disconnect();
include PHP_ROOT . "/common/finalise.php";

