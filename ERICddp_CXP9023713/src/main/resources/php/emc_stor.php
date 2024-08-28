<?php

$pageTitle = "EMC Storage";


if ( isset($_REQUEST['plotluns']) || isset($_REQUEST['plotrgs']) ) {
  $UI = false;
}

include "common/init.php";
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const LUN_NAME = 'lunname';
const POOL_NAME = 'poolname';
const LUN_ID = 'lunid';
const BORDER = 'border=1';
const COLSPAN = 'colspan';
const GRAPHS = 'graphs';
const SYS_ID = 'sysid';
const POOL_ID = 'poolid';
const LOCATION = 'Location: ';

$lunMainColMap = array(
               'Utilization (%)' => array( 'key' => 'utilization' ),
               'Queue Length' => array( 'key' => 'qlen' ),
               'Average Busy Queue Length' => array( 'key' => 'qlenbusy' ),
               'Response Time (ms)' => array( 'key' => 'resptime' ),
               'Service Time (ms)' => array( 'key' => 'servtime' ),
               'Read Bandwidth (MB/s)' => array( 'key' => 'readbw' ),
               'Read Throughput (IO/s)' => array( 'key' => 'readiops' ),
               'Write Bandwidth (MB/s)' => array( 'key' => 'writebw' ),
               'Write Throughput (IO/s)' => array( 'key' => 'writeiops' ),
               'Utilization-Nonoptimal (%)' => array( 'key' => 'utilnonopt' ),
               'Total Bandwidth (MB/s)' => array( 'key' => 'totalbw',
                              'col' => 'readbw+writebw' ),
               'Total Throughput (IO/s)' => array( 'key' => 'totaliops',
                               'col' => 'readiops+writeiops' ),
               'Read Size (KB)' => array( 'key' => 'readsize',
                          'col' => 'ROUND((readbw*1024)/readiops, 0)' ),
               'Write Size (KB)' => array( 'key' => 'writesize',
                           'col' => 'ROUND((writebw*1024)/writeiops, 0)' )
               );

$rgLunMainColMap = array(
               'SP Cache Read Hits/s' => array( 'key' => 'spc_read_hit' ),
               'SP Cache Read Misses/s' => array( 'key' => 'spc_read_miss' ),
               'SP Cache Write Hits/s' => array( 'key' => 'spc_write_hit' ),
               'SP Cache Write Misses/s' => array( 'key' => 'spc_write_miss' ),
               'SP Cache Forced Flushes/s' => array( 'key' => 'spc_forced_flush' ),
             );

$lunIoSizes = array( '512', '1k', '2k', '4k', '8k', '16k', '32k', '64k', '128k', '256k', '512k' );

$rgColMap =
  array('Utilization (%)' => 'utilization',
    'Queue Length' => 'qlen',
    'Average Busy Queue Length' => 'qlenbusy',
    'Response Time (ms)' => 'resptime',
    'Service Time (ms)' => 'servtime',
    'Read Bandwidth (MB/s)' => 'readbw',
    'Read Throughput (IO/s)' => 'readiops',
    'Write Bandwidth (MB/s)' => 'writebw',
    'Write Throughput (IO/s)' => 'writeiops',
    'Average Seek Distance (GB)' => 'avgseekdist'
    );

function outputPoolRG($statsDB,$sysid,$fromDate,$toDate,$poolName) {
  global $rgColMap, $php_webroot,$webargs;

  $rgStats = getPoolRG($statsDB,$sysid,$fromDate,$toDate,$poolName);
  if ( count($rgStats) == 0 ) {
      return;
  }

  $divName = "div_" . $poolName . "_rg";
  $arrayName = $poolName . "_rgData";
  $fnName = "showPoolRGTable_" . $poolName;

  echo <<<EOF
<div id="$divName" class="yui-skin-sam"></div>
<script type="text/javascript">
var $arrayName = Array();

EOF;

   foreach ( $rgStats as $row ) {
     $cols = array( 'rgid:' . $row['rgid'], 'name:"' . $row['name'] . '"');
     foreach ( $rgColMap as $label => $param ) {
       $cols[] = "$param:" . $row[$param];
     }
     echo "$arrayName.push( {" . implode(", ",$cols) . "} );\n";
   }

  echo <<<EOF

poolRgData['$poolName'] = $arrayName;

function $fnName() {
    makeRgMain("$poolName");
}
YAHOO.util.Event.addListener(window, "load", $fnName);
</script>

EOF;


}

function outputLunMain($lunMainData,$name,$hasRG) {
  global $lunMainColMap, $rgLunMainColMap, $php_webroot,$webargs;

  $divName = "div_" . $name . "_lunmain";
  $arrayName = $name . "_lunMainData";
  $fnName = "showLunMainTable_" . $name;
  echo <<<EOF
<div id="$divName" class="yui-skin-sam"></div>
<script type="text/javascript">
var $arrayName = Array();

EOF;


  foreach ( $lunMainData as $row ) {
    $objStr = sprintf("lunid:\"%s\", rgid:\"%s\", name:\"%s\"", $row[LUN_ID], $row['rgid'], $row['name']);
    foreach ( array( $lunMainColMap, $rgLunMainColMap ) as $colMap ) {
      foreach ( $colMap as $label => $param ) {
    if ( is_null($row[$param['key']]) ) {
      $objStr .= sprintf(", \"%s\":\"NA\"", $param['key']);
    } else {
      $objStr .= sprintf(", \"%s\":%s", $param['key'], $row[$param['key']]);
    }
      }
    }
    echo "$arrayName.push( { $objStr } );\n";
  }
  $hasRGStr = "true";
  if ( $hasRG == FALSE ) {
    $hasRGStr = "false";
  }
  echo <<<EOF

lunMainData['$name'] = $arrayName;

function $fnName() {
    makeLunMain("$name",$hasRGStr);
}
YAHOO.util.Event.addListener(window, "load", $fnName);
</script>

EOF;
}

