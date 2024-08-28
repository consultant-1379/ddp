<?php

require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/indexIncFunctions.php";

function genericGenerateContent() {
    global $date, $site, $debug, $webargs, $statsDB, $rootdir, $grafanaURL;

    $links = array();

    // File search
    $links[] = makeLink('/common/findfile.php', 'File Search');

    /* Health Check */
    $links[] = makeLink('/common/hc.php', 'Generic Health Status');

    /* Granfana */
    $grafanaLink = getGrafanaLink();
    if ( ! is_null($grafanaLink) ) {
        $links[] = makeLinkForURL($grafanaLink, "Grafana");
    }

    /* Display OMBS Activity Monitor */
    if ( $statsDB->hasData('ombs_activity_monitor', 'endTime') ) {
        $links[] = makeLink('/GENERIC/OMBS/activity_monitor.php', 'OMBS Activity Monitor' );
    }

    if ($statsDB->hasData("k8s_pod", 'date')) {
        $links[] = makeLink('/k8s/appinst.php', 'K8S Application Pods');
    }

    if ( file_exists($rootdir . "/k8s/k8s_events.json") ) {
        $links[] = makeLink('/k8s/events.php', 'K8S Events');
    }

    if ($statsDB->hasData('k8s_helm_update', 'end')) {
        $links[] = makeLink('/k8s/helm.php', 'Helm');
    }

    if ($statsDB->hasData("generic_jmx_stats")) {
        $links[] = makeLink('/common/jvms.php', 'JVM Stats');
    }

    if ($statsDB->hasData("enm_postgres_stats_db")) {
        $links[] = makeLink('/TOR/databases/postgres.php', 'Postgres', array('serverid' => 'multi'));
    }

    if ($statsDB->hasData( 'enm_elasticsearch_getlog', 'date', true )) {
        $links[] = makeLink('/TOR/system/elasticsearch.php', 'Logs', array('logdir' => 'logs'));
    }

    $content = array();

    $swim = new ModelledTable('common/swim', 'swim');
    if ( $swim->hasRows() ) {
        $content[] = $swim->getTableWithHeader("Product Version");
        $content[] = addLineBreak();
    }

    $content[] =  makeHTMLList($links);

    $k8sEventsTable = new ModelledTable('common/k8s_ha_counts', 'k8s_ha_counts');
    if ( $k8sEventsTable->hasRows() ) {
        $content[] = addLineBreak();
        $content[] = makeLink( '/k8s/ha.php', 'K8S HA Event Details' );
        $content[] = $k8sEventsTable->getTable();
    }

    return implode($content);
}
