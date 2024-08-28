<?php
$pageTitle = "Cluster";


include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

require_once 'HTML/Table.php';

$serversBySG = array();

$statsDB = new StatsDB();

$k8sPods = array();
$statsDB->query("
SELECT DISTINCT serverid
FROM k8s_pod_cadvisor, sites
WHERE
 k8s_pod_cadvisor.siteid = sites.id AND sites.name = '$site' AND
 k8s_pod_cadvisor.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
while ($row = $statsDB->getNextRow()) {
  $k8sPods[] = $row[0];
}

$vmServers = array();
$statsDB->query("
SELECT servers.id, servers.hostname
FROM sites, servers
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.type = 'ENM_VM'");
while ($row = $statsDB->getNextRow()) {
  $vmServers[$row[0]] = $row[1];
}

$jmxNames = array();
$jvmInstances = getGenJmxJvms();
$allJvmNames = array();
foreach ($jvmInstances as $jvmInstance) {
    $allJvmNames[] = $jvmInstance['jvmname'];
}
$jmxNames = array_unique($allJvmNames);
if ( $debug > 0 ) { echo "<pre>\n"; echo "jmxNames:\n"; print_r($jmxNames); echo "</pre>\n"; }

$jbossSrvIds = array();
$statsDB->query("
SELECT DISTINCT(serverid)
FROM enm_jboss_threadpools, sites
WHERE
    enm_jboss_threadpools.siteid = sites.id AND sites.name = '$site' AND
    enm_jboss_threadpools.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
while ($row = $statsDB->getNextRow()) {
    $jbossSrvIds[$row[0]] = 1;
}
if ( $debug > 0 ) { echo "<pre>\n"; echo "jbossSrvIds:\n"; print_r($jbossSrvIds); echo "</pre>\n"; }

$statsDB->query("
SELECT enm_servicegroup_names.name, servers.hostname, servers.id
FROM enm_servicegroup_instances, enm_servicegroup_names, sites, servers
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serviceid =  enm_servicegroup_names.id AND
 enm_servicegroup_instances.serverid = servers.id
ORDER BY enm_servicegroup_names.name");
while ($row = $statsDB->getNextRow()) {
    if ( ! array_key_exists($row[0],$serversBySG) ) {
        $serversBySG[$row[0]] = array();
    }
    $serversBySG[$row[0]][$row[1]] = $row[2];
}


$grid = new HTML_Table("border=1");
$grid->addRow(array("Service Group","Instances"),null,'th');
foreach ( $serversBySG as $serviceGroup => $instances ) {
    $row = array();
    $hasJMX = in_array($serviceGroup,$jmxNames);
    $isJBossService = FALSE;
    foreach ( $instances as $hostname => $serverid ) {
        if ( array_key_exists($serverid,$jbossSrvIds) ) {
            $isJBossService = true;
        }
    }

    if ( $hasJMX ) {
        if ( $isJBossService ) {
            $row[] = "<span title='Click here to see the generic JMX information for this service i.e. heap, threads, CPU & GC.'><a href=\"" .
                   PHP_WEBROOT . "/TOR/jboss.php?$webargs&servicegroup=$serviceGroup\">$serviceGroup</a></span>";
        } else {
            $serverNames = array();
            foreach ( $instances as $hostname => $serverid ) {
                $serverNames[] = $hostname . "," .$serviceGroup;
            }
            $row[] = "<span title='Click here to see the generic JMX information for this service i.e. heap, threads, CPU & GC.'><a href=\"" .
                   PHP_WEBROOT . "/genjmx.php?$webargs&names=" . implode(";",$serverNames) . "\">$serviceGroup</a></span>";
        }
    } else {
        $row[] = $serviceGroup;
    }

    ksort($instances);
    foreach ( $instances as $hostname => $serverid ) {
        if ( in_array($serverid, $k8sPods) ) {
            $row[] = makeLink("/k8s/cadvisor.php", $hostname, array('serverid' => $serverid));
        } else {
            $row[] = "<span title='Click here to see the information for the server $hostname i.e. CPU, memory & network interfaces.'><a href=\"" .
                   PHP_WEBROOT . "/server.php?$webargs&server=$hostname\">$hostname</a></span>";
        }
    }

    $grid->addRow($row);
}
$colspanValue = $grid->getColCount()-1;
$grid->setCellAttributes(0, 1, "colspan=" .$colspanValue);

echo "<H2>Service Groups</H2>\n";
echo $grid->toHTML();

include PHP_ROOT . "/common/finalise.php";
?>
