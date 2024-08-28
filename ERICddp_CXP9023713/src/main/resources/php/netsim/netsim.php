<?php
$pageTitle = "NetSim";

$YUI_DATATABLE = true;

include "../common/init.php";

require_once PHP_ROOT . "/classes/DDPObject.class.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once "HTML/Table.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

const NETSIM_REQUESTS = 'netsim_requests';
const NETSIM_RESPONSE = 'netsim_response';
const SELECTED = 'selected';
const HOSTNAME = 'hostname';
const SERVERS_HOSTNAME = 'servers.hostname';
const NETSIM_NE_TYPES = 'netsim_netypes';
const REPLACE = 'REPLACE(servers.hostname,"ieatnetsimv","v")';
const NO_BORDER = 'border=0';
const SERVER_IDS = 'serverids';
const DAILY = 'Daily';

drawHeaderWithHelp("NSS Log Server", 1, "NssLogServer", "DDP_Bubble_480_ENM_NSSLOG");
$logfile = $datadir . "/server/nsslog.txt";
if ( file_exists($logfile) ) {
    $loglink = file_get_contents($logfile);
    $loglink =  preg_replace("(')", urlencode("'"), $loglink);
    echo "<ul><li><a target='_blank' href='$loglink'>NSS Log Server</a></li></ul>\n";
} else {
    $defaultlink = "http://141.137.244.123:5601/app/kibana#/discover?_g=(refreshInterval:(display:Off,pause:!f,value:0),time:(from:now-24h,mode:quick,to:now))";
    echo "<ul><li><a target='_blank' href='$defaultlink'>NSS Log Server</a></li></ul>\n";
}

function plotRequests($selected, $colNames, $tab, $name) {
    echo "<H1>$name</H1>\n";
    global $site, $date, $webargs;

    $table = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();

    $hosts = htmlentities("'" . implode("','",explode(",",$selected)) . "'");
    $where = "
$tab.siteid = sites.id AND sites.name = '%s' AND
$tab.serverid = servers.id AND servers.hostname IN ( %s )
";
    $dbTables = array("$tab", StatsDB::SITES, StatsDB::SERVERS);

    foreach ( $colNames as $colName ) {
        $sqlParam = SqlPlotParamBuilder::init()
                       ->title($colName)
                       ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                       ->yLabel("$name")
                       ->addQuery(
                           SqlPlotParam::DEFAULT_TIME_COL,
                           array( $colName => $colName ),
                           $dbTables,
                           $where,
                           array('site', 'hosts'),
                           SqlPlotParam::SERVERS_HOSTNAME
                           )
                       ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $table->addRow(array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320, "hosts=" . $hosts )));
    }
    echo $table->toHTML();
}


function showRequestsTable($colNames, $tab, $name) {
    global $site, $date, $webargs, $statsDB;

    $tableCols = array( array( 'key' => HOSTNAME, 'db' => SERVERS_HOSTNAME, DDPTable::LABEL => 'Host' ) );
    foreach ( $colNames as $colName ) {
        $tableCols[] = array( 'key' => $colName, 'db' => 'SUM(' . $colName . ')', DDPTable::LABEL => $colName );
    }

    if ( $statsDB->hasData("sum_" . $tab, 'date', true) ) {
        $queryTable = "sum_" . $tab;
        $timeCondition = "$queryTable.date = '$date'";
    } else {
        $queryTable = $tab;
        $timeCondition = "$tab.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    }

    $where = <<<EOS
$queryTable.siteid = sites.id AND sites.name = '$site' AND
$queryTable.serverid = servers.id AND
$timeCondition
GROUP BY servers.id
EOS;

    $table =
            new SqlTable($tab . "_" . $name . "_table",
                         $tableCols,
                         array( $queryTable, StatsDB::SITES, StatsDB::SERVERS ),
                         $where,
                         TRUE,
                         array(
                             DDPTable::ORDER => array( 'by' => HOSTNAME, 'dir' => 'ASC'),
                             DDPTable::ROWS_PER_PAGE => 10,
                             DDPTable::ROWS_PER_PAGE_OPTIONS => array(25, 100, 1000),
                             DDPTable::CTX_MENU => array('key' => "plot_" . $tab . "_" . $name,
                                                'multi' => true,
                                                'menu' => array( 'requests' => 'Plot' ),
                                                'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                                'col' => HOSTNAME,
                             )
                         )
            );
    echo $table->getTable();
}

function showRequests($colNames, $tab) {
    global $site, $date, $webargs;

    $where = "
$tab.siteid = sites.id AND
sites.name = '$site' AND
$tab.serverid = servers.id";

    $table = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();
    $dbTables = array("$tab", StatsDB::SERVERS, StatsDB::SITES);
    $type = ucfirst(substr($tab, strpos($tab, "_") + 1));

    $sqlParam = SqlPlotParamBuilder::init()
                   ->title("$type per Host")
                   ->type(SqlPlotParam::CATEGORY)
                   ->yLabel('')
                   ->disableUserAgg()
                   ->presetAgg('SUM', DAILY);
    foreach ( $colNames as $colName ) {
        $sqlParam = $sqlParam->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            array( $colName => $colName ),
            $dbTables,
            $where,
            array('site'),
            NULL,
            REPLACE
            );
    }
    $sqlParam = $sqlParam->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    $table->addRow(array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400) ) );
    echo $table->toHTML();
}

