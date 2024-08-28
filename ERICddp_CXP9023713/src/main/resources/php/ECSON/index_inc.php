<?php

require_once PHP_ROOT . "/common/indexKafkaFunctions.php";

function ecsonGenerateContent() {
    global $date, $site, $debug, $webargs, $statsDB, $rootdir, $grafanaURL;

    $links = array();

    // File search
    $links[] = makeLink('/common/findfile.php', 'File Search');

    /* Granfana */
    if ( isset($grafanaURL) && $statsDB->hasData('k8s_node', 'date', true) ) {
        $links[] = makeLinkForURL($grafanaURL, "Grafana");
    }

    if ($statsDB->hasData("k8s_pod", 'date')) {
        $links[] = makeLink('/k8s/appinst.php', 'K8S Application Pods');
    }

    if ( file_exists($rootdir . "/k8s/k8s_events.json") ) {
        $links[] = makeLink('/k8s/events.php', 'K8S Events');
    }

    if ($statsDB->hasData("generic_jmx_stats")) {
        $links[] = makeLink('/common/jvms.php', 'JVM Stats');
    }

    if ( $statsDB->hasData('enm_logs', 'date', true) ) {
        $links[] = makeLink('/TOR/system/elasticsearch.php', 'Elasticsearch Logs', array( 'logdir' => 'logs'));
    }

    if ( $statsDB->hasData("ecson_cm_topology_model") || $statsDB->hasData("ecson_cm_loader_er") ||
         $statsDB->hasData("ecson_cm_loader_mos") || $statsDB->hasData("ecson_cm_data_loader") ) {

        $links[] = makeLink('/ECSON/cm_services.php', 'CM Services');
    }

    if ( $statsDB->hasData("ecson_cm_change_mediation") ) {
        $links[] = makeLink('/ECSON/cm_changeMediation.php', 'CM Change Mediation');
    }

    // Postgres stats (ECSON can have multiple Postgres servers)
    $statsDB->query("
    SELECT DISTINCT servers.id, servers.hostname
    FROM enm_postgres_stats_db, sites, servers
    WHERE
    enm_postgres_stats_db.siteid = sites.id AND sites.name = '$site' AND
    enm_postgres_stats_db.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_postgres_stats_db.serverid = servers.id
    ");
    while ( $row = $statsDB->getNextRow() ) {
        $links[] = makeLink('/TOR/databases/postgres.php', 'Postgres - ' . $row[1], array('serverid' => $row[0]));
    }

    $serverIdToApp = array();
    $appToTopics = kafkaTopics($serverIdToApp);
    foreach ($appToTopics as $app => $topics) {
        $links[] = makeLink('/common/kafka.php', 'Kakfa - ' . $app, array('topics' => implode(",", $topics)));
    }

    $sparkServerIdsPerApp = array();
    $statsDB->query("
SELECT DISTINCT serverid
FROM spark_executor
JOIN sites ON spark_executor.siteid = sites.id
WHERE
 sites.name = '$site' AND
 spark_executor.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    while ($row = $statsDB->getNextRow()) {
        $serverId = $row[0];
        if ( array_key_exists($serverId, $serverIdToApp)) {
            $app = $serverIdToApp[$serverId];
            if ( ! array_key_exists($app, $sparkServerIdsPerApp)) {
                $sparkServerIdsPerApp[$app] = array();
            }
            $sparkServerIdsPerApp[$app][] = $serverId;
        }
    }

    foreach ($sparkServerIdsPerApp as $app => $serverIds) {
        $links[] = makeLink('/common/spark_executor.php', 'Spark - ' . $app, array('serverids' => implode(",", $serverIds)));
    }

    if ( $statsDB->hasData("ecson_frequency_manager") ) {
        $links[] = makeLink('/ECSON/frequency_level_manager.php', 'Frequency Layer Manager');
    }

    if ( $statsDB->hasData("ecson_kpi_service") ) {
        $links[] = makeLink('/ECSON/kpi_service.php', 'KPI Calculator Service');
    }

    if ( $statsDB->hasData("kafka_consumer") || $statsDB->hasData("kafka_producer") ) {
        $links[] = makeLink('/ECSON/kafka.php', 'Kafka Consumer/Producer');
    }

    if ( $statsDB->hasData("ecson_ret_custom_service") ) {
        $links[] = makeLink('/ECSON/ret_service.php', 'RET Services');
    }

    if ( $statsDB->hasData("ecson_pm_events_cell_pipeline") || $statsDB->hasData("ecson_pm_events_jdbc_updates") ||
         $statsDB->hasData("ecson_pm_events_processor") || $statsDB->hasData("ecson_event_data_collector") ) {
        $links[] = makeLink('/ECSON/pm_service.php', 'PM Services');
    }

    return makeHTMLList($links);
}
