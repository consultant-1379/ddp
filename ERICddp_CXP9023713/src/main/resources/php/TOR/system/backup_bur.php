<?php

$pageTitle = "Backup Stage Info";
$pageType = "backup_stages";
if ( isset( $_GET['page_type'] ) ) {
    if ( $_GET['page_type'] == 'backup_throughput' ) {
        $pageTitle = "Backup Throughput Info";
        $pageType = "backup_throughput";
    } else if ( $_GET['page_type'] == 'backup_stages' ) {
        $pageTitle = "Backup Stage Info";
        $pageType = "backup_stages";
    }
}

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/JsPlot.php";
require_once 'HTML/Table.php';

class EnmBackupStageStats extends DDPObject {
    var $cols = array(
                      'start_time'   => 'Start Time',
                      'end_time'     => 'End Time',
                      'backup_stage' => 'Backup Stage',
                      'status'       => 'Status',
                      'duration'     => 'Duration (hh:mm:ss)'
                      );
    var $keyword = "";

    public function __construct($keyword) {
        parent::__construct("bur" . $keyword);
        $this->keyword = $keyword;
    }

    function getData()  {
        global $date, $site;
        $keyword = $this->keyword;
        $sql = "
SELECT
    CAST(enm_bur_backup_stage_stats.start_time AS time) AS start_time,
    IFNULL( CAST(enm_bur_backup_stage_stats.end_time AS time), 'NA' ) AS end_time,
    enm_bur_backup_stage_names.backup_stage_name AS backup_stage,
    IF(enm_bur_backup_stage_statuses.backup_stage_status_name = '', 'NA', backup_stage_status_name) AS status,
    IFNULL( SEC_TO_TIME(enm_bur_backup_stage_stats.duration), 'NA')  AS duration
FROM
    enm_bur_backup_stage_stats,
    enm_bur_backup_stage_names,
    enm_bur_backup_stage_statuses,
    enm_bur_backup_keywords,
    sites
WHERE
    enm_bur_backup_stage_stats.siteid = sites.id AND
    enm_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_stage_stats.backup_stage_id = enm_bur_backup_stage_names.id AND
    enm_bur_backup_stage_stats.backup_stage_status_id = enm_bur_backup_stage_statuses.id AND
    enm_bur_backup_keywords.backup_keyword = '$keyword' AND
    sites.name = '$site' AND
    enm_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY start_time";

        $this->populateData($sql);
        return $this->data;
    }
}

class SfsBackupStageStats extends DDPObject {
    var $cols = array(
                      'start_time'   => 'Start Time',
                      'end_time'     => 'End Time',
                      'backup_stage' => 'Backup Stage',
                      'status'       => 'Status',
                      'duration'     => 'Duration (hh:mm:ss)'
                      );
    var $keyword = "";

    public function __construct($keyword) {
        parent::__construct("bur" . $keyword);
        $this->keyword = $keyword;
    }

    function getData()  {
        global $date, $site;
        $keyword = $this->keyword;
        $sql = "
SELECT
    CAST(sfs_bur_backup_stage_stats.start_time AS time) AS start_time,
    IFNULL( CAST(sfs_bur_backup_stage_stats.end_time AS time), 'NA' ) AS end_time,
    enm_bur_backup_stage_names.backup_stage_name AS backup_stage,
    IF(enm_bur_backup_stage_statuses.backup_stage_status_name = '', 'NA', backup_stage_status_name) AS status,
    IFNULL( SEC_TO_TIME(sfs_bur_backup_stage_stats.duration), 'NA')  AS duration
FROM
    sfs_bur_backup_stage_stats,
    enm_bur_backup_stage_names,
    enm_bur_backup_stage_statuses,
    enm_bur_backup_keywords,
    sites
WHERE
    sfs_bur_backup_stage_stats.siteid = sites.id AND
    sfs_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_stage_stats.backup_stage_id = enm_bur_backup_stage_names.id AND
    sfs_bur_backup_stage_stats.backup_stage_status_id = enm_bur_backup_stage_statuses.id AND
    enm_bur_backup_keywords.backup_keyword = '$keyword' AND
    sites.name = '$site' AND
    sfs_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY start_time";

        $this->populateData($sql);
        return $this->data;
    }
}

class EnmBackupThroughputDailyTotals extends DDPObject {
    var $cols = array(
                      array('key' => 'start_time', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'end_time', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'backup_keyword', 'label' => 'Backup'),
                      array('key' => 'overall_throughput_mbps', 'label' => 'Overall Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'overall_parallel_throughput_mbps', 'label' => 'Overall Parallel Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'no_of_filesystems', 'label' => 'Num of Filesystems', 'formatter' => 'ddpFormatNumber'),
                      array('key' => 'total_size', 'label' => 'Total Size (MB)', 'formatter' => 'ddpFormatNumber')
                      );

    function __construct() {
        parent::__construct("EnmbackupThroughputDailyTotals");
    }

    var $title = "Daily Totals";