function outputLunIoSize($statsDB,$sysid,$fromDate,$toDate) {
  global $lunIoSizes, $php_webroot,$webargs;

  $lunIoSizeData = getLunIoSizeData($statsDB,$sysid,$fromDate,$toDate);

  echo <<<EOF
<div id="luniosizediv" class="yui-skin-sam"></div>
<script type="text/javascript">

EOF;

  foreach ( $lunIoSizeData as $row ) {
    printf("lunIoSizeData.push( { lunid:\"%s\", name:\"%s\", read:\"%s\", write:\"%s\" } );\n",
       $row[LUN_ID], $row['name'],
       join(",", $row['read']),
       join(",", $row['write']) );
  }

  echo <<<EOF
YAHOO.util.Event.addListener(window, "load", makeLunIoSize);
</script>

EOF;

}

function getPoolLuns($statsDB,$sysid,$fromDate) {
  $poolLuns = array();
  $statsDB->query("
SELECT emc_pool.name AS poolname , emc_pool.id AS poolid ,
 emc_lun.name AS lunname , emc_lun.id AS lunid
FROM emc_pool_lun, emc_pool, emc_lun
WHERE
 emc_pool_lun.sysid = $sysid AND
 emc_pool_lun.filedate = '$fromDate' AND
 emc_pool_lun.poolid = emc_pool.id AND
 emc_pool_lun.lunid = emc_lun.id");
  while ( $row = $statsDB->getNextNamedRow() ) {
    $poolLuns[] = array( POOL_NAME => $row[POOL_NAME],
             POOL_ID => $row[POOL_ID],
             LUN_NAME => $row[LUN_NAME],
             LUN_ID => $row[LUN_ID] );
  }

  return $poolLuns;
}

function getLunIoSizeData($statsDB,$sysid,$fromDate,$toDate) {
  global $debug, $lunIoSizes;

  $sql = "SELECT emc_lun.id AS id, emc_lun.name AS name ";
  foreach ( array( 'read', 'write' ) as $rw ) {
    if ( $debug ) { echo "<p>getLunIoSizeData rw=$rw</p>\n"; }

    foreach ( $lunIoSizes as $size ) {
      $colName = $rw . "_" . $size;
      $sql .= sprintf(", ROUND( AVG( %s ), 1 ) AS '%s'", $colName, $colName);
    }
  }
  $sql .= "
FROM emc_lun, emc_lun_iosize
WHERE
 emc_lun.sysid = $sysid AND emc_lun_iosize.lunid = emc_lun.id AND
 emc_lun_iosize.time BETWEEN '$fromDate' AND '$toDate'
GROUP BY emc_lun_iosize.lunid";
  $statsDB->query($sql);

  $result = array();
  while ($row = $statsDB->getNextNamedRow()) {
    $resultRow = array();
    $resultRow[LUN_ID] = $row['id'];
    $resultRow['name'] = $row['name'];

    foreach ( array( 'read', 'write' ) as $rw ) {
      $stats = array();
      foreach ( $lunIoSizes as $size ) {
    $colName = $rw . "_" . $size;
    $stats[] = $row[$colName];
      }
      $resultRow[$rw] = $stats;
    }

    $result[] = $resultRow;
  }

  return $result;
}

function getLunMainData($statsDB,$sysid,$fromDate,$toDate) {
  global $lunMainColMap, $rgLunMainColMap, $debug;

  list($date) = explode(" ", $fromDate);
  $statsDB->query("
SELECT
 emc_lun_rg.lunid, emc_lun_rg.rgid, emc_rg.name
FROM emc_lun_rg, emc_rg
WHERE
 emc_lun_rg.rgid = emc_rg.id AND
 emc_lun_rg.sysid = $sysid AND emc_lun_rg.filedate = '$date'");
  $lunToRg = array();
  while ($row = $statsDB->getNextRow()) {
       list($d1,$d2,$rgNum) = explode(" ", $row[2]);
       $lunToRg[$row[0]] = $row[1] . "," . $rgNum;
  }

  $columns = array("emc_lun.id AS id", "emc_lun.name AS name ");
  foreach ( array( $lunMainColMap, $rgLunMainColMap ) as $colMap ) {
      foreach ( $colMap as $label => $param ) {
          $key = $param['key'];
          if ( array_key_exists( 'col', $param ) ) {
              $dbCol = $param['col'];
          } else {
              $dbCol = $param['key'];
          }
          $columns[] = "ROUND( AVG( $dbCol ), 1 ) AS $key";
      }
  }
  $columnsStr = implode(",",$columns);

  # Try the new way where the sysid in emc_lun_stats column is populated
  $sql = "SELECT $columnsStr
FROM emc_lun_stats FORCE INDEX(sysIdIdx), emc_lun
WHERE
 emc_lun_stats.sysid = $sysid AND
 emc_lun_stats.time BETWEEN '$fromDate' AND '$toDate' AND
 emc_lun_stats.lunid = emc_lun.id
GROUP BY emc_lun_stats.lunid
ORDER BY emc_lun_stats.utilization DESC";
  $statsDB->query($sql);
  if ( $statsDB->getNumRows() == 0 ) {
      # If we don't find any rows using the new way, then we
      # assume that the sysid column is not populated and we have
      # to query the old way
      $sql = "
SELECT $columnsStr
FROM emc_lun, emc_lun_stats
WHERE
 emc_lun_stats.sysid IS NULL AND
 emc_lun_stats.time BETWEEN '$fromDate' AND '$toDate' AND
 emc_lun.sysid = $sysid AND emc_lun_stats.lunid = emc_lun.id
GROUP BY emc_lun_stats.lunid
ORDER BY emc_lun_stats.utilization DESC";
      $statsDB->query($sql);
  }

  $result = array();
  while ($row = $statsDB->getNextNamedRow()) {
    $resultRow = array();
    $resultRow[LUN_ID] = $row['id'];

    if ( array_key_exists( $row['id'], $lunToRg ) ) {
      $resultRow['rgid'] = $lunToRg[$row['id']];
    } else {
      $resultRow['rgid'] = "";
    }

    $resultRow['name'] = $row['name'];

    foreach ( array( $lunMainColMap, $rgLunMainColMap ) as $colMap ) {
      foreach ( $colMap as $label => $param ) {
    $resultRow[$param['key']] = $row[$param['key']];
      }
    }
    $result[] = $resultRow;
  }

  if ( $debug > 0 ) { echo "<pre>getLunMainData: result\n"; print_r($result); echo "</pre>\n"; }
  return $result;
}

function getPoolRG($statsDB,$sysid,$fromDate,$toDate,$poolName) {
  global $rgColMap;

  list($date) = explode(" ", $fromDate);
  $sql = "SELECT emc_pool_rg.rgid AS rgid, emc_rg.name AS name";
  foreach ( $rgColMap as $label => $param ) {
    $sql .= ", ROUND( AVG( emc_rg_stats.$param ), 1 ) AS $param";
  }
  $sql .= "
FROM emc_pool_rg, emc_rg, emc_pool, emc_rg_stats
WHERE
 emc_pool_rg.poolid = emc_pool.id AND emc_pool.name = '$poolName' AND
 emc_pool_rg.rgid = emc_rg.id AND
 emc_pool_rg.sysid = $sysid AND
 emc_pool_rg.filedate = '$date' AND
 emc_pool_rg.rgid = emc_rg_stats.rgid AND
 emc_rg_stats.time BETWEEN '$fromDate' AND '$toDate'
GROUP BY emc_rg_stats.rgid";
  $statsDB->query($sql);

  $result = array();
  while ($row = $statsDB->getNextNamedRow()) {
    $result[] = $row;
  }

  return $result;
}

function getSpFsRows($statsDB, $sysid, $fromDate, $toDate) {
    $colMap = array(
        'File Read Bandwidth (MB/s)' => 'readkb/1024',
        'File Read Throughput (IO/s)' => 'readiops',
        'File Write Bandwidth (MB/s)' => 'writekb/1024',
        'File Write Throughput (IO/s)' => 'writeiops'
    );

    $sql = "SELECT SUBSTRING(emc_filesystem.name, 4, 1) AS sp ";
    foreach ( $colMap as $label => $dbCol ) {
        $sql .= ", ROUND( AVG( $dbCol ), 1 ) AS '$label'";
    }
    $sql .= "
FROM
    emc_filesystem_stats
JOIN emc_sys ON emc_filesystem_stats.sysid = emc_sys.id
JOIN emc_filesystem ON emc_filesystem_stats.fsid = emc_filesystem.id
WHERE
    emc_filesystem_stats.time BETWEEN '$fromDate' AND '$toDate' AND
    emc_filesystem_stats.sysid = $sysid AND
    emc_filesystem.name IN ( 'SP A', 'SP B' )
GROUP BY emc_filesystem.name";

    $statsDB->query($sql);
    $fsRows = array();
    if ( $statsDB->getNumRows() > 0 ) {
        $stats = array();
        while ($row = $statsDB->getNextNamedRow()) {
            $stats[$row['sp']] = $row;
        }

        foreach ( $colMap as $label => $dbCol ) {
            /* Don't display null values */
            if ( is_null($stats['A'][$label]) ) {
                continue;
            }
            $fsRows[] = array( $label, $stats['A'][$label], $stats['B'][$label]);
        }
    }

    return $fsRows;
}

function addOverallGraphs($sysid, &$graphTableLines) {
    $iops = new ModelledGraph('common/emc_overall_iops');
    $iopsRow = sprintf(
        "<tr> <td colspan=4>%s</td></tr>\n",
        $iops->getImage(array(SYS_ID => $sysid))
    );

    $bw = new ModelledGraph('common/emc_overall_bw');
    $bwRow = sprintf(
        "<tr> <td colspan=4>%s</td></tr>\n",
        $bw->getImage(array(SYS_ID => $sysid))
    );

    array_splice($graphTableLines, 1, 0, array($iopsRow,$bwRow));
}

function getSpTable($statsDB,$sysid,$fromDate,$toDate) {
    global $debug;

    $colMap = array(
        'Utilization (%)' => 'utilization',
        'Block Read Bandwidth (MB/s)' => 'readbw',
        'Block Read Throughput (IO/s)' => 'readiops',
        'Block Write Bandwidth (MB/s)' => 'writebw',
        'Block Write Throughput (IO/s)' => 'writeiops',
        'SP Cache Dirty Pages (%)' => 'spc_dirty',
        'SP Cache MBs Flushed (MB/s)' => 'spc_flushbw',
        'SP Cache Read Hit Ratio(%)' => 'spc_read_hr',
        'SP Cache Write Hit Ratio(%)' => 'spc_write_hr',
        'Core 0 Utilization(%)' => 'cpu0_util',
        'Core 1 Utilization(%)' => 'cpu1_util',
        'Core 2 Utilization(%)' => 'cpu2_util',
        'Core 3 Utilization(%)' => 'cpu3_util'
    );

    $sql = "SELECT sp ";
    foreach ( $colMap as $label => $dbCol ) {
        $sql .= ", ROUND( AVG( $dbCol ), 1 ) AS '$label'";
    }
    $sql .= " FROM emc_sp_stats WHERE sysid = $sysid AND time BETWEEN '$fromDate' AND '$toDate' GROUP BY sp";
    $statsDB->query($sql);

    $stats = array();
    while ($row = $statsDB->getNextNamedRow()) {
        $stats[$row['sp']] = $row;
    }

    if ( $debug > 0 ) {
        echo "<pre>getSpTable: stats\n"; print_r($stats); echo "</pre>\n";
    }
    // If we don't have SP stats then return nulls here
    if ( count($stats) == 0) {
      return array(null, null);
    }

    $summaryTable = new HTML_Table(BORDER);
    $summaryTable->addRow( array( '', 'SP A', 'SP B'), null, 'th');
    foreach ( $colMap as $label => $dbCol ) {
        /* Don't display null values */
        if ( is_null($stats['A'][$label]) ) {
            continue;
        }
        $summaryTable->addRow( array( $label, $stats['A'][$label], $stats['B'][$label]) );
    }

    $fsRows = getSpFsRows($statsDB, $sysid, $fromDate, $toDate);
    foreach ( $fsRows as $fsRow ) {
        $summaryTable->addRow($fsRow);
    }

    $dbColToLabel = array();
    foreach ( $colMap as $label => $dbCol ) {
        $dbColToLabel[$dbCol] = $label;
    }
    $graphLayout = array(
        array(COLSPAN => 4, GRAPHS => array('utilization')),
        array(COLSPAN => 2, GRAPHS => array('readbw', 'writebw')),
        array(COLSPAN => 2, GRAPHS => array('readiops', 'writeiops')),
        array(COLSPAN => 2, GRAPHS => array('spc_dirty', 'spc_flushbw')),
        array(COLSPAN => 2, GRAPHS => array('spc_read_hr', 'spc_write_hr')),
        array(COLSPAN => 1, GRAPHS => array('cpu0_util', 'cpu1_util', 'cpu2_util', 'cpu3_util'))
    );

    $sqlParamWriter = new SqlPlotParam();
    $graphTableLines = array();
    $graphTableLines[] = "<table border=0>";
    foreach ( $graphLayout as $graphRow ) {
        if ( is_null($stats['A'][$dbColToLabel[$graphRow[GRAPHS][0]]]) ) {
            continue;
        }
        $graphTableLines[] =  " <tr>\n";
        foreach ( $graphRow[GRAPHS] as $graph ) {
            $label = $dbColToLabel[$graph];
            $sqlParam = SqlPlotParamBuilder::init()
                      ->title($label)
                      ->yLabel('')
                      ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array( $graph => $label ),
                          array('emc_sp_stats'),
                          'emc_sp_stats.sysid = %d',
                          array( SYS_ID ),
                          'sp'
                      )
                      ->build();

            $id = $sqlParamWriter->saveParams($sqlParam);
            $url = $sqlParamWriter->getImgURL(
                $id,
                "$fromDate",
                "$toDate",
                true,
                $graphRow[COLSPAN] * 240,
                240,
                "sysid=$sysid"
            );
            $graphTableLines[] = "  <td colspan=" . $graphRow[COLSPAN] . ">" . $url . "</td>\n";
        }
        $graphTableLines[] =  " </tr>\n";
    }
    $graphTableLines[] = "</table>";

    if ( count($fsRows) > 0 ) {
        addOverallGraphs($sysid, $graphTableLines);
    }

    return array($summaryTable,$graphTableLines);
}

function getCfgTable($statsDB,$sysid,$date) {
  $table = new HTML_Table(BORDER);
  $row = $statsDB->queryNamedRow("SELECT * FROM emc_config WHERE sysid = $sysid AND date = '$date'");
  if ( $row ) {
    $cols = array( 'name'   => 'Name',
           'model'  => 'Model',
           'version'=> 'Version',
           'writecache' => 'Write Cache(MB)',
           'readcache'  => 'Read Cache(MB)',
           'freemem'    => 'Free Memory(MB)',
           'lwm'        => 'Low Water Mark(%)',
           'hwm'        => 'High Water Mark(%)',
           'dae'        => 'Disk Enclosures',
           'disks'      => 'Disks' );
    foreach ( $cols as $colName => $label ) {
      if ( ! is_null($row[$colName]) ) {
        $table->addRow( array( $label, $row[$colName] ) );
      }
    }
  }

  return $table;
}

function showPoolCfgTable($statsDB,$sysid,$date) {
  $table =
    new SqlTable("pool_cfg",
                 array(
                       array( 'key' => 'name', 'db' => 'emc_pool.name', 'label' => 'Name' ),
                       array( 'key' => 'size', 'db' => 'emc_pool_cfg.sizeGB', 'label' => 'Size(GB)' ),
                       array( 'key' => 'used', 'db' => 'emc_pool_cfg.usedGB', 'label' => 'Used(GB)' ),
                       array( 'key' => 'usedpercent', 'db' => 'ROUND((emc_pool_cfg.usedGB*100)/emc_pool_cfg.sizeGB,1)', 'label' => 'Used(%)' ),
                       array( 'key' => 'disks', 'db' => 'emc_pool_cfg.numdisks', 'label' => '#Disks' ),
                       array( 'key' => 'raid', 'db' => 'emc_pool_cfg.raid', 'label' => 'RAID Type' )
                       ),
                 array( 'emc_pool_cfg', 'emc_pool' ),
                 "emc_pool_cfg.sysid = $sysid AND emc_pool_cfg.poolid = emc_pool.id AND emc_pool_cfg.filedate = '$date'",
                 TRUE,
                 array( 'order' => array( 'by' => 'name', 'dir' => 'ASC') )
                 );
  echo $table->getTable();
}

function getGraphDef($label,$param) {

  $where = 'emc_lun.sysid = %d AND emc_lun.id IN ( %s ) AND emc_lun.id = emc_lun_stats.lunid';
  if ( $param['key'] == 'readsize' ) {
    $where = $where . ' AND readiops > 0';
  } else if ( $param['key'] == 'writesize' ) {
    $where = $where . ' AND writeiops > 0';
  }

  $dbCol = $param['key'];
  if ( array_key_exists( 'col', $param ) ) {
    $dbCol = $param['col'];
  }

  $sqlParam =
    array( 'title'    => $label,
           'ylabel'     => '',
           'useragg'    => 'true',
       'persistent' => 'false',
       'type'       => 'tsc',
       'querylist' =>
       array(
         array (
            'timecol' => 'time',
            SqlPlotParam::MULTI_SERIES => "SUBSTRING_INDEX(emc_lun.name, '[',1)",
            'whatcol' => array( $dbCol => $label ),
            SqlPlotParam::TABLES  => "emc_lun, emc_lun_stats",
            SqlPlotParam::WHERE   => $where,
            SqlPlotParam::Q_ARGS   => array( SYS_ID, 'lunids' )
            )
         )
       );

  return $sqlParam;
}

function getRgGraphDef($label,$param) {

  $where = 'emc_rg.sysid = %d AND emc_rg.id IN ( %s ) AND emc_rg.id = emc_rg_stats.rgid';

  $sqlParam =
    array( 'title'    => $label,
           'ylabel'     => '',
           'useragg'    => 'true',
       'persistent' => 'false',
       'type'       => 'tsc',
       'querylist' =>
       array(
         array (
            'timecol' => 'time',
            SqlPlotParam::MULTI_SERIES => "emc_rg.name",
            'whatcol' => array( $param => $label ),
            SqlPlotParam::TABLES  => "emc_rg, emc_rg_stats",
            SqlPlotParam::WHERE   => $where,
            SqlPlotParam::Q_ARGS   => array( SYS_ID, 'rgids' )
            )
         )
       );

  return $sqlParam;
}

function plotLUNs($statsDB,$sysid,$fromDate,$toDate) {
  global $debug, $lunMainColMap;
  //phpinfo(INFO_VARIABLES);

  $lunIdsStr = $_REQUEST['lunids'];
  $lunIds = explode(",",$lunIdsStr);

  $stat = $_REQUEST['plotluns'];
  $label = "";
  foreach ( $lunMainColMap as $aLabel => $aParam ) {
      if ( $stat == $aParam['key'] ) {
    $label = $aLabel;
    $param = $aParam;
      }
  }
  if ( $label == "" ) {
    /* Check the rg specific stats */
    foreach ( $rgLunMainColMap as $aLabel => $aParam ) {
      if ( $stat == $aParam['key'] ) {
    $label = $aLabel;
    $param = $aParam;
      }
    }
  }

  if ( $debug ) { echo "<p>stat=$stat label=$label</p>\n"; }

  if ( $label == "" ) {
    return;
  }

  $sqlParam = getGraphDef($label,$param);

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  $url = $sqlParamWriter->getURL($id, "$fromDate", "$toDate", "sysid=$sysid&lunids=" . implode(",", $lunIds));
  if ( $debug ) {
    echo "<p>url=$url</p>\n";
  } else {
    header(LOCATION . $url);
  }
}

function plotRGs($statsDB,$sysid,$fromDate,$toDate) {
  global $debug, $rgColMap;
  //phpinfo(INFO_VARIABLES);

  $rgIds = explode(",", requestValue('rgids') );

  $stat = $_REQUEST['plotrgs'];
  $label = "";
  foreach ( $rgColMap as $aLabel => $aParam ) {
      if ( $stat == $aParam ) {
    $label = $aLabel;
    $param = $aParam;
      }
  }

  if ( $debug ) { echo "<p>stat=$stat label=$label</p>\n"; }

  if ( $label == "" ) {
    return;
  }

  $sqlParam = getRgGraphDef($label,$param);

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  $url = $sqlParamWriter->getURL($id, "$fromDate", "$toDate", "sysid=$sysid&rgids=" . implode(",", $rgIds));
  if ( $debug ) {
    echo "<p>url=$url</p>\n";
  } else {
    header(LOCATION . $url);
  }
}

function showLUNs( $statsDB,$sysid,$fromDate,$toDate, $lunIdsStr) {
  global $lunMainColMap;

  echo "<H1>LUN Stats</H1>\n";

  $graphTable = new HTML_Table('border=0');

  $sqlParamWriter = new SqlPlotParam();
  foreach ( $lunMainColMap as $label => $param ) {
    $sqlParam = getGraphDef($label,$param);
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url = $sqlParamWriter->getImgURL($id, "$fromDate", "$toDate", true, 640, 240, "sysid=$sysid&lunids=$lunIdsStr");
    $graphTable->addRow(array($url));
  }

  echo $graphTable->toHTML();
}

function showRgs( $statsDB,$sysid,$fromDate,$toDate, $rgIdsStr) {
  global $rgColMap;

  echo "<H1>RAID Groups</H1>\n";

  $graphTable = new HTML_Table('border=0');
  $sqlParamWriter = new SqlPlotParam();
  foreach ( $rgColMap as $label => $param ) {
    $sqlParam = getRgGraphDef($label,$param);
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url = $sqlParamWriter->getImgURL($id, "$fromDate", "$toDate", true, 640, 240, "sysid=$sysid&rgids=$rgIdsStr");
    $graphTable->addRow(array($url));
  }
  echo $graphTable->toHTML();
}

function getAlertsTable( $sysid, $date ) {
    return SqlTableBuilder::init()
        ->name("alerts")
        ->tables(array("emc_alerts"))
        ->where("emc_alerts.sysid = $sysid AND emc_alerts.date = '$date'")
        ->addSimpleColumn('msg', 'Message')
        ->paginate()
        ->build();
}

function showRg( $statsDB,$sysid,$fromDate,$toDate, $lunid, $rgId,$rgNum) {
  global $rgColMap;

  echo "<H1>RAID Group $rgNum</H1>\n";

  $table = new HTML_Table(BORDER);
  $headerRow = array( 'Name');
  foreach ( $rgColMap as $label => $dbCol ) {
    $headerRow[] = $label;
  }
  $table->addRow( $headerRow, null, 'th');

  $sql = "SELECT emc_rg.name AS Name ";
  foreach ( $rgColMap as $label => $dbCol ) {
    $sql .= ", ROUND( AVG( $dbCol ), 1 ) AS '$label'";
  }
  $sql .= "
FROM emc_rg, emc_rg_stats
WHERE
 emc_rg.sysid = $sysid AND emc_rg_stats.rgid = emc_rg.id AND
 emc_rg_stats.rgid = $rgId AND
 emc_rg_stats.time BETWEEN '$fromDate' AND '$toDate'
GROUP BY emc_rg_stats.rgid
ORDER BY emc_rg.name";
  $statsDB->query($sql);
  while ($row = $statsDB->getNextNamedRow()) {
    $tableRow = array();
    $tableRow[] = $row['Name'];
    foreach ( $rgColMap as $label => $dbCol ) {
      $tableRow[] = $row[$label];
    }
    $table->addRow($tableRow);
  }

  echo $table->toHTML();

  $graphTable = new HTML_Table('border=0');
  foreach ( $rgColMap as $label => $dbCol ) {
    $sqlParam =
      array( 'title'    => $label,
         'ylabel'     => '',
         'useragg'    => 'true',
         'persistent' => 'false',
         'type'       => 'tsc',
         'querylist' =>
         array(
           array (
              'timecol' => 'time',
              SqlPlotParam::MULTI_SERIES => 'emc_rg.name',
              'whatcol' => array( $dbCol => $label ),
              SqlPlotParam::TABLES  => "emc_rg, emc_rg_stats",
              SqlPlotParam::WHERE   => "emc_rg.id = %d AND emc_rg.id = emc_rg_stats.rgid",
              SqlPlotParam::Q_ARGS   => array( 'rgid' )
              )
           )
         );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url = $sqlParamWriter->getImgURL($id, "$fromDate", "$toDate", true, 640, 240, "rgid=$rgId");
    $graphTable->addRow(array($url));
  }

  echo $graphTable->toHTML();
}

function plotFS($sysid, $selected) {
    $graphs = array();
    $graphParam = array(SYS_ID => $sysid, 'fsidlist' => $selected);
    getGraphsFromSet( 'all', $graphs, 'common/emc_filesystem_stats', $graphParam);
    plotGraphs($graphs, 2);
}

function plotNfsV4($sysid, $selected) {
    $graphs = array();
    $quoteSelected = "'" . str_replace(",", "','", $selected) . "'";
    $graphParam = array(SYS_ID => $sysid, 'oplist' => $quoteSelected);
    getGraphsFromSet( 'all', $graphs, 'common/emc_nfsv4_ops', $graphParam);
    plotGraphs($graphs, 2);
}

#
# Main
#

$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = "$date 00:00:00";
   $toDate = "$date 23:59:59";
}
$sysid = requestValue(SYS_ID);
$plot = requestValue('plot');

