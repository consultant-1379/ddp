<?php
$pageTitle = "NMA Instrumentation";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

$phpDir = dirname($_SERVER['PHP_SELF']);
$reportGeneration = $_SERVER['SERVER_NAME'] . $phpDir . "/../adminui/reports.php";
$statsDB = new StatsDB();


class getNodeSyncStatusResults extends DDPObject {
    var $cols = array(
        "date" => "Time",
        "sync_success" => "Number nodes sync Success",
        "sync_failed" => "Number nodes sync Failed",
        "unsynced" => "Number of Unsync Nodes",
        "top_sync" => "Number Topology Synch Nodes",
        "att_sync" => "Number of Attribute Sync Nodes",
        "sgsn_mme_ongoing" => "Sync ongoing for SGSN/MME",
        "epg_ongoing" => "Sync ongoing for EPG",
        "h_two_s_ongoing" => "Sync ongoing for H2S",
        "mtas_ongoing" => "Sync ongoing for MTAS",
        "cscf_ongoing" => "Sync ongoing for CSCF",
        "prbs_ongoing" => "Sync ongoing for PRBS"
    );

    var $defaultOrderBy = "date";
    var $defaultOrderDir = "ASC";

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");


    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT  nss.date AS 'date',
                    nss.sync_success AS 'sync_success',
                    nss.sync_failed AS 'sync_failed',
                    nss.unsynced AS 'unsynced',
                    nss.top_sync AS 'top_sync',
                    nss.att_sync AS 'att_sync',
                    nss.sgsn_mme_ongoing AS 'sgsn_mme_ongoing',
                    nss.epg_ongoing AS 'epg_ongoing',
                    nss.h_two_s_ongoing AS 'h_two_s_ongoing',
                    nss.mtas_ongoing AS 'mtas_ongoing',
                    nss.cscf_ongoing AS 'cscf_ongoing',
                    nss.prbs_ongoing AS 'prbs_ongoing'
            FROM    nma_node_sync_status_data nss, sites
            WHERE   nss.date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     nss.siteid = sites.id AND sites.name = '" . $site . "'";

        $this->populateData($sql);

        return $this->data;

    }


}

class getStatisticsInstrTable extends DDPObject {
    var $cols = array(
        "date" => "Time",
        "sync_success" => "Sync Success",
        "alive_nodes" => "Number of total alive nodes",
        "total_node_sync" => "Number of total nodes synched",
        "node_count" => "Total number of nodes"
    );

    var $defaultOrderBy = "sync_success";
    var $defaultOrderDir = "DESC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT  ns.date AS 'date',
                    ns.sync_success AS 'sync_success',
                    ns.alive_nodes AS 'alive_nodes',
                    ns.total_node_sync AS 'total_node_sync',
                    ns.node_count AS 'node_count'
            FROM    nma_stats_data ns, sites
            WHERE   ns.date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     ns.siteid = sites.id AND sites.name = '" . $site . "'";

        $this->populateData($sql);

        return $this->data;
    }


}

class getNotifcationHandeledTable extends DDPObject {
  var $cols = array(
        "date" => "Time",
        "notif_rec_in_fifteen_min" => "Number of Notifs received in 15 min ROP",
        "avg_notif_per_sec" => "Avg Notifs per sec",
        "notif_in_buffer" => "Number of Notifs in buffer",
        "avg_ttp_notif" => "Avg time to process a notif (mS)",
        "mx_ttp_notif" =>  "Max time to process a notif (mS)"
    );

    var $defaultOrderBy = "notif_rec_in_fifteen_min";
    var $defaultOrderDir = "DESC";

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT  nhd.date AS 'date',
                    nhd.notif_in_buffer AS 'notif_in_buffer',
                    nhd.notif_rec_in_fifteen_min AS 'notif_rec_in_fifteen_min',
                    FLOOR(nhd.notif_rec_in_fifteen_min/900) AS 'avg_notif_per_sec',
                    nhd.avg_ttp_notif AS 'avg_ttp_notif',
                    nhd.mx_ttp_notif AS 'mx_ttp_notif'
            FROM    nma_notif_handling_data nhd, sites
            WHERE   nhd.date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     nhd.siteid = sites.id AND sites.name = '" . $site . "'";

        $this->populateData($sql);

