<?php
$pageTitle = "Response Count";

include_once "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

function protocolResponsePerVM() {
    global $date;
    $sqlParamWriter = new SqlPlotParam();

    $instances = getInstances("netsim_response");

    $where = "netsim_response.siteid = sites.id AND
              sites.name = '%s' AND
              netsim_response.serverid = servers.id AND
              servers.hostname = '%s'";

    $whatCol = array ( 'ecim_avc' => 'ecim_avc',
                       'ecim_MOcreated' => 'ecim_MOcreated',
                       'ecim_MOdeleted' => 'ecim_MOdeleted',
                       'ecim_reply' => 'ecim_reply',
                       'cpp_avc' => 'cpp_avc',
                       'cpp_MOcreated' => 'cpp_MOcreated',
                       'cpp_MOdeleted' => 'cpp_MOdeleted',
                       'cpp_reply' => 'cpp_reply',
                       'sftp_FileClose' => 'sftp_FileClose',
                       'snmp_response' => 'snmp_response',
                       'snmp_traps' => 'snmp_traps'
               );

    $row = array();

    foreach ( $instances as $instance ) {
        $graphName = str_replace("ieatnetsimv", "v", $instance);
        $sqlParam =
            array( 'title' => "Response Counts: %s",
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
                               'whatcol' => $whatCol,
                               'tables' => "netsim_response, sites, servers",
                               'where' => $where,
                               'qargs' => array( 'site', 'inst' )
                           )
                       )
            );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $extraParams = "inst=$instance&title=$graphName";
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, $extraParams);
    }

    $size = sizeof($row);
    for ($x = 0; $x < $size; $x += 2) {
        echo $row[$x];
        if ( isset($row[$x+1]) ) {
            echo $row[$x+1];
        }
        echo addLineBreak();
    }
}

function mainFlow() {
    echo "<H1>Response Counts per VM</H1>";
    echo addLineBreak();
    protocolResponsePerVM();
}

mainFlow();

include_once "../common/finalise.php";