if ( isset($plot) ) {
    $selected = requestValue('selected');
    if ( $plot === 'fs' ) {
        plotFS($sysid, $selected);
    } elseif ( $plot === 'fsused' ) {
        plotFSUsed($sysid, $selected);
    } elseif ( $plot === 'nfsv4op' ) {
        plotNfsV4($sysid, $selected);
    }
    exit;
}

if ( isset($_REQUEST['plotluns']) ) {
  plotLUNs($statsDB,$sysid,$fromDate,$toDate);
  exit;
} else if ( isset($_REQUEST['plotrgs']) ) {
  plotRGs($statsDB,$sysid,$fromDate,$toDate);
  exit;
}

if ( isset($_REQUEST['rgid']) ) {
  showRg($statsDB, $sysid, $fromDate, $toDate, $_REQUEST[LUN_ID], $_REQUEST['rgid'], $_REQUEST['rgnum']);
} else if ( isset($_REQUEST['showrgs']) ) {
    showRGs( $statsDB, $sysid, $fromDate,$toDate, $_REQUEST['showrgs'] );
} else if ( isset($_REQUEST['showluns']) ) {
    showLUNs( $statsDB, $sysid, $fromDate,$toDate, $_REQUEST['showluns'] );
} else {
  $rowOfTables = array();

  $row = $statsDB->queryRow("SELECT name FROM emc_sys WHERE id = $sysid");
  echo "<H1>$row[0]</H1>\n";

  $hasFsRow = $statsDB->queryRow("
  SELECT sysid
  FROM emc_filesystem_stats
  WHERE
   sysid = $sysid AND
   time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
  LIMIT 1
  ");
  $hasFs = ! is_null($hasFsRow);

  $cfgTable = getCfgTable($statsDB,$sysid,$fromDate);
  if ( $cfgTable->getRowCount() > 0 ) {
    drawHeaderWithHelp("Array Configuration", 2, "arrayconfiguration", "DDP_Bubble_474_ENM_HW_EMC_ARRAYCONFIGURATION");
    echo $cfgTable->toHTML();
  }

  $poolLuns = getPoolLuns($statsDB,$sysid,$fromDate);
  /* If we have pool LUNs then display the pool cfg table */
  $row = $statsDB->queryRow("
  SELECT sysid
  FROM emc_pool_cfg
  WHERE
   sysid = $sysid AND
   filedate = '$fromDate' AND
   dataReductionRatio IS NOT NULL
LIMIT 1");
  $hasDataReduction = ! is_null($row);
  if ( count($poolLuns) > 0 ) {
      if ( $hasDataReduction ) {
        $table = new ModelledTable(
            'common/emc_pool_cfg',
            'poolconfiguration',
            array(
                SYS_ID => $sysid,
            )
        );
        echo $table->getTableWithHeader("Pool Configration");
      } else {
        drawHeaderWithHelp("Pool Configuration", 2, "poolconfiguration", "DDP_Bubble_475_ENM_HW_EMC_POOLCONFIGURATION");
        showPoolCfgTable($statsDB,$sysid,$fromDate);
      }
  }

  $alertsTable = getAlertsTable($sysid, $fromDate);
  if ( $alertsTable->hasRows() ) {
      drawHeaderWithHelp("Alerts/Issues", 2, "emc_alerts");
      echo $alertsTable->getTable();
  }

  list($spTable, $spGraphTableLines) = getSpTable($statsDB, $sysid, $fromDate, $toDate);
  if ( ! is_null($spTable) ) {
    drawHeaderWithHelp("SP Stats", 2, "spstats", "DDP_Bubble_476_ENM_HW_EMC_SPSTATS");
    echo  $spTable->toHTML();
    echo addLineBreak();
    echo implode("\n", $spGraphTableLines);
  }

  /* Split up the LUN data to pools and "RAIDGroup" based LUNs */
  $lunToPool = array();
  $poolLunByName = array();
  foreach ( $poolLuns as $poolLun ) {
    $lunToPool[$poolLun[LUN_ID]] = $poolLun[POOL_NAME];
    $poolLunByName[$poolLun[LUN_NAME]] = $poolLun;
  }
  if ( $debug > 0 ) { echo "<pre>lunToPool\n"; print_r($lunToPool); echo "</pre>\n"; }

  $lunMainDataRG = array();
  $lunMainDataByPool = array();
  foreach ( getLunMainData($statsDB,$sysid,$fromDate,$toDate) as $resultRow ) {
    $trimmedLunName = explode(' ', $resultRow['name'] )[0];
    if ($debug > 0) {
        echo "<pre>checking LUN $trimmedLunName: " . $resultRow[LUN_ID] . "<pre>\n";
    }

    if ( array_key_exists($resultRow[LUN_ID], $lunToPool) ) {
      $pool = $lunToPool[$resultRow[LUN_ID]];
      if ($debug > 0) {
          echo "<pre>mapped " . $resultRow[LUN_ID] . " to $pool\n</pre>\n";
      }
      if ( ! array_key_exists($pool,$lunMainDataByPool) ) {
    $lunMainDataByPool[$pool] = array();
      }
      $lunMainDataByPool[$pool][] = $resultRow;
    } elseif ( array_key_exists($trimmedLunName, $poolLunByName) ) {
      $pool = $poolLunByName[$trimmedLunName][POOL_NAME];
      if ( ! array_key_exists($pool, $lunMainDataByPool) ) {
          $lunMainDataByPool[$pool] = array();
      }
      $lunMainDataByPool[$pool][] = $resultRow;
    } else {
      $lunMainDataRG[] = $resultRow;
    }
  }

  if ( $debug > 0 ) { echo "<pre>pool LUNs\n"; print_r($lunMainDataByPool); echo "</pre>\n"; }

  /* From here onwards we're generating the Javascript to "drive" the page */
  $selfURL = $_SERVER['PHP_SELF'] . "?" . $webargs . "&sysid=$sysid";
  echo <<<EOT
<script type="text/javascript" src="$php_webroot/emc_stor.js"></script>
<script type="text/javascript">
var selfURL = "$selfURL";

EOT;

  /* Define the columns used to display LUN data */
  foreach ( $lunMainColMap as $label => $param ) {
    $key = $param['key'];
    echo "lunTableCol.push( { key:\"$key\", label:\"$label\" } );\n";
  }
  echo "\n";

  foreach ( $rgLunMainColMap as $label => $param ) {
    $key = $param['key'];
    echo "rgLunTableCol.push( { key:\"$key\", label:\"$label\" } );\n";
  }
  echo "\n";
  /* Define the columns used to display LUN IO Size data */
  echo "var lunIoSizes = [ \"" . join("\",\"", $lunIoSizes) . "\" ];\n";
  echo "\n";
  /* Define the columns used to display RG data */
  foreach ( $rgColMap as $label => $param ) {
    echo "rgTableCol.push( { key:\"$param\", label:\"$label\" } );\n";
  }
  echo "\n";
  echo "</script>\n";

  if ( count($lunMainDataRG) > 0 ) {
    drawHeaderWithHelp("RAIDGroup LUN Stats", 2, "raidgrouplunstats", "DDP_Bubble_477_ENM_HW_EMC_RAIDGROUPLUNSTATS");
    outputLunMain($lunMainDataRG,"raidgroup",TRUE);
    outputLunIoSize($statsDB,$sysid,$fromDate,$toDate);
  }

  foreach ( $lunMainDataByPool as $poolName => $lunData ) {
    drawHeaderWithHelp( "Pool: $poolName", 2, "poolHelp" );
    outputLunMain($lunData,$poolName,FALSE);
    outputPoolRG($statsDB,$sysid,$fromDate,$toDate,$poolName);

    if ( $hasDataReduction ) {
        $row = $statsDB->queryRow("SELECT id FROM emc_pool WHERE sysid = $sysid AND name = '$poolName'");
        $table = new ModelledTable(
            'common/emc_pool_lun',
            'poollunusage',
            array(
                SYS_ID => $sysid,
                POOL_ID => $row[0]
            )
        );
        echo $table->getTableWithHeader("LUN Usage", 4);
    }
  }
}

if ( $hasFs ) {
    drawHeader("File Systems", HEADER_1, "filesystems");
    $fsIds = array();
    $statsDB->query("SELECT id FROM emc_filesystem WHERE name IN ('SP A', 'SP B') AND sysid = $sysid");
    while ($row = $statsDB->getNextRow()) {
        $fsIds[] = $row[0];
    }
    plotFS($sysid, implode(",", $fsIds));

    $table = new ModelledTable(
        'common/emc_filesystem_stats',
        'fs_stats',
        array(
            SYS_ID => $sysid,
            'url' => makeSelfLink() . "&sysid=$sysid"
        )
    );
    echo $table->getTableWithHeader("File System Stats");

    $table = new ModelledTable(
        'common/emc_filesystem_state',
        'fs_state',
        array(
            SYS_ID => $sysid,
            'url' => makeSelfLink() . "&sysid=$sysid"
        )
    );
    echo $table->getTableWithHeader("File System Usage");


    $table = new ModelledTable(
        'common/emc_nfsv4_ops',
        'nfsv4op',
        array(
            SYS_ID => $sysid,
            'url' => makeSelfLink() . "&sysid=$sysid"
        )
    );
    echo $table->getTableWithHeader("NFS V4 Operations");
}

if ( $debug > 1 ) {
  echo <<<EOS
<div id="myLogger" class="yui-log-container yui-log">
 <style>
#myLogger {position:relative;float:right;margin:1em;}
 </style>
</div>
<script type="text/javascript">
var myLogReader = new YAHOO.widget.LogReader("myLogReader", {verboseOutput:false});
</script>

EOS;

}

$statsDB->disconnect();
include "common/finalise.php";
?>


