<?php
$pageTitle = "Server Usage ";
//
// If the user has clicked on a graph
//
include "common/init.php";

require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

require_once "SqlPlotParam.php";
require_once 'HTML/Table.php';

const SERVER_ID = 'serverid';

function getCpuTable($statsDB, $serverId, $fromDate, $toDate) {

    $table = new HTML_Table('border=1');

    $row = $statsDB->queryNamedRow("
SELECT
 ROUND( AVG(user) ) AS user, ROUND( AVG(sys) ) AS sys, ROUND( AVG(iowait) ) AS iowait,
 ROUND( AVG(steal) ) AS steal, ROUND( AVG(guest) ) AS guest,
 ROUND( AVG(sys + iowait + user + IFNULL(guest,0) )) AS total
FROM hires_server_stat
WHERE
 hires_server_stat.serverid = $serverId AND
 hires_server_stat.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");

    $table->addRow(array('Usr', $row['user']));
    $table->addRow(array('Sys', $row['sys']));
    $table->addRow(array('IO Wait',$row['iowait']));

    $optCols = array( 'steal' => FALSE, 'guest' => FALSE );
    if ( ! is_null($row['guest']) ) {
        $table->addRow(array("Guest", $row['guest']));
        $optCols['guest'] = TRUE;
    }
    if ( ! is_null($row['steal']) ) {
        $table->addRow(array("Steal", $row['steal']));
        $optCols['steal'] = TRUE;
    }

    $table->addRow(array('Total', $row['total']));

    return array($table, $optCols);
}

function getHostNameFromFile($rootdir) {

    if (file_exists($rootdir . "/hostname.php")) {
        include($rootdir . "/hostname.php");
        if ((!isset($hostname)) || (strlen($hostname) <= 0)) {
            echo "<b>Failed to read hostname</b>\n";
            exit(1);
        } else {
            return $hostname;
        }
    }
    return "shouldnotsee";
}

function getHardware($statsDB, $serverCfgId, $biosver) {

    $mainTable = new HTML_Table('border=1');
    $row       = $statsDB->queryRow("
        SELECT system,ROUND(mbram/1024,1)
        FROM servercfgtypes
        WHERE id = $serverCfgId"
    );
    $mainTable->addRow(array(
        "<b>Server Type</b>",
        $row[0]
    ));
    if (isset($biosver)) {
        $mainTable->addRow(array(
            "<b>BIOS</b>",
            $biosver
        ));
    }
    $mainTable->addRow(array(
        "<b>Memory (GB)</b>",
        $row[1]
    ));

    $cpuTable = new HTML_Table('border=1');
    $cpuTable->addRow(array(
        'Number',
        'Type',
        'Clock Speed (MHz)',
        'Cores',
        'Threads Per Core',
        'Cache (MB)',
        'Normalised CPU Performance'
    ), null, 'th');
    $statsDB->query("
        SELECT servercpu.num AS num, cputypes.name AS name, cputypes.mhz AS freq,
            cputypes.cores, cputypes.threadsPerCore,
        ROUND(cputypes.kbCache/1024,1) AS cache,
            cputypes.normCpu * servercpu.num AS normCpu
        FROM cputypes, servercpu
        WHERE servercpu.cfgid = $serverCfgId AND
            servercpu.typeid = cputypes.id");
    while ($row = $statsDB->getNextRow()) {
        $cpuTable->addRow($row);
    }
    $mainTable->addRow(array(
        '<b>CPU</b>',
        $cpuTable->toHTML()
    ));

    return $mainTable;
}

function getServerStatGraph( $serverId, $title, $ylabel, $fromDate, $toDate, $whatCol, $graphType = 'tsc') {

    $sqlParam       = array(
        'title' => $title,
        'ylabel' => $ylabel,
        'useragg' => 'true',
        'persistent' => 'true',
        'type' => $graphType,
        'querylist' => array(
            array(
                'timecol' => 'time',
                'whatcol' => $whatCol,
                'tables' => "hires_server_stat",
                'where' => 'hires_server_stat.serverid = %d',
                'qargs' => array(
                    'serverid'
                )
            )
        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id             = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL(
        $id, "$fromDate 00:00:00", "$toDate 23:59:59",
        true, 640, 240, "serverid=$serverId"
    );

}

function getServerDataFilePath($serverDirs, $fileNames) {
    foreach ($serverDirs as $serverDir) {
        foreach ($fileNames as $fileName) {
            $path = $serverDir . "/" . $fileName;
            debugMsg("getServerDataFilePath: Checking $path");
            if ( file_exists($path) ) {
                debugMsg("getServerDataFilePath: returning $path");
                return $path;
            }
        }
    }
    return "";
}

$statsDB = new StatsDB();

if (isset($_GET['start'])) {
    $fromDate = $_GET['start'];
    $toDate   = $_GET['end'];
} else {
    $fromDate = $date;
    $toDate   = $date;
}

$hostname = $_GET['server'];
$serverId = getServerId($statsDB, $site, $hostname);
if (!is_int($serverId)) {
    echo "<b>Could not get server id for " . $hostname . ": " . $serverId . "</b>\n";
    include "common/finalise.php";
    exit(0);
}

//
// Main Flow
//
if ( file_exists($rootdir . "/servers") ) {
  $serverDir = "servers/" . $hostname;
} else {
  $serverDir = "server";
}

$webroot = $webroot . "/" . $serverDir;
$webargs = "site=$site&dir=$dir&date=$fromDate&oss=$oss&server=$hostname&serverdir=" . urlencode($serverDir);
$rootdir = $rootdir . "/" . $serverDir;

$myServerDir = null;
$myDataDir = null;

$datadirsPath = $datadir . "/server_datadirs.txt";
if ( is_file($datadirsPath) ) {
    $serverDataDirs = preg_grep( "/^${hostname}:/", file($datadirsPath) ); // NOSONAR
    debugMsg("Searching $datadirsPath for $hostname count=" . count($serverDataDirs), $serverDataDirs);
    if ( count($serverDataDirs) == 1 ) {
        $serverDataDir = array_pop($serverDataDirs);
        $myServerDir = trim( preg_replace( '/^.*::/', '', $serverDataDir) );
        if ( $view_archive ) {
            $oldLiveDataRoot = sprintf("%s/%s/%s/data/%s", $stats_dir, $oss, $site, $dir);
            if ( substr($myServerDir, 0, strlen($oldLiveDataRoot)) === $oldLiveDataRoot ) {
                $relpath = substr($myServerDir, strlen($oldLiveDataRoot) + 1);
                $myServerDir = $datadir . "/" . $relpath;
            }
        }
        debugMsg("myServerDir=$myServerDir");
        $myDataDir = dirname($myServerDir);
    }
} elseif ( $debug > 0 ) {
    echo "<pre>datadirsPath not found $datadirsPath</pre>\n";
}


?>
<h1>Server Stats - <?= $hostname; ?></h1>
<ul>
 <li><a href="#cpu">CPU</a></li>
 <li><a href="#net">Network</a></li>
 <li><a href="#mem">Memory</a></li>
 <li><a href="<?= $php_webroot ?>/storage.php?<?= $webargs ?>">Storage</a></li>
<?php
$year = explode("-",$date)[0];
# Only show processes link if we have the data
$row = $statsDB->queryRow("SELECT COUNT(*) FROM proc_stats WHERE time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND serverid = $serverId");
if ( $row[0] > 0 ) {
    echo "<li><a href=\"$php_webroot/topn.php?$webargs\">Processes</a></li>\n";
}

if (file_exists($rootdir . "/internal.jpg")) {
    echo "<li><a href=\"" .
        $php_webroot . "/socket.php?" .
        $webargs . "\">Sockets</a></li>\n";
}
// Any SMF services on this host?
$row = $statsDB->queryRow("
    SELECT COUNT(status)
    FROM smf_status
    WHERE serverid = " . $serverId . " AND date = '" . $date . "'");
if ($row[0] > 0) {
    echo "<li><a href=\"" . $php_webroot . "/smf.php?" . $webargs . "\">SMF Services</a></li>\n";
}

# Crontab
$row = $statsDB->queryRow("SELECT COUNT(*) FROM crontabs WHERE date = '$date' AND serverid = $serverId");
if ( $row[0] > 0 ) {
    echo "<li><a href=\"$php_webroot/crontab_stats.php?$webargs\">Crontab</a></li>\n";
}

// Any Server log analysis on this host?
if ( strtoupper($oss) == "TOR" ) {
    $row = $statsDB->queryRow("
SELECT COUNT(serverid)
FROM enm_logs, sites
WHERE
 enm_logs.siteid = sites.id AND sites.name = '$site' AND
 enm_logs.date = '$date' AND
 serverid = $serverId");

    if ($row[0] > 0) {
        $lbl = 'View Server Log Analysis';
        $path = "/TOR/system/elasticsearch.php";
        $args = array( 'logdir' => 'enmlogs', 'host' => $hostname );
        $link = makeLink( $path, $lbl, $args );
        echo "<li>$link</li>";
    }
}

// All the following depends on having a server dir to look in
if ( ! is_null($myServerDir) ) {
    $engLogDir = $myDataDir . "/TOR/vcs";
    $engLogPath = getServerDataFilePath(array($engLogDir), array('engine_A.log', 'engine_A.log.gz'));
    if ( ! empty($engLogPath) ) {
        echo makeHTMLListItem(makeLinkForURL(getUrlForFile($engLogPath), "View Engine Logs")) . "\n";
    }

    // Any Server Messages data on this host?
    // Is there any 'messages*' file on this host?
    $msgFilePath = getServerDataFilePath(array($myServerDir), array("messages", "messages.gz"));
    if ( ! empty($msgFilePath) ) {
        echo makeHTMLListItem(makeLinkForURL(getUrlForFile($msgFilePath), "View Server Messages")) . "\n";
    }


    // Any iptables data on this host?
    $ipfilepath = getServerDataFilePath(array($myServerDir), array("iptables.txt"));
    if ( ! empty($ipfilepath) ) {
        echo makeHTMLListItem(makeLinkForURL(getUrlForFile($ipfilepath), "View Server iptables")) . "\n";
    }

    $cronlog = getServerDataFilePath(array($myServerDir, $myDataDir), array("cron.log"));
    if ( ! empty($cronlog) ) {
        echo makeHTMLListItem(makeLinkForURL( getUrlForFile($cronlog), "View Server cron.log")) . "\n";
    }

    $healthcheckLog = getServerDataFilePath(array($myDataDir . "/TOR"), array("enm_healthcheck.log"));
    if ( ! empty($healthcheckLog) ) {
        echo makeHTMLListItem(makeLinkForURL(getUrlForFile($healthcheckLog), "View ENM Healthcheck Log")) . "\n";
    }
}

// NFS V3 Server data?
    $query = "
SELECT
    COUNT(*)
FROM
    nfsd_v3ops,
    sites
WHERE
    sites.id = nfsd_v3ops.siteid AND
    sites.name = '$site' AND
    serverid = '$serverId' AND
    time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

$row = $statsDB->queryRow($query);

if ($row[0] > 0) {
    echo "<li><a href=\"" . $php_webroot . "/nfsd_v3ops.php?" . $webargs . "&serverid=$serverId\">NFS Server Operations</a></li>\n";
}

$hasNFSD = $statsDB->hasData('nfsd_stat', 'time', false, "nfsd_stat.serverid = $serverId");
if ( $hasNFSD ) {
    echo "<li>" . makeAnchorLink("nfsd", 'NFS Server') . "</li>\n";
}

echo "</ul>\n";

if ($debug) {
    echo "<p>rootdir=$rootdir</p>\n";
}

//
// Server Hardware
//
$row = $statsDB->queryRow("SELECT cfgid,biosver FROM servercfg WHERE serverid = $serverId AND date = '$fromDate'");
if ($row[0] || file_exists($rootdir . "/hw.html")) {
    drawHeaderWithHelp("Server Hardware", 1, "ServerHardware");
    if ($row[0]) {
        $hwTable = getHardware($statsDB, $row[0], $row[1]);
        if (file_exists($rootdir . "/array.html")) {
            $hwTable->addRow(array(
                "<b>Storage</b>",
                file_get_contents($rootdir . "/array.html")
            ));
        }
        echo $hwTable->toHTML();
    } else {
        echo "<table>\n";
        include($rootdir . "/hw.html");
        if (file_exists($rootdir . "/array.html")) {
            echo " <tr> <th>Storage</th> <td>\n";
            include($rootdir . "/array.html");
            echo " </td> </tr>\n";
        }
        echo "</table>\n";
    }
}
//
// User Information
//
if (file_exists($datadir . "/numusers.txt")) {
    echo "<H1>Number of User Accounts";
    drawHelpLink("numusershelp");
    echo "</H1>\n";
    drawHelp("numusershelp", "Number of User Accounts", "
The number of user accounts is identified by running a command similar to the following:
<p/>
<code>
getent passwd | awk -F: '{print $1}' | sort | uniq | wc -l | awk '{print $1}'
</code>
        ");
    include $datadir . "/numusers.txt";
}

echo '<a name="cpu">';drawHeaderWithHelp("CPU", 1, "cpuHelp");echo "\n";
drawHeaderWithHelp("Daily Average", 1, "dailyavghelp");

list ($cpuTable,$cpuOptCols) = getCpuTable($statsDB, $serverId, $fromDate, $toDate);
echo $cpuTable->toHTML();

if ( $debug ) { echo "<pre>cpuOptCols"; print_r($cpuOptCols); echo "</pre>\n"; }

$cpuGraphCols = array(
    'iowait' => 'IO Wait',
    'sys' => 'Sys',
    'user' => 'Usr'
);
if ( $cpuOptCols['guest'] ) {
    $cpuGraphCols['guest'] = "Guest";
}
if ( $cpuOptCols['steal'] ) {
    $cpuGraphCols['steal'] = "Steal";
}

echo "<p>" . getServerStatGraph($serverId, 'CPU', '%', $fromDate, $toDate,
                                $cpuGraphCols, 'sa') . "</p>\n";


echo "<p>" . getServerStatGraph($serverId, 'Run Q', 'Length', $fromDate, $toDate, array(
    'runq' => 'Run Q Length'
)) . "</p>\n";

drawHeaderWithHelp("Process Count", 1, "prochelp");

echo getServerStatGraph($serverId, 'Process Count', 'Processes', $fromDate, $toDate, array(
    'numproc' => 'Processes'
));

drawHeaderWithHelp("Processes / Second", 1, "procSechelp");

echo getServerStatGraph($serverId, 'New Processes/Second', 'Count', $fromDate, $toDate, array(
    'proc_s' => 'Processes/Second'
)) . "\n";

//
// Memory
//
echo '<a name="mem">';drawHeaderWithHelp("Memory", 1, "MemoryHelp");echo "\n";

echo "<p>" .getServerStatGraph($serverId, 'Page Scan Rate', 'Pages', $fromDate, $toDate, array(
     'pgscan' => 'Page Scan'
))."</p>\n";
echo "<p>" .getServerStatGraph($serverId, 'Free RAM', 'MB', $fromDate, $toDate, array(
     'freeram' => 'MB'
))."</p>\n";

echo "<p>" .getServerStatGraph($serverId, 'Free Swap', 'MB', $fromDate, $toDate, array(
     'freeswap' => 'MB'
))."</p>\n";
$row = $statsDB->queryRow("
    SELECT COUNT(*) FROM hires_server_stat
    WHERE serverid = $serverId AND time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59' AND
        memused IS NOT NULL");
if ($row[0] > 0) {
  echo "<p>" .getServerStatGraph($serverId, 'Memory Usage', 'MB', $fromDate, $toDate, array(
            'memused - membuffers - memcached' => 'Used',
            'membuffers' => 'Buffers',
            'memcached' => 'Cached',
            'freeram' => 'Free'
        ), 'sa')."</p>\n";

}

//
// Network Interfaces
//

echo '<a name="net">';drawHeaderWithHelp("Network Interfaces", 1, "NetworkInterfaceHelp");echo "\n";

$hasSummary = $statsDB->hasData("sum_nic_stat", 'date', 'true');
if ( $hasSummary ) {
    $table = "sum_nic_stat";
} else {
    $table = "nic_stat";
}

$nicStatTable = new ModelledTable(
    'common/' . $table,
    'nicstats',
    array('serverid' => strval($serverId))
);
echo $nicStatTable->getTable();

$nicCfgTable = new ModelledTable(
    'common/network_interface_config',
    'network_interface_config',
    array('serverid' => strval($serverId))
);
if ( $nicCfgTable->hasRows() ) {
    echo $nicCfgTable->getTableWithHeader("NIC Configuration", 3);
}

$nicErrorTable = new ModelledTable(
    'common/nic_errors',
    'nic_errors',
    array('serverid' => strval($serverId))
);
if ( $nicErrorTable->hasRows() ) {
    echo $nicErrorTable->getTableWithHeader("NIC Errors");
}

if ( $hasNFSD ) {
    drawHeader("NFS Server", HEADER_2, "nfsd");
    $nfsdStat = new ModelledGraph('common/nfsd_stat');
    echo  $nfsdStat->getImage(array(SERVER_ID => $serverId));
}

$statsDB->disconnect();
include "common/finalise.php";
?>

