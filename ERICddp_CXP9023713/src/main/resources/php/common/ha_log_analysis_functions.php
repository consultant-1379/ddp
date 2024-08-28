<?php
const TS = 'timestamp';
const LBL = 'label';
const ST = 'startTime';
const START = 'start';


function showWfLog($id) {
    global $site, $date, $rootdir, $debug, $statsDB;

    // For a workflow that spans midnight, if it's finished, the
    // gz file will be in the date directory for endTime,
    // otherwise it will be in the date directory of startTime
    list($instanceId,$startTime) = explode("@", $id);
    if ( $debug ) {
        print "<pre>showWfLog: id=$id instanceId=$instanceId startTime=$startTime</pre>\n";
    }
    $row = $statsDB->queryRow("
SELECT
 IFNULL(DATE_FORMAT(enm_vnflaf_wfexec.end,'%d%m%y'), DATE_FORMAT(enm_vnflaf_wfexec.start,'%d%m%y'))
FROM enm_vnflaf_wfexec, sites
WHERE
 enm_vnflaf_wfexec.siteid = sites.id AND sites.name = '$site' AND
 enm_vnflaf_wfexec.start = '$startTime' AND
 enm_vnflaf_wfexec.instanceId = '$instanceId'");
    if ( count($row) == 0 ) {
        echo "<b>ERROR: Cound not find entry in DB</b>";
        return;
    }
    $workFlowInstanceFile = dirname($rootdir) . "/" . $row[0] . "/workflows/" . $instanceId . ".gz";
    if ( $debug ) {
        print "<pre>showWfLog: workFlowInstanceFile=$workFlowInstanceFile</pre>\n";
    }
    $gzFH = gzopen($workFlowInstanceFile, 'r');
    if ( $gzFH === false ) {
        echo "<b>Failed to open workflow log for $instanceId</b>\n";
        return;
    }
    $logLine = gzgets($gzFH);
    gzclose($gzFH);
    $wfexec = json_decode($logLine, true);
    if ( $debug > 1 ) {
        echo "<pre>showWfLog: wfexec";
        print_r($wfexec);
        echo "</pre>\n";
    }
    drawHeaderWithHelp($wfexec['definitionName'], 1, "workflowdetails");

    $hasNodeName = false;
    $hasStartEnd = false;
    $rows = array();
    foreach ($wfexec['_tasks'] as $task ) {
        $row = array(
            'log'  => formatLog($task['log'])
        );

        if ( array_key_exists('nodeName', $task) ) {
            $row['node'] = $task['nodeName'];
            $hasNodeName = true;
        }

        if ( array_key_exists(ST, $task) ) {
            $duration = date_diff(date_create($task[ST]), date_create($task['endTime']));
            $row[START] = $task[ST];
            $row['end']  = $task['endTime'];
            $row['duration'] = $duration->format('%H:%I:%S');
            $hasStartEnd = true;
        }

        // If it's the old format (doesn't have startTime) and the log log is
        // empty then, we can't depend on the order so don't show the row
        if ( array_key_exists(START, $row) ||  $row['log'] != '' ) {
            $rows[] = $row;
        }
    }

    $columns = array();
    if ( $hasStartEnd ) {
        $columns[] = array('key' => START, LBL => 'Start Time', 'formatter' => 'ddpFormatTime');
        $columns[] = array('key' => 'end', LBL => 'End Time', 'formatter' => 'ddpFormatTime');
        $columns[] = array('key' => 'duration', LBL => 'Duration');
    }
    if ( $hasNodeName ) {
        $columns[] = array('key' => 'node', LBL => 'Node');
    }
    $columns[] = array('key' => 'log', LBL => 'Log Entry');

    $table = new DDPTable(
        "log",
        $columns,
        array('data' => $rows),
        array('rowsPerPage' => 50, 'rowsPerPageOptions' => array(1000))
    );
    echo $table->getTable();
}

function formatLog($logEntries) {

    if ( count($logEntries) == 0 ||
         ( (count($logEntries) == 1) && is_null($logEntries[0]) ) ) {
        return '';
    }

    $table = new HTML_Table("border=0");
    foreach ($logEntries as $logEntry ) {
        $time = substr($logEntry, 11, 12);
        $txt  = substr($logEntry, 24 );
        $table->addRow( array( $time, $txt ) );
    }
    return $table->toHTML();
}

