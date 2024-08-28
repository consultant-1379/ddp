<?php
$pageTitle = "Snmp PM instrumentation";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

function getSnmpTotals()
{
    global $site,$date,$webargs;

    $cols = array(

                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'maximumDuration', 'db' => 'MAX(enm_mspmip_instr.snmpGetDurationTime)','label' => 'Snmp Get Maximum Duration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minimumDuration', 'db' => 'MIN(enm_mspmip_instr.snmpGetDurationTime)','label' => 'Snmp Get Minimum Duration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'averageDuration', 'db' => 'AVG(enm_mspmip_instr.snmpGetDurationTime)','label' => 'Snmp Get Average Duration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'maxSizeResponse', 'db' => 'MAX(enm_mspmip_instr.snmpGetSizeResponseMessage)', 'label' => 'Snmp Get Maximum Size Response', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minSizeResponse', 'db' => 'MIN(enm_mspmip_instr.snmpGetSizeResponseMessage)', 'label' => 'Snmp Get Minimum Size Response', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'avgSizeResponse', 'db' => 'AVG(enm_mspmip_instr.snmpGetSizeResponseMessage)', 'label' => 'Snmp Get Average Size Response', 'formatter' => 'ddpFormatNumber')
      );

      $where = "
enm_mspmip_instr.siteid = sites.id AND sites.name = '$site' AND
enm_mspmip_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_mspmip_instr.serverid = servers.id
GROUP BY servers.hostname WITH ROLLUP";

      $snmpTotals = new sqlTable("SnmpTotals",
                                        $cols,
                                        array('enm_mspmip_instr', 'sites', 'servers'),
                                        $where,
                                        TRUE
                                        );
      return $snmpTotals;
}

function getFileGeneratedTotals ()
{
    global $site,$date,$webargs;

    $cols = array(

                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'maximumTimeTaken', 'db' => 'MAX(enm_mspmip_instr.snmpGetDurationTime)','label' => 'Maximum Time Taken', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minimumTimeTaken', 'db' => 'MIN(enm_mspmip_instr.snmpGetDurationTime)','label' => 'Minimum Time Taken', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'averageTimeTaken', 'db' => 'AVG(enm_mspmip_instr.snmpGetDurationTime)','label' => 'Average Time Taken', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'maxSizeofPmFile', 'db' => 'MAX(enm_mspmip_instr.fileGeneratedTime)', 'label' => 'Maximum Size Of PM File', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'minSizeofPmFile', 'db' => 'MIN(enm_mspmip_instr.fileGeneratedTime)', 'label' => 'Minimum Size Of PM File', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'avgSizeofPmFile', 'db' => 'AVG(enm_mspmip_instr.fileGeneratedTime)', 'label' => 'Average Size Of PM File', 'formatter' => 'ddpFormatNumber')
      );

      $where = "
enm_mspmip_instr.siteid = sites.id AND sites.name = '$site' AND
enm_mspmip_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_mspmip_instr.serverid = servers.id
GROUP BY servers.hostname WITH ROLLUP";

      $fileGeneratedTotals = new sqlTable("FileGeneratedTotals",
                                        $cols,
                                        array('enm_mspmip_instr', 'sites', 'servers'),
                                        $where,
                                        TRUE
                                        );
      return $fileGeneratedTotals;
}

function showSnmpGetNumberOperationData()
{
    global $date, $site;

    $graphTable = new HTML_Table("border=0");

    $sqlParamWriter = new SqlPlotParam();

    $row = array();
    $sqlParam = array(
                'title' => 'snmpGetNumberOperation',
                'ylabel' => 'Unit',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => 'tsc',
                'forcelegend' => 'true',
                'querylist' => array(
                      array (
                        'timecol' => 'time',
                        'whatcol' => array('snmpGetNumberOperation'=>'NumberOperation'),
                        'multiseries' => 'servers.hostname',
                        'tables' => "enm_mspmip_instr, sites, servers",
                        'where' => "enm_mspmip_instr.siteid = sites.id AND sites.name = '%s' AND enm_mspmip_instr.serverid = servers.id",
                'qargs' => array( 'site' )
                )
            )
         );

     $id = $sqlParamWriter->saveParams($sqlParam);
     $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
     $graphTable->addRow($row);
     echo $graphTable->toHTML();
}