        return $this->data;

    }

    function totalCollection(){
        global $date, $site, $statsDB;

        $totalTable = new HTML_Table('border=1');
        $totalTable->addRow( array( "Statistic", "Value" ), null, 'th' );

        $row = $statsDB->queryRow(" 
            SELECT SUM(notif_rec_in_fifteen_min),
                FLOOR(SUM(notif_rec_in_fifteen_min)/(COUNT(notif_rec_in_fifteen_min)*900))
            FROM nma_notif_handling_data nhd, sites
            WHERE date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND siteid = sites.id AND sites.name = '" . $site . "'
            AND nhd.notif_rec_in_fifteen_min > 0");


        $totalTable->addRow( array( "Total Number of notifications received", $row[0] ) );
        $totalTable->addRow( array( "Avg Number notifications received per second", $row[1] ) );

        return $totalTable;
    }
}



class getNotifcationRecievedTable extends DDPObject {
    var $cols = array(
        "date" => "Time",
        "event_type" => "Event Type",
        "node_type" => "Node Type",
        "mo" => "MO",
        "attribute" => "Attribute",
        "count" => "Count"
    );

    var $defaultOrderBy = "event_type";
    var $defaultOrderDir = "DESC";

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT  nr.date AS 'date',
                    nr.event_type AS 'event_type',
                    nr.node_type AS 'node_type',
                    nr.mo AS 'mo',
                    nr.attribute AS 'attribute',
                    nr.count AS 'count'
            FROM    nma_notif_recieved_data nr, sites
            WHERE   nr.date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     nr.siteid = sites.id AND sites.name = '" . $site . "'";

        $this->populateData($sql);

        return $this->data;
    }


   function getTotal(){
        global $date, $site, $statsDB;

        $totalTable = new HTML_Table('border=1');
        $totalTable->addRow( array( "MO", "Node Type", "Attribute", "Count" ), null, 'th' );

        $row = $statsDB->query(" 
            SELECT  mo AS 'mo',
                    node_type AS 'node_type',
                    attribute AS 'attribute',
                    SUM(count) AS 'count'
            FROM    nma_notif_recieved_data, sites
            WHERE   date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     siteid = sites.id AND sites.name = '" . $site . "'
            GROUP BY attribute,mo,node_type,event_type
            ORDER BY count DESC");

        while($row = $statsDB->getNextNamedRow()) {
            $totalTable->addRow( array( $row['mo'], $row['node_type'], $row['attribute'], $row['count'] ) );
        }

        return $totalTable;
    }

}


class getNodeSyncResults extends DDPObject {
    var $cols = array(
        "date" => "Time",
        "rns" => "RNS",
        "node" => "Node",
        "success" => "Successful Syncs",
        "failure" =>  "Failed Syncs"
    );

    var $defaultOrderBy = "date";
    var $defaultOrderDir = "ASC";

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");
    var $filter = "";

    function __construct($filter = "") {
        parent::__construct("sync_succ");
        $this->filter = $filter;
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT  nsd.date AS 'date',
                    rns.name AS 'rns',
                    ne.name AS 'node',
                    nsd.failure AS 'failure',
                    nsd.success AS 'success'
            FROM    nma_sync_by_node_data nsd, ne, rns, sites
            WHERE   nsd.date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     nsd.siteid = sites.id AND sites.name = '" . $site . "' AND
                    nsd.neid = ne.id AND ne.rnsid = rns.id " . $this->filter;

        $this->populateData($sql);
                    

        return $this->data;

    }
}

class getConnectionStatusResults extends DDPObject {
    var $cols = array(
        "rns" => "RNS",
        "node" => "Node",
	"connect" => "Number of Connects",
        "disconnect" => "Number of Disconnects"
    );

    var $defaultOrderBy = "disconnect";
    var $defaultOrderDir = "DESC";

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, 1000 => 1000, 10000 => 10000, "" => "Unlimited");


    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT  rns.name AS 'rns',
                    ne.name AS 'node',
	                SUM(csd.no_connect) AS 'connect',
                    SUM(csd.no_disconnect) AS 'disconnect'
            FROM    nma_con_status_data csd, ne, rns, sites
            WHERE   csd.date BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND     csd.siteid = sites.id AND sites.name = '" . $site . "' AND
                    csd.neid = ne.id AND ne.rnsid = rns.id GROUP by csd.neid";


        $this->populateData($sql);

        return $this->data;

    }
}

