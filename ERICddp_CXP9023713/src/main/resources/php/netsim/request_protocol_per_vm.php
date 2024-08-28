<?php
$pageTitle = "Request Protocol Count Per VM";

$YUI_DATATABLE = true;

include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

function protocolRequestPerVM() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $instances = getInstances("netsim_requests");
    foreach ( $instances as $instance ) {
        $header[] = $instance;
    }
    $row = array();
    $where = "netsim_requests.siteid = sites.id
        AND sites.name = '%s'
        AND netsim_requests.serverid = servers.id
        AND servers.hostname = '%s'";
    foreach ( $instances as $instance ) {
        $graphName = str_replace("ieatnetsimv", "v", $instance);
        $sqlParam =
            array( 'title' => "Protocol Counts: %s",
                'targs' => array('title'),
                'ylabel' => '',
                'type' => 'tsc',
                'useragg' => 'true',
                'sb.barwidth' => '100',
                'persistent' => 'true',
                'querylist' =>
                array(
                    array(
                        'timecol' => 'time',
                        'whatcol' => array(
                            'NETCONF' => 'NETCONF',
                            'CPP' => 'CPP',
                            'SNMP' => 'SNMP',
                            'SIMCMD' => 'SIMCMD',
                            'SFTP' => 'SFTP'
                        ),
                        'tables' => "netsim_requests, sites, servers",
                        'where' => $where,
                        'qargs' => array( 'site', 'inst' )
                    )
                )
            );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);

        $extraArgs = "inst=$instance&title=$graphName";
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, $extraArgs);
    }
    $size = sizeof($row);
    for ($x = 0; $x <= $size; $x += 2) {
        echo $row[$x];
        if ( isset($row[$x+1]) ) {
            echo $row[$x+1];
        }
        echo addLineBreak();
    }
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site;
    echo "<H1>Request Protocol Counts per VM</H1>";
    echo "</BR>";
    protocolRequestPerVM();
}

mainFlow();

include "../common/finalise.php";
?>