function showHostGraphs($servers) {
    global $site, $date;

    $table = new HTML_Table(NO_BORDER);

    $sqlParamWriter = new SqlPlotParam();

    $srvIds = implode(",", array_values($servers));
    $where = "hires_server_stat.serverid IN ( %s ) AND hires_server_stat.serverid = servers.id";
    $dbTables = array('hires_server_stat', StatsDB::SERVERS);

    $sqlParam = SqlPlotParamBuilder::init()
                   ->title('CPU Load')
                   ->type(SqlPlotParam::CATEGORY)
                   ->yLabel("%")
                   ->disableUserAgg()
                   ->presetAgg('AVG', DAILY)
                   ->addQuery(
                       SqlPlotParam::DEFAULT_TIME_COL,
                       array( 'iowait+sys+user+IFNULL(steal,0)' => 'CPU' ),
                       $dbTables,
                       $where,
                       array( 'srvids' ),
                       NULL,
                       REPLACE
                       )
                   ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    $table->addRow(array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400, "srvids=$srvIds") ) );

    $sqlParam = SqlPlotParamBuilder::init()
                   ->title('Memory Used')
                   ->type(SqlPlotParam::CATEGORY)
                   ->yLabel('MB')
                   ->presetAgg('AVG', DAILY)
                   ->disableUserAgg()
                   ->addQuery(
                       SqlPlotParam::DEFAULT_TIME_COL,
                       array( 'memused - membuffers - memcached' => "Memory Used" ),
                       $dbTables,
                       $where,
                       array( 'srvids' ),
                       NULL,
                       REPLACE
                       )
                   ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    $table->addRow(array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400, "srvids=$srvIds") ) );

    echo $table->toHTML();
}

function showHostTable($servers) {
    global $site, $date;

    $serverURL = PHP_WEBROOT . "/server.php" . '?' . $_SERVER['QUERY_STRING'] . '&server=';
    echo <<<EOS
<script type="text/javascript">
function formatHostname(elCell, oRecord, oColumn, oData) {
 var hostname = oRecord.getData("hostname");
 elCell.innerHTML = "<a href=\"$serverURL" + hostname + "\">" + hostname + "</a>";
}
</script>
EOS;

    $table = new ModelledTable(
        'common/hires_server_stat_cpumem',
        'hosts',
        array(SERVER_IDS => implode(",", array_values($servers)))
    );
    echo $table->getTable();
}

