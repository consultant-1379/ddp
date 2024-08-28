<?php
$pageTitle = "VCS Events";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";


$startDate = $_GET['start'];
$endDate   = $_GET['end'];

function getQueryWithoutArg($arg) {
    $params = $_GET;
    if (isset($params[$arg])) {
        unset($params[$arg]);
    }
    $query = http_build_query($params);
    return $query;
}

function getQueryWithArg($arg, $value) {
    $params = $_GET;
    $params[$arg] = $value;
    $query = http_build_query($params);
    return $query;
}

class ServiceGroups extends DDPObject {
    var $cols = array(
        'id' => 'ID',
        'name' => 'Service Group'
    );

    function __construct() {
        parent::__construct("Service_Groups");
    }

    function getData() {
        global $startDate, $endDate, $site;
        global $php_webroot, $oss, $year, $month;

        $sql="
SELECT DISTINCT
  enm_cluster_svc_names.id AS id,
  IF(enm_cluster_svc_names.name = '', 'NA', enm_cluster_svc_names.name) AS name
FROM
  enm_cluster_svc_names
  JOIN enm_vcs_events
    ON enm_cluster_svc_names.id  = enm_vcs_events.serviceid
  JOIN sites
    ON sites.id = enm_vcs_events.siteid
WHERE
  sites.name = '$site'
  AND enm_vcs_events.time between '$startDate 00:00:00' AND '$endDate 23:59:59'
ORDER BY id";

        $this->populateData($sql);
        foreach ($this->data as &$row) {
             $url = $_SERVER['PHP_SELF'] . "?" . getQueryWithArg('serviceGroup', $row['id']);
             # The comments at the start of each entry are for sorting purposes.
             $row['name'] = "<!--" . $row['name'] . "--><a href='$url'>" . $row['name'] . "</a>";
        }
        return $this->data;
    }
}

class NodeTable extends DDPObject {
    var $cols = array(
        'id' => 'ID',
        'nodename' => 'Node'
    );

    function __construct() {
        parent::__construct("Nodes");
    }

    function getData() {
        global $startDate, $endDate, $site;

        $sql = "
SELECT
  @rownum:=@rownum+1 AS id,
  nodes.nodename AS nodename
FROM
  ( SELECT DISTINCT
      nodename
    FROM enm_cluster_host
	ORDER BY nodename
  ) AS nodes
  JOIN (SELECT @rownum:=0) AS counter";

        $this->populateData($sql);
        return $this->data;
    }
}

function getVCSEventGraphParam($eventType, $serviceGroup = '') {
    $tables = "enm_vcs_events, sites, enm_cluster_svc_names, servers";
    $where = "sites.id = enm_vcs_events.siteid AND sites.name = '%s' AND enm_cluster_svc_names.id  = enm_vcs_events.serviceid AND servers.id = enm_vcs_events.serverid AND eventtype = '$eventType'";
    $what = 'enm_cluster_svc_names.id';

    if ( $serviceGroup ) {
        $nodeIDSQL = "
SELECT
  @rownum:=@rownum+1 AS id,
  nodes.nodename AS nodename
FROM
  ( SELECT DISTINCT
      nodename
    FROM enm_cluster_host
	ORDER BY nodename
  ) AS nodes
  JOIN (SELECT @rownum:=0) AS counter
";

        $tables .= " ,enm_cluster_host , ($nodeIDSQL) AS nodes";
        $where .= " AND enm_cluster_svc_names.id = '%s' AND enm_cluster_host.serverid = enm_vcs_events.serverid AND nodes.nodename = enm_cluster_host.nodename";
        $what = "nodes.id";
    }

    $queryParam = array(
        'timecol' => 'enm_vcs_events.time',
        'whatcol' => array( $what => $eventType ),
        'tables' => $tables,
        'where' => $where,
        'qargs' => array( 'site', 'filter_param' )
    );
    return $queryParam;
}

