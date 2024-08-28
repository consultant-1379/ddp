<?php
$pageTitle = "Job Details";

const ACT = 'action';
$DISABLE_UI_PARAMS = array( ACT );

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once 'HTML/Table.php';

const JOB_TITLE = 'Job Title';
const SITE_ID = 'siteId';
const SITES = 'sites';
const Y_AXIS = 'yaxis';
const NO_OF_NODES = 'no of nodes';
const ACTIVITY = 'activity';
const STATUS = 'status';
const PROGRESS_PERCENTAGE = 'progress_percentage';
const NO_OF_NETWORK_ELEMENTS = 'number_of_network_elements';
const JOB_NAME = 'job_name';
const J_TYPE = 'Job Type';
const JOB_TYPE = 'jobType';
const ENM_SHMCORESERV_DETAILS_LOGS = "enm_shmcoreserv_details_logs";
const DURATION = 'duration';
const T_NEJOB_CREATION = 't_nejobcreation';
const BACKUP = 'Backup';
const NETYPES = 'ne_types.name';
const BORDER = 'border=0';
const NE_TYPE_TABLE = "ne_types";
const T_COUNT = "Count";
const NODE_TABLE = "/TOR/shm/shmcoreserv_jobsleveldetails_instrumentation.php";

$START_TIME_DB = 'DATE_SUB(' . ENM_SHMCORESERV_DETAILS_LOGS . '.time, INTERVAL ROUND((' .
               ENM_SHMCORESERV_DETAILS_LOGS . '.duration/1000),0) SECOND)';
$TABLE_SHM_FILESIZE_LOGS = "enm_shm_filesize_logs";

function displayPlatformLevelJobsLink($statsDB) {
    global $webargs, $date, $site;
    $row = $statsDB->queryRow("
        SELECT COUNT(*)
        FROM shm_nejob_instr, sites
    WHERE
        siteid = sites.id AND sites.name = '$site' AND
        time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ($row[0] > 0) {
        echo "<a href=\"" . PHP_WEBROOT . "/TOR/shm/shmcoreserv_platformleveljobs_instrumentation.php?$webargs\">
             Platform level jobs</a>";
    }
}

function displayShmGraph($statsDB) {
    global $site, $date;

    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table(BORDER);
    $row = array();

    $where = "jobType != 'NODE_HEALTH_CHECK' AND
              enm_shmcoreserv_details_logs.siteid = sites.id AND
              sites.name = '$site'";
    $whereGraph = "jobType != 'NODE_HEALTH_CHECK' AND
              enm_shmcoreserv_details_logs.siteid = sites.id AND
              sites.name = '%s'";
    $whereTime = " AND enm_shmcoreserv_details_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $params = SqlPlotParamBuilder::init()
        ->title('SHM Performance')
        ->type(SqlPlotParam::XY)
        ->makePersistent()
        ->yLabel('Rate (Nodes/sec)');

    $query = "SELECT jobType AS jobType, result AS result
              FROM enm_shmcoreserv_details_logs, sites
              WHERE " . $where . $whereTime .  " GROUP BY jobType, result";

    $statsDB->query($query);
    $queryRow = $statsDB->getNextRow();
    while ($queryRow) {
        $params = $params->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            array(
                '(number_of_network_elements) / (duration/1000)' =>
                $queryRow[0] . " (" . $queryRow[1] . ")"
                ),
            array(ENM_SHMCORESERV_DETAILS_LOGS, "sites"),
            $whereGraph . " AND jobType='" . $queryRow[0] . "' AND result='" . $queryRow[1] . "'",
            array('site')
            );
        $queryRow = $statsDB->getNextRow();
    }
    $params = $params->build();

    $id = $sqlParamWriter->saveParams($params);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300);
    $graphTable->addRow($row);
    drawHeaderWithHelp("SHM Performance", 2, "shmPerformance", "", "");
    echo $graphTable->toHTML();
}

