<?php
$pageTitle = "ESXi server Details";

$YUI_DATATABLE = true;

include "common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";

function ESXiInstanceDiskAndNetgraph($statsDB,$esxihostname,$table)
{
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    if($table == "esxi_disk_obj_details" || $table == "esxi_net_obj_details")
    {
        if($table == "esxi_disk_obj_details")
        {
            $title = "Average Disk Usage";
            $ylabel = "KBps";
        }
        if($table == "esxi_net_obj_details")
        {
            $title = "Average Network Usage";
            $ylabel = "KBps";
        }
        $where = "$table.siteid = sites.id AND  sites.name = '%s' AND $table.serverid= esxi_servers.id AND esxi_servers.hostname='$esxihostname' AND $table.instance not like 'vmnic'";
        $mutliseries = "$table.hostname";
    }
 
    $row = array();
    $sqlParam =
            array( 'title' => $title,
            'ylabel' => $ylabel,
            'type' => 'tsc',
            'sb.barwidth' => '60',
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' =>
             array(
                   array(
                        'timecol' => "$table.date",
                        'multiseries'=> "$mutliseries",
                        'whatcol' => array ( "$table.metric_value"  => 'Metric_value'),
                        'tables' => "$table,sites,esxi_servers",
                        'where' => $where,
                        'qargs' => array( 'site' )
                        )
                   )
            );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);

    $graphTable->addRow($row);

    echo $graphTable->toHTML();

}

function ESXiInstanceMemAndCpugraph($statsDB,$esxihostname,$table)
{
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();

    if($table == "esxi_mem_obj_details") {
        $metricTitleMap = array( "mem.consumed.average" => "Average Memory Usage (Consumed)", "mem.llSwapUsed.average" => "Average Memory Usage (Swap)" );
        $mutliseries = "$table.hostname";
        foreach ( $metricTitleMap as $metric => $title) {
            $row = array();
            $sqlParam =
            array( 'title' => $title,
            'ylabel' => 'MB',
            'type' => 'tsc',
            'sb.barwidth' => '60',
            'useragg' => 'true',
            'persistent' => 'true',
            'forcelegend' => 'true',
            'querylist' =>
             array(
                   array(
                        'timecol' => "$table.date",
                        'multiseries'=> "$mutliseries",
                        'whatcol' => array ( "$table.metric_value/1024"  => 'Metric_value'),
                        'tables' => "$table,sites,esxi_servers",
                        'where' => "$table.siteid = sites.id AND sites.name = '%s' AND esxi_servers.hostname='$esxihostname' AND $table.serverid= esxi_servers.id and $table.metric='$metric'",
                        'qargs' => array( 'site' )
                        )
                   )
            );

            $id = $sqlParamWriter->saveParams($sqlParam);
            $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
            $graphTable->addRow($row);
        }
    }

    if($table == "esxi_cpu_obj_details")
    {
        $title = "Average CPU Usage";
        $where = "$table.siteid = sites.id AND  sites.name = '%s' AND esxi_servers.hostname='$esxihostname' AND $table.serverid= esxi_servers.id and $table.metric='cpu.usage.average' and esxi_cpu_obj_details.instance REGEXP '[a-z]'";
        $mutliseries = "$table.hostname";
        $ylabel = "%";
        $row = array();
        $sqlParam =
            array( 'title' => $title,
            'ylabel' => $ylabel,
            'type' => 'tsc',
            'sb.barwidth' => '60',
            'useragg' => 'true',
            'persistent' => 'true',
            'forcelegend' => 'true',
            'querylist' =>
             array(
                   array(
                        'timecol' => "$table.date",
                        'multiseries'=> "$mutliseries",
                        'whatcol' => array ( "$table.metric_value/100"  => 'Metric_value'),
                        'tables' => "$table,sites,esxi_servers",
                        'where' => $where,
                        'qargs' => array( 'site' )
                        )
                   )
            );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        $graphTable->addRow($row);
    }

    echo $graphTable->toHTML();

}

function mainFlow($statsDB)
{
    global $debug, $webargs, $php_webroot, $date, $site,$esxihostname;
    $esxihostname = $_GET['hostname'];
    $tables = array('esxi_cpu_obj_details','esxi_mem_obj_details','esxi_net_obj_details','esxi_disk_obj_details');
     ?>
    <h1>Server Stats - <?= $esxihostname; ?></h1>
    <ul>
    <li><a href="#cpu">CPU</a></li>
    <li><a href="#mem">Memory</a></li>
    <li><a href="#net">Network</a></li>
    <li><a href="#disk">Disk</a></li>
    </ul>
    <?php
    foreach ($tables as $table)
    {
        if($table == "esxi_disk_obj_details" || $table == "esxi_net_obj_details")
        {
            if($table == "esxi_net_obj_details")
            {
                echo "<H3 id=\"net\"></H3>\n";
                $cellmgmntHelp = "DDP_Bubble_375_ESXi_NETWORK";
                drawHeaderWithHelp("NETWORK",1, "esxinetworkHelp",$cellmgmntHelp);
            }
            if($table == "esxi_disk_obj_details")
            {
                echo "<H3 id=\"disk\"></H3>\n";
                $cellmgmntHelp = "DDP_Bubble_376_ESXi_DISK";
                drawHeaderWithHelp("DISK",1, "esxidiskHelp",$cellmgmntHelp);
            }
            ESXiInstanceDiskAndNetgraph($statsDB,$esxihostname,$table);
        }

        if($table == "esxi_mem_obj_details" || $table == "esxi_cpu_obj_details") {
            if($table == "esxi_mem_obj_details") {
                echo "<H3 id=\"mem\"></H3>\n";
                $cellmgmntHelp = "DDP_Bubble_377_ESXi_MEMORY";
                drawHeaderWithHelp("MEMORY",1, "esximemoryHelp",$cellmgmntHelp);
            }
            if($table == "esxi_cpu_obj_details") {
                echo "<H3 id=\"cpu\"></H3>\n";
                $cellmgmntHelp = "DDP_Bubble_378_ESXi_CPU";
                drawHeaderWithHelp("CPU",1, "esxicpuHelp",$cellmgmntHelp);
            }
            ESXiInstanceMemAndCpugraph($statsDB,$esxihostname,$table);
        }

        echo "<br/>";
    }
}

$statsDB = new StatsDB();
mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>
