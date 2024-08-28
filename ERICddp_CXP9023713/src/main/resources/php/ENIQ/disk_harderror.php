<?php
$pageTitle = "Disk Hardware Error";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';

$statsDB = new StatsDB();
$arrayOfServers = array();

$statsDB->query("
    SELECT
     DISTINCT(servers.hostname)
    FROM
     sites, servers, disk_harderror_details
    WHERE
     sites.name = '$site' AND
     sites.id = disk_harderror_details.siteid AND
     servers.id = disk_harderror_details.serverid
    ");

while($serverListRow = $statsDB->getNextRow()) {
    $arrayOfServers[] = $serverListRow[0];
}

$diskHelp = <<<EOT
    This page displays the disk hardware error for the local and SAN disk's on each blade of ENIQ server.
EOT;
drawHeaderWithHelp("Disk Hardware Error", 1, "diskHelp", $diskHelp);

foreach($arrayOfServers as $key => $value) {
    plotTable($value);
}

function plotTable($serverName) {
    global $site, $date, $statsDB;

    $diskHardwareErrorDataRow = $statsDB->query("
        SELECT
         disk, harderrorCount
        FROM
         sites, servers, disk_harderror_details
        WHERE
         sites.name = '$site' AND
         servers.hostname = '$serverName' AND
         disk_harderror_details.siteid = sites.id AND
         disk_harderror_details.serverid = servers.id AND
         disk_harderror_details.harderrorCount > 0 AND
         disk_harderror_details.date = '$date'
        ORDER BY
         harderrorCount DESC
        ");
    $diskHardwareErrorTable = new HTML_Table("border=1");
    if($diskHardwareErrorDataRow = $statsDB->getNumRows() > 0) {
        echo "<h2>$serverName</h2>\n";
        $diskHardwareErrorTable->addRow(array('<b>Disk</b>', '<b>Hardware Error Count</b>'));
        while($diskHardwareErrorDataRow = $statsDB->getNextNamedRow()) {
            $diskHardwareErrorTable->addRow(array($diskHardwareErrorDataRow['disk'], $diskHardwareErrorDataRow['harderrorCount']));
        }
        echo $diskHardwareErrorTable->toHTML();
        echo "\n\n";
    }
}

include "../common/finalise.php";
?>