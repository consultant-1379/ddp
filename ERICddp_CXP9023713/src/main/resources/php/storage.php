<?php
$pageTitle = "Storage ";
$YUI_DATATABLE = true;

if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'plotvolused' ) {
    $UI = false;
}

include "common/init.php";

require_once "SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once 'classes/SqlTable.php';
require_once "classes/ModelledGraph.php";

const NFS_MOUNTS = 'nfs_mounts';
const BLK_DEVICES = 'blk_devices';
const PLOTDISK = 'plotdisk';
const PERSISTENT = 'persistent';

$diskGraphParam =
  array('avserv' => array('title' => 'Service Time',
                          'ylabel' => 'ms',
                          'whatcol' => array( 'avserv' => 'ms' )
                          ),
        'avwait' => array('title' => 'Wait Time',
                          'ylabel' => 'ms',
                          'whatcol' => array( 'avwait' => 'ms' )
                          ),
        'avque'   => array('title' => 'Queue Length',
                           'ylabel' => 'Length',
                           'whatcol' => array( 'avque' => 'avque' )
                           ),
        'blks'   => array('title' => 'Blocks Per Second',
                          'ylabel' => 'blocks',
                          'whatcol' => array( 'blks' => 'blks' )
                          ),
        'rblks'   => array('title' => 'Read Blocks Per Second',
                          'ylabel' => 'blocks',
                          'whatcol' => array( 'readblks' => 'rblks' )
                          ),
        'wblks'   => array('title' => 'Write Blocks Per Second',
                          'ylabel' => 'blocks',
                          'whatcol' => array( 'blks-readblks' => 'wblks' )
                          ),
        'rws'    => array('title' => 'Read/Writes Per Second',
                          'ylabel' => 'Read/Writes',
                          'whatcol' => array( 'rws' => 'rws' )
                          ),
        'busy'   => array('title' => '%Busy',
                          'ylabel' => '%Busy',
                          'whatcol' => array( 'busy' => 'busy' )
                          )
        );

$vxVolGraphParam =
  array('rop' => array('title' => 'Read OP/s',
                       'ylabel' => 'OP/s',
                       'whatcol' => array( 'rop' => 'rop' )
                       ),
        'wop' => array('title' => 'Write OP/s',
                       'ylabel' => 'OP/s',
                       'whatcol' => array( 'wop' => 'wop' )
                       ),
        'rblk' => array('title' => 'Read Blocks/s',
                        'ylabel' => 'Blocks',
                        'whatcol' => array( 'rblk' => 'rblk' )
                        ),
        'wblk' => array('title' => 'Write Blocks/s',
                        'ylabel' => 'Blocks',
                        'whatcol' => array( 'wblk' => 'wblk' )
                        ),
        'rtime' => array('title' => 'Read Time(ms)',
                         'ylabel' => 'ms',
                         'whatcol' => array( 'rtime' => 'rtime' )
                         ),
        'wtime' => array('title' => 'Write Time(ms)',
                         'ylabel' => 'ms',
                         'whatcol' => array( 'wtime' => 'wtime' )
                         )
        );

