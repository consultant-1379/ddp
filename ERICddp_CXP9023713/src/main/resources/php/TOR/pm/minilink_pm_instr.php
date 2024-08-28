<?php
$pageTitle = "Minilink PM instrumentation";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

const PATH = '/TOR/pm/minilink_pm_instr.php';
const TRANSFER_TYPE = 'transfertype';

function getSnmpPm15mParams() {
    $snmp15mparams = array (
        'snmp_noOfContinuousFiles15min',
        'snmp_noOfHistoricalFiles15min',
        'snmp_noOfRecoveredHistoricalFiles15min',
        'snmp_minCounterValues15min',
        'snmp_avgCounterValues15min',
        'snmp_maxCounterValues15min',
        'snmp_minCollectionHandlerTime15min',
        'snmp_maxCollectionHandlerTime15min',
        'snmp_minCounterCollectionTime15min',
        'snmp_maxCounterCollectionTime15min',
        'snmp_minCreationHandlerTime15min',
        'snmp_maxCreationHandlerTime15min',
        'snmp_noOfSnmpPingFailures15min',
        'snmp_noOfInterfacePopulationFailures15min',
        'snmp_numberOfZeroCountersFile15min',
        'snmp_noOfErrorsInFiles15min',
        'snmp_overallnoOfHistoricalFiles15min',
        'snmp_overallnoOfContinuousFiles15min',
        'snmp_overallnoOfRecoveredHistoricalFiles15min'
    );
    return array(
        array ('SnmpPmFileCollection15min', $snmp15mparams, 'MiniLinkSnmp_Instrumentation_15')
    );
}

function getSnmpPm24hParams() {
    $snmp24hparams = array (
        'snmp_noOfContinuousFiles24h',
        'snmp_noOfHistoricalFiles24h',
        'snmp_noOfRecoveredHistoricalFiles24h',
        'snmp_minCounterValues24h',
        'snmp_avgCounterValues24h',
        'snmp_maxCounterValues24h',
        'snmp_minCollectionHandlerTime24h',
        'snmp_maxCollectionHandlerTime24h',
        'snmp_minCounterCollectionTime24h',
        'snmp_maxCounterCollectionTime24h',
        'snmp_minCreationHandlerTime24h',
        'snmp_maxCreationHandlerTime24h',
        'snmp_noOfSnmpPingFailures24h',
        'snmp_noOfInterfacePopulationFailures24h',
        'snmp_numberOfZeroCountersFile24h',
        'snmp_noOfErrorsInFiles24h',
        'snmp_overallnoOfHistoricalFiles24h',
        'snmp_overallnoOfContinuousFiles24h',
        'snmp_overallnoOfRecoveredHistoricalFiles24h'
    );
    return array(
        array ('SnmpPmFileCollection24h', $snmp24hparams, 'MiniLinkSnmp_Instrumentation_24')
    );
}

function getEthernetPm15mParams() {
    $mlInEthernet15m = array (
        'ml_indoor_noOfCollectedEthernetFiles15min',
        'ml_indoor_noOfRecoveredEthernetFiles15min',
        'ml_indoor_EthernetminProcessingHandlerTime15min',
        'ml_indoor_EthernetmaxProcessingHandlerTime15min',
        'overallEthernetPmFileCollection15min',
        'ml_indoor_numberOfUploadRequestFailuresEthernet15min',
        'ml_indoor_numberOfSuccessfulRequestsEthernet15min',
        'ml_indoor_numberOfProcessingFlowFailuresEthernet15min',
        'ml_indoor_numberOfSuccessfulRecoveryRequestsEthernet15min',
        'ml_indoor_numberOfFailedRecoveryRequestsEthernet15min'
    );
    return array (
        array ('EthernetPmFileCollection15m', $mlInEthernet15m, 'MiniLinkEthernet_Instrumentation_15')
    );
}

function getEthernetPm24hParams() {
    $mlInEthernet24h = array (
        'ml_indoor_noOfCollectedEthernetFiles24h',
        'ml_indoor_noOfRecoveredEthernetFiles24h',
        'ml_indoor_minProcessingHandlerTime24h',
        'ml_indoor_maxProcessingHandlerTime24h',
        'overallEthernetPmFileCollection24h',
        'ml_indoor_numberOfUploadRequestFailuresEthernet24h',
        'ml_indoor_numberOfSuccessfulRequestsEthernet24h',
        'ml_indoor_numberOfProcessingFlowFailuresEthernet24h'
    );
    return array (
        array ('EthernetPmFileCollection24h', $mlInEthernet24h, 'MiniLinkEthernet_Instrumentation_24')
    );
}

function getSoamPm15mParams() {
    $mlInSoam15m = array (
        'ml_indoor_noOfCollectedSOAMFiles15min',
        'ml_indoor_noOfRecoveredSOAMFiles15min',
        'ml_indoor_SOAMminProcessingHandlerTime15min',
        'ml_indoor_SOAMmaxProcessingHandlerTime15min',
        'overallSOAMPmFileCollection15min',
        'ml_indoor_numberOfUploadRequestFailuresSoam15min',
        'ml_indoor_numberOfSuccessfulRequestsSoam15min',
        'ml_indoor_numberOfProcessingFlowFailuresSoam15min',
        'ml_indoor_numberOfSuccessfulRecoveryRequestsSoam15min',
        'ml_indoor_numberOfFailedRecoveryRequestsSoam15min'
    );
    return array (
        array ('SOAMPmFileCollection15m', $mlInSoam15m, 'MiniLinkSoam_Instrumentation_15')
    );
}

