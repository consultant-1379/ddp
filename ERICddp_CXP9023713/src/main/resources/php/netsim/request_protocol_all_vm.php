<?php
$pageTitle = "Request Protocol Count All";

$YUI_DATATABLE = true;

include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

function protocolRequestAllVM() {
  global $debug, $webargs, $php_webroot, $date, $site;

  echo "<H1>Request Protocol Counts on All VM's</H1>";
  echo "</BR>";

  $sqlParamWriter = new SqlPlotParam();

  $graphTable = new HTML_Table("border=0");
    $instrGraphParams = array(
        'NETCONF' => array(
            'title' => 'NETCONF',
            'cols' => array(
                'NETCONF' => 'NETCONF'
            )
        ),
        'CPP' => array(
            'title' => 'CPP',
            'cols' => array(
                'CPP' => 'CPP'
            )
                ),
        'SNMP' => array(
            'title' => 'SNMP',
            'cols' => array(
                'SNMP' => 'SNMP'
            )
               ),
        'SIMCMD' => array(
            'title' => 'SIMCMD',
            'cols' => array(
                'SIMCMD' => 'SIMCMD'
            )
               ),
        'SFTP' => array(
            'title' => 'SFTP',
            'cols' => array(
                'SFTP' => 'SFTP'
            )
        )
    );

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = array(
            'title' => $instrGraphParam['title'],
            'type' => 'tsc',
            'ylabel' => "",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => $instrGraphParam['cols'],
                    'tables' => "netsim_requests, sites, servers",
                    "multiseries"=> 'servers.hostname',
                    'where' => "netsim_requests.siteid = sites.id AND sites.name = '%s' AND netsim_requests.serverid = servers.id",
                    'qargs' => array('site')
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320)));
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site;
    protocolRequestAllVM();
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
