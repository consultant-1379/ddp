<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class ChaOverallStats extends DDPObject {

    var $title = "CHA Overall Statistics";
    var $cols = array(
        "cmd" => "Script Name",
        "type" => "Script Type",
        "userid" => "User",
        "count" => "Count",
        "max" => "Max time (Seconds)",
        "min" => "Min Time (Seconds)",
        "avg" => "Average Time (Seconds)",
	"total" => "Total Time (Seconds)",
    );

    // Override defaults for these
    var $defaultLimit = 25;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "cmd";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
        function __construct() {
        parent::__construct("cha_overall");
    }

    function getData($site = SITE, $date = DATE) {
        $sql = "SELECT cha_cmd_names.name AS cmd, cha_instrumentation.cmdtype AS type, oss_users.name AS userid, COUNT(cha_instrumentation.cmdid) AS count, " .
	   "MAX(TIMESTAMPDIFF(SECOND,cha_instrumentation.start_time,cha_instrumentation.end_time)) AS max, MIN(TIMESTAMPDIFF(SECOND,cha_instrumentation.start_time,cha_instrumentation.end_time)) AS min, " .
	   "AVG(TIMESTAMPDIFF(SECOND,cha_instrumentation.start_time,cha_instrumentation.end_time)) AS avg, SUM(TIMESTAMPDIFF(SECOND,cha_instrumentation.start_time,cha_instrumentation.end_time)) AS total " .
	   "FROM cha_cmd_names, cha_instrumentation, oss_users, sites " .
	   "WHERE cha_instrumentation.cmdid=cha_cmd_names.id AND oss_users.id=cha_instrumentation.uid AND " .
	   "cha_instrumentation.end_time BETWEEN '" . $date . " 00:00:00" . "' AND '" . $date . " 23:59:59" . "' AND " .
	   "cha_instrumentation.siteid=sites.id AND sites.name='" . $site ."' GROUP BY cha_instrumentation.cmdid";
        $this->populateData($sql);
        return $this->data;
        }
}
?>

<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class ChaIndividualStats extends DDPObject {

    var $title = "CHA Individual Statistics";
    var $cols = array(
        "cmd" => "Script Name",
        "type" => "Script Type",
        "userid" => "User",
        "startTime" => "Start Time",
        "endTime" => "End Time",
        "total" => "Total Time (Seconds)",
        "result" => "Result",
        "cpu" => "CPU Usage (User + System Secs)",
        "pr" => "PR Memory",
        "rss" => "RSS Memory"
    );

    // Override defaults for these
    var $defaultLimit = 25;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "type";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
function __construct()
{
    parent::__construct("job_super");
}

function getData($site = SITE, $date = DATE)
{
		$sql = "SELECT cha_cmd_names.name AS cmd, cha_instrumentation.cmdtype AS type, oss_users.name AS userid, cha_instrumentation.start_time AS startTime, cha_instrumentation.end_time AS endTime, " .
	               "TIMESTAMPDIFF(SECOND,cha_instrumentation.start_time,cha_instrumentation.end_time) AS total, cha_instrumentation.result AS result, cha_instrumentation.cpuusage AS cpu, " . 
                       "cha_instrumentation.prMem AS pr, cha_instrumentation.rssMem AS rss " .
                       "FROM cha_cmd_names, cha_instrumentation, oss_users, sites " .
                       "WHERE cha_instrumentation.start_time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND cha_instrumentation.cmdid=cha_cmd_names.id AND " .
                       "cha_instrumentation.siteid=sites.id AND sites.name='" . $site . "' AND " .
                       "cha_instrumentation.uid=oss_users.id ";
		$this->populateData($sql);
        return $this->data;
    }
}
?>
