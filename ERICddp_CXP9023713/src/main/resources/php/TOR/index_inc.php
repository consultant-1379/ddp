<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/indexIncFunctions.php";
require_once PHP_ROOT . "/common/indexFunctions.php";
require_once PHP_ROOT . "/TOR/util.php";

const KPICALCSERV = 'kpicalcserv';
const CONSKPISERV = 'conskpiserv';
const CONSCMEDITOR = 'conscmeditor';
const CONSFM = 'consfm';

// Generate the vcs count table.
function getVCSEventCounts() {
    global $site, $date;
    $where = "enm_vcs_events.siteid = sites.id AND sites.name = '$site'
              AND enm_vcs_events.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              GROUP BY enm_vcs_events.clustertype";

    return SqlTableBuilder::init()
           ->name("vcsevent_count")
           ->tables(array('enm_vcs_events', 'sites'))
           ->where($where)
           ->addSimpleColumn("enm_vcs_events.clustertype", 'Cluster')
           ->addSimpleColumn("COUNT(*)", 'Count')
           ->build();
}

// Checks if the deprecated path is being used and outputs a warning.
function checkIfOldPath() {
    global $datadir;
    if ( file_exists( $datadir. "/TOR/OLD_DDCUPLOAD_PATH" ) ) {
        return '<p><b><font color="red">Warning:</font></b> Use of the path
                <code>/opt/ericsson/ddc/bin/ddcDataUpload</code> is deprecated.</p>
                <p>The link <code>/opt/ericsson/ddc</code> will be removed in a future version of DDC.
                Please update the ddcDataUpload cron to use
                <code>/opt/ericsson/ERICddc/bin/ddcDataUpload</code></p> \n';
    }
}

// Display the ENM Version
function displayVersion() {
    global $statsDB, $date, $site;
    $statsDB->query("
SELECT
    tor_ver_names.name
FROM
    tor_ver,
    tor_ver_names,
    sites
WHERE
    tor_ver.siteid = sites.id AND
    tor_ver.verid = tor_ver_names.id AND
    sites.name = '$site' AND
    tor_ver.date = '$date'");

    if ($statsDB->getNumRows() == 0) {
        return "";
    }
    $row = $statsDB->getNextRow();

    return "<p><b>Version</b>: " . formatTorVersion($row[0], true);
}

// generate<view name>View functions are uesd to generate the individual lists that are used to generate the links,
// using generateLinkList, the results of which are returned.
// If a fucntion becomes too large we can extend it to another function and pass the $dataArray by reference,
// See example in generatePMView2
function generateSystemView( ) {
    global $site, $date, $statsDB, $datadir, $rootdir, $grafanaURL;

    /* Cluster View */
    if ( $statsDB->hasData('enm_cluster_host', 'date', true) ) {
        $dataArray[] = array(QUERY => array(), PATH =>  '/TOR/system/cluster.php',
                             LABEL => 'Cluster View');
    } elseif ( $statsDB->hasData('k8s_node', 'date', true) ) {
        $dataArray[] = array(QUERY => array(), PATH =>  '/k8s/appinst.php',
                             LABEL => 'K8S Applications');
    } else {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/system/servicegroups.php',
                             LABEL => 'Service Groups');
    }

    // Logs
    // In Cloud native, the logs gets processed by the generic make stats
    // and out is written to logs, not enmlogs
    $logdir = 'enmlogs';
    if ( is_dir($rootdir . "/logs")) {
        $logdir = 'logs';
    }
    $query1 = $statsDB->hasDataQuery( 'enm_logs', 'date', true );
    $query2 = $statsDB->hasDataQuery( 'enm_elasticsearch_getlog', 'date', true );
    $dataArray[] = array(
        QUERY => array($query1, $query2),
        PATH => '/TOR/system/elasticsearch.php',
        LABEL => 'Logs',
        OTHER_ARGS => array( 'logdir' => $logdir)
    );

    /* Apache Requests */
    $query = $statsDB->hasDataQuery( 'enm_apache_requests', 'date', true );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/system/apache.php', LABEL => 'Apache Requests' );

    /* Health Check */
    $dataArray[] = array(QUERY => array(), PATH => '/common/hc.php', LABEL => 'Health Status' );

    /* File Search */
    if ( file_exists($datadir) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/common/findfile.php', LABEL => 'File Search' );
    }

    /* mBean Viewer */
    if ( ! file_exists("$datadir/remote_writer") ) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/system/mBeanViewer.php', LABEL => 'mBean Viewer' );
    }

    /* Network Elements */
    $query = $statsDB->hasDataQuery( 'enm_network_element_details', 'date', true );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/system/ne_details.php', LABEL => 'Network Elements' );

    /* Capacity */
    $query = $statsDB->hasDataQuery( 'enm_capacity', 'date', true );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/system/capacity.php', LABEL => 'Capacity' );

    /* Upgrade */
    $query = "
SELECT
    COUNT(*)
FROM
    enm_upgrade_events, enm_upgrade_stage_names, sites
WHERE
    enm_upgrade_events.siteid = sites.id AND sites.name = '$site' AND
    enm_upgrade_events.stageid = enm_upgrade_stage_names.id AND
    enm_upgrade_stage_names.name = 'UPGRADE_ENM' AND enm_upgrade_events.state = 'START' AND
    enm_upgrade_events.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/system/enm_upgrade.php', LABEL => 'Upgrade' );

    /* Helm */
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery('k8s_helm_update', 'end')),
        PATH => '/k8s/helm.php',
        LABEL => 'Helm'
    );

    /* Granfana */
    $grafanaLink = getGrafanaLink();
    if ( ! is_null($grafanaLink) ) {
        $dataArray[] = array(HTML => makeLinkForURL($grafanaLink, "Grafana"));
    }

    /* Backup */
    $query = "
SELECT
    *
FROM
    enm_bur_backup_stage_stats, enm_bur_backup_keywords, sites
WHERE
    enm_bur_backup_stage_stats.siteid = sites.id AND sites.name = '$site' AND
    enm_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_bur_backup_keywords.backup_keyword != '' LIMIT 1";

    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/system/backup_bur.php', LABEL => 'Backup' );

    /* Restore */
    $query = "
SELECT
    *
FROM
    enm_bur_restore_stage_stats, enm_bur_restore_keywords, sites
WHERE
    enm_bur_restore_stage_stats.siteid = sites.id AND sites.name = '$site' AND
    enm_bur_restore_stage_stats.restore_keyword_id = enm_bur_restore_keywords.id AND
    enm_bur_restore_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_bur_restore_keywords.restore_keyword != '' LIMIT 1";

$dataArray[] = array(QUERY => array($query), PATH => '/TOR/restore_bur.php', LABEL => 'Restore',
    OTHER_ARGS => array('page_type' => 'restore_stages') );

    return generateLinkList( $dataArray, false );
}

