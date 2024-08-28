<?php
$pageTitle = "httpd";

const COUNT = 'count';
const REQUESTS_LABEL = 'Requests';
const LABEL = 'label';
const UI_APP_TABLE = 'enm_ui_app';
const ACTION = 'action';
const APP_UI = 'Daily Request Totals by Application UI';

$DISABLE_UI_PARAMS = array(ACTION);

include "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';

function showUsingApps($statsDB,$uriId) {
    $row = $statsDB->queryRow("SELECT uri FROM enm_apache_uri WHERE id = $uriId");
    echo "<H1>Applications using $row[0]</H1>\n";
    $table = getAppTable($uriId,false);
    echo $table->getTable();
}

function showUsedURI($statsDB,$appId) {
    $row = $statsDB->queryRow("SELECT name FROM enm_apache_app_names WHERE id = $appId");
    echo "<H1>URI used by $row[0]</H1>\n";
    $table = getUriTable($appId,false);
    echo $table->getTable();
}

function getUriTable($appId,$addMenu) {
    global $site,$date,$webargs;

    $builder = SqlTableBuilder::init()
             ->name("uri_table")
             ->tables(array("enm_apache_requests"))
             ->join("enm_apache_uri", "enm_apache_requests.uriid = enm_apache_uri.id")
             ->join(StatsDB::SITES, "enm_apache_requests.siteid = sites.id")
             ->join(
                 "enm_servicegroup_names",
                 "enm_apache_requests.sgid = enm_servicegroup_names.id",
                 SqlTable::LEFT_OUTER_JOIN
             )
             ->addHiddenColumn('uriid', 'enm_apache_requests.uriid')
             ->addSimpleColumn('enm_apache_uri.uri', "URI")
             ->addSimpleColumn('enm_servicegroup_names.name', "Provider")
             ->paginate()
             ->sortBy(COUNT, DDPTable::SORT_DESC);

    $where = "sites.name = '$site' AND enm_apache_requests.date = '$date'";
    if ( is_null($appId) ) {
        $builder
            ->addColumn(COUNT, 'SUM(enm_apache_requests.requests)', REQUESTS_LABEL)
            ->groupBy(array('enm_apache_requests.uriid'));
    } else {
        $builder
            ->addColumn(COUNT, 'enm_apache_requests.requests', REQUESTS_LABEL)
            ->addSimpleColumn('enm_apache_requests.method', 'Method');
        $where .= " AND enm_apache_requests.appid = $appId";
    }
    $builder
        ->where($where);

    if ( $addMenu ) {
        $builder->ctxMenu(
            'show',
            false,
            array( 'apps' => 'Show using Applications' ),
            makeSelfLink(),
            'uriid'
        );
    }

    return $builder->build();
}

function getAppTable($uriId,$addMenu) {
    global $site,$date,$webargs;

    $columns = array(
        array( 'key' => 'appid', 'db' => 'enm_apache_requests.appid', 'visible' => false ),
        array( 'key' => 'app', 'db' => 'enm_apache_app_names.name', LABEL => 'Name' ),
    );
    $where = "
enm_apache_requests.siteid = sites.id AND sites.name = '$site' AND
enm_apache_requests.date = '$date' AND
enm_apache_requests.appid = enm_apache_app_names.id";
    if ( is_null($uriId) ) {
        $dbCol = 'SUM(enm_apache_requests.requests)';
        $where = $where . " AND enm_apache_app_names.name != 'NA' GROUP BY enm_apache_requests.appid";
    } else {
        $dbCol = 'enm_apache_requests.requests';
        $where = $where ." AND enm_apache_requests.uriid = $uriId";
        $columns[] = array( 'key' => 'method', 'db' => 'enm_apache_requests.method', LABEL => 'Method' );
    }
    $columns[] = array( 'key' => COUNT, 'db' => $dbCol, LABEL => REQUESTS_LABEL, 'formatter' => 'ddpFormatNumber');

    $tableOptions = array(
        'order' => array( 'by' => COUNT, 'dir' => 'DESC'),
        'rowsPerPage' => 25,
        'rowsPerPageOptions' => array(50, 100)
    );
    if ( $addMenu ) {
        $tableOptions['ctxMenu'] = array('key' => 'show',
                                         'multi' => false,
                                         'menu' => array( 'uri' => 'Show used URI'),
                                         'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                         'col' => 'appid');
    }

    return new SqlTable(
        "app_table",
        $columns,
        array( 'enm_apache_requests', 'enm_apache_app_names', StatsDB::SITES ),
        $where,
        TRUE,
        $tableOptions
    );
}

function showSlotGraphs($httpdInstances) {
    global $site,$date;

    drawHeaderWithHelp("Server Status", 1, "httpd_server_status");

    $sqlParamWriter = new SqlPlotParam();

    $where = "
enm_apache_slots.siteid = sites.id AND sites.name = '%s' AND
enm_apache_slots.serverid = %d";
    $dbTables = array( "enm_apache_slots", "sites" );
    foreach ( $httpdInstances as $hostname => $serverid ) {
        echo "<H2>$hostname</H2>\n";
        $row = array();

        $sqlParam = SqlPlotParamBuilder::init()
                  ->title('httpd slot status')
                  ->type(SqlPlotParam::STACKED_BAR)
                  ->barwidth(900)
                  ->yLabel("")
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array(
                          'keepalive' => 'Keep Alive',
                          'sendreply' => 'Sending Reply',
                          'waitingconn' => 'Waiting for Connection',
                          'other' => 'Other'
                      ),
                      $dbTables,
                      $where,
                      array('site','serverid')
                  )
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            500,
            320,
            "serverid=$serverid"
        );

        $sqlParam = SqlPlotParamBuilder::init()
                  ->title('httpd connection source')
                  ->type(SqlPlotParam::STACKED_BAR)
                  ->barwidth(900)
                  ->yLabel("")
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array(
                          'internal' => 'Internal Connections',
                          'external' => 'External Connections'
                      ),
                      $dbTables,
                      $where,
                      array('site','serverid')
                  )
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            500,
            320,
            "serverid=$serverid"
        );

        $graphTable = new HTML_Table("border=0");
        $graphTable->addRow($row);
        echo $graphTable->toHTML();
    }
}

