<?php
$pageTitle = "DomainProxy Mediation";

const ACT = 'action';

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/routeFunctions.php";
require_once PHP_ROOT . "/common/links.php";
require_once PHP_ROOT . "/common/queueFunctions.php";

const TITLE = 'title';
const YLABEL = 'ylabel';
const COUNT = 'Count';

function getDailyNotifTotals($statsDB, $srvIds) {
    global $site, $date;

    drawHeaderWithHelp("Daily Notification Totals", 2, "Daily_Notification_Totals");
    $servIdStr = implode(",", $srvIds);
    $names = array();

    $hasSummary = $statsDB->hasData("sum_enm_route_instr", "date", true);
    if ( $hasSummary ) {
        $query = <<<EOT
SELECT
 IFNULL( servers.hostname, 'Total') AS Inst,
 SUM(ExchangesCompleted) AS Total,
 enm_route_names.name AS Name
FROM sum_enm_route_instr
JOIN enm_route_names ON sum_enm_route_instr.routeid = enm_route_names.id
JOIN sites ON sum_enm_route_instr.siteid = sites.id
JOIN servers ON sum_enm_route_instr.serverid = servers.id
WHERE
 sites.name = '$site' AND
 sum_enm_route_instr.date = '$date' AND
 sum_enm_route_instr.serverid IN ( $servIdStr ) AND
 sum_enm_route_instr.ExchangesCompleted IS NOT NULL
GROUP BY enm_route_names.name, servers.hostname WITH ROLLUP
EOT;
    } else {
        $query = <<<EOT
SELECT
    IFNULL( servers.hostname, 'Total') AS Inst,
    SUM(ExchangesCompleted) AS Total,
    enm_route_names.name AS Name
FROM
    enm_route_instr, enm_route_names, sites, servers
WHERE
    enm_route_instr.siteid = sites.id
    AND sites.name = '$site'
    AND enm_route_instr.serverid = servers.id
    AND enm_route_instr.serverid IN ( $servIdStr )
    AND enm_route_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    AND enm_route_instr.routeid = enm_route_names.id
    AND enm_route_instr.ExchangesCompleted IS NOT NULL
GROUP BY enm_route_names.name, servers.hostname WITH ROLLUP
EOT;
    }

    $statsDB->query($query);

    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( $row['Name'] != '' ) {
            preg_match('/([a-zA-Z]*$)/', $row['Name'], $matches);
            $row['Name'] =  ucfirst( $matches[0] );
            $names[] = ucfirst( $matches[0] );
            $results[] = $row;
        }
    }
    $names = array_unique($names);
    sort($names);

    foreach ($names as $name) {
        foreach ($results as $result) {
            if ($result['Name'] == $name) {
                $data[] = $result;
            }
        }
        $table = new DDPTable(
            "Totals_$name",
            array(
                array('key' => 'Inst', DDPTable::LABEL => 'Instance'),
                array('key' => 'Name', DDPTable::LABEL => 'Route Name'),
                array('key' => 'Total', DDPTable::LABEL => 'ExchangesTotal')
            ),
            array('data' => $data)
        );

        echo $table->getTable();
        echo "<br>";
        $data = array();
    }
}

function showSasCRA() {
    global $date, $site;
    $instrGraphParams = array (
    array(
        'totalNumberOfHbToSAS' => array (
            TITLE => 'Heartbeat Requests to SAS',
            YLABEL => COUNT,
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'totalNumberOfHbToSAS' => 'Heartbeat Requests to SAS'
            )
        ),
        'numberOfFailedAttempsWithSas ' => array (
            TITLE => 'Failed Attempts towards SAS',
            YLABEL => COUNT,
            'type' => SqlPlotParam::STACKED_BAR,
            'cols' => array(
            'numberOfFailedAttempsWithSas ' => 'Failed Attempts towards SAS'
            )
        )
        ),
        array ( 'totalHbResponseTimeFromSas' => array (
            TITLE => 'Heartbeat Response Time',
            YLABEL => 'ms',
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'totalHbResponseTimeFromSas' => 'Heartbeat Response Time'
            )
        ),
        'maxHbResponseTimePerMinute' => array (
            TITLE => 'Max Heartbeat Response Time',
            YLABEL => 'ms',
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'maxHbResponseTimePerMinute' => 'Max Heartbeat Response Time'
            )
        )
        )
    );
    drawHeaderWithHelp("SAS Client Resource Adapter", 1, "SAS_Client_Resource_Adapter");
    showInstrGraph($instrGraphParams);
}

