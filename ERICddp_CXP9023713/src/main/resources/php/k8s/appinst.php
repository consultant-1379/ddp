<?php
$pageTitle = "K8S Application Instances";

require_once 'HTML/Table.php';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const VIEW_PARAM = 'view';
const RESOURCE_VIEW = 'resource';
const DEPLOYMENT_VIEW = 'deployment';
const CONTAINER_VIEW = 'container';
const POD_VIEW = 'pod';
const PLOT_PODS_VIEW ='plotpods';
const SERVER_IDS= 'serverids';
const MAPPING_VIEW = 'mapping';
const APP_INST_PATH = '/k8s/appinst.php';
const SERVER = 'server';

function oldResourceView() {
    global $site, $date, $statsDB;

    $k8sPods = array();
    $statsDB->query("
    SELECT DISTINCT serverid
    FROM k8s_pod_cadvisor, sites
    WHERE
    k8s_pod_cadvisor.siteid = sites.id AND sites.name = '$site' AND
    k8s_pod_cadvisor.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    while ($row = $statsDB->getNextRow()) {
        $k8sPods[] = $row[0];
    }
    debugMsg("k8sPods", $k8sPods);

    $podsByApp = array();
    $statsDB->query("
    SELECT k8s_pod_app_names.name, servers.hostname, servers.id
    FROM k8s_pod, k8s_pod_app_names, sites, servers
    WHERE
    k8s_pod.siteid = sites.id AND sites.name = '$site' AND
    k8s_pod.date = '$date' AND
    k8s_pod.appid =  k8s_pod_app_names.id AND
    k8s_pod.serverid = servers.id
    ORDER BY k8s_pod_app_names.name");
    while ($row = $statsDB->getNextRow()) {
        if ( ! array_key_exists($row[0], $podsByApp) ) {
            $podsByApp[$row[0]] = array();
        }
        $podsByApp[$row[0]][$row[1]] = $row[2];
    }


    $grid = new HTML_Table("border=1");
    $grid->addRow(array("Application","Instances"), null, 'th');
    foreach ( $podsByApp as $app => $instances ) {
        $row = array($app);
        ksort($instances);
        foreach ( $instances as $hostname => $serverid ) {
            if ( in_array($serverid, $k8sPods) ) {
                $row[] = makeLink("/k8s/cadvisor.php", $hostname, array('serverid' => $serverid));
            } else {
                $row[] = $hostname;
            }
        }

        $grid->addRow($row);
    }
    $colspanValue = $grid->getColCount()-1;
    $grid->setCellAttributes(1, 0, "colspan=" .$colspanValue);

    echo "<H2>Applications</H2>\n";
    echo $grid->toHTML();
}

function resourceView() {
    global $statsDB;

    showViewLinks(RESOURCE_VIEW);
    $appTable = new ModelledTable(
        'common/k8apps',
        'k8apps',
        array(ModelledTable::URL => makeURL('/k8s/cadvisor.php'))
    );
    echo $appTable->getTableWithHeader("K8S Application Resource Utilization");
}

function plotPodsView($serverids) {
    $graphs = array();

    $params = array( SERVER_IDS => $serverids);
    $graphs = array();
    foreach ( array('cpu', 'cpu_throttled', 'mem', 'net') as $col ) {
        $modelledGraph = new ModelledGraph("common/k8s_pod_cadvisor_" . $col);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs($graphs, 1);
}

function podsOnNodeView($nodeId) {
    global $statsDB, $site, $date;

    $row = $statsDB->queryRow("SELECT hostname FROM servers WHERE id = $nodeId");
    $nodeName = $row[0];

    $podServerIds = array();
    $statsDB->query("
SELECT
  k8s_pod.serverid AS serverid
FROM k8s_pod
JOIN sites ON k8s_pod.siteid = sites.id
WHERE
k8s_pod.date = '$date' AND
k8s_pod.nodeid = $nodeId AND
sites.name = '$site'");
    while ( $row = $statsDB->getNextRow() ) {
        $podServerIds[] = $row[0];
    }
    debugMsg("podsOnNodeView podServerIds", $podServerIds);
    $table = new ModelledTable(
        'common/k8spods',
        'k8spods',
        array(
            ModelledTable::URL => makeURL(APP_INST_PATH, array(VIEW_PARAM => PLOT_PODS_VIEW)),
            'serverids' => implode(",", $podServerIds)
        )
    );
    echo $table->getTableWithHeader("Pods on $nodeName");
}

function getNodeExporterStats(&$data) {
    global $site, $date, $statsDB;

    if ( ! $statsDB->hasData('hires_server_stat') ) {
        return false;
    }

    $statsDB->query("
SELECT
 hires_server_stat.serverid AS serverid,
 ROUND(AVG(hires_server_stat.user+hires_server_stat.sys), 1) AS cpu,
 ROUND(AVG(hires_server_stat.memused/1024), 1) AS mem
FROM hires_server_stat
JOIN sites ON hires_server_stat.siteid = sites.id
WHERE
 hires_server_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 sites.name = '$site'
GROUP BY hires_server_stat.serverid
ORDER BY cpu DESC");
    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( array_key_exists($row['serverid'], $data) ) {
            $data[$row['serverid']]['cpu'] = $row['cpu'];
            $data[$row['serverid']]['mem'] = $row['mem'];
        }
    }

    $statsDB->query("
SELECT
 hires_disk_stat.serverid AS serverid,
 ROUND(AVG(blks),1) AS disk_blks
FROM hires_disk_stat
JOIN sites ON hires_disk_stat.siteid = sites.id
WHERE
 hires_disk_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 sites.name = '$site'
GROUP BY hires_disk_stat.serverid");
    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( array_key_exists($row['serverid'], $data) ) {
            $data[$row['serverid']]['diskio'] = $row['disk_blks'];
        }
    }

    $statsDB->query("
SELECT
 nic_stat.serverid AS serverid,
 ROUND((AVG(nic_stat.ibytes_per_sec+nic_stat.obytes_per_sec)*8)/1000000, 1) AS net_mbit
FROM nic_stat
JOIN sites ON nic_stat.siteid = sites.id
WHERE
nic_stat.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 sites.name = '$site'
GROUP BY nic_stat.serverid");
    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( array_key_exists($row['serverid'], $data) ) {
            $data[$row['serverid']]['netio'] = $row['net_mbit'];
        }
    }

    return true;
}

function deploymentView() {
    global $site, $date, $statsDB;

    showViewLinks(DEPLOYMENT_VIEW);
    drawHeader("K8S Nodes", HEADER_2, '');
    $data = array();
    $statsDB->query("
SELECT k8s_node.serverid, servers.hostname
FROM k8s_node
JOIN sites ON k8s_node.siteid = sites.id
JOIN servers ON k8s_node.serverid = servers.id
WHERE
 k8s_node.date = '$date' AND
 sites.name = '$site'");
    while ( $row = $statsDB->getNextRow() ) {
        $data[$row[0]] = array('serverid' => $row[0], SERVER => $row[1]);
    }
    debugMsg("deploymentView data 1", $data);

    $statsDB->query("
SELECT
 k8s_pod.nodeid AS nodeid,
 COUNT(*) AS n_pods
FROM k8s_pod
JOIN sites ON k8s_pod.siteid = sites.id
WHERE
 k8s_pod.date = '$date' AND
 sites.name = '$site'
GROUP BY k8s_pod.nodeid");
    while ( $row = $statsDB->getNextNamedRow() ) {
        $data[$row['nodeid']]['n_pods'] = $row['n_pods'];
    }

    $hasNodeExporterStats = getNodeExporterStats($data);

    debugMsg("deploymentView data 2", $data);

    $params = array( SERVER_IDS => implode(",", array_keys($data)));

    if ( $hasNodeExporterStats ) {
        $graphs = array();
        foreach ( array('cpu', 'mem') as $col ) {
            $modelledGraph = new ModelledGraph("common/hires_server_stat_" . $col);
            $graphs[] = $modelledGraph->getImage($params);
        }
        plotgraphs($graphs, 1);
    }

    $baseURL = makeURL('/server.php');
    echo <<<ESCRIPT
<script type="text/javascript">
function formatNode(elCell, oRecord, oColumn, oData) {
    var serverid = oRecord.getData("serverid");
    var refLink = "$baseURL" + "&serverid=" + serverid + "&server=" + oData;
    elCell.innerHTML = "<a href=\"" + refLink + "\">" + oData + "</a>";
}
</script>
ESCRIPT;

    if ( $hasNodeExporterStats ) {
        $columns = array(
            array( DDPTable::KEY => 'serverid', DDPTable::VISIBLE => false),
            array( DDPTable::KEY => SERVER, DDPTable::LABEL => 'Node', DDPTable::FORMATTER => 'formatNode'),
            array( DDPTable::KEY => 'cpu', DDPTable::LABEL => 'CPU %', DDPTable::TYPE => 'float'),
            array( DDPTable::KEY => 'mem', DDPTable::LABEL => 'Memory GB', DDPTable::TYPE => 'float'),
            array( DDPTable::KEY => 'diskio', DDPTable::LABEL => 'Disk I/O (blk/s)', DDPTable::TYPE => 'float'),
            array( DDPTable::KEY => 'netio', DDPTable::LABEL => 'Network (bytes/s)', DDPTable::TYPE => 'float'),
            array( DDPTable::KEY => 'n_pods', DDPTable::LABEL => '# Pods', DDPTable::TYPE => 'int')
        );
    } else {
        $columns = array(
            array( DDPTable::KEY => 'serverid', DDPTable::VISIBLE => false),
            array( DDPTable::KEY => SERVER, DDPTable::LABEL => 'Node'),
            array( DDPTable::KEY => 'n_pods', DDPTable::LABEL => '# Pods', DDPTable::TYPE => 'int')
        );
    }

    $table = new DDPTable(
        "deployment",
        $columns,
        array('data' => array_values($data)),
        array (
            DDPTable::CTX_MENU => array(
                DDPTable::KEY => VIEW_PARAM,
                DDPTable::MULTI => false,
                DDPTable::MENU => array( POD_VIEW => 'Show Pods'),
                DDPTable::URL => makeSelfLink(),
                DDPTable::COL => 'serverid')
        )
    );
    echo $table->getTable();
}

function containerView() {
    showViewLinks(CONTAINER_VIEW);
    $table = new ModelledTable(
        'common/k8s_container_cadvisor',
        'containers',
        array(ModelledTable::URL => makeURL('/k8s/cadvisor.php'))
    );
    echo $table->getTableWithHeader("K8S Container Resource Utilization");
}

function mappingView() {
    showViewLinks(MAPPING_VIEW);
    $table = new ModelledTable(
        'common/k8s_pod_mapping',
        'podmapping'
    );
    echo $table->getTableWithHeader("Pod Mapping");
}

function showViewLinks($currentView) {
    global $statsDB;

    $links = array();
    if ( $currentView != MAPPING_VIEW) {
        $links[] = makeLink(APP_INST_PATH, 'Mapping View', array(VIEW_PARAM => MAPPING_VIEW));
    }
    if ($currentView != RESOURCE_VIEW && $statsDB->hasData('k8s_container_cadvisor') ) {
        $links[] = makeLink(APP_INST_PATH, 'Resource View', array(VIEW_PARAM => RESOURCE_VIEW));
    }
    if ($currentView != DEPLOYMENT_VIEW && $statsDB->hasData('k8s_pod', 'date', true, 'nodeid IS NOT NULL') ) {
        $links[] = makeLink(APP_INST_PATH, 'Deployment View', array(VIEW_PARAM => DEPLOYMENT_VIEW));
    }
    if ($currentView != CONTAINER_VIEW && $statsDB->hasData('k8s_container_cadvisor') ) {
        $links[] = makeLink(APP_INST_PATH, 'Container View', array(VIEW_PARAM => CONTAINER_VIEW));
    }

    echo makeHTMLList($links);
}

function main() {
    global $statsDB;

    $view = requestValue(VIEW_PARAM);
    if ( is_null($view) ) {
        $view = RESOURCE_VIEW;
    }

    if ( $view === RESOURCE_VIEW ) {
        if ( $statsDB->hasData('k8s_pod_cadvisor', 'time', false, 'appid IS NOT NULL') ) {
            resourceView();
        } else {
            oldResourceView();
        }
    } elseif ( $view === CONTAINER_VIEW ) {
        containerView();
    } elseif ( $view === MAPPING_VIEW ) {
        mappingView();
    } elseif ( $view === POD_VIEW ) {
        podsOnNodeView(requestValue('selected'));
    } elseif ( $view === PLOT_PODS_VIEW ) {
        plotPodsView(requestValue('selected'));
    } else {
        deploymentView();
    }

}

main();

require_once PHP_ROOT . "/common/finalise.php";
