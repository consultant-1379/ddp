<?php
$pageTitle = "SAP IQ Metrics";
$YUI_DATATABLE = true;
include_once "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$fromDate = $date;
$toDate = $date;
$sqlParamWriter = new SqlPlotParam();
$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
}

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}

if ( isset($_GET['server']) ) {
    $server = $_GET['server'];
    $serverId = getServerId($statsDB, $site, $server);
}

$table = new HTML_Table("border=1");
$row = $statsDB->queryRow("
    SELECT
     ROUND(AVG(sap_iq_metrics.cache_used), 2) as cache_used,
     ROUND(AVG(sap_iq_metrics.cache_allocated), 2) as cache_allocated
    FROM
     sap_iq_metrics, sites
    WHERE
     sites.name = '$site' AND
     sap_iq_metrics.siteid = sites.id AND
     sap_iq_metrics.serverid = $serverId AND
     sap_iq_metrics.time between '$date 00:00:00' and '$date 23:59:59'
");
$table->addRow( array('Cache Allocated (KB)', $row[1]) );
$table->addRow( array('Cache Used (%)', $row[0]) );

echo"\n";
$sapIqMetrics = array(
    'dbUsers' => array(
        'title'      => 'User Info',
        'type'       => 'sb',
        'yLabel'     => 'Users',
        'preset'     => 'Count:Per Minute',
        'graphType'  => 'tsc',
        'querylist' => array(
            'timecol'     => "time",
            'multiseries' => "sap_db_users.db_username",
            'whatcol'     => array('number_of_connections' => 'Number of users'),
            'tables'      => "sap_db_users, sap_db_users_connections, sites, servers",
            'where'       => "sap_db_users_connections.db_user_id = sap_db_users.db_user_id AND
                              sap_db_users_connections.serverid = servers.id AND
                              sap_db_users_connections.siteid = sites.id AND
                              servers.hostname = '$server' AND
                              sites.name = '%s'",
            'qargs'       => array( 'site' )
        )
    ),
    'totalDbUsers' => array(
        'title'      => 'Total User Info',
        'type'       => 'sb',
        'yLabel'     => 'Total Users',
        'preset'     => 'SUM:Per Minute',
        'graphType'  => 'tsc',
        'querylist' => array(
            'timecol' => "time",
            'whatcol' => array( 'number_of_connections' => 'Number of users'),
            'tables'  => "sap_db_users_connections, sap_db_users, sites,servers",
            'where'   => "sap_db_users_connections.db_user_id = sap_db_users.db_user_id AND
                          sap_db_users_connections.siteid = sites.id AND
                          sap_db_users_connections.serverid = servers.id AND
                          servers.hostname = '$server' AND
                          sites.name = '%s'",
            'qargs'   => array( 'site' )
        )
    ),
    'CacheStatistics' => array(
        'title'      => 'Cache Statistics',
        'type'       => 'sb',
        'yLabel'     => 'KB',
        'preset'     => 'Count:Per Minute',
        'graphType'  => 'tsc',
        'querylist' => array(
            'timecol' => "time",
            'whatcol' => array( 'cache_allocated' => 'cache allocated', 'cache_used' => 'cache used' ),
            'tables'  => "sap_iq_metrics, sites",
            'where'   => "sap_iq_metrics.serverid = $serverId AND
                          sap_iq_metrics.siteid = sites.id AND
                          sites.name = '%s'",
            'qargs'   => array( 'site' )
       )
    )
);

foreach ( $sapIqMetrics as $iqMetrics => $metricInformation ) {
    drawGraph( $iqMetrics, $metricInformation );
}

function drawGraph( $iqMetrics, $metricInformation ) {
    global $sqlParamWriter;
    global $site;
    global $date;
    global $table;
    $sqlParam = array(
        'title'       => $metricInformation['title'],
        'ylabel'      => $metricInformation['yLabel'],
        'type'        => $metricInformation['graphType'],
        'useragg'     => 'true',
        'presetagg'   => $metricInformation['preset'],
        'persistent'  => 'false',
        'sb.barwidth' => 3600,
        'useragg'     => 'false',
        'querylist'   => array($metricInformation['querylist'])
    );
    if ($iqMetrics == "CacheStatistics") {
        $sapIqHelp = <<<EOT
        SAP IQ catalogue cache instrumentation is to analyze the catalogue cache metrics for ENIQ.
EOT;
        drawHeaderWithHelp("Catalogue Cache Statistics", 1, "sapIqHelp", $sapIqHelp);
        echo $table->toHTML();
        echo "<br>";
    }
    $id = $sqlParamWriter->saveParams($sqlParam);
    $image_tag = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240);
    echo "$image_tag<br><br><br>";
}
?>