<?php
$pageTitle = "Workflow Log Analysis";

$YUI_DATATABLE = true;

include_once "../../common/init.php";
include_once "../../common/ha_log_analysis_functions.php";

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/SqlTable.php";


function mainFlow() {
    global $site,$date,$webargs;

    drawHeaderWithHelp("Workflow Executions",1,"workflows");

    $where = "
enm_vnflaf_wfexec.siteid = sites.id AND sites.name = '$site' AND
(
 enm_vnflaf_wfexec.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'
 OR
 (
   enm_vnflaf_wfexec.start BETWEEN '$date' - INTERVAL 1 DAY AND '$date' AND
   enm_vnflaf_wfexec.end BETWEEN '$date 00:00:00' AND '$date 23:59:59'
 )
) AND
enm_vnflaf_wfexec.nameid = enm_vnflaf_wfnames.id";

    $table =
           new SqlTable("execs",
                        array(
                            array( 'key' => 'id', 'db' => 'CONCAT(enm_vnflaf_wfexec.instanceId,"@",enm_vnflaf_wfexec.start)', 'visible' => false ),
                            array( 'key' => 'start', 'db' => 'enm_vnflaf_wfexec.start', 'label' => 'Start', 'formatter' => 'ddpFormatTime' ),
                            array( 'key' => 'end', 'db' => 'enm_vnflaf_wfexec.end', 'label' => 'End', 'formatter' => 'ddpFormatTime' ),
                            array( 'key' => 'duration', 'db' => 'TIMEDIFF(enm_vnflaf_wfexec.end,enm_vnflaf_wfexec.start)', 'label' => 'Duration' ),
                            array( 'key' => 'name', 'db' => 'enm_vnflaf_wfnames.name', 'label' => 'Workflow' )
                        ),
                        array( 'enm_vnflaf_wfexec', 'enm_vnflaf_wfnames', 'sites' ),
                        $where,
                        TRUE,
                        array('ctxMenu' => array('key' => 'action',
                                                 'multi' => false,
                                                 'menu' => array( 'showlog' => 'Show Log'),
                                                 'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                                 'col' => 'id'),
                              'rowsPerPage' => 50,
                              'rowsPerPageOptions' => array(1000)
                        )
           );
    echo $table->getTable();
}

if ( isset($_REQUEST['action']) ) {
    if ( $_REQUEST['action'] === 'showlog' ) {
        showWfLog($_REQUEST['selected']);
    }
} else {
    mainFlow($site,$date);
}

include PHP_ROOT . "/common/finalise.php";
?>