function displayJobDetailsCount() {
    global $site, $date;
    $cols = array(
                array( 'key' => JOB_TYPE, 'db' => JOB_TYPE, DDPTABLE::LABEL => J_TYPE ),
                array(
                    'key' => 'count',
                    'db' => 'COUNT(number_of_network_elements)',
                    DDPTABLE::LABEL => 'No. of Jobs'
                ),
                array(
                    'key' => 'sum',
                    'db' => 'SUM(number_of_network_elements)',
                    DDPTABLE::LABEL => 'No. of Network Elements'
                ),
                array( 'key' => RESULT, 'db' => RESULT, DDPTABLE::LABEL => RESULT )
                );
    $where = "
            enm_shmcoreserv_details_logs.siteid = sites.id AND
            sites.name = '$site' AND
            enm_shmcoreserv_details_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY jobType, result";

    $table = new SqlTable(
        "job_details_count",
        $cols,
        array(ENM_SHMCORESERV_DETAILS_LOGS, SITES),
        $where,
        true,
        array(
          DDPTABLE::ROWS_PER_PAGE => 10,
          DDPTABLE::ROWS_PER_PAGE_OPTIONS => array(25, 50)
        )
    );
    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader("Node Level Count per Job Type", 1, "", "", "");
    }
}

function displayMainJobGraphs($statsDB) {
    global $debug, $date, $site;
    if ( !$statsDB->hasData('shm_mainjob_instr') ) {
        return;
    }
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table(BORDER);
    $row = array();
    $sqlParam =
               array( SqlPlotParam::TITLE => "Main Job",
                      SqlPlotParam::Y_LABEL => T_COUNT,
                      'type' => 'sb',
                      SqlPlotParam::USER_AGG => 'true',
                      'sb.barwidth' => '60',
                      SqlPlotParam::PERSISTENT => 'true',
                      SqlPlotParam::QUERY_LIST =>
                        array(
                             array(
                                   SqlPlotParam::TIME_COL => 'time',
                                   SqlPlotParam::WHAT_COL => array (
                                                       'upgradeMainJobs' => 'upgradeMainJobs',
                                                       'backupMainJobs' => 'backupMainJobs',
                                                       'licenseMainJobs' => 'licenseMainJobs',
                                                       'restoreMainJobs' => 'restoreMainJobs',
                                                       'deleteBackupMainJobs' => 'deleteBackupMainJobs',
                                                       'deleteUpgradePackageMainJobs' => 'deleteUpgradePackageMainJobs',
                                                       'nodeHealthCheckMainJobs' => 'nodeHealthCheckMainJobs',
                                                       'lkfRefreshMainJobs' => 'lkfRefreshMainJobs'
                                                       ),
                                   SqlPlotParam::TABLES => "shm_mainjob_instr,sites,servers",
                                   SqlPlotParam::WHERE => "shm_mainjob_instr.siteid = sites.id AND sites.name = '%s' AND
                                              shm_mainjob_instr.serverid=servers.id",
                                   SqlPlotParam::Q_ARGS => array( 'site' )
                                  )
                            )
                     );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300);
        $graphTable->addRow($row);
        drawHeaderWithHelp("Main Jobs", 2, "mainJobs", "", "");
        echo $graphTable->toHTML();

}