function generateCMServicesView( $availableServices ) {
    global $site, $date, $statsDB;

    /* CM ACTIVATION Domain Proxy */
    $query = $statsDB->hasDataQuery('enm_domainproxy_instr');
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_domainproxy.php',
                         LABEL => 'Domain Proxy' );

    /* CM ACTIVATION Domain Proxy V2*/
    $query = $statsDB->hasDataQuery('enm_domainproxy_v2_instr');
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_domainproxy_v2.php',
                         LABEL => 'Domain Proxy V2' );

    /* CM ACTIVATION AND HISTORY DETAILS */
    $query = $statsDB->hasDataQuery( 'enm_cm_activation', START );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_activation.php',
                          LABEL => 'Activation & Historical Writer' );

    /* AMOS Commands */
    $query1 = $statsDB->hasDataQuery( 'enm_amos_commands', 'date' );
    $query2 = $statsDB->hasDataQuery('enm_amos_sessions');
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/cm/enm_amos.php', LABEL => 'AMOS Usage' );

    /* OPS */
    $dataArray[] = array(
        QUERY => array( $statsDB->hasDataQuery( 'enm_ops_server' ) ),
        PATH => '/TOR/cm/ops.php',
        LABEL => 'OPS'
    );

    if (isServiceGroupAvailable("autocellmgt", $availableServices)) {
        $sg = "autocellmgt";
    } else {
        $sg = "autoidservice";
    }
    /* Auto Cell ID Node Stats */
    $query = $statsDB->hasDataQuery( 'enm_saidserv_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/autoid.php', LABEL => 'Auto ID',
                         OTHER_ARGS => array( 'SG' => $sg));

    /* Apserv Metrics */
    $query = "
SELECT
    COUNT(*)
FROM
    enm_apserv_metrics, sites
WHERE
    enm_apserv_metrics.view='Metric' AND
    enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
    enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/ap_daily_metrics.php', LABEL => 'Auto Provisioning' );

    /* Bulk CM Import */
    $query = $statsDB->hasDataQuery( 'cm_import', 'job_end' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/cm/cm_imports.php',
        LABEL => 'Bulk CM Import',
        OTHER_ARGS => array( 'type' => 'cli')
    );

    /* Bulk CM Export */
    $query = $statsDB->hasDataQuery( 'cm_export', 'export_end_date_time' );
    $dataArray[] =  array(QUERY => array($query), PATH => '/TOR/cm/cm_exports.php',
                          LABEL => 'Bulk CM Export' );

    /* Bulk CM Import UI */
    $query = $statsDB->hasDataQuery( 'enm_bulk_import_ui' );
    $dataArray[] =  array(
        QUERY => array($query),
        PATH => '/TOR/cm/cm_imports.php',
        LABEL => 'Bulk CM Import UI',
        OTHER_ARGS => array( 'type' => 'ui')
    );

    /* CM REST NBI */
    $query = $statsDB->hasDataQuery( 'enm_impexpserv_instr' );
    $dataArray[] =  array(QUERY => array($query), PATH => '/TOR/cm/impexpserv_rest_nbi.php',
                          LABEL => 'Bulk CM REST NBI' );

    /* Apserv cell management metrics */
    if ( $statsDB->hasData("enm_cm_cell_management") ) {
        $dataArray[] = array(
            QUERY => array($statsDB->hasDataQuery("enm_cm_cell_management")),
            PATH => '/TOR/cm/cellmgmt.php',
            LABEL => 'Cell Management'
        );
    } else {
        $query = "
SELECT
    COUNT(*)
FROM
    enm_apserv_metrics, sites
WHERE
    enm_apserv_metrics.view NOT LIKE 'Metric' AND
    enm_apserv_metrics.siteid = sites.id AND sites.name = '$site' AND
    enm_apserv_metrics.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
        $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/ap_daily_cell_metrics.php',
                             LABEL => 'Cell Management' );
    }

    /* Cm Edit */
    $query1 = $statsDB->hasDataQuery( 'enm_cmserv_cmreader_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_cmserv_cmwriter_instr' );
    $query3 = $statsDB->hasDataQuery( 'enm_cmserv_cmsearchreader_instr' );
    $dataArray[] = array(QUERY => array( $query1, $query2, $query3 ), PATH => '/TOR/cm/cm_edit.php',
                         LABEL => 'CM CLI Usage' );

    /* CM CRUD NBI */
    $query = $statsDB->hasDataQuery( 'enm_cm_crud_nbi' );
    $dataArray[] =  array(QUERY => array($query), PATH => '/TOR/cm/cm_crud_nbi.php', LABEL => 'CM CRUD NBI' );

    /* CM Event NBI */
    $query = $statsDB->hasDataQuery( 'cm_event_nbi_instr' );
    $dataArray[] =  array(QUERY => array($query), PATH => '/TOR/cm/cm_event_nbi.php', LABEL => 'CM Events NBI' );

    /* CM Revocation Stats */
    $query = $statsDB->hasDataQuery( 'enm_cm_revocation_instr', START_TIME );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_revocation.php', LABEL => 'CM Revocation' );

    /* CM Config Stats */
    $query = $statsDB->hasDataQuery( 'enm_cmconfig_logs', START_TIME );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_config.php', LABEL => 'CM Config Copy' );

    /* CM Audit Service */
    $query = $statsDB->hasDataQuery( 'enm_cm_audit_service' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_audit.php', LABEL => 'CM Audit' );

    /* Cmserv NHC Logs */
    $query1 = $statsDB->hasDataQuery( 'enm_nhc_logs' );
    $query2 = $statsDB->hasDataQuery( 'enm_nhc_ac_modifications' );
    $query3 = $statsDB->hasDataQuery( 'enm_cmserv_nhcinstr' );
    $query4 = $statsDB->hasDataQuery( 'enm_mscm_nhcinstr' );
    $dataArray[] =  array(QUERY => array( $query1, $query2, $query3, $query4 ),
                          PATH => '/TOR/cm/cmserv_nhc_activities.php', LABEL => 'NHC' );

    /* CM Node and Cell Reparenting*/
    $query = $statsDB->hasDataQuery( 'enm_cm_resource_requests' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/cm/cm_node_and_cell_reparenting.php',
        LABEL => 'Node and Cell Reparenting'
    );

    /* Network Explorer */
    $query1 = $statsDB->hasDataQuery( 'enm_netexserv_topologysearch_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_netexserv_topologycollection_instr' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/cm/netex.php', LABEL => 'Network Explorer' );

    /* NSM Instrumentation */
    $query = $statsDB->hasDataQuery( 'enm_nsm_instr' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/cm/cm_nsm_instr.php',
        LABEL => 'NSM'
    );

    /* RELEASE INDEPENDENCE */
    $query1 = $statsDB->hasDataQuery( 'enm_cmconfig_services_logs' );
    $query2 = $statsDB->hasDataQuery( 'enm_cmconfig_support_logs' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/cm/discoveredNodeVersions.php',
                         LABEL => 'Release Independence' );

    /* TransportCimNormalization */
    $query1 = $statsDB->hasDataQuery( 'cm_transportcimnormalization_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_node_tcim_normalization' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/cm/transportCimNormalization.php',
                         LABEL => 'TransportCimNormalization' );

    /*Physical Link Management*/
    $query = $statsDB->hasDataQuery( 'enm_plms_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/plm_stats.php', LABEL => 'Physical Link Management' );

    /*Bulk Node CLI */
    $query = $statsDB->hasDataQuery( 'enm_bulknode_cli_logs' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/enm_bulknode_cli.php', LABEL => 'Bulk Node CLI' );

    /*Topology Relation Service*/
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery("enm_trs_relreq")),
        PATH => '/TOR/cm/trs.php',
        LABEL => 'Topology Relation Service'
    );

    /*Parameter Management*/
    $dataArray[] = array(
        QUERY => array(
            $statsDB->hasDataQuery("enm_parammgt_genimport"),
            $statsDB->hasDataQuery("enm_parammgt_gencsv")
        ),
        PATH => '/TOR/cm/parammgt.php',
        LABEL => 'Parameter Management'
    );

    /*Configuration Templates*/
    if (isServiceGroupAvailable('cmutilities', $availableServices) ||
        isServiceGroupAvailable(CONSCMEDITOR, $availableServices) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/cm/cm_templates.php', LABEL => 'Configuration Templates' );
    }

    /* Network Viewer Service */
    if (isServiceGroupAvailable('networkexplorer', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/cm/cm_network_viewer_service.php',
                             LABEL => 'Network Viewer Service' );
    }

    /* WINFOIL Services */
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery("enm_winfiol_services")),
        PATH => '/TOR/cm/winfiol_service.php',
        LABEL => 'WinFIOL'
    );

    /*CM Subscribed Events NBI*/
    $query1 = $statsDB->hasDataQuery( 'enm_cm_subscriptions_nbi' );
    $query2 = $statsDB->hasDataQuery( 'enm_cm_subscribed_events_nbi' );
    $dataArray[] = array(
        QUERY => array($query1, $query2),
        PATH => '/TOR/cm/subscribed_events_nbi.php',
        LABEL => 'CM Subscribed Events NBI'
    );

    /* Element Manager Usage */
    $query = $statsDB->hasDataQuery( 'enm_cm_element_manager_usage' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/cm/element_manager_usage.php',
        LABEL => 'Element Manager Usage'
    );

    /*RESTCONF NBI*/
    $query = $statsDB->hasDataQuery( 'enm_cm_restconf_nbi' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/cm/cm_restconf_nbi.php', LABEL => 'Restconf NBI' );

    /* SITE ENERGY VISUALIZATION */
    $query = $statsDB->hasDataQuery( 'enm_cm_site_energy_visualization_instr' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/cm/site_energy_visualization.php',
        LABEL => 'Site Energy Visualization'
    );

    return generateLinkList( $dataArray );
}

