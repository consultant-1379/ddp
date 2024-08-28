<?php
$pageTitle = "VCS Events";

include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

const DOWNTIME = 'downtime';
const TIMEFORMAT = '%02d:%02d:%02d';
const ONLINESTART = 'OnlineStart';

function drawLinks() {
    global $php_webroot, $webargs, $date, $statsDB;

    $args = explode("&", $webargs);
    $lastDate = date('Y-m-d', strtotime($date . '-31 days'));

    $links = array();
    $links[] = makeAnchorLink('vcsStatsHelp_anchor', 'VCS Event Details');
    if ( $statsDB->hasData('enm_vcs_events') ) {
        $links[] = makeAnchorLink('downtimeTotalsHelp_anchor', 'Downtime Totals');
    }
    $url = "$php_webroot/monthly/TOR/vcs_events.php?$args[0]&start=$lastDate&end=$date&$args[3]";
    $links[] = makeLinkForURL($url, 'Last 31 Day Summary');
    if ( $statsDB->hasData('enm_vm_hc') ) {
        $links[] = makeLink('/TOR/misc/serviceHc.php', 'Service Health Status');
    }

    echo makeHTMLList($links);
}

class EnmVcsEvents extends DDPObject {
    var $from;
    var $to;
    var $vcsDowntimeTotals = array();
    var $vcsOutageCounts = array();
    var $svcJmxLinks = array();
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'clustertype', 'label' => 'Cluster'),
                      array('key' => 'node', 'label' => 'Node'),
                      array('key' => 'service', 'label' => 'Resource'),
                      array('key' => 'eventtype', 'label' => 'Event'),
                      array('key' => DOWNTIME, 'label' => 'Downtime'),
                      array('key' => 'onlinetime', 'label' => 'Online Time'),
                     );
    function __construct($hostToNode, $svcActStandTypes, $svcAppCounts, $svcModelToSvcGrpMap, $hostsWithGenJmxData, $from, $to) {
        parent::__construct("enmvcsevents");
        $this->hostToNode = $hostToNode;
        $this->svcActStandTypes = $svcActStandTypes;
        $this->svcAppCounts = $svcAppCounts;
        $this->svcModelToSvcGrpMap = $svcModelToSvcGrpMap;
        $this->hostsWithGenJmxData = $hostsWithGenJmxData;
        $this->from = $from;
        $this->to = $to;
    }
    public function vcsEventTime($vcsEventTimeType, $vcsEvents, $row, $resourceKey ) {
        global $vcsEventTimeTypenHhMmSs;
        if ( isset($vcsEvents[$resourceKey]) && array_key_exists($vcsEventTimeType, $vcsEvents[$resourceKey]) ) {
            $vcsEventTimeTypeInSec = strtotime($row['time']) - strtotime($vcsEvents[$resourceKey][$vcsEventTimeType]);
            if ( array_key_exists($resourceKey, $this->vcsDowntimeTotals) ) {
                $this->vcsDowntimeTotals[$resourceKey] += $vcsEventTimeTypeInSec;
                $this->vcsOutageCounts[$resourceKey]++;
            } else {
                $this->vcsDowntimeTotals[$resourceKey] = $vcsEventTimeTypeInSec;
                $this->vcsOutageCounts[$resourceKey] = 1;
            }
            $vcsEventTimeTypenHhMmSs = sprintf(
                TIMEFORMAT,
                ($vcsEventTimeTypeInSec/3600),
                (($vcsEventTimeTypeInSec/60)%60),
                ($vcsEventTimeTypeInSec%60)
            );
            if ($vcsEventTimeType == ONLINESTART) {
                unset ($this->vcsDowntimeTotals[$resourceKey]);
            }
            return $vcsEventTimeTypenHhMmSs;
        }
        else {
            if ( isset($this->naAbbrv) ) {
                $vcsEventTimeTypenHhMmSs = $this->naAbbrv;
            }
            if ( isset($this->debug) && $this->debug ) {
                error_log("Unable to calculate downtime of " . $resourceKey .
                    " at " . $row['time'] . " as no " . $vcsEventTimeType . " found in the last 24 hrs");
            }
            return $vcsEventTimeTypenHhMmSs;
        }
    }
    function getData() {
        global $site, $debug, $oss, $dir, $date, $yDate, $naAbbrv;
        $sql = "
SELECT
    enm_vcs_events.time AS time,
    enm_vcs_events.clustertype AS clustertype,
    servers.hostname AS server,
    enm_cluster_svc_names.name AS service,
    enm_cluster_svc_app_ids.name AS appid,
    enm_vcs_events.eventtype AS eventtype,
    vms.hostname AS service_hostname,
    ' NA' AS downtime,
    ' NA' AS onlinetime
FROM
    enm_vcs_events
    JOIN sites
        ON enm_vcs_events.siteid = sites.id
    JOIN servers
        ON enm_vcs_events.serverid = servers.id
    JOIN enm_cluster_svc_names
        ON enm_vcs_events.serviceid = enm_cluster_svc_names.id
    JOIN enm_cluster_svc_app_ids
        ON enm_vcs_events.appid = enm_cluster_svc_app_ids.id
    -- join to find out the hostname of the vm
    LEFT JOIN (
        -- This sub query gets a table mapping hostserverid & serviceid to hostname
        SELECT DISTINCT
            enm_cluster_svc.hostserverid AS hostserverid,
            enm_cluster_svc.serviceid AS serviceid,
            servers.hostname AS hostname
        FROM enm_cluster_svc
            JOIN servers
                ON enm_cluster_svc.vmserverid = servers.id
            JOIN sites -- The join onto sites and the where clause are for improving performance
                ON sites.id = enm_cluster_svc.siteid
        WHERE
            ( enm_cluster_svc.date = '$yDate' OR enm_cluster_svc.date = '$date' )
            AND sites.name = '$site'
    )  AS vms
        ON vms.hostserverid = enm_vcs_events.serverid
        AND vms.serviceid = enm_vcs_events.serviceid
WHERE
    sites.name = '$site'
    AND enm_vcs_events.time BETWEEN '$yDate 00:00:00' AND '$date 23:59:59'
ORDER BY time, eventtype";

        $freeze="freeze persistent evacuate";
        $unfreeze="unfreeze persistent";
        $this->populateData($sql);
        $this->columnTypes['node'] = 'string';
        $newData = array();
        $vcsEvents = array();

        foreach ($this->data as &$row) {
            $clustertype = $row['clustertype'];
            $hostname = $row['server'];
            $service = $row['service'];
            $appid = $row['appid'];
            $service_host = $row['service_hostname'];
            $eventtype = $row['eventtype'];
            if ( array_key_exists($hostname, $this->hostToNode) ) {
                $node = $this->hostToNode[$hostname];
            } else {
                $node = $hostname;
            }
            $resourceKey = '';
            if ( $eventtype  == $freeze || $eventtype == $unfreeze ) {
                $row['eventtype'] = "Command: " . $row['eventtype'];
            } else {
                # To uniquely distinguish the services with more than one application under them
                #  append the service name with the application id for such multi-app services
                $row['service'] = $service;
                if ( array_key_exists($service, $this->svcAppCounts) && $this->svcAppCounts[$service] > 1 ) {
                    $row['service'] .= '_' . $appid;
                }
                # Use both server and service names to identify VCS events of a parallel service
                #  (eg: mscm) while use the service name alone to identify the events of a fail-over
                #  service (eg: elasticsearch)
                $resourceKey = $clustertype . '@@' . $hostname . '@@' . $row['service'];
                if ( array_key_exists($service, $this->svcActStandTypes) && $this->svcActStandTypes[$service] == 1 ) {
                    $resourceKey = $clustertype . '@@' . 'NA' . '@@' . $row['service'];
                }
                if ( $eventtype == 'OfflineCompleted'
                    || $eventtype == 'CleanCompleted'
                    || $eventtype == ONLINESTART
                ) {
                    $vcsEvents[$resourceKey][$eventtype] = $row['time'];
                } else if ( $eventtype == 'OnlineCompleted' || $eventtype == 'RestartCompleted' ) {
                    $offlineEventType = '';
                    $onlineStartEvent = '';
                    if ( $eventtype == 'OnlineCompleted' ) {
                        $offlineEventType = 'OfflineCompleted';
                        $onlineStartEvent = 'OnlineStart';
                        $naAbbrv = ' NOCEF';
                    } else {
                        $offlineEventType = 'CleanCompleted';
                        $naAbbrv = ' NCCEF';
                    }
                    # If the resource came 'ONLINE' the previous day then just ignore it. If it
                    #  came 'ONLINE' today then calculate the downtime
                    if ( !(strpos($row['time'], $yDate)) ) {
                        $row['onlinetime'] = $this->vcsEventTime($onlineStartEvent, $vcsEvents, $row, $resourceKey);
                        $row[DOWNTIME] = $this->vcsEventTime($offlineEventType, $vcsEvents, $row, $resourceKey);
                    }
                }
            }
            if ( strpos($row['time'], $yDate) !== false ) {
                continue;
            }
            # Get the new 'SERVICE_GROUP' based service name, if it exists. Otherwise use the old
            # 'LITP model' based service name while generating generic JMX links
            $svcGrpName = $service;
            if ( array_key_exists($service, $this->svcModelToSvcGrpMap) ) {
                $svcGrpName = $this->svcModelToSvcGrpMap[$service];
            }
            # If we have generic JMX data generate the link parameter to display it
            $linkNames = null;
            $genJmxDataExists = false;
            if ( ! is_null($service_host) && array_key_exists("{$service_host}@{$svcGrpName}", $this->hostsWithGenJmxData) ) {
                $genJmxDataExists = true;
            } else if ( is_null($service_host) && array_key_exists($svcGrpName, $this->hostsWithGenJmxData) ) {
                $genJmxDataExists = true;
            }
            if ( $genJmxDataExists || $row['clustertype'] == 'SCRIPTING' ) {
                if ( ! is_null($service_host) ) {
                    # If has a vm use "host,service"
                    $linkNames = "&names=$service_host,$svcGrpName";
                } else {
                    # Otherwise just use the service (e.g. Elasticsearch and JMS)
                    $linkNames = "&name=$svcGrpName";
                }
            }
            # If we have linkNames we build up the link around them
            if ( !is_null($linkNames) ) {
                $row['service'] = "<span id='svcname-" . $service . "' title='Click here to see the generic JMX information for this service i.e. heap, threads, "
                    . "CPU & GC.'><a href='../genjmx.php?site=" . $site . "&dir=" . $dir . "&date=" . $date . "&oss=" . $oss . "$linkNames'>" . $row['service'] . "</a></span>";
            } else {
                $row['service'] = "<span id='svcname-" . $service . "'>" . $row['service'] . "</span>";
            }
            if ( $resourceKey != '' ) {
                $this->svcJmxLinks[$resourceKey] = $row['service'];
            }
            $row['node'] = "<span title='Click here to see the information for the server $hostname on " . '(' . $node . ')'
                . "  i.e. CPU, memory & network interfaces.'><a href='../server.php?dir=" . $dir . "&date=" . $date . "&oss=" . $oss . "&site=" . $site . "&server=" . $hostname . "'>"
                . $node . "</a></span>";

            $row['eventtype'] = "<span id=".$eventtype."_id>".$eventtype."</span>";
            $newData[] = $row;
        }
        $this->data = $newData;
        if ( $debug ) {
            echo "<pre>getData: data\n";
            print_r($this->data);
            echo "</pre>\n";
        }
        return $this->data;
    }
}

