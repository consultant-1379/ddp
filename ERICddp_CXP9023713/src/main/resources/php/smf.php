<?php
$pageTitle = "SMF Services";
include "common/init.php";
require_once 'HTML/Table.php';

$statsDB = new StatsDB();

#
# Main Flow
#

$serverDir="server";
if ( isset($_GET["serverdir"]) ) $serverDir=$_GET["serverdir"];

$webroot = $webroot . "/" . $serverDir;
$webargs = "site=$site&dir=$dir&date=$date&oss=$oss&serverdir=$serverDir";

//$rootdir = "/data/stats" . $webroot;
$rootdir = $rootdir . "/" . $serverDir;
if ( isset($_GET['server'])) { $hostname = $_GET['server'] ; $serverId = getServerId($statsDB,$site,$hostname); }
else { $serverId = getServerId($statsDB,$site,$rootdir); }

require_once PHP_ROOT . "/classes/DDPObject.class.php";
class SMFStatus extends DDPObject {
    var $cols = array(
        "smf" => "SMF Service",
        "status" => "Status"
    );

    var $defaultOrderBy = "smf";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("smf_status");
    }

    function getData() {
        global $serverId, $date;
        $sql = "SELECT smf_names.name AS smf, status FROM smf_status,smf_names WHERE serverid = " . $serverId . " AND
            date = '" . $date . "' AND smf_names.id = smf_status.smfid";
        $this->populateData($sql);
        return $this->data;
    }
}

class SMFEvents extends DDPObject {
    var $cols = array(
        "time" => "Time",
        "svc" => "Service",
        "event" => "Event",
        "reason" => "Reason",
        "status" => "Status"
    );

    var $defaultOrderBy = "time, sequenceid";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("svc_events_");
    }

    function getData() {
        global $serverId, $date;
        $sql = "SELECT time, smf_names.name AS svc, event, smf_reasons.name AS reason, status FROM smf_events, smf_reasons, smf_names
            WHERE time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND
            smf_events.smfid = smf_names.id AND
            serverid = " . $serverId . " AND smf_reasons.id = reasonid"; 
        $this->populateData($sql);
        return $this->data;
    }
}

class SMFDowntime extends DDPObject {
    var $cols = array(
        "svc" => "Service",
        "downtime" => "Downtime (seconds)"
    );

    var $defaultOrderBy = "downtime";
    var $defaultOrderDir = "DESC";

    function __construct() {
        parent::__construct("svc_downtime");
    }

    function getData() {
        global $serverId, $date;
        $sql = "SELECT smf_names.name AS svc, downtime FROM smf_downtime, smf_names WHERE
            date = '" . $date . "' AND serverid = " . $serverId . " AND
            smf_downtime.smfid = smf_names.id";
        $this->populateData($sql);
        return $this->data;
    }
}

$webargs = "site=$site&dir=$dir&date=$date&oss=$oss&server=$hostname";
?>
    <h1>SMF Services</h1>

<ul>
<li><a href="?<?=$webargs?>&view=status">Service Status</a></li>
<li><a href="?<?=$webargs?>&view=events">Service Events</a></li>
<li><a href="?<?=$webargs?>&view=downtime">Service Downtime</a></li>
</ul>
<?php
$view = "status";
if (isset($_GET['view'])) $view = $_GET['view'];
switch ($view) {
case "status":
    echo "<h2>SMF Service Status</h2>\n";
    $smfStatus = new SMFStatus();
    $smfStatus->getSortableHtmlTable();
    break;
case "events":
    echo "<h2>SMF Service Events</h2>\n";
    $evts = new SMFEvents();
    $evts->getHtmlTable();
    break;
case "downtime":
    echo "<h2>SMF Service Downtime</h2>\n";
    $smfDowntime = new SMFDowntime();
    $smfDowntime->getSortableHtmlTable();
    break;
}
include "common/finalise.php";
?>
