<?php

$pageTitle = "SFS / Access NAS";
if ( isset($_GET['nodetype']) && strtoupper($_GET['nodetype']) == 'SFS' ) {
  $pageTitle = 'SFS';
} else if ( isset($_GET['nodetype']) && strtoupper($_GET['nodetype']) == 'ACCESSNAS' ) {
  $pageTitle = 'Access NAS';
}

include "common/init.php";

require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";
require_once PHP_ROOT . "/classes/NICPlot.php";

const INODE_TABLE = "vxfs_inode_cache";
const SERVERID = "serverid";
const POOL_TABLE = "nfsd_pool";

function addVxfsInodeCacheRows($sfsServers, $graphTable, $sqlParamWriter) {
    global $date, $site, $debug, $statsDB;

    $row = array();
    $sqlPlotParam = SqlPlotParamBuilder::init()
                  ->title('Memory Usage')
                  ->type(SqlPlotParam::STACKED_AREA)
                  ->yLabel('MB')
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array(
                          'memused - membuffers - memcached' => 'Used',
                          'membuffers' => 'Buffers',
                          'memcached' => 'Cached',
                          'freeram' => 'Free'
                      ),
                      array("hires_server_stat"),
                      "hires_server_stat.serverid = %d",
                      array(SERVERID)
                  )
                  ->build();
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    foreach ( $sfsServers as $serverId ) {
        $row[] = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            480,
            240,
            "serverid=$serverId"
        );
    }
    $graphTable->addRow($row);

    $params = array(
        'recycle' => array( 'Recycle Age', 'Seconds' ),
        'vxi_icache_inuseino' => array('vxi_icache_inuseino', '#'),
        'lookups' => array('Cache Lookups', '#'),
        'hitrate' => array('Cache Hit Rate', '%')
    );

    foreach ( $params as $column => $titleLabel ) {
        $sqlPlotParam = SqlPlotParamBuilder::init()
                      ->title($titleLabel[0])
                      ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                      ->yLabel($titleLabel[1])
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array($column => $column),
                          array(INODE_TABLE, StatsDB::SITES),
                          "vxfs_inode_cache.siteid = sites.id AND sites.name = '%s' AND vxfs_inode_cache.serverid = %d",
                          array( 'site', SERVERID )
                      )
                      ->build();
        $id = $sqlParamWriter->saveParams($sqlPlotParam);
        $row = array();
        foreach ( $sfsServers as $serverId ) {
            $row[] = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                480,
                240,
                "serverid=$serverId"
            );
        }
        $graphTable->addRow($row);
    }
}

function addNfsdPoolRows($sfsServers, $graphTable, $sqlParamWriter) {
    global $date, $site, $debug, $statsDB;

    $deferred = <<<ESQL
ROUND( IF(
          packets_arrived > (threads_woken+sockets_enqueued),
          ((packets_arrived-(threads_woken+sockets_enqueued))*100) / packets_arrived,
          0
         ),
       1
)
ESQL;
    $row = array();
    $sqlPlotParam = SqlPlotParamBuilder::init()
                  ->title('NFSD Pool Stats')
                  ->type(SqlPlotParam::STACKED_AREA)
                  ->yLabel('%')
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array(
                          'ROUND( (threads_woken * 100) / packets_arrived, 1)' => 'Processed',
                          'ROUND( (sockets_enqueued*100)/packets_arrived, 1)' => 'Queued',
                          $deferred => 'Deferred'
                      ),
                      array(POOL_TABLE, StatsDB::SITES),
                      "nfsd_pool.siteid = sites.id AND sites.name = '%s' AND nfsd_pool.serverid = %d",
                      array( 'site', SERVERID )
                  )
                  ->build();
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    foreach ( $sfsServers as $serverId ) {
        $row[] = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            480,
            240,
            "serverid=$serverId"
        );
    }
    $graphTable->addRow($row);
}

