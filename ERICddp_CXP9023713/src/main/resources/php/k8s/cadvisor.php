<?php
$pageTitle = "K8S Pod";

require_once "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

require_once 'HTML/Table.php';

const TITLE_KEY = "title";
const TYPE_KEY = "key";
const COLUMNS_KEY = "columns";
const YLABEL = 'yLabel';

$siteId = getSiteId($statsDB, $site);

$graphParams = array(
    array(
        TITLE_KEY => "CPU",
        TYPE_KEY => SqlPlotParam::STACKED_AREA,
        YLABEL  => 'Seconds',
        COLUMNS_KEY => array(
            "cpu_user" => "User",
            "cpu_sys" => "System"
        )
    ),
    array(
        TITLE_KEY => "CPU Throttled",
        TYPE_KEY => SqlPlotParam::TIME_SERIES_COLLECTION,
        YLABEL  => 'Seconds',
        COLUMNS_KEY => array(
            "cpu_throttled" => "CPU Throttled",
        )
    ),
    array(
        TITLE_KEY => "Memory (MB)",
        TYPE_KEY => SqlPlotParam::TIME_SERIES_COLLECTION,
        YLABEL  => '',
        COLUMNS_KEY => array(
            "mem_mb" => "Memory (MB)"
        )
    ),
    array(
        TITLE_KEY => "Memory Cached (MB)",
        TYPE_KEY => SqlPlotParam::TIME_SERIES_COLLECTION,
        YLABEL  => '',
        COLUMNS_KEY => array(
            "mem_cache" => "MB",
        )
    ),
    array(
        TITLE_KEY => "Network",
        TYPE_KEY => SqlPlotParam::TIME_SERIES_COLLECTION,
        YLABEL  => '',
        COLUMNS_KEY => array(
            "net_rx_mb" => "RX (MB)",
            "net_tx_mb" => "TX (MB)"
        )
    )
);

$serverIds = array();

$plotType = requestValue('plot');
if ( ! is_null($plotType) ) {
    $selected = requestValue('selected');
    if ( $plotType === 'appname') {
        $statsDB->query("
SELECT DISTINCT servers.hostname, servers.id
FROM k8s_pod_cadvisor
JOIN servers ON k8s_pod_cadvisor.serverid = servers.id
JOIN k8s_pod_app_names ON k8s_pod_cadvisor.appid = k8s_pod_app_names.id
WHERE
k8s_pod_cadvisor.siteid = $siteId AND
k8s_pod_cadvisor.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 k8s_pod_app_names.name = '$selected'");
        while ($row = $statsDB->getNextRow() ) {
            $serverIds[$row[0]] = $row[1];
        }
    } elseif ( $plotType === 'serverid' ) {
        $statsDB->query("SELECT hostname,id FROM servers WHERE id IN ($selected)");
        while ($row = $statsDB->getNextRow() ) {
            $serverIds[$row[0]] = $row[1];
        }
    }
} else {
    $serverid = requestValue("serverid");
    $row = $statsDB->queryRow("SELECT hostname FROM servers WHERE id = $serverid");
    $serverIds[$row[0]] = $serverid;
}

if (count($serverIds) == 1 ) {
    $width = 800;
    $height = 400;
} else {
    $width = 400;
    $height = 240;
}

$sqlParamWriter = new SqlPlotParam();
$dbTables = array("k8s_pod_cadvisor");
$where = "siteid = %d AND serverid = %d";

$graphTable = new HTML_Table("border=0");
$headerRow = array();
foreach ( $serverIds as $hostname => $serverid ) {
    $headerRow[] = $hostname;
}
$graphTable->addRow($headerRow);

foreach ( $graphParams as $graphParam ) {
    $notNullFilter = array();
    foreach ( array_keys($graphParam[COLUMNS_KEY]) as $column) {
        $notNullFilter[] = "$column IS NOT NULL";
    }
    $sqlParam = SqlPlotParamBuilder::init()
              ->title($graphParam[TITLE_KEY])
              ->longTitle($graphParam[TITLE_KEY] . " for %s")
              ->titleArgs(array('pod'))
              ->type($graphParam[TYPE_KEY])
              ->yLabel($graphParam[YLABEL])
              ->makePersistent()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $graphParam[COLUMNS_KEY],
                  $dbTables,
                  $where . " AND " . implode(" AND ", $notNullFilter),
                  array('siteid','serverid')
              )
              ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);

    $row = array();
    foreach ( $serverIds as $hostname => $serverid ) {
        $row[] = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            $width,
            $height,
            "siteid=$siteId&serverid=$serverid&pod=$hostname"
        );
    }

    $graphTable->addRow($row);
}

echo $graphTable->toHTML();

require_once PHP_ROOT . "/common/finalise.php";
