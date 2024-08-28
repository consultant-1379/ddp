<?php
$pageTitle = "OMBS Backup";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}

class OmbsBackup extends DDPObject {
    var $cols;

    function __construct() {
        parent::__construct("Instance");
    }

    function getData() {
        global $date;
        global $site;
        global $serverName;
        $sql = "
            SELECT
             ombs_backup_metrics.successful_backup_time
            FROM
             ombs_backup_metrics, sites, servers
            WHERE
             sites.name = '$site' AND
             ombs_backup_metrics.siteid = sites.id AND
             servers.id = ombs_backup_metrics.serverid AND
             servers.hostname = '$serverName' AND
             ombs_backup_metrics.successful_backup_time <= '$date 23:59:59'
            ORDER BY
             successful_backup_time DESC
            LIMIT 10
            ";
        $this->populateData($sql);
        return $this->data;
    }
}

$ombsHelp = <<<EOT
OMBS Backup Analysis displays the time when OMBS backup was initiated on ENIQ and NetAn server.
<p>
    <b>Note</b>: These timings do not indicate successful backup.
EOT;
drawHeaderWithHelp("OMBS Backup Analysis", 1, "ombsHelp", $ombsHelp);

$ombsBackupTableHelp = <<<EOT
The below tables will show maximum last ten OMBS backup initiation time from the selected date for each ENIQ and NetAn server.
EOT;
drawHeaderWithHelp("Server wise backups", 2, "ombsBackupTableHelp", $ombsBackupTableHelp);

$statsDB->query("
    SELECT
     DISTINCT(servers.hostname), servers.type
    FROM
     ombs_backup_metrics, sites, servers
    WHERE
     sites.name = '$site' AND
     ombs_backup_metrics.siteid = sites.id AND
     ombs_backup_metrics.serverid = servers.id
    ");

while ( $serverListRow = $statsDB->getNextRow() ) {
    $serverName = $serverListRow[0];
    $ombsBackupDates = new OmbsBackup();
    $ombsBackupDates->cols = array('successful_backup_time' => "$serverName:$serverListRow[1]");
    $ombsBackupDates->getHtmlTable();
    echo "<br>";
}

include "../common/finalise.php";
?>
