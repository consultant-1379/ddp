<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class CrontabStats extends DDPObject {

    var $title = "Cron Stats";
    var $cols = array(
        "name" => "CMD Name",
        "cmdcount" => "Count",
    );

    // Override defaults for these
    var $defaultLimit = 20;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "cmdcount";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
    private $host = "";
    function __construct($hostname) {
        // pass an id into the parent constructor
        $this->host = $hostname;
        parent::__construct("cron_stats");
    }


    function getData($site = SITE, $date = DATE) {
                $sql = <<<ESQL
SELECT
 process_names.name AS name,
 crontabs.execs AS cmdcount
FROM crontabs,process_names
WHERE
 crontabs.process_name_id=process_names.id AND
 crontabs.date = '$date' AND
 crontabs.serverid = (
   SELECT servers.id
   FROM servers, sites
   WHERE
      servers.siteid=sites.id AND
      sites.name= '$site' AND servers.hostname='$this->host' )
GROUP BY crontabs.process_name_id
ESQL;
        $this->populateData($sql);
        return $this->data;
    }
}