function showSasHandling() {
    global $date, $site;
    $instrGraphParams = array (
    array(
        'totalTransmitExpiryTimeSetOnNode' => array (
            TITLE => 'Transmit Expiry Time Set on Node',
            YLABEL => 'seconds',
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'totalTransmitExpiryTimeSetOnNode' => 'Transmit Expiry Time Set on Node'
            )
        ),
        'totalNumberOfTransmitExpiryTimeSetOnNode' => array (
            TITLE => 'Number of Transmit Expiry Time set on Node',
            YLABEL => COUNT,
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'totalNumberOfTransmitExpiryTimeSetOnNode' => 'Number of Transmit Expiry Time set on Node'
            )
            )
        ),
       array( 'totalNumberOfHbResponsesFromSAS' => array (
            TITLE => 'Number of Heartbeat Responses from SAS',
            YLABEL => COUNT,
            'type' => SqlPlotParam::STACKED_BAR,
            'cols' => array(
            'totalNumberOfHbResponsesFromSAS' => 'Number of Heartbeat Responses from SAS'
            )
        ),
        'totalTransmitExpiryTimePerHbResponseFromSas' => array (
            TITLE => 'Total Transmit Expiry Time per Heartbeat Response from SAS',
            YLABEL => 'ms',
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'totalTransmitExpiryTimePerHbResponseFromSas' => 'Total Transmit Expiry Time per HeartbeatResponse fromSAS'
            )
          )
        ),
        array ('minTxExpiryTimePerMinute' => array (
            TITLE => 'Minimum Time to Live of Transmit Expiry Time',
            YLABEL => 'ms',
            'type' => SqlPlotParam::TIME_SERIES_COLLECTION,
            'cols' => array(
            'minTxExpiryTimePerMinute' => 'Minimum Time to Live of Transmit Expiry Time'
            )
        )
        )
    );
    drawHeaderWithHelp("SAS Handling", 1, "SAS_Handling");
    showInstrGraph($instrGraphParams);
}

function showInstrGraph($instrGraphParams) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $dbTables = array( "enm_dpmediation_sas_instr", StatsDB::SITES, StatsDB::SERVERS );
    $where = "enm_dpmediation_sas_instr.siteid = sites.id AND
    sites.name = '%s' AND
    enm_dpmediation_sas_instr.serverid = servers.id";

    $graphTable = new HTML_Table("border=0");
    foreach ( $instrGraphParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
        $sqlParam = SqlPlotParamBuilder::init()
                    ->title($instrGraphParamName[TITLE])
                    ->type($instrGraphParamName['type'])
                    ->barwidth(60)
                    ->yLabel($instrGraphParamName[YLABEL])
                    ->forcelegend("true")
                    ->makePersistent()
                    ->addQuery(
                        SqlPlotParam::DEFAULT_TIME_COL,
                        $instrGraphParamName['cols'],
                        $dbTables,
                        $where,
                        array('site'),
                        "servers.hostname"
                    )
                    ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 600, 400);
        }
        $graphTable->addRow($row);
    }
  echo $graphTable->toHTML();
}

function mainFlow() {
    global $date, $site, $statsDB;

    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_dpmediation_sas_instr, sites
WHERE
 enm_dpmediation_sas_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_dpmediation_sas_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ( $row[0] > 0 ) {
        echo "<ul>\n";
        echo "<li>SAS Instrumentation\n";
        $sasCralink = makeAnchorLink("SAS_Client_Resource_Adapter_anchor", "SAS Client Resource Adapter" );
        $sashandlerlink = makeAnchorLink("SAS_Handling_anchor", "SAS Handling" );
        echo makeHTMLList(array($sasCralink,$sashandlerlink));
        echo "</ul>\n";
    }

    $srv = enmGetServiceInstances( $statsDB, $site, $date, "dpmediation");
    $serverIdsArr = array_values($srv);

    getDailyNotifTotals( $statsDB, $serverIdsArr );

    $queueNames = array('DPMediationServiceConsumer', 'DomainProxyServiceConsumer');
    plotQueues( $queueNames );

    getRouteInstrTable( $serverIdsArr );

    if ( $row[0] > 0 ) {
        showSasCRA();
        showSasHandling();
    }

}

if ( issetUrlParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

