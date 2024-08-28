<?php
$pageTitle = "REST Queries";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/common/tableFunctions.php";
require_once 'HTML/Table.php';

const WHERE = 'where';
const TABLES = 'tables';
const COLS = 'cols';
const HEADING = 'heading';

function getDBStrings( $type ) {
    if ( $type == 'kpi' ) {
        return array(
                   TABLES => "enm_kpiserv_instr, sites, servers",
                   WHERE => "enm_kpiserv_instr.siteid = sites.id AND  sites.name = '%s' AND
                             enm_kpiserv_instr.serverid = servers.id"
               );
    }elseif ( $type == 'rest' ) {
        return array(
                   TABLES => "kpiserv_reststatistics_instr, sites, servers",
                   WHERE => "kpiserv_reststatistics_instr.siteid = sites.id AND  sites.name = '%s' AND
                             kpiserv_reststatistics_instr.serverid = servers.id"
               );
    }else {
        return null;
    }
}

function generateParams( $cols, $labels, $types, $heading ) {
    $params = array();

    while ( count($cols) > 0 ) {
        $col1 = array_shift($cols);
        $col2 = array_shift($cols);

        if ( is_Array($types) ) {
            $type = array_shift($types);
        }else {
            $type = $types;
        }

        if ( is_array($labels) ) {
            $labelType = array_shift($labels);
        }else {
            $labelType = $labels;
        }

        $dbStr = getDBStrings( $type );
        $tables = $dbStr[TABLES];
        $where = $dbStr[WHERE];

        if ( $labelType == 'null' || $labelType == '1' ) {
            $label1 = $col1;
            $label2 = $col2;
        }else {
            $label1 = 'averageExecutionTime(ms)';
            $label2 = 'methodInvocations';
        }

        $newParams = array(
            $col1 => array(
                COLS => array( $col1 => $label1),
            ),
            $col2 => array(
                COLS => array( $col2 => $label2),
            ),
            TABLES => $tables,
            WHERE => $where
        );

        $params[] = $newParams;
    }
    
    return $params += ['heading' => $heading]; //NOSONAR
}

