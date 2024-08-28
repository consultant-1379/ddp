<?php
$pageTitle = "Versant Database Statistics";

$YUI_DATATABLE = TRUE;

include "common/init.php";
require_once "SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

require_once 'HTML/Table.php';

class SutLogs extends DDPObject {
    var $cols = array(
        'instance' => 'Instance',
        'time' => 'Time',
        'duration' => 'Duration',
    );
    var $title = "SUT Logs";

    function __construct() {
        parent::__construct("sutlogs");
    }

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
servers.hostname AS instance,
DATE_FORMAT(time,'%H:%i:%s') AS time,
duration AS duration
FROM enm_sutlogs, sites, servers
WHERE
enm_sutlogs.siteid = sites.id AND sites.name = '$site' AND
enm_sutlogs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_sutlogs.serverid = servers.id
GROUP BY servers.hostname
";

        $this->populateData($sql);
        return $this->data;
    }
}

class VersantVolumeInformation extends DDPObject {
    var $cols = array(
        'volumename' => 'Volume Name',
        'sysname' => 'System Name',
        'size' => 'Size (MB)',
        'free' => 'Free Space (MB)',
        'percentagefree' => '% Free Extents',
    );
    var $title = "Versant Database Summary";
    var $defaultOrderBy = "volumename";
    var $defaultOrderDir = "ASC";
    var $vdbId;

    function __construct($vdbId) {
        parent::__construct("volumename");
        $this->vdbId = $vdbId;
    }

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT vdb_volumes.volumename as 'Volume Name',
vdb_volumes.sysname as 'System Name',
floor(size/1024) as 'Size (MB)',
floor(free/1024) as 'Free Space (MB)',
percentagefree as '% Free Extents'
FROM vdb, vdb_names, vdb_volumes, sites
WHERE
vdb.siteid = sites.id AND sites.name = '$site' AND
vdb.date = '$date' AND
vdb.vdbid = $this->vdbId AND vdb.vdbid = vdb_names.id AND
vdb.vdbvolumeid = vdb_volumes.id
";

        $this->populateData($sql);
        return $this->data;
    }
}

