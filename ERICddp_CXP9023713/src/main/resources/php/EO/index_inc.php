<?php
require_once PHP_ROOT . "/common/indexFunctions.php";
require_once PHP_ROOT . "/common/indexKafkaFunctions.php";
require_once PHP_ROOT . "/common/indexIncFunctions.php";

const PERF_STATS = '/EO/perf_stats.php';

$links = array();

function f5Links() {
    global $statsDB;
    $f5SubLinks = array();
    $f5Tables = array(
        'f5_pool_stats', 'f5_node_stats',
        'f5_virtual_stats', 'f5_cpu_stats',
        'eo_f5_memory_stats', 'eo_f5_nic_stats',
        'eo_f5_http_stats', 'eo_f5_tcp_stats', 'eo_f5_ld_stats'
    );
    foreach ($f5Tables as $table) {
        if ($statsDB->hasData($table)) {
            $f5SubLinks[] = makeLink('/EO/f5.php', 'Stats');
            break;
        }
    }
    $f5States = array(
        'eo_f5_pool_states',
        'eo_f5_node_states',
        'eo_f5_virtual_states'
    );
    foreach ($f5States as $table) {
        if ($statsDB->hasData($table)) {
            $f5SubLinks[] = makeLink('/EO/f5_states.php', 'States');
            break;
        }
    }
    return $f5SubLinks;
}

function perfServiceLinks() {
    global $statsDB, $date, $site;
    $serviceSubLinks = array();

    $sql = "
SELECT
    eo_perf_service_names.name
FROM
    eo_perf_service_stats
JOIN sites
    ON eo_perf_service_stats.siteid = sites.id
JOIN eo_perf_service_names
    ON eo_perf_service_stats.serviceid = eo_perf_service_names.id
WHERE
    sites.name = '$site'
    AND eo_perf_service_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $restApiSql = "$sql AND eo_perf_service_names.name NOT LIKE 'EOC%' AND eo_perf_service_names.name NOT LIKE 'NSO%'";
    $statsDB->query($restApiSql);
    if ($statsDB->getNumRows() > 0 ) {
       $serviceSubLinks[] = makeLink(PERF_STATS, 'REST API', array('servicePerf' => '1'));
    }

    $orderCareSql = "$sql AND eo_perf_service_names.name LIKE 'EOC%'";
    $statsDB->query($orderCareSql);
    if ($statsDB->getNumRows() > 0 ) {
       $serviceSubLinks[] = makeLink(PERF_STATS, 'OrderCare', array('orderCare' => '1'));
    }

    $nsoSql = "$sql AND eo_perf_service_names.name LIKE 'NSO%'";
    $statsDB->query($nsoSql);
    if ($statsDB->getNumRows() > 0 ) {
       $serviceSubLinks[] = makeLink(PERF_STATS, 'NetworkServiceOrchestration', array('nsoPerf' => '1'));
    }
    return $serviceSubLinks ;
}

function displayTab($table, $page, $title) {
    global $statsDB, $links;

    if ( $statsDB->hasData($table) ) {
        $links[] = makeLink( $page, $title );
    }
}

