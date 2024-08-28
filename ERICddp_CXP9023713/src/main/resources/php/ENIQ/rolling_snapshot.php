<?php
$pageTitle = "Rolling Snapshot";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}

class RollingSnapshot extends DDPObject {
    var $cols;

    function __construct() {
        parent::__construct("Instance");
    }

    function getData() {
        global $date;
        global $site;
        global $serverName ;
        $sql = "
            SELECT
             rolling_snapshot_backup_metrics.successful_roll_snap_time
            FROM
             servers, rolling_snapshot_backup_metrics, sites
            WHERE
             sites.name = '$site' AND
             servers.hostname = '$serverName' AND
             rolling_snapshot_backup_metrics.siteid = sites.id AND
             servers.id = rolling_snapshot_backup_metrics.serverid AND
             rolling_snapshot_backup_metrics.successful_roll_snap_time <= '$date 23:59:59'
            ORDER BY
             successful_roll_snap_time DESC
            LIMIT 10
            ";
        $this->populateData($sql);
        return $this->data;
    }
}

$rollingHelp = <<<EOT
Rolling Snapshot Analysis displays the time when the Rolling Snapshot was successful on ENIQ server.
EOT;
drawHeaderWithHelp("Rolling Snapshot Analysis", 1, "rollingHelp", $rollingHelp);

$rollingSnapshotTableHelp = <<<EOT
The below tables will show maximum last ten backup time from the selected date where snapshot is available for each ENIQ server.
EOT;
drawHeaderWithHelp("Server wise backups", 2, "rollingSnapshotTableHelp", $rollingSnapshotTableHelp);

$statsDB->query("
    SELECT
    DISTINCT(servers.hostname), servers.type
    FROM
     servers, rolling_snapshot_backup_metrics, sites
    WHERE
     sites.name = '$site' AND
     sites.id = rolling_snapshot_backup_metrics.siteid AND
     servers.id = rolling_snapshot_backup_metrics.serverid
    ");

while ( $serverListRow = $statsDB->getNextRow() ) {
    $serverName = $serverListRow[0];
    $rollingsnapshot = new RollingSnapshot();
    $rollingsnapshot->cols = array('successful_roll_snap_time' => "$serverName:$serverListRow[1]");
    $rollingsnapshot->getHtmlTable();
    echo "<br>";
}

include "../common/finalise.php";
?>
