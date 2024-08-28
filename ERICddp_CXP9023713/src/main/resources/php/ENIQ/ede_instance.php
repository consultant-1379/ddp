<?php
$pageTitle = "EDE Instrumentation";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

$statsDB = new StatsDB();
if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}
class EDE extends DDPObject {
    var $cols = array(
        'event_name'                    => 'Event name',
        'event_count'                   => 'Event Count',
        'percentage_event_distribution' => 'Percentage Distribution'
    );
    var $defaultOrderBy = "event_count";
    var $defaultOrderDir = "DESC";
    function __construct() {
        parent::__construct("ede");
    }

    function getData() {
        global $date, $site, $source, $instance;
        $sql = "
            SELECT
             event_name, sum(event_count) as event_count,
             (sum(event_count)/sum(total_events_count)*100) as percentage_event_distribution
            FROM
             ede_event_distribution_details, sites, data_source_id_mapping, ede_event_name_id_mapping
            WHERE
             ede_event_distribution_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             ede_event_distribution_details.siteid = sites.id AND sites.name = '$site' AND
             data_source_id_mapping.data_source = '$source' AND
             ede_event_distribution_details.ede_instance = '$instance' AND
             ede_event_distribution_details.event_id = ede_event_name_id_mapping.id AND
             ede_event_distribution_details.data_source_id = data_source_id_mapping.id
            GROUP BY event_name
        ";
        $this->populateData($sql);
        return $this->data;
    }
}
$source = $_GET['source'];
$instance = $_GET['instance'];
$row = $statsDB->query("
           SELECT
            DISTINCT ede_instance as instance
           FROM ede_output_csv_log_details, data_source_id_mapping, sites
           WHERE
            ede_output_csv_log_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ede_output_csv_log_details.siteid = sites.id AND sites.name = '$site' AND
            ede_output_csv_log_details.data_source_id = data_source_id_mapping.id and
            data_source_id_mapping.data_source = '$source'
           UNION
           SELECT
            DISTINCT ede_instance as instance
           FROM ede_controller, data_source_id_mapping, sites
           WHERE
            ede_controller.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ede_controller.siteid = sites.id AND sites.name = '$site' AND
            data_source_id_mapping.data_source = '$source' AND
            ede_controller.data_source_id = data_source_id_mapping.id
           UNION
           SELECT
            DISTINCT ede_instance as instance
           FROM ede_event_distribution_details, data_source_id_mapping, sites
           WHERE
            ede_event_distribution_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ede_event_distribution_details.siteid = sites.id AND sites.name = '$site' AND
            data_source_id_mapping.data_source = '$source' AND
            ede_event_distribution_details.data_source_id = data_source_id_mapping.id
           UNION
           SELECT
            DISTINCT ede_instance as instance
           FROM ede_linkfile_details, data_source_id_mapping, sites
           WHERE
            ede_linkfile_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ede_linkfile_details.siteid = sites.id AND sites.name = '$site' AND
            data_source_id_mapping.data_source = '$source' AND
            ede_linkfile_details.data_source_id = data_source_id_mapping.id
           ORDER BY instance
       ");
if ($row = $statsDB->getNumRows() > 0) {
    while ($row = $statsDB->getNextNamedRow()) {
        echo '<li><a href=?' . $webargs . "&source=" . $source . "&instance=" . $row['instance'] . ">" . $row['instance'] . "</a></li>\n";
    }
}
$ropDuration = $statsDB->query("
                   Select
                    rop
                   FROM
                    ede_output_csv_log_details, sites, data_source_id_mapping
                   WHERE
                    siteid = sites.id AND
                    time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                    data_source_id = data_source_id_mapping.id AND
                    ede_instance = '$instance' AND
                    data_source_id_mapping.data_source = '$source' AND
                    sites.name = '$site'
                   ORDER BY rop
                   LIMIT 2
               ");
$time_data = array();
while ( $ropDuration = $statsDB->getNextNamedRow() ) {
    $time_data[] = $ropDuration['rop'];
}
$time_diff = ( strtotime($time_data[1]) - strtotime($time_data[0]) )/60;
$numberOfNEs = $statsDB->queryRow("
                   Select
                    nodeCount
                   FROM
                    ede_controller_node_count, sites, data_source_id_mapping
                   WHERE
                    time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                    data_source_id = data_source_id_mapping.id AND
                    ede_instance = '$instance' AND
                    data_source_id_mapping.data_source = '$source' AND
                    siteid = sites.id AND
                    sites.name = '$site'
               ");
$colOfOutputCsvLog = array("max(event_count)", "round(avg(event_count), 0)", "round(max(file_size), 1)", "round(avg(file_size), 1)", "max(file_count)", "round(avg(file_count), 0)");
$maxAvgOfOutputCsvLog = calculateMaxAverage("ede_output_csv_log_details", $colOfOutputCsvLog);
$colOfController = array("round(max(TIME_TO_SEC(TIMEDIFF(time, rop))), 2)", "round(avg(TIME_TO_SEC(TIMEDIFF(time, rop))), 2)");
$maxAvgOfStreamLatency = calculateMaxAverage("ede_controller", $colOfController);
$colOfLinkFile = array("round(max(TIME_TO_SEC(TIMEDIFF(time, rop))), 2)", "round(avg(TIME_TO_SEC(TIMEDIFF(time, rop))), 2)");
$maxAvgOfFileLatency = calculateMaxAverage("ede_linkfile_details", $colOfLinkFile);

function calculateMaxAverage($dataTable, $col) {
    $cols = implode(",", $col);
    global $source, $date, $instance, $site, $statsDB;
    $rowDataValue = $statsDB->queryRow("
                        Select
                         $cols
                        FROM
                         $dataTable, sites, data_source_id_mapping
                        WHERE
                         time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                         data_source_id = data_source_id_mapping.id AND
                         ede_instance = '$instance' AND
                         data_source_id_mapping.data_source = '$source' AND
                         siteid = sites.id AND
                         sites.name = '$site'
               ");
    return $rowDataValue;
}

function getEDEgraph($title, $ylabel, $whatColumn, $plottype) {
    global $date;
    global $source;
    global $instance;
    $sqlParam = array(
        'title'      => $title,
        'ylabel'     => $ylabel,
        'useragg'    => 'true',
        'persistent' => 'true',
        'type'       => 'sb',
        'querylist'  => array()
    );
    if ($plottype == 'stream') {
        $sqlParam['querylist'][] = array(
            'timecol' => 'rop',
            'whatcol' => $whatColumn,
            'tables'  => "ede_controller, data_source_id_mapping, sites",
            'where'   => "ede_controller.siteid = sites.id AND
                          ede_controller.data_source_id = data_source_id_mapping.id AND
                          ede_controller.ede_instance = '$instance' AND
                          data_source_id_mapping.data_source = '$source' AND
                          sites.name = '%s'",
            'qargs'   => array('site')
        );
    }
    elseif ($plottype == 'file') {
        $sqlParam['querylist'][] = array(
            'timecol' => 'rop',
            'whatcol' => $whatColumn,
            'tables'  => "ede_linkfile_details, data_source_id_mapping, sites",
            'where'   => "ede_linkfile_details.siteid = sites.id AND
                          ede_linkfile_details.data_source_id = data_source_id_mapping.id AND
                          ede_linkfile_details.ede_instance = '$instance' AND
                          data_source_id_mapping.data_source = '$source' AND
                          sites.name = '%s'",
            'qargs'   => array('site')
        );
    }
    else {
        $sqlParam['querylist'][] = array(
            'timecol' => 'time',
            'whatcol' => $whatColumn,
            'tables'  => "ede_output_csv_log_details, data_source_id_mapping, sites",
            'where'   => "ede_output_csv_log_details.siteid = sites.id AND
                          ede_output_csv_log_details.data_source_id = data_source_id_mapping.id AND
                          data_source_id_mapping.data_source = '$source' AND
                          ede_output_csv_log_details.ede_instance = '$instance' AND
                          sites.name = '%s'",
            'qargs'   => array('site')
        );
    }
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240);
    echo "$url<br><br><br>";
}
if ( isset($_GET['instance']) && $_GET['instance'] != "" ) {
    global $instance;
    $pageHelp = <<<EOT
    This page display graphs representing the following information:
    <p>
    <ol>
        <li>Node Summary.</li>
        <li>Event Count Per ROP across the network.</li>
        <li>File Size per ROP.</li>
        <li>Number of Files per ROP.</li>
        <li>Stream and File based Latency.</li>
        <li>Event Distribution across the network.</li>
    </ol>
EOT;
    drawHeaderWithHelp($instance . " Statistics for " . $source . "", 1, "pageHelp", $pageHelp);
    $nodeHelp = <<<EOT
    The below table shows the number of distinct Node and ROP duration per source.
    <p>
    <b>Note:</b> If FDN logs are not enabled the Number of NEs displayed will be zero.
EOT;
    drawHeaderWithHelp("Node Summary", 2, "nodeHelp", $nodeHelp);
    $table = new HTML_Table("border=1");
    $table->addRow( array('Number of Nodes', $numberOfNEs[0]) );
    $table->addRow( array('ROP Duration in Minutes', $time_diff) );
    echo $table->toHTML();
    $eventHelp = <<<EOT
    The below table shows the Maximum and Average value of Event count per ROP across the Network.
    <p>
    The below graph shows the information of Event count per ROP across the Network.
EOT;
    drawHeaderWithHelp("Event Count per ROP Across Network", 2, "eventHelp", $eventHelp);
    $table = new HTML_Table("border=1");
    $table->addRow( array('Max Event Count', $maxAvgOfOutputCsvLog[0]) );
    $table->addRow( array('Average Event Count', $maxAvgOfOutputCsvLog[1]) );
    echo $table->toHTML();
    echo "<br>";
    getEDEgraph('Event Count per ROP', 'Number of Events', array('event_count' => 'Event Count'));
    $fileHelp = <<<EOT
    The below table shows the Maximum and Average value of File Size per ROP.
    <p>
    The below graph shows the information of File size per ROP.
EOT;
    drawHeaderWithHelp("File Size Per ROP", 2, "fileHelp", $fileHelp);
    $table = new HTML_Table("border=1");
    $table->addRow( array('Max File Size (in MB)', $maxAvgOfOutputCsvLog[2]) );
    $table->addRow( array('Avg File Size (in MB)', $maxAvgOfOutputCsvLog[3]) );
    echo $table->toHTML();
    echo "<br>";
    getEDEgraph('File size per ROP', 'File size(MB)', array('file_size' => 'File Size'));
    $fileNumberHelp = <<<EOT
    The below table shows the Maximum and Average value of Number of Files per ROP.
    <p>
    The below graph shows the information of Number of Files per ROP.
EOT;
    drawHeaderWithHelp("Number of Files per ROP", 2, "fileNumberHelp", $fileNumberHelp);
    $table = new HTML_Table("border=1");
    $table->addRow( array('Max Number of Files', $maxAvgOfOutputCsvLog[4]) );
    $table->addRow( array('Avg Number of Files', $maxAvgOfOutputCsvLog[5]) );
    echo $table->toHTML();
    echo "<br>";
    getEDEgraph('Number of Files per ROP', 'Number of Files', array('file_count' => 'File Count'));
    $latencyHelp = <<<EOT
    It helps to monitor the EDE data latency into EE system per ROP across all the nodes simulated per source node type to check for any delays in the ROP.
EOT;
    drawHeaderWithHelp("Latency", 1, "latencyHelp", $latencyHelp);
    $streamLatencyHelp = <<<EOT
    The below table shows the Maximum and Average value of delay(Stream end Time-ROP End Time) in the ROP Streaming across all the nodes simulated per source.
    <p>
    The below graph helps to monitor any delay(Stream End Time-ROP End Time) in the ROP Streaming across all the nodes simulated per source.
EOT;
    drawHeaderWithHelp("Stream Based Latency", 2, "streamLatencyHelp", $streamLatencyHelp);
    $table = new HTML_Table("border=1");
    $table->addRow( array('Max Latency (in secs)', $maxAvgOfStreamLatency[0]) );
    $table->addRow( array('Avg Latency (in secs)', $maxAvgOfStreamLatency[1]) );
    echo $table->toHTML();
    echo "<br>";
    getEDEgraph('Stream Based Latency', 'Streaming Latency(seconds)', array('TIME_TO_SEC(TIMEDIFF(time,rop))' => 'Seconds'), "stream");
    $fileLatencyHelp = <<<EOT
    The below table shows the Maximum and Average value of Symlink creation delay(Symlink creation Time - ROP End Time) across all the nodes simulated per source.
    <p>
    The below graph helps to monitor any Symlink creation delay(Symlink Creation Time - ROP Time) across all the nodes simulated per source.
EOT;
    drawHeaderWithHelp("File Based Latency", 2, "fileLatencyHelp", $fileLatencyHelp);
    $table = new HTML_Table("border=1");
    $table->addRow( array('Max Latency (in secs)', $maxAvgOfFileLatency[0]) );
    $table->addRow( array('Avg Latency (in secs)', $maxAvgOfFileLatency[1]) );
    echo $table->toHTML();
    echo "<br>";
    getEDEgraph('File Based Latency', 'File Latency(seconds)', array('TIME_TO_SEC(TIMEDIFF(time,rop))' => 'Seconds'), "file");
    $eventDistributionHelp = <<<EOT
    The below table shows the distribution of Events Across Network per source.
EOT;
    drawHeaderWithHelp("Event Distribution Across Network", 2, "eventDistributionHelp", $eventDistributionHelp);
    $ede = new EDE();
    echo $ede->getClientSortableTableStr();
    $statsDB->disconnect();
}
include "../common/finalise.php";
?>