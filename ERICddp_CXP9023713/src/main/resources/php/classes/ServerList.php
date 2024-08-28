<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/common/functions.php";

class ServerList extends DDPObject {

    var $title = "Servers";
    var $cols = array(
        "hostname" => "Server",
        "type" => "Type",
        "server" => "",
        "storage" => "",
    );

    // Override defaults for these
    var $defaultLimit = "";
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "type ASC,hostname";

    function __construct() {
        parent::__construct("serverlist");
    }

    function getData($site = SITE, $date = DATE) {
         global $oss;

         if ( isset($_GET[$this->id . "_orderby"]) && $_GET[$this->id . "_orderby"] == "type" ) {
            $_GET[$this->id . "_orderby"] = "type " . $_GET[$this->id . "_orderdir"] . ", hostname";
        }

        $sql = "
SELECT servers.hostname AS hostname, CAST(servers.type AS char) AS type
FROM servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 servers.type NOT IN ('ENM_VM','NETSIM')";
        $this->populateData($sql);

        if ($oss === 'eniq') {
            $sql = "SELECT DISTINCT(servers.hostname) AS hostname, CAST(servers.type AS char) AS type
                FROM servers,windows_processor_details,sites WHERE
                windows_processor_details.serverid = servers.id AND
                windows_processor_details.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND
                servers.siteid = sites.id AND
                sites.name = '" . $site . "'";
            $this->populateData($sql);

            $sql = "SELECT DISTINCT(servers.hostname) AS hostname, CAST(servers.type AS char) AS type
                FROM servers,ocs_processor_details,sites WHERE
                ocs_processor_details.serverid = servers.id AND
                ocs_processor_details.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND
                servers.siteid = sites.id AND
                sites.name = '" . $site . "'";
            $this->populateData($sql);
        }
        $newData = array();
        foreach ($this->data as $key => $d) {
            # add in links to topn, server and cron
            $hostname = $d['hostname'];
            if ($d['type'] == "NetAnServer" || $d['type'] == "BIS") {
                $d['server'] = makeLink(
                    "/ENIQ/WindowsServer.php",
                    "server",
                    array('server' => $hostname, 'type' => $d['type'])
                );
                $d['storage'] = makeLink("/ENIQ/WindowsStorage.php", "storage", array('server' => $hostname));
            }
            elseif ($d['type'] == "OCS_ADDS" || $d['type'] == "OCS_CCS" ||
                $d['type'] == "OCS_VDA" || $d['type'] == "OCS") {
                $d['server'] = makeLink(
                    "/ENIQ/ocsServer.php",
                    "server",
                    array('server' => $hostname, 'ocsType' => $d['type'])
                );
                $d['storage'] = makeLink(
                    "/ENIQ/ocsStorage.php",
                    "storage",
                    array('server' => $hostname, 'ocsType' => $d['type'])
                );
            }
            else {
               $d['server'] = makeLink("/server.php", "server", array('server' => $hostname));
               $d['storage'] = makeLink("/storage.php", "storage", array('server' => $hostname));
            }
            $newData[] = $d;
        }

        $this->data = $newData;
        return $this->data;
    }
}
?>
