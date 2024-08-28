<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class EAMCommandInitiator extends DDPObject {

    var $title = "EAM Command Initiators";
    var $cols = array(
        "initiator" => "Command Initiator Name",
        "count" => "Number of configured initiators"
    );

    // Override defaults for these
    var $defaultLimit = 25;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "initiator";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
        function __construct() {
        parent::__construct("eam_cmd_init");
    }

    function getData($site = SITE, $date = DATE) {
        $sql = "SELECT eam_init_stats.cmd_responders_initiators AS initiator, eam_init_stats.count AS count " .
           "FROM eam_init_stats, sites " .
           "WHERE eam_init_stats.cmd_responders_initiators LIKE '%_in' AND " .
           "eam_init_stats.date='$date' AND " .
           "eam_init_stats.siteid=sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
        }
}
?>

<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class EAMCommandResponder extends DDPObject {

    var $title = "EAM Command Responders";
    var $cols = array(
        "responder" => "Command Responder Name",
        "count" => "Number of configured Responder"
    );

    // Override defaults for these
    var $defaultLimit = 25;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "responder";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
        function __construct() {
        parent::__construct("eam_cmd_resp");
    }

    function getData($site = SITE, $date = DATE) {
        $sql = "SELECT eam_init_stats.cmd_responders_initiators AS responder, eam_init_stats.count AS count " .
           "FROM eam_init_stats, sites " .
           "WHERE eam_init_stats.cmd_responders_initiators NOT LIKE '%_in' AND " .
           "eam_init_stats.date='$date' AND " .
           "eam_init_stats.siteid=sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
        }
}
?>

<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class EAMNETimeout extends DDPObject {

    var $title = "EAM NE Timeout";
    var $cols = array(
        "ne" => "Network Element",
        "connIdle" => "Connection Idle Timeout",
        "shortBuf" => "Short Buffer Timeout",
        "longBuf" => "Long Buffer Timeout"
    );

    // Override defaults for these
    var $defaultLimit = 25;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "ne";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
        function __construct() {
        parent::__construct("eam_ne_timeout");
    }

    function getData($site = SITE, $date = DATE) {
        $sql = "SELECT eam_ne_names.name AS ne, eam_ne_config.conn_idle_to AS connIdle, eam_ne_config.short_buf_to AS shortBuf, eam_ne_config.long_buf_to AS longBuf " .
           "FROM eam_ne_names,eam_ne_config, sites " .
           "WHERE eam_ne_names.id=eam_ne_config.neid AND " .
           "eam_ne_config.date='$date' AND " .
           "eam_ne_config.siteid=sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
        }
}
?>