function displayWaitingJobGraphs($statsDB) {
    global $date, $site;
    if ( !$statsDB->hasData('shm_waitingjob_instr') ) {
        return;
    }
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table(BORDER);
    $row = array();
    $sqlParam =
      array( SqlPlotParam::TITLE => 'Waiting Job',
             SqlPlotParam::Y_LABEL => T_COUNT,
             'type' => 'sb',
             'sb.barwidth' => '60',
             SqlPlotParam::USER_AGG => 'true',
             SqlPlotParam::PERSISTENT => 'true',
             SqlPlotParam::QUERY_LIST =>
             array(
                   array(
                         SqlPlotParam::TIME_COL => 'time',
                          SqlPlotParam::WHAT_COL => array (
                                              'NHCWaitingMainJobs' => 'NHCWaitingMainJobs',
                                              'NHCWaitingNEJobs' => 'NHCWaitingNEJobs',
                                              'licenseRefreshWaitingMainJobs' => 'licenseRefreshWaitingMainJobs',
                                              'licenseRefreshWaitingNEJobs' => 'licenseRefreshWaitingNEJobs',
                                              'dusGen2LicenseRefreshWaitingNeJobs' =>
                                              'dusGen2LicenseRefreshWaitingNeJobs',
                                              'vRANUpgradeWaitingMainJobs' => 'vRANUpgradeWaitingMainJobs',
                                              'vRANUpgradeWaitingNEJobs' => 'vRANUpgradeWaitingNEJobs'
                                             ),
                          SqlPlotParam::TABLES => "shm_waitingjob_instr,sites,servers",
                          SqlPlotParam::WHERE => "shm_waitingjob_instr.siteid = sites.id AND sites.name = '%s' AND
                                     shm_waitingjob_instr.serverid = servers.id",
                          SqlPlotParam::Q_ARGS => array( 'site' )
                        )
                   )
            );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300);
        $graphTable->addRow($row);
        drawHeaderWithHelp("Waiting Jobs", 2, "waitingJobs", "", "");
        echo $graphTable->toHTML();
}

function displayLogGraphs($statsDB) {
      global $date, $site;
      if ( !$statsDB->hasData('enm_shmcoreserv_jobexecution_logs') ) {
          return;
      }
      $sqlParamWriter = new SqlPlotParam();
      $instances = getInstances("enm_shmcoreserv_jobexecution_logs");
      $instrGraphParams = array(
               array(
                    SqlPlotParam::TITLE => 'CPP Upgrade (Upgrade Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'CppUpgrade'
                  ),
               array(
                    SqlPlotParam::TITLE => 'CPP Restore (Restore Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'CppRestore'
                  ),
               array(
                    SqlPlotParam::TITLE => 'ECIM Upgrade (Activate Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'EcimUpgrade'
                  ),
               array(
                    SqlPlotParam::TITLE => 'ECIM Restore (Restore Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'EcimRestore'
                  ),
               array(
                    SqlPlotParam::TITLE => 'CPP Backup (Upload Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'CppUpload'
                  ),
               array(
                    SqlPlotParam::TITLE => 'ECIM Backup (Upload Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'EcimUpload'
                  ),
               array(
                    SqlPlotParam::TITLE => 'ECIM Backup (Create Activity)',
                    Y_AXIS => NO_OF_NODES,
                    ACTIVITY => 'EcimCreate'
                  ),
      );

     foreach ( $instances as $instance ) {
          $header[] = $instance;
      }

      $graphTable = new HTML_Table(BORDER);
      $graphTable->addRow($header, null, 'th');
       foreach ( $instrGraphParams as $instrGraphParamNames ) {
            $row = array();
            $instActivity = $instrGraphParamNames[ACTIVITY];
            $instTitle = $instrGraphParamNames[SqlPlotParam::TITLE];
            foreach ( $instances as $instance ) {
                $sqlParam =
                      array( SqlPlotParam::TITLE =>  '%s',
                             SqlPlotParam::T_ARGS => array('instTitle'),
                             SqlPlotParam::Y_LABEL => $instrGraphParamNames[Y_AXIS],
                             'type' => 'sb',
                             SqlPlotParam::USER_AGG => 'true',
                             SqlPlotParam::PERSISTENT => 'true',
                             'forcelegend' => 'true',
                             SqlPlotParam::QUERY_LIST =>
                              array(
                                  array(
                                        SqlPlotParam::TIME_COL => 'time',
                                        'multiseries'=> "enm_shmcoreserv_jobexecution_logs.flow",
                                        SqlPlotParam::WHAT_COL => array("COUNT(flow)"=>'count'),
                                        SqlPlotParam::TABLES => "enm_shmcoreserv_jobexecution_logs,sites,servers",
                                        SqlPlotParam::WHERE => "enm_shmcoreserv_jobexecution_logs.siteid = sites.id AND
                                                   sites.name = '%s' AND
                                                   enm_shmcoreserv_jobexecution_logs.serverid = servers.id AND
                                                   servers.hostname='%s' AND
                                                   enm_shmcoreserv_jobexecution_logs.activity=
                                                   '%s' AND
                                                   flow in('COMPLETED_THROUGH_TIMEOUT',
                                                           'COMPLETED_THROUGH_POLLING',
                                                           'COMPLETED_THROUGH_NOTIFICATIONS')
                                                   GROUP BY flow,HOUR(time),MINUTE(time)",
                                        SqlPlotParam::Q_ARGS => array( 'site', 'inst', 'instActivity')
                                  )
                            )
                      );
                $extArgs = "inst=$instance&instActivity=$instActivity&instTitle=$instTitle";
                $id = $sqlParamWriter->saveParams($sqlParam);
                $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 280, $extArgs);
            }
            $graphTable->addRow($row);
      }
      drawHeaderWithHelp(
          "Metrics for Job Execution Flow",
          2,
          "executionFlowJobs",
          "DDP_Bubble_310_SHMCORESERV_Job_Execution_Flow"
      );
      echo $graphTable->toHTML();
}