function generateCMMediationView( $availableServices ) {
    global $site, $date, $statsDB;

    /* COM/ECIM Mediation */
    if ( isServiceGroupAvailable('comecimmscm', $availableServices) ) {
        $dataArray[] =  array(
            QUERY => array(),
            PATH => '/TOR/cm/ecim_med.php',
            LABEL => 'COM/ECIM Mediation',
            OTHER_ARGS => array(
                SERVICE_GROUP => 'comecimmscm'
            )
        );
    }

    /* COM/APG Mediation*/
    if ( isServiceGroupAvailable('mscmapg', $availableServices) ) {
        $dataArray[] =  array(
            QUERY => array(),
            PATH => '/TOR/cm/ecim_med.php',
            LABEL => 'COM/APG Mediation',
            OTHER_ARGS => array(
                SERVICE_GROUP => 'mscmapg'
            )
        );
    }

    /* CM Mediation */
    $query = $statsDB->hasDataQuery( 'enm_cm_med_instr' );
    if ( isServiceGroupAvailable(CONSCMEDITOR, $availableServices)) {
        $sg = CONSCMEDITOR;
    }  else {
        $sg = 'cmservice';
    }
    $dataArray[] =  array(QUERY => array($query), PATH => '/TOR/cm/cm_med.php', LABEL => 'CPP CM Mediation',
                          OTHER_ARGS => array( 'SG' => $sg ) );

    /* IPSMSERV IP Node Stats */
    $query1 = $statsDB->hasDataQuery( 'enm_ipsmserv_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_iptrnsprt_notifrec', 'date', true );
    $query3 = $statsDB->hasDataQuery( 'enm_mscmip_syncs_stats', 'start' );

    $dataArray[] = array(
        QUERY => array( $query1, $query2, $query3 ),
        PATH => '/TOR/cm/ip_transport_stats.php',
        LABEL => 'IP Transport'
    );

    /* SNMPCM Mediation */
    $query1 = $statsDB->hasDataQuery( 'enm_mssnmpcm_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_minilink_cmsync' );
    $query3 = $statsDB->hasDataQuery( 'enm_stn_cmsync' );

    $dataArray[] =
        array(
            QUERY => array( $query1, $query2, $query3 ),
            PATH => '/TOR/cm/mssnmpcm.php',
            LABEL => 'SNMP Mediation'
        );

    /* DomainProxy Mediation */
    $query1 = $statsDB->hasDataQuery( 'enm_dpmediation_sas_instr' );
    $srv = enmGetServiceInstances( $statsDB, $site, $date, "dpmediation");
    if ( !empty($srv) ) {
        $dataArray[] = array(QUERY => array($query1), PATH => '/TOR/cm/cm_dp_med.php',
                             LABEL => 'DomainProxy Mediation' );
    }

    return generateLinkList( $dataArray );
}

function generateNCMView() {
    global $statsDB;

    /* NCM */
    $query1 = $statsDB->hasDataQuery( 'enm_ncmagent_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_ncm_nodes_list_realignment' );
    $query3 = $statsDB->hasDataQuery( 'enm_ncm_node_realignment' );
    $query4 = $statsDB->hasDataQuery( 'enm_ncm_session' );
    $query5 = $statsDB->hasDataQuery( 'enm_ncm_links_realignment' );
    $query6 = $statsDB->hasDataQuery( 'enm_ncmagent_messageMbean_instr' );

    $dataArray[] = array(
        QUERY => array( $query1, $query2, $query3, $query4, $query5 ),
        PATH => '/TOR/ncm/ncm.php',
        LABEL => 'MEF Service Discovery',
        OTHER_ARGS => array('flow' => 'MEFServiceDiscovery')
    );

    /* Node CLI */
    $dataArray[] = array(
        QUERY => array( $query1 ),
        PATH => '/TOR/ncm/nodeCli.php',
        LABEL => 'Node CLI'
    );

    /* CLI Messages */
    $dataArray[] = array(
        QUERY => array( $query6 ),
        PATH => '/TOR/ncm/ncm_cliMessages.php',
        LABEL => 'CLI Messages'
    );

    /* MEF Service LCM */
    $query = $statsDB->hasDataQuery( 'enm_ncm_mef_service_lcm' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/ncm/mef_service_lcm.php',
        LABEL => 'MEF Service LCM'
    );

    return generateLinkList( $dataArray );
}

