<?php
$pageTitle = "ENM Cluster";


include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

require_once 'HTML/Table.php';

# For the given service (svc) return an array of strings
# each string has two parts seperated by a comma, first part
# of the string has the hostname of where the service is running,
# the second part is the service name
function getServerNames($svc,$svcInfo,$vmServers) {
  global $debug;

  $serverNames = array();
  foreach ( $svcInfo['hosts'] as $hostname => $svcOnHostInfo ) {
    if ( $svcOnHostInfo['state'] == "ONLINE" ) {
      # If the service in running in a VM, use the hostname of the VM
      if ( isset($svcOnHostInfo['vmsrvid']) &&
           array_key_exists($svcOnHostInfo['vmsrvid'], $vmServers) ) {
        $serverNames[] = $vmServers[$svcOnHostInfo['vmsrvid']] . "," ."$svc";
      } else {
        # Else it's a service on a physical host, so use the hostname
        $serverNames[] = $hostname . "," .$svc;
      }
    }
  }

  if ( $debug > 1 ) { echo "<pre>getServerNames: "; print_r($serverNames); echo "</pre>\n"; }

  return $serverNames;
}

function getServiceGroupForService($statsDB,$hostInfos,$vmServers) {
    global $debug,$site,$date;

    $serverIds = array();
    foreach ( $hostInfos as $svcOnHostInfo ) {
        # If the service in running in a VM, use the hostname of the VM
        if ( isset($svcOnHostInfo['vmsrvid']) &&
             array_key_exists($svcOnHostInfo['vmsrvid'], $vmServers) ) {
            $serverIds[] = $svcOnHostInfo['vmsrvid'];
        }
    }

    if ( count($serverIds) > 0 ) {
        $idStr = implode(",",$serverIds);
        $row = $statsDB->queryRow("
SELECT DISTINCT(enm_servicegroup_names.name)
FROM enm_servicegroup_instances, enm_servicegroup_names, sites
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serviceid =  enm_servicegroup_names.id AND
 enm_servicegroup_instances.serverid IN ( $idStr )
LIMIT 1");
        return $row[0];
    } else {
        return NULL;
    }
}

function getClusterInfo($statsDB) {
  global $site, $date, $debug;

  $clusters = array();

  $hostToCluster = array();

  $statsDB->query("
SELECT
 enm_cluster_host.clustertype AS clust, servers.hostname AS svr,
 enm_cluster_host.nodename AS node
FROM enm_cluster_host, servers, sites
WHERE
 enm_cluster_host.siteid = sites.id AND sites.name = '$site' AND
 enm_cluster_host.date = '$date' AND
 enm_cluster_host.serverid = servers.id
ORDER BY
 enm_cluster_host.nodename");
  while($row = $statsDB->getNextNamedRow()) {
    if ( ! array_key_exists( $row['clust'], $clusters ) ) {
      $clusters[$row['clust']] = array('hosts' => array(),
                                       'svc'   => array());
    }
    $clusterHost = array('nodename' => $row['node']);
    $clusters[$row['clust']]['hosts'][$row['svr']] = $clusterHost;

    $hostToCluster[$row['svr']] = $row['clust'];
  }

  $statsDB->query("
SELECT
 enm_cluster_svc_names.name AS svc ,
 servers.hostname AS hostsvr, enm_cluster_svc.vmserverid AS vmsrvid,
 enm_cluster_svc.state AS state, enm_cluster_svc.actstand AS actstand
FROM enm_cluster_svc, enm_cluster_svc_names, servers, sites
WHERE
 enm_cluster_svc.siteid = sites.id AND sites.name = '$site' AND
 enm_cluster_svc.date = '$date' AND
 enm_cluster_svc.hostserverid = servers.id AND
 enm_cluster_svc.serviceid = enm_cluster_svc_names.id
ORDER BY enm_cluster_svc_names.name");
  while($row = $statsDB->getNextNamedRow()) {
    $cluster = $hostToCluster[$row['hostsvr']];
    if ( ! array_key_exists( $row['svc'], $clusters[$cluster]['svc'] ) ) {
      $clusters[$cluster]['svc'][$row['svc']] =
        array( 'actstand' => $row['actstand'],
               'hosts' => array() );
    }
    $svcInfo = & $clusters[$cluster]['svc'][$row['svc']];
    $svcInfo['hosts'][$row['hostsvr']] = array( 'state' => $row['state'], 'vmsrvid' => $row['vmsrvid'] );
  }

  if ( $debug > 0 ) { echo "<pre>getClusterInfo: \n"; print_r($clusters); echo "</pre>\n"; }

  return $clusters;
}

function showServersGraphs($clusters,$statsDB) {
  global $site, $date;

  $table = new HTML_Table('border=0');
  $table->addRow( array("Cluster", "CPU Load", "Memory Used"), null, 'th' );

  $sqlParamWriter = new SqlPlotParam();

  foreach ( $clusters as $clusterType => $clusterInfo ) {
    $graphRow = array( "<b><a href='#" . $clusterType . "'>" . $clusterType . "</a></b>");

    $sqlParam =
      array(
            'title' => '',
            'type' => 'tsc',
            'useragg' => 'true',
            'persistent' => 'false',
            );

    $serversStr = implode("','", array_keys($clusterInfo['hosts']) );
    $serverIds = array();
    $statsDB->query("
SELECT servers.hostname, servers.id
FROM sites, servers
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.hostname IN ( '$serversStr' )");
    while($row = $statsDB->getNextRow()) {
      $serverIds[$row[0]] = $row[1];
    }

    foreach ( array( 'iowait+sys+user+IFNULL(guest,0)', 'memused' ) as $col ) {
      $queryList = array();
      foreach ( $serverIds as $hostname => $serverId ) {
        $queryList[] =
          array('timecol' => 'time',
                'whatcol' => array( $col => "$hostname" ),
                'tables' => "hires_server_stat",
                'where' => "hires_server_stat.serverid = $serverId"
                );
      }
      $sqlParam['querylist'] = $queryList;
      if ( $col == 'memused' ) {
        $sqlParam['ylabel'] = 'MB Used';
      } else {
        $sqlParam['ylabel'] = 'CPU Load %';
      }

      $id = $sqlParamWriter->saveParams($sqlParam);
      $graphRow[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 525, 165, "servers=" . htmlentities($serversStr));
    }

    $table->addRow($graphRow);
  }

  echo $table->toHTML();
}


$statsDB = new StatsDB();

$vmServers = array();
$statsDB->query("
SELECT servers.id, servers.hostname
FROM sites, servers
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.type = 'ENM_VM'");
while($row = $statsDB->getNextRow()) {
  $vmServers[$row[0]] = $row[1];
}

if ( $debug > 0 ) { echo "<pre>\n"; echo "vmServers:\n"; print_r($vmServers); echo "</pre>\n"; }

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
while($row = $statsDB->getNextRow()) {
    $jbossSrvIds[$row[0]] = 1;
}
if ( $debug > 0 ) { echo "<pre>\n"; echo "jbossSrvIds:\n"; print_r($jbossSrvIds); echo "</pre>\n"; }

$clusters = getClusterInfo($statsDB);

showServersGraphs($clusters,$statsDB);

foreach ( $clusters as $clusterType => $clusterInfo ) {
  if( $clusterType != "SERVICE" ) {
    echo "<H1>" . $clusterType . " Cluster<a name='" . $clusterType . "'></a>" . "</H1>\n";
  } else {
        echo "<a name='" . $clusterType . "'></a>";
        drawHeaderWithHelp( "$clusterType Cluster ", 1, "service cluster", "DDP_Bubble_155_Service_cluster" );
  }
  /* Set the header up as with links to the server stats for each host in the cluster */
  $table = new HTML_Table('border=1');
  $headerRow = array("");
  foreach ( $clusterInfo['hosts'] as $host => $hostInfo ) {
      $nodeName = $clusterInfo['hosts'][$host]['nodename'];
      $headerRow[] = <<<EOT
<span title='Click here to see the information for this server i.e. CPU, memory & network interfaces'>
<a href="$php_webroot/server.php?$webargs&server=$host">$host</a> ( $nodeName )
</span>
EOT;
  }
  $table->addRow($headerRow);

  /* Each row has the service name in the first column and the state of
     the service under each column/host where the service is running. If
     the service is a VM, then the state is a link to the server stats for
     the VM */
  foreach ( $clusterInfo['svc'] as $svc => $svcInfo ) {
    $row = array();

    if ( $debug > 2 ) { echo "<pre>svc=$svc svcInfo:"; print_r($svcInfo); echo "</pre>\n"; }

    $isJBossService = false;
    foreach ( $svcInfo['hosts'] as $host => $hostInfo ) {
        if ( isset($hostInfo['vmsrvid']) && array_key_exists($hostInfo['vmsrvid'],$jbossSrvIds) ) {
            $isJBossService = true;
        }
    }

    /* If we have JMX stats for the srv instances then the name is set to a link for the JMX stats */
    /* Now the JMX stats are looked using the service Group name */
    $serviceGroup = getServiceGroupForService($statsDB,$svcInfo['hosts'],$vmServers);

    if ( $debug ) { echo "<pre>isJBossService=$isJBossService serviceGroup=$serviceGroup</pre>\n"; }

    if ( is_null($serviceGroup) ) {
        $serviceGroup = $svc;
    }
    if ( in_array($serviceGroup,$jmxNames) || $clusterType == "SCRIPTING" ) {
      if (stripos($svc , 'elasticsearch') === 0 || stripos($svc , 'jms') === 0){
          /* If we have any svc that can do a failover like elasticsearch and jms,
             then the data for the same should be displayed across servers*/
          $row[] = <<<EOT
<span title='Click here to see the generic JMX information for this service i.e. heap, threads, CPU & GC.'>
 <a href="$php_webroot/genjmx.php?$webargs&name=$svc">$svc</a>
</span>
EOT;
      } else {
          if ( $isJBossService ) {
              $row[] = <<<EOT
<span title='Click here to see the generic JMX information for this service i.e. heap, threads, CPU & GC.'>
 <a href="$php_webroot/TOR/jboss.php?$webargs&servicegroup=$serviceGroup">$serviceGroup</a>
</span>
EOT;
          } else {
              $serverNamesStr = implode(";",getServerNames($serviceGroup,$svcInfo,$vmServers));
              $row[] = <<<EOT
<span title='Click here to see the generic JMX information for this service i.e. heap, threads, CPU & GC.'>
 <a href="$php_webroot/genjmx.php?$webargs&names=$serverNamesStr">$svc</a>
</span>
EOT;
          }
      }
    } else {
      $row[] = $svc;
    }

    foreach ( $clusterInfo['hosts'] as $host => $hostInfo ) {
      $tableCellContent = "";
      if ( array_key_exists( $host, $svcInfo['hosts'] ) ) {
            $tableCellContent = $svcInfo['hosts'][$host]['state'];
      }
      if ( $tableCellContent != "" && isset($svcInfo['hosts'][$host]['vmsrvid'])
           && array_key_exists($svcInfo['hosts'][$host]['vmsrvid'],$vmServers) ) {
        $vmServ = $vmServers[$svcInfo['hosts'][$host]['vmsrvid']];
        $nodeName = $clusterInfo['hosts'][$host]['nodename'];
        $tableCellContent = <<<EOT
<span title='Click here to see the information for the server $svc on $nodeName i.e. CPU, memory & network interfaces.'>
 <a href="$php_webroot/server.php?$webargs&server=$vmServ">$tableCellContent</a>
</span>
EOT;
      }

      $row[] = $tableCellContent;
    }
    if ( $debug > 3 ) { echo "<pre>row:"; print_r($row); echo "</pre>\n"; }

    $table->addRow($row);
  }

  echo $table->toHTML();
}

include PHP_ROOT . "/common/finalise.php";
?>