function plotAppOpen($date) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));

    $where = "
enm_ui_app.siteid = sites.id AND sites.name = '%s' AND
enm_ui_app.uiappid = enm_ui_app_names.id";
    $dbTables = array( UI_APP_TABLE, 'sites', 'enm_ui_app_names' );
    $sqlParam = SqlPlotParamBuilder::init()
              ->title('Applications Opened')
              ->type(SqlPlotParam::STACKED_BAR)
              ->yLabel("")
              ->makePersistent()
              ->addQuery(
                  'date',
                  array( 'enm_ui_app.num' => 'num' ),
                  $dbTables,
                  $where,
                  array('site'),
                  'enm_ui_app_names.name'
              )
              ->build();
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59"));
}

function getApacheLogs($logDir) {
    global $php_webroot;
    global $debug;

    // Do the check for both apache log types
    $apacheLogTypes = array(
        "access",
        "error"
    );
    foreach ($apacheLogTypes as $apacheLogType) {
            $apacheLogs = array();
        if ($handle = opendir($logDir)) {
            while (false !== ($fileName = readdir($handle))) {
                $matches = array();
                if (preg_match("/^apache_$apacheLogType\.log(\.\d*\.gz|\.gz)/", $fileName)) {
                    $filePath = "$logDir/$fileName";
                    $fileSize = (string) round(filesize($filePath) / 1048576);
                    $logFile  = makeLinkForURL(getUrlForFile($filePath), $fileName);
                    array_push($matches, $logFile, $fileSize);
                    array_push($apacheLogs, $matches);
                }
            }
        }
        if (count($apacheLogs) < 25) {
            $table = new DDPTable("$apacheLogType", array(
                array(
                    'key' => '0',
                    LABEL => 'Log File'
                ),
                array(
                    'key' => '1',
                    LABEL => 'File Size(MB)',
                    'sortOptions' => array( 'sortFunction' => 'forceSortAsNums')

                )
            ), array(
                'data' => $apacheLogs
            ));
        } else {
            $table = new DDPTable("$apacheLogType", array(
                array(
                    'key' => '0',
                    LABEL => 'Log File'
                ),
                array(
                    'key' => '1',
                    LABEL => 'File Size(MB)',
                    'sortOptions' => array( 'sortFunction' => 'forceSortAsNums')
                )
            ), array(
                'data' => $apacheLogs
            ), array(
                'rowsPerPage' => 25,
                'rowsPerPageOptions' => array(
                    50,
                    100,
                    250,
                    500,
                    1000
                )
            ));
        }
        // Capitalise the first letter of the Apache log type when presenting
        echo $table->getTableWithHeader(
            "Apache " . ucfirst($apacheLogType) . " Log",
            2,
            "",
            "",
            "Apache" . ucfirst($apacheLogType) . "LogsHelp"
        );
    }
}