    function getData()  {
        global $site, $date;

        $sql = "
SELECT
    IFNULL( MIN(enm_bur_backup_throughput_stats.start_time), 'NA' ) AS 'start_time',
    IFNULL( MAX(enm_bur_backup_throughput_stats.end_time), 'NA' ) AS 'end_time',
    IFNULL(enm_bur_backup_keywords.backup_keyword, 'All') AS 'backup_keyword',
    IFNULL( ROUND(
        SUM( CASE
                WHEN enm_bur_backup_throughput_stats.start_time IS NOT NULL AND
                     enm_bur_backup_throughput_stats.end_time IS NOT NULL
                THEN enm_bur_backup_throughput_stats.filesystem_used_size
                ELSE 0
             END ) /
        SUM( CASE
                WHEN enm_bur_backup_throughput_stats.start_time = enm_bur_backup_throughput_stats.end_time
                THEN 1
                ELSE TIMESTAMPDIFF(SECOND, enm_bur_backup_throughput_stats.start_time, enm_bur_backup_throughput_stats.end_time)
             END )
        , 2 ), 'NA' ) AS 'overall_throughput_mbps',
    IFNULL( ROUND(
        SUM(enm_bur_backup_throughput_stats.filesystem_used_size) /
        CASE
            WHEN MIN(enm_bur_backup_throughput_stats.start_time) = MAX(enm_bur_backup_throughput_stats.end_time)
            THEN 1
            ELSE TIMESTAMPDIFF( SECOND, MIN(enm_bur_backup_throughput_stats.start_time), MAX(enm_bur_backup_throughput_stats.end_time) )
        END
        , 2 ), 'NA' ) AS 'overall_parallel_throughput_mbps',
    CASE
        WHEN MIN(enm_bur_backup_throughput_stats.start_time) = MAX(enm_bur_backup_throughput_stats.end_time)
        THEN 1
        WHEN MIN(enm_bur_backup_throughput_stats.start_time) IS NULL OR
             MAX(enm_bur_backup_throughput_stats.end_time) IS NULL
        THEN 'NA'
        ELSE TIMESTAMPDIFF( SECOND, MIN(enm_bur_backup_throughput_stats.start_time), MAX(enm_bur_backup_throughput_stats.end_time) )
    END AS 'overall_parallel_backup_time',
    COUNT(enm_bur_backup_throughput_stats.backup_mount_point_id) AS 'no_of_filesystems',
    SUM(enm_bur_backup_throughput_stats.filesystem_used_size) AS 'total_size'
FROM
    sites,
    enm_bur_backup_keywords,
    enm_bur_backup_throughput_stats
WHERE
    enm_bur_backup_throughput_stats.siteid = sites.id AND
    enm_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sites.name = '$site' AND
    enm_bur_backup_throughput_stats.date = '$date'
GROUP BY enm_bur_backup_keywords.backup_keyword WITH ROLLUP";

        $this->populateData($sql);

        $overallParallelBkpTimeTotal = 0;
        $overallBackupSizeTotal = 0;
        foreach ($this->data as $key => $row) {
            if ( $row['backup_keyword'] != 'All' && $row['overall_parallel_backup_time'] != 'NA' ) {
                $overallParallelBkpTimeTotal += $row['overall_parallel_backup_time'];
                $overallBackupSizeTotal += $row['total_size'];
            }
        }
        if ( $overallParallelBkpTimeTotal <= 0 ) {
            $overallParallelBkpTimeTotal = 1;
        }
        $overallParallelThruputTotal = round($overallBackupSizeTotal/$overallParallelBkpTimeTotal, 2);

        foreach ($this->data as &$row) {
            if ( $row['backup_keyword'] == 'All' ) {
                $row['start_time'] = "Totals";
                $row['end_time'] = "";
                $row['overall_parallel_throughput_mbps'] = $overallParallelThruputTotal;
            }
        }

        return $this->data;
    }
}

class SfsBackupThroughputDailyTotals extends DDPObject {
    var $cols = array(
                      array('key' => 'start_time', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'end_time', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'backup_keyword', 'label' => 'Backup'),
                      array('key' => 'overall_throughput_mbps', 'label' => 'Overall Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'overall_parallel_throughput_mbps', 'label' => 'Overall Parallel Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'no_of_filesystems', 'label' => 'Num of Filesystems', 'formatter' => 'ddpFormatNumber'),
                      array('key' => 'total_size', 'label' => 'Total Size (MB)', 'formatter' => 'ddpFormatNumber')
                      );

    function __construct() {
        parent::__construct("SfsbackupThroughputDailyTotals");
    }

    var $title = "Daily Totals";