function getNicIp($statsDB,$sfsServers) {
  global $date, $debug;

  $nicIp = array();
  foreach ( $sfsServers as $hostname => $serverId ) {
    $statsDB->query("
SELECT network_interfaces.name, network_interfaces.id,
 network_interface_ip.ipaddress, network_interface_ip.isvirtual
 FROM network_interface_ip, network_interfaces
 WHERE
  network_interface_ip.ifid = network_interfaces.id AND
  network_interface_ip.date = '$date' AND
  network_interfaces.serverid = $serverId");
    while ( $row = $statsDB->getNextRow() ) {
      $nicName = $row[0];
      if ( ! array_key_exists($row[0], $nicIp) ) {
    $nicIp[$nicName] = array();
      }
      if ( ! array_key_exists($hostname,$nicIp[$nicName]) ) {
    $nicIp[$nicName][$hostname] = array( 'id' => $row[1], 'virtual' => array() );
      }
      if ( $row[3] == 0 ) {
    $nicIp[$nicName][$hostname]['phyiscal'] = $row[2];
      } else {
    $nicIp[$nicName][$hostname]['virtual'][] = $row[2];
      }
    }
  }

  if ( $debug ) { echo "<pre>getNicIp: nicIp\n"; print_r($nicIp); echo "</pre>\n"; }
  return $nicIp;
}

function getMountsByIp($statsDB) {
  global $date, $site,$debug;

  $mountsByIp = array();
  $statsDB->query("
SELECT nfs_mounts.remoteip, servers.hostname, nfs_mounts.mnt
 FROM nfs_mounts, servers, sites
 WHERE
  nfs_mounts.date = '$date' AND
  nfs_mounts.serverid = servers.id AND
  nfs_mounts.remoteip IS NOT NULL AND
  servers.siteid = sites.id AND sites.name = '$site'");
  while ( $row = $statsDB->getNextRow() ) {
    if ( ! array_key_exists($row[0],$mountsByIp) ) {
      $mountsByIp[$row[0]] = array();
    }
    $mntParts = explode(":", $row[2]);
    $mountsByIp[$row[0]][] = array('host' => $row[1], 'share' => $mntParts[1]);
  }

  if ( $debug ) { echo "<pre>getMountsByIp: mountsByIp\n"; print_r($mountsByIp); echo "</pre>\n"; }
  return $mountsByIp;
}

$nodeType = "";
if ( isset($_GET["nodetype"]) ) {
  $nodeType = strtoupper($_GET["nodetype"]);
} else {
  $queryResult = $statsDB->queryRow("
SELECT
 servers.type
FROM
 servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 (servers.type = 'SFS' OR servers.type = 'ACCESSNAS')
LIMIT 1");
  if ( ! isset($queryResult[0]) ) {
    exit;
  } else {
    $nodeType = $queryResult[0];
  }
}

/* Get serverids for SFS [OR] Access NAS heads */
  $row = $statsDB->query("
SELECT servers.hostname AS hostname, servers.id AS id
FROM servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 servers.type = '$nodeType'");
$sfsServers = array();
while ($row = $statsDB->getNextRow()) {
  $sfsServers[$row[0]] = $row[1];
}

/* Overview graphs, NIC traffic, NFS operations */
$table = new HTML_Table('border=0');
$table->addRow(array_keys($sfsServers), null, "th");

$sqlParamWriter = new SqlPlotParam();

$sqlParam =
  array( 'title'      => "NFS Requests",
     'ylabel'     => 'Operations',
     'useragg'    => 'true',
     'persistent' => 'false',
     'type'       => 'sb',
     'sb.barwidth' => '60',
     'querylist' =>
     array(
           array (
              'timecol' => 'time',
              'whatcol' => array(
                     '(write_op + setattr + create_op + remove + rename_op + link + symlink + mkdir + commit_op)'  => 'Write',
                     '(read_op + getattr + lookup + readlink + access + readdir + readdirplus +fsstat)'   => 'Read',
                      ),
              'tables'  => "nfsd_v3ops, sites",
              'where'   => "nfsd_v3ops.siteid = sites.id AND sites.name = '%s' AND nfsd_v3ops.serverid = %d",
              'qargs'   => array( 'site', 'serverid' )
              )
                 )
     );
$nfsOpId = $sqlParamWriter->saveParams($sqlParam);
$nfsOpRow = array();
foreach ( $sfsServers as $hostname => $serverId ) {
  $nfsOpRow[] = $sqlParamWriter->getImgURL( $nfsOpId, "$date 00:00:00", "$date 23:59:59", true, 480, 240,
                        "serverid=$serverId" );
}
$table->addRow($nfsOpRow);

$nicTxRow = array();
$nicRxRow = array();

foreach ( $sfsServers as $hostname => $serverId ) {
    $query = "
SELECT
  DISTINCT nicid
FROM
  nic_stat
WHERE
  serverid = $serverId AND
  time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $statsDB->query($query);
    $nicIds = array();
    while ($row = $statsDB->getNextRow()) {
        $nicIds[] = $row[0];
    }
    if ($nicIds) {
        $nicPlot = new NICPlot($statsDB,$date,implode(",",$nicIds),NICPlot::NIC);
        $nicTxRow[] = $nicPlot->getGraph(NICPlot::TX,"NIC TX Bandwidth", 480, 240);
        $nicRxRow[] = $nicPlot->getGraph(NICPlot::RX,"NIC RX Bandwidth", 480, 240);
    }
}

$table->addRow($nicTxRow);
$table->addRow($nicRxRow);

if ( $statsDB->hasData(INODE_TABLE) ) {
    addVxfsInodeCacheRows($sfsServers, $table, $sqlParamWriter);
}

if ( $statsDB->hasData(POOL_TABLE) ) {
    addNfsdPoolRows($sfsServers, $table, $sqlParamWriter);
}

drawHeader('NFS Info', 2, 'nfsInfo');
echo $table->toHTML();


/* NIC->IP Table */
$nicIp = getNicIp($statsDB,$sfsServers);

$ipTable = new HTML_Table('border=0');
$ipTable->addRow(array_merge(array(''),array_keys($sfsServers)), null, "th");
foreach ( $nicIp as $nicName => $nicCfg ) {
  $tableRow = array();
  $tableRow[] = $nicName;
  foreach ( $sfsServers as $hostname => $serverId ) {
    $ipList = array_merge( array($nicCfg[$hostname]['phyiscal']),$nicCfg[$hostname]['virtual']);
    $tableRow[] = implode(", ",$ipList);
  }
  $ipTable->addRow($tableRow);
}

drawHeader( 'IP Addresses per NIC', 1, 'addressHelp' );
echo $ipTable->toHTML();



/*  NIC->mount Table */
$mountsByIp = getMountsByIp($statsDB);

$mntTable = new HTML_Table('border=0');
 $mntTable->addRow(array_merge(array(''),array_keys($sfsServers)), null, "th");
foreach ( $nicIp as $nicName => $nicCfg ) {
  $tableRow = array();
  $tableRow[] = $nicName;
  $hasMount = 0;
  foreach ( $sfsServers as $hostname => $serverId ) {
    $nicMounts = new HTML_Table("border=1");
    $ipList = array_merge( array($nicCfg[$hostname]['phyiscal']),$nicCfg[$hostname]['virtual']);
    foreach ( $ipList as $ip ) {
      if ( array_key_exists($ip,$mountsByIp) ) {
        $mnts = $mountsByIp[$ip];
        if ( $debug ) { print "<pre>mounts for $ip\n"; print_r($mnts); "</pre>\n"; }
        foreach ( $mnts as $mnt ) {
          $nicMounts->addRow(array($mnt['host'],$mnt['share']));
          $hasMount = 1;
        }
      }
    }
    $tableRow[] = $nicMounts->toHTML();
  }
  if ( $hasMount > 0 ) {
    $mntTable->addRow($tableRow);
  }
}

if ( $mntTable->getRowCount() > 1 ) {
    echo "<H2>NFS Clients per NIC</H2>\n";
    echo $mntTable->toHTML();
}

/* SFS OR Access NAS Audits */
$auditLink = NULL;
$auditFilePattern = "";
if ( $nodeType == 'SFS' ) {
  $auditFilePattern = '^SFS_Audit_';
} else if ( $nodeType == 'ACCESSNAS' ) {
  $auditFilePattern = "^NAS_Audit_";
}
foreach ( $sfsServers as $hostname => $serverId ) {
  $sfsSubDir = "/remotehosts/" . str_replace("_","",$hostname) . "_$nodeType" . "/$dir";
  $serverDir = $datadir . $sfsSubDir;
  if ( $debug > 0 ) {
    echo "<pre>hostname=$hostname serverDir=$serverDir</pre>\n";
  }
  if ( is_dir($serverDir) && ($dh = opendir($serverDir)) ) {
      while (($file = readdir($dh)) != false) {
          $entry = $serverDir . "/" . $file;
          if ( preg_match("/$auditFilePattern/", $file) ) {
              $auditLink = '<a href="' . str_replace('/analysis/','/data/',$webroot) . "$sfsSubDir/$file" . '">' . $hostname . "</a>";
          }
      }
      closedir($dh);
  }
}
if ( ! is_null($auditLink) ) {
  echo "<H2>{$pageTitle} Audit</H2>\n";
  echo $auditLink;
}

include "common/finalise.php";
