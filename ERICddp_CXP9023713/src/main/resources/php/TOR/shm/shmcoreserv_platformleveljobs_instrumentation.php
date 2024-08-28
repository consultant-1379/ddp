<?php
$pageTitle = "NE and Activity Job Details";

$YUI_DATATABLE = true;

include_once "../../common/init.php";

require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once 'HTML/Table.php';
include_once  "../../common/graphFunctions.php";

const ECIM_NODE_HEALTH_CHECKS = 'ecimNodeHealthCHecks';
const ECIM_DELETE_UPGRADE_PACKAGES = 'ecimDeleteUpgradePackages';
const CPP_DELETE_UPGRADE_PACKAGES = 'cppDeleteUpgradePackages';
const ECIM_DELETE_BACKUPS = 'ecimDeleteBackups';
const CPP_DELETE_BACKUP = 'cppDeleteBackup';
const ECIM_LICENSE_INSTALLS = 'ecimLicenseInstalls';
const CPP_LICENSE_INSTALLS = 'cppLicenseInstalls';
const ECIM_NODE_HEALTH_CHECK_NE_JOBS_COUNT = 'ecimNodeHealthCheckNeJobscount';
const AXE_LICENSE_NE_JOBS = 'axeLicenseNeJobs';
const AXE_LICENSE_INSTALLS = 'axeLicenseInstalls';
const AXE_BACKUP_NE_JOBS = 'AXEBackupNeJobs';
const AXE_DELETE_BACKUP_NE_JOBS = 'AXEDeleteBackupNeJobs';
const AXE_BACKUP_CREATE_BACKUPS = 'axeBackupCreateBackups';
const AXE_BACKUP_UPLOADS = 'axeBackupUploads';
const AXE_DELETE_BACKUPS = 'axeDeleteBackups';
const ECIM_LICENSE_REFRESH = 'ecimLicenseRefreshNeJobscount';
const ECIM_LICENSE_REFRESH_ACTIVITY = 'ecimLicenseRefreshJobRefreshActivities';
const ECIM_LICENSE_REQUEST_ACTIVITY = 'ecimLicenseRefreshJobRequestActivities';
const ECIM_LICENSE_INSTALL_ACTIVITY = 'ecimLicenseRefreshJobInstallActivities';
const VRAN_UPGRADE_ACTIVITY = 'vranUpgradeActivates';
const VRAN_UPGRADE_CONFIRM_ACTIVITY = 'vranUpgradeConfirms';
const VRAN_UPGRADE_PREPARE_ACTIVITY = 'vranUpgradePrepares';
const VRAN_UPGRADE_VERIFIES_ACTIVITY = 'vranUpgradeVerifies';
const VRAN_UPGRADE_NE_JOBS = 'vranUpgradeNeJobscount';
const SHM_NEJOB_INSTR = 'shm_nejob_instr';
const SHM_ACTIVITYJOB_INSTR = 'shm_activityjob_instr';