function getLocksTable($statsDB, $site, $date, $vdbId) {
    $table = new HTML_Table("border=1");

    $row = $statsDB->queryRow("
SELECT vdb_names.name, total,outstanding,deadlocks,conflicts,requests,objects
FROM vdb_locks, vdb_names, sites
WHERE vdb_locks.siteid = sites.id
AND sites.name = '$site'
AND vdb_locks.date = '$date'
AND vdb_locks.vdbid = $vdbId
AND vdb_locks.vdbid = vdb_names.id");
    if ( $row ) {
        $table->addRow( array("Total", $row[1]) );
        $table->addRow( array("Outstanding", $row[2]) );
        $table->addRow( array("Deadlocks", $row[3]) );
        $table->addRow( array("Conflicts", $row[4]) );
        $table->addRow( array("Requests", $row[5]) );
        $table->addRow( array("Objects", $row[6]) );
    }

    return $table;
}

function getProfileTable($statsDB, $site, $date, $vdbId) {
    $table = new HTML_Table("border=1");

    $row = $statsDB->queryRow("
SELECT vdb_names.name,sysvol,plogvol,llogvol,extent_size,logging,locking,commit_flush,polling_optimize,async_buffer_cleaner,async_logger,event_registration_mode,event_msg_mode,event_msg_transient_queue_size,bf_dirty_high_water_mark,bf_dirty_low_water_mark,class,db_timeout,prof_index,llog_buf_size,lock_wait_timeout,max_page_buffs,multi_latch,plog_buf_size,heap_size,heap_arena_size,heap_arena_size_increment,heap_arena_trim_threshold,heap_max_arenas,heap_arena_segment_merging,transaction,user,volume,stat,assertion_level,trace_entries,trace_file,versant_be_dbalogginglevel,be_syslog_level,blackbox_trace_comps,treat_vstr_of_1b_as_string_in_query
FROM vdb_profile_types, versant_dbs, vdb_names, sites
WHERE vdb_profile_types.id = versant_dbs.profileid
AND versant_dbs.siteid = sites.id
AND sites.name = '$site'
AND versant_dbs.date = '$date'
AND versant_dbs.vdbid = $vdbId
AND versant_dbs.vdbid = vdb_names.id");
    if ( $row ) {
        $sysvol = $row[1];
        $plogvol = $row[2];
        $llogvol = $row[3];
        $extent_size = $row[4];
        $logging = $row[5];
        $locking = $row[6];
        $commit_flush = $row[7];
        $polling_optimize = $row[8];
        $async_buffer_cleaner = $row[9];
        $async_logger = $row[10];
        $event_registration_mode = $row[11];
        $event_msg_mode = $row[12];
        $event_msg_transient_queue_size = $row[13];
        $bf_dirty_high_water_mark = $row[14];
        $bf_dirty_low_water_mark = $row[15];
        $class = $row[16];
        $db_timeout = $row[17];
        $prof_index = $row[18];
        $llog_buf_size = $row[19];
        $lock_wait_timeout = $row[20];
        $max_page_buffs = $row[21];
        $multi_latch = $row[22];
        $plog_buf_size = $row[23];
        $heap_size = $row[24];
        $heap_arena_size = $row[25];
        $heap_arena_size_increment = $row[26];
        $heap_arena_trim_threshold = $row[27];
        $heap_max_arenas = $row[28];
        $heap_arena_segment_merging = $row[29];
        $transaction = $row[30];
        $user = $row[31];
        $volume = $row[32];
        $stat = $row[33];
        $assertion_level = $row[34];


        $table->addRow( array("sysvol", $sysvol) );
        $table->addRow( array("plogvol", $plogvol) );
        $table->addRow( array("llogvol", $llogvol) );
        $table->addRow( array("extent_size", $extent_size) );
        $table->addRow( array("logging", $logging) );
        $table->addRow( array("locking", $locking) );
        $table->addRow( array("commit_flush", $commit_flush) );
        $table->addRow( array("polling_optimize", $polling_optimize) );
        $table->addRow( array("async_buffer_cleaner", $async_buffer_cleaner) );
        $table->addRow( array("async_logger", $async_logger) );
        $table->addRow( array("event_registration_mode", $event_registration_mode) );
        $table->addRow( array("event_msg_mode", $event_msg_mode) );
        $table->addRow( array("event_msg_transient_queue_size", $event_msg_transient_queue_size) );
        $table->addRow( array("bf_dirty_high_water_mark", $bf_dirty_high_water_mark) );
        $table->addRow( array("bf_dirty_low_water_mark", $bf_dirty_low_water_mark) );
        $table->addRow( array("class", $class) );
        $table->addRow( array("db_timeout", $db_timeout) );
        $table->addRow( array("prof_index", $prof_index) );
        $table->addRow( array("llog_buf_size", $llog_buf_size) );
        $table->addRow( array("lock_wait_timeout", $lock_wait_timeout) );
        $table->addRow( array("max_page_buffs", $max_page_buffs) );
        $table->addRow( array("multi_latch", $multi_latch) );
        $table->addRow( array("plog_buf_size", $plog_buf_size) );
        $table->addRow( array("heap_size", $heap_size) );
        if ( $heap_arena_size != "" ){ $table->addRow( array("heap_arena_size", $heap_arena_size) ); }
        if ( $heap_arena_size_increment != "" ){ $table->addRow( array("heap_arena_size_increment", $heap_arena_size_increment) ); }
        if ( $heap_arena_trim_threshold != "" ){ $table->addRow( array("heap_arena_trim_threshold", $heap_arena_trim_threshold) ); }
        if ( $heap_max_arenas != "" ){ $table->addRow( array("heap_max_arenas", $heap_max_arenas) ); }
        if ( $heap_arena_segment_merging != "" ) { $table->addRow( array("heap_arena_segment_merging", $heap_arena_segment_merging) ); }
        $table->addRow( array("transaction", $transaction) );
        $table->addRow( array("user", $user) );
        $table->addRow( array("volume", $volume) );
        $table->addRow( array("stat", $stat) );
        $table->addRow( array("assertion_level", $assertion_level) );
    }

    return $table;
}

function getConnectionsTable($statsDB, $site, $date, $vdbId) {
    $table = new HTML_Table("border=1");

    $statsDB->query("
SELECT
process_names.name AS process,
vdb_connections.count AS connections
FROM vdb_connections, process_names, sites
WHERE
vdb_connections.siteid = sites.id AND sites.name = '$site' AND
vdb_connections.date = '$date' AND
vdb_connections.vdbid = $vdbId AND
vdb_connections.procid = process_names.id
ORDER BY connections DESC");
    if ( $statsDB->getNumRows() > 0 ) {
        $table->addRow( array ("Process","Connections"), null, 'th' );
        while($row = $statsDB->getNextRow()) {
            $table->addRow($row);
        }
    }

    return $table;
}

function showVersantVolumeInformation($databaseVolumeInformationHelpMessage,$vdbId) {
    $table = new VersantVolumeInformation($vdbId);
    drawHeaderWithHelp( "Database Volume Information", 2, "databaseVolumeInformationHelpMessage", $databaseVolumeInformationHelpMessage);
    echo $table->getSortableHtmlTable();
}

function showDatabaseSummaryInformation($statsDB, $site, $date, $vdbId) {
    drawHeaderWithHelp( "Database  Summary Information", 2, "databaseSummaryInfoHelpMessage", "DDP_Bubble_185_database_summary_info_help_message");
    $table = new HTML_Table("border=1");
    $row = $statsDB->queryRow("
SELECT
format(SUM(count),0) as 'Total MOs in DB'
FROM
mo,
sites
WHERE
mo.siteid = sites.id AND
sites.name = '$site' AND
mo.date = '$date' AND
mo.vdbid = $vdbId;");

    if ( $row ) {
        $table->addRow( array ("Name","Value"), null, 'th' );
        $table->addRow( array ("Total MOs in DB", $row));
    }

    $rowTwo = $statsDB->queryRow("
SELECT
ROUND(AVG((located)/(datareads+located)*100),2) as Avgcache,
format(Sum(datareads),0) as TotalRead,
format(Sum(datawrites),0) as TotalWrite,
format(Sum(plogwrite/(1024*1024*1024)),0) as TotalPhysicalLogWrites,
format(Sum(llogwrite/(1024*1024*1024)),0) as TotalLogicalLogWrites,
format(Sum(xactcommit),0) as TotalCommits,
format(Sum(lktimeout),0) as TotalLockTimeouts,
format(Sum(lkwait),0) as TotalLockWaits,
format(Sum(xactrollback),0) as TotalRollBacks
FROM
vdb_stats,
sites
WHERE
vdb_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
vdb_stats.siteid = sites.id AND
sites.name = '$site' AND
vdb_stats.vdbid = $vdbId;");

    if ( $rowTwo ) {
        $avgCache = $rowTwo[0];
        $totalRead = $rowTwo[1];
        $totalWrite = $rowTwo[2];
        $totalPhysicalLogWrites = $rowTwo[3];
        $totalLogicalLogWrites = $rowTwo[4];
        $totalCommits = $rowTwo[5];
        $totalLockTimeouts = $rowTwo[6];
        $totalLockWaits = $rowTwo[7];
        $totalRollbacks = $rowTwo[8];

        $table->addRow( array ("Average Cache Hit %", $avgCache));
        $table->addRow( array ("Total 16KB pages read", $totalRead));
        $table->addRow( array ("Total 16KB pages written", $totalWrite));
        $table->addRow( array ("Total Physical log writes (GB)", $totalPhysicalLogWrites));
        $table->addRow( array ("Total Logical log writes (GB)", $totalLogicalLogWrites));
        $table->addRow( array ("Total Commits", $totalCommits));
        $table->addRow( array ("Total Lock Timeouts", $totalLockTimeouts));
        $table->addRow( array ("Total Lock Waits", $totalLockWaits));
        $table->addRow( array ("Total Rollbacks", $totalRollbacks));
    }
    echo $table->toHTML();
}

function showClientStats($statsDB,$srvGrpsStr) {
    global $site, $date;

    $quotedStr = implode("','",explode(",",$srvGrpsStr));
    $statsDB->query("
SELECT enm_servicegroup_instances.serverid
FROM enm_servicegroup_instances, sites, enm_servicegroup_names
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serviceid = enm_servicegroup_names.id AND enm_servicegroup_names.name IN ('$quotedStr')");
    $srvIds = array();
    while ( $row = $statsDB->getNextRow() ) {
        $srvIds[] = $row[0];
    }

    $idStrs = implode(",",$srvIds);

    $cols = array( 'kbReceived' => 'KB Received', 'kbSent' => 'KB Sent',
                   'objectsReceived' => 'Objects Received', 'objectsSent' => 'Objects Sent',
                   'rpcCount' => 'RPC Count' );
    $graphTable = new HTML_Table('border=0');
    $sqlParamWriter = new SqlPlotParam();
    foreach ( $cols as $dbCol => $label ) {
        $sqlParam = array( 'title'      => $label,
                           'type' => 'sb',
                           'sb.barwidth' => 900,
                           'ylabel'     => '',
                           'useragg'    => 'true',
                           'persistent' => 'false',
                           'querylist' =>
                           array(
                               array(
                                   'timecol' => 'time',
                                   'multiseries'=> 'servers.hostname',
                                   'whatcol'    => array( $dbCol => $label ),
                                   'tables'  => "versant_client, sites, servers",
                                   'where'   => "versant_client.siteid = sites.id AND sites.name = '%s' AND versant_client.serverid = servers.id AND versant_client.serverid IN (%s)",
                                   'qargs'   => array( 'site', 'srvids' )
                               )
                           )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320, "srvids=$idStrs")));
    }
    echo $graphTable->toHTML();
}

function mainFlow($statsDB) {
    global $site, $date, $webargs, $debug, $rootdir, $webroot;

    $oss=$_GET["oss"];
    $vdbId=$_GET["vdbid"];
    $vdbName=$_GET["vdbname"];

    if ( $oss != "tor" ) {
        $row = $statsDB->queryRow("
SELECT vdb_names.name,size,free
FROM vdb, vdb_names, sites
WHERE
vdb.siteid = sites.id AND sites.name = '$site' AND
vdb.date = '$date' AND
vdb.vdbid = $vdbId AND vdb.vdbid = vdb_names.id");
        $vdbName = $row[0];
        $vdbSize = $row[1];
        $vdbFree = $row[2];
    }

    $locksTable = getLocksTable($statsDB, $site, $date, $vdbId);
    $profileTable = getProfileTable($statsDB, $site, $date, $vdbId);
    $connTable = getConnectionsTable($statsDB, $site, $date, $vdbId);

    $row = $statsDB->queryRow("
SELECT COUNT(*) FROM versant_client, sites
WHERE
versant_client.siteid = sites.id AND sites.name = '$site' AND
versant_client.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    $hasVersantClientStats = $row[0] > 0;

    echo "<H2>$vdbName</H2>\n";
    echo "<ul>\n";

    //
    // See if we have the vdb_locks entries
    //
    if ( $locksTable->getRowCount() > 0 ) {
        echo " <li><a href=\"#Locks\">Locks</a></li>\n";
    }
    //
    // See if we have the vdb_profile_types entries
    //
    if ( $profileTable->getRowCount() > 0 ) {
        echo " <li><a href=\"#Profile\">Profile</a></li>\n";
    }

    //
    // See if we have the vdb_stats entries
    //
    $logscount = 0;
    $row = $statsDB->queryRow("
SELECT count(*) FROM vdb_logs, sites
WHERE vdb_logs.siteid = sites.id
AND sites.name = '$site'
AND vdb_logs.date = '$date'
AND vdb_logs.vdbid = $vdbId
");
    $logscount = $row[0];
    if ( $logscount > 0 ) {
        echo '<li><a href="vdb_logs.php?' . $webargs .'&vdbid=' . $vdbId . '">Logs</a></li>' . "\n";
    }

    $mocount = 0;
    $row = $statsDB->queryRow("
SELECT sum(count) FROM mo, sites
WHERE mo.siteid = sites.id
AND sites.name = '$site'
AND mo.date = '$date'
AND mo.vdbid = $vdbId
");
    $mocount = $row[0];
    if ( $mocount > 0 ) {
        echo '<li><a href="molist.php?' . $webargs .'&vdbid=' . $vdbId . '&vdbName=' . $vdbName . '">Managed Object List</a></li>' . "\n";
    }

    $fullpath = DATADIR . "/TOR/clustered_data/versant/$vdbName" . ".LOGFILE";
    global $serverName;
    if ( $debug ) { echo "<pre>fullpath: $fullpath</pre>\n"; }
    $fileURL = null;
    if ( file_exists($fullpath) ) {
        $fileURL = getUrlForFile($fullpath);
    } elseif ( file_exists($fullpath . ".gz") ) {
        $fileURL = getUrlForFile($fullpath . ".gz");
    }
    if ( ! is_null($fileURL) ) {
        echo "<li>" . makeLinkForURL($fileURL, "DB LOGFILE") . "</li>\n";
    }

    if ( $connTable->getRowCount() > 0 ) {
        echo "<li><a href=\"#Conn\">Connections</a></li>\n";
    }

    if ( $hasVersantClientStats ) {
        echo "<li><a href=\"#Client\">Versant Client Stats</a></li>\n";
    }

    echo "</ul>\n";


    if ( $oss == "tor" ) {
        drawHeaderWithHelp("SUT Logs", 2, "sutlogs", "DDP_Bubble_217_ENM_Sut_logs");
        $sut_logs = new SutLogs();
        echo $sut_logs->getSortableHtmlTable();
        $databaseVolumeInformationHelpMessage = "DDP_Bubble_183_enm_database_volume_information";
        showVersantVolumeInformation($databaseVolumeInformationHelpMessage,$vdbId);
        showDatabaseSummaryInformation($statsDB, $site, $date, $vdbId);
    } else {
        echo "<a name=DatabaseVolumeInformation>\n";
        $databaseVolumeInformationHelpMessage = "DDP_Bubble_184_other_database_volume_information";
        drawHeaderWithHelp( "Database Volume Information", 2, "databaseVolumeInformationHelpMessage", $databaseVolumeInformationHelpMessage);

        $dbSizeMB = floor($vdbSize/1024);
        $dbFreeMB = floor($vdbFree/1024);
        echo <<<END
<table border>
 <tr> <td><b>DB Name</b></td> <td>$vdbName</td> </tr>
 <tr> <td><b>DB Size (MB)</b></td> <td>$dbSizeMB</td> </tr>
 <tr> <td><b>DB Free (MB)</b></td> <td>$dbFreeMB</td> </tr>
END;

        if ( $mocount != 0 ) {
            echo "<tr> <td><b>Num of MO</b></td> <td>".$mocount."</td> </tr>";
        }
        echo "</table>\n";
    }

    $vdb_webroot = $webroot . "/vdb/" . $vdbName;
    $vdb_rootdir = $rootdir . "/vdb/" . $vdbName;

    if ( $debug ) { echo "<pre>vdb_webroot=$vdb_webroot vdb_rootdir=$vdb_rootdir</pre>\n"; }

    $graphParams =
                 array(
                     'pageio' => array('title' => 'Data Page I/O',
                                       'ylabel' => 'pages',
                                       'whatcol' => array( 'datareads' => 'Read', 'datawrites' => 'Written' )),
                     'logwrites' => array('title' => 'Log Writes',
                                          'ylabel' => 'bytes',
                                          'whatcol' => array('llogwrite' => 'Logical', 'plogwrite' => 'Physical' )),
                     'cachehit' => array( 'title' => 'Cache Hit Ratio',
                                          'ylabel' => '%',
                                          'whatcol' => array( '(100*located) / ( datareads + located )' => 'Cache Hit' )),
                     'commits' => array( 'title' => 'Commits',
                                         'ylabel' => '',
                                         'whatcol' => array( 'xactcommit' => 'Commits' ) ),
                     'rollbacks' => array('title' => 'Rollbacks',
                                          'ylabel' => '',
                                          'whatcol' => array('xactrollback' => 'Rollbacks')),
                     'activetx' => array('title' => 'Active Transactions',
                                         'ylabel' => '',
                                         'whatcol' => array('xactactive' => 'Active Transactions')),
                     'running' => array('title' => 'Running Threads',
                                        'ylabel' => '',
                                        'whatcol' => array('threads' => 'Running Threads')),
                     'locks' => array('title' => 'Locks',
                                      'ylabel' => '',
                                      'whatcol' => array('lktimeout' => 'Timeouts',
                                                         'lkwait' => 'Waits')),
                     'chkpnts' => array('title' => 'Checkpoints',
                                        'ylabel' => '#',
                                        'whatcol' => array('checkpts' => 'Checkpoints')),
                     'llogend' => array('title' => 'Logical Log Buffer Ends',
                                        'ylabel' => '#',
                                        'whatcol' => array('llogend' => 'Logical Log Buffer Ends')),
                     'llogfull' => array('title' => 'Logical Log Buffer Full',
                                         'ylabel' => '#',
                                         'whatcol' => array('llogfull' => 'Logical Log Buffer Full'))
                             
                 );

    $graphTable = new HTML_Table('border=0');
    $sqlParamWriter = new SqlPlotParam();
    foreach ( $graphParams as $key => $params ) {
        $sqlParam = array( 'title'      => $params['title'],
                           'ylabel'     => $params['ylabel'],
                           'useragg'    => 'true',
                           'persistent' => 'true',
                           'querylist' =>
                           array(
                               array(
                                   'timecol' => 'time',
                                   'whatcol'    => $params['whatcol'],
                                   'tables'  => "vdb_stats, sites",
                                   'where'   => "vdb_stats.siteid = sites.id AND sites.name = '%s' AND vdb_stats.vdbid = '%d'",
                                   'qargs'   => array( 'site', 'vdbid' )
                               )
                           )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "vdbid=$vdbId")));
    }
    drawHeaderWithHelp( "$vdbName Versant Backend Statistics", 2, "VersantHelp", "DDP_Bubble_182_versant_help_bubble" );
    echo $graphTable->toHTML();

    if ( $locksTable->getRowCount() > 0 ) {
        echo "<a name=Locks>\n";
        echo "<H2>"; drawHelpLink("lockhelp"); echo "Locks</H2>\n";
        drawHelp("lockhelp", "Versant DB Locks",
                 "Displays the information relating to the locks on the $vdbName Versant DB." .
                 "This information is retrieved from the $vdbName.locks file.");
        echo $locksTable->toHTML();
    }

    if ( $profileTable->getRowCount() > 0 ) {
        echo "<a name=Profile>\n";
        echo "<H2>"; drawHelpLink("profilehelp"); echo "Profile</H2>\n";
        drawHelp("profilehelp", "Versant DB Profile",
                 "Displays the information relating to the profile parameters of the $vdbName Versant DB." .
                 "This information is retrieved from the $vdbName.profile file.");
        echo $profileTable->toHTML();
    }

    if ( $connTable->getRowCount() > 0 ) {
        echo "<a name=Conn>\n";
        echo "<H2>Connections"; drawHelpLink("connhelp"); echo "</H2>\n";
        drawHelp("connhelp", "Versant Coonections",
                 " Displays the processes that have open connections to the database  " .
                 "This information is retrieved from an lsof taken during the MAKETAR/STOP phase");
        echo $connTable->toHTML();
    }

    if ( $hasVersantClientStats ) {
        echo "<H2 id ='Client'></H2>";
        drawHeaderwithHelp("Versant Clients",2,"VersantClientsHelp","DDP_Bubble_281_ENM_Versant_Clients_Stats_Help");
        $where = "
versant_client.siteid = sites.id AND sites.name = '$site' AND
versant_client.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
versant_client.serverid = enm_servicegroup_instances.serverid AND
enm_servicegroup_instances.siteid = sites.id AND enm_servicegroup_instances.date = '$date' AND
enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
GROUP BY sg";
        $table =
               new SqlTable("client_stats",
                            array(
                                array( 'key' => 'sg', 'db' => 'enm_servicegroup_names.name', 'label' => 'Service Group' ),
                                array( 'key' => 'kbRx', 'db' => 'SUM(kbReceived)', 'label' => 'KB Received' ),
                                array( 'key' => 'kbTx', 'db' => 'SUM(kbSent)', 'label' => 'KB Sent' ),
                                array( 'key' => 'objRx', 'db' => 'SUM(objectsReceived)', 'label' => 'Objects Received' ),
                                array( 'key' => 'objTx', 'db' => 'SUM(objectsSent)', 'label' => 'Objects Sent' ),
                                array( 'key' => 'rpc', 'db' => 'SUM(rpcCount)', 'label' => 'RPC Count' )
                            ),
                            array( 'versant_client', 'sites', 'enm_servicegroup_instances', 'enm_servicegroup_names' ),
                            $where,
                            TRUE,
                            array('order' => array( 'by' => 'rpc', 'dir' => 'DESC'),
                                  'ctxMenu' => array('key' => 'client',
                                                     'multi' => true,
                                                     'menu' => array( 'plot' => 'Plot' ),
                                                     'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                                     'col' => 'sg'
                                  )
                            )
               );
        echo $table->getTable();
    }
}

$statsDB = new StatsDB();

if ( isset($_REQUEST['client'] ) ) {
    showClientStats($statsDB,$_REQUEST['selected']);
} else {
    mainFlow($statsDB);
}
$statsDB->disconnect();

include "common/finalise.php";
?>
