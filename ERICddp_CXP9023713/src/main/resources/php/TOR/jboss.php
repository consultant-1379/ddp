<?php

$serviceGroup=$_REQUEST['servicegroup'];

$pageTitle = "JBoss for $serviceGroup";

include "../common/init.php";

require_once PHP_ROOT . "/classes/ServiceJvmStats.php";
require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";


const QUEUESIZE = 'queueSize';
const REJECTEDCOUNT = 'rejectedCount';
const SERVERS_HOSTNAME = 'servers.hostname';

function showGraphs($statsDB, $srvArr, $serviceGroup, $key,$nCpu) {
    global $date,$site,$debug;

    $jvmStats = new ServiceJvmStats($statsDB, $site, $srvArr, $serviceGroup, $date, $date, $nCpu,640,240);

    $graphTable = new HTML_Table('border=0');
    foreach ( $jvmStats->getPerServiceGraphs($key) as  $graphURL ) {
        $graphTable->addRow(array($graphURL));
    }
    echo $graphTable->toHTML();
}

function checkCustomTPNames($statsDB, $srvIdStr) {
    global $date,$site;

    $customTpNames = array();
    $statsDB->query("
SELECT distinct(enm_sg_specific_threadpool_names.name)
FROM
enm_sg_specific_threadpool_names , enm_sg_specific_threadpool,sites
WHERE
 enm_sg_specific_threadpool.siteid = sites.id AND sites.name = '$site' AND
 enm_sg_specific_threadpool.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_sg_specific_threadpool.serverid IN  (" . $srvIdStr . ") AND
 enm_sg_specific_threadpool.threadpoolid = enm_sg_specific_threadpool_names.id");

    if ( $statsDB->getNumRows() > 0 ) {
        while ( $row = $statsDB->getNextRow()) {
            $customTpNames[] = $row[0];
        }
        showSgGraphs($srvIdStr, $customTpNames);
    }
}

function showSgGraphs($srvIdStr, $customTpNames) {
    global $date,$site;

    foreach (  $customTpNames as $customTpName ) {
        drawHeaderWithHelp($customTpName, 2, $customTpName);
        $row = array();
        $params = array( 'tp' => $customTpName, 'srvids' => $srvIdStr);
        foreach ( array('completedTaskCount', 'activeCount', QUEUESIZE, REJECTEDCOUNT) as $column ) {
            $modelledGraph = new ModelledGraph('TOR/common/jboss_threadpool_' . $column);
            $row[] = $modelledGraph->getImage($params);
        }
        $graphTable = new HTML_Table('border=0');
        $graphTable->addRow($row);
        echo $graphTable->toHTML();
    }
}

function shownonStandardTp($srvIdStr) {
    global $date, $site, $statsDB;

    $statsDB->query("
SELECT distinct(enm_sg_specific_threadpool_names.name)
FROM
 enm_sg_specific_threadpool_names, enm_jboss_threadpools_nonstandard, sites
WHERE
 enm_jboss_threadpools_nonstandard.siteid = sites.id AND sites.name = '$site' AND
 enm_jboss_threadpools_nonstandard.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_jboss_threadpools_nonstandard.serverid IN  (" . $srvIdStr . ") AND
 enm_jboss_threadpools_nonstandard.threadpoolid = enm_sg_specific_threadpool_names.id");

    if ( $statsDB->getNumRows() > 0 ) {
        while ( $row = $statsDB->getNextRow()) {
            $customTpNames[] = $row[0];
        }
    }

    $row = $statsDB->queryRow("
SELECT COUNT(*) FROM enm_jboss_threadpools_nonstandard, sites, enm_sg_specific_threadpool_names
WHERE
 enm_jboss_threadpools_nonstandard.siteid = sites.id AND sites.name = '$site' AND
 enm_jboss_threadpools_nonstandard.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_jboss_threadpools_nonstandard.threadpoolid = enm_sg_specific_threadpool_names.id AND
 enm_jboss_threadpools_nonstandard.serverid IN ( $srvIdStr )");

    if ( $row[0] > 0 ) {
        foreach ( $customTpNames as $tp ) {
            drawHeader("io-" . $tp, 3, $tp);
            $graphs = array();
            $graphParams = array('tp' => $tp, 'srvids' => $srvIdStr);
            getGraphsFromSet('threadpools', $graphs, 'TOR/system/enm_jboss_nonstandard', $graphParams);
            plotgraphs( $graphs );
        }
    }

}
function mainFlow($statsDB, $srvArr, $serviceGroup, $nCpu) {
    global $site,$date,$debug;

    if ( $debug ) { echo "<pre>serverNameArray: "; print_r($srvArr); echo "</pre>\n"; }

    $graphTable = new HTML_Table('border=0');
    $linkBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&show=";
    $jvmStats = new ServiceJvmStats($statsDB, $site, $srvArr, $serviceGroup, $date, $date,$nCpu);
    foreach ( $jvmStats->getGraphArray() as $key => $graphURL ) {
        $cellContent = '<a href="' . $linkBase . $key . '">' . $graphURL . "</a>\n";
        $graphTable->addRow(array($cellContent));
    }

    drawHeaderWithHelp("JMX Data for $serviceGroup", 2, "jmxHelp", "DDP_Bubble_194_Generic_JMX_Help");
    echo $graphTable->toHTML();

    $height=240;
    $width=640;
    if ( count($srvArr) > 4 ) {
        $height = 200;
        $width = 280;
    }

    $srvIdStr = implode(",",array_values($srvArr));
    $row = $statsDB->queryRow("
SELECT COUNT(*) FROM enm_jboss_threadpools, sites
WHERE
 enm_jboss_threadpools.siteid = sites.id AND sites.name = '$site' AND
 enm_jboss_threadpools.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_jboss_threadpools.serverid IN ( $srvIdStr )");
    if ( $row[0] > 0 ) {
        drawHeaderWithHelp("Threadpool stats",2,"threadpoolHelp","DDP_Bubble_278_ENM_Jboss_Threadpools_Help");
        $tpParams = array();
        foreach ( array('default','async') as $tpName ) {
            $tpParams[$tpName] = array('completedTaskCount','activeCount', QUEUESIZE, REJECTEDCOUNT);
        }
        foreach ( array('workmanager_short','workmanager_long', 'http_executor','ajp_executor','job_executor_tp') as $tpName ) {
            $tpParams[$tpName] = array(QUEUESIZE, REJECTEDCOUNT,'currentThreadCount');
        }
        if ( $debug ) { echo "<pre>tpParams:"; print_r($tpParams); echo "</pre>\n"; }

        $sqlParamWriter = new SqlPlotParam();

        foreach ( $tpParams as $tpName => $columns ) {
            if ( $debug ) { echo "<pre>columns:"; print_r($columns); echo "</pre>\n"; }

            # Don't show idle pools
            $colNames = array();
            foreach ( $columns as $column ) {
                $colNames[] = $tpName . "_" . $column;
            }
            $row = $statsDB->queryRow("
SELECT SUM(" . implode("+",$colNames) . ")
FROM enm_jboss_threadpools, sites, servers
WHERE
 enm_jboss_threadpools.siteid = sites.id AND sites.name = '$site' AND
 enm_jboss_threadpools.serverid = servers.id AND servers.id IN (" . $srvIdStr . ") AND
 enm_jboss_threadpools.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
            $isIdle = $row[0] == 0;
            if ( $isIdle ) {
                continue;
            }

            $height=240;
            $width=640;
            if ( count($columns) > 2 ) {
                $height = 320;
                $width = 320;
            }

            $row = array();
            foreach ( $columns as $column ) {
                $sqlParam = array(
                    'title' => $column,
                    'type' => 'sb',
                    'sb.barwidth' => 300,
                    'ylabel'     => "",
                    'useragg'    => 'true',
                    'persistent' => 'true',
                    'querylist' => array(
                        array(
                            'timecol' => 'time',
                            'whatcol' => array( $tpName . "_" . $column => $column ),
                            'tables'  => "enm_jboss_threadpools, sites, servers",
                            "multiseries"=> SERVERS_HOSTNAME,
                            'where'   => "enm_jboss_threadpools.siteid = sites.id AND sites.name = '%s' AND enm_jboss_threadpools.serverid = servers.id AND servers.id IN ( %s )",
                            'qargs'   => array('site','serverids')
                        )
                    )
                );
                $id = $sqlParamWriter->saveParams($sqlParam);
                $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, $width, $height, "serverids=" . $srvIdStr);
            }
            $graphTable = new HTML_Table('border=0');
            $graphTable->addRow($row);

            echo "<H3>$tpName</H3>\n";
            echo $graphTable->toHTML();
        }
    }

    checkcustomTPNames($statsDB, $srvIdStr);

    # Display "Versant Client Connection Pool Stats" graphs
    $connectionPoolColNames = array('size', 'connectionsInUse', 'allocationFailures', 'connectionFailures', 'allocationTimeouts');

    $row = $statsDB->queryRow("
SELECT
    SUM(" . implode("+", $connectionPoolColNames) . ")
FROM
    enm_versant_client_connpool, sites
WHERE
    enm_versant_client_connpool.siteid = sites.id AND sites.name = '$site' AND
    enm_versant_client_connpool.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_versant_client_connpool.serverid IN ( $srvIdStr )");

    if ( $row[0] > 0 ) {
        drawHeaderWithHelp("Versant Client Connection Pool Stats", 2, "versantClientConnpoolHelp", "DDP_Bubble_362_ENM_Jboss_Versant_Client_Connection_Pool_Help");
        $row1 = array();
        $row2 = array();
        $row3 = array();
        foreach ($connectionPoolColNames as $column) {
            $sqlParam = array(
                'title' => $column,
                'type' => 'sb',
                'ylabel'     => "",
                'useragg'    => 'true',
                'persistent' => 'true',
                'forcelegend'=> 'true',
                'querylist' => array(
                    array(
                        'timecol' => 'time',
                        'whatcol' => array($column => $column),
                        'tables'  => "enm_versant_client_connpool, sites, servers",
                        'multiseries' => SERVERS_HOSTNAME,
                        'where'   => "enm_versant_client_connpool.siteid = sites.id AND sites.name = '%s' AND enm_versant_client_connpool.serverid = servers.id AND servers.id IN ( %s )",
                        'qargs'   => array('site', 'serverids')
                    )
                )
            );

            $sqlParamWriter = new SqlPlotParam();
            $id = $sqlParamWriter->saveParams($sqlParam);
            if ( $column == "size" || $column == "connectionsInUse" ) {
                $row1[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320, "serverids=" . $srvIdStr);
            }
            else if ( $column == "allocationFailures" || $column == "connectionFailures" ) {
                $row2[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320, "serverids=" . $srvIdStr);
            }
            else {
                $row3[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320, "serverids=" . $srvIdStr);
            }
        }
        $graphTable = new HTML_Table('border=0');
        $graphTable->addRow($row1);
        $graphTable->addRow($row2);
        $graphTable->addRow($row3);
        echo $graphTable->toHTML();
    }

    shownonStandardTp($srvIdStr);
}

$statsDB = new StatsDB();

$srvArr = enmGetServiceInstances($statsDB,$site,$date,$serviceGroup);

$row = $statsDB->queryRow("
SELECT
 SUM( servercpu.num * IFNULL(cputypes.cores, 1) * IFNULL(cputypes.threadsPerCore, 1) ) AS nCpu,
 servercfg.serverid AS serverid
FROM cputypes, servercpu, servercfg
WHERE
 servercfg.date = '$date' AND
 servercfg.serverid IN (" . implode(",",array_values($srvArr)) . ") AND
 servercpu.cfgid = servercfg.cfgid AND
 servercpu.typeid = cputypes.id
GROUP BY
 servercfg.serverid
LIMIT 1");
$nCpu = $row[0];

$serverNameArray = array();
$isSortable = true;
foreach ( array_keys($srvArr) as $srv ) {
    $srvParts = explode("-",$srv);
    if ( $debug ) { echo "<pre>srv=$srv srvParts"; print_r($srvParts); echo "</pre>\n"; }
    if ( $debug ) { echo "<pre>count=" . count($srvParts) . ", is_int=" . is_numeric($srvParts[1]) . "</pre>\n"; }
    if ( count($srvParts) == 3 && is_numeric($srvParts[1]) ) {
        if ( $debug ) { echo "<pre>adding $srvParts[1]=>$srv</pre>\n"; }
        $serverNameArray[$srvParts[1]] = $srv;
    } else {
        $serverNameArray[] = $srv;
        $isSortable = false;
    }
}
if ( $debug ) { echo "<pre>isSortable=$isSortable serverNameArray"; print_r($serverNameArray); echo "</pre>\n"; }
if ( $isSortable ) {
    ksort($serverNameArray);
    $sortedSrvArr = array();
    foreach ( $serverNameArray as $serverName ) {
        $sortedSrvArr[$serverName] = $srvArr[$serverName];
    }
    $srvArr = $sortedSrvArr;
}

if ( array_key_exists("show",$_REQUEST) ) {
    showGraphs($statsDB,$srvArr,$serviceGroup,$_REQUEST["show"],$nCpu);
} else {
    mainFlow($statsDB,$srvArr,$serviceGroup,$nCpu);
}

include PHP_ROOT . "/common/finalise.php";
?>

