<?php
$pageTitle = "OS Memory Profile";
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

if ( isset($_GET['server'])) {
    $host = $_GET['server'];
}

$statsDB = new StatsDB();

$osMemoryTable = new HTML_Table();
$osMemoryTable->addRow(array("Timestamp", "Page Summary", "Pages", "Bytes", "%Tot"), null, "th");

$osMemoryTableRow = $statsDB->query("
                        SELECT
                         time, metric, pages, bytes, totalPercent
                        FROM
                         eniq_stats_os_memory_profile, sites, servers
                        WHERE
                         sites.name = '$site' AND
                         servers.hostname = '$host' AND
                         eniq_stats_os_memory_profile.siteId = sites.id AND
                         eniq_stats_os_memory_profile.serverId = servers.id AND
                         eniq_stats_os_memory_profile.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
                        ORDER By
                         time, pages
                    ");

$count = $statsDB->getNumRows();

while ($osMemoryTableRow = $statsDB->getNextNamedRow()) {
    $osMemoryTable->addRow( array($osMemoryTableRow['time'], $osMemoryTableRow['metric'], $osMemoryTableRow['pages'], $osMemoryTableRow['bytes'], $osMemoryTableRow['totalPercent']) );
}

for($row = 1; $row <= $count; $row = $row + 9 ) {
    $osMemoryTable->setCellAttributes($row, 0, "rowspan='9'");
}
$osMemoryHelp = <<<EOT
    The below table displays the output of the command echo ::memstat | mdb -k for every 15 min interval.
EOT;
drawHeaderWithHelp("OS Memory Profile", 1, "osMemoryHelp", $osMemoryHelp);
echo $osMemoryTable->toHTML();

include "../common/finalise.php";
?>