function generatePMView($availableServices) {
    global $site, $date, $statsDB;

    /* generatePMView is now full (cognitive complexity of 15) */
    /* Please use generatePMView2 for all new links */

    /* PMServ */
    $query1 = $statsDB->hasDataQuery( 'enm_pmic_rop', 'fcs' );
    $query2 = $statsDB->hasDataQuery( 'enm_pmic_filecollection' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/pm/pmserv.php', LABEL => 'PMServ' );

    /* MSPM */
    if (isServiceGroupAvailable('mspm', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/pm/mspm.php', LABEL => 'MSPM' );
    }

    /* PM Push Mediation */
    $query1 = $statsDB->hasDataQuery( 'enm_smrsaudit_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_mspmip_instr' );
    $query3 = "
SELECT
    COUNT(*)
FROM
    enm_pmic_rop_fls, sites
WHERE
    enm_pmic_rop_fls.siteid = sites.id AND sites.name = '$site' AND
    enm_pmic_rop_fls.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND transfertype=";
    $query3PUSH = $query3 . "'PUSH'";
    $dataArray[] = array(QUERY => array($query1,$query2,$query3PUSH), PATH => '/TOR/pm/pm_push_audit.php',
                         LABEL => 'PM Push Mediation', OTHER_ARGS => array('transfertype' => 'PUSH') );

    $query3GENERATION = $query3 . "'GENERATION'";
    $dataArray[] = array(QUERY => array($query1,$query2,$query3GENERATION), PATH => '/TOR/pm/pm_push_audit.php',
                         LABEL => 'PM Generation Mediation', OTHER_ARGS => array('transfertype' => 'GENERATION') );

    /* MSPMIP */
    if (isServiceGroupAvailable('mspmip', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/pm/mspmip.php', LABEL => 'MSPMIP');
    }

    /* MSKPIRT */
    if (isServiceGroupAvailable('mskpirt', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/pm/mskpirt.php', LABEL => 'MSKPIRT' );
    }

    /* KPI Mediation */
    if ( isServiceGroupAvailable(CONSKPISERV, $availableServices ) ) {
        $dataArray[] =
            array(
                QUERY => array(),
                PATH => '/TOR/pm/kpiserv.php',
                LABEL => 'NHM',
                OTHER_ARGS => array( 'kpiserv' => CONSKPISERV, KPICALCSERV => CONSKPISERV)
            );
    } elseif ( isServiceGroupAvailable( 'kpiservice', $availableServices ) ) {
        $dataArray[] =
            array(
                QUERY => array(),
                PATH => '/TOR/pm/kpiserv.php',
                LABEL => 'NHM',
                OTHER_ARGS => array( 'kpiserv' => 'kpiservice', KPICALCSERV => KPICALCSERV)
            );
    }

    /* EBSM Instrumentation */
    $query1 = $statsDB->hasDataQuery( 'enm_ebsm_inst_stats' );
    $query2 = $statsDB->hasDataQuery( 'enm_pmic_notification' );
    $dataArray[] = array(QUERY => array($query1, $query2), PATH => '/TOR/pm/ebsm_statistics.php',
                         LABEL => 'Event Based Statistics(File)' );

    /* ULSA Spectrum Analyser */
    $query = $statsDB->hasDataQuery( 'enm_ulsa_spectrum_analyser_logs' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/pm/pm_uplink_spectrum_analyser.php',
                         LABEL => 'Uplink Spectrum Analyser' );

    /* Uplink Spectrum File Collection */
    $query1 = $statsDB->hasDataQuery( 'enm_pmserv_uplink_instr' );
    $query2 = $statsDB->hasDataQuery( 'pm_uplink_errored_res', 'date', true );
    $query3 = $statsDB->hasDataQuery( 'pm_uplink_errors', 'date', true );
    $dataArray[] = array(QUERY => array($query1, $query2, $query3), PATH => '/TOR/pm/pm_uplink_spectrum_statistics.php',
                         LABEL => 'Uplink Spectrum File Collection' );


    /* FNT / Push Service*/
    $query1 = $statsDB->hasDataQuery( 'enm_fnt_push_service' );
    $query2 = $statsDB->hasDataQuery( 'enm_fnt_product_data' );
    $dataArray[] = array(
        QUERY => array($query1, $query2),
        PATH => '/TOR/pm/fnt_push_service.php',
        LABEL => 'FNT / Push Service'
    );

    /* Stream Termination */
    $msstr = isServiceGroupAvailable('msstr', $availableServices );
    $esnmediationdef = isServiceGroupAvailable('esnmediationdef', $availableServices );
    $eventparserdef = isServiceGroupAvailable('eventparserdef', $availableServices );
    if ($msstr || $esnmediationdef || $eventparserdef) {
        $dataArray[] = array(
                           QUERY => array(),
                           PATH => '/TOR/pm/streaming.php',
                           LABEL => 'Stream Termination and Parsing'
                       );
    }

    /*EBA Stream Termination */
    $ebamsstr = isServiceGroupAvailable('ebastreamterminator', $availableServices );
    $ebaeventparser = isServiceGroupAvailable('ebaeventparser', $availableServices );
    if ($ebamsstr || $ebaeventparser) {
        $dataArray[] = array(QUERY => array(),
                             PATH => '/TOR/pm/eba_streaming.php', LABEL => 'EBA Stream Termination and Parsing' );
    }

    /* ESN */
    $esnforwarderdecodeddef = isServiceGroupAvailable('esnforwarderdecodeddef', $availableServices);
    $asrlforwarderdef = isServiceGroupAvailable('asrlforwardeedef', $availableServices);
    if ($esnforwarderdecodeddef || $asrlforwarderdef) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/pm/streaming_fwd.php', LABEL => 'ESN',
                             OTHER_ARGS => array('sg' => 'esnforwarderdecodeddef') );
    }

    /* ASR */
    $query1 = $statsDB->hasDataQuery( 'enm_str_asrl' );
    $query2 = $statsDB->hasDataQuery( 'enm_str_asrl_spark' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/pm/asrl.php', LABEL => 'ASR' );

    /* EBS-L Stream Instrumentation */
    $query1 = $statsDB->hasDataQuery( 'ebsm_stream_logs' );
    $query2 = $statsDB->hasDataQuery( 'enm_ebsmstream_instr' );
    $dataArray[] = array(QUERY => array( $query1, $query2 ), PATH => '/TOR/pm/ebsm_stream_statistics.php',
                         LABEL => 'Event Based Statistics(Stream)' );

    /* AIM */
    $fm = isServiceGroupAvailable('imfmalarmtransformer', $availableServices);
    $group = isServiceGroupAvailable('imgroupingservice', $availableServices);
    $know = isServiceGroupAvailable('imknowledgebaseservice', $availableServices);
    $anom = isServiceGroupAvailable('imkpianomalydetection', $availableServices);
    $life = isServiceGroupAvailable('imlifecycleservice', $availableServices);
    $showAim = false;
    if ( $fm || $group || $know ) {
        $showAim = true;
    }
    if ( $showAim || $anom || $life ) {
        $dataArray[] = array( QUERY => array(), PATH => '/TOR/pm/enm_aim_main.php', LABEL => 'AIM' );
    }

    /* FCM */
    $query1 = $statsDB->hasDataQuery( 'enm_flexible_controller' );
    $dataArray[] = array( QUERY => array( $query1 ), PATH => '/TOR/pm/fcm.php', LABEL => 'FCM' );

    /* generatePMView is now full (cognitive complexity of 15) */
    /* Please use generatePMView2 for all new links */

    generatePMView2($availableServices, $dataArray);

    return generateLinkList( $dataArray );
}

function generatePMView2($availableServices, &$dataArray) {
    global $site, $date, $statsDB;

    /*PM File Access NBI*/
    if ( isServiceGroupAvailable('fileaccessnbi', $availableServices) ) {
        $dataArray[] = array( QUERY => array(), PATH => '/TOR/pm/pm_nbi.php', LABEL => 'PM File Access NBI' );
    }

    /* PMIC CRUD NBI */
    $query = $statsDB->hasDataQuery( 'enm_pmic_rest_nbi' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/pm/pmic_rest_nbi.php', LABEL => 'PMIC CRUD NBI' );
}

function generateSHMView( $availableServices ) {
    global $site, $date, $statsDB;

    /* LCM */
    if (isServiceGroupAvailable("conslicensemgt", $availableServices)) {
        $sg = "conslicensemgt";
    } else {
        $sg = "lcmservice";
    }
    $query = $statsDB->hasDataQuery( 'enm_lcmserv_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/shm/lcm_statistics.php', LABEL => 'LCM',
                         OTHER_ARGS => array( 'SG' => $sg));

    /* SHM Inventory Mediation */
    $query = $statsDB->hasDataQuery( 'shm_inventorymediation_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/shm/shm_inventorymediation_stats.php',
                         LABEL => 'SHM Inventory' );

    /* MINILINK Inventory Mediation */
    if (isServiceGroupAvailable('mssnmpcm', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/shm/minilink_inventorymediation_stats.php',
                             LABEL => 'MINI-LINK Inventory' );
    }

    /* SHMCORESERV Job Instrumentation */
    if ( isServiceGroupAvailable("shmcoreservice", $availableServices) ) {
        $dataArray[] = array(
            QUERY => array(),
            PATH => '/TOR/shm/shmcoreserv_job_instrumentation.php',
            LABEL => 'Job details'
        );
    }
    /* SHM AXE Instrumentation */
    $query = $statsDB->hasDataQuery( 'enm_shm_axe_inventory' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/shm/shm_axe_inventory.php',
                         LABEL => 'SHM AXE Inventory' );

    /* SHM IMPORT LOGS*/
    $query1 = $statsDB->hasDataQuery( 'enm_shm_import_software_package_log' );
    $query2 = $statsDB->hasDataQuery( 'enm_shm_metadatafilecount_log' );
    $query3 = $statsDB->hasDataQuery( 'enm_shm_releasenotecount_log' );
    $dataArray[] = array(QUERY => array($query1, $query2, $query3),
                         PATH => '/TOR/shm/shm_software_import.php',
                         LABEL => 'SHM Software Import' );

    /* SHM REST NBI*/
    $query1 = $statsDB->hasDataQuery( 'enm_shm_nbi_rest_job' );
    $query2 = $statsDB->hasDataQuery( 'enm_shm_nbi_rest_backup' );
    $dataArray[] = array(QUERY => array($query1, $query2),
                         PATH => '/TOR/shm/shm_rest_nbi.php',
                         LABEL => 'SHM REST NBI' );

    return generateLinkList( $dataArray );
}

function generateFMView( $availableServices ) {
    global $site, $date, $statsDB;

    /* FM Alarm Processing Stats */
    if (isServiceGroupAvailable(CONSFM, $availableServices)) {
        $fmalarmprocessing = CONSFM;
        $fmhistory = CONSFM;
    } else {
         $fmalarmprocessing = 'fmalarmprocessing';
         $fmhistory = 'fmhistory';
    }
    $query1 = $statsDB->hasDataQuery( 'fm_alarmprocessing_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_fmack' );
    $query3 = $statsDB->hasDataQuery( 'enm_openalarms' );
    $query4 = $statsDB->hasDataQuery( 'enm_fmfmx_instr' );
    $dataArray[] = array(
                         QUERY => array($query1, $query2, $query3, $query4),
                         PATH => '/TOR/fm/fm_alarmprocessing.php',
                         LABEL => 'FM',
                         OTHER_ARGS => array('fmalarmprocessing' => $fmalarmprocessing, 'fmhistory' => $fmhistory)
                   );

    /* FM CPP */
    $query1 = $statsDB->hasDataQuery( 'enm_msfm_instr' );
    $dataArray[] = array(QUERY => array($query1), PATH => '/TOR/fm/fm_cpp.php', LABEL => 'FM CPP');

    /* Netlog statistics */
    $query1 = $statsDB->hasDataQuery( 'enm_msnetlog_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_fmservnetlog_instr' );
    $dataArray[] = array(QUERY => array($query1, $query2), PATH => '/TOR/fm/net_log_stats.php', LABEL => 'Netlog' );

    /* FM BNSI */
    $query = $statsDB->hasDataQuery( 'fm_bnsi_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/fm/fmbnsi_stats.php', LABEL => 'FM BNSI' );


    /* FM SDK */
    $instances = getInstances( 'enm_mssnmpfm_instr' );
    $sids = getServIdsFromArray( $instances );
    $sgs = getSgsFromServIdsArray( $sids );

    foreach ( $sgs as $sg ) {
        if ( $sg == 'mssnmpfm' || $sg == 'msapgfm' ) {
            $lbl = "FM SNMP($sg)";
        } else {
            $lbl = "FM SDK($sg)";
        }
        $query = $statsDB->hasDataQuery( 'enm_mssnmpfm_instr' );
        $dataArray[] = array(
            QUERY => array($query),
            PATH => '/TOR/fm/fm_sdk.php',
            LABEL => $lbl,
            OTHER_ARGS => array(
                SERVICE_GROUP => $sg
            )
        );
    }

    /* FMX Engine Metrics */
    $query1 = $statsDB->hasDataQuery( 'enm_fmx_monitor', START_TIME );
    $query2 = $statsDB->hasDataQuery( 'enm_fmx_monitor', START_TIME );
    $dataArray[] = array(QUERY => array($query1, $query2), PATH => '/TOR/fm/fmx_engine_metrics.php', LABEL => 'FMX' );

    /* FM NBI */
    $query = $statsDB->hasDataQuery( 'enm_fmnbalarm_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/fm/fm_nbi_stats.php', LABEL => 'FM CORBA NBI');

    /* FM BSC */
    $query = $statsDB->hasDataQuery( 'enm_fm_bsc_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/fm/fm_bsc_stats.php', LABEL => 'FM AXE' );

    /* FM SNMP NBI */
    $query = $statsDB->hasDataQuery( 'enm_fmsnmpnbi_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/fm/fm_snmpnbi_stats.php', LABEL => 'FM SNMP NBI' );

    /* FM SNMP OSS MS Alarms */
    if (isServiceGroupAvailable('msosssnmpfm', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/fm/fm_eci_stats.php',
                             LABEL => 'FM SNMP OSS MS Alarms' );
    }

    /* FM EMERGENCY */
    if (isServiceGroupAvailable('fmemergency', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/fm/fm_emergency.php',
                             LABEL => 'FM Emergency' );
    }

    /* FM O1 */
    $query = $statsDB->hasDataQuery( 'enm_fm_handler_statistics' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/fm/fm_handler_stats.php', LABEL => 'FM O1' );

    return generateLinkList( $dataArray );
}

function generateSecurityView( $availableServices ) {
    global $site, $date, $statsDB;

    /* Node Security */
    $query1 = $statsDB->hasDataQuery( 'enm_secserv_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_radionode_filetransfer', 'date', true );
    $dataArray[] = array(QUERY => array($query1, $query2), PATH => '/TOR/security/security_stats.php',
                         LABEL => 'Node Security' );

    /* COM AA Access Control */
    $query = $statsDB->hasDataQuery( 'enm_secserv_comaa_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/security_comaa_stats.php',
                         LABEL => 'COM AA Access Control' );

    /* Node Security Jobs Statistics */
    $query1 = $statsDB->hasDataQuery( 'enm_nsj_statistics' );
    $dataArray[] = array(QUERY => array($query1), PATH => '/TOR/security/node_security.php',
                         LABEL => 'Node Security Jobs Statistics' );

    /* Network Privileged Access Management */
    $query1 = $statsDB->hasDataQuery( 'enm_npam_job_details' );
    $query2 = $statsDB->hasDataQuery( 'enm_npam_instr' );
    $dataArray[] = array(
        QUERY => array($query1, $query2),
        PATH => '/TOR/security/npam.php',
        LABEL => 'Network Privileged Access Management'
    );

    /* Node Security Command Handler Statistics */
    $query1 = $statsDB->hasDataQuery( 'enm_cmd_handler_statistics' );
    $dataArray[] = array(
        QUERY => array($query1),
        PATH => '/TOR/security/cmd_handler.php',
        LABEL => 'Node Security Command Handler Statistics'
    );

    /* COM AA External LDAP */
    $query = $statsDB->hasDataQuery( 'enm_secserv_comaaExtIdp_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/security_comaaExtIdp_stats.php',
                         LABEL => 'COM AA External LDAP' );

    /* PKI Security */
    $query = $statsDB->hasDataQuery( 'enm_spsserv_entity_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/pki_security.php', LABEL => 'PKI Security' );

    /* Single Logon Service */
    $query = $statsDB->hasDataQuery( 'enm_secserv_sls_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/single_log_service.php',
                         LABEL => 'Single Logon Service' );

    /* SSO OpenAM */
    $query1 = $statsDB->hasDataQuery( 'enm_secserv_sls_instr' );
    $query2 = $statsDB->hasDataQuery( 'enm_open_am_authorization' );
    $dataArray[] = array(QUERY => array($query1, $query2), PATH => '/TOR/security/openam.php', LABEL => 'SSO OpenAM' );

    /* Proxy Account Statistics */
    $query = $statsDB->hasDataQuery( 'enm_proxy_statistics' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/proxy_statistics.php',
                         LABEL => 'Proxy Account Statistics' );

    /* Eniq applications */
    $query = $statsDB->hasDataQuery( 'enm_secserv_sso_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/eniq_applications.php',
                         LABEL => 'Eniq Applications' );

    /* SSO TOKEN */
    if (isServiceGroupAvailable('sso', $availableServices)) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/security/token_validation.php',
                             LABEL => 'SSO Token validation' );
    }

    /*  Federated Identity Synchronizer */
    $query = $statsDB->hasDataQuery( 'enm_fidm_syncronizer' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/security/federated_identity_sync.php',
                                 LABEL => 'Federated Identity Synchronizer' );

    return generateLinkList( $dataArray );
}

function generateRVView( ) {
    global $site, $date, $statsDB;

    /* ENM workload profile log */
    $query = $statsDB->hasDataQuery( 'enm_profilelog', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/rv/workload.php', LABEL => 'Workload' );

    /* NetSim */
    $query = "
SELECT
    COUNT(*) FROM servers, sites, servercfg
WHERE
    servers.siteid = sites.id AND sites.name = '$site' AND
    servers.id = servercfg.serverid AND
    servercfg.date = '$date' AND
    servers.type = 'NETSIM'";
    $dataArray[] = array(QUERY => array($query), PATH => '/netsim/netsim.php', LABEL => 'NetSim' );

    /* NetSim Network Information */
    $query = "
SELECT
    COUNT(*)
FROM
    netsim_network_stats, sites
WHERE
    netsim_network_stats.siteid = sites.id AND
    sites.name = '$site' AND
    netsim_network_stats.date = '$date'";
    $dataArray[] = array(QUERY => array($query), PATH => '/netsim/netsim_network_info.php',
                         LABEL => 'NetSim Network Information' );

    /* Generic Measurements used by system test to record their own measurements */
    $query = $statsDB->hasDataQuery( 'gen_measurements' );
    $dataArray[] = array(QUERY => array($query), PATH => '/generic_measurements.php',
                         LABEL => 'DDP Generic Measurements' );

    return generateLinkList( $dataArray );
}

function generatePlatformView($availableServices) {
    global $site, $date, $statsDB, $webargs, $rootdir;

    /* JMS */
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery( 'enm_jmstopic' )),
        PATH => '/TOR/platform/jms.php',
        LABEL => 'JMS (Queues & Topics)'
    );

    /* Routes */
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery( 'enm_route_instr' )),
        PATH => '/TOR/platform/routes.php',
        LABEL => 'Routes'
    );

    /* DPS Clients */
    $queries = array(
        $statsDB->hasDataQuery( 'enm_dps_instr' ),
        $statsDB->hasDataQuery( 'enm_dps_neo4j_client_connection_pool' ),
        $statsDB->hasDataQuery( 'enm_dps_neo4jtx' )
    );
    $dataArray[] = array(
        QUERY => $queries,
        PATH => '/TOR/dps.php',
        LABEL => 'DPS Clients'
    );

    /* JGroups */
    $query = $statsDB->hasDataQuery( 'enm_jgroup_udp_stats' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/jgroups.php', LABEL => 'JGroups' );

    /* LVS/NAT */
    $query = $statsDB->hasDataQuery( 'enm_lvs_viphost' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/lvs.php', LABEL => 'LVS/NAT' );

    /* MODELING USAGE Instrumentation */
    $queries = array(
        $statsDB->hasDataQuery( 'enm_shmmodeling_instr' ),
        $statsDB->hasDataQuery( 'enm_mdt_execution' ),
        $statsDB->hasDataQuery( 'enm_ned_tmi' ),
        $statsDB->hasDataQuery( 'enm_ned_swsync' )
    );
    $dataArray[] = array(QUERY => $queries, PATH => '/TOR/platform/modelling.php', LABEL => 'Modelling' );

    /* Mediation Fwk */
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery('enm_eventbasedclient' )),
        PATH => '/TOR/platform/mediationfwk.php',
        LABEL => 'Mediation Framework'
    );

    /* CMSERV CLI Statistics */
    $query = $statsDB->hasDataQuery( 'cmserv_clistatistics_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/cmserv_cli_statistics.php',
                         LABEL => 'CLI scripting support' );

    /* Web Push Stats */
    $query = $statsDB->hasDataQuery( 'enm_wpserv_instr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/wpserv.php', LABEL => 'Web Push' );

    /* Ingress Controller Traffic */
    $query1 = $statsDB->hasDataQuery( 'enm_ingress_controller_traffic' );
    $query2 = $statsDB->hasDataQuery( 'nginx_requests' );
    $dataArray[] = array(
        QUERY => array($query1, $query2),
        PATH => '/common/ui_traffic.php',
        LABEL => 'UI/L7 Ingress Traffic'
    );

    /* Generic JMX Beans */
    $dataArray[] = array(
        QUERY => array($statsDB->hasDataQuery('generic_jmx_stats')),
        PATH => '/common/jvms.php',
        LABEL => 'JVM Stats'
    );

    /* Elasticsearch */
    if ( $statsDB->hasData( 'elasticsearch_tp', 'time', false, "elasticsearch_tp.servicetype IN ('eshistory', 'elasticsearch')" ) ) {
        foreach ( array('elasticsearch', 'eshistory') as $servicetype) {
            /* Now figure out if this is cENM, i.e. if we have multiple ElasticSearch instances */
            $statsDB->query("
SELECT DISTINCT elasticsearch_tp.serverid, servers.hostname
FROM elasticsearch_tp
JOIN sites ON elasticsearch_tp.siteid = sites.id
JOIN servers ON elasticsearch_tp.serverid = servers.id
WHERE
 sites.name = '$site' AND
 elasticsearch_tp.time BETWEEN '$date 00:00:00' AND
 '$date 23:59:59' AND servicetype = '$servicetype'");
            $link = ucfirst($servicetype);
            if ( $statsDB->getNumRows() > 1 ) {
                $esList = array();
                while ( $row = $statsDB->getNextRow() ) {
                    $esList[] = array(
                        QUERY => array(),
                        PATH => '/common/platform/elasticsearch/elasticsearch_stats.php',
                        LABEL => $row[1],
                        OTHER_ARGS => array( 'serverid' => $row[0], 'servicetype' => $servicetype )
                    );
                }
                $dataArray[] = array(HTML => $link . makeHTMLList(generateLinkList($esList)));
            } else {
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/common/platform/elasticsearch/elasticsearch_stats.php',
                    LABEL => $link,
                    OTHER_ARGS => array('servicetype' => $servicetype)
                );
            }
        }
    }

    /* Workflow Log Analysis */

    $query = $statsDB->hasData( 'enm_vnflaf_wfexec', START );
    if ( $query ) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/platform/vnflaf_workflows.php',
                             LABEL => 'Workflow Log Analysis' );
    }

    /* Resource Usage Summary */
    if ( file_exists($rootdir . "/resource_usage/") ) {
        $query = $statsDB->hasDataQuery( 'enm_servicegroup_instances', 'date' );
        $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/resource_usage_summary.php',
                             LABEL => 'Resource Usage Summary', OTHER_ARGS => array('showjsplot' => '0') );
    }

    /* ESXI Analysis */
    $query = $statsDB->hasDataQuery( 'enm_esxi_metrics' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/EsxiStat.php', LABEL => 'ESXI' );

    /* SFTP Connections */
    $query = $statsDB->hasDataQuery( 'enm_smrs_log_stats' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/smrs/smrs_sftp_connections.php',
                         LABEL => 'FTP Connections' );

    $query = $statsDB->hasDataQuery( 'enm_geo_kpi_logs' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/enm_geo_kpi.php',
                         LABEL => 'GEO-R' );

    /* Flow Automation */
    if ( isServiceGroupAvailable('flowautomation', $availableServices) ) {
        $dataArray[] = array(QUERY => array(), PATH => '/TOR/platform/enm_flowautomation.php',
                             LABEL => 'Flow Automation' );
    }

    /* File Transfer */
    $query = $statsDB->hasDataQuery( 'enm_filetransfer_connections' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/platform/enm_filetransfer.php',
        LABEL => 'File Transfer'
    );

    /* SD Assets Reuses */
    $query = $statsDB->hasDataQuery( 'enm_sd_assets' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/platform/sd_assets.php',
        LABEL => 'SD Assets Reuses'
    );

    /* ESM Alert Definitions */
    $query = $statsDB->hasDataQuery( 'esm_alert_def', 'date' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/esm_alert_definitions.php',
                         LABEL => 'ESMON' );

    if ( file_exists($rootdir . "/k8s/k8s_events.json") ) {
        $dataArray[] = array(QUERY => array(), PATH => '/k8s/events.php', LABEL => 'K8S Events' );
    }

    /* Openstack API Count  */
    $query = $statsDB->hasDataQuery( 'enm_api_counters' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/platform/enm_api_counters.php',
                         LABEL => 'Openstack API Count' );

    /* vENM HA Log Analysis */
    $query1 = $statsDB->hasDataQuery( 'enm_sam_server_failure_report' );
    $query2 = $statsDB->hasDataQuery( 'enm_consul_n_sam_events' );
    $query3 = $statsDB->hasDataQuery( 'enm_vnflaf_wfexec', 'end' );
    $dataArray[] = array(
        QUERY => array($query1, $query2, $query3),
        PATH => '/TOR/platform/enm_ha_loganalysis.php',
        LABEL => 'vENM HA Log Analysis'
    );

    /* Infrastructure Monitor */
    $query = $statsDB->hasDataQuery( 'enm_infrastructure_monitor' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/TOR/platform/infra_monitor.php',
        LABEL => 'Infrastructure Monitor'
    );

    /* Log Transformer */
    $query = $statsDB->hasDataQuery( 'logtransformer' );
    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/common/platform/logtransformer.php',
        LABEL => 'Log Transformer'
    );
    return generateLinkList( $dataArray );
}

