<?php

$DISABLE_UI_PARAMS = array( 'plot' );

$pageTitle = "JGroups";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

require_once 'HTML/Table.php';

const PLOT_TX = 'plottx';
const PLOT_RX = 'plotrx';
const CLUSTER = 'cluster';
const MEMBERS = 'members';
const MB_RECIEVED = 'MB Recieved';
const MB_SENT = 'MB Sent';
const MESSAGES_SENT = 'Messages Sent';
const MESSAGES_REC = 'Messages Recieved';
const RECVMB = 'recvmb';
const PRE = "</pre>\n";

class JGroupsClusterSummary extends DDPObject {
  var $cols = array(
                    array( 'key' => CLUSTER, DDPTABLE::LABEL => 'Name'),
                    array( 'key' => MEMBERS, DDPTABLE::LABEL => 'Members', DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM),
                    array(
                        'key' => 'rxvol',
                        DDPTABLE::LABEL => MB_RECIEVED,
                        DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                    ),
                    array( 'key' => 'txvol', DDPTABLE::LABEL => MB_SENT, DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM),
                    array(
                        'key' => 'rxmsg', DDPTABLE::LABEL => MESSAGES_REC,
                        DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                    ),
                    array(
                        'key' => 'txmsg', DDPTABLE::LABEL => MESSAGES_SENT,
                        DDPTABLE::FORMATTER => DDPTABLE::FORMAT_NUM
                    )
              );

  var $defaultOrderBy = "rxvol";
  var $defaultOrderDir = "DESC";

    function __construct() {
        parent::__construct("jgroups_cluster_summary");
    }

  function getData() {
    global $date;
    global $site;
    global $debug;

    $sql = "
SELECT
 enm_jgroup_udp_stats.clusterid AS clusterid,
 enm_jgroup_clusternames.name AS cluster,
 ROUND(SUM(num_bytes_received)/(1024*1024),1) AS rxvol,
 ROUND(SUM(num_bytes_sent)/(1024*1024),1) AS txvol,
 SUM(num_msgs_received) AS rxmsg,
 SUM(num_msgs_sent) AS txmsg
FROM enm_jgroup_udp_stats, sites, enm_jgroup_clusternames
WHERE
 enm_jgroup_udp_stats.siteid = sites.id AND sites.name = '$site' AND
 enm_jgroup_udp_stats.clusterid = enm_jgroup_clusternames.id AND
 enm_jgroup_udp_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_jgroup_udp_stats.clusterid";
    $this->populateData($sql);

    $urlBase = makeSelfLink() . "?" . fromServer('QUERY_STRING') . "&cluster=";
    $rowsByClusterId = array();
    foreach ($this->data as &$row) {
      $cluster = $row[CLUSTER];
      $row[CLUSTER] = sprintf("<a href=\"%s%s\">%s</a>", $urlBase, $cluster, $cluster);
      $rowsByClusterId[$row['clusterid']] = &$row;

    }
    unset($row);
    $this->statsDB->query("
SELECT
 sum_table.clusterid AS clusterid,
 MAX(sum_table.membercount) AS membercount
FROM
(
 SELECT
  enm_jgroup_udp_stats.clusterid AS clusterid,
  SUM(enm_jgroup_udp_stats.count) AS membercount
 FROM enm_jgroup_udp_stats, sites
 WHERE
  enm_jgroup_udp_stats.siteid = sites.id AND sites.name = '$site' AND
  enm_jgroup_udp_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
 GROUP BY enm_jgroup_udp_stats.clusterid, enm_jgroup_udp_stats.time
) AS sum_table
GROUP BY clusterid");
    while ($row = $this->statsDB->getNextNamedRow()) {
      $outputRow = &$rowsByClusterId[$row['clusterid']];
      if ( $debug > 0 ) {
          echo "<pre>outputRow\n";
          print_r($outputRow);
          echo PRE;
      }
      $outputRow[MEMBERS] = $row['membercount'];
    }
    $this->columnTypes[MEMBERS] = 'int';

    if ( $debug > 0 ) {
        echo "<pre>data\n";
        print_r($this->data);
        echo PRE;
    }
    return $this->data;
  }
}

class JGroupClusterMembers extends DDPObject {
  var $cols = array(
                    'server' => 'Server',
                    MEMBERS => 'Members',
                    RECVMB => MB_RECIEVED,
                    'sentmb' => MB_SENT,
                    'recvmsg' => MESSAGES_REC,
                    'sentmsg' => MESSAGES_SENT,
                    'recvsingle' => 'Single Messages Recevied',
                    'sentsingle' => 'Single Messages Sent',
                    'recvbatch' => 'Batches Recevied',
                    'sentbatch' => 'Batches Sent',
                    'rejects' => 'Messages Rejected',
                    'internal' => 'Internal Messages',
                    'oob' => 'OOB Messages',
                    'incoming' => 'Incoming Messages'
                    );