function showSimTable() {
    global $site, $date;

    $where = "
netsim_simulations.siteid = sites.id AND sites.name = '$site' AND
netsim_simulations.date = '$date' AND
netsim_simulations.serverid = servers.id AND
netsim_simulations.netypeid = netsim_netypes.id";
    $simTable =
              new SqlTable("sim_table",
                           array(
                               array( 'key' => HOSTNAME, 'db' => 'servers.hostname', DDPTable::LABEL => 'Host' ),
                               array( 'key' => 'simulation', 'db' => 'netsim_simulations.simulation', DDPTable::LABEL => 'Simulation' ),
                               array( 'key' => 'netype', 'db' => 'netsim_netypes.name', DDPTable::LABEL => 'NE Type' ),
                               array( 'key' => 'numne', 'db' => 'netsim_simulations.numne', DDPTable::LABEL => '#Nodes' )

                             ),
                           array( 'netsim_simulations', NETSIM_NE_TYPES, StatsDB::SITES, StatsDB::SERVERS ),
                           $where,
                             TRUE,
                             array(
                                 DDPTable::ORDER => array( 'by' => HOSTNAME, 'dir' => 'ASC'),
                                 DDPTable::ROWS_PER_PAGE => 20,
                                 DDPTable::ROWS_PER_PAGE_OPTIONS => array(100, 1000)
                             )
                );
     echo $simTable->getTable();
}

function showStartedGraph($simNeTypes) {
    global $site, $date;

    $table = new HTML_Table(NO_BORDER);

    $where = "netsim_numstarted.siteid = sites.id AND sites.name = '%s' AND netsim_numstarted.serverid = servers.id";
    $dbTables = array('netsim_numstarted', StatsDB::SITES, StatsDB::SERVERS);

    $sqlParam = SqlPlotParamBuilder::init()
                   ->title('Number of Started Nodes')
                   ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                   ->yLabel("#")
                   ->disableUserAgg()
                   ->addQuery(
                       SqlPlotParam::DEFAULT_TIME_COL,
                       array( 'numstarted' => "Num Stared" ),
                       $dbTables,
                       $where,
                       array('site'),
                       REPLACE
                       )
                   ->build();

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $table->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 500 )));

    $dbTables = array('netsim_simulations', NETSIM_NE_TYPES, StatsDB::SERVERS, StatsDB::SITES);
    $sqlParam = SqlPlotParamBuilder::init()
                   ->title('Nodes Started by Type')
                   ->type(SqlPlotParam::CATEGORY)
                   ->yLabel('#NEs')
                   ->disableUserAgg()
                   ->presetAgg('SUM', DAILY);
    foreach ( $simNeTypes as $simNeType ) {
        $where = "
netsim_simulations.siteid = sites.id AND sites.name = '%s' AND
netsim_simulations.serverid = servers.id AND
netsim_simulations.netypeid = netsim_netypes.id AND netsim_netypes.name = '$simNeType'";
        $sqlParam = $sqlParam->addQuery(
            'date',
            array( 'numne' => $simNeType ),
            $dbTables,
            $where,
            array('site'),
            NULL,
            REPLACE
            );
    }
    $sqlParam = $sqlParam->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    $table->addRow(array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 500) ) );

    echo $table->toHTML();
}

function showResourceGraphs($simNeTypes) {
    global $site, $date;

    $table = new HTML_Table(NO_BORDER);
    $sqlParamWriter = new SqlPlotParam();

    $graphParam = array( 'CPU' => array( 'col' => 'cpu', SqlPlotParam::Y_LABEL => 'Mins', 'agg' => 'SUM'),
                         'RSS Memory' => array( 'col' => 'rss', SqlPlotParam::Y_LABEL => 'MB', 'agg' => 'MAX' ) );
    $dbTables = array('netsim_resource_usage', NETSIM_NE_TYPES, StatsDB::SERVERS, StatsDB::SITES);
    foreach ( $graphParam as $title => $params ) {
        $sqlParam = SqlPlotParamBuilder::init()
                       ->title($title . ' usage by Type')
                       ->type(SqlPlotParam::CATEGORY)
                       ->yLabel($params['ylabel'])
                       ->disableUserAgg()
                       ->presetAgg($params['agg'], DAILY);
        foreach ( $simNeTypes as $simNeType ) {
            $where = "
netsim_resource_usage.siteid = sites.id AND sites.name = '%s' AND
netsim_resource_usage.serverid = servers.id AND
netsim_resource_usage.netypeid = netsim_netypes.id AND netsim_netypes.name = '$simNeType'";
            $sqlParam = $sqlParam->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $params['col'] => $simNeType ),
                $dbTables,
                $where,
                array('site'),
                NULL,
                REPLACE
                );
        }
        $sqlParam = $sqlParam->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $table->addRow(array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 500) ) );
    }

    echo $table->toHTML();
}