    function getData()  {
        global $site, $date;

        $sql = "
SELECT
    IFNULL( MIN(sfs_bur_backup_throughput_stats.start_time), 'NA' ) AS 'start_time',
    IFNULL( MAX(sfs_bur_backup_throughput_stats.end_time), 'NA' ) AS 'end_time',
    IFNULL(enm_bur_backup_keywords.backup_keyword, 'All') AS 'backup_keyword',
    IFNULL( ROUND(
        SUM( CASE
                WHEN sfs_bur_backup_throughput_stats.start_time IS NOT NULL AND
                     sfs_bur_backup_throughput_stats.end_time IS NOT NULL
                THEN sfs_bur_backup_throughput_stats.filesystem_used_size
                ELSE 0
             END ) /
        SUM( CASE
                WHEN sfs_bur_backup_throughput_stats.start_time = sfs_bur_backup_throughput_stats.end_time
                THEN 1
                ELSE TIMESTAMPDIFF(SECOND, sfs_bur_backup_throughput_stats.start_time, sfs_bur_backup_throughput_stats.end_time)
             END )
        , 2 ), 'NA' ) AS 'overall_throughput_mbps',
    IFNULL( ROUND(
        SUM(sfs_bur_backup_throughput_stats.filesystem_used_size) /
        CASE
            WHEN MIN(sfs_bur_backup_throughput_stats.start_time) = MAX(sfs_bur_backup_throughput_stats.end_time)
            THEN 1
            ELSE TIMESTAMPDIFF( SECOND, MIN(sfs_bur_backup_throughput_stats.start_time), MAX(sfs_bur_backup_throughput_stats.end_time) )
        END
        , 2 ), 'NA' ) AS 'overall_parallel_throughput_mbps',
    CASE
        WHEN MIN(sfs_bur_backup_throughput_stats.start_time) = MAX(sfs_bur_backup_throughput_stats.end_time)
        THEN 1
        WHEN MIN(sfs_bur_backup_throughput_stats.start_time) IS NULL OR
             MAX(sfs_bur_backup_throughput_stats.end_time) IS NULL
        THEN 'NA'
        ELSE TIMESTAMPDIFF( SECOND, MIN(sfs_bur_backup_throughput_stats.start_time), MAX(sfs_bur_backup_throughput_stats.end_time) )
    END AS 'overall_parallel_backup_time',
    COUNT(sfs_bur_backup_throughput_stats.backup_mount_point_id) AS 'no_of_filesystems',
    SUM(sfs_bur_backup_throughput_stats.filesystem_used_size) AS 'total_size'
FROM
    sites,
    enm_bur_backup_keywords,
    sfs_bur_backup_throughput_stats
WHERE
    sfs_bur_backup_throughput_stats.siteid = sites.id AND
    sfs_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sites.name = '$site' AND
    sfs_bur_backup_throughput_stats.date = '$date'
GROUP BY enm_bur_backup_keywords.backup_keyword WITH ROLLUP";

        $this->populateData($sql);

        $overallParallelBkpTimeTotal = 0;
        $overallBackupSizeTotal = 0;
        foreach ($this->data as $key => $row) {
            if ( $row['backup_keyword'] != 'All' && $row['overall_parallel_backup_time'] != 'NA' ) {
                $overallParallelBkpTimeTotal += $row['overall_parallel_backup_time'];
                $overallBackupSizeTotal += $row['total_size'];
            }
        }
        if ( $overallParallelBkpTimeTotal <= 0 ) {
            $overallParallelBkpTimeTotal = 1;
        }
        $overallParallelThruputTotal = round($overallBackupSizeTotal/$overallParallelBkpTimeTotal, 2);

        foreach ($this->data as &$row) {
            if ( $row['backup_keyword'] == 'All' ) {
                $row['start_time'] = "Totals";
                $row['end_time'] = "";
                $row['overall_parallel_throughput_mbps'] = $overallParallelThruputTotal;
            }
        }

        return $this->data;
    }
}

