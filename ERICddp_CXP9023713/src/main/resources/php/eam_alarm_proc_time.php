<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>EAM Alarm Processing Time</h1>
<?php

class EAMAlarmProcTimeIndex extends DDPObject {
    var $cols = array(
        "node_fdn" => "Node FDN",
        "alarm_number" => "Alarm Number",
        "time_received" => "Time Received",
        "time_forwarded" => "Time Forwarded",
        "processing_time" => "Processing Time"
    );

    var $defaultOrderBy = "time_received";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $dataset;
        $sql = "
            SELECT f.name AS 'node_fdn',
                   d.alarm_number AS 'alarm_number',
                   d.time_received AS 'time_received',
                   d.time_forwarded AS 'time_forwarded',
                   TIMESTAMPDIFF(SECOND, d.time_received, d.time_forwarded) AS 'processing_time'
            FROM   eam_alarm_details d, sites s, eam_datasets da, eam_node_fdn_names f
            WHERE  date = '" . $date . "' AND siteid = s.id AND s.name = '" . $site . "' AND datasetid = da.id AND da.name = '" . $dataset . "' AND d.node_fdnid = f.id";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<a href=\"$phpDir/eam_stats.php?$webargs\">Return to EAM Statistics main page</a>\n";
?>
<h2>Datasets</h2>
<li><a href="#EHIP">EHIP</a></li>
<li><a href="#EHMS">EHMS</a></li>
<li><a href="#EHM">EHM</a></li>
<?php
// For each dataset, retrieve the data and display it with a heading
foreach (array("EHIP", "EHMS", "EHM") AS $dataset) {
    $sidx = new EAMAlarmProcTimeIndex();
    $dataCntCheck = $sidx->getData();
    echo "<a name=" . $dataset . "></a>";
    echo "<h3>" . $dataset . "</h3>\n";
    if (isset($dataCntCheck[0]['node_fdn'])) {
        $sidx->getSortableHtmlTable();
    } else {
        echo "<b><i>No " . $dataset . " Data</i></b>\n";
    }
}

include "../php/common/finalise.php";
?>
