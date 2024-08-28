<?php
$pageTitle = "Consul/SAM Events";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";


$startDate = $_GET['start'];
$endDate   = $_GET['end'];
$eventName = $_GET['eventName'];

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

        $sql = "
SELECT
    DISTINCT service_groups.service_id AS 'id',
    IF(service_groups.service_name = '', 'NA', service_groups.service_name) AS 'name'
FROM
    (SELECT
        DISTINCT enm_consul_n_sam_events.serverid AS 'server_id'
    FROM
        enm_consul_n_sam_events,
        sites
    WHERE
        enm_consul_n_sam_events.siteid = sites.id AND
        ( enm_consul_n_sam_events.event_type = 'Consul' OR enm_consul_n_sam_events.event_type = 'SAM' ) AND
        sites.name = '$site' AND
        enm_consul_n_sam_events.time BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
     ) AS consul_members
        INNER JOIN
    (SELECT
        distinct enm_servicegroup_instances.serverid AS 'server_id',
        enm_servicegroup_instances.serviceid AS 'service_id',
        enm_servicegroup_names.name AS 'service_name'
    FROM
        enm_servicegroup_instances,
        enm_servicegroup_names,
        sites
    WHERE
        enm_servicegroup_instances.siteid = sites.id AND
        enm_servicegroup_instances.serviceid = enm_servicegroup_names.id AND
        enm_servicegroup_instances.date between '$startDate' and '$endDate' AND
        sites.name = '$site'
    ) AS service_groups
        ON consul_members.server_id = service_groups.server_id
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

class HostTable extends DDPObject {
    var $cols = array(
        'id' => 'ID',
        'hostname' => 'Host'
    );
    var $serviceGroup = '';

    function __construct($serviceGroup) {
        parent::__construct("Nodes");
        $this->serviceGroup = $serviceGroup;
    }

    function getData() {
        global $startDate, $endDate, $site;

        $sql = "
SELECT
    @rownum:=@rownum+1 AS id,
    hosts.hostname AS hostname
FROM
    (SELECT DISTINCT
        servers.hostname AS 'hostname'
    FROM
        enm_consul_n_sam_events,
        enm_servicegroup_instances,
        servers,
        sites
    WHERE
        enm_consul_n_sam_events.siteid = sites.id AND
        enm_consul_n_sam_events.serverid = servers.id AND
        servers.siteid = sites.id AND
        enm_servicegroup_instances.siteid = sites.id AND
        enm_servicegroup_instances.serverid = servers.id AND
        enm_servicegroup_instances.serviceid = '$this->serviceGroup' AND
        sites.name = '$site' AND
        enm_servicegroup_instances.date BETWEEN '$startDate' AND '$endDate' AND
        enm_consul_n_sam_events.time BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
    order by hostname) AS hosts
        JOIN
    (SELECT @rownum:=0) AS counter";

        $this->populateData($sql);
        return $this->data;
    }
}