?>
<li><a href="?<?=$webargs?>&tbl=syncStatus#syncStatus">Node Sync Status</a></li>
<li><a href="?<?=$webargs?>&tbl=conStatus#conStatus">Node Connection Status</a></li>
<li><a href="?<?=$webargs?>&tbl=syncResults#syncResults">Sync Results by Node</a></li>
<li><a href="?<?=$webargs?>&tbl=syncStatistics#syncStatistics">Statistic Information</a></li>
<li><a href="?<?=$webargs?>&tbl=handeledNotif#handeledNotif">Notification Handling Info</a></li>
<li><a href="?<?=$webargs?>&tbl=recievedNotif#recievedNotif">Notification Recieved Info</a></li>

<?php
if (isset($_GET['tbl'])) {
    if ($_GET['tbl'] == "syncResults" || $_GET['tbl'] == "conStatus" || $_GET['tbl'] == "syncStatus" || $_GET['tbl'] == "syncStatistics" || $_GET['tbl'] == "recievedNotif" || $_GET['tbl'] == "handeledNotif") {
        $doTbl = $_GET['tbl'];
    }
}else $doTbl = "syncStatus"; 

if ($doTbl == "syncResults") {
    echo '<h1><a name="syncResults"></a>Sync Results by Node</h1>';

    $filter = "";
    if (isset($_GET['filter_node']) && $_GET['filter_node'] != "")
        $filter = $filter . " AND ne.name = '" . $statsDB->escape($_GET['filter_node']) . "'";
    if (isset($_GET['filter_rns']) && $_GET['filter_rns'] != "")
        $filter = $filter . " AND rns.name = '" . $statsDB->escape($_GET['filter_rns']) . "'";
    
    $tbl = new getNodeSyncResults($filter);
    $tbl->getSortableHtmlTable();
?>
       <form name=syncfilter action="?<?=$_SERVER['PHP_SELF']?>" method=get>
<?php
    foreach ($_GET as $key => $val) {
        if ($key == "filter_node" || $key == "filter_rns" || $key == "submit") continue;
        echo "<input type=hidden name='" . $key . "' value='" . $val . "' />\n";
    }
?>

<h3>Filter:</h3>
Node: <input type=text name=filter_node value="<?php if(isset($_GET['filter_node'])){echo "";}?>" /><br />
RNS: <input type=text name=filter_rns value="<?php if(isset($_GET['filter_rns'])){echo "";}?>" /><br />
<input type=submit name=submit value="Submit ..." />
</form>

<?php
}// end if ($doTbl == "syncResults")
if ($doTbl == "conStatus") {
    echo '<h1><a name="conStatus"></a>Node Connection Status</h1>';
    $tbl = new getConnectionStatusResults();
    $tbl->getSortableHtmlTable();
}// end if ($doTbl == "syncResults")
else if ($doTbl == "syncStatus"){
    echo '<h1><a name="syncStatus"></a>Node-Sync Status</h1>';
    $tbl = new getNodeSyncStatusResults();
    $tbl->getSortableHtmlTable();
}
else if ($doTbl == "syncStatistics"){
    echo '<h1><a name="syncStats"></a>Statistic Information</h1>';
    $tbl = new getStatisticsInstrTable();
    $tbl->getSortableHtmlTable();
}
else if ($doTbl == "handeledNotif"){
    echo '<h1><a name="handeledNotif"></a>Notification Handling Info</h1>';
    echo getNotifcationHandeledTable::totalCollection()->toHTML();
    echo '<br/><br/><h2>Notification Handling</h2>';
    $tbl = new getNotifcationHandeledTable();
    $tbl->getSortableHtmlTable();
}
else if ($doTbl == "recievedNotif"){
    echo '<h1><a name="recievedNotif"></a>Notification Received Info</h1>';
    echo getNotifcationRecievedTable::getTotal()->toHTML();
    echo '<br/><br/><h2>Notifications Received</h2>';
    $tbl = new getNotifcationRecievedTable();
    $tbl->getSortableHtmlTable();
}

include "../php/common/finalise.php";
return;
?>
