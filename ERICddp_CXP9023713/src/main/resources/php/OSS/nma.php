<?php
$pageTitle = "NMA";

/* Disable the UI for non-main flow */
if (isset($_GET["getdata"]) || isset($_REQUEST['action'])) {
    $UI = false;
}

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/JsPlot.php";

if ( isset($_GET['start']) ) {
  $fromDate = $_GET['start'];
  $toDate = $_GET['end'];
} else {
  $fromDate = $date;
  $toDate = $date;
}

class SyncStats extends DDPObject {
  var $cols = array(
            'time'         => 'Completed',
            'ne'           => 'Node',
            'twait'        => 'Time Waiting in Pool',
            'tneconnect'   => 'Time to Connect to NE',
            'tneresp'      => 'Time for NE Response',
            'tneclose'     => 'Time to Disconnect from NE',
            'tnesub'       => 'Time to Subscribe to NE',
            'tnma'         => 'NMA Time',
            'ttotal'       => 'Total Time',
            'treadmo'      => 'Time to Read MOs from CS',
            'tcreatemo'    => 'Time to Create MOs in CS',
            'tupdatemo'    => 'Time to Update MOs in CS',
            'tdeletemo'    => 'Time to Delete MOs in CS',
            'ncreatedmo'   => 'Num MOs Created in CS',
            'ndeletedmo'   => 'Num MOs Deleted in CS',
            'nupdatedmo'   => 'Num MOs Updated in CS'
            );

    var $title = "Sync Statistics";

    var $defaultLimit = 25;
    var $defaultOrderBy = "time";
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");
    var $filter = "";

    function __construct($filter = "") {
        parent::__construct("syncstats");
        $this->filter = $filter;
    }
    function getData() {
        global $date;
    global $site;
    $sql = "
SELECT
 DATE_FORMAT(time, '%H:%i:%s') AS time,ne.name AS ne,
 twait, tneconnect, tneresp, tneclose, tnesub, tnma, ttotal, treadmo, tcreatemo,
 tupdatemo, tdeletemo, ncreatedmo, nupdatedmo, ndeletedmo
FROM nma_sync_success, sites, ne
WHERE
 nma_sync_success.siteid = sites.id AND sites.name = '$site' AND
 nma_sync_success.neid = ne.id AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'

";
    $this->populateData($sql);
    return $this->data;
    }
}


class NotifRec extends DDPObject {
    var $cols = array(
                      array('key' => 'eventtype', 'label' => 'Event Type'),
                      array('key' => 'nodetype', 'label' => 'Node Type'),
                      array('key' => 'mo', 'label' => 'MO'),
                      array('key' => 'attrib', 'label' => 'Attribute'),
                      array('key' => 'count', 'label' => '#Events', 'formatter' => 'ddpFormatNumber')
                      );

    var $title = "notifRec";

    function __construct() {
        parent::__construct("notifRec");
    }

    function getData() {
        global $date;
    global $site;
    $sql = "
SELECT
  nma_notifrec.eventtype AS eventtype, nma_notifrec.nodetype AS nodetype,
  mo_names.name AS mo, nead_attrib_names.name AS attrib,
  SUM(nma_notifrec.count) AS count
FROM nma_notifrec, mo_names, nead_attrib_names, sites
WHERE
   nma_notifrec.date = '$date' AND
   nma_notifrec.siteid = sites.id AND sites.name = '$site' AND
   nma_notifrec.moid = mo_names.id AND
   nma_notifrec.attribid = nead_attrib_names.id
GROUP BY
 eventtype, nodetype, moid, attribid
ORDER BY
 count DESC
";
    $this->populateData($sql);
    return $this->data;
    }
}

