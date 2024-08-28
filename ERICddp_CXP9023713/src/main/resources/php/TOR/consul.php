<?php
$pageTitle = "Consul & SAM";

$YUI_DATATABLE = true;

include_once "../common/init.php";

require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class ConsulSamEvents extends DDPObject {
    var $cols = array(
                      array('key' => 'timestamp', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'type', 'label' => 'Type'),
                      array('key' => 'member', 'label' => 'Member'),
                      array('key' => 'message', 'label' => 'Message')
                      );

    function __construct() {
        parent::__construct("ConsulSamEvents");
    }

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_consul_n_sam_events.time AS 'timestamp',
    enm_consul_n_sam_events.event_type AS 'type',
    servers.hostname AS 'member',
    enm_consul_event_names.name AS 'message'
FROM
    enm_consul_n_sam_events,
    enm_consul_event_names,
    servers,
    sites
WHERE
    enm_consul_n_sam_events.siteid = sites.id AND
    enm_consul_n_sam_events.serverid = servers.id AND
    enm_consul_n_sam_events.event_id = enm_consul_event_names.id AND
    servers.siteid = sites.id AND
    sites.name = '$site' AND
    enm_consul_n_sam_events.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time, time_only_millisec, hostname";

        $this->populateData($sql);
        $this->defaultOrderBy = "timestamp";
        $this->defaultOrderDir = "ASC";

        foreach ($this->data as &$event) {
            if ( preg_match('/^\s*(HcFailed\s*:\s*)(.*)$/', $event['message'], $matches) ) {
                $event['message'] = $matches[1] . basename($matches[2]);
            }
        }

        return $this->data;
    }
}


function main() {
    global $site, $date, $rootdir, $webargs, $oss;

    echo "<H2>Consul & SAM</H2>\n";
    echo "<ul>\n";
    echo " <li><a href='#consulSamEventsHelp_anchor'>Consul/SAM Events</a></li>\n";
    $analysisStartDate = date( 'Y-m-d', strtotime($date . '-31 days') );
    echo " <li><a href=\"" . PHP_WEBROOT . "/monthly/TOR/consul_n_sam_events.php?site=$site&start=$analysisStartDate" .
         "&eventName=Consul/SAM&end=$date&oss=$oss&\">Last 31 Day Summary</a></li>";

    echo "</ul>\n";

    # Display 'Consul/SAM Events' table
    $consulSamEventsObj = new ConsulSamEvents();
    drawHeaderWithHelp("Consul/SAM Events", 2, "consulSamEventsHelp", "DDP_Bubble_382_vENM_Consul_N_SAM_Events");
    echo $consulSamEventsObj->getCount($consulSamEventsObj->getData()) > 20
         ? $consulSamEventsObj->getClientSortableTableStr(20, array(50, 100))
         : $consulSamEventsObj->getClientSortableTableStr();
}

main();

include_once PHP_ROOT . "/common/finalise.php";
