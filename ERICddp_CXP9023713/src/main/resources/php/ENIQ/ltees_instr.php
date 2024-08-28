<?php
$pageTitle = "LTE ES Instrumentation";
$YUI_DATATABLE = true;
include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class CounterWf extends DDPObject {
  var $cols = array(
            'wfName'         => 'Workflow',
            'Files'          => 'Files',
            'Events'         => 'Events',
            'gb'             => 'Volume (GB)',
            'FilesToArchive' => 'Files Archived',
            'ProcessedCount' => 'Counter Files Generated',
            'Delay'          => 'Average Processing Delay'
            );

    var $title = "Counter Workflow";

    function __construct() {
        parent::__construct("lteesCounter");
    }

    function getData() {
        global $date;
        global $site;
    $sql = "
        SELECT
            IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
            FORMAT(SUM(eniq_ltees_counter.Files),0) AS Files,
            FORMAT(SUM(eniq_ltees_counter.Events),0) AS Events,
            ROUND(SUM(eniq_ltees_counter.Bytes)/(1024*1024*1024),2) AS gb,
            FORMAT(SUM(eniq_ltees_counter.FilesToArchive),0) AS FilesToArchive,
            FORMAT(SUM(eniq_ltees_counter.ProcessedCount),0) AS ProcessedCount,
            SEC_TO_TIME(AVG(eniq_ltees_counter.Delay/eniq_ltees_counter.Files)) AS Delay
        FROM
            eniq_ltees_counter, eniq_workflow_names, sites
        WHERE
            eniq_ltees_counter.siteid = sites.id AND sites.name = '$site' AND
            eniq_ltees_counter.wfid = eniq_workflow_names.id AND
            eniq_ltees_counter.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        GROUP BY
            eniq_workflow_names.name WITH ROLLUP
        ";
    $this->populateData($sql);
    return $this->data;
    }
}

if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}
if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}

echo "<H1>LTE-ES Counter</H1>\n";

echo "<H2>Daily Totals</H2>\n";
$counterTotalTable = new CounterWf();
echo $counterTotalTable->getHtmlTableStr();

echo "<H2>Hourly Totals</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table('border=0');

$params =
  array(
        'Files'          => array('title' => 'Files Consumed'),
        'Events'         => array('title' => 'Events Consumed'),
        'Bytes'          => array('title' => 'Volume Consumed (GB)', 'ylabel' => 'GB', 'counterMod' => '/(1024*1024*1024)'),
        'ProcessedCount' => array('title' => 'Counter Files Generated', 'ylabel' => 'Files'),
        'Delay'          => array('title' => 'Average Processing Delay', 'ylabel' => 'Secs', 'counterMod' => '/Files', 'agg' => 'AVG')
    );

foreach ( array('Files','Events','Bytes','ProcessedCount','Delay') as $counterType ) {
    $thisGraphParams = array(
               'title'      => $counterType,
               'ylabel'     => $counterType,
               'counterMod' => "",
               'agg'        => 'SUM'
               );
    foreach ($thisGraphParams as $key => $value) {
        if ( array_key_exists($key, $params[$counterType]) ) {
            $thisGraphParams[$key] = $params[$counterType][$key];
    }
  }

    $sqlParam =
        array(
            'title'       =>  $thisGraphParams['title'],
            'ylabel'      =>  $thisGraphParams['ylabel'],
            'type'        =>  'sb',
            'sb.barwidth' =>  '3600',
            'presetagg'   =>  $thisGraphParams['agg'] . ":Hourly",
            'persistent'  =>  'false',
            'useragg'     =>  'false',
            'querylist'   =>
                            array(
                                array(
                                    'timecol' => 'time',
                                    'whatcol' => array( $counterType . $thisGraphParams['counterMod'] => $counterType ),
                                    'tables'  => "eniq_ltees_counter, sites",
                                    'where'   => "eniq_ltees_counter.siteid = sites.id AND sites.name = '%s'",
                                    'qargs'   => array( 'site' )
                                )
                            )
        );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}
echo $graphs->toHTML();

