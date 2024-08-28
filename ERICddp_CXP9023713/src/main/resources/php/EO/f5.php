<?php

$pageTitle = 'F5';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function drawGraphGroup($group) {
    $graphs = array();
    foreach ( $group['graphs'] as $modelledGraph ) {
        $graphs[] = array( $modelledGraph->getImage(null, null, null, 640, 240) );
    }
    plotgraphs( $graphs, 1 );
}

function plotStats($selected, $type) {
    $cols = array();
    $graphs = array();
    if ( $type == "poolstats" ) {
        $params = array( 'poolids' => $selected );
        $url = "EO/f5_pool_stats_";
        $cols = array('kbitsInPerSec', 'kbitsOutPerSec', 'connections', 'requests');
    } elseif ( $type == "nodestats" ) {
        $params = array( 'nodeids' => $selected );
        $url = "EO/f5_node_stats_";
        $cols = array('kbitsInPerSec', 'kbitsOutPerSec', 'connections', 'requests');
    } elseif ( $type == "virtualstats" ) {
        $params = array( 'virtualids' => $selected );
        $url = "EO/f5_virtual_stats_";
        $cols = array(
                      'clientsidekbitsout',
                      'clientsidekbitsin',
                      'clientsidetotconns',
                      'clientsideslowkilled',
                      'clientsideevictedconn',
                      'ephemeralkbitsout',
                      'ephemeralkbitsin',
                      'ephemeraltotconns',
                      'ephmeralslowkilled',
                      'ephmeralevictedconns',
                      'totrequests'
                  );
    } elseif ( $type == "cpustats" ) {
        $params = array( 'cpuids' => $selected );
        $url = "EO/f5_cpu_stats_";
        $cols = array('user', 'system', 'iowait');
    } elseif ( $type == "memorystats" ) {
        $params = array( 'memStats' => $selected );
        $url = "EO/f5_mem_stats_";
        $cols = array('memoryTotal', 'memoryUsed', 'tmmMemoryTotal', 'tmmMemoryUsed', 'swapTotal', 'swapUsed');
    } elseif ( $type == "nicstats" ) {
        $params = array( 'nicids' => $selected );
        $url = "EO/f5/f5_nic_stats_";
        $cols = array('kbitsInPerSec', 'kbitsOutPerSec', 'dropsAll', 'errorsAll');
    } elseif ( $type == "httpstats" ) {
        $params = array( 'httpstats' => $selected );
        $url = "EO/f5/http_stats_";
        $cols = array('numberReqs', 'postReqs', 'getReqs', 'resp_2xxCnt', 'resp_3xxCnt', 'resp_4xxCnt', 'resp_5xxCnt');
    } elseif ( $type == "tcpstats" ) {
        $params = array( 'tcpstats' => $selected );
        $url = "EO/f5/tcp_stats_";
        $cols = array('connects', 'connFails');
    } elseif ( $type == "ldstats" ) {
        $ldStatsGraphSet = new ModelledGraphSet('EO/f5/ld_stats');
        drawGraphGroup($ldStatsGraphSet->getGroup("ld"));
    }

    foreach ( $cols as $col ) {
        $modelledGraph = new ModelledGraph($url . $col);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs($graphs, 1);
}

function params() {
    return array(
        array('EO/f5_pool_stats', 'f5_pool_stats', 'F5 Pool Stats'),
        array('EO/f5_node_stats', 'f5_node_stats', 'F5 Node Stats'),
        array('EO/f5_virtual_server_stats', 'f5_virtual_server_stats', 'F5 Virtual Server Stats'),
        array('EO/f5_cpu_stats', 'f5_cpu_stats', 'F5 CPU Stats'),
        array('EO/f5_memory_stats', 'f5_memory_stats', 'F5 Memory Stats'),
        array('EO/f5_nic_stats', 'f5_nic_stats', 'F5 NIC Stats'),
        array('EO/f5_http_stats', 'f5_http_stats', 'F5 HTTP Stats'),
        array('EO/f5_tcp_stats', 'f5_tcp_stats', 'F5 TCP Stats'),
        array('EO/f5_ld_stats', 'f5_ld_stats', 'F5 LogicalDisk Stats')
    );
}

function main() {
    global $site, $date, $webargs;

    $f5URLs = array();
    $f5URLs[] = makeAnchorLink("f5_pool_stats", 'F5 Pool Stats');
    $f5URLs[] = makeAnchorLink("f5_node_stats", 'F5 Node Stats' );
    $f5URLs[] = makeAnchorLink("f5_virtual_server_stats", 'F5 Virtual Server Stats' );
    $f5URLs[] = makeAnchorLink("f5_cpu_stats", 'F5 CPU Stats');
    $f5URLs[] = makeAnchorLink("f5_memory_stats", 'F5 Memory Stats');
    $f5URLs[] = makeAnchorLink("f5_nic_stats", 'F5 NIC Stats');
    $f5URLs[] = makeAnchorLink("f5_http_stats", 'F5 HTTP Stats');
    $f5URLs[] = makeAnchorLink("f5_tcp_stats", 'F5 TCP Stats');
    $f5URLs[] = makeAnchorLink("f5_ld_stats", 'F5 LogicalDisk Stats');

    echo makeHTMLList($f5URLs);

    $params = params();
    foreach ($params as $param) {
        $table = new ModelledTable($param[0], $param[1],
            array(
              ModelledTable::URL => makeSelfLink(),
            )
        );
        echo $table->getTableWithHeader($param[2]);
    }
}

$selected = requestValue('selected');
$plottype = requestValue('plot');

if ( is_null($selected) ) {
    main();
} else {
    plotStats($selected, $plottype);
}