function showFileGeneratedNumberData()
{
   global $date, $site;

   $graphTable = new HTML_Table("border=0");

   $sqlParamWriter = new SqlPlotParam();
   $row = array();
   $sqlParam = array(
                'title' => 'fileGeneratedNumber',
                'ylabel' => 'Unit',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => 'tsc',
                'forcelegend' => 'true',
                'querylist' => array(
                      array (
                        'timecol' => 'time',
                        'whatcol' => array('fileGeneratedNumber'=>'FileGeneratedNumber'),
                        'multiseries' => 'servers.hostname',
                        'tables' => "enm_mspmip_instr, sites, servers",
                        'where' => "enm_mspmip_instr.siteid = sites.id AND sites.name = '%s' AND enm_mspmip_instr.serverid = servers.id",
                'qargs' => array( 'site' )
                )
            )
         );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showPmPolicyGraphsOne()
{
   global $date, $site;

   drawHeaderWithHelp ("Distribution Cache instrumentation",2,"instrCacheHelp","DDP_Bubble_372_ENM_PM_Distribution_Cache_Instr");
   $graphTable = new HTML_Table("border=0");

   $sqlParamWriter = new SqlPlotParam();
   $row = array();
   $sqlParam = array(
                'title' => 'numberOfManagedNodesInStickyCache',
                'ylabel' => 'Unit',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => 'tsc',
                'forcelegend' => 'true',
                'querylist' => array(
                      array (
                        'timecol' => 'time',
                        'whatcol' => array('numberOfManagedNodesInStickyCache'=>'NumberOfNodes'),
                        'multiseries' => 'servers.hostname',
                        'tables' => "enm_pmpolicy_instr, sites, servers",
                        'where' => "enm_pmpolicy_instr.siteid = sites.id AND sites.name = '%s' AND enm_pmpolicy_instr.serverid = servers.id",
                        'qargs' => array( 'site' )
                )
            )
         );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showPmPolicyGraphsTwo()
{
   global $date, $site;

   drawHeaderWithHelp ("Active Ms Instance Instrumentation",2,"msInstanceInstrHelp","DDP_Bubble_374_ENM_PM_Active_Ms_Instance_Instr");
   $graphTable = new HTML_Table("border=0");

   $sqlParamWriter = new SqlPlotParam();
   $row = array();
   $sqlParam = array(
                'title' => 'activePmRouterPolicy',
                'ylabel' => 'Unit',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => 'tsc',
                'forcelegend' => 'true',
                'querylist' => array(
                      array (
                        'timecol' => 'time',
                        'whatcol' => array('activePmRouterPolicy'=>'activePmRouterPolicy'),
                        'multiseries' => 'servers.hostname',
                        'tables' => "enm_pmpolicy_instr, sites, servers",
                        'where' => "enm_pmpolicy_instr.siteid = sites.id AND sites.name = '%s' AND enm_pmpolicy_instr.serverid = servers.id",
                        'qargs' => array( 'site' )
                )
            )
         );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;

    $link = makeLink('/TOR/pm/pm_push_audit.php', 'Return to PM Mediation Link');
echo "$link\n";

    $row=$statsDB->queryRow("
SELECT COUNT(*)
FROM enm_mspmip_instr, sites
WHERE
enm_mspmip_instr.siteid = sites.id AND sites.name = '$site' AND
enm_mspmip_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ( $row[0] > 0 ) {

            $snmpTotals = getSnmpTotals();
            echo $snmpTotals->getTableWithHeader("Snmp File Collection Instrumentation", 2, "DDP_Bubble_370_ENM_PM_Snmp_Totals");
            echo "<br>";
            showSnmpGetNumberOperationData();

            $fileGeneratedTotals = getFileGeneratedTotals();
            echo $fileGeneratedTotals->getTableWithHeader("File Generated Totals",2,"DDP_Bubble_371_ENM_PM_File_Generated_Totals");
            echo "<br>";
            showFileGeneratedNumberData();
    }

    $row=$statsDB->queryRow("
SELECT COUNT(*)
FROM enm_pmpolicy_instr, sites
WHERE
enm_pmpolicy_instr.siteid = sites.id AND sites.name = '$site' AND
enm_pmpolicy_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    if ( $row[0] > 0 ) {
            showPmPolicyGraphsOne();
            showPmPolicyGraphsTwo();
    }

}

$statsDB = new StatsDB();
mainFlow($statsDB);

include PHP_ROOT . "/common/finalise.php";
?>