class LteesTopology extends DDPObject {
    var $cols;
    function __construct() {
        parent::__construct("LteesTopology");
    }
    function getData() {
        global $site;
        global $requiredColumn;
        global $table;
        global $additionalWhereClause;
        unset($this->data);
        $sql = "
            SELECT
                $requiredColumn
            FROM
                $table, eniq_workflow_names, sites
            WHERE
                sites.name = '$site' AND
                $additionalWhereClause
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

$requiredColumn = "TIME(eniq_ltees_topology.time) AS LastUpdatedTime,
                   eniq_workflow_names.name AS wfName,
                   eniq_ltees_topology.NoOfEnodeB AS NoOfEnodeB,
                   eniq_ltees_topology.NoOfCells AS NoOfCells,
                   eniq_ltees_topology.NoOfExtCells AS NoOfExtCells,
                   eniq_ltees_topology.NoOfEutranCellRelations AS NoOfEutranCellRelations,
                   eniq_ltees_topology.NoOfUtranCellRelations AS NoOfUtranCellRelations,
                   eniq_ltees_topology.NoOfGeranCellRelations AS NoOfGeranCellRelations,
                   eniq_ltees_topology.NoOfExtEUtranCells AS NoOfExtEUtranCells,
                   eniq_ltees_topology.NoOfExtUtranCells AS NoOfExtUtranCells,
                   eniq_ltees_topology.NoOfExtGeranCells AS NoOfExtGeranCells";
$table = "eniq_ltees_topology";
$additionalWhereClause = "eniq_ltees_topology.siteid = sites.id AND
                          eniq_ltees_topology.wfid = eniq_workflow_names.id AND
                          time IN   (
                                    SELECT  MAX(time) FROM
                                        $table, eniq_workflow_names, sites
                                    WHERE
                                        sites.name = '$site' AND
                                        eniq_ltees_topology.siteid = sites.id AND
                                        eniq_ltees_topology.wfid = eniq_workflow_names.id AND
                                        eniq_ltees_topology.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
                                    GROUP BY
                                        eniq_workflow_names.name
                                    )
                                    GROUP BY
                                        eniq_workflow_names.name";
$LteesTopology = new LteesTopology();
$LteesTopology->cols = array(
                                'LastUpdatedTime'         => 'Last Updated Time',
                                'wfName'                  => 'Workflow',
                                'NoOfEnodeB'              => 'Number of EnodeBs',
                                'NoOfCells'               => 'Total Cells',
                                'NoOfExtCells'            => 'External Cells',
                                'NoOfEutranCellRelations' => 'Eutran CellRelations',
                                'NoOfUtranCellRelations'  => 'Utran CellRelations',
                                'NoOfGeranCellRelations'  => 'Geran CellRelations',
                                'NoOfExtEUtranCells'      => 'External EUtran Cells',
                                'NoOfExtUtranCells'       => 'External Utran Cells',
                                'NoOfExtGeranCells'       => 'External Geran Cells'
                            );
echo "<br>";
echo "<H1>LTE-ES Topology</H1>\n";
$LttesTableHelp = <<<EOT
The Daily Full Topology table shows below information per OSSRC workflow across the day.
    <ul>
    <li><b>Number of EnodeBs: Total number of eNodeBs in the loaded topology</li>
    <li><b>Total Cells: Total number of internal cells of all the eNodeBs</li>
    <li><b>External Cells: Total number of external cells</li>
    <li><b>Eutran CellRelations: Total number of EUtran cell relations</li>
    <li><b>Utran CellRelations: Total number of Utran cell relations</li>
    <li><b>Geran CellRelations: Total number of Geran cell relations</li>
    <li><b>External EUtran Cells: Total number of external EUtran cells</li>
    <li><b>External Utran Cells: Total number of external Utran cells</li>
    <li><b>External Geran Cells: Total number of external Geran cells</li>
    </ul>
EOT;
drawHeaderWithHelp("Daily Full Topology", 2, "LttesTableHelp", $LttesTableHelp);
echo $LteesTopology->getHtmlTable();
echo "<br><br>";

$requiredColumn = "IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
                   FORMAT(SUM(eniq_delta_topology.files),0) AS files,
                   MAX(eniq_delta_topology.files) AS MAX,
                   ROUND(AVG(eniq_delta_topology.files)) AS Average";
$table = "eniq_delta_topology";
$additionalWhereClause = "eniq_delta_topology.siteid = sites.id AND
                          eniq_delta_topology.wfid = eniq_workflow_names.id AND
                          eniq_delta_topology.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
                          GROUP BY
                          eniq_workflow_names.name with ROLLUP";
$LteesTopology->cols = array(
                                'wfName'  => 'Workflow',
                                'files'   => 'Total processed Files',
                                'MAX'     => 'Max Processed Files per ROP',
                                'Average' => 'Average Processed Files per ROP'
                            );
$deltaTableHelp = <<<EOT
The below table shows the total number of delta topology updates received along with the max and avg number of updates throughout a day per Workflow.
EOT;
drawHeaderWithHelp("Daily Delta Topology", 2, "deltaTableHelp", $deltaTableHelp);
$LteesTopology->getData();
echo $LteesTopology->getClientSortableTableStr();

echo "<br><br>";
$deltaGraphHelp = <<<EOT
    The below graph shows the per minute sum of processed files throughout a day per Workflow.
EOT;
drawHeaderWithHelp("Delta Topology processed files", 2, "deltaGraphHelp", $deltaGraphHelp);

        $sqlParam = array(
                        'title'      => 'Processed Files',
                        'ylabel'     => 'Number of files',
                        'useragg'    => 'true',
                        'persistent' => 'true',
                        'presetagg'  => 'SUM:Per Minute',
                        'type'       => 'tsc',
                        'querylist'  => array(
                                            array(
                                            'timecol'     => 'time',
                                            'multiseries' => 'eniq_workflow_names.name',
                                            'whatcol'     =>  array('files' => 'Processed Files'),
                                            'tables'      => "eniq_delta_topology, eniq_workflow_names, sites",
                                            'where'       => "eniq_delta_topology.siteid = sites.id AND
                                                              eniq_delta_topology.wfid = eniq_workflow_names.id AND
                                                              sites.name = '%s'",
                                            'qargs'       =>  array('site')
                                            )
                                        )
                    );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $url = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240);
        echo "$url<br><br><br>";

include "../common/finalise.php";
?>
