<?php
$pageTitle = "Backlog Analysis";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class BacklogMonitoringTable extends DDPObject {
    var $cols = array(
        'interface'       => 'Interface Name',
        'file_in_process' => 'Files Processed'
    );

    var $title = "Backlog Monitoring";
    var $interfaceName = "";

    function __construct() {
        parent::__construct("Instance");
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        $sql = "
            SELECT
             frh_interface.frhInterface as interface,
             sum(totalBacklog) as totalBacklog,
             sum(filesInProcess) as file_in_process
            FROM
             frh_controller_backlog,sites,frh_interface
            WHERE
             frh_controller_backlog.time
            BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             frh_controller_backlog.interfaceId = frh_interface.id AND
             sites.name = '$site' AND
             sites.id = frh_controller_backlog.siteid
            GROUP BY
             interfaceId
        ";
        $this->populateData($sql);

    $hyperlinkdata = array();
    foreach ($this->data as $key => $row) {
        $interfaceName = $row['interface'];
        $row['interface'] = "<a href='?" . $webargs. "&interface=" . $interfaceName . "'>" . $interfaceName ."</a>";
        $hyperlinkdata[] = $row;
    }
        $this->data = $hyperlinkdata;
        return $this->data;
    }
}
$fromDate = $date;
$toDate = $date;

if ( isset($_GET['start']) ) {
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
}
if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}

$backlogAnalysisHelp = <<<EOT
Backlog Analysis involves monitoring the number of files processed and the backlog for each interface on ENIQ STATS.
EOT;
drawHeaderWithHelp("Backlog Analysis", 1, "backlogAnalysisHelp", $backlogAnalysisHelp);

$statsDB = new StatsDB();

$interfacesFilesBacklog = getTopSixInterfaces("filesInBacklog");
$interfacesFilesProcessed = getTopSixInterfaces("filesInProcess");

$backlogFileIntfId  = implode(",", $interfacesFilesBacklog);
$processedFileIntfId = implode(",", $interfacesFilesProcessed);