function doZfsCacheGraph($fromDate,$toDate,$serverId,$statsDB) {
  $row = $statsDB->queryRow("SELECT COUNT(*) FROM zfs_cache WHERE serverid = $serverId AND time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
  if ( $row[0] == 0 ) {
    return;
  }

  echo "<H3>ZFS Cache<H3>";
  $ZfsGraphs = new HTML_Table('border=0');

  $graphURLs['graphs'] = array();
  $title = array("ZFS Cache Size(Mb)","ZFS Cache Hit Ratio(%)");
  $field = array("size","hitratio");
  $sqlParamWriter = new SqlPlotParam();
  for ($i=0;$i<count($title);++$i) {
    $totalField =
      array( 'title'      => "$title[$i]",
             'ylabel'     => "$title[$i]",
             PERSISTENT => 'false',
             'useragg'    => 'true',
             'querylist' =>
             array(
                   array (
                          'timecol' => 'time',
                          'whatcol' => array( "$field[$i]" => "$field[$i]" ),
                          'tables'  => "zfs_cache, servers",
                          'where'   => "servers.id='$serverId' AND servers.id=zfs_cache.serverid"
                          )
                   )
             );
    $id = $sqlParamWriter->saveParams($totalField);
    $ZfsGraphs->addRow(array($sqlParamWriter->getImgURL( $id,"$fromDate 00:00:00", "$toDate 23:59:59",true, 640, 240 )));

  }

  echo $ZfsGraphs->toHTML();
}

function plotAllForDisks($statsDB,$serverId,$fromDate,$toDate, $diskIdsStr, $hasReadBlks, $useOldTable) {
    global $diskGraphParam;

    if ( $useOldTable ) {
        $statTable = 'hires_disk_stat_old';
        $where = 'hires_disk_stat_old.diskid = disks.id AND disks.id IN (%s)';
        $qargs = array( 'diskid' );
    } else {
        $statTable = 'hires_disk_stat';
        $where = 'hires_disk_stat.serverid = %d AND hires_disk_stat.diskid = disks.id AND disks.id IN (%s)';
        $qargs = array( 'serverid', 'diskid' );

    }

    $diskGraphs = new HTML_Table('border=0');
    foreach ( $diskGraphParam as $key => $param ) {

        if ( $hasReadBlks == 0 ) {
            if ($key === 'rblks' || $key === 'wblks') {
                continue;
            }
        } else {
            if ($key === 'blks') {
                continue;
            }
        }
        $sqlParam =
                  array(
                      'title'      => '%s',
                      'targs'      => array('title'),
                      'ylabel'     => $param['ylabel'],
                      'useragg'    => 'true',
                      PERSISTENT => 'true',
                      'querylist' =>
                      array(
                          array (
                              'timecol' => 'time',
                              'multiseries'=> 'disks.name',
                              'whatcol' => $param ['whatcol'],
                              'tables'  => "$statTable, disks",
                              'where'   => $where,
                              'qargs'   => $qargs
                          )
                      )
                  );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $diskGraphs->addRow(array(
            $sqlParamWriter->getImgURL( $id, "$fromDate 00:00:00", "$toDate 23:59:59",
                                        true, 640, 240,
                                        "serverid=$serverId&diskid=$diskIdsStr&title=" . htmlentities($param['title'])
            )
        )
        );
    }
    echo $diskGraphs->toHTML();
}


function plotVxVols($statsDB,$serverId,$fromDate,$toDate,$volIdsStr) {
  global $vxVolGraphParam;

  $sqlParamWriter = new SqlPlotParam();
  $graphTable = new HTML_Table('border=0');
  foreach ( $vxVolGraphParam as $key => $param ) {
      $sqlParam =
                array(
                    'title'      => '%s',
                    'targs'      => array('title'),
                    'ylabel'     => $param['ylabel'],
                    'useragg'    => 'true',
                    PERSISTENT => 'true',
                    'querylist' =>
                    array(
                        array (
                            'timecol' => 'time',
                            'multiseries'=> 'volumes.name',
                            'whatcol' => $param ['whatcol'],
                            'tables'  => "vxstat, volumes",
                            'where'   => 'vxstat.volid = volumes.id AND volumes.id IN (%s) AND vxstat.serverid = %d',
                            'qargs'   => array( 'volids', 'serverid' )
                        )
                    )
                );
      $id = $sqlParamWriter->saveParams($sqlParam);
      $graphTable->addRow(array(
          $sqlParamWriter->getImgURL( $id, "$fromDate 00:00:00", "$toDate 23:59:59",
                                      true, 640, 240,
                                      "volids=$volIdsStr&serverid=$serverId&title=" . htmlentities($param['title']))));
  }
  echo $graphTable->toHTML();
}

function showVxStatTable( $statsDB, $serverId,$fromDate,$toDate) {
    global $webargs;

    $columns = array(
        array( 'key' => "id", 'db' => 'volumes.id', 'visible' => false),
        array( 'key' => "name", 'db' => 'volumes.name', 'label' => 'Volume'),
        array( 'key' => "rop", 'db' => 'ROUND(AVG(rop))', 'label' => 'Read OP/s'),
        array( 'key' => "wop", 'db' => 'ROUND(AVG(wop))', 'label' => 'Write OP/s'),
        array( 'key' => "rblk", 'db' => 'ROUND(AVG(rblk))', 'label' => 'Read Blocks/s'),
        array( 'key' => "wblk", 'db' => 'ROUND(AVG(wblk))', 'label' => 'Write Blocks/s'),
        array( 'key' => "rtime", 'db' => 'ROUND(AVG(rtime))', 'label' => ' Read Time(msec)'),
        array( 'key' => "wtime", 'db' => 'ROUND(AVG(wtime))', 'label' => 'Write Time(msec)')
    );
    $where = <<<EOT
vxstat.serverid = $serverId AND
vxstat.volid = volumes.id AND
vxstat.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
GROUP BY vxstat.volid
EOT;
    $sqlTable =
              new SqlTable('vxstat',
                           $columns,
                           array('vxstat','volumes'),
                           $where,
                           TRUE,
                           array(
                               'order' => array( 'by' => 'wblk', 'dir' => 'DESC' ),
                               'ctxMenu' => array('key' => 'action',
                                                  'multi' => true,
                                                  'menu' => array( 'plotvxvol' => 'Plot'),
                                                  'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                                  'col' => 'id')
                           )
              );

    echo "<H2>Veritas Volume Stats</H2>\n";
    echo $sqlTable->getTable();
}


function showVolumeTable($statsDB,$serverId,$date)
{
    global $webargs;

    $where = "
volumes.id = volume_stats.volid AND
volume_stats.serverid = $serverId AND
volume_stats.date = '$date'";
    $table =
           new SqlTable("volume_usage",
                        array(
                            array( 'key' => "id", 'db' => 'volumes.id', 'visible' => false),
                            array('key' => 'name', 'db' => 'volumes.name', 'label' => 'Volume'),
                            array('key' => 'size', 'db' => 'volume_stats.size', 'label' => 'Size(MB)' ),
                            array('key' => 'used', 'db' => 'volume_stats.used', 'label' => 'Used(MB)' ),
                            array('key' => 'percent', 'db' => 'ROUND( (volume_stats.used / volume_stats.size) * 100, 1)', 'label' => '%' )
                        ),
                        array('volumes','volume_stats'),
                        $where,
                        TRUE,
                        array(
                            'order' => array( 'by' => 'size', 'dir' => 'DESC' ),
                               'ctxMenu' => array('key' => 'action',
                                                  'multi' => true,
                                                  'menu' => array( 'plotvolused' => 'Plot used for last month'),
                                                  'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                                  'col' => 'id')
                        )
           );
    echo $table->getTable();
}

function plotVolumeUsed($serverId, $date, $volIdStr) {
    $fromDate = date('Y-m-d', strtotime($date.'-1 month'));
    $params = array( 'serverid' => $serverId, 'volids' => $volIdStr );
    $modelledGraph = new ModelledGraph('common/volume_used');
    $link = $modelledGraph->getLink($params, "$fromDate 00:00:00", "$date 23:59:59");
    header("Location:" .  $link);
}

function presentDisks(
    $serverId,
    $fromDate,
    $toDate,
    &$hasVeritas,
    $table,
    $hasReadBlks,
    $diskIds
) {
    global $webargs, $statsDB;

    $titles = array("svm_disks" => "Solaris Volume Manager",
                    "vrts_disks" => "Veritas Volume Manager",
                    "zfs_disks" => "ZFS",
                    "raw_devices" => "Raw Devices",
                    NFS_MOUNTS => "NFS Mounts",
                    BLK_DEVICES => "Block Devices");
    $extra_cols = array("vrts_disks" => "dg", "zfs_disks" => "pool" );


    echo "<H3>" . $titles[$table] . "</H3>\n";

    if ( $table == "vrts_disks" ) {
        $hasVeritas = TRUE;
    }

    $diskNameCol = "disks.name";
    if ( array_key_exists( $table, $extra_cols ) ) {
        $diskNameCol = "CONCAT(" . $table . "." . $extra_cols[$table] . ",'.',disks.name)";
    }

    $columns = array();
    $columns[] = array( 'key' => "id", 'db' => 'hires_disk_stat.diskid', 'visible' => false);
    $columns[] = array( 'key' => "name", 'db' => $diskNameCol, 'label' => "Disk");
    $columns[] = array( 'key' => "busy", 'db' => 'ROUND(AVG(busy))', 'label' => "Busy (%)");
    $columns[] = array( 'key' => "avque", 'db' => 'ROUND(AVG(avque))', 'label' => "Queue Length" );
    $columns[] = array( 'key' => "rws", 'db' => 'ROUND(AVG(rws))', 'label' => "IOPs/s" );
    if ( $hasReadBlks ) {
        $columns[] = array( 'key' => "rblks", 'db' => 'ROUND(AVG(readblks))', 'label' => "Read Blocks/s" );
        $columns[] = array( 'key' => "wblks", 'db' => 'ROUND(AVG(blks-readblks))', 'label' => "Write Blocks/s" );
    } else {
        $columns[] = array( 'key' => "blks", 'db' => 'ROUND(AVG(blks))', 'label' => "Blocks/s" );
    }
    $columns[] = array( 'key' => "avserv", 'db' => 'ROUND(AVG(avserv),1)', 'label' => "Service Time(ms)" );
    $columns[] = array( 'key' => "avwait", 'db' => 'ROUND(AVG(avwait),1)', 'label' => "Wait Time(ms)" );

    $tables = array('hires_disk_stat','disks');
    if ( $table === BLK_DEVICES ) {
        $where = <<<EOT
hires_disk_stat.serverid = $serverId AND
hires_disk_stat.time BETWEEN "$fromDate 00:00:00" AND "$toDate 23:59:59" AND
hires_disk_stat.diskid = disks.id
GROUP BY hires_disk_stat.diskid
EOT;
    } else {
        $tables[] = $table;
        $where = <<<EOT
hires_disk_stat.serverid = $serverId AND
hires_disk_stat.time BETWEEN "$fromDate 00:00:00" AND "$toDate 23:59:59" AND
hires_disk_stat.diskid = disks.id AND
hires_disk_stat.diskid = $table.diskid AND
$table.date = '$fromDate' AND
$table.serverid = $serverId
GROUP BY hires_disk_stat.diskid
EOT;
    }

    $sqlTable =
              new SqlTable($table,
                           $columns,
                           $tables,
                           $where,
                           TRUE,
                           array('ctxMenu' => array('key' => 'action',
                                                    "multi" => true,
                                                    "menu" => array( PLOTDISK => 'Plot'),
                                                    "url" => makeSelfLink() . '&hasreadblks=' . $hasReadBlks,
                                                    "col" => 'id')
                           )
              );

    echo "<H4>Daily Average</H4>\n";
    echo $sqlTable->getTable();

    /* Only output the graphs if there are less then 4 disks */
    if ( count($diskIds) > 0 && count($diskIds) <= 4 ) {
        plotAllForDisks($statsDB,$serverId,$fromDate,$toDate,implode(",", $diskIds),$hasReadBlks,FALSE);
    }
}

function presentStorage($statsDB,$serverId,$fromDate,$toDate,&$hasVeritas) {
    global $webargs, $php_webroot, $debug, $diskGraphParam;

    $allDiskIds = array();
    $statsDB->query("
SELECT hires_disk_stat.diskid FROM hires_disk_stat
WHERE
 hires_disk_stat.serverid = $serverId AND
 hires_disk_stat.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
    while ( $row = $statsDB->getNextRow() ) {
        $allDiskIds[$row[0]] = 1;
    }

    if ( count($allDiskIds) == 0 ) {
        presentStorageOld($statsDB,$serverId,$fromDate,$toDate,$hasVeritas);
        return;
    }

    $disksByType = array();

    $hasReadBlks = 0;
    $row = $statsDB->queryRow("
SELECT COUNT(*) FROM hires_disk_stat
WHERE
 hires_disk_stat.serverid = $serverId AND
 hires_disk_stat.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59' AND
 hires_disk_stat.readblks IS NOT NULL");
    if ( $row[0] > 0 ) {
        $hasReadBlks = 1;
    }

    foreach ( array( "svm_disks", "vrts_disks", "zfs_disks", "raw_devices", NFS_MOUNTS ) as $table ) {
        $diskIds = array();
        $statsDB->query("SELECT diskid FROM $table WHERE $table.date = '$fromDate' AND $table.serverid = $serverId");
        while ( $row = $statsDB->getNextRow() ) {
            $diskIds[] = $row[0];
            unset($allDiskIds[$row[0]]);
        }

        if ( count($diskIds) ) {
            presentDisks(
                $serverId,
                $fromDate,
                $toDate,
                $hasVeritas,
                $table,
                $hasReadBlks,
                $diskIds
            );
        }
    }

    if ( count($allDiskIds) ) {
        presentDisks(
            $serverId,
            $fromDate,
            $toDate,
            $hasVeritas,
            BLK_DEVICES,
            $hasReadBlks,
            array_keys($allDiskIds)
        );
    }

    if ( $debug ) { echo "<p>hasVeritas=$hasVeritas</p>\n"; }
}

function presentStorageOld($statsDB,$serverId,$fromDate,$toDate,&$hasVeritas) {
    global $webargs, $php_webroot, $debug, $diskGraphParam;

    $titles = array("svm_disks" => "Solaris Volume Manager",
                    "vrts_disks" => "Veritas Volume Manager",
                    "zfs_disks" => "ZFS",
                    "raw_devices" => "Raw Devices",
                    NFS_MOUNTS => "NFS Mounts");
    $extra_cols = array("vrts_disks" => "dg", "zfs_disks" => "pool" );

    $disksByType = array();

    $hasReadBlks = 0;
    $row = $statsDB->queryRow("
SELECT COUNT(*) FROM hires_disk_stat_old, disks
WHERE
 hires_disk_stat_old.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59' AND
 hires_disk_stat_old.diskid = disks.id AND disks.serverid = $serverId AND
 hires_disk_stat_old.readblks IS NOT NULL");
    if ( $row[0] > 0 ) {
        $hasReadBlks = 1;
    }

    foreach ( array( "svm_disks", "vrts_disks", "zfs_disks", "raw_devices", NFS_MOUNTS ) as $table ) {
        $diskIds = array();
        $statsDB->query("SELECT diskid FROM $table WHERE $table.date = '$fromDate' AND $table.serverid = $serverId");
        while ( $row = $statsDB->getNextRow() ) {
            $diskIds[] = $row[0];
        }

        if ( count($diskIds) ) {
            echo "<H3>" . $titles[$table] . "</H3>\n";

            if ( $table == "vrts_disks" ) {
                $hasVeritas = TRUE;
            }

            $diskNameCol = "disks.name";
            if ( array_key_exists( $table, $extra_cols ) ) {
                $diskNameCol = "CONCAT(" . $table . "." . $extra_cols[$table] . ",'.',disks.name)";
            }
            $columns = array();
            $columns[] = array( 'key' => "id", 'db' => 'hires_disk_stat_old.diskid', 'visible' => false);
            $columns[] = array( 'key' => "name", 'db' => $diskNameCol, 'label' => "Disk");
            $columns[] = array( 'key' => "busy", 'db' => 'ROUND(AVG(busy))', 'label' => "Busy (%)");
            $columns[] = array( 'key' => "avque", 'db' => 'ROUND(AVG(avque))', 'label' => "Queue Length" );
            $columns[] = array( 'key' => "rws", 'db' => 'ROUND(AVG(rws))', 'label' => "IOPs/s" );
            if ( $hasReadBlks ) {
                $columns[] = array( 'key' => "rblks", 'db' => 'ROUND(AVG(readblks))', 'label' => "Read Blocks/s" );
                $columns[] = array( 'key' => "wblks", 'db' => 'ROUND(AVG(blks-readblks))', 'label' => "Write Blocks/s" );
            } else {
                $columns[] = array( 'key' => "blks", 'db' => 'ROUND(AVG(blks))', 'label' => "Blocks/s" );
            }
            $columns[] = array( 'key' => "avserv", 'db' => 'ROUND(AVG(avserv))', 'label' => "Service Time(ms)" );
            $columns[] = array( 'key' => "avwait", 'db' => 'ROUND(AVG(avwait))', 'label' => "Wait Time(ms)" );

            $where = <<<EOT
hires_disk_stat_old.time BETWEEN "$fromDate 00:00:00" AND "$toDate 23:59:59" AND
hires_disk_stat_old.diskid = disks.id AND
hires_disk_stat_old.diskid = $table.diskid AND
$table.date = "$fromDate" AND $table.serverid = $serverId
GROUP BY hires_disk_stat_old.diskid
EOT;
            $sqlTable =
                      new SqlTable($table,
                                   $columns,
                                   array($table,'hires_disk_stat_old','disks'),
                                   $where,
                                   TRUE,
                                   array('ctxMenu' => array('key' => 'action',
                                                            'multi' => true,
                                                            'menu' => array( PLOTDISK => 'Plot'),
                                                            'url' => $_SERVER['PHP_SELF'] . "?" . $webargs . '&hasreadblks=' . $hasReadBlks . "&useoldtable=1",
                                                            'col' => 'id')
                                   )
                      );

            echo "<H4>Daily Average</H4>\n";
            echo $sqlTable->getTable();

            /* Only output the graphs if there are less then 4 disks */
            if ( count($diskIds) > 0 && count($diskIds) <= 4 ) {
                plotAllForDisks($statsDB,$serverId,$fromDate,$toDate,implode(",", $diskIds),$hasReadBlks,TRUE);
            }
        }
    }

    if ( $debug ) { echo "<p>hasVeritas=$hasVeritas</p>\n"; }
}

function mainFlow($statsDB,$webargs,$serverId,$hostname,$rootdir,$fromDate,$toDate) {
  global $debug;
  global $php_webroot;
  global $site;
  global $dir;
  global $date;
  global $oss;

  $selfURL = $_SERVER['PHP_SELF'] . "?" . $webargs . "&serverid=$serverId";

  $server=$_GET["server"];

  $serverPageLink = "$php_webroot/server.php?site=$site&dir=$dir&date=$date&oss=$oss&server=$server";

  print <<<END
<p><a href="$serverPageLink">Return to Server Stats</a><p>
<h1>Storage - $hostname</h1>
 <ul>
 <li><a href="#disks">Disks</a></li>
 <li><a href="#volumes">Volumes</a></li>
</ul>
END;

  if ( $debug ) { echo "<p>rootdir=$rootdir</p>\n"; }

  $hasVeritas = FALSE;
  echo '<a name="disks">';drawHeaderWithHelp("Storage", 1, "StorageContent"); echo "\n";
  if ( file_exists($rootdir . "/zfs_avserv.jpg") ) {
    if ( $fromDate == $toDate ) {
      echo "<H2>ZFS Pool" . drawHelpLink("zfspoolhelp") . "</H2>\n";
      drawHelp("zfspoolhelp","ZFS Pool","
The plots below are for all the disks on one side of the mirror in the ZFS pool.
Blocks and R/W are summed across all the disks, service times and queue lengths are
averaged across the disks
            ");
      $zfsIoGraphs = new HTML_Table("border=0");
      foreach ( array("blks", "rws", "avgq", "avserv") as $key ) {
        $zfsIoGraphs->addRow( array( '<img src="' . $webroot . '/zfs_' . $key . '.jpg">' ) );
      }
      echo $zfsIoGraphs->toHTML();
    }
  } else {
    presentStorage($statsDB,$serverId,$fromDate,$toDate,$hasVeritas);
  }

  if ( $hasVeritas == TRUE ) {
      showVxStatTable($statsDB,$serverId,$fromDate,$toDate);
  }

  /* Output ZFS Cache Graphs (if we have data available */
  doZfsCacheGraph($fromDate,$toDate,$serverId,$statsDB);

  /* If we have info in the volume_stats table for this server, display it */
  echo '<a name="volumes">';drawHeaderWithHelp("Volumes", 1, "VolumeContent"); echo "\n";
  if ( $fromDate == $toDate ) {
      showVolumeTable($statsDB,$serverId,$fromDate);
  } else {
    $sqlParam =
      array( 'title'      => 'Space Used',
             'ylabel'     => 'GB',
             'useragg'    => 'true',
             PERSISTENT => 'true',
             'querylist' =>
             array(
                   array (
                          'timecol' => 'date',
                          'multiseries'=> 'volumes.name',
                          'whatcol' => array( 'volume_stats.used / 1024' => 'Used' ),
                          'tables'  => "volume_stats, volumes",
                          'where'   => 'volume_stats.serverid = %d AND volumes.id = volume_stats.volid',
                          'qargs'   => array( 'serverid' )
                          )
                   )
             );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL( $id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 640, 240, "serverid=$serverId" );
  }
}

$statsDB = new StatsDB();

$serverDir="server";
if ( isset($_GET["serverdir"]) ) $serverDir=$_GET["serverdir"];

if ( isset($_GET['start']) ) {
  $fromDate = $_GET['start'];
  $toDate = $_GET['end'];
} else {
  $fromDate = $date;
  $toDate = $date;
}

$webroot = $webroot . "/" . $serverDir;
$webargs = "site=$site&dir=$dir&date=$fromDate&oss=$oss&serverdir=$serverDir";
$rootdir = $rootdir . "/" . $serverDir;
if (isset($_GET['server'])) {
  $hostname = $_GET['server'];
  $webargs .= "&server=" . $hostname;
  $serverId = getServerId($statsDB,$site,$hostname);
} else {
  echo "Getting old way";
  $serverId = getServerId($statsDB,$site,$rootdir);
}
if (! is_int($serverId)) {
  echo "<b>Could not get server id for " . $hostname . ": " . $serverId . "</b>\n";
  include "common/finalise.php";
  exit(0);
}

$action = requestValue('action');
if ( ! is_null($action) ) {
    $selected = requestValue('selected');
    if ( $action === PLOTDISK ) {
        $readBlks = requestValue('hasreadblks');
        $useOldTable = issetURLParam('useoldtable');
        plotAllForDisks( $statsDB, $serverId, $fromDate, $toDate, $selected, $readBlks, $useOldTable );
    } elseif ( $action === 'plotvxvol' ) {
        plotVxVols( $statsDB, $serverId, $fromDate, $toDate, $selected );
    } elseif ( $action === 'plotvolused' ) {
        plotVolumeUsed( $serverId, $toDate, $selected );
        exit;
    }
} else {
    mainFlow( $statsDB, $webargs, $serverId, $hostname, $rootdir, $fromDate, $toDate );
}

$statsDB->disconnect();

include "common/finalise.php";