class BackupThroughputDetails extends DDPObject {
    var $cols = array(
                      array('key' => 'start_time', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'end_time', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'backup_keyword', 'label' => 'Backup'),
                      array('key' => 'host', 'label' => 'Host'),
                      array('key' => 'throughput_mb_per_sec', 'label' => 'Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'backup_mount_point', 'label' => 'Backup Mount Point'),
                      array('key' => 'filesystem', 'label' => 'Filesystem'),
                      array('key' => 'filesystem_used_size', 'label' => 'Filesystem Used Size (MB)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'filesystem_size', 'label' => 'Filesystem Total Size (MB)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );
    var $backupKeyword = "";

    function __construct($backupKeyword = '%', $backupSerialNo = 1) {
        parent::__construct("BackupThroughputDetails_" . $backupSerialNo);
        $this->backupKeyword = $backupKeyword;
    }

    var $title = "Throughput Details";

    function getData()  {
        global $site, $date;

        $sql = "
SELECT
    IFNULL(enm_bur_backup_throughput_stats.start_time, 'NA') AS 'start_time',
    IFNULL(enm_bur_backup_throughput_stats.end_time, 'NA') AS 'end_time',
    enm_bur_backup_keywords.backup_keyword AS 'backup_keyword',
    servers.hostname AS 'host',
    IFNULL( ROUND(enm_bur_backup_throughput_stats.throughput_mb_per_sec, 2), 'NA' ) AS 'throughput_mb_per_sec',
    IF(enm_bur_backup_mount_points.backup_mount_point = '', 'NA', backup_mount_point) AS 'backup_mount_point',
    IF(enm_bur_filesystems.fs_name = '', 'NA', fs_name) AS 'filesystem',
    IFNULL(enm_bur_backup_throughput_stats.filesystem_used_size, 'NA') AS 'filesystem_used_size',
    IFNULL(enm_bur_backup_throughput_stats.filesystem_size, 'NA') AS 'filesystem_size'
FROM
    sites,
    servers,
    enm_bur_filesystems,
    enm_bur_backup_keywords,
    enm_bur_backup_mount_points,
    enm_bur_backup_throughput_stats
WHERE
    enm_bur_backup_throughput_stats.siteid = sites.id AND
    enm_bur_backup_throughput_stats.serverid = servers.id AND
    enm_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_throughput_stats.backup_mount_point_id = enm_bur_backup_mount_points.id AND
    enm_bur_backup_throughput_stats.filesystem_id = enm_bur_filesystems.id AND
    servers.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword like '$this->backupKeyword' AND
    sites.name = '$site' AND
    enm_bur_backup_throughput_stats.date = '$date'
ORDER BY start_time";

        $this->populateData($sql);
        $this->defaultOrderBy = "start_time";
        $this->defaultOrderDir = "ASC";

        return $this->data;
    }
}

class SfsBackupThroughputDetails extends DDPObject {
    var $cols = array(
                      array('key' => 'start_time', 'label' => 'Start Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'end_time', 'label' => 'End Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'backup_keyword', 'label' => 'Backup'),
                      array('key' => 'host', 'label' => 'Host'),
                      array('key' => 'throughput_mb_per_sec', 'label' => 'Throughput (MB/sec)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'backup_mount_point', 'label' => 'Backup Mount Point'),
                      array('key' => 'filesystem', 'label' => 'Filesystem'),
                      array('key' => 'filesystem_used_size', 'label' => 'Filesystem Used Size (MB)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'filesystem_size', 'label' => 'Filesystem Total Size (MB)', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );
    var $backupKeyword = "";

    function __construct($backupKeyword = '%', $backupSerialNo = 1) {
        parent::__construct("SfsBackupThroughputDetails_" . $backupSerialNo);
        $this->backupKeyword = $backupKeyword;
    }

    var $title = "Throughput Details";

    function getData()  {
        global $site, $date;

        $sql = "
SELECT
    IFNULL(sfs_bur_backup_throughput_stats.start_time, 'NA') AS 'start_time',
    IFNULL(sfs_bur_backup_throughput_stats.end_time, 'NA') AS 'end_time',
    enm_bur_backup_keywords.backup_keyword AS 'backup_keyword',
    servers.hostname AS 'host',
    IFNULL( ROUND(sfs_bur_backup_throughput_stats.throughput_mb_per_sec, 2), 'NA' ) AS 'throughput_mb_per_sec',
    IF(enm_bur_backup_mount_points.backup_mount_point = '', 'NA', backup_mount_point) AS 'backup_mount_point',
    IF(enm_bur_filesystems.fs_name = '', 'NA', fs_name) AS 'filesystem',
    IFNULL(sfs_bur_backup_throughput_stats.filesystem_used_size, 'NA') AS 'filesystem_used_size',
    IFNULL(sfs_bur_backup_throughput_stats.filesystem_size, 'NA') AS 'filesystem_size'
FROM
    sites,
    servers,
    enm_bur_filesystems,
    enm_bur_backup_keywords,
    enm_bur_backup_mount_points,
    sfs_bur_backup_throughput_stats
WHERE
    sfs_bur_backup_throughput_stats.siteid = sites.id AND
    sfs_bur_backup_throughput_stats.serverid = servers.id AND
    sfs_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_throughput_stats.backup_mount_point_id = enm_bur_backup_mount_points.id AND
    sfs_bur_backup_throughput_stats.filesystem_id = enm_bur_filesystems.id AND
    servers.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword like '$this->backupKeyword' AND
    sites.name = '$site' AND
    sfs_bur_backup_throughput_stats.date = '$date'
ORDER BY start_time";

        $this->populateData($sql);
        $this->defaultOrderBy = "start_time";
        $this->defaultOrderDir = "ASC";

        return $this->data;
    }
}

function getThroughputPerfQueryList($backupKeyword = "", $statsDB) {
    global $site, $date, $debug;

    //Get the list of all hosts associated with the given backup
    $hosts = array();
    $statsDB->query("
SELECT
    DISTINCT servers.hostname
FROM
    enm_bur_backup_throughput_stats,
    enm_bur_backup_keywords,
    servers,
    sites
WHERE
    enm_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_throughput_stats.serverid = servers.id AND
    enm_bur_backup_throughput_stats.siteid = sites.id AND
    servers.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword = '$backupKeyword' AND
    enm_bur_backup_throughput_stats.date = '$date' AND
    sites.name = '$site'");

    while ( $row_data = $statsDB->getNextRow() ) {
        $hosts[] = $row_data[0];
    }

    $queryList = array();
    foreach ($hosts as $index => $host) {
        $query = array(
                       'timecol' => 'start_time',
                       'whatcol' => array('ROUND(enm_bur_backup_throughput_stats.throughput_mb_per_sec, 2)' => $host),
                       'tables' => "enm_bur_backup_throughput_stats, enm_bur_backup_keywords, servers, sites",
                       'where' => "enm_bur_backup_throughput_stats.siteid = sites.id AND
                                   enm_bur_backup_throughput_stats.serverid = servers.id AND
                                   enm_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
                                   servers.siteid = sites.id AND
                                   enm_bur_backup_throughput_stats.start_time IS NOT NULL AND
                                   enm_bur_backup_throughput_stats.throughput_mb_per_sec IS NOT NULL AND
                                   enm_bur_backup_keywords.backup_keyword = '$backupKeyword' AND
                                   servers.hostname = '$host' AND
                                   sites.name = '$site' AND
                                   enm_bur_backup_throughput_stats.date = '$date'",
                       );
        $queryList[] = $query;
    }

    return $queryList;
}

function SFSgetThroughputPerfQueryList($backupKeyword = "", $statsDB) {
    global $site, $date, $debug;

    //Get the list of all hosts associated with the given backup
    $hosts = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_filesystems.fs_name
FROM
    sfs_bur_backup_throughput_stats,
    enm_bur_backup_keywords,
    enm_bur_filesystems,
    servers,
    sites
WHERE
    sfs_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_throughput_stats.serverid = servers.id AND
    sfs_bur_backup_throughput_stats.siteid = sites.id AND
    servers.siteid = sites.id AND
    sfs_bur_backup_throughput_stats.filesystem_id = enm_bur_filesystems.id AND
    enm_bur_backup_keywords.backup_keyword = '$backupKeyword' AND
    sfs_bur_backup_throughput_stats.date = '$date' AND
    sites.name = '$site'");

    while ( $row_data = $statsDB->getNextRow() ) {
        $hosts[] = $row_data[0];
    }

    $queryList = array();
    foreach ($hosts as $index => $host) {
        $query = array(
                       'timecol' => 'sfs_bur_backup_throughput_stats.start_time',
                       'whatcol' => array('ROUND(sfs_bur_backup_throughput_stats.throughput_mb_per_sec, 2)' => $host),
                       'tables' => "sfs_bur_backup_throughput_stats, enm_bur_backup_keywords,
                                   enm_bur_filesystems, servers, sites",
                       'where' => "sfs_bur_backup_throughput_stats.siteid = sites.id AND
                                   sfs_bur_backup_throughput_stats.serverid = servers.id AND
                                   sfs_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
                                   servers.siteid = sites.id AND
                                   sfs_bur_backup_throughput_stats.filesystem_id = enm_bur_filesystems.id AND
                                   sfs_bur_backup_throughput_stats.start_time IS NOT NULL AND
                                   sfs_bur_backup_throughput_stats.throughput_mb_per_sec IS NOT NULL AND
                                   enm_bur_backup_keywords.backup_keyword = '$backupKeyword' AND
                                   enm_bur_filesystems.fs_name = '$host' AND
                                   sites.name = '$site' AND
                                   sfs_bur_backup_throughput_stats.date = '$date'",
                       );
        $queryList[] = $query;
    }

    return $queryList;
}

function ENMmainFlowBackupStages($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site, $web_temp_dir;

    $backupStageKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_backup_keywords.backup_keyword
FROM
    enm_bur_backup_stage_stats,
    enm_bur_backup_keywords,
    sites
WHERE
    enm_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_stage_stats.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword != '' AND
    sites.name = '$site' AND
    enm_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY enm_bur_backup_stage_stats.start_time");

    while ( $row_data = $statsDB->getNextRow() ) {
        $backupStageKeywords[] = $row_data[0];
    }

    $backupThruputKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_backup_keywords.backup_keyword
FROM
    enm_bur_backup_throughput_stats,
    enm_bur_backup_keywords,
    sites
WHERE
    enm_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_throughput_stats.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword != '' AND
    sites.name = '$site' AND
    enm_bur_backup_throughput_stats.date = '$date'
ORDER BY enm_bur_backup_throughput_stats.start_time");

    while ( $row_data = $statsDB->getNextRow() ) {
        $backupThruputKeywords[$row_data[0]] = 1;
    }

 echo "<H2 id ='ENMBackupHelp_anchor'>ENM Backup</H2>";
    if ( count($backupThruputKeywords) > 0 ) {
        echo "<ul>\n";
        echo "  <li><span title='Click here to go to the page containing the backup throughput details'>" .
             "<a href=\"$php_webroot/TOR/system/backup_bur.php?$webargs&page_type=backup_throughput#ENMThroughput_anchor\">ENM Backup Throughput Details</a></span></li>\n";
        echo "</ul><br/>\n";
    }

    foreach ($backupStageKeywords as $value) {
        $timings = array();
        $row = array();
        $dbSizeHelpBubble = "DDP_Bubble_301_ENM_Bur_Logs";
        drawHeaderWithHelp(" Backup " . $value, 2, "BackupStages_$value", $dbSizeHelpBubble);
        if ( array_key_exists($value, $backupThruputKeywords) ) {
            echo "<ul>\n";
            echo "  <li><span title='Click here to go to the table containing the throughput details of this backup'>" .
                 "<a href=\"$php_webroot/TOR/system/backup_bur.php?$webargs&page_type=backup_throughput#$value" . "_anchor\">Throughput Details</a></span></li>\n";
            echo "</ul>\n";
        }

        $backupStageStats = new EnmBackupStageStats($value);
        $statsDB->query("
SELECT
    enm_bur_backup_stage_names.backup_stage_name AS backup_stage,
    SUM(enm_bur_backup_stage_stats.duration) AS duration,
    enm_bur_backup_stage_statuses.backup_stage_status_name AS backup_status
FROM
    enm_bur_backup_stage_stats,
    enm_bur_backup_stage_names,
    enm_bur_backup_stage_statuses,
    enm_bur_backup_keywords,
    sites
WHERE
    enm_bur_backup_stage_stats.siteid = sites.id AND
    enm_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_stage_stats.backup_stage_id = enm_bur_backup_stage_names.id AND
    enm_bur_backup_stage_stats.backup_stage_status_id = enm_bur_backup_stage_statuses.id AND
    enm_bur_backup_stage_names.backup_stage_name != 'BOSsystem_backupOperation' AND
    enm_bur_backup_stage_stats.duration IS NOT NULL AND
    enm_bur_backup_keywords.backup_keyword = '$value' AND
    sites.name = '$site' AND
    enm_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_bur_backup_stage_names.backup_stage_name");

        while ( $row = $statsDB->getNextNamedRow() ) {
            $duration = $row['duration'];
            $row['backup_status'] = str_replace(' ', '', $row['backup_status']);
            $str_tmp = $row['backup_stage'] . "_" . $row['backup_status'];
            $timings[] = array($str_tmp, $duration);
        }

        $filename = tempnam($web_temp_dir, "");
        file_put_contents( $filename, json_encode(array('name' => 'Stages', 'data' => $timings)) );

        $sqlParam = array(
            'title' => "$value",
            'type' => 'pie',
            'ylabel' => "",
            'useragg' => 'false',
            'persistent' => 'false',
            'seriesfile' => $filename
        );

        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 400, 400);
        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow( array($backupStageStats->getHtmlTableStr(),
                             $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", false, 400, 400) ) );
        echo $graphTable->toHTML();
        echo "<br/>";
    }
}


function ENMmainFlowBackupThroughput($statsDB) {
    global $site, $date, $php_webroot, $webargs, $debug;

    echo "<h1 id='ENMThroughput_anchor'>ENM Backup Throughput Details</h1>\n";
    //Display the 'Daily Totals' table
    drawHeaderWithHelp("Daily Totals", 2, "dailyTotalsHelp", "DDP_Bubble_338_BUR_Backup_Throughput_Daily_Totals");
    $backupThroughputDailyTotals = new EnmBackupThroughputDailyTotals();
    echo $backupThroughputDailyTotals->getClientSortableTableStr() . "<br/>";

    //Get the list of backups for which throughput details are available for the given day
    $backupThruputKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_backup_keywords.backup_keyword
FROM
    enm_bur_backup_throughput_stats,
    enm_bur_backup_keywords,
    sites
WHERE
    enm_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    enm_bur_backup_throughput_stats.siteid = sites.id AND
    enm_bur_backup_throughput_stats.date = '$date' AND
    sites.name = '$site'
ORDER BY enm_bur_backup_throughput_stats.start_time");

    if ( isset($_GET['all_backups']) ) {
        //Display the throughput details for all the backups and return
        echo "<br/>";
        $thruputTableExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=backup_throughput_details">[Download Excel]</a>';
        drawHeaderWithHelp("Throughput Details (All Backups)", 2, "ThruputAllBckpsHelp", "DDP_Bubble_340_BUR_Backup_Throughput_Details", "", $thruputTableExcelLink);
        $backupThroughputDetails = new BackupThroughputDetails();
        echo $backupThroughputDetails->getClientSortableTableStr(1000, array(2000, 5000, 10000));
        echo "<br/>";
        return;
    }

    echo "<ul>\n";
    while ( $row_data = $statsDB->getNextRow() ) {
        echo "  <li><span title='Click here to go to the table containing the throughput details of this backup'>" .
             "<a href=\"#$row_data[0]" . "_anchor\">Backup $row_data[0]</a></span></li>";
        $backupThruputKeywords[] = $row_data[0];
    }
    if ( count($backupThruputKeywords) > 1 ) {
        echo "  <li><span title='Click here to view the throughput details of all the backups for the given day under one single table'>" .
             "<a href=\"$php_webroot/TOR/system/backup_bur.php?$webargs&page_type=backup_throughput&all_backups=1#ThruputAllBckpsHelp_anchor\">All Backups</a></span></li>";
    }
    echo "</ul><br/>\n";

    //Display the throughput details for each backup, one after the other
    $backupSerialNo = 1;
    foreach ($backupThruputKeywords as $backupKeyword) {
        echo "<h2>Backup $backupKeyword<a name=\"" . $backupKeyword . "_anchor\"></a></h2>\n";

        //Display the throughput details performance graph (scatter plot)
        $queryList = getThroughputPerfQueryList($backupKeyword, $statsDB);
        $sqlPlotParam = array(
            'title' => "Throughput Performance",
            'type' => 'xy',
            'xlabel' => "Start Time",
            'ylabel' => "Throughput (MB/sec)",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => $queryList
        );
        echo '<div id="throughputPerfGraph_' . $backupSerialNo . '" style="height: 400px"></div>' . "\n";
        $tstart = "$date 00:00:00";
        $tend = date( 'Y-m-d', strtotime($date . ' +2 day') ) . ' 23:59:59';
        $jsPlot = new JsPlot();
        $jsPlot->show($sqlPlotParam, 'throughputPerfGraph_' . $backupSerialNo , array(
                                                                                      'tstart' => $tstart,
                                                                                      'tend' => $tend,
                                                                                      'aggType' => 0,
                                                                                      'aggInterval' => 0,
                                                                                      'aggCol' => "",
                                                                                      )
                      );
        echo "<br/>";

        //Display the throughput details table
        $thruputTableExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=backup_throughput_details&backup_keyword=' .
                                 $backupKeyword . '">[Download Excel]</a>';
        drawHeaderWithHelp("Throughput Details", 2, $backupKeyword . "TdHelp", "DDP_Bubble_340_BUR_Backup_Throughput_Details", "", $thruputTableExcelLink);
        $backupThroughputDetails = new BackupThroughputDetails($backupKeyword, $backupSerialNo);
        echo $backupThroughputDetails->getClientSortableTableStr(200, array(500, 1000, 5000));
        echo "<br/>";

        $backupSerialNo++;
    }
}

function SFSmainFlowBackupStages($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site, $web_temp_dir;

    $backupStageKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_backup_keywords.backup_keyword
FROM
    sfs_bur_backup_stage_stats,
    enm_bur_backup_keywords,
    sites
WHERE
    sfs_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_stage_stats.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword != '' AND
    sites.name = '$site' AND
    sfs_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY sfs_bur_backup_stage_stats.start_time");

    while ( $row_data = $statsDB->getNextRow() ) {
        $backupStageKeywords[] = $row_data[0];
    }

    $backupThruputKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_backup_keywords.backup_keyword
FROM
    sfs_bur_backup_throughput_stats,
    enm_bur_backup_keywords,
    sites
WHERE
    sfs_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_throughput_stats.siteid = sites.id AND
    enm_bur_backup_keywords.backup_keyword != '' AND
    sites.name = '$site' AND
    sfs_bur_backup_throughput_stats.date = '$date'
ORDER BY sfs_bur_backup_throughput_stats.start_time");

    while ( $row_data = $statsDB->getNextRow() ) {
        $backupThruputKeywords[$row_data[0]] = 1;
    }
echo "<h2 id='SFSBackupHelp_anchor'>SFS Backup</h2>\n";
    if ( count($backupThruputKeywords) > 0 ) {
        echo "<ul>\n";
        echo "  <li><span title='Click here to go to the page containing the backup throughput details'>" .
             "<a href=\"$php_webroot/TOR/system/backup_bur.php?$webargs&page_type=backup_throughput#SFSThroughput_anchor\">SFS Backup Throughput Details</a></span></li>\n";
        echo "</ul><br/>\n";
    }

    foreach ($backupStageKeywords as $value) {
        $timings = array();
        $row = array();
        $dbSizeHelpBubble = "DDP_Bubble_431_SFS_Bur_Logs";
        drawHeaderWithHelp("Backup " . $value, 2, "BackupStages_$value", $dbSizeHelpBubble);
        if ( array_key_exists($value, $backupThruputKeywords) ) {
            echo "<ul>\n";
            echo "  <li><span title='Click here to go to the table containing the throughput details of this backup'>" .
                 "<a href=\"$php_webroot/TOR/system/backup_bur.php?$webargs&page_type=backup_throughput#$value" . "_anchor\">Throughput Details</a></span></li>\n";
            echo "</ul>\n";
        }

        $backupStageStats = new SfsBackupStageStats($value);
        $statsDB->query("
SELECT
    enm_bur_backup_stage_names.backup_stage_name AS backup_stage,
    SUM(sfs_bur_backup_stage_stats.duration) AS duration,
    enm_bur_backup_stage_statuses.backup_stage_status_name AS backup_status
FROM
    sfs_bur_backup_stage_stats,
    enm_bur_backup_stage_names,
    enm_bur_backup_stage_statuses,
    enm_bur_backup_keywords,
    sites
WHERE
    sfs_bur_backup_stage_stats.siteid = sites.id AND
    sfs_bur_backup_stage_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_stage_stats.backup_stage_id = enm_bur_backup_stage_names.id AND
    sfs_bur_backup_stage_stats.backup_stage_status_id = enm_bur_backup_stage_statuses.id AND
    enm_bur_backup_stage_names.backup_stage_name != 'BOSsystem_backupOperation' AND
    sfs_bur_backup_stage_stats.duration IS NOT NULL AND
    enm_bur_backup_keywords.backup_keyword = '$value' AND
    sites.name = '$site' AND
    sfs_bur_backup_stage_stats.start_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_bur_backup_stage_names.backup_stage_name");

        while ( $row = $statsDB->getNextNamedRow() ) {
            $duration = $row['duration'];
            $row['backup_status'] = str_replace(' ', '', $row['backup_status']);
            $str_tmp = $row['backup_stage'] . "_" . $row['backup_status'];
            $timings[] = array($str_tmp, $duration);
        }

        $filename = tempnam($web_temp_dir, "");
        file_put_contents( $filename, json_encode(array('name' => 'Stages', 'data' => $timings)) );

        $sqlParam = array(
            'title' => "$value",
            'type' => 'pie',
            'ylabel' => "",
            'useragg' => 'false',
            'persistent' => 'false',
            'seriesfile' => $filename
        );

        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 400, 400);
        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow( array($backupStageStats->getHtmlTableStr(),
                             $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", false, 400, 400) ) );
        echo $graphTable->toHTML();
        echo "<br/>";
    }
}

function SFSmainFlowBackupThroughput($statsDB) {
    global $site, $date, $php_webroot, $webargs, $debug;

    echo "<h1 id='SFSThroughput_anchor'>SFS Backup Throughput Details</h1>\n";
    //Display the 'Daily Totals' table
    drawHeaderWithHelp("Daily Totals", 2, "dailyTotalsSfsHelp", "DDP_Bubble_432_SFS_BUR_Backup_Throughput_Daily_Totals");
    $backupThroughputDailyTotals = new SfsBackupThroughputDailyTotals();
    echo $backupThroughputDailyTotals->getClientSortableTableStr() . "<br/>";

    //Get the list of backups for which throughput details are available for the given day
    $backupThruputKeywords = array();
    $statsDB->query("
SELECT
    DISTINCT enm_bur_backup_keywords.backup_keyword
FROM
    sfs_bur_backup_throughput_stats,
    enm_bur_backup_keywords,
    sites
WHERE
    sfs_bur_backup_throughput_stats.backup_keyword_id = enm_bur_backup_keywords.id AND
    sfs_bur_backup_throughput_stats.siteid = sites.id AND
    sfs_bur_backup_throughput_stats.date = '$date' AND
    sites.name = '$site'
ORDER BY sfs_bur_backup_throughput_stats.start_time");

    if ( isset($_GET['all_backups']) ) {
        //Display the throughput details for all the backups and return
        echo "<br/>";
        $thruputTableExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=Sfs_backup_throughput_details">[Download Excel]</a>';
        drawHeaderWithHelp("Throughput Details (All Backups)", 2, "ThruputAllSfsBckpsHelp", "DDP_Bubble_433_SFS_BUR_Backup_Throughput_Details", "", $thruputTableExcelLink);
        $SFSbackupThroughputDetails = new SfsBackupThroughputDetails();
        echo $SFSbackupThroughputDetails->getClientSortableTableStr(1000, array(2000, 5000, 10000));
        echo "<br/>";
        return;
    }

    echo "<ul>\n";
    while ( $row_data = $statsDB->getNextRow() ) {
        echo "  <li><span title='Click here to go to the table containing the throughput details of this backup'>" .
             "<a href=\"#$row_data[0]" . "_anchor\">Backup $row_data[0]</a></span></li>";
        $backupThruputKeywords[] = $row_data[0];
    }
    if ( count($backupThruputKeywords) > 1 ) {
        echo "  <li><span title='Click here to view the throughput details of all the backups for the given day under one single table'>" .
             "<a href=\"$php_webroot/TOR/system/backup_bur.php?$webargs&page_type=backup_throughput&all_backups=1#ThruputAllBckpsHelp_anchor\">All Backups</a></span></li>";
    }
    echo "</ul><br/>\n";

    //Display the throughput details for each backup, one after the other
    $backupSerialNo = 1;
    foreach ($backupThruputKeywords as $backupKeyword) {
        echo "<h2>Backup $backupKeyword<a name=\"" . $backupKeyword . "_anchor\"></a></h2>\n";

        //Display the throughput details performance graph (scatter plot)
        $queryList = SFSgetThroughputPerfQueryList($backupKeyword, $statsDB);
        $sqlPlotParam = array(
            'title' => "Throughput Performance",
            'type' => 'xy',
            'xlabel' => "Start Time",
            'ylabel' => "Throughput (MB/sec)",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => $queryList
        );
        echo '<div id="SfsthroughputPerfGraph_' . $backupSerialNo . '" style="height: 400px"></div>' . "\n";
        $tstart = "$date 00:00:00";
        $tend = date( 'Y-m-d', strtotime($date . ' +2 day') ) . ' 23:59:59';
        $jsPlot = new JsPlot();
        $jsPlot->show($sqlPlotParam, 'SfsthroughputPerfGraph_' . $backupSerialNo , array(
                                                                                      'tstart' => $tstart,
                                                                                      'tend' => $tend,
                                                                                      'aggType' => 0,
                                                                                      'aggInterval' => 0,
                                                                                      'aggCol' => "",
                                                                                      )
                      );
        echo "<br/>";

        //Display the throughput details table
        $thruputTableExcelLink = '<a href="?' . $_SERVER['QUERY_STRING'] . '&format=xls&table=backup_throughput_details&backup_keyword=' .
                                 $backupKeyword . '">[Download Excel]</a>';
        drawHeaderWithHelp("Throughput Details", 2, $backupKeyword . "TdSfsHelp", "DDP_Bubble_433_SFS_BUR_Backup_Throughput_Details", "", $thruputTableExcelLink);
        $SFSbackupThroughputDetails = new SfsBackupThroughputDetails($backupKeyword, $backupSerialNo);
        echo $SFSbackupThroughputDetails->getClientSortableTableStr(200, array(500, 1000, 5000));
        echo "<br/>";

        $backupSerialNo++;
    }
}

if (isset($_GET['format']) && $_GET['format'] == "xls" && isset($_GET['table'])) {
    $table;
        echo "HELLO1 ". $_GET['table'];
    if ( $_GET['table'] == "backup_throughput_details" ) {
        if ( isset($_GET['backup_keyword']) ) {
            $table = new BackupThroughputDetails($_GET['backup_keyword']);
        } else {
            $table = new BackupThroughputDetails();
        }
        $table->title = "Throughput Details";
    } else {
        echo "Invalid table name: " . $_GET['table'];
        exit;
    }

    ob_clean();
    $excel = new ExcelWorkbook();
    $excel->addObject($table);
    $excel->write();
    exit;
}
 
if (isset($_GET['format']) && $_GET['format'] == "xls" && isset($_GET['table'])) {
    $table;
        echo "HELLO2 ". $_GET['table'];
    if ( $_GET['table'] == "Sfs_backup_throughput_details" ) {
        if ( isset($_GET['backup_keyword']) ) {
            $table = new SfsBackupThroughputDetails($_GET['backup_keyword']);
        } else {
            $table = new SfsBackupThroughputDetails();
        }
        $table->title = "Throughput Details";
    } else {
        echo "Invalid table name: " . $_GET['table'];
        exit;
    }

    ob_clean();
    $excel = new ExcelWorkbook();
    $excel->addObject($table);
    $excel->write();
    exit;
}

$statsDB = new StatsDB();
if ( $pageType == 'backup_throughput' ) {
   ENMmainFlowBackupThroughput($statsDB);
   SFSmainFlowBackupThroughput($statsDB);
} else if ( $pageType == 'backup_stages' ) {
    ENMmainFlowBackupStages($statsDB);
    SFSmainFlowBackupStages($statsDB);
}

include_once PHP_ROOT . "/common/finalise.php";