function getTopSixInterfaces($file_type) {

    global $date;
    global $site;

    $statsDB = new StatsDB();

    $row = $statsDB->query("
        SELECT
         frh_controller_backlog.interfaceId,
         sum($file_type) as sumofday
        FROM
         frh_controller_backlog, sites, frh_interface
        WHERE
         frh_controller_backlog.time
        BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
         frh_controller_backlog.siteid = sites.id AND
         sites.name = '$site' AND
         frh_interface.id = frh_controller_backlog.interfaceId
        GROUP BY
         interfaceId
        ORDER BY
         sumofday
        DESC limit 6
        ");

    $interfacesProcess = array();

    while ( $row = $statsDB->getNextRow() ) {
        $interfaces_process[] = $row[0];
    }
    return $interfaces_process;
}

$graphs = array(
    'filesInProcess' => array(
        'title'  => 'Files Processed',
        'type'   => 'sb',
        'preset' => 'SUM:Hourly'
    ),
    'filesInBacklog' => array(
        'title'  => 'Files in backlog',
        'type'   => 'tsc',
        'preset' => 'SUM:Per Minute'
    )
);

if ( isset($_GET['interface']) && $_GET['interface'] != "" ) {
  $interfaceName = $_GET['interface'];
   echo "<h2>$interfaceName</h2>\n";
 foreach ($graphs as $columnName => $graphProperties) {
    printGraphInstancewise($columnName, $graphProperties['title'], $graphProperties['type'], $graphProperties['preset']);
}
$interfaceName = "";
}

$dailyStatsHelp = <<<EOT
The following information is presented:
<ul>
  <li><b>Interface Name:</b> Name of the interface.</li>
  <li><b>Files Processed:</b> Total number of files processed by the interface.</li>
</ul>
EOT;
drawHeaderWithHelp("Daily Totals", 2, 'dailystat', $dailyStatsHelp);
$dailyTotals = new BacklogMonitoringTable();
$dailyTotals->getSortableHtmlTable();

$interfaceStatsHelp = <<<EOT
The graph shows overall files processed and backlog for all the interfaces:
<ul>
  <li><b>Files Processed:</b>Total number of files processed.</li>
  <li><b>Files in backlog:</b>Total number of files in the backlog.</li>
</ul>
EOT;
drawHeaderWithHelp("Interface Statistics", 2, 'interfaceStatsHelp', $interfaceStatsHelp);

foreach ($graphs as $columnName => $graphProperties) {
    printGraphInstancewise($columnName, $graphProperties['title'], $graphProperties['type'], $graphProperties['preset']);
}


$topSixInterfaceStatsHelp = <<<EOT
This graph shows the top six interfaces which have the highest number of files processed and their corresponding backlog total:
<ul>
  <li><b>Files Processed:</b>Total number of files processed.</li>
  <li><b>Files in backlog:</b>Total number of files in the backlog.</li>
</ul>
EOT;
drawHeaderWithHelp("Top Six Interface Statistics", 2, 'topSixInterfaceStatsHelp', $topSixInterfaceStatsHelp);

$topSixInterfacesGraphs = array(
    'filesInProcess' => array(
        'title'        => 'Files Processed',
        'type'         => 'sb',
        'preset'       => 'SUM:Hourly',
        'fileIntfId' => $processedFileIntfId
    ),
    'filesInBacklog' => array(
        'title'        => 'Files in backlog',
        'type'         => 'tsc',
        'preset'       => 'SUM:Per Minute',
        'fileIntfId' => $backlogFileIntfId
    )
);


foreach ($topSixInterfacesGraphs as $columnName => $graphProperties) {
    printGraphInstancewise($columnName, $graphProperties['title'], $graphProperties['type'], $graphProperties['preset'], $graphProperties['fileIntfId']);
}

function printGraphInstancewise($column, $title, $type, $preset, $intfId) {
    $sqlParamWriter = new SqlPlotParam();
    global $date;
    global $interfaceName;

    $sqlParam = array(
        'title'       => $title,
        'ylabel'      => $title,
        'useragg'     => 'true',
        'persistent'  => 'false',
        'presetagg'   => $preset,
        'sb.barwidth' => 3600,
        'type'        => $type,
        'querylist'   => array()
    );

    if($interfaceName != "" ) {
            $sqlParam['querylist'][] = array(
            'timecol'     => 'time',
            'whatcol'     => array( $column => $title ),
            'tables'      => "frh_controller_backlog,sites,frh_interface",
            'where'       => "frh_interface.id = frh_controller_backlog.interfaceId AND
                             frh_controller_backlog.siteid = sites.id AND sites.name = '%s' AND
                             frh_interface.frhInterface = '$interfaceName'",
            'qargs'       => array( 'site' )
        );
  } else {
      if(isset($intfId)) {
        $sqlParam['querylist'][] = array(
            'timecol'     => 'time',
            'multiseries' => 'frh_interface.frhInterface',
            'whatcol'     => array( $column => $title ),
            'tables'      => "frh_controller_backlog,sites,frh_interface",
            'where'       => "frh_interface.id = frh_controller_backlog.interfaceId AND
                             frh_controller_backlog.siteid = sites.id AND sites.name = '%s' AND
                             frh_interface.id IN ($intfId)",
            'qargs'       => array( 'site' )
        );
    } else {
        $sqlParam['querylist'][] = array(
            'timecol'     => 'time',
            'whatcol'     => array( $column => $title ),
            'tables'      => "frh_controller_backlog,sites,frh_interface",
            'where'       => "frh_interface.id = frh_controller_backlog.interfaceId AND
                             frh_controller_backlog.siteid = sites.id AND sites.name = '%s'",
            'qargs'       => array( 'site' )
        );
    }
}
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url =  $sqlParamWriter->getImgURL($id,"$date 00:00:00", "$date 23:59:59",true, 640, 240);
    echo "$url<br><br><br>";
}

include "../common/finalise.php";
?>