function displayJobPackageSizeDetails($statsDB, $jobType, $title) {
    global $site, $date, $TABLE_SHM_FILESIZE_LOGS;
    $where =  $statsDB->where($TABLE_SHM_FILESIZE_LOGS) .
             "AND $TABLE_SHM_FILESIZE_LOGS.jobType = '$jobType' AND
             ne_types.id=$TABLE_SHM_FILESIZE_LOGS.netypeid";
    $table = SqlTableBuilder::init()
           ->name($jobType)
           ->tables(array($TABLE_SHM_FILESIZE_LOGS, NE_TYPE_TABLE, StatsDB::SITES))
           ->where($where)
           ->groupBy(array("$TABLE_SHM_FILESIZE_LOGS.netypeid"))
           ->addSimpleColumn(NETYPES, "Node Type")
           ->addSimpleColumn(StatsDB::ROW_COUNT, "$jobType file count")
           ->addSimpleColumn('ROUND(MIN(fileSize)/(1024*1024),2)', "$jobType File Min Size(MB)")
           ->addSimpleColumn('ROUND(MAX(fileSize)/(1024*1024),2)', "$jobType File Max Size(MB)")
           ->addSimpleColumn('ROUND(AVG(fileSize)/(1024*1024),2)', "Avg $jobType File Size(MB)");
    if ($jobType === 'Backup') {
        $table->addSimpleColumn('Component', 'Component');
    }
    echo $table->build()->getTableWithHeader($title, 1);
}

function plotShmFilesize($jobType, $title) {
    global $site, $date, $TABLE_SHM_FILESIZE_LOGS;
    $sqlParamWriter = new SqlPlotParam();
    $dbTables = array( $TABLE_SHM_FILESIZE_LOGS, NE_TYPE_TABLE, StatsDB::SITES );
    $where =
    "$TABLE_SHM_FILESIZE_LOGS.siteid = sites.id AND
    sites.name = '%s' AND
    $TABLE_SHM_FILESIZE_LOGS.jobType = '%s' AND
    ne_types.id=$TABLE_SHM_FILESIZE_LOGS.netypeid";

    $sqlParam = SqlPlotParamBuilder::init()
                ->title("$title")
                ->forcelegend('true')
                ->type(SqlPlotParam::STACKED_BAR)
                ->presetagg('SUM', 'Per Minute')
                ->yLabel("FileSize(MB)")
                ->makePersistent()
                ->addQuery(
                    SqlPlotParam::DEFAULT_TIME_COL,
                    array('fileSize/(1024*1024)' => 'filesize'),
                    $dbTables,
                    $where,
                    array('site', 'jobType'),
                    NETYPES
                    )
                ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $extraArgs = "jobType=$jobType";
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320, $extraArgs);
}