function generateDBView($availableServices) {
    global $site, $date, $statsDB;

   /* Versant Databases */
  $statsDB->query("
SELECT
    DISTINCT(vdb_names.id), vdb_names.name
FROM
    vdb_names, vdb, sites
WHERE
    vdb.date = '$date' AND
    vdb.siteid = sites.id AND sites.name = '$site' AND
    vdb.vdbid = vdb_names.id");
    while ( $versantDatabaseList = $statsDB->getNextRow() ) {
        $dataArray[] = array(QUERY => array(), PATH => '/csdb.php', LABEL => 'Versant',
                             OTHER_ARGS => array( 'vdbid' => $versantDatabaseList[0],
                                                  'vdbname' => $versantDatabaseList[1] )
                       );
    }

    /* neo4j */
    $query = $statsDB->hasDataQuery( 'enm_neo4j_srv' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/databases/neo4j.php', LABEL => 'Neo4j' );

    /* Postgres */
    if ( $statsDB->hasData('enm_postgres_stats_db') ) {
        /* Now figure out if this is cENM, i.e. if we have multiple Postgres instances */
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
                $dataArray[] = array(
                    QUERY => array(),
                    PATH => '/TOR/databases/postgres.php',
                    LABEL => 'Postgres: ' . $row[1],
                    OTHER_ARGS => array( 'serverid' => $row[0] )
                );
            }
        } else {
            $dataArray[] = array(QUERY => array(), PATH => '/TOR/databases/postgres.php', LABEL => 'Postgres' );
        }
    }

    /* Solr */
    $query = $statsDB->hasDataQuery( 'enm_solr' );
    $dataArray[] = array(QUERY => array($query), PATH => '/TOR/databases/solr.php', LABEL => 'Solr' );

    /* OpenDJ */
    $query = $statsDB->hasDataQuery( 'opendj_ldap_stats' );
    $otherArgs = array( 'type' => 'opendj' );

    $dataArray[] = array(
        QUERY => array($query),
        PATH => '/opendj_ldap.php',
        LABEL => 'OpenDJ LDAP Data',
        OTHER_ARGS => $otherArgs
    );

    if ( isServiceGroupAvailable('cts', $availableServices) ) {
        $otherArgs = array( 'type' => 'cts' );
        $dataArray[] = array(
            QUERY => array($query),
            PATH => '/opendj_ldap.php',
            LABEL => 'CTS LDAP Data',
            OTHER_ARGS => $otherArgs
        );
    }

    return generateLinkList( $dataArray );
}