function addPostgres(&$links) {
    global $date, $site, $statsDB;

    if ( $statsDB->hasData('enm_postgres_stats_db') ) {
        /* Now figure out if this is CloudNative, i.e. if we have multiple Postgres instances */
        $statsDB->query("
SELECT DISTINCT enm_postgres_stats_db.serverid, servers.hostname
FROM enm_postgres_stats_db
JOIN sites ON enm_postgres_stats_db.siteid = sites.id
JOIN servers ON enm_postgres_stats_db.serverid = servers.id
WHERE
 sites.name = '$site' AND
 enm_postgres_stats_db.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ( $statsDB->getNumRows() > 1 ) {
            while ( $row = $statsDB->getNextRow() ) {
                $links[] = makeLink(
                    '/TOR/databases/postgres.php',
                    'Postgres: ' . $row[1],
                    array('serverid' => $row[0])
                );
            }
        } else {
            $links[] = makeLink('/TOR/databases/postgres.php', 'Postgres' );
        }
    }
}

function getKafkaLink(&$links) {
    $serverIdToApp = array();
    $appToTopics = kafkaTopics($serverIdToApp);
    foreach ( $appToTopics as $app => $topics ) {
        if (!empty($topics)) {
            $links[] = makeLink('/common/kafka.php', 'Kakfa - ' . $app, array('topics' => implode(",", $topics)));
        }
    }
}

function eoGenerateContent() {
    global $date, $site, $debug, $statsDB, $AdminDB, $links, $rootdir;

    $content = array();

    $statsDB->query("
SELECT
 eo_ver_names.name FROM eo_ver, eo_ver_names, sites
WHERE
 eo_ver.siteid = sites.id AND sites.name = '$site' AND
 eo_ver.verid = eo_ver_names.id AND
 eo_ver.date = '$date'
");
    if ($statsDB->getNumRows() == 1) {
        $row = $statsDB->getNextRow();
        $content[] = "<p><b>EO Version</b>: $row[0]</b></p>";
    }

    // File search
    $links[] = makeLink('/common/findfile.php', 'File Search');

    // Health status
    if ($statsDB->hasData("$AdminDB.healthcheck_results", 'date')) {
        $links[] = makeLink('/common/hc.php', 'Health Status');
    }

    /* Granfana */
    $grafanaLink = getGrafanaLink();
    if ( ! is_null($grafanaLink) ) {
        $links[] = makeLinkForURL($grafanaLink, "Grafana");
    }

    // Workload Profiles
    if ($statsDB->hasData('enm_profilelog', 'date')) {
        $links[] = makeLink('/TOR/rv/workload.php', 'Workload');
    }

    if ( $statsDB->hasData('eo_assets', 'date', true) ) {
        $links[] = makeLink('/EO/assets.php', 'Assets');
    }

    if ($statsDB->hasData("k8s_pod", 'date')) {
        $links[] = makeLink('/k8s/appinst.php', 'K8S Application Pods');
    }

    if ( file_exists($rootdir . "/k8s/k8s_events.json") ) {
        $links[] = makeLink('/k8s/events.php', 'K8S Events');
    }

    if ($statsDB->hasData("k8s_helm_update", 'end')) {
        $links[] = makeLink('/k8s/helm.php', 'K8s Helm');
    }

    addPostgres($links);

    if ( $statsDB->hasData('opendj_ldap_stats') ) {
        $links[] = makeLink( '/opendj_ldap.php', 'OpenDJ LDAP Data', array( 'type' => 'opendj') );
    }

    displayTab('activemq_queue_stats', '/activemq.php', 'ActiveMQ Queues/Topics');
    displayTab('eo_cassandra_stats', '/EO/cassandra_stats.php', 'EDA Cassandra');

    if ( $statsDB->hasData('eo_perf_service_stats') ) {
        $getSerivceLinks = perfServiceLinks();
        $links[] = "Service Performance \n" .makeHTMLList($getSerivceLinks);
    }

    $getF5Links = f5Links();
    if ( count($getF5Links) > 0) {
        $links[] = "F5 \n" .makeHTMLList($getF5Links);
    }

    if ($statsDB->hasData("generic_jmx_stats")) {
        $links[] = makeLink('/common/jvms.php', 'JVM Stats');
    }

    if ($statsDB->hasData("eo_jboss_connection_pool")) {
        $links[] = makeLink('/EO/jboss_connectionPool.php', 'Jboss Connection Pool');
    }

    if ( $statsDB->hasData('enm_logs', 'date', true) ) {
        $links[] = makeLink('/TOR/system/elasticsearch.php', 'Elasticsearch Logs', array( 'logdir' => 'logs'));
    }
    getKafkaLink($links);

    $content[] = makeHTMLList($links);

    return implode("\n", $content);
}