function mainFlow( $servers, $requestProtocols, $requestOthers, $responseProtocols, $responseOthers) {
    global $site, $date, $webargs, $statsDB;

    $link = makeLink( '/netsim/netsim.php', 'Netsim Hosts Table', array( 'plot' => 'plotHostsTable' ) );
    echo makeHTMLList( array( $link ) );

    $simNeTypes = array();
    $statsDB->query("
SELECT DISTINCT(netsim_netypes.name)
FROM netsim_simulations, netsim_netypes, sites
WHERE
 netsim_simulations.siteid = sites.id AND sites.name = '$site' AND
 netsim_simulations.date = '$date' AND
 netsim_simulations.netypeid = netsim_netypes.id");
    while ($row = $statsDB->getNextRow()) {
        $simNeTypes[] = $row[0];
    }

    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM netsim_resource_usage, sites
WHERE
 netsim_resource_usage.siteid = sites.id AND sites.name = '$site' AND
 netsim_resource_usage.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    if ( $row[0] > 0 ) {
        $hasResourceUsage = TRUE;
    } else {
        $hasResourceUsage = FALSE;
    }

#Protocol Netsim

    $hasRequests = $statsDB->queryRow("
SELECT
    COUNT(*)
FROM
    netsim_requests, sites
WHERE
    netsim_requests.siteid = sites.id AND sites.name = '$site' AND
    netsim_requests.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    $hasResponse = $statsDB->queryRow("
SELECT
    COUNT(*)
FROM
    netsim_response, sites
WHERE
    netsim_response.siteid = sites.id AND sites.name = '$site' AND
    netsim_response.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ( $hasRequests[0] > 0 || $hasResponse[0] > 0 ) {
        echo "<br>";
        drawHeaderWithHelp("NSS Counters", 1, "NSSCntrsHelp", "");
        if ( $hasRequests[0] > 0 ) {
            drawHeaderWithHelp("Request Charts", 2, "ReqCntrsHelp", "");
            echo "<li><a href=\"" . PHP_WEBROOT . "/netsim/request_protocol_all_vm.php?$webargs\">Request Protocol Counts on All VMs</a></li>";
            echo "<li><a href=\"" . PHP_WEBROOT . "/netsim/request_protocol_per_vm.php?$webargs\">Request Protocol Counts per VM</a></li>";
            echo "<li><a href=\"" . PHP_WEBROOT . "/netsim/request_count_per_vm.php?$webargs\">Request Counts per VM</a></li>";
        }
        if ( $hasResponse[0] > 0 ) {
            drawHeaderWithHelp("Response Charts", 2, "ResCntrsHelp", "");
            echo "<li><a href=\"" . PHP_WEBROOT . "/netsim/response_protocol_all_vm.php?$webargs\">Response Protocol Counts on All VMs</a></li>";
            echo "<li><a href=\"" . PHP_WEBROOT . "/netsim/response_protocol_per_vm.php?$webargs\">Response Protocol Counts per VM</a></li>";
            echo "<li><a href=\"" . PHP_WEBROOT . "/netsim/response_count_per_vm.php?$webargs\">Response Counts per VM</a></li>";
        }
    }

    $nrmTable = new ModelledTable(
        'netsim/netsim_nrm',
        'netsim_nrm'
    );
    if ( $nrmTable->hasRows() ) {
        drawHeader("NRM", 1, "nrm");
        echo $nrmTable->getTable();
    }

    if ( count($simNeTypes) > 0 ) {
        echo "<H1>Simulations</H1>\n";
        showSimTable();
        echo "<H2>Number of Started Nodes</H2>\n";
        showStartedGraph($simNeTypes);

        if ( $hasResourceUsage ) {
            echo "<H2>Resource Usage by NE Type</H2>\n";
            showResourceGraphs($simNeTypes);
        }
    }
    if ( $hasRequests[0] > 0 ) {
        drawHeaderWithHelp("Request Counts", 2, "ReqCntrsProHelp", "");
        showRequests( $requestProtocols, NETSIM_REQUESTS);
        drawHeaderWithHelp("Request Protocol Counts for All VM's", 2, "ReqCntrsProHelp", "");
        showRequestsTable( $requestProtocols, NETSIM_REQUESTS, 'Protocols');
        drawHeaderWithHelp("Request Counts for All VM's", 2, "ReqCntrsOthHelp", "");
        showRequestsTable( $requestOthers, NETSIM_REQUESTS, 'Others');
    }
    if ( $hasResponse[0] > 0 ) {
        drawHeaderWithHelp("Response Counts", 2, "ResCntrsProHelp", "");
        showRequests( $responseProtocols, NETSIM_RESPONSE);
        drawHeaderWithHelp("Response Protocol Counts for All VM's", 2, "ResCntrsProHelp", "");;
        showRequestsTable( $responseProtocols, NETSIM_RESPONSE, 'Protocols');
        drawHeaderWithHelp("Response Counts for All VM's", 2, "ResCntrsOthHelp", "");
        showRequestsTable( $responseOthers, NETSIM_RESPONSE, 'Others');
    }

    echo "<H1>Netsim Hosts</H1>\n";
    showHostGraphs($servers);
}

$statsDB = new StatsDB();
$requestOthers = array(
                     'ecim_get', 'ecim_edit', 'ecim_MOaction', 'cpp_createMO',
                     'cpp_deleteMO', 'cpp_setAttr', 'cpp_getMIB', 'cpp_nextMOinfo',
                     'cpp_get', 'cpp_MOaction', 'snmp_get', 'snmp_bulk_get', 'snmp_get_next',
                     'snmp_set', 'AVCbursts', 'MCDbursts', 'AlarmBursts', 'sftp_FileOpen', 'sftp_get_cwd'
                 );
$requestProtocols = array( 'NETCONF', 'CPP', 'SNMP', 'SIMCMD', 'SFTP' );
$responseProtocols = array( 'NETCONF', 'CORBA', 'SNMP', 'SSH', 'SFTP' );
$responseOthers = array(
                      'ecim_avc', 'ecim_MOcreated', 'ecim_MOdeleted',
                      'ecim_reply', 'cpp_avc', 'cpp_MOcreated', 'cpp_MOdeleted',
                      'cpp_reply', 'sftp_FileClose', 'snmp_response', 'snmp_traps'
                  );

$action = requestValue('plot');

$servers = array();
$statsDB->query("
SELECT servers.hostname AS hostname, servers.id AS id
FROM servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 servers.type = 'NETSIM'");
while ($row = $statsDB->getNextNamedRow()) {
    $servers[$row['hostname']] = $row['id'];
}

if ( isset($_GET['plot_netsim_requests_Protocols']) ) {
    plotRequests( $_GET[SELECTED], $requestProtocols, NETSIM_REQUESTS, ' Requests');
} else if ( isset($_GET['plot_netsim_requests_Others']) ) {
    plotRequests( $_GET[SELECTED], $requestOthers, NETSIM_REQUESTS, ' Requests');
} else if ( isset($_GET['plot_netsim_response_Protocols']) ) {
    plotRequests( $_GET[SELECTED], $responseProtocols, NETSIM_RESPONSE, ' Response');
} else if ( isset($_GET['plot_netsim_response_Others']) ) {
    plotRequests( $_GET[SELECTED], $responseOthers, NETSIM_RESPONSE, ' Response');
} elseif ( $action === 'plotHostsTable' ) {
    echo "<H1>Netsim Hosts</H1>\n";
    // This table results in a slow query, so we are moving it here so it is not loaded by default
    showHostTable($servers);
} else {
    mainFlow($servers, $requestProtocols, $requestOthers, $responseProtocols, $responseOthers);
}

$statsDB->disconnect();

include "../common/finalise.php";

