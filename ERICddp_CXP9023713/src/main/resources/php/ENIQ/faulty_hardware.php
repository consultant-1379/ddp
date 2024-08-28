<?php
$pageTitle = "Faulty Hardware Information";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';

$statsDB = new StatsDB();
$arrayOfServers = array();

$statsDB->query("
    SELECT
     DISTINCT(servers.hostname)
    FROM
     sites, servers, eniq_stats_faulty_hardware_details
    WHERE
     sites.name = '$site' AND
     sites.id = eniq_stats_faulty_hardware_details.siteId AND
     servers.id = eniq_stats_faulty_hardware_details.serverId
    ");

while ($serverListRow = $statsDB->getNextRow()) {
    $arrayOfServers[] = $serverListRow[0];
}

$faultyHardwareHelp = <<<EOT
    <p>This page displays status information for resources that the Fault Manager currently identifies to be faulty.
    <p>It is captured using 'fmadm faulty' command on each blade of ENIQ STATS server.
    <p><b>NOTE</b>: Information is displayed only for servers where 'fmadm faulty' command returns some output.
EOT;
drawHeaderWithHelp("Faulty Hardware", 1, "faultyHardwareHelp", $faultyHardwareHelp);

foreach ($arrayOfServers as $key => $value) {
    plotTable($value);
}

function plotTable($serverName) {
    global $site, $date, $statsDB;

    $faultyHardwareDataRow = $statsDB->query("
        SELECT
         occurrenceTime, eventId, msgId, severity, problemClass, affects
        FROM
         sites, servers, eniq_stats_faulty_hardware_details
        WHERE
         sites.name = '$site' AND
         servers.hostname = '$serverName' AND
         eniq_stats_faulty_hardware_details.siteId = sites.id AND
         eniq_stats_faulty_hardware_details.serverId = servers.id AND
         eniq_stats_faulty_hardware_details.date = '$date'
        ");
    $faultyHardwareTable = new HTML_Table("border=1");
    if ($faultyHardwareDataRow = $statsDB->getNumRows() > 0) {
        echo "<h2>$serverName</h2>\n";
        $faultyHardwareTable->addRow( array('<b>Occurrence Time</b>', '<b>Event Id</b>', '<b>msg Id</b>', '<b>Severity</b>', '<b>Problem Class</b>', '<b>Affects</b>') );
        while ($faultyHardwareDataRow = $statsDB->getNextNamedRow()) {
            $faultyHardwareTable->addRow( array($faultyHardwareDataRow['occurrenceTime'], $faultyHardwareDataRow['eventId'], $faultyHardwareDataRow['msgId'], $faultyHardwareDataRow['severity'], $faultyHardwareDataRow['problemClass'], $faultyHardwareDataRow['affects']) );
        }
        echo $faultyHardwareTable->toHTML();
        echo "\n\n";
    }
}

include "../common/finalise.php";
?>