  var $defaultOrderBy = "recvmb";
  var $defaultOrderDir = "DESC";
  var $cluster;

  function __construct($cluster) {
    parent::__construct("jgroup_cluster_members");
    $this->cluster = $cluster;
  }

  function getData() {
    global $date;
    global $site;
    global $debug;

    $sql = "
SELECT
 servers.hostname AS server,
 MAX(enm_jgroup_udp_stats.count) AS members,
 ROUND(SUM(enm_jgroup_udp_stats.num_bytes_received)/(1024*1024),1) AS recvmb,
 ROUND(SUM(enm_jgroup_udp_stats.num_bytes_sent)/(1024*1024),1) AS sentmb,
 SUM(enm_jgroup_udp_stats.num_msgs_received) AS recvmsg,
 SUM(enm_jgroup_udp_stats.num_msgs_sent) AS sentmsg,
 SUM(enm_jgroup_udp_stats.num_single_msgs_received) AS recvsingle,
 SUM(enm_jgroup_udp_stats.num_single_msgs_sent) AS sentsingle,
 SUM(enm_jgroup_udp_stats.num_batches_received) AS recvbatch,
 SUM(enm_jgroup_udp_stats.num_batches_sent) AS sentbatch,
 SUM(enm_jgroup_udp_stats.num_rejected_msgs) AS rejects,
 SUM(enm_jgroup_udp_stats.num_internal_msgs_received) AS internal,
 SUM(enm_jgroup_udp_stats.num_oob_msgs_received) AS oob,
 SUM(enm_jgroup_udp_stats.num_incoming_msgs_received) AS incoming
FROM enm_jgroup_udp_stats, sites, enm_jgroup_clusternames, servers
WHERE
 enm_jgroup_udp_stats.siteid = sites.id AND sites.name = '$site' AND
 enm_jgroup_udp_stats.clusterid = enm_jgroup_clusternames.id AND enm_jgroup_clusternames.name = '$this->cluster' AND
 enm_jgroup_udp_stats.serverid = servers.id AND
 enm_jgroup_udp_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_jgroup_udp_stats.serverid";
    $this->populateData($sql);

    return $this->data;
  }
}

function showJGroupNICsTable() {
    global $statsDB, $site, $date, $webargs;

    $nicIds = array();
    $statsDB->query("
    SELECT
        enm_jgroup_nics.nicid AS nicid
    FROM
        enm_jgroup_nics, sites
    WHERE
        enm_jgroup_nics.siteid = sites.id AND sites.name = '$site' AND
        enm_jgroup_nics.date = '$date'");

    while ( $row = $statsDB->getNextRow() ) {
        $nicIds[] = $row[0];
    }
    if ( count($nicIds) == 0 ) {
        return;
    }

    $nicIdsStr = implode(",", $nicIds);

    $hasSummary = $statsDB->hasData("sum_nic_stat", 'date', 'true');
    if ( $hasSummary ) {
        $nicTableField = "sum_nic_stat";
        $where = <<<EOT
sum_nic_stat.siteid = sites.id AND sites.name = '$site' AND
sum_nic_stat.date BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
sum_nic_stat.nicid IN ( $nicIdsStr ) AND
sum_nic_stat.nicid = network_interfaces.id AND
sum_nic_stat.serverid = servers.id
GROUP BY sum_nic_stat.nicid
EOT;
    } else {
        $nicTableField = "nic_stat FORCE INDEX(siteIdTime)";
        $where = $statsDB->where('nic_stat');
        $where .= "
AND nic_stat.nicid IN ( $nicIdsStr ) AND
nic_stat.nicid = network_interfaces.id AND
nic_stat.serverid = servers.id
GROUP BY nic_stat.nicid";
    }

    $nicTable = SqlTableBuilder::init()
              ->name("nics")
              ->tables(array(
                  $nicTableField,
                  'network_interfaces',
                  StatsDB::SITES,
                  StatsDB::SERVERS)
              )
              ->where($where)
              ->addHiddenColumn('id', 'nicid')
              ->addSimpleColumn(SqlPlotParam::SERVERS_HOSTNAME, 'Server')
              ->addSimpleColumn('network_interfaces.name', 'NIC')
              ->addSimpleColumn('ROUND((AVG(ibytes_per_sec)*8/1000000), 2)', 'Average Recevied (Mbit/s)')
              ->addSimpleColumn('ROUND((AVG(obytes_per_sec)*8/1000000), 2)', 'Average Transmitted (Mbit/s)')
              ->addSimpleColumn('ROUND((MAX(ibytes_per_sec)*8/1000000), 2)', 'Max Recevied (Mbit/s)')
              ->addSimpleColumn('ROUND((MAX(obytes_per_sec)*8/1000000), 2)', 'Max Transmitted (Mbit/s)')
              ->paginate()
              ->ctxMenu(
                  'plot',
                  true,
                  array( PLOT_TX => 'Plot TX', PLOT_RX => 'Plot RX' ),
                  makeSelfLink(),
                  'id'
                )
              ->build();

    drawHeaderWithHelp(
        "JGroups Network Interfaces",
        1,
        "JGroups Network Interfaces",
        "DDP_Bubble_424_ENM_JGroupes_Network_Interfaces_Help"
    );
    echo $nicTable->getTable();
}

function plotMembers($cluster, $serversStr) {
    global $debug, $date, $site;

    echo "<H1>$cluster Statistics for $serversStr</H1>\n";

    $plots = array(
                array(
                    SqlPlotParam::TITLE => MB_RECIEVED,
                    'col' => 'enm_jgroup_udp_stats.num_bytes_received/(1024*1024)'
                ),
                array(
                    SqlPlotParam::TITLE => MB_SENT,
                    'col' => 'enm_jgroup_udp_stats.num_bytes_sent/(1024*1024)'
                ),
                array(
                     SqlPlotParam::TITLE => MESSAGES_REC,
                     'col' => 'num_msgs_received'
                ),
                array( SqlPlotParam::TITLE => MESSAGES_SENT, 'col' => 'num_msgs_sent' ),
                array(
                    SqlPlotParam::TITLE => 'Single Messages Recevied',
                    'col' => 'num_single_msgs_received'
                ),
                array(
                    SqlPlotParam::TITLE => 'Single Messages Sent',
                    'col' => 'num_single_msgs_sent'
                ),
                array(
                    SqlPlotParam::TITLE => 'Batches Recevied',
                    'col' => 'num_batches_received'
                ),
                array(
                    SqlPlotParam::TITLE => 'Batches Sent',
                    'col' => 'num_batches_sent'
                ),
                array(
                    SqlPlotParam::TITLE => 'Messages Rejected',
                    'col' => 'num_rejected_msgs'
                ),
                array(
                     SqlPlotParam::TITLE => 'Internal Messages',
                     'col' => 'num_internal_msgs_received'
                ),
                array(
                    SqlPlotParam::TITLE => 'OOB Messages',
                    'col' => 'num_oob_msgs_received'
                ),
                array(
                     SqlPlotParam::TITLE => 'Incoming Messages',
                     'col' => 'num_incoming_msgs_received'
                )
           );

    $quotedServersStr = "'" . implode("','", explode(",", $serversStr)) . "'";

    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    foreach ( $plots as $plot ) {
        $sqlParam =
        array(
            SqlPlotParam::TITLE      => $plot[SqlPlotParam::TITLE],
            'ylabel'     => '',
            'useragg'    => 'true',
            'persistent' => 'false',
            'querylist' =>
            array(
                array(
                    'timecol' => 'time',
                    'multiseries'=> SqlPlotParam::SERVERS_HOSTNAME,
                    'whatcol' => array( $plot['col'] => $plot[SqlPlotParam::TITLE] ),
                    'tables'  => "enm_jgroup_udp_stats, enm_jgroup_clusternames, sites, servers",
                    'where'   => "enm_jgroup_udp_stats.siteid = sites.id AND sites.name = '%s' AND
                                  enm_jgroup_udp_stats.clusterid = enm_jgroup_clusternames.id AND
                                  enm_jgroup_clusternames.name = '%s' AND
                                  enm_jgroup_udp_stats.serverid = servers.id AND servers.hostname IN ( %s )",
                    'qargs'   => array( 'site', CLUSTER, 'servers' )
                    )
                 )
             );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $url =  $sqlParamWriter->getImgURL(
        $id,
        $date . " 00:00:00",
        $date . " 23:59:59",
        true,
        640,
        240,
        "cluster=$cluster&servers=" . urlencode($quotedServersStr)
    );
    $graphTable->addRow(array($url));
  }

    echo $graphTable->toHTML();
}

function plotNICs($selected, $type) {
    global $debug, $date, $site;

    if ( $debug > 0 ) {
        echo "<pre>servers\n";
        print_r($servers);
        echo PRE;
    }

    $srvIds = array();
    $statsDB = new StatsDB();
    $statsDB->query("
    SELECT
        DISTINCT enm_jgroup_nics.serverid
    FROM
        enm_jgroup_nics, sites
    WHERE
        enm_jgroup_nics.siteid = sites.id AND sites.name = '$site' AND
        enm_jgroup_nics.date = '$date' AND
        enm_jgroup_nics.nicid IN ( $selected )");
    while ($row = $statsDB->getNextRow()) {
        $srvIds[] = $row[0];
    }
    $srvIdsStr = implode(",", $srvIds);
    if ( $debug > 0 ) {
        echo "<pre>srvIdsStr=$srvIdsStr</pre>\n";
    }

    $whatcol = array('((ibytes_per_sec * 8)/1000000)' => 'RX');
    if ( $type === PLOT_TX ) {
        $whatcol = array('((obytes_per_sec * 8)/1000000)' => 'TX');
    }

    $sqlParamWriter = new SqlPlotParam();
    $sqlPlotParam =
    array(
        SqlPlotParam::TITLE => "JGroups NIC Bandwidth Usage",
        'type' => 'tsc',
        'ylabel' => "MBit/s",
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' => array(
                           array(
                               'timecol' => 'time',
                               "multiseries" => SqlPlotParam::SERVERS_HOSTNAME,
                               'whatcol'=> $whatcol,
                               'tables' => "nic_stat, network_interfaces, servers",
                               'where' => 'nic_stat.serverid IN ( %s ) AND nic_stat.nicid IN ( %s ) AND
                                           nic_stat.nicid = network_interfaces.id AND
                                           nic_stat.serverid = servers.id',
                               'qargs' => array('srvids','nicids')
                           )
                       )
    );
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    header("Location:" .
         $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59", "srvids=$srvIdsStr&nicids=$selected"));
}

function showCluster($cluster) {
    echo "<H1>$cluster</H1>\n";
    $table = new JGroupClusterMembers($cluster);
    echo $table->getClientSortableTableStr(0, null, 'member_setupMenu');
}

function mainFlow() {
    $mismatches = new ModelledTable('TOR/platform/enm_jgroup_view_mismatch', 'partition_counts');
    if ( $mismatches->hasRows() ) {
        echo $mismatches->getTableWithHeader("JGroup View Mismatches");
    }

    drawHeaderWithHelp("JGroups Clusters", 1, "JGroups Clusters", "DDP_Bubble_423_ENM_JGroupes_Clusters_Help");
    $clusterTable = new JGroupsClusterSummary();
    echo $clusterTable->getClientSortableTableStr(25, array(50, 100, 1000, 10000));

    showJGroupNICsTable();
}

    if (issetURLParam("plot")) {
        if ( requestValue("plot") === PLOT_TX || requestValue("plot") === PLOT_RX ) {
            plotNICs(requestValue('selected'), requestValue("plot"));
        }
    }
        elseif ( issetURLParam("plots")) {
            if ( requestValue("plots") == "members" ) {
                $cluster = requestValue(CLUSTER);
                $servers = requestValue("servers");
                plotMembers($cluster, $servers);
            }
        }
        else {
  $selfURL = makeSelfLink() . '?' . fromServer('QUERY_STRING');
  echo <<<EOS
<script type="text/javascript" src="$php_webroot/TOR/platform/jgroups.js"></script>
<script type="text/javascript">
 var selfURL = "$selfURL";
</script>

EOS;

  if ( issetURLParam(CLUSTER) ) {
    showCluster(requestValue(CLUSTER));
  } else {
    mainFlow();
  }
}

include_once PHP_ROOT . "/common/finalise.php";