function shmcoreservNeJobGraphs() {

 /* Shm NE Job Instrumentation Graphs */
    drawHeaderWithHelp("NE Jobs", 2, "NeJobs", "", "");

    $instrGraphParams = array(
        'upgradeNeJobs' => array(
        SqlPlotParam::TITLE => 'SHM Upgrade NE Jobs',
        SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
        SqlPlotParam::TYPE => 'sb',
        SqlPlotParam::WHAT_COL => array('cppUpgradeNeJobs' => 'cppUpgradeNeJobs',
                        'ecimUpgradeNeJobs' => 'ecimUpgradeNeJobs',
                        'AXEUpgradeNeJobs' => 'AXEUpgradeNeJobs')
        ),
        'BackupNeJobs' => array(
            SqlPlotParam::TITLE => 'SHM Backup NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('cppBackupNeJobs' => 'cppBackupNeJobs',
                            'ecimBackupNeJobs' => 'ecimBackupNeJobs')
         ),

        'LicenseNeJobs' => array(
            SqlPlotParam::TITLE => 'SHM License NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('cppLicenseNeJobs' => 'cppLicenseNeJobs',
                            'ecimLicenseNeJobs' => 'ecimLicenseNeJobs')
        ),
        'RestoreNeJobs ' => array(
            SqlPlotParam::TITLE => 'SHM Restore NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('cppRestoreNeJobs' => 'cppRestoreNeJobs',
                            'ecimRestoreNeJobs' => 'ecimRestoreNeJobs')
        ),
        'DeleteBackupNeJobs' => array(
            SqlPlotParam::TITLE => 'SHM DeleteBackup NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'cppDeleteBackupNeJobs'  => 'cppDeleteBackupNeJobs',
                'ecimDeleteBackupNeJobs' => 'ecimDeleteBackupNeJobs'
            )
        ),
        'DeleteUpgradePackageNeJobs' => array(
            SqlPlotParam::TITLE => 'SHM DeleteUpgradePackage NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'cppDeleteUpgradePackageNeJobs'  => 'cppDeleteUpgradePackageNeJobs',
                'ecimDeleteUpgradePackageNeJobs' => 'ecimDeleteUpgradePackageNeJobs'
            )
        ),
        ECIM_NODE_HEALTH_CHECK_NE_JOBS_COUNT => array(
            SqlPlotParam::TITLE => 'NHC NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_NODE_HEALTH_CHECK_NE_JOBS_COUNT  =>
                            ECIM_NODE_HEALTH_CHECK_NE_JOBS_COUNT)
        ),
        AXE_LICENSE_NE_JOBS => array(
            SqlPlotParam::TITLE => 'AXE License NE Jobs',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_LICENSE_NE_JOBS => AXE_LICENSE_NE_JOBS)
        ),
        AXE_BACKUP_NE_JOBS => array(
            SqlPlotParam::TITLE => 'AXE Backup NE JOBS',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_BACKUP_NE_JOBS => AXE_BACKUP_NE_JOBS)
        ),
        AXE_DELETE_BACKUP_NE_JOBS => array(
            SqlPlotParam::TITLE => 'AXE DeleteBackup NE JOBS',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_DELETE_BACKUP_NE_JOBS => AXE_DELETE_BACKUP_NE_JOBS)
        ),
        ECIM_LICENSE_REFRESH =>  array(
            SqlPlotParam::TITLE => 'ECIM License Refresh NE JOBS',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_LICENSE_REFRESH => ECIM_LICENSE_REFRESH)
        ),
        VRAN_UPGRADE_NE_JOBS =>  array(
            SqlPlotParam::TITLE => 'vRAN Upgrade NE Jobs Count',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(VRAN_UPGRADE_NE_JOBS => VRAN_UPGRADE_NE_JOBS)
        )
    );

    $instances = getInstances(SHM_NEJOB_INSTR);
    $graphs = array();
    foreach ( $instances as $instance ) {
        $graphs[] = $instance;
    }
    foreach ( $instrGraphParams as $instrGraphParamName ) {
        $title = $instrGraphParamName[SqlPlotParam::TITLE];
        $graphType = $instrGraphParamName[SqlPlotParam::TYPE];
        $ylabel = $instrGraphParamName[SqlPlotParam::Y_LABEL];
        $col = $instrGraphParamName[SqlPlotParam::WHAT_COL];
        foreach ( $instances as $instance ) {
            $graphs[] = generateGraph($title, $graphType, $ylabel, $col, SHM_NEJOB_INSTR, $instance);
        }
    }
    plotGraphs($graphs);
}

function generateGraph($title, $graphType, $ylabel, $col, $table, $instance) {
    global $date;

    $where = "$table.siteid = sites.id AND sites.name = '%s' AND
             $table.serverid = servers.id AND servers.hostname='%s'";

    $dbTables = array( $table, StatsDB::SITES, StatsDB::SERVERS );
    $sqlParamWriter = new SqlPlotParam();
    $sqlParam = SqlPlotParamBuilder::init()
         ->title($title)
         ->type($graphType)
         ->yLabel($ylabel)
         ->forceLegend()
         ->makePersistent()
         ->addQuery(
             SqlPlotParam::DEFAULT_TIME_COL,
             $col,
             $dbTables,
             $where,
             array('site', 'inst')
            )
         ->build();
    $extArgs = "inst=$instance";
    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 550, 320, $extArgs);
}