function showEventsGraph($serviceGroup = '', $seriesSet = '') {
    global $startDate, $endDate;

    $label = "Service Group ID";
    $helpTitle = "VCS Events";
    $helpReference = "DDP_Bubble_275_ENM_monthly_vcs_events_help";

    if ($serviceGroup) {
        $serviceGroupName = getServiceGroupName( $serviceGroup );
        drawHeaderWithHelp("$serviceGroupName $helpTitle", 2, "profilesGraphHelp", $helpReference);
        $nodeTable = new NodeTable();
        drawHeaderWithHelp("Node ID Mapping", 4, "nodeIDsHelp", $nodeTable->getClientSortableTableStr());
        $label = "Node ID";
    }
    else {
        drawHeaderWithHelp($helpTitle, 2, "profilesGraphHelp", $helpReference);
    }

    $queryList = array();

    if($seriesSet == '1') {
        $queryList = array(
            getVCSEventGraphParam('MonitorTimeout', $serviceGroup),
            getVCSEventGraphParam('CleanStart', $serviceGroup),
            getVCSEventGraphParam('RestartStart', $serviceGroup),
            getVCSEventGraphParam('MonitorOffline', $serviceGroup),
            getVCSEventGraphParam('Faulted', $serviceGroup)
        );
    } else {
        $queryList = array(
            getVCSEventGraphParam('unfreeze persistent', $serviceGroup),
            getVCSEventGraphParam('freeze persistent evacuate', $serviceGroup),
            getVCSEventGraphParam('MonitorTimeout', $serviceGroup),
            getVCSEventGraphParam('CleanStart', $serviceGroup),
            getVCSEventGraphParam('CleanCompleted', $serviceGroup),
            getVCSEventGraphParam('RestartStart', $serviceGroup),
            getVCSEventGraphParam('RestartCompleted', $serviceGroup),
            getVCSEventGraphParam('MonitorOffline', $serviceGroup),
            getVCSEventGraphParam('OfflineStart', $serviceGroup),
            getVCSEventGraphParam('OfflineCompleted', $serviceGroup),
            getVCSEventGraphParam('OnlineStart', $serviceGroup),
            getVCSEventGraphParam('OnlineCompleted', $serviceGroup),
            getVCSEventGraphParam('Faulted', $serviceGroup)
        );
    }

    $sqlParam = array(
        'title' => 'VCS Events',
        'type' => 'xy',
        'ylabel' => $label,
        'useragg' => 'true',
        'persistent' => 'true',
        'querylist' => $queryList
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo "<p>" . $sqlParamWriter->getImgURL($id, "$startDate 00:00:00", "$endDate 23:59:59", true, 800, 500, "filter_param=$serviceGroup") . "</p>\n";

    if($seriesSet == '1') {
        $url = $_SERVER['PHP_SELF'] . "?" . getQueryWithoutArg('seriesSet');
        echo "<a href='$url'>Show All Events.</a>";
    } else {
        $url = $_SERVER['PHP_SELF'] . "?" . getQueryWithArg('seriesSet', '1');
        echo "<a href='$url'>Show Only MonitorTimeout, CleanStart, RestartStart, MonitorOffline & Faulted.</a>";
    }
}

function getServiceGroupName ( $serviceGroupID) {
    $statsDB = new StatsDB();
    $row = $statsDB->queryRow("SELECT name FROM enm_cluster_svc_names WHERE id = $serviceGroupID");
    return $row[0];
}

function main() {
    global $php_webroot, $site, $oss, $startDate, $endDate, $year, $month;

    $serviceGroup = isset($_GET['serviceGroup']) ? $_GET['serviceGroup'] : '';
    $seriesSet = isset($_GET['seriesSet']) ? $_GET['seriesSet'] : '';

    echo '<table><tr><td valign="top">';
    echo '<div class="drill-down-table" style="max-height:600px">';
    if ( $serviceGroup != '' ) {
        $query = getQueryWithoutArg('serviceGroup');
        echo "<p align='center'><a href='$php_webroot/monthly/TOR/vcs_events.php?$query'>View All VCS Groups</a></p>";
    }
    $serviceGroups = new ServiceGroups();
    echo $serviceGroups->getClientSortableTableStr();
    echo '</div>';
    echo '</td><td valign="top">';
    showEventsGraph($serviceGroup, $seriesSet);
    echo "</td></tr></table>";
}

main();

include PHP_ROOT . "/common/finalise.php";
?>