function getConsulEventGraphParam($eventType, $serviceGroup = '') {
    global $startDate, $endDate, $site;

    if ( $serviceGroup ) {
        $timecol = "enm_consul_n_sam_events.time";
        $what = "server_serial_nos.serial_no";
        $tables = "
    (SELECT
        enm_consul_n_sam_events.time AS 'time',
        enm_consul_n_sam_events.serverid AS 'server_id'
    FROM
        enm_consul_n_sam_events,
        enm_consul_event_names,
        sites";

        $where = "
        enm_consul_n_sam_events.siteid = sites.id AND
        enm_consul_n_sam_events.event_id = enm_consul_event_names.id AND
        enm_consul_event_names.name = '$eventType' AND
        sites.name = '%s'
    ) AS enm_consul_n_sam_events
        INNER JOIN
    (SELECT
        distinct enm_servicegroup_instances.serverid AS 'server_id',
        enm_servicegroup_instances.serviceid AS 'service_id'
    FROM
        enm_servicegroup_instances,
        sites
    WHERE
        enm_servicegroup_instances.siteid = sites.id AND
        enm_servicegroup_instances.serviceid = '%s' AND
        sites.name = '%s' AND
        enm_servicegroup_instances.date BETWEEN '%s' and '%s'
    ) AS service_groups ON enm_consul_n_sam_events.server_id = service_groups.server_id
        INNER JOIN
    (SELECT
        @rownum:=@rownum+1 AS 'serial_no',
        hosts.server_id AS 'server_id'
    FROM
        (SELECT DISTINCT
            servers.id AS 'server_id'
        FROM
            enm_consul_n_sam_events,
            enm_servicegroup_instances,
            servers,
            sites
        WHERE
            enm_consul_n_sam_events.siteid = sites.id AND
            enm_consul_n_sam_events.serverid = servers.id AND
            servers.siteid = sites.id AND
            enm_servicegroup_instances.siteid = sites.id AND
            enm_servicegroup_instances.serverid = servers.id AND
            enm_servicegroup_instances.serviceid = '%s' AND
            sites.name = '%s' AND
            enm_servicegroup_instances.date BETWEEN '%s' AND '%s' AND
            enm_consul_n_sam_events.time BETWEEN '%s 00:00:00' AND '%s 23:59:59'
        ORDER BY servers.hostname
        ) AS hosts
            JOIN
        (SELECT @rownum:=0) AS counter
    ) AS server_serial_nos ON enm_consul_n_sam_events.server_id = server_serial_nos.server_id";

        $qargs = array( 'site', 'filter_param', 'site', 'start', 'end', 'filter_param', 'site', 'start', 'end', 'start', 'end' );
    }
    else {
        $timecol = "enm_consul_n_sam_events.time";
        $what = "service_groups.service_id";

        $tables = "
    (SELECT
        enm_consul_n_sam_events.time AS 'time',
        enm_consul_n_sam_events.serverid AS 'server_id'
    FROM
        enm_consul_n_sam_events,
        enm_consul_event_names,
        sites";

    $where = "
        enm_consul_n_sam_events.siteid = sites.id AND
        enm_consul_n_sam_events.event_id = enm_consul_event_names.id AND
        enm_consul_event_names.name = '$eventType' AND
        sites.name = '%s'
    ) AS enm_consul_n_sam_events
        INNER JOIN
    (SELECT
        DISTINCT enm_servicegroup_instances.serverid AS 'server_id',
        enm_servicegroup_instances.serviceid AS 'service_id'
    FROM
        enm_servicegroup_instances,
        sites
    WHERE
        enm_servicegroup_instances.siteid = sites.id AND
        sites.name = '%s' AND
        enm_servicegroup_instances.date between '%s' AND '%s'
    ) AS service_groups
        ON enm_consul_n_sam_events.server_id = service_groups.server_id";

        $qargs = array( 'site', 'site', 'start', 'end' );
    }

    $queryParam = array(
        'timecol' => $timecol,
        'whatcol' => array( $what => $eventType ),
        'tables' => $tables,
        'where' => $where,
        'qargs' => $qargs
    );

    return $queryParam;
}

function showEventsGraph($serviceGroup = '') {
    global $startDate, $endDate, $eventName;

    $label = "Service Group ID";
    $helpTitle = $eventName." Events";
    $helpReference = "DDP_Bubble_393_ENM_monthly_Consul_N_SAM_events";

    if ($serviceGroup) {
        $serviceGroupName = getServiceGroupName( $serviceGroup );
        drawHeaderWithHelp("$serviceGroupName $helpTitle", 2, "profilesGraphHelp", $helpReference);
        $hostTable = new HostTable($serviceGroup);
        drawHeaderWithHelp("Host ID Mapping", 4, "hostIDsHelp", $hostTable->getClientSortableTableStr());
        $label = "Host ID";
    } else {
        drawHeaderWithHelp($helpTitle, 2, "profilesGraphHelp", $helpReference);
    }

    $queryList = array(
        getConsulEventGraphParam('MemberFailed_HealthCritical', $serviceGroup),
        getConsulEventGraphParam('MemberFailed_VnfLafNotified', $serviceGroup),
        getConsulEventGraphParam('MemberLeft_Deregistering', $serviceGroup),
        getConsulEventGraphParam('MemberJoined_HealthAlive', $serviceGroup)
    );

    $sqlParam = array(
        'title' => $helpTitle,
        'type' => 'xy',
        'ylabel' => $label,
        'useragg' => 'true',
        'persistent' => 'true',
        'forcelegend' => 'true',
        'querylist' => $queryList
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo "<p>" . $sqlParamWriter->getImgURL($id, "$startDate 00:00:00", "$endDate 23:59:59", true, 800, 500, "start=$startDate&end=$endDate&filter_param=$serviceGroup") . "</p>\n";
}

function getServiceGroupName ($serviceGroupID) {
    $statsDB = new StatsDB();
    $row = $statsDB->queryRow("SELECT name FROM enm_servicegroup_names WHERE id = $serviceGroupID");
    return $row[0];
}

function main() {
    global $php_webroot, $site, $oss, $startDate, $endDate, $year, $month;

    $serviceGroup = isset($_GET['serviceGroup']) ? $_GET['serviceGroup'] : '';

    echo '<table><tr><td valign="top">';
    echo '<div class="drill-down-table" style="max-height:600px">';
    if ( $serviceGroup != '' ) {
        $query = getQueryWithoutArg('serviceGroup');
        echo "<p align='center'><a href='" . $_SERVER['PHP_SELF'] . "?$query'>View for All Service Groups</a></p>";
    }

    $serviceGroups = new ServiceGroups();
    echo $serviceGroups->getClientSortableTableStr();
    echo '</div>';
    echo '</td><td valign="top">';
    showEventsGraph($serviceGroup);
    echo "</td></tr></table>";
}

main();

include PHP_ROOT . "/common/finalise.php";
?>