function activityParams() {
   $activityParam = array(
        'cppUpgrade' => array(
            SqlPlotParam::TITLE => 'SHM CppUpgrade',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'cppUpgradeInstalls'  => 'cppUpgradeInstalls',
                'cppUpgradeVerifies' => 'cppUpgradeVerifies',
                'cppUpgradeUpgrades' => 'cppUpgradeUpgrades',
                'cppUpgradeConfirms' => 'cppUpgradeConfirms'
            )
        ),
        'ecimUpgrade' => array(
            SqlPlotParam::TITLE => 'SHM EcimUpgrade',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'ecimUpgradePrepares'  => 'ecimUpgradePrepares',
                'ecimUpgradeVerifys' => 'ecimUpgradeVerifys',
                'ecimUpgradeActivates' => 'ecimUpgradeActivates',
                'ecimUpgradeConfirms' => 'ecimUpgradeConfirms'
            )
        ),
        'cppBackup' => array(
            SqlPlotParam::TITLE => 'SHM CppBackup',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'cppBackupCreateCVs'  => 'cppBackupCreateCVs',
                'cppBackupSetCVAsStartables' => 'cppBackupSetCVAsStartables',
                'cppBackupSetCVFirstInRollbackLists' => 'cppBackupSetCVFirstInRollbackLists',
                'cppBackupExportCVs' => 'cppBackupExportCVs'
            )
        ),
        'ecimBackup' => array(
            SqlPlotParam::TITLE => 'SHM EcimBackup',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'ecimBackupCreateBackups' => 'ecimBackupCreateBackups',
                'ecimBackupUploads' =>'ecimBackupUploads'
            )
        ),
        CPP_LICENSE_INSTALLS => array(
            SqlPlotParam::TITLE => 'SHM CppLicense Install',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL =>  array(CPP_LICENSE_INSTALLS  => CPP_LICENSE_INSTALLS)
        ),
        ECIM_LICENSE_INSTALLS => array(
            SqlPlotParam::TITLE => 'SHM EcimLicense Install',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_LICENSE_INSTALLS  => ECIM_LICENSE_INSTALLS)
        ),
        CPP_DELETE_BACKUP => array(
            SqlPlotParam::TITLE => 'SHM CppDelete Backup',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(CPP_DELETE_BACKUP  => CPP_DELETE_BACKUP)
        ),
        ECIM_DELETE_BACKUPS => array(
            SqlPlotParam::TITLE => 'SHM EcimDelete Backup',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_DELETE_BACKUPS  => ECIM_DELETE_BACKUPS)
        ),
        CPP_DELETE_UPGRADE_PACKAGES => array(
            SqlPlotParam::TITLE => 'SHM CppDelete Upgrade',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(CPP_DELETE_UPGRADE_PACKAGES  => CPP_DELETE_UPGRADE_PACKAGES)
        ),
        ECIM_DELETE_UPGRADE_PACKAGES => array(
            SqlPlotParam::TITLE => 'SHM EcimDelete Upgrade',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_DELETE_UPGRADE_PACKAGES  => ECIM_DELETE_UPGRADE_PACKAGES)
        ),
        'cppRestore' => array(
            SqlPlotParam::TITLE => 'SHM CppRestore',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'cppRestoreDownloadCVs'  => 'cppRestoreDownloadCVs',
                'cppRestoreVerifyCVs' =>'cppRestoreVerifyCVs',
                'cppRestoreInstallCVs' => 'cppRestoreInstallCVs',
                'cppRestoreRestores' => 'cppRestoreRestores',
                'cppRestoreConfirmCVs' => 'cppRestoreConfirmCVs'
            )
        ),
        'ecimRestore' => array(
            SqlPlotParam::TITLE => 'SHM EcimRestore',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(
                'ecimRestoreDownloadBackups'  => 'ecimRestoreDownloadBackups',
                'ecimRestoreRestoreBackups' => 'ecimRestoreRestoreBackups',
                'ecimRestoreConfirmBackups' => 'ecimRestoreConfirmBackups'
            )
        ),
        ECIM_NODE_HEALTH_CHECKS => array(
            SqlPlotParam::TITLE => 'ECIM NodeHealthChecks',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_NODE_HEALTH_CHECKS  => ECIM_NODE_HEALTH_CHECKS)
        ),
        AXE_LICENSE_INSTALLS => array(
            SqlPlotParam::TITLE => 'AXE LicenseInstalls',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_LICENSE_INSTALLS => AXE_LICENSE_INSTALLS)
        ),
        AXE_BACKUP_CREATE_BACKUPS => array(
            SqlPlotParam::TITLE => 'AXE Backup CreateBackups',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_BACKUP_CREATE_BACKUPS => AXE_BACKUP_CREATE_BACKUPS)
        ),
        AXE_BACKUP_UPLOADS => array(
            SqlPlotParam::TITLE => 'AXE Backup Uploads',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_BACKUP_UPLOADS => AXE_BACKUP_UPLOADS)
        ),
        AXE_DELETE_BACKUPS => array(
            SqlPlotParam::TITLE => 'AXE Delete Backups',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(AXE_DELETE_BACKUPS => AXE_DELETE_BACKUPS)
        )
    );
    $ecimActivityParams = ecimActivityParams();
    return array_merge($activityParam, $ecimActivityParams);
}