function displayShmTable($statsDB) {
    global $site, $date, $START_TIME_DB;

    $where = $statsDB->where(ENM_SHMCORESERV_DETAILS_LOGS);
    $where .= " AND jobType != 'NODE_HEALTH_CHECK'
                GROUP BY enm_shmcoreserv_details_logs.job_name";

    $reqBind = SqlTableBuilder::init()
              ->name(ENM_SHMCORESERV_DETAILS_LOGS)
              ->tables(array(ENM_SHMCORESERV_DETAILS_LOGS, StatsDB::SITES))
              ->where($where)
              ->addSimpleColumn(JOB_TYPE, J_TYPE)
              ->addSimpleColumn(JOB_NAME, JOB_TITLE)
              ->addSimpleColumn(NO_OF_NETWORK_ELEMENTS, 'No.of Network Elements')
              ->addSimpleColumn('n_components', 'Number Of Components')
              ->addSimpleColumn($START_TIME_DB, 'Start Time')
              ->addSimpleColumn('enm_shmcoreserv_details_logs.time', 'End Time')
              ->addColumn('total_time', DURATION, 'Total Time taken', DDPTable::FORMAT_MSEC)
              ->addSimpleColumn(PROGRESS_PERCENTAGE, 'Progress Percentage')
              ->addSimpleColumn(STATUS, 'Status')
              ->addSimpleColumn(RESULT, RESULT)
              ->addColumn('ne_job_creation', T_NEJOB_CREATION, 'Duration Of NeJobsCreation', DDPTable::FORMAT_MSEC)
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader("SHM Job details ", 2, "", "", ENM_SHMCORESERV_DETAILS_LOGS);

}

function displayNhcTable($statsDB) {
    global $site, $date, $START_TIME_DB;

    $where = $statsDB->where(ENM_SHMCORESERV_DETAILS_LOGS);
    $where .= " AND jobType = 'NODE_HEALTH_CHECK' AND
                enm_shmcoreserv_details_logs.configTypeId = nhc_config_types.id
                GROUP BY enm_shmcoreserv_details_logs.job_name";

    $reqBind = SqlTableBuilder::init()
              ->name('enm_nhcserv_details_logs')
              ->tables(array(ENM_SHMCORESERV_DETAILS_LOGS, StatsDB::SITES, 'nhc_config_types'))
              ->where($where)
              ->addSimpleColumn(JOB_TYPE, J_TYPE)
              ->addSimpleColumn(JOB_NAME, JOB_TITLE)
              ->addSimpleColumn('IFNULL(enm_shmcoreserv_details_logs.netypes, "NA")', 'NE Types')
              ->addSimpleColumn(NO_OF_NETWORK_ELEMENTS, 'No.of Network Elements')
              ->addSimpleColumn($START_TIME_DB, 'Start Time')
              ->addSimpleColumn('enm_shmcoreserv_details_logs.time', 'End Time')
              ->addColumn('total_time', DURATION, 'Total Time taken', DDPTable::FORMAT_MSEC)
              ->addSimpleColumn(PROGRESS_PERCENTAGE, 'Progress Percentage')
              ->addSimpleColumn(STATUS, 'Status')
              ->addSimpleColumn(RESULT, RESULT)
              ->addSimpleColumn('IFNULL(healthy_nodes_count, "NA")', 'Nodes Health status Count')
              ->addColumn('ne_job_creation', T_NEJOB_CREATION, 'Duration Of NeJobsCreation', DDPTable::FORMAT_MSEC)
              ->addSimpleColumn('nhc_config_types.name', 'Configuration Type')
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader("NHC Job Details", 2, "", "", 'enm_nhcserv_details_logs');
}


function showNhcProfiles() {
    $tbl = new ModelledTable( 'TOR/shm/nhc_profiles', 'logTable' );
    if ( $tbl->hasRows() ) {
        echo drawHeader('Node Health Check Profiles', 2, 'NhcProfiles');
        echo $tbl->getTable();

        echo addLineBreak();
        $selfLink = array( ModelledTable::URL => makeSelfLink() );
        $tbl = new ModelledTable( 'TOR/shm/nhc_profiles_netype_count', 'neTypeCount', $selfLink );
        echo $tbl->getTable();
    }
}