function generateHWView( ) {
    global $site, $date, $statsDB;

    /* EMC Storage */
    $statsDB->query("
SELECT DISTINCT emc_sys.name, emc_sys.id
FROM emc_sys
JOIN emc_site ON emc_sys.id = emc_site.sysid
JOIN sites ON emc_site.siteid = sites.id
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

    /* Virtual Connect */
    $query = $statsDB->hasDataQuery( 'virtualconnect', 'date', true );
    $dataArray[] = array(QUERY => array($query), PATH => '/vc.php', LABEL => 'Virtual Connect' );

    return generateLinkList( $dataArray );
}



//Gets a list of all available service groups.
//Then checks that list for the supplied service group.
//If found returns true
function isServiceGroupAvailable( $srvGrp, $availableServices ) {
    if (in_array($srvGrp, $availableServices)) {
        return true;
    }
    return false;
}

function torGenerateContent() {
    global $statsDB, $datadir, $site, $date, $php_webroot;
    $availableServices = getServiceGroupList();

    $content = array();

    $content[] = "<div class='h1-inline-text'><h1>Statistics</h1><span>DATA_AVAILABILITY_STATUS</span></div>\n";
    $content[] = checkIfOldPath();
    $content[] = displayVersion();

    if (file_exists($datadir. "/TOR/gateway_hostname")) {
        $gatewayName = file_get_contents($datadir . "/TOR/gateway_hostname");//NOSONAR
        $content[] = "<p><b>VApp Gateway</b>: $gatewayName</p>\n";
    }


    $menuScript = <<<EOS
<script type="text/javascript" src="$php_webroot/TOR/index_inc.js"></script>
<script type="text/javascript">
YAHOO.util.Event.addListener(window, "load", torMenuTreeEnhance);
</script>

<div id="torMenuTree" class="yui-skin-sam">

EOS;
    $content[] = $menuScript;

    // An array of arrays (one for each view to be displayed) each containing a list,
    // a unique name (the value of VIEW) and a label.
    $allViewLists[] = array(
        array( VIEW_LIST => generateSystemView(), VIEW => 'SystemView', LABEL => 'System View' ),
        array( VIEW_LIST => generateCMMediationView($availableServices), VIEW => 'CmMed', LABEL => 'CM Mediation' ),
        array( VIEW_LIST => generateCMServicesView($availableServices), VIEW => 'CmServ', LABEL => 'CM Services' ),
        array( VIEW_LIST => generateNCMView(), VIEW => 'NCM', LABEL => 'NCM'),
        array( VIEW_LIST => generatePMView($availableServices), VIEW => 'Pm', LABEL => 'PM'),
        array( VIEW_LIST => generateSHMView($availableServices), VIEW => 'Shm', LABEL => 'SHM/NHC'),
        array( VIEW_LIST => generateFMView($availableServices), VIEW => 'Fm', LABEL => 'FM'),
        array( VIEW_LIST => generateSecurityView($availableServices), VIEW => 'Security', LABEL => 'Security'),
        array( VIEW_LIST => generateRVView(), VIEW => 'RVInfo', LABEL => 'RV Information'),
        array( VIEW_LIST => generatePlatformView($availableServices), VIEW => 'Platform', LABEL => 'Platform'),
        array( VIEW_LIST => generateDBView($availableServices), VIEW => 'Databases', LABEL => 'Databases'),
        array( VIEW_LIST => generateHWView(), VIEW => 'Hw', LABEL => 'HW')
    );

    // Calls getViewHtml for each View
    foreach ( $allViewLists as $viewList) {
        getViewHtml( $viewList, $content );
    }

    // End the menu tree
    $content [] = "</div>";

    // Calls getVCSEventCounts and if there is data displays a link and the table.
    $vcsTable = getVCSEventCounts();
    if ( $vcsTable->hasRows() ) {
        $content[] = '<BR>';
        $content[] = makeLink( '/TOR/vcs_events.php', 'VCS Event Details' );
        $content[] = $vcsTable->getTable();
    }

    $k8sEventsTable = new ModelledTable('common/k8s_ha_counts', 'k8s_ha_counts');
    if ( $k8sEventsTable->hasRows() ) {
        $content[] = addLineBreak();
        $content[] = makeLink( '/k8s/ha.php', 'K8S HA Event Details' );
        $content[] = $k8sEventsTable->getTable();
    }

    $jbossShutdownTable = new ModelledTable('TOR/platform/enm_jboss_shutdown_summary', 'jboss_shutdown_summary');
    if ( $jbossShutdownTable->hasRows() ) {
        $content[] = addLineBreak();
        $content[] = ModelledTable::getTargetLink('TOR/platform/enm_jboss_shutdown', 'JBoss Shutdown');
        $content[] = $jbossShutdownTable->getTable();
    }

    $query = $statsDB->hasDataQuery( 'enm_consul_n_sam_events' );
    $list[] = array(QUERY => array($query), PATH => '/TOR/consul.php', LABEL => 'Consul/SAM Events' );
    $linkList = generateLinkList($list);

    if ( is_array($linkList) ) {
        foreach ( $linkList as $link ) {
            $content[] = $link;
        }
    }

    return implode($content);
}
