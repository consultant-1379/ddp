<?php
$pageTitle = "Virtual Connect";

$YUI_DATATABLE = true;

if (isset($_GET["nicstats"])) {
    $UI = false;
}

include "./common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/NICPlot.php";

require_once 'HTML/Table.php';

$statsDB = new StatsDB();

function getGraphURL($title,$column,$srvId) {
  global $date;

  $where = "
nic_stat.serverid = %d AND
nic_stat.serverid = servers.id AND
nic_stat.nicid = network_interfaces.id
";
  $sqlParamWriter = new SqlPlotParam();
  $sqlPlotParam =
    array(
          'title' => $title,
          'type' => 'sb',
          'sb.barwidth' => 900,
          'ylabel' => "MBit/s",
          'useragg' => 'true',
          'persistent' => 'false',
          'querylist'
          => array(
                   array(
                         'timecol' => 'time',
                         'multiseries'=> 'network_interfaces.name',
                         'whatcol'=>
                         array(
                               "(($column * 8)/1000000)" => $title,
                               ),
                         'tables' => "nic_stat, network_interfaces, servers",
                         'where' => $where,
                         'qargs' => array('srvid')
                         )
                   )
          );
  $id = $sqlParamWriter->saveParams($sqlPlotParam);
  $url =  $sqlParamWriter->getImgURL( $id,
                                      $date . " 00:00:00", $date . " 23:59:59",
                                      true, 480, 250,
                                      "srvid=$srvId" );
  return $url;
}

function mainFlow($statsDB) {
  global $date,$site,$webargs;

  $vcIds = array();
  $statsDB->query("SELECT servers.hostname, servers.id FROM virtualconnect, sites, servers WHERE
virtualconnect.siteid = sites.id AND sites.name = '$site' AND
virtualconnect.serverid = servers.id AND
virtualconnect.date = '$date'");
  while($row = $statsDB->getNextRow()) {
    $vcIds[$row[0]] = $row[1];
  }

  $vcIdsStr = implode(",",array_values($vcIds));
  $ifWhere = "
nic_stat.serverid IN ($vcIdsStr) AND
nic_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
nic_stat.serverid = servers.id AND
nic_stat.nicid = network_interfaces.id
";
  $ifTable = new SqlTable("nic_table",
                          array(
                                array( 'key' => 'server', 'db' => 'servers.hostname', 'label' => 'Instance' ),
                                array( 'key' => 'nicid', 'db' => 'network_interfaces.id', 'visible' => false, 'label' => 'None' ),
                                array( 'key' => 'nicname', 'db' => 'network_interfaces.name', 'label' => 'NIC' ),
                                array( 'key' => 'rxavg', 'db' => 'ROUND((AVG(nic_stat.ibytes_per_sec)*8/1000000), 2)', 'label' => 'Average Recevied (Mbit/s)' ),
                                array( 'key' => 'txavg', 'db' => 'ROUND((AVG(nic_stat.obytes_per_sec)*8/1000000), 2)', 'label' => 'Average Transmitted (Mbit/s)' ),
                                array( 'key' => 'rxmax', 'db' => 'ROUND((MAX(nic_stat.ibytes_per_sec)*8/1000000), 2)', 'label' => 'Max Recevied (Mbit/s)' ),
                                array( 'key' => 'txmax', 'db' => 'ROUND((MAX(nic_stat.obytes_per_sec)*8/1000000), 2)', 'label' => 'Max Transmitted (Mbit/s)' ),
                                ),
                          array( 'nic_stat', 'servers', 'network_interfaces' ),
                          $ifWhere . " GROUP BY nicid",
                          TRUE,
                          array( 'order' => array( 'by' => 'server', 'dir' => 'ASC'),
                                 'ctxMenu' => array('key' => 'nicstats',
                                                    'multi' => true,
                                                    'menu' => array( 'plot' => 'Plot' ),
                                                    'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                                    'col' => 'nicid'
                                                    )
                                 )
                          );
  echo "<H2>Uplink Traffic</H2>\n";
  echo $ifTable->getTable();

  echo "<br>\n";

  $graphTable = new HTML_Table('border=1');
  foreach ($vcIds as $name => $id) {
    $graphTable->addRow( array(
                               $name,
                               getGraphURL('Recevied','ibytes_per_sec',$id),
                               getGraphURL('Transmitted','obytes_per_sec',$id)
                               )
                         );
  }
  echo $graphTable;
}

if (isset($_GET["nicstats"])) {
    $nicPlot = new NICPlot($statsDB,$date,$_GET["selected"]);
    $nicPlot->openQPlot();
} else {
  mainFlow($statsDB);
  include PHP_ROOT . "/common/finalise.php";
}

?>
