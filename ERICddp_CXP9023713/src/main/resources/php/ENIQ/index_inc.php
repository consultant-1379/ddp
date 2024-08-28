<?php
require_once PHP_ROOT . "/common/indexIncFunctions.php";
require_once PHP_ROOT . "/common/indexKafkaFunctions.php";
require_once PHP_ROOT . "/TOR/util.php";

function displayVersion() {
    global $statsDB, $date, $site;
    $statsDB->query("
SELECT
    eniq_ver_names.name
FROM
    eniq_ver, eniq_ver_names, sites
WHERE
    eniq_ver.siteid = sites.id AND
    eniq_ver.verid = eniq_ver_names.id AND
    sites.name = '$site' AND
    eniq_ver.date = '$date'
    ");

    if ($statsDB->getNumRows() == 0) {
        return "";
    }
    $row = $statsDB->getNextRow();
    return "<p><b>ENIQ Version</b>: " . formatTorVersion($row[0], true);
}

function displayEniqShipmentType( ) {
    global $statsDB, $date, $site;
    $statsDB->query("
SELECT
    eniq_ver_names.name
FROM
    eniq_ver, eniq_ver_names, sites
WHERE
    eniq_ver.siteid = sites.id AND
    eniq_ver.verid = eniq_ver_names.id AND
    sites.name = '$site' AND
    eniq_ver.date = '$date'");

    if ($statsDB->getNumRows() == 1) {
        $row = $statsDB->getNextRow();
        $eniqShipmentType = $row[0];
    }
    return $eniqShipmentType;
}

function generateSystemView( ) {
    global $site, $date, $statsDB, $datadir, $eniqShipmentType, $shipmentMatchType;

    /* File Search */
    if ( file_exists($datadir) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/common/findfile.php', LABEL => 'File Search' );
    }

    /* Servers Performance */
    $query1 = "
SELECT
    COUNT(*)
FROM
    servers, sites, hires_server_stat
WHERE
    servers.siteid = sites.id AND
    sites.name = '$site' AND
    hires_server_stat.serverid = servers.id AND
    hires_server_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

   $query2 = "
SELECT
    COUNT(*)
FROM
    servers, windows_processor_details, sites
WHERE
    servers.siteid = sites.id AND
    sites.name = '$site' AND
    windows_processor_details.serverid = servers.id AND
    windows_processor_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $dataArray[] = array(QUERY => array($query1, $query2), PATH => '/ENIQ/overview.php',
                           LABEL => 'System Performance Trend' );


    /* System Information */
    $eventBasedTableRow = $statsDB->queryRow("
SELECT
    IFNULL(CEILING(SUM(files_in_process)/96),0) AS file_in_process
FROM
    backlog_monitoring_stats, sites, backlog_interface
WHERE
    backlog_monitoring_stats.siteid = sites.id AND sites.name = '$site' AND
    backlog_monitoring_stats.intf_id = backlog_interface.id AND
    (backlog_interface.backlog_intf LIKE 'INTF_DC_E_NR_EVENTS%') AND
    backlog_monitoring_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    IFNULL(CEILING(SUM(eniq_stats_adaptor_totals.rows_sum)/96),0) AS file_in_process
FROM
    eniq_stats_adaptor_totals, eniq_stats_source, eniq_stats_types, sites
WHERE
    eniq_stats_adaptor_totals.siteid = sites.id AND sites.name = '$site' AND
    (eniq_stats_types.name = 'DC_E_ERBS_EVENTS_EUTRANCELLFDD' OR
    eniq_stats_types.name = 'DC_E_ERBS_EVENTS_EUTRANCELLTDD') AND
    eniq_stats_adaptor_totals.sourceid = eniq_stats_source.id AND
    eniq_stats_adaptor_totals.typeid = eniq_stats_types.id AND
    eniq_stats_adaptor_totals.day = '$date'
UNION
SELECT
    IFNULL(CEILING(SUM(files_in_process)/96),0) AS file_in_process
FROM
    backlog_monitoring_stats, sites, backlog_interface
WHERE
    backlog_monitoring_stats.siteid = sites.id
    AND sites.name = '$site' AND
    backlog_monitoring_stats.intf_id = backlog_interface.id AND
    (backlog_interface.backlog_intf LIKE 'INTF_DC_E_BSS_EVENTS%') AND
    backlog_monitoring_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
");

    $nodeCellCountFile = $datadir . "/plugin_data/radioNode/node_count_cell_count.json";
    $nodeNameTypeFile = $datadir . "/plugin_data/radioNode/node_name_node_type.json";
    $fileCheck = (file_exists($nodeCellCountFile) || file_exists($nodeNameTypeFile));
    $firstCheck = ($fileCheck || $statsDB->hasData('eniq_radio_cell_count_details', 'date'));
    $secondCheck = ($statsDB->hasData('eniq_transport_ims_core_node_details', 'date') || $eventBasedTableRow[0] > 0);
    $thirdCheck = ( $firstCheck || $secondCheck );
    $radioNodeCheck = ($statsDB->hasData('eniq_radio_node_count_details', 'date'));
    $picoRncCellCheck = ($statsDB->hasData('eniq_pico_rnc_cell_count_details', 'date'));
    $picoRncNodeCheck = ($statsDB->hasData('eniq_pico_rnc_node_count_details', 'date'));
    $fourthCheck = ($radioNodeCheck || $picoRncCellCheck || $picoRncNodeCheck);

    if ( $thirdCheck || $fourthCheck ) {
        $dataArray[] = array(
            QUERY => array( ),
            PATH => '/ENIQ/radioSystemInformation.php',
            LABEL => 'System Information'
        );
    }

    /* Installed Tech Packs */
    $query = $statsDB->hasDataQuery( 'active_techpack_details' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/installed_techpacks.php',
                           LABEL => 'Installed Tech Packs' );

    /* Installed Features */
    $installedFeaturesFile = $datadir . "/plugin_data/installed_features/installed_feature.json";
    if ( file_exists( $installedFeaturesFile ) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/ENIQ/installed_features.php', LABEL => 'Installed Features' );
    }

    $eniqShipmentType = displayEniqShipmentType();
    /*Upgrade Timing */
    $query = "
SELECT
    COUNT(*)
FROM
    eniq_upgrade_timing_detail, sites WHERE
    eniq_upgrade_timing_detail.siteid = sites.id AND
    sites.name = '$site' AND
    eniq_upgrade_timing_detail.date <= '$date'";

    $dataArray[] = array(QUERY => array($query), PATH =>  '/ENIQ/upgrade_timing.php', LABEL => 'Upgrade Information',
                         OTHER_ARGS => array('eniqShipmentType' => $eniqShipmentType ));

    /* ENIQ Certificate Information */
    $query = $statsDB->hasDataQuery( 'eniq_windows_certificate_details', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/eniqCertificateInformation.php',
                           LABEL => 'Certificate Information' );

    /* RHEL OS AND Patch Version */
    $query1 = $statsDB->hasDataQuery( 'eniq_rhel_version', 'date' );
    $query2 = $statsDB->hasDataQuery( 'eniq_patch_version', 'date' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/rhelPatchVersion.php',
                           LABEL => 'RHEL OS Patch Version' );

    /* JVM Stats */
    $query = $statsDB->hasDataQuery( 'generic_jmx_stats' );
    $dataArray[] = array(QUERY => array($query), PATH => '/common/jvms.php', LABEL => 'JVM Stats' );

    /* OM AND Patch Media Information */
    $query1 = $statsDB->hasDataQuery('eniq_om_media_table_info', 'date');
    $query2 = $statsDB->hasDataQuery('eniq_patch_media_table_info', 'date');
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/omPatchMediaInfo.php',
                           LABEL => 'OM & Patch Media Information' );


    return generateLinkList( $dataArray, false );
}

function generateSystemMonitoringView() {
    global $site, $date, $statsDB, $AdminDB, $isCloudNative;

    /* Health Check */
    if ($statsDB->hasData( "$AdminDB.healthcheck_results", 'date' )) {
        $dataArray[] = array(QUERY => array(), PATH => '/common/hc.php', LABEL => 'Health Status' );
    }

    /* System Monitoring */
    if (! $isCloudNative) {
        $dataArray[] = array(QUERY => array(), PATH => '/ENIQ/system_monitoring.php', LABEL => 'Service Restart Information' );
    }

    /* Disk Hardware Error */
    $query = "
SELECT
    COUNT(*)
FROM
    disk_harderror_details, sites
WHERE
    disk_harderror_details.siteid = sites.id AND
    sites.name = '$site' AND
    disk_harderror_details.harderrorCount > 0 AND
    disk_harderror_details.date = '$date'";

    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/disk_harderror.php', LABEL => 'Disk Hardware Error' );

    /* Dmesg Hardware Error */
    $query = $statsDB->hasDataQuery( 'eniq_stats_dmesg', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/dmesg_error.php', LABEL => 'Dmesg Hardware Error' );

    /* Faulty Hardware Instrumentation */
    $query = $statsDB->hasDataQuery( 'eniq_stats_faulty_hardware_details', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/faulty_hardware.php', LABEL => 'Faulty Hardware' );

    /* Core Dump Information */
    $query1 = $statsDB->hasDataQuery( 'eniq_coredump_path', 'date' );
    $query2 = $statsDB->hasDataQuery( 'eniq_coredump', 'collectionTime' );
    $beforeDate = date('Y-m-d', strtotime('-6 month', strtotime($date)));
    $query3 = "
SELECT
    COUNT(*)
FROM
    eniq_coredump, sites
WHERE
    sites.name = '$site' AND
    eniq_coredump.siteId = sites.id AND
    eniq_coredump.collectionTime < '$date 00:00:00' AND
    eniq_coredump.collectionTime > '$beforeDate 00:00:00'";

    $dataArray[] = array(QUERY => array( $query1, $query2, $query3 ), PATH => '/ENIQ/coredump_information.php',
                           LABEL => 'Core Dump Information' );

    /* Engine Scheduler Heap Usage */
    $query1 = $statsDB->hasDataQuery( 'eniq_engine_heap_memory' );
    $query2 = $statsDB->hasDataQuery( 'eniq_scheduler_heap_memory' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/engine_scheduler_heap_usage.php',
                           LABEL => 'Heap Usage' );

    /* ENIQ FC Switch Port Alarm Information */
    $query = $statsDB->hasDataQuery( 'eniq_fc_switch_port_alarms', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/fcSwitchPortAlarm.php',
                           LABEL => 'FC SwitchPort Alarm' );

    /* SIM Analysis */
    $query = $statsDB->hasDataQuery( 'sim_stats', 'start_time' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/CollectionStatistics.php',
                           LABEL => 'Collection Statistics' );

    $query = $statsDB->hasDataQuery( 'sim_error' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/ErrorAnalysis.php', LABEL => 'Error Analysis' );

    /* OMBS Backup */
    $query = "
SELECT
    COUNT(*)
FROM
    ombs_backup_metrics, sites
WHERE
    ombs_backup_metrics.siteid = sites.id AND
    sites.name = '$site'";

    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/ombs_backup.php', LABEL => 'OMBS Backup' );

    /* Rolling Snapshot */
    $query = "
SELECT
    COUNT(*)
FROM
    rolling_snapshot_backup_metrics, sites
WHERE
    rolling_snapshot_backup_metrics .siteid = sites.id AND
    sites.name = '$site'";

    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/rolling_snapshot.php', LABEL => 'Rolling Snapshot' );

    /* Delimiter Error Loader Sets */
    $query = $statsDB->hasDataQuery( 'eniq_loader_delimiter_error', 'time_stamp' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/ENIQ/delimiterErrorLoaderSets.php',
        LABEL => 'Delimiter Error Loader Sets'
    );

    /* Failed Set Information */
    $query = $statsDB->hasDataQuery( 'eniq_loader_aggregator_failedset_details', 'time_stamp' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/ENIQ/failedSetInformation.php',
        LABEL => 'Failed Set Information'
    );

    /* IBS Error Loader Sets */
    $query = $statsDB->hasDataQuery( 'eniq_IBS_error_loaderset', 'timeStamp' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/ENIQ/ibsErrorLoaderSets.php',
        LABEL => 'IBS Error Loader Sets'
    );

    return generateLinkList( $dataArray, false );

}

function generateETLMonitoringView( ) {
    global $site, $date, $statsDB;

    /* Data Task Information */
    $query = $statsDB->hasDataQuery( 'eniq_meta_transfer_batches');
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/data_task_info.php', LABEL => 'Data Task Information' );

    /* Stats Parsing/Loading */
    // the OR condition(eniq_stats_adaptor_totals.workflow_type = 0) is added in the below query since the table
    // structure was altered for existing data in db newly added column workflow_type value was set to 0 by default.
    $query1 = "
SELECT
    COUNT(*)
FROM
    eniq_stats_adaptor_totals, sites, eniq_stats_workflow_types
WHERE
    eniq_stats_adaptor_totals.siteid = sites.id AND sites.name = '$site' AND
    (eniq_stats_adaptor_totals.workflow_type = eniq_stats_workflow_types.workflow_type_id OR
    eniq_stats_adaptor_totals.workflow_type = 0) AND
    eniq_stats_workflow_types.workflow_type='' AND eniq_stats_adaptor_totals.day = '$date'";

    $query2 = "
SELECT
    COUNT(*)
FROM
    eniq_stats_adaptor_sessions, sites, eniq_stats_workflow_types
WHERE
    eniq_stats_adaptor_sessions.siteid = sites.id AND sites.name = '$site' AND
    (eniq_stats_adaptor_sessions.workflow_type = eniq_stats_workflow_types.workflow_type_id OR
    eniq_stats_adaptor_sessions.workflow_type = 0) AND
    eniq_stats_workflow_types.workflow_type='' AND
    eniq_stats_adaptor_sessions.timeslot BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/stats_parsing.php', LABEL => 'Parsing' );

    /* Loading */
    $query1= $statsDB->hasDataQuery( 'eniq_stats_loader_running' );
    $query2= $statsDB->hasDataQuery( 'eniq_stats_loader_sessions', 'minstart' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/stats_loading.php', LABEL => 'Loading' );

    /* Aggregation */
    $query1 = $statsDB->hasDataQuery( 'eniq_stats_aggregator_sessions', 'start' );
    $query2 = $statsDB->hasDataQuery( 'eniq_stats_aggregator_running' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/stats_aggregation.php',
                           LABEL => 'Aggregation' );

    /* Stats Average Parsing Loading */
    $query = $statsDB->hasDataQuery( 'eniq_loading_parsing_duration', 'ropStartTime' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/stats_parsing_loading_average.php',
                           LABEL => 'ROP Loading Performance' );

    /*Backlog Analysis */
    $query = $statsDB->hasDataQuery( 'backlog_monitoring_stats' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/backlog_monitoring.php', LABEL => 'Backlog Analysis' );

    /* ROP File Integrity */
    $query = "
SELECT
    COUNT(*)
FROM
    eniq_stats_file_ingress_processed, sites
WHERE
    eniq_stats_file_ingress_processed.site_id = sites.id AND
    sites.name = '$site' AND
    eniq_stats_file_ingress_processed.rop_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/rop_file_integrity.php',
                           LABEL => 'ROP File Integrity' );

    return generateLinkList( $dataArray, false );
}

function generateDatabasesView( ) {
    global $site, $date, $statsDB;
    $eniqShipmentType = displayEniqShipmentType();


    /* DBCC Information */
    $query = $statsDB->hasDataQuery( 'eniq_dbcc_table_info', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/dbccTableInfo.php', LABEL => 'DBCC Information' );

    /* SAP IQ */
    $query = $statsDB->hasDataQuery( 'eniq_sap_iq_version_patch_details', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/sap_iq_version_patch.php',
                           LABEL => 'Version Information' );

    $query = $statsDB->hasDataQuery( 'iq_dbspaces', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/sap_iq_disk_utilization.php',
                           LABEL => 'Disk Utilization' );

    $query1 = $statsDB->hasDataQuery( 'eniq_stats_dwhdb_count' );
    $query2 = $statsDB->hasDataQuery( 'eniq_stats_repdb_count' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/database_connection.php',
                           LABEL => 'DB Connection Information' );

    $statsDB->query("
SELECT
    DISTINCT(servers.hostname) AS host, servers.type AS type
FROM
    iq_monitor_summary,servers,sites
WHERE
    iq_monitor_summary.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    iq_monitor_summary.serverid = servers.id AND servers.siteid = sites.id AND
    sites.name = '$site'
UNION
SELECT
    DISTINCT(servers.hostname) AS host, servers.type AS type
FROM
    eniq_sap_iq_large_memory_details, servers, sites
WHERE
    eniq_sap_iq_large_memory_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    eniq_sap_iq_large_memory_details.serverId = servers.id AND servers.siteid = sites.id AND
    sites.name = '$site'");

    if ( $statsDB->getNumRows() > 0 ) {
        while ( $row = $statsDB->getNextRow() ) {
            $dataArray[] = array(
                QUERY => array(),
                PATH => '/ENIQ/iq.php',
                LABEL => $row[0].':'.$row[1],
                OTHER_ARGS => array( 'server' => $row[0], 'eniqShipmentType' => $eniqShipmentType )
            );
        }
    }

    return generateLinkList( $dataArray, false );
}

function generateENIQEventsView( ) {

    global $site, $date, $statsDB, $phpDir;

    $query = "
SELECT
    eniq_workflow_names.name AS name,
    eniq_workflow_types.name AS type,
    eventcount AS num,
    avgduration AS avg,
    maxduration AS max
FROM
    eniq_workflow_events, eniq_workflow_names, eniq_workflow_types, sites
WHERE
    eniq_workflow_names.id = nameid AND eniq_workflow_types.id = typeid AND siteid = sites.id AND sites.name = '$site'
    AND date = '$date'";

    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/workflow_event_info.php',
                        LABEL => 'Workflow Event Information' );

    /* Events Streaming */
    $query = $statsDB->hasDataQuery( 'eniq_streaming', 'rop' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/streaming.php', LABEL => 'Events Streaming' );

    /* Events Loading */
    $query = $statsDB->hasDataQuery( 'eniq_events_loaded' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/events_loaded.php', LABEL => 'Events Loaded' );

    /* Generic Measurements used by system test to record their own measurements */
    $query = $statsDB->hasDataQuery( 'gen_measurements' );
    $dataArray[] = array(QUERY => array($query), PATH => $phpDir . '/generic_measurements.php',
                         LABEL => 'DDP Generic Measurements');

    /* LTE-ES Counter Configuration */
    $query = "
SELECT
    COUNT(*)
FROM
    eniq_ltees_counter_details, sites
WHERE
    eniq_ltees_counter_details.siteid = sites.id AND sites.name = '$site' AND
    eniq_ltees_counter_details.datetime <= '$date 23:59:59'";
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/ltees_counter_config.php',
                         LABEL => 'LTE-ES Counter Configuration' );

    /* ENIQ Events MZ Workflow Execution */
    $statsDB->query("
SELECT
    DISTINCT(eniq_folder_names.name) AS folder
FROM
    eniq_workflow_executions, eniq_folder_names, sites
WHERE
    eniq_workflow_executions.tload BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND
    eniq_workflow_executions.fldrid = eniq_folder_names.id AND
    eniq_workflow_executions.siteid = sites.id AND sites.name = '$site'");
    if ( $statsDB->getNumRows() > 0 ) {
        while ( $row = $statsDB->getNextRow() ) {
            $dataArray[] = array(QUERY => array(), PATH => '/ENIQ/wfexec.php', LABEL =>  $row[0]);
        }
    }

    /* ENIQ Events MZ Workflow Instr */
    $mzWfInstr    = array(
        'eniq_streaming_ctum_collector:eniq_streaming_ctr_collector' => array(
            'title' => 'Streaming',
            'page'  => 'streaming_instr.php'
        ),
        'eniq_ltees_counter:eniq_delta_topology:eniq_ltees_topology' => array(
            'title' => 'LTE ES',
            'page'  => 'ltees_instr.php'
        ),
        'eniq_lteefa_processor:eniq_lteefa_rf_enrichment:eniq_lteefa_rfevents_load_balance' => array(
            'title' => 'LTE CFA HFA',
            'page'  => 'lteefa_instr.php'
        ),
        'eniq_sgeh_processing_nfs:eniq_sgeh_success_handling' => array(
            'title' => 'SGEH',
            'page'  => 'sgeh_instr.php'
        ),
        'eniq_streaming_dvtp_collector' => array(
            'title' => 'DVTP',
            'page'  => 'dvtp_instr.php'
        )
    );
    foreach ($mzWfInstr as $table => $params) {
        if (preg_match('/:/',$table)) {
            $processedFiles = 'true';
            $tableArray = explode(':', $table);
            foreach ($tableArray as $tableName) {
                if ( $processedFiles == 'true' ) {
                    $query1 = $statsDB->hasDataQuery( $tableName );
                    $dataArray[] = array(QUERY => array($query1), PATH => 'ENIQ/' . $params['page'] . '',
                                         LABEL => ''. $params['title'] . '');
                    $processedFiles = 'false';
                }
            }
        } else {
            $query2 = $statsDB->hasDataQuery( $tableName );
            $dataArray[] = array(QUERY => array($query2), PATH => 'ENIQ/' . $params['page'] . '',
                                 LABEL => ''. $params['title'] . '');
        }
    }

    /* Glassfish Accesslog Instrumentation and Success Aggregation Instrumentation */
    $query1 = $statsDB->hasDataQuery( 'glassfish_stats');
    $dataArray[] = array(QUERY => array($query1), PATH => 'ENIQ/url_analysis.php', LABEL => 'URL Access Analysis' );
    $query2 = $statsDB->hasDataQuery( 'event_notification_succ_aggr_jmx_stats');
    $dataArray[] = array(QUERY => array($query2), PATH => 'ENIQ/jmsQueueMetrics.php', LABEL => 'JMS Queue Metrics' );

    generateENIQEventsView2( );

    return generateLinkList( $dataArray );
}

function generateENIQEventsView2( ) {

    global $site, $date, $statsDB, $phpDir;

    /* EDE Instrumentation */
    $edeInstrumentation = array(
        'ede_ctr'  => array(
            'title' => 'CTR'
        ),
        'ede_ctum' => array(
            'title' => 'CTUM'
        ),
       'ede_epg' => array(
            'title' => 'EPG'
        ),
       'ede_ebm' => array(
            'title' => 'EBM'
        ),
       'ede_ebm2g3g' => array(
            'title' => 'EBM2G3G'
        ),
       'ede_gpeh' => array(
            'title' => 'GPEH'
        )
    );
    $edeFlag = true;
    foreach ($edeInstrumentation as $table => $params) {
        $query1 = "
        SELECT
            COUNT(*)
        FROM
            ede_output_csv_log_details, sites, data_source_id_mapping
        WHERE
            ede_output_csv_log_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ede_output_csv_log_details.siteid = sites.id AND sites.name = '$site' AND
            data_source_id_mapping.data_source = '" . $params['title'] . "' AND
            data_source_id_mapping.id = ede_output_csv_log_details.data_source_id";
        $query2 = "
        SELECT
            COUNT(*)
        FROM
            ede_controller, sites, data_source_id_mapping
        WHERE
           ede_controller.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
           ede_controller.siteid = sites.id AND sites.name = '$site' AND
           data_source_id_mapping.data_source = '" . $params['title'] . "' AND
           data_source_id_mapping.id = ede_controller.data_source_id";
        $query3 = "
        SELECT
           COUNT(*)
        FROM
           ede_event_distribution_details, sites, data_source_id_mapping
        WHERE
           ede_event_distribution_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
           ede_event_distribution_details.siteid = sites.id AND sites.name = '$site' AND
           data_source_id_mapping.data_source = '" . $params['title'] . "' AND
           data_source_id_mapping.id = ede_event_distribution_details.data_source_id";
        $query4 = "
         SELECT
            COUNT(*)
         FROM
            ede_linkfile_details, sites, data_source_id_mapping
         WHERE
            ede_linkfile_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            ede_linkfile_details.siteid = sites.id AND sites.name = '$site' AND
            data_source_id_mapping.data_source = '" . $params['title'] . "' AND
            data_source_id_mapping.id = ede_linkfile_details.data_source_id";
            if ($edeFlag == true) {
                $dataArray[] = array(
                    QUERY => array($query1, $query2, $query3, $query4),
                    PATH => 'ENIQ/ede_instance.php',
                    LABEL => ''. $params['title'] . '',
                    OTHER_ARGS => array('source' => ''. $params['title'] . '') );
                $edeFlag = false;
            }
        }

    return generateLinkList( $dataArray );
}


function generateCloudEniqView( ) {
    global $site, $date, $statsDB, $isCloudNative;

    /* K8S Application */
    $isCloudNative = false;
    if ($statsDB->hasData( "k8s_pod", 'date', true )) {
        $isCloudNative = true;
        $dataArray[] = array(QUERY => array(), PATH => '/k8s/appinst.php',LABEL => 'K8S Applications' );
    }

    /* SAP IQ Stats */
    $query = $statsDB->hasDataQuery( 'eniq_data_layer_sap_iq' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/data_layer_sap_iq.php', LABEL => 'SAP IQ Stats' );

    /* zookeeper */
    $query = $statsDB->hasDataQuery( 'zookeeper' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/zookeeper.php', LABEL => 'Zookeeper Stats' );

    /* Elasticsearch */
    if ( $statsDB->hasData( 'elasticsearch_tp' ) ) {
        $servicetype = 'elasticsearch';
        $statsDB->query("
SELECT
    DISTINCT elasticsearch_tp.serverid, servers.hostname
FROM
    elasticsearch_tp
JOIN
    sites ON elasticsearch_tp.siteid = sites.id
JOIN
    servers ON elasticsearch_tp.serverid = servers.id
WHERE
    sites.name = '$site' AND
    elasticsearch_tp.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND servicetype = '$servicetype'");
        if ( $statsDB->getNumRows() > 0 ) {
            while ( $row = $statsDB->getNextRow() ) {
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/common/platform/elasticsearch/elasticsearch_stats.php',
                    LABEL =>  $row[1],
                    OTHER_ARGS => array( 'serverid' => $row[0], 'servicetype' => $servicetype )
                );
            }
        }
    }

    /* kafka */
    $serverIdToApp = array();
    $appToTopics = kafkaTopics($serverIdToApp);
    foreach ($appToTopics as $app => $topics) {
        if (!empty($topics)) {
            $dataArray[] = array(
                QUERY => array(),
                PATH => '/common/kafka.php',
                LABEL => 'Kakfa - ' . $app,
                OTHER_ARGS => array( array('topics' => implode(",", $topics) ))
            );
        }
    }

    return generateLinkList( $dataArray );
}

function generateCounterView( ) {
    global $site, $date, $statsDB;

    /* Counter Statistics */
    $query = $statsDB->hasDataQuery( 'eniq_aggregated_accessed_counter_details', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/aggregatedAccessedCounter.php',
                        LABEL => 'Counter Statistics' );

    /* Failed Counter Tool Aggregation */
    $query = $statsDB->hasDataQuery( 'eniq_agg_fail_counter_date', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/eniq_counter_aggregation_failed.php',
                           LABEL => 'Failed Counter Tool Aggregation' );

    return generateLinkList( $dataArray );
}

function generateOSSIntegrationView( ) {
    global $site, $date, $statsDB, $datadir;

    $ossIntegrationModeFile = $datadir . "/plugin_data/file_lookup_service/fls_oss_integration_mode.json";
    if ( file_exists( $ossIntegrationModeFile ) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/ENIQ/ossIntegrationModeInformation.php',
                               LABEL => 'Integration Mode Information' );
    }

    $query = "
SELECT
    COUNT(*)
FROM
    eniq_fls_master_slave_details, sites, fls_role_type_id_mapping, fls_server_name_id_mapping
WHERE
    sites.id = eniq_fls_master_slave_details.siteId AND sites.name = '$site' AND
    eniq_fls_master_slave_details.roleId  = fls_role_type_id_mapping.id AND
    eniq_fls_master_slave_details.serverId = fls_server_name_id_mapping.id AND
    eniq_fls_master_slave_details.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'";

    $dataArray[] = array(QUERY => array($query), PATH => 'ENIQ/fls_master_slave_role.php',
                           LABEL => 'Role Information' );

    $query1 = "
SELECT
    COUNT(*)
FROM
    eniq_stats_fls_symlink_details, sites
WHERE
    sites.id = eniq_stats_fls_symlink_details.siteId AND sites.name = '$site' AND
    eniq_stats_fls_symlink_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $query2 = "
SELECT
    COUNT(*)
FROM
    eniq_stats_fls_file_details, sites
WHERE
    sites.id = eniq_stats_fls_file_details.siteId AND sites.name = '$site' AND
    eniq_stats_fls_file_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => 'ENIQ/fls_metadata.php',
                           LABEL => 'Metadata Symlink Information' );


    return generateLinkList( $dataArray );
}

function generateFSSnapshotView( ) {
    global $site, $date, $statsDB;
    $dataArray = array();

    if ( $statsDB->hasData('eniq_fs_snapshot_utilization') ) {
        $statsDB->query("
        SELECT
         DISTINCT(servers.hostname),
         servers.type
        FROM
         eniq_fs_snapshot_utilization, servers, sites
        WHERE
         sites.id = eniq_fs_snapshot_utilization.siteid AND
         sites.name = '$site' AND
         servers.id = eniq_fs_snapshot_utilization.serverid AND
         eniq_fs_snapshot_utilization.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ( $statsDB->getNumRows() > 0 ) {
            while ( $row = $statsDB->getNextRow() ) {
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/ENIQ/fsSnapshotUtilization.php',
                    LABEL => $row[0].':'.$row[1],
                    OTHER_ARGS => array( 'server' => $row[0] )
                );
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateOSMemoryProfileView( ) {

    global $site, $date, $statsDB;

    $osMemoryProfile = $statsDB->queryRow("
        SELECT
         COUNT(*)
        FROM
         eniq_stats_os_memory_profile, sites
        WHERE
         sites.id = eniq_stats_os_memory_profile.siteId AND
         sites.name = '$site' AND
         eniq_stats_os_memory_profile.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    $dataArray = array();
    if ( $osMemoryProfile[0] > 0 ) {
        $statsDB->query("
        SELECT
         DISTINCT (servers.hostname) , servers.type
        FROM
         eniq_stats_os_memory_profile
        JOIN sites ON eniq_stats_os_memory_profile.siteId = sites.id
        JOIN servers ON eniq_stats_os_memory_profile.serverId = servers.id
        WHERE
         sites.name = '$site' AND
         eniq_stats_os_memory_profile.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ( $statsDB->getNumRows() > 0 ) {
            while ( $row = $statsDB->getNextRow() ) {
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/ENIQ/osMemoryProfile.php',
                    LABEL => $row[0].':'.$row[1],
                    OTHER_ARGS => array( 'server' => $row[0] )
                );
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateOSProfileRhelView( ) {

    global $site, $date, $statsDB;

    $osMemoryProfile = $statsDB->queryRow("
        SELECT
         COUNT(*)
        FROM
         eniq_stats_os_memory_profile_Rhel, sites
        WHERE
         sites.id = eniq_stats_os_memory_profile_Rhel.siteId AND
         sites.name = '$site' AND
         eniq_stats_os_memory_profile_Rhel.timeStamp BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        ");
    if ( $osMemoryProfile[0] > 0 ) {

        $statsDB->query("
        SELECT
         DISTINCT (servers.hostname) AS host, servers.type AS type
        FROM
         eniq_stats_os_memory_profile_Rhel
        JOIN sites ON eniq_stats_os_memory_profile_Rhel.siteId = sites.id
        JOIN servers ON eniq_stats_os_memory_profile_Rhel.serverId = servers.id
        WHERE
         sites.name = '$site' AND
         eniq_stats_os_memory_profile_Rhel.timeStamp BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ( $statsDB->getNumRows() > 0 ) {
            while ( $row = $statsDB->getNextRow() ) {
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/ENIQ/osMemoryProfileRhel.php',
                    LABEL => $row[0].':'.$row[1],
                    OTHER_ARGS => array( 'server' => $row[0] )
                );
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateNodeHardeningView( ) {
    global $datadir;

    /* Node Hardening Summary Status*/
    $path = $datadir . "/plugin_data/node_hardening/";
    $dataArray = array();
    if ( isset($path) && file_exists($path) ) {
        $files = array_diff(scandir($path), array('.', '..'));
        foreach ( $files as $file ) {
            $filePath = $path . $file;
            if ( file_exists($filePath) ) {
                $pattern='/^node_hardening_(.*)-(.*)-(\w+).json/';
                preg_match($pattern, $file, $fileMatchType);
                if ( isset($fileMatchType[1]) && isset($fileMatchType[2]) && isset($fileMatchType[3]) ) {
                    $lbl = $fileMatchType[2].':'.strtoupper($fileMatchType[3]);
                    $otherArgs = array('hardeningType' => $fileMatchType[1],'server' => $fileMatchType[2],'serverType' => $fileMatchType[3]);
                    $dataArray[] = array(
                            QUERY => array(),
                            PATH => '/ENIQ/nodeHardeningSummary.php',
                            LABEL =>  $lbl,
                            OTHER_ARGS => $otherArgs
                    );
                }
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateEniqActivityHistoryView( ) {
    global $datadir;

    /* Eniq Activity History Information*/
    $path = $datadir . "/plugin_data/eniq_activity_history/";
    $dataArray = array();
    if ( file_exists($path) ) {
        $files = array_diff(scandir($path), array('.', '..'));
        foreach ( $files as $file ) {
            $filePath = $path . $file;
            if ( isset($filePath) && file_exists($filePath) ) {
                $pattern='/^eniq_activity_history-(.*)-(\w+).json/';
                preg_match($pattern, $file, $fileMatchType);
                if ( isset($fileMatchType[1]) && isset($fileMatchType[2]) ) {
                    $lbl = $fileMatchType[1].':'.strtoupper($fileMatchType[2]);
                    $otherArgs = array('server' => $fileMatchType[1],'serverType' => $fileMatchType[2]);
                    $dataArray[] = array(
                        QUERY => array(),
                        PATH => '/ENIQ/eniqActivityHistoryInformation.php',
                        LABEL =>  $lbl,
                        OTHER_ARGS => $otherArgs
                    );
                }
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateLUNMpathIQHeaderMappingView( ) {
    global $datadir;

    /* LUNs Mpath IQ Header Mapping*/
    $path = $datadir . "/plugin_data/lun_mpath_iq_header_mapping/";
    $dataArray = array();
    if ( file_exists($path) ) {
        $files = array_diff(scandir($path), array('.', '..'));
        foreach ( $files as $file ) {
            $filePath = $path . $file;
            if ( isset($filePath) && file_exists($filePath) ) {
                $pattern='/^lun_mpath_mapping-(.*)-(\w+).json/';
                preg_match($pattern, $file, $fileMatchType);
                if ( isset($fileMatchType[1]) && isset($fileMatchType[2]) ) {
                    $lbl = $fileMatchType[1].':'.strtoupper($fileMatchType[2]);
                    $otherArgs = array('server' => $fileMatchType[1], 'serverType' => $fileMatchType[2]);
                    $dataArray[] = array(
                        QUERY => array(),
                        PATH => '/ENIQ/LunMpathIQHeaderMapping.php',
                        LABEL =>  $lbl,
                        OTHER_ARGS => $otherArgs
                    );
                }
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateNetanApplicationView( ) {
    global $site, $date, $statsDB;

    /* User Statistics */
    $query1 = $statsDB->hasDataQuery( 'netanserver_userauditlog_details' );
    $query2 = $statsDB->hasDataQuery( 'netanserver_user_session_statistics_details' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/useraudit.php',
                           LABEL => 'User Statistics' );

    /* Analysis Statistics */
    $query1 = $statsDB->hasDataQuery( 'netanserver_auditlog_details' );
    $query2 = $statsDB->hasDataQuery( 'netanserver_open_file_statistics_details' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/analysisStats.php',
                           LABEL => 'Analysis Statistics' );

    return generateLinkList( $dataArray );
}

function generateNetanFeatureView( ) {
    global $site, $date, $statsDB;

    /* PM Alarming */
    $query1 = $statsDB->hasDataQuery( 'eniq_netan_pma_details' );
    $query2 = $statsDB->hasDataQuery( 'eniq_netan_pmdb_alarm_summary_details' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/netanPmaStatistics.php',
                           LABEL => 'PM Alarming' );

    /* PM Explorer */
    $query1 = $statsDB->hasDataQuery( 'eniq_netan_pme_details' );
    $query2 = $statsDB->hasDataQuery( 'eniq_netan_pmdb_collections_summary_details' );
    $query3 = $statsDB->hasDataQuery( 'eniq_netan_pmdb_report_summary_details' );
    $dataArray[] = array(QUERY => array( $query1, $query2, $query3 ), PATH => '/ENIQ/netanPmeStatistics.php',
                           LABEL => 'PM Explorer' );

    return generateLinkList( $dataArray );
}

function generateBISServerView( ) {
    global $site, $date, $statsDB;

    /* User Statistics */
    $query1 = $statsDB->hasDataQuery( 'bis_active_users_list' );
    $query2 = "
SELECT
    COUNT(*)
FROM
    bis_users_list, sites
WHERE
    bis_users_list.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    bis_users_list.siteid = sites.id AND
    sites.name = '$site' AND
    bis_users_list.userTypeId = 0 ";

    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/userStats.php',
                           LABEL => 'User Statistics' );

    /* Report Statistics */
    $query1 = $statsDB->hasDataQuery( 'bis_report_refresh_time' );
    $query2 = $statsDB->hasDataQuery( 'bis_report_list' );
    $query3 = $statsDB->hasDataQuery( 'bis_report_instances' );
    $dataArray[] = array(QUERY => array( $query1, $query2, $query3 ), PATH => '/ENIQ/reportStats.php',
                           LABEL => 'Report Statistics' );

    /* Scheduling Information */
    $query = $statsDB->hasDataQuery( 'bis_scheduling_info' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/bisSchedulingInfo.php',
                           LABEL => 'Scheduling Information' );

    /* Prompt Details */
    $query = $statsDB->hasDataQuery( 'bis_prompt_info' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/promptDetails.php', LABEL => 'Prompt Details' );

    /* System BO */
    $query1 = $statsDB->hasDataQuery( 'eniq_system_bo' );
    $query2 = $statsDB->hasDataQuery( 'eniq_system_bo_all' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/systemBoData.php', LABEL => 'System BO' );

    return generateLinkList( $dataArray );
}

/* Citrix License Usage and OCS System BO Statistics */
function generateOCSServerView( ) {
    global $site, $date, $statsDB;

    /* Citrix License Usage Statistics */
    $query = $statsDB->hasDataQuery( 'eniq_ocs_license_usage_details' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/citrix_license_usage.php',
                           LABEL => 'Citrix License Usage Statistics' );

    /* SYSTEM_BO */
    $query1 = $statsDB->hasDataQuery( 'eniq_ocs_system_bo' );
    $query2 = $statsDB->hasDataQuery( 'eniq_ocs_system_bo_all' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/ocsSystemBoData.php',
                           LABEL => 'SYSTEM BO' );

    /* Published Application in CCS */
    if ( $statsDB->hasData('eniq_ocs_published_application') ) {
         $statsDB->query("
SELECT DISTINCT(servers.hostname) AS host
FROM eniq_ocs_published_application
JOIN sites ON eniq_ocs_published_application.siteid = sites.id
JOIN servers ON eniq_ocs_published_application.serverid = servers.id
WHERE
 sites.name = '$site' AND
 eniq_ocs_published_application.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        if ( $statsDB->getNumRows() > 0 ) {
            while ( $row = $statsDB->getNextNamedRow() ) {
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/ENIQ/publishedApplicationInfo.php',
                    LABEL => 'Published Application in CCS',
                    OTHER_ARGS => array( 'server' => $row['host'])
                );
            }
        }
    }

    return generateLinkList( $dataArray );
}

function generateOCSWithoutCitrixServerView( ) {
    global $site, $date, $statsDB;

    /* SYSTEM_BO */
    $query1 = $statsDB->hasDataQuery( 'eniq_ocs_system_bo' );
    $query2 = $statsDB->hasDataQuery( 'eniq_ocs_system_bo_all' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/ENIQ/ocsSystemBoData.php',
                           LABEL => 'SYSTEM BO' );

    /* Certificate Information */
    $query = $statsDB->hasDataQuery( 'eniq_windows_certificate_details', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/ocsWithoutCitrixCertificateInformation.php',
                           LABEL => 'Certificate Information' );

    return generateLinkList( $dataArray );
}

function generateHWView( ) {
    global $site, $date, $statsDB;

    /* NAS File System Status */
    $query = $statsDB->hasDataQuery( 'eniq_sfs_storage_fs_details', 'date' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/ENIQ/nasFileSystemStatus.php',
        LABEL => 'NAS File System Status'
    );

    /* SFS Snapshot Cache Status */
    $query = $statsDB->hasDataQuery( 'eniq_sfs_snap_cache_status', 'date' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/ENIQ/sfsSnapshotCacheStatus.php',
        LABEL => 'SFS Snapshot Cache Status'
    );

    /* Certificate Information */
    $query = $statsDB->hasDataQuery( 'eniq_windows_certificate_details', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/ENIQ/unityCertificateInformation.php',
                           LABEL => 'Unity/UnityXT Certificate Information' );


    /* EMC Storage */
    $statsDB->query("
SELECT
    DISTINCT emc_sys.name, emc_sys.id
FROM
    emc_sys
JOIN
    emc_site ON emc_sys.id = emc_site.sysid
JOIN
    sites ON emc_site.siteid = sites.id
WHERE
    sites.name = '$site' AND
    emc_site.filedate = '$date'
");
    while ( $row = $statsDB->getNextRow() ) {
        $dataArray[] = array(QUERY => array(), PATH => '/emc_stor.php', LABEL => "EMC Storage: $row[0]",
                             OTHER_ARGS => array( 'sysid' => $row[1] ) );
    }

    /* SFS [OR] Access NAS Summary Page */
    $sfsNodeTypes = getSfsNodeTypes($statsDB, $site, $date);
    if ( array_key_exists('SFS', $sfsNodeTypes) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/sfs.php', LABEL => 'SFS' );
    }
    if ( array_key_exists('ACCESSNAS', $sfsNodeTypes) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/sfs.php', LABEL => "Access NAS",
                             OTHER_ARGS => array( 'nodetype' => 'accessnas' ) );
    }

    return generateLinkList( $dataArray );
}

function generateUserGuideView( ) {
    global $site, $date, $eniqShipmentType, $shipmentMatchType;

    /* DDP User Guide */
    $eniqShipmentType = displayEniqShipmentType();
    $pattern='/^ENIQ_(\w+)_Shipment.*/';
    preg_match($pattern, $eniqShipmentType, $shipmentMatchType);

    if ($shipmentMatchType[1] == "Statistics") {
        $confUrl = "https://eteamspace.internal.ericsson.com/pages/viewpage.action?spaceKey=EAG&title=ENIQ+Stats+DDP+User+Guide+PDF&preview=/1910095091/1910097572/ENIQ%20Stats%20Diagnostic%20Data%20Presentation%20User%20Guide.pdf"; //NOSONAR
    } elseif ($shipmentMatchType[1] == "Events") {
        $confUrl = "https://eteamspace.internal.ericsson.com/display/EAG/ENIQ+Events+DDP+User+Guide+PDF?preview=/1910095120/1910097274/ENIQ%20Events%20Diagnostic%20Data%20Presentation%20User%20Guide.pdf"; //NOSONAR
    }
    if ( isset($confUrl) ) {
        $dataArray[] = array(HTML => makeLinkForURL($confUrl, "DDP User Guide"));
    }

    return generateLinkList( $dataArray );
}

function eniqGenerateContent() {
    global $php_webroot;

    $content = array();

    $content[] = "<div class='h1-inline-text'><h1>ENIQ Statistics</div>\n";
    $content[] = displayVersion();

    $menuScript = <<<EOS
<script type="text/javascript" src="$php_webroot/ENIQ/index_inc.js"></script>
<script type="text/javascript">
YAHOO.util.Event.addListener(window, "load", eniqMenuTreeEnhance);
</script>

<div id="eniqMenuTree" class="yui-skin-sam">

EOS;
    $content[] = $menuScript;

    // An array of arrays (one for each view to be displayed) each containing a list,
    // a unique name (the value of VIEW) and a label.
    $allViewLists[] = array(
        array(
                VIEW_LIST => generateSystemView(),
                VIEW => 'SystemView',
                LABEL => 'System View'
        ),
        array(
                VIEW_LIST => generateSystemMonitoringView(),
                VIEW => 'SystemMoni',
                LABEL => 'System Monitoring'
        ),
        array(
                VIEW_LIST => generateETLMonitoringView(),
                VIEW => 'ETL',
                LABEL => 'ETL Monitoring'
        ),
        array(
                VIEW_LIST => generateDatabasesView(),
                VIEW => 'Databases',
                LABEL => 'Databases'
        ),
        array(
                VIEW_LIST => generateENIQEventsView(),
                VIEW => 'EniqEvent',
                LABEL => 'ENIQ-Events'
        ),
        array(
                VIEW_LIST => generateCloudEniqView(),
                VIEW => 'CloudEniq',
                LABEL => 'C-ENIQ '
        ),
        array(
                VIEW_LIST => generateCounterView(),
                VIEW => 'Counter',
                LABEL => 'Accessed Counter Details'
        ),
        array(
                VIEW_LIST => generateOSSIntegrationView(),
                VIEW => 'OSSIntegration',
                LABEL => 'OSS Integration'
        ),
        array(
                VIEW_LIST => generateFSSnapshotView(),
                VIEW => 'FSSnapshot',
                LABEL => 'FS Snapshot Utilization'
        ),
        array(
                VIEW_LIST => generateOSMemoryProfileView(),
                VIEW => 'OSMemory',
                LABEL => 'OS Memory Profile'
        ),
        array(
                VIEW_LIST => generateOSProfileRhelView(),
                VIEW => 'OSMemoryRhel',
                LABEL => 'OS Memory Profile'
        ),
        array(
                VIEW_LIST => generateNodeHardeningView(),
                VIEW => 'NodeHardening',
                LABEL => 'Node Hardening Summary Status'
        ),
        array(
                VIEW_LIST => generateEniqActivityHistoryView(),
                VIEW => 'EniqActivityHistory',
                LABEL => 'ENIQ Activity History'
        ),
        array(
                VIEW_LIST => generateLUNMpathIQHeaderMappingView(),
                VIEW => 'LUNMpathIQHeaderMapping',
                LABEL => 'LUNs Mpath IQHeader Mapping'
        ),
        array(
                VIEW_LIST => generateNetanApplicationView(),
                VIEW => 'NetanApplication',
                LABEL => 'NetAn Application Statistics'
        ),
        array(
                VIEW_LIST => generateNetanFeatureView(),
                VIEW => 'NetanFeature',
                LABEL => 'NetAn Feature Statistics'
        ),
        array(
                VIEW_LIST => generateBISServerView(),
                VIEW => 'BIS',
                LABEL => 'BIS'
        ),
        array(
                VIEW_LIST => generateOCSServerView(),
                VIEW => 'OCS',
                LABEL => 'OCS'
        ),
        array(
                VIEW_LIST => generateOCSWithoutCitrixServerView(),
                VIEW => 'OCSWithout',
                LABEL => 'OCS Without Citrix'
        ),
        array(
                VIEW_LIST => generateHWView(),
                VIEW => 'Hw',
                LABEL => 'SAN & NAS Hardware'
        ),
        array(
                VIEW_LIST => generateUserGuideView(),
                VIEW => 'UserGuide',
                LABEL => 'User Guide'
        )
    );

   // Calls getViewHtml for each View
    foreach ( $allViewLists as $viewList ) {
        getViewHtml( $viewList, $content );
    }

    // End the menu tree
    $content [] = "</div>";

    return implode($content);
}