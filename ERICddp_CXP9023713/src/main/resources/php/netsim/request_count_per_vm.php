<?php
$pageTitle = "Request Count";

include_once "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

function protocolRequestPerVM() {
    global $date;
    $sqlParamWriter = new SqlPlotParam();

    $instances = getInstances("netsim_requests");

    $where = "netsim_requests.siteid = sites.id AND
              sites.name = '%s' AND
              netsim_requests.serverid = servers.id AND
              servers.hostname = '%s'";

    $whatCol = array ( 'ecim_get' => 'ecim_get',
                       'ecim_edit' => 'ecim_edit',
                       'ecim_MOaction' => 'ecim_MOaction',
                       'cpp_createMO' => 'cpp_createMO',
                       'cpp_deleteMO' => 'cpp_deleteMO',
                       'cpp_setAttr' => 'cpp_setAttr',
                       'cpp_getMIB' => 'cpp_getMIB',
                       'cpp_nextMOinfo' => 'cpp_nextMOinfo',
                       'cpp_get' => 'cpp_get',
                       'cpp_MOaction' => 'cpp_MOaction',
                       'snmp_get' => 'snmp_get',
                       'snmp_bulk_get' => 'snmp_bulk_get',
                       'snmp_get_next' => 'snmp_get_next',
                       'snmp_set' => 'snmp_set',
                       'MCDbursts' => 'MCDbursts',
                       'AlarmBursts' => 'AlarmBursts',
                       'sftp_FileOpen' => 'sftp_FileOpen',
                       'sftp_get_cwd' => 'sftp_get_cwd'
                   );

    $row = array();

    foreach ( $instances as $instance ) {
        $graphName = str_replace("ieatnetsimv", "v", $instance);
        $sqlParam =
            array( 'title' => "Request Counts: %s",
                   'targs' => array('title'),
                   'ylabel' => '',
                   'type' => 'tsc',
                   'useragg' => 'true',
                   'sb.barwidth' => '100',
                   'persistent' => 'true',
                   'querylist' =>
                       array(
                           array( 'timecol' => 'time',
                                  'whatcol' => $whatCol,
                                  'tables' => "netsim_requests, sites, servers",
                                  'where' => $where,
                                  'qargs' => array( 'site', 'inst' )
                           )
                       )
            );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);

        $extraArgs = "inst=$instance&title=$graphName";

        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, $extraArgs);
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
    echo "<H1>Request Counts per VM</H1>";
    echo addLineBreak();
    protocolRequestPerVM();
}

mainFlow();

include_once "../common/finalise.php";

