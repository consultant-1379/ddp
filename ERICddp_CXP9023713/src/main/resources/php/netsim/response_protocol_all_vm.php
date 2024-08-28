<?php
$pageTitle = "Response Protocol Count All";

$YUI_DATATABLE = true;

include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

function protocolResponseAllVM() {
  global $debug, $webargs, $php_webroot, $date, $site;

  echo "<H1>Response Protocol Counts on All VM's</H1>";
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
        'CORBA' => array(
            'title' => 'CORBA',
            'cols' => array(
                'CORBA' => 'CORBA'
            )
                ),
        'SNMP' => array(
            'title' => 'SNMP',
            'cols' => array(
                'SNMP' => 'SNMP'
            )
               ),
        'SSH' => array(
            'title' => 'SSH',
            'cols' => array(
                'SSH' => 'SSH'
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
                    'tables' => "netsim_response, sites, servers",
                    "multiseries"=> 'servers.hostname',
                    'where' => "netsim_response.siteid = sites.id AND sites.name = '%s' AND netsim_response.serverid = servers.id",
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
    protocolResponseAllVM();
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