function defineParams( $date ) {
    $cols = array('getCellStatusDataexecutionTimeMillis/getCellStatusDatamethodInvocations',
                  'getCellStatusDatamethodInvocations');
    $instrGraphParams = generateParams( $cols, '2', 'rest', 'Cell Status Requests' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('getKpiViewerDataexecutionTimeMillis/getKpiViewerDatamethodInvocations',
                  'getKpiViewerDatamethodInvocations', 'numberOfKpisRequested',
                  'numberOfKpisExported', 'numberOfKpiViewerExportRequests');
    $types = array('rest', 'kpi', 'kpi');
    $labels = array('2', '1', '1',);
    $instrGraphParams = generateParams( $cols, $labels, $types, 'KPI Viewer Requests' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('getKpiBreachSummaryexecutionTimeMillis/getKpiBreachSummarymethodInvocations',
                  'getKpiBreachSummarymethodInvocations');
    $instrGraphParams = generateParams( $cols, '2', 'rest', 'Nodes Breached' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('getWorstPerformersexecutionTimeMillis/getWorstPerformersmethodInvocations',
                  'getWorstPerformersmethodInvocations');
    $instrGraphParams = generateParams( $cols, '2', 'rest', 'Worst Performers' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('numberGetAllElementsInScopeRequests', 'numberGetQueryScopeRequests',
                  'numberGetAttributesForScopeRequests', 'numberGetNeTypesInScopeRequets',
                  'numberIsScopeReadyRequests', 'numberAddElementToScopeRequests',
                  'numberAddElementsToScopeRequests', 'numberAddElementsToSelectedScopeRequests',
                  'numberCreateFixedScopeRequests', 'numberDeleteFixedScopeRequests',
                  'numberDeleteFromScopeRequests', 'numberDeleteScopeByIdRequests',
                  'numberDeleteAllElementsInScopeRequests');
    $instrGraphParams = generateParams( $cols, '1', 'kpi', 'Network Scope Requests' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('processNeStateChangeexecutionTimeMillis/processNeStateChangemethodInvocations',
                  'processNeStateChangemethodInvocations');
    $instrGraphParams = generateParams( $cols, '2', 'rest', 'Network Status Requests - ProcessNEStateChange' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('processStateChangeEventexecutionTimeMillis/processStateChangeEventmethodInvocations',
                  'processStateChangeEventmethodInvocations');
    $instrGraphParams = generateParams( $cols, '2', 'rest', 'Network Status Requests - ProcessStateChangeEvent' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('numberOfKpiTabRequests', 'numberOfCellTabRequests', 'numberOfNodeTabRequests',
                  'numberOfNhaKpisRequested', 'numberOfNHAExportsRequested', 'numberOfNhaCmStateRequests',
                  'numberOfHistoricalDataRequests');
    $instrGraphParams = generateParams( $cols, '1', 'kpi', 'NHA KPI and CM' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('numberSyncStateWidgetRequests');
    $instrGraphParams = generateParams( $cols, '1', 'kpi', 'Sync State Widget Requests' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('numberNetworkStateWidgetRequests');
    $instrGraphParams = generateParams( $cols, '1', 'kpi', 'Network State Widget Requests' );
    plotGraph( $date, $instrGraphParams );

    $cols = array('numberOfAdminStateEventsPushed', 'numberOfAvailabilityStatusEventsPushed',
                  'numberOfNodeLevelStateEventsPushed', 'numberOfOpStateEventsPushed', 'numberOfSyncStateEventsPushed');
    $instrGraphParams = generateParams( $cols, '1', 'kpi', 'Webpush' );
    plotGraph( $date, $instrGraphParams );
}

function generateGraph( $col, $title, $tables, $where, $date ) {
    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
          ->title($title)
          ->type(SqlPlotParam::STACKED_BAR)
          ->barwidth(60)
          ->yLabel('')
          ->makePersistent()
          ->forceLegend()
          ->addQuery(
              SqlPlotParam::DEFAULT_TIME_COL,
              array ($col => $title),
              array($tables),
              $where,
              array('site'),
              'servers.hostname'
              )
          ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 550, 320);
}

function plotGraph( $date, $instrGraphParams ) {
    $heading = $instrGraphParams[HEADING];
    $helpHeading = str_replace(" ", "_", $heading);
    drawHeaderWithHelp( $heading, 2, $helpHeading, "");

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $graphTable = new HTML_Table("border=0");
        $row = array();

        if ( !is_array($instrGraphParam) ) {
            break;
        }
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $where = $instrGraphParam[WHERE];
            $tables = $instrGraphParam[TABLES];
            if ( !isset($instrGraphParamName[COLS]) ) {
                break;
            }
            foreach ( $instrGraphParamName[COLS] as $col => $title ) {
                $row[] = generateGraph( $col, $title, $tables, $where, $date );
            }
        }
        $graphTable->addRow($row);
        echo $graphTable->toHTML();
    }
    echo "<br/>";
}

function getParams() {
    return array(
        'numberOfKpiRequestsLessBreachInfoSent',
        'numberOfKpiResponsesLessBreachInfoReceived',
        'numberOfKpiRequestsLessBreachInfoFailed'
    );
}

function showLinks() {
    $links = array();
    $links[] = makeAnchorLink("Cell_Status_Requests_anchor", 'Cell Status Requests');
    $links[] = makeAnchorLink("KPI_Viewer_Requests_anchor", 'KPI Viewer Requests');
    $links[] = makeAnchorLink("Nodes_Breached_anchor", 'Nodes Breached');
    $links[] = makeAnchorLink("Worst_Performers_anchor", 'Worst Performers');
    $links[] = makeAnchorLink("Network_Scope_Requests_anchor", 'Network Scope Requests');
    $links[] = makeAnchorLink(
        "Network_Status_Requests_-_ProcessNEStateChange_anchor",
        'Network Status Requests - ProcessNEStateChange'
    );
    $links[] = makeAnchorLink(
        "Network_Status_Requests_-_ProcessStateChangeEvent_anchor",
        'Network Status Requests - ProcessStateChangeEvent'
    );
    $links[] = makeAnchorLink("NHA_KPI_and_CM_anchor", 'NHA KPI and CM');
    $links[] = makeAnchorLink("Sync_State_Widget_Requests_anchor", 'Sync State Widget Requests');
    $links[] = makeAnchorLink("Network_State_Widget_Requests_anchor", 'Network State Widget Requests');
    $links[] = makeAnchorLink("Webpush_anchor", 'Webpush');
    $links[] = makeAnchorLink("KPI_Rest_Resource_Requests_anchor", 'KPI Rest Resource Requests');
    $links[] = makeLink( '/TOR/pm/nhm_fetch_kpi.php', 'NHM REST NBI' );
    echo makeHTMLList($links);
}

function mainFlow() {
    global $date;
    echo "<H1>NHM REST Queries</H1>";
    showLinks();
    defineParams( $date );
    drawHeader("KPI Rest Resource Requests", 1, "KPI_Rest_Resource_Requests");
    $graphs = buildGraphsFromSelectedMetrics( getParams(), 'enm_kpiserv_instr' );
    plotGraphs($graphs);
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

