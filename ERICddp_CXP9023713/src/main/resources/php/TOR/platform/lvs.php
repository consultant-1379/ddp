<?php
$pageTitle = "NAT/LVS";

$DISABLE_UI_PARAMS = array( 'nicstats', 'conntrack' );

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/NICPlot.php";

require_once 'HTML/Table.php';

const NICSTATS = "nicstats";
const CONNTRACK = 'conntrack';
const SERVER = 'server';
const SITES = 'sites';
const SERVERS = 'servers';
const INSTANCE = 'Instance';
const NIC_ID = 'nicid';
const CTX_MENU = 'ctxMenu';
const SELECTED = "selected";

$statsDB = new StatsDB();

function plotLVS($idStr) {
    global $date;

    $lvsIds = array();
    $srvIds = array();
    foreach ( explode(",", $idStr) as $srvLvsId ) {
        list($srvId,$lvsId) = explode(":", $srvLvsId);
        $srvIds[$srvId] = 1;
        $lvsIds[$lvsId] = 1;
    }
    $srvIdsStr = implode(",", array_keys($srvIds));
    $lvsIdsStr = implode(",", array_keys($lvsIds));

    $colAndTitles = array( 'inpkts' => 'Incoming Packets',
                           'outpkts' => 'Outgoing Packets',
                           'inbytes' => 'Incoming Bytes',
                           'outbytes' => 'Outgoing Bytes' );

    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table('border=0');
    foreach ( $colAndTitles as $column => $title ) {
        $sqlPlotParam =
        array(
            SqlPlotParam::TITLE => $title,
            'type' => 'tsc',
            SqlPlotParam::Y_LABEL => "",
            SqlPlotParam::USER_AGG => 'true',
            SqlPlotParam::PERSISTENT => SqlPlotParam::FALSE_VALUE,
            SqlPlotParam::QUERY_LIST
            => array(
                     array(
                           SqlPlotParam::TIME_COL => 'time',
                           'multiseries'=> 'CONCAT(
                               servers.hostname,
                               "-",
                               enm_lvs.lhost,
                               ":",
                               enm_lvs.lport,
                               "->",
                               enm_lvs.rhost
                           )',
                           SqlPlotParam::WHAT_COL=>
                           array(
                                 $column => $title,
                                 ),
                           SqlPlotParam::TABLES => "enm_lvs_stats, enm_lvs, servers, sites",
                           SqlPlotParam::WHERE => "enm_lvs_stats.siteid = sites.id AND
                                                   sites.name = '%s' AND
                                                   enm_lvs_stats.lvsid = enm_lvs.id AND
                                                   enm_lvs.id IN (%s) AND
                                                   enm_lvs_stats.serverid = servers.id AND
                                                   servers.id IN (%s)",
                           SqlPlotParam::Q_ARGS => array('site', 'lvsids', 'srvids')
                     )
               )
            );
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    $url =  $sqlParamWriter->getImgURL(
        $id,
        "$date 00:00:00",
        "$date 23:59:59",
        true,
        800,
        300,
        "lvsids=$lvsIdsStr&srvids=$srvIdsStr"
    );
    $graphTable->addRow( array( $url ) );
  }
    echo $graphTable->toHTML();
}

