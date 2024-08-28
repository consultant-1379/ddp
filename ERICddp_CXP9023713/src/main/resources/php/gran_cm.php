<?php
$pageTitle = "GRAN CM Activities";
include "common/init.php";
if (isset($_GET['start'])) {
    $start = $_GET['start'];
} else {
    $start = $date . " 00:00:00";
}
if (isset($_GET['end'])) {
    $end = $_GET['end'];
} else {
    $end = $date . " 23:59:59";
}
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class GranCmData extends DDPObject {
    var $cols = array(
        "start" => "Start Time",
        "end" => "End Time",
        "duration" => "Duration",
        "activity" => "Activity",
        "args" => "Command-line Arguments",
        "status" => "Status",
        "reason" => "Reason",
        "user" => "User"
    );

    var $actfilter = "";
    var $defaultOrderBy = "start";
    var $defaultOrderDir = "ASC";

    function __construct($type) {
        parent::__construct($type . "_activities");
        $this->actfilter = "SUBSTR(activity, 1, 3) = '" . $type . "'";
    }

    function getData() {
        global $site, $start, $end;
        $sql = "SELECT start,end,TIMEDIFF(end,start) AS duration,activity,args,
            status,reason,oss_users.name AS user FROM
            gran_cm_activities,sites,oss_users WHERE
            gran_cm_activities.siteid = sites.id
            AND sites.name = '" . $site . "'
            AND start BETWEEN '" . $start . "' AND '" . $end . "'
            AND gran_cm_activities.userid = oss_users.id
            AND " . $this->actfilter;
        $this->populateData($sql);
        return $this->data;
    }
}

$cna = new GranCmData("cna");
$bsm = new GranCmData("bsm");
?>
<h1>GRAN CM Activities</h1>
<ul>
<li><a href=#cna>CNA Activities</a></li>
<li><a href=#bsm>BSM Activities</a></li>
</ul>
<a name=cna /><h2>CNA Activities</h2>
<?php
$cna->getSortableHtmlTable();
?>
<a name=bsm /><h2>BSM Activities</h2>
<?php
$bsm->getSortableHtmlTable();

include "common/finalise.php";
?>
