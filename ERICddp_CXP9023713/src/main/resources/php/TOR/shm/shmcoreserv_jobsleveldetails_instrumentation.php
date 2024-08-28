<?php
$pageTitle = "Node Level SHM Job Details";

include_once "../../common/init.php";

require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const SITES = 'sites';
const J_TYPE = 'Job Type';
const JOB_TYPE = 'jobType';
const JOB_TITLE = 'Job Name';
const ENM_NODE_DETAILS_LOGS = "enm_shmcoreserv_job_instrumentation_logs";


function displayShmNodeLevelTable($statsDB) {

    $where = $statsDB->where(ENM_NODE_DETAILS_LOGS);
    $where .= " AND enm_shmcoreserv_job_instrumentation_logs.jobType != 'NODE_HEALTH_CHECK'
                AND enm_shmcoreserv_job_instrumentation_logs.netypeid = ne_types.id
                GROUP BY enm_shmcoreserv_job_instrumentation_logs.netypeid,
                enm_shmcoreserv_job_instrumentation_logs.activities,
                enm_shmcoreserv_job_instrumentation_logs.jobName";
    $reqBind = SqlTableBuilder::init()
              ->name(ENM_NODE_DETAILS_LOGS)
              ->tables(array(ENM_NODE_DETAILS_LOGS, 'ne_types', StatsDB::SITES))
              ->where($where)
              ->addSimpleColumn(JOB_TYPE, J_TYPE)
              ->addSimpleColumn('IFNULL(ne_types.name, "NA")', 'Node Type')
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.totalCount', 'Count')
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.jobName', JOB_TITLE)
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.activities', 'Activity List')
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.successCount', 'Success Count')
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.failedCount', 'Failed Count')
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.skippedCount', 'Skipped Count')
              ->addSimpleColumn('enm_shmcoreserv_job_instrumentation_logs.cancelledCount', 'Cancelled Count')
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader("Node Level SHM Job details ", 2, "", "", ENM_NODE_DETAILS_LOGS);


}


function mainFlow() {
    global $debug, $webargs, $php_webroot;
    $statsDB = new StatsDB();
     $mainnodelevel = makeLink(
         '/TOR/shm/shmcoreserv_job_instrumentation.php',
         'Back to Main Jobs Page',
         array('MainLevelError'=> '1')
         );
         echo "$mainnodelevel";
    displayShmNodeLevelTable($statsDB);
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
