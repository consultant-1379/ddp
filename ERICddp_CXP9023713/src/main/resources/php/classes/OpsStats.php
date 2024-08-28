<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class OpsStats extends DDPObject {

    var $title = "OPS Stats";
    var $cols = array(
        "name" => "Script Name",
        "starttime" => "Start Time",
        "endtime" => "End Time",
        "username" => "User",
        "total" => "Total Time (Secs)",
        "cpu" => "CPU Usage (User+System) Secs"
    );

    // Override defaults for these
    var $defaultLimit = 20;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "name";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
        function __construct() {
        // pass an id into the parent constructor
        parent::__construct("ops_stats");
    }


        function getData($site = SITE, $date = DATE) {
            $sql = "SELECT ops_scriptnames.name AS name, " .
                "oss_users.name AS username, " .
                "ops_instrumentation.start_time AS starttime, " .
                "ops_instrumentation.end_time AS endtime, " .
                "TIMESTAMPDIFF(SECOND,ops_instrumentation.start_time,ops_instrumentation.end_time) AS total, " .
                "ops_instrumentation.cpuusage AS cpu " .
                "FROM ops_instrumentation, ops_scriptnames, oss_users, sites " .
                "WHERE ops_instrumentation.ops_script_id=ops_scriptnames.id " .
                "AND oss_users.id=ops_instrumentation.userid " .
                "AND ops_instrumentation.end_time BETWEEN '" . $date . " 00:00:00" . "' AND '" . $date . " 23:59:59" . "' " .
                "AND ops_instrumentation.siteid = sites.id " .
                "AND sites.name = '" . $site . "'" .
                "GROUP BY starttime, endtime";

            $this->populateData($sql);
            return $this->data;
        }
}
?>
