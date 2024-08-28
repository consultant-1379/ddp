<?php
$pageTitle = "ENIQ Statistics";
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/StatsDB.php";
require_once "../SqlPlotParam.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function prepareGraph($title, $ylabel, $type, $whatcol, $tables, $where) {
    global $date, $host;
    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
                  ->title($title)
                  ->type($type)
                  ->yLabel($ylabel)
                  ->makePersistent()
                  ->forceLegend()
                  ->addQuery(
                      'time',
                      $whatcol,
                      array($tables),
                      $where,
                      array('host', 'site')
                      )
                  ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "host=$host");
}

    if ( isset($_GET['start']) ) {
        $fromDate = $_GET['start'];
        $toDate = $_GET['end'];
    } else {
        $fromDate = $date;
        $toDate = $date;
    }
    if ( isset($_GET['eniqShipmentType']) ) {
        $eniqShipmentType = $_GET['eniqShipmentType'];
        $pattern='/^ENIQ_(\w+)_Shipment.*/';
        preg_match($pattern, $eniqShipmentType, $matches);
        $eniqShipmentType = $matches[1];
    }
    $statsDB = new StatsDB();

    if ( isset($_GET['server'])) {
        $host = $_GET['server'];
    } else {
        $row = $statsDB->queryRow("SELECT servers.hostname FROM iq_monitor_summary, servers, sites
            WHERE iq_monitor_summary.serverid = servers.id AND servers.siteid = sites.id
            AND sites.name = '" . $site . "' AND servers.type
            IN ('ENIQ','ENIQ_IQW','ENIQ_COORDINATOR') limit 1");
        $host = $row[0];
    }
    $serverId = getServerId($statsDB, $site, $host);
    if (!is_int($serverId)) {
        echo "<b>Could not get server id for " . $hostname . ": " . $serverId . "</b>\n";
        include_once "../common/finalise.php";
        exit(0);
    }

main($fromDate, $toDate, $eniqShipmentType, $statsDB, $host, $serverId);

function main($fromDate, $toDate, $eniqShipmentType, $statsDB, $host, $serverId) {
    global $site, $serverId;

    $table = new HTML_Table("border=1");

    # Get server type
    $row = $statsDB->queryRow("
        SELECT servers.type as host FROM servers,sites WHERE
        servers.hostname = '" . $host . "' AND
        servers.siteid = sites.id AND
        sites.name = '" . $site . "'");
    $serverType = $row[0];
    $table->addRow( array('Server Type', $serverType) );

    $row = $statsDB->queryRow("
    SELECT ROUND(AVG(main_hr),2), ROUND(AVG(temp_hr),2), ROUND(AVG(main_inuse),2), ROUND(AVG(temp_inuse),2)
     FROM iq_monitor_summary
    WHERE
      iq_monitor_summary.serverid = $serverId AND
      iq_monitor_summary.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
        $table->addRow( array('Main Cache Hit Rate(%)', $row[0]) );
        $table->addRow( array('Temp Cache Hit Rate(%)', $row[1]) );
        $table->addRow( array('Main Cache In Use(%)', $row[2]) );
        $table->addRow( array('Temp Cache In Use(%)', $row[3]) );

    $row = $statsDB->queryRow("
    SELECT COUNT(*)
     FROM iq_monitor_summary
     WHERE
      iq_monitor_summary.serverid = $serverId AND
      iq_monitor_summary.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59' AND
      iq_monitor_summary.main_gdirty > 0");
    $table->addRow( array('Number of num zero GDirty', $row[0]) );

    echo "<H1>SAP IQ: " . $host . "</H1>\n";
    echo $table->toHTML();
    echo addLineBreak();

    $iqModelledGraphSet = new ModelledGraphSet('ENIQ/iq_cache_users');
    $iqGraphs = $iqModelledGraphSet->getGroup("iqcache");
    foreach ( $iqGraphs['graphs'] as $modelledGraph ) {
        $graphs[] = $modelledGraph->getImage(array('serverid' => $serverId ), null, null, 640, 240);
    }
    plotgraphs( $graphs );

    if ($eniqShipmentType == "Statistics"){
        $whatCol =  array(
                    'totalMemory' => 'Total Memory',
                    'flexibleUsed' => 'Flexible Memory',
                    'inflexibleUsed' => 'Inflexible Memory'
                );
        $where = "eniq_sap_iq_large_memory_details.siteId = sites.id AND servers.hostname = '%s'
              AND eniq_sap_iq_large_memory_details.serverId = servers.id AND sites.name = '%s'";
        $tables = "eniq_sap_iq_large_memory_details, sites, servers";
        drawHeaderWithHelp("Large Memory", 2, "largeMemoryHelp");
        echo prepareGraph("Large Memory", "Mb", "tsc", $whatCol, $tables, $where),"<br><br><br>";
    }
}

include "sap_iq.php";
include "../common/finalise.php";

?>