class DowntimeTotals extends DDPObject {
    var $cols = array(
                      array('key' => 'cluster', 'label' => 'Cluster'),
                      array('key' => 'node', 'label' => 'Node'),
                      array('key' => 'service', 'label' => 'Resource'),
                      array('key' => 'outagecount', 'label' => 'Outage Count'),
                      array('key' => 'totaldowntime', 'label' => 'Total Downtime')
                     );
    var $defaultOrderBy = "totaldowntime";
    var $defaultOrderDir = "DESC";

    function __construct($data = array()) {
        parent::__construct("downtimetotals");
        $this->data = array_values($data);
        $this->columnTypes = array(
            "cluster" => "string",
            "node" => "string",
            "service" => "string",
            "outagecount" => "int",
            "totaldowntime" => "string"
        );
    }

    function getData() {
        return $this->data;
    }
}


if (isset($_GET["from"])) {
  $from = $_GET["from"];
  $to = $_GET["to"];
} else {
  $from = "$date 00:00:00";
  $to = "$date 23:59:59";
}

# Get the mapping between the given hosts and their node types
$statsDB = new StatsDB();
$hostToNode = array();
$statsDB->query("
SELECT
    servers.hostname,
    enm_cluster_host.nodename
FROM
    enm_cluster_host,
    servers,
    sites
WHERE
 enm_cluster_host.siteid = sites.id AND sites.name = '$site' AND
 enm_cluster_host.serverid = servers.id AND
 enm_cluster_host.date = '$date'");
while ($row = $statsDB->getNextRow()) {
    $hostToNode[$row[0]] = $row[1];
}

# Get the mapping between services and their 'standby' states
$svcActStandTypes = array();
$statsDB->query("
SELECT
    DISTINCT enm_cluster_svc_names.name,
    enm_cluster_svc.actstand
FROM
    enm_cluster_svc,
    enm_cluster_svc_names,
    sites
WHERE
    enm_cluster_svc.serviceid = enm_cluster_svc_names.id AND
    enm_cluster_svc.siteid = sites.id AND
    ( enm_cluster_svc.date = '$yDate' OR enm_cluster_svc.date = '$date' ) AND
    sites.name = '$site'");
while ($row = $statsDB->getNextRow()) {
    $svcActStandTypes[$row[0]] = $row[1];
}

# Get the mapping between services and their application counts
$svcAppCounts = array();
$statsDB->query("
SELECT
    enm_cluster_svc_names.name,
    COUNT(DISTINCT enm_cluster_svc.appid) as app_count
FROM
    enm_cluster_svc,
    enm_cluster_svc_names,
    enm_cluster_svc_app_ids,
    sites
WHERE
    enm_cluster_svc.serviceid = enm_cluster_svc_names.id AND
    enm_cluster_svc.siteid = sites.id AND
    enm_cluster_svc.appid = enm_cluster_svc_app_ids.id AND
    enm_cluster_svc_app_ids.name != 'NA' AND
    enm_cluster_svc.date = '$date' AND
    sites.name = '$site'
GROUP BY
    enm_cluster_svc.serviceid");
while ($row = $statsDB->getNextRow()) {
    $svcAppCounts[$row[0]] = $row[1];
}

# Get the mapping between the old 'LITP model' based service names and the new 'SERVICE_GROUP'
# based service names
$svcModelToSvcGrpMap = array();
$statsDB->query("
SELECT
    distinct enm_cluster_svc_names.name,
    enm_servicegroup_names.name
FROM
    enm_servicegroup_instances,
    enm_servicegroup_names,
    enm_cluster_svc,
    enm_cluster_svc_names,
    sites
WHERE
    enm_servicegroup_instances.serviceid = enm_servicegroup_names.id AND
    enm_servicegroup_instances.serverid = enm_cluster_svc.vmserverid AND
    enm_cluster_svc.serviceid = enm_cluster_svc_names.id AND
    enm_servicegroup_instances.siteid = sites.id AND
    enm_cluster_svc.siteid = sites.id AND
    enm_servicegroup_instances.date = '$date' AND
    enm_cluster_svc.date = '$date' AND
    sites.name = '$site'");
while ($row = $statsDB->getNextRow()) {
    $svcModelToSvcGrpMap[$row[0]] = $row[1];
}

# Get the hosts and their jmx names for which Generic JMX data exists on the given day
$hostsWithGenJmxData = array();
$jvmInstances = getGenJmxJvms();
foreach ($jvmInstances as $jvmInstance) {
    $key = $jvmInstance['servername'] . '@' . $jvmInstance['jvmname'];
    $hostsWithGenJmxData[$key] = 1;
    $hostsWithGenJmxData[$jvmInstance['jvmname']] = 1;
}

echo "<h1>VCS Events</h1>\n";
drawLinks();

drawHeaderWithHelp("VCS Event Details", 2, "vcsStatsHelp", "DDP_Bubble_205_ENM_VCS_Event_Details");
$enmVcsEvents = new EnmVcsEvents($hostToNode, $svcActStandTypes, $svcAppCounts, $svcModelToSvcGrpMap, $hostsWithGenJmxData, $from, $to);
echo $enmVcsEvents->getCount($enmVcsEvents->getData()) > 25
     ? $enmVcsEvents->getClientSortableTableStr(25, array(50, 100, 1000))
     : $enmVcsEvents->getClientSortableTableStr();

if ( $statsDB->hasData('enm_vcs_events') ) {
    drawHeaderWithHelp("Downtime Totals", 2, "downtimeTotalsHelp", "DDP_Bubble_206_ENM_VCS_Resource_Downtime_Totals");
    $downtimeTotalsData = array();
    arsort($enmVcsEvents->vcsDowntimeTotals, SORT_NUMERIC);
    foreach ($enmVcsEvents->vcsDowntimeTotals as $resKey => $totalDowntimeInSec) {
        if ( preg_match("/^(.*)@@(.*)@@(.*)$/", $resKey, $resProperties) ) {
            $nodeServerInfoLink = 'NA';
            if ( $resProperties[2] != 'NA' ) {
                $nodeServerInfoLink = "<span title='Click here to see the information for the server "
                    . $resProperties[2] . " on (". $hostToNode[$resProperties[2]]
                    . ") i.e. CPU, memory & network interfaces.'><a href='../server.php?dir=" . $dir
                    . "&date=" . $date . "&oss=" . $oss . "&site=" . $site . "&server=" . $resProperties[2] . "'>"
                    . $hostToNode[$resProperties[2]] . "</a></span>";
            }
            $vcsservice='';
            if ( isset($enmVcsEvents->svcJmxLinks[$resKey]) ) {
                $vcsservice = $enmVcsEvents->svcJmxLinks[$resKey];
            }
            $downtimeTotalsData[] = array(
                'cluster' => $resProperties[1],
                'node' => $nodeServerInfoLink,
                'service' => $vcsservice,
                'outagecount' => $enmVcsEvents->vcsOutageCounts[$resKey],
                'totaldowntime' => sprintf(
                    TIMEFORMAT,
                    ($totalDowntimeInSec/3600),
                    (($totalDowntimeInSec/60)%60),
                    ($totalDowntimeInSec%60))
                );
        } else {
            continue;
        }
    }
    $downtimeTotals = new DowntimeTotals($downtimeTotalsData);
    echo $downtimeTotals->getCount($downtimeTotals->getData()) > 25
        ? $downtimeTotals->getClientSortableTableStr(25, array(50, 100))
        : $downtimeTotals->getClientSortableTableStr();
}

include PHP_ROOT . "/common/finalise.php";
?>
