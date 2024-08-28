<?php
$pageTitle = "Storage usage";
$YUI_DATATABLE = true;

include_once "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

function getStorageStatGraph($title, $ylabel, $whatCol) {
    global $serverId;
    global $fromDate;
    global $toDate;
    $sqlParam = array(
        'title'      => $title,
        'ylabel'     => $ylabel,
        'useragg'    => 'true',
        'persistent' => 'true',
        'type'       => 'tsc',
        'querylist'  => array(
                            array(
                                'timecol' => 'time',
                                'whatcol' => $whatCol,
                                'tables'  => "ocs_physicaldisk_details",
                                'where'   => 'ocs_physicaldisk_details.serverid = %d',
                                'qargs'   => array('serverid')
                            )
                        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 640, 240, "serverid=$serverId"
    );
}

$statsDB = new StatsDB();
$fromDate = $date;
$toDate   = $date;
if (isset($_REQUEST['start'])) {
    $fromDate = $_REQUEST['start'];
    $toDate   = $_REQUEST['end'];
}
$hostname = $_REQUEST['server'];
$serverId = getServerId($statsDB, $site, $hostname);
if (!is_int($serverId)) {
    echo "<b>Could not get server id for " . $hostname . ": " . $serverId . "</b>\n";
    include_once "../common/finalise.php";
    exit(0);
}
print <<<END
    <h1>Storage - $hostname</h1>
END;
$physicalDiskHelp = <<<EOT
    This page shows Physical Disk performance metrics of hard or fixed disk drive on a computer.
    This is fetched from Physical_Disk.tsv created by Ocs performance monitor.
    </p>
    The following metrics are presented:
    <ul>
    <li><b>Avg Disk Queue Length:</b> Avg. Disk Queue Length is the average number of both read
    and write requests that were queued for the selected disk during the sample interval.</li>
    <li><b>Reads Per Sec:</b> Disk Reads/sec is the rate of read operations on the disk.</li>
    <li><b>Writes Per Sec:</b> Disk Writes/sec is the rate of write operations on the disk.</li>
    <li><b>%Busy:</b> %Busy Time reports the percentage of time during the sample interval that
    the disk was busy i.e. %Busy = 100 - %Idle.</li>
    </ul>
EOT;
drawHeaderWithHelp("Physical Disk Statistics", 2, "physicalDiskHelp", $physicalDiskHelp);
getStorageStatGraph('Average Disk Queue Length', 'Length', array('avgDiskQueueLength' => 'Queue Length'));
getStorageStatGraph('Reads Per Second', 'Reads', array('readsPerSec' => 'Reads per sec'));
getStorageStatGraph('Writes Per Second', 'Writes', array('writesPerSec' => 'Writes per sec'));
getStorageStatGraph('%Busy', '%Busy', array('100 - idleTimePercent' => 'busy'));

$statsDB->disconnect();
include_once "../common/finalise.php";
