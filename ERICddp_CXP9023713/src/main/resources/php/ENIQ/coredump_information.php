<?php
$pageTitle = "Core Dump Information";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}
if ( isset($_GET['date']) ) {
    $date = $_GET['date'];
}

$statsDB = new StatsDB();

function getCoredumpsDetails($table, $column, $additionalWhereClause) {
    global $site, $statsDB, $result;
    $result = $statsDB->query("
                  SELECT
                   $column
                  FROM
                   $table, sites
                  WHERE
                   sites.name = '$site'
                   $additionalWhereClause
              ");
}

function getSpannedCoredumpTableData($coredumpPathOrServerNameArray, $coredumpPathTypeArrayOrEmptyString, $coredumpFlag, $coredumpTable, $initialIndexCol, $lastIndexCol) {
    $arrLength = count($coredumpPathOrServerNameArray);
    $initialIndexForRowSpan = 0;
    $rowSpanCounter = 1;
    for($index = 0; $index < $arrLength; $index++) {
        $nextIndex = $index + 1;
        if(($coredumpPathOrServerNameArray[$nextIndex] == $coredumpPathOrServerNameArray[$index] && $coredumpPathTypeArrayOrEmptyString[$nextIndex] == $coredumpPathTypeArrayOrEmptyString[$index] && $coredumpPathTypeArrayOrEmptyString[$nextIndex] == "shared" && $coredumpFlag == "coredumpPathFlag") || ($coredumpPathOrServerNameArray[$nextIndex] == $coredumpPathOrServerNameArray[$index] && $coredumpFlag == "localCoredumpDetailsFlag") || ($coredumpPathOrServerNameArray[$nextIndex] == $coredumpPathOrServerNameArray[$index] && $coredumpFlag == "sharedCoredumpDetailsFlag")) {
            if($rowSpanCounter == 1) {
                $initialIndexForRowSpan = $index;
            }
            $rowSpanCounter++;
        }
        else {
            for($col = $initialIndexCol; $col <= $lastIndexCol; $col++) {
                $coredumpTable->setCellAttributes($initialIndexForRowSpan + 1, $col, "rowspan='$rowSpanCounter'");
            }
            $initialIndexForRowSpan = $index + 1;
            $rowSpanCounter = 1;
        }
    }
}

$coredumpPathAndDetailsTableAttributes = array (
                                             'width' => '800',
                                             'style' => 'text-align:left'
                                         );

$coredumpStatisticsHelp = <<<EOT
    Core Dump statistics page displays information about the existing core dump files on ENIQ deployment for the below collection time.
    It also displays information about the history of core dump files.
    <p>
    <b>Note:</b> The collection time is the time when the core dump data was last collected by DDC on ENIQ server.
EOT;
drawHeaderWithHelp("Core Dump Statistics", 1, "coredumpStatisticsHelp", $coredumpStatisticsHelp);

$requiredTable = "eniq_coredump";
$requiredColumn = "max(collectionTime) AS collectiontime";
$requiredWhereClause = "AND eniq_coredump.siteId = sites.id AND collectionTime BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY eniq_coredump.siteId";
getCoredumpsDetails($requiredTable, $requiredColumn, $requiredWhereClause);
$maxCollectionTimeCount = $statsDB->getNumRows();
global $maxCollectionTime;

if($maxCollectionTimeCount > 0) {
    $result = $statsDB->getNextNamedRow();
    $maxCollectionTime = $result['collectiontime'];
    echo "<b>Collection Time:</b>"." ".$maxCollectionTime;
    $maxCollectionTime = substr($maxCollectionTime, 0, 16).":00";
}

$requiredTable = "eniq_coredump, eniq_coredump_path, servers";
$requiredColumnForLocal = "eniq_coredump.serverId AS serverid, hostname, CAST(servers.type AS char) AS type, coredumpPath, coredumpName, coredumpCreationTime";
$requiredWhereClauseForLocal = "AND eniq_coredump.serverId = servers.id AND eniq_coredump_path.serverId = eniq_coredump.serverId AND eniq_coredump.siteId = sites.id AND eniq_coredump_path.date = '$date' AND collectionTime BETWEEN '$maxCollectionTime' AND '$date 23:59:59' AND coredumpPathType = 'local' ORDER BY hostname, coredumpCreationTime DESC";
getCoredumpsDetails($requiredTable, $requiredColumnForLocal, $requiredWhereClauseForLocal);
$localPathRowCount = $statsDB->getNumRows();
$arrOfServerIds = array();

if($localPathRowCount > 0) {
    $localPathCoredumpDetailsTable = new HTML_Table($coredumpPathAndDetailsTableAttributes);
    $localPathCoredumpDetailsTable->addRow( array('<b>Server Name</b>', '<b>Server Type</b>', '<b>Core Dump Path</b>', '<b>Core Dump Name</b>', '<b>Creation Time</b>') );
    $arrOfServerName = array();
    $emptyString = "";
    while ($result = $statsDB->getNextNamedRow()) {
        $arrOfServerName[] = $result['hostname'];
        $arrOfServerIds[] = $result['serverid'];
        $localPathCoredumpDetailsTable->addRow( array($result['hostname'], $result['type'], $result['coredumpPath'], $result['coredumpName'], $result['coredumpCreationTime']) );
    }
    getSpannedCoredumpTableData($arrOfServerName, $emptyString, localCoredumpDetailsFlag, $localPathCoredumpDetailsTable, 0, 2);
}

$requiredColumnForShared = "eniq_coredump.serverId AS serverid, coredumpPath, coredumpName, coredumpCreationTime";
$requiredWhereClauseForShared = "AND eniq_coredump.serverId = servers.id AND eniq_coredump_path.serverId = eniq_coredump.serverId AND eniq_coredump.siteId = sites.id AND eniq_coredump_path.date = '$date' AND collectionTime BETWEEN '$maxCollectionTime' AND '$date 23:59:59' AND coredumpPathType = 'shared' GROUP BY coredumpName ORDER BY coredumpPath, coredumpCreationTime DESC";
getCoredumpsDetails($requiredTable, $requiredColumnForShared, $requiredWhereClauseForShared);
$sharedPathRowCount = $statsDB->getNumRows();

if($sharedPathRowCount > 0) {
    $sharedPathCoredumpDetailsTable = new HTML_Table($coredumpPathAndDetailsTableAttributes);
    $sharedPathCoredumpDetailsTable->addRow( array('<b>Core Dump Path</b>', '<b>Core Dump Name</b>', '<b>Creation Time</b>') );
    $arrOfCoredumpPath = array();
    while ($result = $statsDB->getNextNamedRow()) {
        $arrOfCoredumpPath[] = $result['coredumpPath'];
        $arrOfServerIds[] = $result['serverid'];
        $sharedPathCoredumpDetailsTable->addRow( array($result['coredumpPath'], $result['coredumpName'], $result['coredumpCreationTime']) );
    }
    getSpannedCoredumpTableData($arrOfCoredumpPath, $emptyString, sharedCoredumpDetailsFlag, $sharedPathCoredumpDetailsTable, 0, 0);
}

$arrServerId = implode(',', $arrOfServerIds);

if (!empty($arrServerId)) {
    $coredumpPathHelp = <<<EOT
        The following table gives information about the core dump path set on each ENIQ server and memory utilized by the core dump files for a selected date.
        <p>
        <b>Note:</b> Information is fetched only for the server where "init core file pattern" of coreadm is set.
        <p>
        <ol>
            <li><b>Server Name:</b> Name of the server.</li>
            <li><b>Server Type:</b> Type of the server.</li>
            <li><b>Core Dump Path:</b> Path of the core dump as set in coreadm utility.</li>
            <li><b>Path Type:</b> Type of the path (shared/local).</li>
            <li><b>Allocated Memory(GB):</b> Memory allocated to core dump file system.</li>
            <li><b>Used Memory(GB):</b> Memory occupied by existing core dump files on the ENIQ server.</li>
            <li><b>Number of Core Dump Files:</b> Number of existing core dump files on the ENIQ server.</li>
        </ol>
EOT;
    drawHeaderWithHelp("Core Dump Path", 2, "coredumpPathHelp", $coredumpPathHelp);

    $coredumpPathTable = new HTML_Table($coredumpPathAndDetailsTableAttributes);
    $coredumpPathTable->addRow( array('<b>Server Name</b>', '<b>Server Type</b>', '<b>Core Dump Path</b>', '<b>Path Type</b>', '<b>Allocated Memory(GB)</b>', '<b>Used Memory(GB)</b>', '<b>Number of Core Dump Files</b>') );
    $requiredTable = "eniq_coredump_path, servers";
    $requiredColumn = "hostname, CAST(servers.type AS char) AS type, coredumpPath, coredumpPathType, allocatedSpace, usedSpace, coredumpCount";
    $requiredWhereClause = "AND eniq_coredump_path.serverId IN ($arrServerId) AND eniq_coredump_path.serverId = servers.id AND eniq_coredump_path.siteId = sites.id AND eniq_coredump_path.date = '$date' ORDER BY coredumpPathType, coredumpPath, hostname";
    getCoredumpsDetails($requiredTable, $requiredColumn, $requiredWhereClause);
    $arrOfCoredumpPath = array();
    $arrOfPathType = array();
    while ($result = $statsDB->getNextNamedRow()) {
        $arrOfCoredumpPath[] = $result['coredumpPath'];
        $arrOfPathType[] = $result['coredumpPathType'];
        $coredumpPathTable->addRow( array($result['hostname'], $result['type'], $result['coredumpPath'], $result['coredumpPathType'], chop($result['allocatedSpace'], "G"), round($result['usedSpace']/(1024*1024*1024), 2), $result['coredumpCount']) );
    }
    getSpannedCoredumpTableData($arrOfCoredumpPath, $arrOfPathType, coredumpPathFlag, $coredumpPathTable, 2, 6);
    echo $coredumpPathTable->toHTML();
    echo "<br>";

    if($localPathRowCount > 0) {
        $localPathCoredumpDetailsHelp = <<<EOT
            The following table displays details about the existing core dumps at individual server's local path on the system for a selected date.
            <p>
            <ol>
                <li><b>Server Name:</b> Name of the server.</li>
                <li><b>Server Type:</b> Type of the server.</li>
                <li><b>Core Dump Path:</b> Path of the core dump as set in coreadm utility.</li>
                <li><b>Core Dump Name:</b> Name of the core dump file.</li>
                <li><b>Creation Time:</b> Time when the core dump file was created on the system.</li>
            </ol>
EOT;
        drawHeaderWithHelp("Local Path Core Dump Details", 2, "localPathCoredumpDetailsHelp", $localPathCoredumpDetailsHelp);

        echo $localPathCoredumpDetailsTable->toHTML();
        echo "<br>";
    }

    if($sharedPathRowCount > 0) {
        $sharedPathCoredumpDetailsHelp = <<<EOT
            The following table displays details about the existing core dumps at shared path on the system for a selected date.
            <p>
            <ol>
                <li><b>Core Dump Path:</b> Path of the core dump as set in coreadm utility.</li>
                <li><b>Core Dump Name:</b> Name of the core dump file.</li>
                <li><b>Creation Time:</b> Time when the core dump file was created on the system.</li>
            </ol>
EOT;
        drawHeaderWithHelp("Shared Path Core Dump Details", 2, "sharedPathCoredumpDetailsHelp", $sharedPathCoredumpDetailsHelp);

        echo $sharedPathCoredumpDetailsTable->toHTML();
    }
}

$coredumpHistoryDetailsHelp = <<<EOT
    This displays information about the history of core dump files till last 6 months from the selected date on ENIQ deployment.
    <p>
    <b>Note:</b> The history includes the information of existing core dumps on the system as well as the deleted core dumps from the system.
    <p>
    <ol>
        <li><b>Collection Time:</b> Time when the core dump data was last collected by DDC on ENIQ server.</li>
        <li><b>Server Name:</b> Name of the server.</li>
        <li><b>Server Type:</b> Type of the server.</li>
        <li><b>Core Dump Name:</b> Name of the core dump file.</li>
        <li><b>Core Dump Size(GB):</b> Size of core dump file on the ENIQ server.</li>
        <li><b>Creation Time:</b> Time when the core dump file was created on the system.</li>
    </ol>
EOT;
drawHeaderWithHelp("Core Dump History Details", 2, "coredumpHistoryDetailsHelp", $coredumpHistoryDetailsHelp);

class CoredumpHistoryDetailsTable extends DDPObject {
    var $cols = array(
        'collectionTime'        => 'Collection Time',
        'hostname'              => 'Server Name',
        'type'                  => 'Server Type',
        'coredumpName'          => 'Core Dump Name',
        'coredumpSize'          => 'Core Dump Size(GB)',
        'coredumpCreationTime'  => 'Creation Time'
    );

    function __construct() {
        parent::__construct("CoredumpHistoryDetailsTable");
    }

    function getData() {
        global $date, $site;
        $beforeDate = date('Y-m-d', strtotime('-6 month', strtotime($date)));
        $sql = "
            SELECT
             DISTINCT collectionTime, hostname, CAST(servers.type AS char) AS type, coredumpName, round(coredumpSize/(1024*1024*1024), 2) as coredumpSize, coredumpCreationTime
            FROM
             eniq_coredump, sites, servers
            WHERE
             sites.name = '$site' AND
             eniq_coredump.siteId = sites.id AND
             eniq_coredump.serverId = servers.id AND
             eniq_coredump.collectionTime < '$date 00:00:00' AND
             eniq_coredump.collectionTime > '$beforeDate 00:00:00'
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

$coredumpHistoryDetails = new CoredumpHistoryDetailsTable();
echo $coredumpHistoryDetails->getSortableHtmlTable();

include "../common/finalise.php";
?>