function getNotifRec($statsDB,$site,$date)
{
    global $webargs;

  $row = $statsDB->queryRow("
SELECT COUNT(*)
 FROM nma_notifrec, sites
 WHERE
   nma_notifrec.date = '$date' AND
   nma_notifrec.siteid = sites.id AND sites.name = '$site'
");
  if ( $row[0] == 0 ) {
    return NULL;
  }

  return array(
           new SqlTable("notif_rec_details",
                array(
                  array( 'key' => 'id', 'visible' => false, 'db' => 'CONCAT(nma_notifrec.eventtype,":",mo_names.name,":",nead_attrib_names.name)'),
                  array( 'key' => 'eventtype', 'label' => 'Event Type' ),
                  array( 'key' => 'nodetype', 'label' => 'Node Type' ),
                  array( 'key' => 'mo', 'db' => 'mo_names.name', 'label' => 'MO' ),
                  array( 'key' => 'attrib', 'db' => 'nead_attrib_names.name', 'label' => 'Attribute' ),
                  array( 'key' => 'count', 'db' => 'sum(nma_notifrec.count)', 'label' => '#Events' )
                  ),
                array( 'nma_notifrec', 'mo_names', 'sites', 'nead_attrib_names' ),
                "nma_notifrec.date = '$date' AND nma_notifrec.siteid = sites.id AND sites.name = '$site' AND nma_notifrec.moid = mo_names.id AND nma_notifrec.attribid = nead_attrib_names.id GROUP BY eventtype, nodetype, moid, attribid",
                TRUE,
                array( 'order' => array( 'by' => 'count', 'dir' => 'DESC'),
                       'rowsPerPage' => 25,
                       'rowsPerPageOptions' => array(50, 100, 1000, 10000),
                       'ctxMenu' => array('key' => 'action',
                                          'multi' => true,
                                          'menu' => array( 'plotnotifrec' => 'Plot for last month'),
                                          'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                          'col' => 'id')
                   )
                ),
           );
}

function plotNotifRec($site, $date, $selectedStr) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
nma_notifrec.siteid = sites.id AND sites.name = '%s' AND
nma_notifrec.eventtype = '%s' AND
nma_notifrec.moid = mo_names.id AND mo_names.name = '%s' AND
nma_notifrec.attribid = nead_attrib_names.id AND nead_attrib_names.name = '%s'
group by date
";
    $queryList = array();
    foreach ( explode(",",$selectedStr) as $selected ) {
        $selectedParts = explode(":",$selected);
        $queryList[] = array(
            'timecol' => 'date',
            'whatcol' => array( 'sum(count)' => $selected ),
            'tables' => "nma_notifrec, mo_names, sites, nead_attrib_names",
            'where' => sprintf($where,$site,$selectedParts[0],$selectedParts[1],$selectedParts[2])
        );
    }

    $sqlParam = array(
        'title' => "Notifications Received",
        'type' => 'tsc',
        'ylabel' => "#Notifications",
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' => $queryList
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59"));
}

function getSyncOnGoingCols($site,$statsDB,$fromDate,$toDate,$cols,$nmaInst) {
  global $debug;

  $sumCols = array();
  foreach ( $cols as $col ) {
    $sumCols[] = "SUM($col) AS $col";
  }
  if ( $debug > 0 ) { echo "<pre>sumCols\n"; print_r($sumCols); echo "</pre>\n"; }

  $sql = "SELECT " . implode(",", $sumCols) . " FROM nma_instr,sites WHERE nma_instr.siteid = sites.id AND sites.name = '$site' AND nma_instr.nameid = $nmaInst AND nma_instr.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
  $totals = $statsDB->queryNamedRow($sql);
  if ( $debug > 0 ) { echo "<pre>totals\n"; print_r($totals); echo "</pre>\n"; }

  $result = array();
  foreach ( $totals as $col => $value ) {
    if ( $value > 0 ) {
      $result[$col] = substr($col,19);
    }
  }
  if ( $debug > 0 ) { echo "<pre>result\n"; print_r($result); echo "</pre>\n"; }


  return $result;
}

function graphTable($statsDB,$site,$fromDate,$toDate,$nmaInst) {
  global $debug;

  /* Frist get the list of NoOfSynchOngoingFor cols */
  $statsDB->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'nma_instr' AND TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE 'NoOfSynchOngoingFor%'");
  $cols = array();
  while ( $row = $statsDB->getNextRow() ) {
    $cols[] = $row[0];
  }
  if ( $debug > 0 ) { echo "<pre>cols\n"; print_r($cols); echo "</pre>\n"; }

  $plots =
    array(
      'nodes' => array( 'title'  => 'Total/Alive/Synced Nodes',
                'ylabel' => 'Nodes',
                'whatcol'=> array( 'TotalNumberOfNodes' => 'Total',
                           'NumberOfTotalAliveNodes' => 'Alive',
                           'NumberOfTotalNodesSynched' => 'Synced' )
                ),
      'sync' => array( 'title'  => 'Node Sync Status',
               'ylabel' => 'Nodes',
               'whatcol'   => array( 'NoOfUnSynchedNodes' => 'Unsynced',
                         'NoOfPartiallySynchedNodes' => 'Partial Synced')
               ),
      'ongoing' => array( 'title'  => 'Ongoing Node Syncs',
                  'ylabel' => 'Nodes',
                  'whatcol' => array()
                  ),
      'notif_buf' => array( 'title'  => 'Notification Buffer Size',
                'ylabel' => 'Notifications',
                'whatcol'   => array('NoOfNotificationInBuffer' => 'Notifications Buffer Size')
                ),
      'notif_rec' => array( 'title'  => 'Notifications Received',
                'ylabel' => 'Notifications',
                'whatcol'   => array('TotalNotificationsReceived' => 'Notifications')
                ),
      'notiftime' => array( 'title'  => 'Notification Processing Time',
                'ylabel' => 'MilliSec',
                'whatcol'   => array('TotalTimeTakenToProcessNotifications' => 'Time' ),
                ),
      'nesu' => array( 'title'  => 'Ping Thread Pool',
               'ylabel' => 'Tasks',
               'whatcol'   => array('NESup_Execution' => 'Executing',
                        'NESup_Waiting' => 'Waiting')
               ),
      'ping_count' => array( 'title'  => 'Ping Counts',
                 'ylabel' => 'Counts',
                 'whatcol'   => array('TotalSuccessfulPings' => 'Successful',
                              'TotalFailedPings' => 'Failed')
                 )
      );

  $sqlParamWriter = new SqlPlotParam();
  $graphTable = new HTML_Table("border=1");
  $graphTable->addRow( array_keys( $nmaInst ), null, 'th' );
  foreach ( $plots as $key => $param ) {
    $row = array();
    $sqlParam =
      array( 'title'      => $param['title'],
         'ylabel'     => $param['ylabel'],
         'useragg'    => 'true',
         'persistent' => 'true',
         'querylist' =>
         array(
           array(
             'timecol' => 'time',
             'whatcol'    => $param['whatcol'],
             'tables'  => "nma_instr, sites",
             'where'   => "nma_instr.siteid = sites.id AND sites.name = '%s' AND nma_instr.nameid = %d",
             'qargs'   => array( 'site', 'nmaid' )
             )
            )
         );
    $graphHeight = 200;
    if ( $key == 'ongoing' ) {
      $graphHeight = 300;
      $sqlParam['persistent'] = 'false';
      $sqlParam['forcelegend'] = 'true';
    } else {
      $id = $sqlParamWriter->saveParams($sqlParam);
    }

    foreach ( $nmaInst as $nmaId ) {

      if ( $key == 'ongoing' ) {
        $sqlParam['querylist'][0]['whatcol'] = getSyncOnGoingCols($site,$statsDB,$fromDate,$toDate,$cols,$nmaId);
    $id = $sqlParamWriter->saveParams($sqlParam);
      }

      $row[] = $sqlParamWriter->getImgURL( $id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 400, $graphHeight, 'nmaid=' . $nmaId );
    }
    $graphTable->addRow($row);
  }

  return $graphTable;
}

function genRow($nmaStats,$instNames,$rowKey,$label,$agg) {
  global $debug;

  $row = array();
  $row[] = $label;
  $aggValue = 0;
  foreach ( $instNames as $instName ) {
    $row[] = $nmaStats[$instName][$rowKey];
    if ( $agg == 'sum' ) {
       $aggValue += $nmaStats[$instName][$rowKey];
    }
  }

  if ( $agg == 'sum' ) {
    $row[] = $aggValue;
  }

  foreach ($row as &$rowVal) {
    if ( is_numeric($rowVal) ) {
      $rowVal = number_format($rowVal);
    }
  }

  if ( $debug ) { echo "<pre>genRow rowKey=$rowKey\n"; print_r($row); echo "</pre>\n"; }
  return $row;
}

function mainFlow($fromDate,$toDate) {
  global $debug,$site,$webroot,$rootdir,$dir,$oss,$php_webroot;
  global $date;
  $nmaInst = array();
  $nmaStats = array();
  $statsDB = new StatsDB();

  echo "<ul>\n";
  echo " <li><a href=\"" . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&show=syncs\">Sync Stats</a></li>\n";
  echo "</ul>\n";

  $statsDB->query("
SELECT (jmx_names.name), nma_instr.nameid,
 SUM(TotalNotificationsReceived), SUM(TotalTimeTakenToProcessNotifications)/SUM(TotalNotificationsReceived)
FROM jmx_names, nma_instr, sites
WHERE
 nma_instr.nameid = jmx_names.id AND
 nma_instr.siteid = sites.id AND sites.name = '$site' AND
 nma_instr.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
GROUP BY nma_instr.nameid
ORDER BY jmx_names.name");
  while ( $row = $statsDB->getNextRow() ) {
    $nmaInst[$row[0]] = $row[1];
    $nmaStats[$row[0]] = array( 'numnotif' => $row[2], 'avgtime' => $row[3] );
  }
  if ( $debug ) { echo "<pre>\n"; print_r($nmaStats); echo "</pre>\n"; }

  $nmaInstNames = array_keys( $nmaInst );

  drawHeaderWithHelp( "Daily Statistics", 2, "Daily Statistics", "DDP_Bubble_96_OSS_NMA_Daily_Statistics" );
  $overallTable = new HTML_Table('border=1');
  $overallTable->addRow( array_merge(array(""), $nmaInstNames, array("Total")), null, 'th' );
  $overallTable->addRow( genRow($nmaStats, $nmaInstNames, 'numnotif',"Notifications Received",'sum') );
  echo $overallTable->toHTML();

  /* Connection Status Events */
  $connDiscFile = $rootdir . "/nma/conn_disc.json";

  $connDiscExists = file_exists($connDiscFile);
  if ( $debug ) { echo "<pre>connDiscFile=$connDiscFile connDiscExists=$connDiscExists</pre>\n"; }
  drawHeaderWithHelp( "Connection Status Events", 1, "conn_events", "DDP_Bubble_97_OSS_NMA_Connection_Status_Events" );
  $tableURL = "site=$site&dir=$dir&oss=$oss&file=nma/conn_disc_table.html";
  echo "<p>Click <a href=$php_webroot/conn_disc.php?$tableURL>here</a> to see the nodes corresponding to the position on vertical axis.</p>\n";

  if ( $connDiscExists ) {
    echo '<div id="nmaconndisc" style="width: 800px; height: 400px "></div>' . "\n";
    $sqlParam = array(
        'title' => "Connect/Disconnect Events",
        'type' => 'xy',
        'ylabel' => "Node",
        'useragg' => 'false',
        'persistent' => 'false',
        'seriesfile' => $connDiscFile
    );
    $jsPlot = new JsPlot();
    $jsPlot->show($sqlParam,'nmaconndisc',NULL);
  } else if ( file_exists($rootdir . "/nma/conn_disc.jpg") ) {
      echo "<img src=\"$webroot/nma/conn_disc.jpg\" alt=\"\"></a>\n";
  }

  drawHeaderWithHelp( "Instrumentation Graphs", 1, "instr", "DDP_Bubble_98_OSS_NMA_Instrumentation_Graphs" );
  $graphTable = graphTable($statsDB,$site,$fromDate,$toDate,$nmaInst);
  echo $graphTable->toHTML();

   $notifRecTable = getNotifRec($statsDB,$site,$date);
    if ( ! is_null($notifRecTable) ) {
      echo $notifRecTable[0]->getTableWithHeader("Notifications Received", 1, "DDP_Bubble_99_OSS_NMA_Notifications_Received");
    }
}
$statsDB = new StatsDB();

if ( isset($_GET['show']) ) {
  $action = $_GET['show'];
  if ( $action == 'syncs' ) {
    $tbl = new SyncStats();
    drawHeaderWithHelp("Sync Stats", 2, "syncStatsHelp", "");
    echo $tbl->getCount($tbl->getData()) > 25
         ? $tbl->getClientSortableTableStr(25, array(50, 100, 500, 1000))
         : $tbl->getClientSortableTableStr();
  }
} else {
     if (isset($_REQUEST['action'])) {
        if ( $_REQUEST['action'] === 'plotnotifrec' ) {
            plotNotifRec( $site, $date, $_REQUEST['selected'] );
        }
     } else {
       mainFlow($fromDate,$toDate);
     }
}

include "../common/finalise.php";

?>

