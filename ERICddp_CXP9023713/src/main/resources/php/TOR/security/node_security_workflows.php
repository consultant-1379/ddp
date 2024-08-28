<?php
$pageTitle = "Node Security Workflow Counters";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/common/tableFunctions.php";

const BORDER_0 = "border=0";
const SECSERV_INSTR_TABLE = "enm_secserv_instr";
const WORKFLOWS = "Workflows";

function workflows() {
    return array('SSHKeyGeneration',
                 'CppSL2Activate',
                 'CppSL2Deactivate',
                 'CppIpSecActivate',
                 'CppIpSecDeactivate',
                 'CppCertificateEnrollment',
                 'EcimCertificateEnrollment',
                 'CppTrustDistribute',
                 'EcimTrustDistribute',
                 'CppTrustRemove',
                 'EcimTrustRemove',
                 'EcimLdapConfigure',
                 'CppCRLCheck',
                 'EcimCRLCheck',
                 'CppOnDemandCRLDownload',
                 'EcimOnDemandCRLDownload',
                 'SetCiphers',
                 'CppRTSELActivate',
                 'CppRTSELDeactivate',
                 'CppRTSELDelete',
                 'CppHTTPSActivate',
                 'CppHTTPSDeactivate',
                 'CppHTTPSGet',
                 'EcimFTPESActivate',
                 'EcimFTPESDeactivate',
                 'CppLaadDistribute');
}

function main() {
    $aggregation = array('SUM');
    $workflows = workflows();
    $columnNames = array();
    foreach ( $workflows as $workflow ) {
        $params = array(
                    "numOfSuccessful".$workflow.WORKFLOWS,
                    "numOfFailed".$workflow.WORKFLOWS,
                    "numOfErrored".$workflow.WORKFLOWS,
                    "numOfTimedOut".$workflow.WORKFLOWS
        );
        foreach ( $params as $col ) {
            $columnNames[] = $col;
        }
    }
    $sqlData = getAggTableData( SECSERV_INSTR_TABLE, $aggregation, $columnNames);
    $structuredData = reStructureAggTableData($sqlData, $aggregation, $columnNames);
    drawHeaderWithHelp("Node Security Workflow Counters", 1, "Workflow_Help");
    drawAggTable( $structuredData, $aggregation, "Main_Table", '' );
}

function plotGraph( $graphs ) {
    $graphTable = new HTML_Table("border=0");
    $count = count($graphs);
    while ( $count > 0 ) {
        $row = array();
        $row[0] = array_shift($graphs);
        if ( $count > 1 ) {
            $row[1] = array_shift($graphs);
        }
        $graphTable->addRow($row);
        $count = count($graphs);
    }
    echo $graphTable->toHTML();
    echo addLineBreak();
}

$statsDB = new StatsDB();
if ( requestValue('plot') == 'true' ) {
    $selectedStr = requestValue('selected');
    $selectedArr = explode(',', $selectedStr);
    $graphs = buildGraphsFromSelectedMetrics( $selectedArr, SECSERV_INSTR_TABLE );
    plotGraph($graphs);
} else {
    main();
}


include_once PHP_ROOT . "/common/finalise.php";

