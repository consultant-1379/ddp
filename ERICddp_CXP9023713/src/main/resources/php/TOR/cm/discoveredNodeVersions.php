<?php
$pageTitle = "Release Independence";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";




function mainFlow($statsDB) {
    global $date, $site;
    echo " <a href=\"#addsupport\">Add Support Node Versions</a>\n";

     $table =
         new SqlTable("DiscoveredNodeVersions",
                        array(
                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'time', 'db' => 'DATE_FORMAT(enm_cmconfig_services_logs.time,"%H:%i:%s")','label' => 'Time'),
                  array('key' => 'productVersion', 'db' => 'enm_cmconfig_services_logs.product_version', 'label' => 'Product Version'),
                  array('key' => 'netype', 'db' => 'enm_cmconfig_services_logs.netype','label' => 'NE Type'),
                  array('key' => 'duration', 'db' => 'enm_cmconfig_services_logs.duration','label' => 'Duration (MilliSeconds)', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'model_id', 'db' => 'enm_cmconfig_services_logs.model_id','label' => 'Model Identity'),
                  array('key' => 'model_status', 'db' => 'enm_cmconfig_services_logs.model_status', 'label' => 'Model Status'),
                  array('key' => 'model_size', 'db' => 'enm_cmconfig_services_logs.model_size','label' => 'Model Size', 'formatter' => 'ddpFormatNumber')
                ),
                array( 'enm_cmconfig_services_logs','sites', 'servers' ),
                "enm_cmconfig_services_logs.siteid = sites.id AND sites.name = '$site' AND
                 enm_cmconfig_services_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                 enm_cmconfig_services_logs.serverid = servers.id",
                TRUE,
                      array( 'order' => array( 'by' => 'time', 'dir' => 'ASC'),
                             'rowsPerPage' => 50,
                             'rowsPerPageOptions' => array(100,500,1000)
                      )
         );
 echo $table->getTableWithHeader("Discovered Node Versions", 2, "DDP_Bubble_328_Discovered_Node_Versions");
 $table =
         new SqlTable("AddSupportForNodeVersions",
                        array(
                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'time', 'db' => 'DATE_FORMAT(enm_cmconfig_support_logs.time,"%H:%i:%s")','label' => 'Time'),
                  array('key' => 'duration', 'db' => 'IFNULL(enm_cmconfig_support_logs.duration,"NA")', 'label' => 'Duration (Seconds)'),
                  array('key' => 'productVersion', 'db' => 'enm_cmconfig_support_logs.product_version', 'label' => 'Product Version'),
                  array('key' => 'netype', 'db' => 'enm_cmconfig_support_logs.netype','label' => 'NE Type'),
                  array('key' => 'result', 'db' => 'enm_cmconfig_support_logs.result','label' => 'Result'),
                  array('key' => 'numberOfNodes', 'db' => 'enm_cmconfig_support_logs.numberOfNodes','label' => 'Number of Nodes')
                ),
                array( 'enm_cmconfig_support_logs','sites', 'servers' ),
                "enm_cmconfig_support_logs.siteid = sites.id AND sites.name = '$site' AND
                 enm_cmconfig_support_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                 enm_cmconfig_support_logs.serverid = servers.id AND
                 enm_cmconfig_support_logs.activity = 'ADD_SUPPORT'",
                TRUE,
                      array( 'order' => array( 'by' => 'time', 'dir' => 'ASC'),
                             'rowsPerPage' => 50,
                             'rowsPerPageOptions' => array(100,500,1000)
                      )
         );
    echo "<H1 id=\"addsupport\"></H1>\n";
    echo $table->getTableWithHeader("Add Support for Node Versions", 2, "DDP_Bubble_329_AddSupport_Node_Versions");
 $table =
         new SqlTable("RemoveSupportForNodeVersions",
                        array(
                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'time', 'db' => 'DATE_FORMAT(enm_cmconfig_support_logs.time,"%H:%i:%s")','label' => 'Time'),
                  array('key' => 'productVersion', 'db' => 'enm_cmconfig_support_logs.product_version', 'label' => 'Product Version'),
                  array('key' => 'netype', 'db' => 'enm_cmconfig_support_logs.netype','label' => 'NE Type'),
                  array('key' => 'result', 'db' => 'enm_cmconfig_support_logs.modelIdentity','label' => 'Model Identity'),
                  array('key' => 'numberOfNodes', 'db' => 'enm_cmconfig_support_logs.numberOfNodes','label' => 'Number of Nodes')
                ),
                array( 'enm_cmconfig_support_logs','sites', 'servers' ),
                "enm_cmconfig_support_logs.siteid = sites.id AND sites.name = '$site' AND
                 enm_cmconfig_support_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                 enm_cmconfig_support_logs.serverid = servers.id AND
                 enm_cmconfig_support_logs.activity = 'REMOVE_SUPPORT'",
                TRUE,
                      array( 'order' => array( 'by' => 'time', 'dir' => 'ASC'),
                             'rowsPerPage' => 50,
                             'rowsPerPageOptions' => array(100,500,1000)
                      )
         );
    echo "<H1 id=\"removesupport\"></H1>\n";
    echo $table->getTableWithHeader("Remove Support for Node Versions", 2, "DDP_Bubble_341_RemoveSupport_Node_Versions");
}

$statsDB = new StatsDB();
mainFlow($statsDB);

include PHP_ROOT . "/common/finalise.php";
?>
