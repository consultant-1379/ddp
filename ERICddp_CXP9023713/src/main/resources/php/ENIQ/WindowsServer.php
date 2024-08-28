<?php
$pageTitle = "Server Usage ";
$YUI_DATATABLE = true;
const PROCESS = "process";
const SERVERID = "serverid";
const TIMECOL = "timecol";
const WHATCOL = "whatcol";
const TABLES = "tables";
const WHERE = "where";
const QARGS = "qargs";
const CLOSEPARAGRAPH = "</p>\n";
const QUERYLIST = 'querylist';
const BORDER = 'border=1';
const TYPE = 'type';
include "../common/init.php";

require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/ENIQ/functions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once 'HTML/Table.php';

function getCpuTable($statsDB, $serverId, $fromDate, $toDate)
{
    $table = new HTML_Table(BORDER);

    $row = $statsDB->queryRow("
        SELECT
            ROUND( AVG(processorTimePercent), 2),
            ROUND( AVG(userTimePercent), 2),
            ROUND( AVG(processorTimePercent + userTimePercent), 2)
        FROM
            windows_processor_details
        WHERE
            windows_processor_details.serverid = $serverId AND
            windows_processor_details.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
        ");

    $table->addRow(array('Processor',$row[0]));
    $table->addRow(array('User',$row[1]));
    $table->addRow(array('Total',$row[2]));
    return $table;
}

function getServerStatGraph(
    $serverId, $title, $ylabel, $fromDate, $toDate, $whatCol,$plottype, $graphType = 'tsc')
{
    $sqlParam = array(
        'title'      => $title,
        'ylabel'     => $ylabel,
        'useragg'    => 'true',
        'persistent' => 'true',
        'type'       => $graphType,
        QUERYLIST    => array()
    );
    if ($plottype == 'memory') {
        $sqlParam[QUERYLIST][] = array(
            TIMECOL => 'time',
            WHATCOL => $whatCol,
            TABLES  => "windows_memory_details",
            WHERE   => 'windows_memory_details.serverid = %d',
            QARGS   => array(SERVERID)
        );
    }
    elseif ($plottype == PROCESS) {
        $sqlParam[QUERYLIST][] = array(
            TIMECOL => 'time',
            WHATCOL => $whatCol,
            TABLES  => "windows_system_details",
            WHERE   => 'windows_system_details.serverid = %d',
            QARGS   => array(SERVERID)
        );
    }
    else {
        $sqlParam[QUERYLIST][] = array(
            TIMECOL => 'time',
            WHATCOL => $whatCol,
            TABLES  => "windows_processor_details",
            WHERE   => 'windows_processor_details.serverid = %d',
            QARGS   => array(SERVERID)
        );
    }
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL(
        $id, "$fromDate 00:00:00", "$toDate 23:59:59",
        true, 640, 240, "serverid=$serverId"
    );

}
$statsDB = new StatsDB();
$fromDate = $date;
$toDate   = $date;
$varStart = requestValue('start');
if (isset($varStart)) {
    $fromDate = requestValue('start');
    $toDate   = requestValue('end');
}
$hostname = requestValue('server');
$type  = requestValue('type');
$serverId = getServerId($statsDB, $site, $hostname);
if (!is_int($serverId)) {
    echo "<b>Could not get server id for " . $hostname . ": " . $serverId . "</b>\n";
    include "../common/finalise.php";
    exit(0);
}
echo '<h1>Server Stats - ' . $hostname . '</h1>';
echo '<ul>
 <li><a href="#cpu">CPU</a></li>
 <li><a href="#mem">Memory</a></li>';

$serverhardware = <<<EOT
    The following table displays Server Hardware details.
EOT;

if ($type == "BIS" || $type == "NetAnServer") {
    drawHeaderWithHelp("Server Hardware", 1, "ServerHardware", $serverhardware);
    echo getHardware($statsDB, $serverId, $fromDate, $type)->toHTML();
}
function getHardware($statsDB, $serverId, $fromDate, $type) {
    $mainTable = new HTML_Table(BORDER);
    $row = $statsDB->queryRow("SELECT serverType, bios, osName, osVersion, systemBootTime, physicalMemory,
                totalDisk from bis_ocs_hardware_details where serverId = $serverId AND date = '$fromDate'");
    $mainTable->addRow(array("<b>Server Type</b>", $row[0]));
    $mainTable->addRow(array("<b>BIOS</b>", $row[1]));
    $mainTable->addRow(array("<b>OSName</b>", $row[2]));
    $mainTable->addRow(array("<b>OSVersion</b>", $row[3]));
    $mainTable->addRow(array("<b>System Boot Time</b>", $row[4]));
    $mainTable->addRow(array("<b>Physical Memory(MB)</b>", $row[5]));
    $mainTable->addRow(array("<b>Total Disk(GB)</b>", $row[6]));
    $cpuTable = new HTML_Table(BORDER);
    $cpuTable->addRow(array('Number', 'Type', 'Clock Speed (MHz)', 'Cores'), null, 'th');
    $statsDB->query("SELECT num, cpuType, clockSpeed, cores
        from bis_ocs_hardware_details where serverId = $serverId and date = '$fromDate'");
    while ($row = $statsDB->getNextRow()) {
        $cpuTable->addRow($row);
    }
    $mainTable->addRow(array('<b>CPU</b>', $cpuTable->toHTML()));

    $query=<<<EOT
SELECT
    displayName AS version
FROM
    eniq_bo_version_details,
    eniq_bo_display_name_id_mapping
WHERE
    eniq_bo_version_details.displayId = eniq_bo_display_name_id_mapping.id AND
    eniq_bo_version_details.serverId = '$serverId' AND
    eniq_bo_version_details.type = '$type' AND
    eniq_bo_version_details.date = '$fromDate';
EOT;

    $statsDB->query($query);
    $versionRowsAppend = "";
    $numberOfRows = $statsDB->getNumRows();

    if ( $numberOfRows > 0 ) {
        while ( $result = $statsDB->getNextNamedRow() ) {
            $versionRowsAppend .= $result['version']."<br>";
        }
        $versionArray = array("<b>BI Platform Version</b>", $versionRowsAppend);
        $mainTable->addRow($versionArray);
    }

    return $mainTable;
}

// BIS-Netan Logical Drives Information

function createTable( $params, $name, $title, $table, $where ) {

    drawHeader($title, 2, $name);

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables($table)
            ->where($where);

    foreach ($params as $key => $value) {
        $table->addSimpleColumn($value, $key);
    }

    echo $table->paginate( array(10, 100, 1000, 10000) )
               ->build()
               ->getTable();
    echo addLineBreak(2);
}

$colsArr = array(
                'Drive Name'      => 'bis_netan_logical_drive_details.name',
                'Capacity (GB)'   => 'bis_netan_logical_drive_details.capacity',
                'Free Space (GB)' => 'bis_netan_logical_drive_details.freeSpace',
                'Used Space (%)'  => 'bis_netan_logical_drive_details.usedSpacePercent'
                );
$name = 'logicalDriveDetailsHelp';
$title = 'Logical Drives';
$table = array('bis_netan_logical_drive_details', 'sites', 'servers');
$where = $statsDB->where('bis_netan_logical_drive_details', 'date');
$where .= " AND servers.id = bis_netan_logical_drive_details.serverid
            AND servers.hostname = '$hostname'";

createTable( $colsArr, $name, $title, $table, $where );

// BIS-OCS Security certificate Information

createWindowsCertificateTable();

echo '<a name="cpu"/>';
$cpuHelp = <<<EOT
CPU usage information is retrieved from the server using Window Performance Monitor Tool.
The CPU usage shows percentage CPU utilised.The data is fetched from processor.tsv file which is generated by Window Performance Monitor Tool.
EOT;
drawHeaderWithHelp("CPU", 1, "cpuHelp", $cpuHelp);

$dailyavgHelp = <<<EOT
The table presents the daily average CPU usage, while the graph presents
the CPU load across the day.
</p>
The following metrics are presented:
<ul>
<li><b>Processor:</b> Processor Time is the percentage of elapsed time that all of process threads used the processor to execute the instructions.</li>
<li><b>User:</b> User Time is the percentage of elapsed time that the process threads spent executing code in user mode. Applications, environment subsystems, and integral subsystems execute in user mode.</li>
<li><b>Total:</b> The Total percentage of CPU time spent  i.e. %processor + %user</li>
</ul>
EOT;
drawHeaderWithHelp("Daily Average", 2, "dailyavgHelp", $dailyavgHelp);

echo getCpuTable($statsDB, $serverId, $fromDate, $toDate)->toHTML();

echo "<p>" . getServerStatGraph($serverId, 'CPU', '%', $fromDate, $toDate, array(
    'processorTimePercent' => 'Processor',
    'userTimePercent'      => 'User',
    'totalTimePercent'     => 'Total'
    ), 'sa') . CLOSEPARAGRAPH;

$procq = <<<EOT
Process Queue Length information is retrieved from the server using the Window Performance Monitor Tool.
</p>
Processor Queue Length is the number of threads in the processor queue.
</p>
This counter shows ready threads only, not threads that are running.
</p>
EOT;
drawHeaderWithHelp("Processor Queue Length", 1, "procq", $procq);

echo "<p>" . getServerStatGraph($serverId, 'Run Q', 'Length', $fromDate, $toDate, array(
    'processorQueueLength' => 'Run Q Length'
    ), PROCESS) . CLOSEPARAGRAPH;

$prochelp = <<<EOT
Process count information is retrieved from the server using the Window Performance Monitor Tool.
</p>
This tool generate system.tsv file which shows the Process count.
<p/>
Processes is the number of processes in the computer at the time of data collection. This is an instantaneous count, not an average over the time interval.
</p>
EOT;
drawHeaderWithHelp("Process Count", 1, "prochelp", $prochelp);

echo getServerStatGraph($serverId, 'Process Count', 'Processes', $fromDate, $toDate, array(
    'numberOfProcesses' => 'Processes'
    ), PROCESS);

echo '<a name="mem"/>';
$memhelp = <<<EOT
Memory information is retrieved from memory.tsv file generated by Window Performance Monitor Tool.
</p>
Available memory is the amount of physical memory, in Megabytes, immediately available for allocation to a process or for system use.
</p>
EOT;
drawHeaderWithHelp("Memory", 1, "memhelp", $memhelp);
echo addLineBreak();

echo "<p>" .getServerStatGraph($serverId, 'Free RAM', 'MB', $fromDate, $toDate, array(
     'freeram' => 'MB'
     ), "memory"). CLOSEPARAGRAPH;

$statsDB->disconnect();

echo addLineBreak();
$type = requestValue(TYPE);
$params = array( TYPE => $type);

if ( $statsDB->hasData('eniq_windows_interface_stats', 'time', false) ) {
    $table = new ModelledTable('ENIQ/windowsInterfaceStats', 'windowsInterfaceHelp', $params);
    echo $table->getTableWithHeader("Network Interfaces");
    echo addLineBreak();
}

include "../common/finalise.php";