function getSoamPm24hParams() {
    $mlInSoam24h = array (
        'ml_indoor_noOfCollectedSOAMFiles24h',
        'ml_indoor_noOfRecoveredSOAMFiles24h',
        'ml_indoor_SOAMminProcessingHandlerTime24h',
        'ml_indoor_SOAMmaxProcessingHandlerTime24h',
        'overallSOAMPmFileCollection24h',
        'ml_indoor_numberOfUploadRequestFailuresSoam24h',
        'ml_indoor_numberOfSuccessfulRequestsSoam24h',
        'ml_indoor_numberOfProcessingFlowFailuresSoam24h'
    );
    return array (
        array ('SOAMPmFileCollection24h', $mlInSoam24h, 'MiniLinkSoam_Instrumentation_24')
    );
}


function displaylink() {
    if ( issetURLParam('Minisnmp15') ) {
        $snmp15mparams = getSnmpPm15mParams();
        makeGraphs($snmp15mparams);
    }
    if ( issetURLParam('Minisnmp24') ) {
        $snmp24hparams = getSnmpPm24hParams();
        makeGraphs($snmp24hparams);
    }
    if ( issetURLParam('MiniEth15') ) {
        $ethernetPmParams15m = getEthernetPm15mParams();
        makeGraphs($ethernetPmParams15m);
    }
    if ( issetURLParam('MiniEth24') ) {
        $ethernetPmParams24h = getEthernetPm24hParams();
        makeGraphs($ethernetPmParams24h);
    }
    if ( issetURLParam('MiniSoam15') ) {
        $soamPmParams15m = getSoamPm15mParams();
        makeGraphs($soamPmParams15m);
    }
    if ( issetURLParam('MiniSoam24') ) {
        $soamPmParams24h = getSoamPm24hParams();
        makeGraphs($soamPmParams24h);
    }
    if ( issetURLParam('MiniBulk15') ) {
        drawHeader("BulkPmFileCollection15m", 1, "MiniLinkBulk_Instrumentation_15");
        $graphs=array();
        getGraphsFromSet('bulkPm15m', $graphs, 'TOR/pm/bulk_pm_file_collection');
        $modelledGraph = new ModelledGraph('TOR/pm/BulkPmFileCollection15min');
        $graphs[] = $modelledGraph->getImage();
        plotGraphs($graphs);
    }
    if ( issetURLParam('MiniBulk24') ) {
        drawHeader("BulkPmFileCollection24h", 1, "MiniLinkBulk_Instrumentation_24");
        $graphs = array();
        getGraphsFromSet('bulkPm24h', $graphs, 'TOR/pm/bulk_pm_file_collection');
        $modelledGraph = new ModelledGraph('TOR/pm/BulkPmFileCollection24h');
        $graphs[] = $modelledGraph->getImage();
        plotGraphs($graphs);
    }
}

function makeGraphs($params) {
    foreach ( $params as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 1, $help);
        $graphParams = $param[1];
        foreach ( $graphParams as $graphParam ) {
            $modelledGraph = new ModelledGraph( 'TOR/pm/' . $graphParam );
            $graphs[] = $modelledGraph->getImage();
        }
        plotgraphs( $graphs );
    }
}

function mainFlow() {
    global $statsDB;

    $transferType = requestValue(TRANSFER_TYPE);

    $link = makeLink(
        '/TOR/pm/pm_push_audit.php',
        'Return to PM Mediation Link',
        array( TRANSFER_TYPE => "$transferType")
    );
    echo "$link\n";

    if ( $statsDB->hasData( "enm_mspmip_instr" ) ) {
        $linkList =array();
        if ($transferType == "GENERATION") {
            $linkList[] = makeLink(
                PATH,
                'SnmpPmFileCollection15min',
                array('Minisnmp15' => 1, TRANSFER_TYPE => "$transferType")
            );
            $linkList[] = makeLink(
                PATH,
                "SnmpPmFileCollection24h",
                array('Minisnmp24' => 1, TRANSFER_TYPE => "$transferType" )
            );
        } elseif ($transferType == "PUSH") {
            $linkList[] = makeLink(
                PATH,
                "EthernetPmFileCollection15m",
                array('MiniEth15' => 1, TRANSFER_TYPE => "$transferType" )
            );
            $linkList[] = makeLink(
                PATH,
                "EthernetPmFileCollection24h",
                array('MiniEth24' => 1, TRANSFER_TYPE => "$transferType" )
            );
            $linkList[] = makeLink(
                PATH,
                "SOAMPmFileCollection15m",
                array('MiniSoam15' => 1, TRANSFER_TYPE => "$transferType" )
            );
            $linkList[] = makeLink(
                PATH,
                "SOAMPmFileCollection24h",
                array('MiniSoam24' => 1, TRANSFER_TYPE => "$transferType" )
            );
            $linkList[] = makeLink(
                PATH,
                "BulkPmFileCollection15m",
                array('MiniBulk15' => 1, TRANSFER_TYPE => "$transferType" )
            );
            $linkList[] = makeLink(
                PATH,
                "BulkPmFileCollection24h",
                array('MiniBulk24' => 1, TRANSFER_TYPE => "$transferType" )
            );
        }
        echo makeHTMLList( $linkList );
        displaylink();
    }

}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