function ecimActivityParams() {
   return array(
        ECIM_LICENSE_REFRESH_ACTIVITY => array(
            SqlPlotParam::TITLE => 'ECIM License Refresh Job Refresh Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_LICENSE_REFRESH_ACTIVITY => ECIM_LICENSE_REFRESH_ACTIVITY)
        ),
        ECIM_LICENSE_REQUEST_ACTIVITY => array(
            SqlPlotParam::TITLE => 'ECIM License Refresh Job Request Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_LICENSE_REQUEST_ACTIVITY => ECIM_LICENSE_REQUEST_ACTIVITY)
        ),
        ECIM_LICENSE_INSTALL_ACTIVITY => array(
            SqlPlotParam::TITLE => 'ECIM License Refresh Job Install Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(ECIM_LICENSE_INSTALL_ACTIVITY => ECIM_LICENSE_INSTALL_ACTIVITY)
        ),
        VRAN_UPGRADE_ACTIVITY => array(
            SqlPlotParam::TITLE => 'vRAN Upgrade Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(VRAN_UPGRADE_ACTIVITY => VRAN_UPGRADE_ACTIVITY)
        ),
        VRAN_UPGRADE_CONFIRM_ACTIVITY => array(
            SqlPlotParam::TITLE => 'vRAN Upgrade Confirms Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(VRAN_UPGRADE_CONFIRM_ACTIVITY => VRAN_UPGRADE_CONFIRM_ACTIVITY)
        ),
        VRAN_UPGRADE_PREPARE_ACTIVITY => array(
            SqlPlotParam::TITLE => 'vRAN Upgrade Prepares Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(VRAN_UPGRADE_PREPARE_ACTIVITY => VRAN_UPGRADE_PREPARE_ACTIVITY)
        ),
        VRAN_UPGRADE_VERIFIES_ACTIVITY => array(
            SqlPlotParam::TITLE => 'vRAN Upgrade Verifies Activities',
            SqlPlotParam::Y_LABEL => SqlPlotParam::COUNT_LABEL,
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array(VRAN_UPGRADE_VERIFIES_ACTIVITY => VRAN_UPGRADE_VERIFIES_ACTIVITY)
        )
    );
}

function activityJobs() {
 /* SHM Activity Job Instrumentation Graphs */

    drawHeaderWithHelp("Activity Jobs", 2, "activityJobs", "", "");

    $instrGraphParams = activityParams();

    $instances = getInstances(SHM_ACTIVITYJOB_INSTR);
    $graphs = array();
    foreach ( $instances as $instance ) {
        $graphs[] = $instance;
    }

    foreach ( $instrGraphParams as $instrGraphParamName ) {
        $title = $instrGraphParamName[SqlPlotParam::TITLE];
        $graphType = $instrGraphParamName[SqlPlotParam::TYPE];
        $ylabel = $instrGraphParamName[SqlPlotParam::Y_LABEL];
        $col = $instrGraphParamName[SqlPlotParam::WHAT_COL];
        foreach ( $instances as $instance ) {
            $graphs[] = generateGraph($title, $graphType, $ylabel, $col, SHM_ACTIVITYJOB_INSTR, $instance);
        }
    }
    plotGraphs($graphs);
}

function shmcoreservActivityJobGraphs($dbTable) {
    global $debug, $webargs, $php_webroot, $date, $site;
    $sqlParamWriter = new SqlPlotParam();

    activityJobs();

    $statsDB = new StatsDB();
    if ( $statsDB->hasData($dbTable) ) {
        drawHeaderWithHelp("AXE Activities", 2, "axeact");
        $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND
                  $dbTable.nameid = enm_shm_axeactivity_names.id";
        $dbTables = array( $dbTable, StatsDB::SITES, "enm_shm_axeactivity_names");
        $params = SqlPlotParamBuilder::init()
                ->title('AXE Activity')
                ->type(SqlPlotParam::STACKED_BAR)
                ->barwidth(60)
                ->yLabel('Activities')
                ->makePersistent()
                ->forceLegend()
                ->addQuery(
                    SqlPlotParam::DEFAULT_TIME_COL,
                    array( "n_count" => "Act" ),
                    $dbTables,
                    $where,
                    array('site'),
                    "enm_shm_axeactivity_names.name"
                )
                ->build();
        $id = $sqlParamWriter->saveParams($params);
        echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300);
    }
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site;
    echo "<a href='$php_webroot/TOR/shm/shmcoreserv_job_instrumentation.php?$webargs'>Back to Main Jobs Page</a>";
    $neURL = makeSelfLink() . "?" . $webargs . "&showne=1";
    echo "<br><br>";
    echo "<a href=\"$neURL\">Activity(NE) level jobs</a>\n";
    shmcoreservNeJobGraphs();
}

if (issetURLParam('showne')) {
    echo "<a href='$php_webroot/TOR/shm/shmcoreserv_platformleveljobs_instrumentation.php?$webargs'>
         Back to NE Job Page</a>";
    shmcoreservActivityJobGraphs("enm_shm_axeactivity");
    include_once PHP_ROOT . "/common/finalise.php";
}
else {
    mainFlow();
    include_once PHP_ROOT . "/common/finalise.php";
}
