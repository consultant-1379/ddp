<?php
$pageTitle = "Forwarder instances for " . $_GET['sg']; //NOSONAR

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const ESN = 'esnforwarderdecodeddef';
const ASR = 'asrlforwarderdef';
const SIDS = 'serverids';
const STR_PATH = 'TOR/pm/streaming/';
const ASR_HELP = 'asrDaily';
const ESN_HELP = 'esnDaily';

function asrlParams() {
   return array(
       'enm_asrl_forward_eventsIn',
       'enm_asrl_forward_eventsOut',
       'enm_asrl_forward_delta'
   );
}

function asrnParams() {
   return array(
       'enm_asrn_forward_eventsIn',
       'enm_asrn_forward_eventsOut',
       'enm_asrn_forward_delta'
   );
}

function esnParams() {
   return array(
       'enm_esnforward_eventsIn',
       'enm_esnforward_eventsOut',
       'enm_esnforward_delta'
   );
}

function drawGraphs( $graphsParams, $srvIdsStr ) {
    $params = array( SIDS => $srvIdsStr);
    foreach ( $graphsParams as $param ) {
        $modelledGraph = new ModelledGraph(STR_PATH . $param);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotGraphs( $graphs );
}

function mainFlow( $sg, $srvIdsStr ) {
    $selfLink = makeSelfLink();
    $selfLink .= "&sg=$sg";
    $tblParams = array(SIDS => $srvIdsStr,  ModelledTable::URL => $selfLink );

    if ( $sg === ESN ) {
        $table = new ModelledTable('TOR/pm/enm_esnforward', ESN_HELP, $tblParams);
        $esnGraphs = esnParams();
    } elseif ( $sg === ASR ) {
        $table = new ModelledTable('TOR/pm/enm_asrforward', ASR_HELP, $tblParams);
        $asrlGraphs = asrlParams();
        $asrnGraphs = asrnParams();
    } else {
        echo "Something went wrong!";
    }

    if ( $table ) {
        echo $table->getTableWithHeader("Daily Totals", 1);
        echo addLineBreak();
    }
    if ( isset($asrlGraphs) ) {
        drawHeader('ASR-L Graphs', 1, ASR_HELP);
        drawGraphs( $asrlGraphs, $srvIdsStr );
    }
    if ( isset($asrnGraphs) ) {
        drawHeader('ASR-N Graphs', 1, ASR_HELP);
        drawGraphs( $asrnGraphs, $srvIdsStr );
    }
    if ( isset($esnGraphs) ) {
        drawHeader('ESN Graphs', 1, ESN_HELP);
        drawGraphs( $esnGraphs, $srvIdsStr );
    }
}

$sg = requestValue('sg');
$action = requestValue('action');
$srvList = enmGetServiceInstances($statsDB, $site, $date, $sg);
$srvIdsStr = implode(",", $srvList);

if ( $action ) {
    if ( $sg === ESN ) {
        $graphsParams = esnParams();
        $type = 'ESN';
        $help = ESN_HELP;
    } elseif ( $sg === ASR ) {
        $last = substr($action, -2);
        $help = ASR_HELP;
        if ( $last === '-L' ) {
            $graphsParams = asrlParams();
            $type = 'ASRL';
        } elseif ( $last === '-N' ) {
            $graphsParams = asrnParams();
            $type = 'ASRN';
        }
    }

    $plotAll = strpos($action, 'plotAll');
    $plotInd = strpos($action, 'plotInd');

    if ( $plotAll === 0 ) {
        $graphs = array();
        $params = array( SIDS => $srvIdsStr );
        foreach ( $graphsParams as $param ) {
            $param .= "_All";
            $modelledGraph = new ModelledGraph(STR_PATH . $param);
            $graphs[] = $modelledGraph->getImage($params);
        }
        drawHeader("$type All Instances", 1, $help);
        plotGraphs( $graphs );
    } elseif ( $plotInd === 0 ) {
        drawHeader("$type Individual Instances", 1, $help);
        foreach ( $srvList as $host => $sId ) {
            $graphs = array();
            $params = array( 'serverid' => $sId );
            foreach ( $graphsParams as $param ) {
                $param .= "_Ind";
                $modelledGraph = new ModelledGraph(STR_PATH . $param);
                $graphs[] = $modelledGraph->getImage($params);
            }
            drawHeader($host, 2, '');
            plotGraphs( $graphs );
        }
    }
} else {
    mainFlow( $sg, $srvIdsStr );
}

include_once PHP_ROOT . "/common/finalise.php";