function plotNETypeCountMonth( $selectedStr ) {
    global $date, $site;

    $dbTables = array( 'enm_nhc_profiles_log', StatsDB::SITES, 'ne_types' );

    $where = "enm_nhc_profiles_log.siteid = sites.id
              AND sites.name = '%s'
              AND enm_nhc_profiles_log.netypeid = ne_types.id
              AND enm_nhc_profiles_log.netypeid IN (%s)
              GROUP BY DATE(time), seriesid
              ";

    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
          ->title('Health Check Profile Metrics')
          ->type(SqlPlotParam::STACKED_BAR)
          ->barwidth(60)
          ->yLabel(T_COUNT)
          ->makePersistent()
          ->forceLegend()
          ->disableUserAgg()
          ->addQuery(
              'DATE(time)',
              array ("COUNT(*)" => T_COUNT),
              $dbTables,
              $where,
              array('site', 'selected'),
              NETYPES
          )
          ->build();

    $fromDate=date('Y-m-d', strtotime($date.'- 1 month'));
    $extraArgs = "&selected=$selectedStr";
    $id = $sqlParamWriter->saveParams($sqlParam);

    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59", $extraArgs));
}

function displayImportExport() {

    $types = array('IMPORT', 'EXPORT');

    foreach ( $types as $type ) {
        $graphs = array();
        if ( $type == 'IMPORT' ) {
            $head = "NHC Profile Import Requests";
            $helpBubble = 'nhc_import';
        }elseif ( $type == 'EXPORT' ) {
            $head = "NHC Profile Export Requests";
            $helpBubble = 'nhc_export';
        }

        drawHeader( $head, 2, $helpBubble );
        $graphParams = array( 'type' => $type );
        $modelledGraph = new ModelledGraph( 'TOR/shm/nhc_profiles_requests', $helpBubble );
        $graphs[] = $modelledGraph->getImage( $graphParams );

        $modelledGraph = new ModelledGraph( 'TOR/shm/nhc_profiles_method', $helpBubble );
        $graphs[] = $modelledGraph->getImage( $graphParams );
        plotGraphs( $graphs );
    }
}

function mainFlow() {
    global $TABLE_SHM_FILESIZE_LOGS, $webargs, $date, $site;
    $statsDB = new StatsDB();

    displayPlatformLevelJobsLink($statsDB);
    $nodelevel = $statsDB->queryRow($statsDB->hasDataQuery( 'enm_shmcoreserv_job_instrumentation_logs' ));
    if ( $nodelevel[0] > 0 ) {
         $shmnodelevel = makeLink(NODE_TABLE, 'Node Level SHM Job Details', array('NodeLevelError'=> '1'));
        echo "<br><br>$shmnodelevel";
    }
    displayJobDetailsCount();
    if ( $statsDB->hasData('enm_shmcoreserv_details_logs') ) {
        displayShmGraph($statsDB);
    }
    displayShmTable($statsDB);
    if ( $statsDB->hasData($TABLE_SHM_FILESIZE_LOGS) ) {
        displayJobPackageSizeDetails($statsDB, BACKUP, 'Uploaded Backup File Size');
        echo addLineBreak();
        plotShmFilesize(BACKUP, 'Uploaded Backup File Size');
    }
    displayNhcTable($statsDB);
    displayMainJobGraphs($statsDB);
    displayWaitingJobGraphs($statsDB);
    displayLogGraphs($statsDB);
    showNhcProfiles();
    if ( $statsDB->hasData('enm_nhc_profiles_requests') ) {
        displayImportExport();
    }
}

if ( issetURLParam(ACT) ) {
    if ( requestValue(ACT) === 'plotNETypeCountMonth') {
        $selectedStr = requestValue('selected');
        plotNETypeCountMonth( $selectedStr );
    } else {
        echo "Error";
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";