function plotConnTrack($server) {
    global $date;
    $queryList = array(
                    array(
                        SqlPlotParam::TIME_COL => 'time',
                        SqlPlotParam::WHAT_COL=>
                        array(
                            'port_56834' => 'CorbaUnsecure(56834)',
                            'port_6513' => 'Netconf-TLS(6513)',
                            'port_2049' => 'NFS(2049)',
                            'port_80' => 'HTTP(80)',
                            'port_Other' => 'Other'
                        ),
                        SqlPlotParam::TABLES => "enm_lvs_conntrack, sites, servers",
                        SqlPlotParam::WHERE => "enm_lvs_conntrack.siteid = sites.id AND sites.name = '%s' AND
                                                enm_lvs_conntrack.serverid = servers.id AND servers.hostname = '%s'",
                        SqlPlotParam::Q_ARGS => array('site',SERVER)
                    )
    );
    $sqlPlotParam = array(
                        SqlPlotParam::TITLE => 'Connections',
                        'type' => 'sa',
                        SqlPlotParam::Y_LABEL => "",
                        SqlPlotParam::USER_AGG => 'true',
                        SqlPlotParam::PERSISTENT => SqlPlotParam::FALSE_VALUE,
                        SqlPlotParam::QUERY_LIST => $queryList
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    $url = $sqlParamWriter->getURL(
        $id,
        "$date 00:00:00",
        "$date 23:59:59",
        "server=" . $server
    );
    header("Location:" . $url);
}

function mainFlowNicTableArr() {
    return array(
                array(
                     'key' => SERVER,
                     'db' => SqlPlotParam::SERVERS_HOSTNAME,
                     DDPTABLE::LABEL => INSTANCE
                ),
                array(
                    'key' => NIC_ID,
                    'db' => 'network_interfaces.id',
                    'visible' => false,
                    DDPTABLE::LABEL => 'None'
                ),
                array(
                    'key' => 'nicname',
                    'db' => 'network_interfaces.name',
                    DDPTABLE::LABEL => 'NIC'
                ),
                array(
                    'key' => 'rxavg',
                    'db' => 'ROUND((AVG(nic_stat.ibytes_per_sec)*8/1000000), 2)',
                    DDPTABLE::LABEL => 'Average Recevied (Mbit/s)',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
                array(
                    'key' => 'txavg',
                    'db' => 'ROUND((AVG(nic_stat.obytes_per_sec)*8/1000000), 2)',
                    DDPTABLE::LABEL => 'Average Transmitted (Mbit/s)',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
                array(
                    'key' => 'rxmax',
                    'db' => 'ROUND((MAX(nic_stat.ibytes_per_sec)*8/1000000), 2)',
                    DDPTABLE::LABEL => 'Max Recevied (Mbit/s)',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
                array(
                    'key' => 'txmax',
                    'db' => 'ROUND((MAX(nic_stat.obytes_per_sec)*8/1000000), 2)',
                    DDPTABLE::LABEL => 'Max Transmitted (Mbit/s)',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
    );
}

function mainFlowLvsTableArr() {
    return array(
                array(
                    'key' => 'lvsid',
                    'db' => 'CONCAT(servers.id,":",enm_lvs.id)',
                    'visible' => false, DDPTABLE::LABEL => 'None'
                ),
                array( 'key' => SERVER, 'db' => SqlPlotParam::SERVERS_HOSTNAME, DDPTABLE::LABEL => INSTANCE ),
                array( 'key' => 'lhost', 'db' => 'enm_lvs.lhost', DDPTABLE::LABEL => 'External Address' ),
                array( 'key' => 'lport', 'db' => 'enm_lvs.lport', DDPTABLE::LABEL => 'External Port' ),
                array( 'key' => 'rhost', 'db' => 'enm_lvs.rhost', DDPTABLE::LABEL => 'Internal Address' ),
                array( 'key' => 'rport', 'db' => 'enm_lvs.rport', DDPTABLE::LABEL => 'Internal Port' ),
                array( 'key' => 'proto', 'db' => 'enm_lvs.proto', DDPTABLE::LABEL => 'Protocol' ),
                array(
                    'key' => 'rpkt',
                    'db' => 'SUM(inpkts)',
                    DDPTABLE::LABEL => 'Packets Received',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
                array(
                    'key' => 'tpkt',
                    'db' => 'SUM(outpkts)',
                    DDPTABLE::LABEL => 'Packets Sent',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
                array(
                    'key' => 'rbtye',
                    'db' => 'SUM(inbytes)',
                    DDPTABLE::LABEL => 'Bytes Received',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                ),
                array(
                    'key' => 'tbyte',
                    'db' => 'SUM(outbytes)',
                    DDPTABLE::LABEL => 'Bytes Sent',
                    DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                )
           );
}

function mainFlow($statsDB) {
    global $date,$site,$webargs;

    $vipWhere = "enm_lvs_viphost.nicid = network_interfaces.id AND
                 network_interfaces.serverid = servers.id AND
                 servers.siteid = sites.id AND sites.name = '$site' AND
                 enm_lvs_viphost.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    $vipDBTables = array( 'enm_lvs_viphost', SITES, SERVERS, 'network_interfaces' );
    $vipTable = new SqlTable(
        "vip_table",
        array(
            array( 'key' => 'time', 'db' => 'DATE_FORMAT(enm_lvs_viphost.time,"%H:%i")', DDPTABLE::LABEL => 'Time' ),
            array( 'key' => 'vip', 'db' => 'enm_lvs_viphost.vip', DDPTABLE::LABEL => 'VIP' ),
            array( 'key' => SERVER, 'db' => SqlPlotParam::SERVERS_HOSTNAME, DDPTABLE::LABEL => INSTANCE ),
            array( 'key' => 'nicname', 'db' => 'network_interfaces.name', DDPTABLE::LABEL => 'NIC' ),
            ),
        $vipDBTables,
        $vipWhere,
        true,
        array( DDPTABLE::ORDER => array( 'by' => 'time', 'dir' => 'ASC'),
        'rowsPerPage' => 20,
        'rowsPerPageOptions' => array(100, 500, 1000, 5000, 10000)
        )
    );

    drawHeaderWithHelp("VIP Assignment", 1, "VIPAssignmentHelp", "DDP_Bubble_95_VIP_Assignment_Summary_LVSRouters");
    echo $vipTable->getTable();

    $srvIds = array();
    $vipNicIds = array();
    $statsDB->query("SELECT DISTINCT servers.id AS srvid, enm_lvs_viphost.nicid AS
                     nicid FROM " . implode(",", $vipDBTables) . " WHERE " . $vipWhere);
    while ($row = $statsDB->getNextNamedRow()) {
        $vipNicIds[] = $row[NIC_ID];
        $srvIds[$row['srvid']] = 1;
    }

    if ( count($vipNicIds) == 0 ) {
        return;
    }

    $srvIdsStr = implode(",", array_keys($srvIds));
    $vipNicIdsStr = implode(",", $vipNicIds);
    $ifWhere = "nic_stat.serverid IN ($srvIdsStr) AND
                nic_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                nic_stat.nicid IN ($vipNicIdsStr) AND
                nic_stat.nicid = network_interfaces.id AND
                network_interfaces.serverid = servers.id";
    $cols = mainFlowNicTableArr();
    $ifTable = new SqlTable(
        "nic_table",
        $cols,
        array( 'nic_stat', SERVERS, 'network_interfaces' ),
        $ifWhere . " GROUP BY nicid",
        true,
        array( DDPTABLE::ORDER => array( 'by' => SERVER, 'dir' => 'ASC'),
        CTX_MENU => array('key' => NICSTATS,
                           DDPTABLE::MULTI => true,
                           'menu' => array( 'plot' => 'Plot' ),
                           'url' => makeSelfLink(),
                           'col' => NIC_ID
                    )
        )
);

    drawHeaderWithHelp("NIC Traffic", 1, "NICTrafficHelp", "DDP_Bubble_105_NIC_Traffic_Summary_LVSRouters");
    echo $ifTable->getTable();

    $lvsWhere = "enm_lvs_stats.lvsid = enm_lvs.id AND
                 enm_lvs_stats.serverid = servers.id AND
                 servers.siteid = sites.id AND sites.name = '$site' AND
                 sites.id = enm_lvs_stats.siteid AND
                 enm_lvs_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $lvsTable = new SqlTable(
        "lvs_table",
        mainFlowLvsTableArr(),
        array( 'enm_lvs_stats', 'enm_lvs', SERVERS, SITES ),
        $lvsWhere . " GROUP BY enm_lvs_stats.serverid, enm_lvs_stats.lvsid",
        true,
        array(
            DDPTABLE::ORDER => array( 'by' => 'lhost', 'dir' => 'ASC'),
            CTX_MENU => array('key' => 'lvsstats',
            DDPTABLE::MULTI => true,
            'menu' => array( 'plot' => 'Plot' ),
            'url' => makeSelfLink() . "?" . $webargs,
            'col' => 'lvsid'
            ),
            'rowsPerPage' => 20,
            'rowsPerPageOptions' => array(100, 500,1000,5000,10000)
        )
    );

    drawHeaderWithHelp("LVS Traffic", 1, "LVSTrafficHelp", "DDP_Bubble_106_LVS_Traffic_Summary_LVSRouters");
    echo $lvsTable->getTable();

    echo "<H2>Connection Tracking</H2>\n";
    $lvsTable = new SqlTable(
        "conntrack_table",
        array(
            array( 'key' => SERVER, 'db' => SqlPlotParam::SERVERS_HOSTNAME, DDPTABLE::LABEL => INSTANCE ),
            array(
                'key' => 'avg_count',
                'db' => 'ROUND(AVG(enm_lvs_conntrack.tcp+enm_lvs_conntrack.udp),0)',
                DDPTABLE::LABEL => 'Avg'
            ),
            array(
                'key' => 'max_count',
                'db' => 'MAX(enm_lvs_conntrack.tcp+enm_lvs_conntrack.udp)',
                DDPTABLE::LABEL => 'Max'
            )
      ),
        array( 'enm_lvs_conntrack', SERVERS, SITES ),
        "enm_lvs_conntrack.siteid = sites.id AND sites.name = '$site' AND
        enm_lvs_conntrack.time BETWEEN '$date 00:00:00' AND
        '$date 23:59:59' AND enm_lvs_conntrack.serverid = servers.id GROUP BY servers.hostname",
        true,
        array(
            DDPTABLE::ORDER => array( 'by' => 'max_count', 'dir' => 'DESC'),
            CTX_MENU => array('key' => CONNTRACK,
            DDPTABLE::MULTI => false,
            'menu' => array( 'portplot' => 'Port Plot' ),
            'url' => makeSelfLink() . "?" . $webargs,
            'col' => SERVER
            )
        )
    );
    echo $lvsTable->getTable();
    echo "<br>\n";

    $sqlPlotParam =
                array(
                    SqlPlotParam::TITLE => 'Connections',
                    'type' => 'tsc',
                    SqlPlotParam::Y_LABEL => "",
                    SqlPlotParam::USER_AGG => 'true',
                    SqlPlotParam::PERSISTENT => SqlPlotParam::FALSE_VALUE,
                    SqlPlotParam::QUERY_LIST => array(
                        array(
                            SqlPlotParam::TIME_COL => 'time',
                            'multiseries'=> SqlPlotParam::SERVERS_HOSTNAME,
                            SqlPlotParam::WHAT_COL =>
                            array( '(enm_lvs_conntrack.tcp+enm_lvs_conntrack.udp)' => 'Count' ),
                            SqlPlotParam::TABLES => "enm_lvs_conntrack, sites, servers",
                            SqlPlotParam::WHERE => "enm_lvs_conntrack.siteid = sites.id AND
                            sites.name = '%s' AND enm_lvs_conntrack.serverid = servers.id",
                            SqlPlotParam::Q_ARGS => array('site')
                        )
                    )
                );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    echo $sqlParamWriter->getImgURL(
        $id,
        "$date 00:00:00",
        "$date 23:59:59",
        true,
        800,
        400
    );
}

    if (issetURLParam(NICSTATS)) {
        $nicPlot = new NICPlot($statsDB, $date, requestValue(SELECTED));
        $nicPlot->openQPlot();
    } elseif (issetURLParam("lvsstats")) {
        plotLVS(requestValue(SELECTED));
    } elseif (issetURLParam(CONNTRACK)) {
        plotConnTrack(requestValue(SELECTED));
    } else {
        mainFlow($statsDB);
    include_once PHP_ROOT . "/common/finalise.php";
}