function mainFlow($statsDB) {
    global $site,$date, $webargs, $rootdir;

    $apacheURLs = array(
        makeAnchorLink("uri_table_anchor", 'Daily Request Totals by URI'),
        makeAnchorLink("appui_anchor", APP_UI),
        makeAnchorLink("ui_apps_anchor", 'Application Opens'),
        makeAnchorLink('ApacheAccessLogsHelp_anchor', 'Apache Access Log'),
        makeAnchorLink('ApacheErrorLogsHelp_anchor', 'Apache Error Log')
    );
    echo makeHTMLList($apacheURLs);


    drawHeaderWithHelp("httpd Instances", 1, "httpd_instances");

    $httpdInstances = enmGetServiceInstances($statsDB, $site, $date, "httpd");

    $serverIds=implode(",", array_values($httpdInstances));
    $where = "
proc_stats.serverid IN ($serverIds) AND proc_stats.serverid = servers.id AND
proc_stats.procid = process_names.id AND process_names.name = '/usr/sbin/httpd.worker'";
    $dbTables = array( "proc_stats FORCE INDEX(serverTimeIdx)", "process_names", StatsDB::SERVERS );
    $sqlParam = SqlPlotParamBuilder::init()
              ->title('httpd instances')
              ->type(SqlPlotParam::STACKED_AREA)
              ->barwidth(300)
              ->yLabel("")
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  array( 'nproc' => 'instances' ),
                  $dbTables,
                  $where,
                  array('site'),
                  'servers.hostname'
              )
              ->build();

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320);


    $where = "
enm_apache_srv_unavail.siteid = sites.id AND sites.name = '$site' AND
enm_apache_srv_unavail.date = '$date' AND enm_apache_srv_unavail.uriid = enm_apache_uri.id";
    $srvunavail = SqlTableBuilder::init()
                 ->name("service_unavailable")
                 ->tables(array("enm_apache_srv_unavail", "enm_apache_uri", StatsDB::SITES))
                 ->where($where)
                 ->addSimpleColumn("enm_apache_srv_unavail.num", "Count")
                 ->addSimpleColumn("enm_apache_uri.uri", "URI" )
                 ->paginate()
                 ->build();
    echo $srvunavail->getTableWithHeader("Service Unavailable", 2, "", "", "service_unavailable");

    if ( $statsDB->hasData( "enm_apache_slots" ) ) {
        showSlotGraphs($httpdInstances);
    }

    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_apache_requests, enm_apache_app_names, sites
WHERE
 enm_apache_requests.siteid = sites.id AND sites.name = '$site' AND
 enm_apache_requests.appid = enm_apache_app_names.id AND enm_apache_app_names.name != 'NA' AND
 enm_apache_requests.date = '$date'");
    $hasXTorApplication = $row[0] > 0;

    $uriTable = getUriTable(NULL,$hasXTorApplication);
    echo $uriTable->getTableWithHeader("Daily Request Totals by URI", 1, "", "", "uri_table");

    if ( $hasXTorApplication ) {
        $appTable = getAppTable(NULL,true);
        echo $appTable->getTableWithHeader(APP_UI, 2, "DDP_Bubble_276_ENM_Apache_Totals_Application_UI", "", "appui");
    }

    $where = $statsDB->where(UI_APP_TABLE, "date", true) . " AND enm_ui_app.uiappid = enm_ui_app_names.id";
    $appTable = SqlTableBuilder::init()
              ->name("ui_apps")
              ->tables(array(UI_APP_TABLE, "enm_ui_app_names", StatsDB::SITES))
              ->where($where)
              ->addSimpleColumn("enm_ui_app_names.name", "Application" )
              ->addSimpleColumn("enm_ui_app.num", "Count")
              ->addSimpleColumn("enm_ui_app.n_users", "Distinct Users")
              ->paginate()
              ->ctxMenu(
                  ACTION,
                  true,
                  array( 'plotappopen' => 'Plot for last month'),
                  fromServer(PHP_SELF) . "?" . $webargs,
                  'id'
              )
              ->build();
    if ( $appTable->hasRows() ) {
        echo $appTable->getTableWithHeader("Application Opens", 2, "", "", "ui_apps");
    }

    $logdir = null;
    if ( file_exists($rootdir . "/enmlogs") ) {
        $logdir = $rootdir . "/enmlogs";
    } elseif ( file_exists($rootdir . "/logs") ) {
        $logdir = $rootdir . "/logs";
    }
    if ( ! is_null($logdir) ) {
        getApacheLogs($logdir);
    }
}

$statsDB = new StatsDB();
if ( isset($_REQUEST['show'] ) ) {
    $showType = $_REQUEST['show'];
    $selected = $_REQUEST['selected'];
    if ( $showType == 'apps' ) {
        showUsingApps($statsDB,$selected);
    } else {
        showUsedURI($statsDB,$selected);
    }
} elseif (isset($_REQUEST[ACTION])) {
    if ( $_REQUEST[ACTION] === 'plotappopen' ) {
        plotAppOpen($date);
    }
} else {
    mainFlow($statsDB);
}

include PHP_ROOT . "/common/finalise.php";
?>
