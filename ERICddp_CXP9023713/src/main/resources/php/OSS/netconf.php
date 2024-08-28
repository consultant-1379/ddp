<?php
$pageTitle = "Netconf";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

function graphTable($site,$statsDB,$fromDate,$toDate,$netConfInst) {
  $params = array( 'ActiveSessions', 'SimultaneousSessions',
       'RequestCount', 'ResponseCount', 'ActionCount', 'NotificationCount',
       'BytesRx', 'BytesTx',
       'ReqCRUDProcessTime',  'ResCRUDProcessTime',
       'ReqTRPCProcessTime', 'ResTRPCProcessTime',
       'ReqRPCConstructionTime', 'ResRPCExtractionTime',
       );

  $sqlParamWriter = new SqlPlotParam();
  $graphTable = new HTML_Table("border=1");
  $graphTable->addRow( array_keys( $netConfInst ), null, 'th' );
  foreach ( $params as $param ) {
    $row = array();
    $sqlParam =
      array( 'title'      => $param,
       'ylabel'     => "",
       'useragg'    => 'true',
       'persistent' => 'false',
       'querylist' =>
       array(
       array(
       'timecol' => 'time',
       'whatcol'    => array ( $param => $param ),
       'tables'  => "netconf_instr, sites",
       'where'   => "netconf_instr.siteid = sites.id AND sites.name = '%s' AND netconf_instr.nameid = %d AND $param IS NOT NULL",
       'qargs'   => array( 'site', 'netconfid' )
       )
        )
       );
    $id = $sqlParamWriter->saveParams($sqlParam);
    if(!empty($netConfInst)) {
        foreach ((array)$netConfInst as $netconfName => $netconfId ) {
            $row[] = $sqlParamWriter->getImgURL( $id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 400, 200, 'netconfid=' . $netconfId );
        }
        $graphTable->addRow($row);
    }
  }

  return $graphTable;
}


if ( isset($_GET['start']) ) {
  $fromDate = $_GET['start'];
  $toDate = $_GET['end'];
} else {
  $fromDate = $date;
  $toDate = $date;
}

$statsDB = new StatsDB();
$netConfInst = array();
$statsDB->query("
SELECT DISTINCT(jmx_names.name), netconf_instr.nameid
FROM jmx_names, netconf_instr, sites
WHERE
 netconf_instr.nameid = jmx_names.id AND
 netconf_instr.siteid = sites.id AND sites.name = '$site' AND
 netconf_instr.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
GROUP BY netconf_instr.nameid
ORDER BY jmx_names.name");
while ( $row = $statsDB->getNextRow() ) {
  $netconfInst[$row[0]] = $row[1];
}

$graphTable = graphTable($site,$statsDB,$fromDate,$toDate,$netconfInst);
echo $graphTable->toHTML();

include PHP_ROOT . "/common/finalise.php";
?>
