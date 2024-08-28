<?php
$pageTitle = "DomainProxy";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const ACTION = 'action';
const SAS = 'SASInteractions';
const DPS = 'DPSInteractions';
const DP = 'DomainProxyCounts';
const AC = 'AbsoluteCounts';
const TITLE = 'title';
const COLS = 'cols';
const PARAMS = 'params';

function graphParams() {
    return array(
        array(
            TITLE => 'SAS Response',
            COLS => array('ROUND(postToSasTimeRunningTotal/requestsPostedToSASCount)', 'requestsPostedToSASCount')
        ),
        array(
            TITLE => 'Set CBRS Tx Expiration',
            COLS => array('ROUND(setCbrsTxExpireTimeTimeRunningTotal/setCbrsTxExpireTimeCount)',
                          'setCbrsTxExpireTimeCount')
        )
    );
}

function tableParams($type) {
    if ( $type == SAS ) {
        $sectionTitle = 'SAS Interactions - Count Totals';
        $graphParams = array(
                            array('De-registration Requests' => 'deregistrationRequestsCount',
                                  'De-registration Responses' => 'deregistrationResponsesCount'),
                            array('Grant Requests' => 'grantRequestsCount', 'Grant Responses' => 'grantResponsesCount'),
                            array('Heartbeat Requests' => 'heartbeatRequestsCount',
                                  'Heartbeat Responses' => 'heartbeatResponsesCount'),
                            array('Spectrum Inquiry Requests' => 'spectrumInquiryRequestsCount',
                                  'Spectrum Inquiry Responses' => 'spectrumInquiryResponsesCount')
        );
        $helpId = 'sas';
    } elseif ( $type == DPS ) {
        $sectionTitle = 'DPS Interactions';
        $graphParams = array(
                            array('Frequency Changes' => 'frequenciesChangedCount',
                                  'Deleted EUtranFrequencies' => 'EUtranFrequenciesDeletedCount'),
                            array('Deleted EUtranFrequencyRelations' => 'EUtranFrequencyRelationsDeletedCount',
                                  'MO(s) Read from DPS' => 'MOsReadFromDPSCount'),
                            array('Set CBRS Tx Expire Time' => 'setCbrsTxExpireTimeCount')
        );
        $helpId = 'dps';
    } elseif ( $type == DP ) {
        $sectionTitle = 'Domain Proxy - Count Totals';
        $graphParams = array(
                            array('Terminated Grants' => 'numberOfTerminatedGrantsIncremental',
                                  'Relinquished Grants' => 'numberOfRelinquishedGrantsIncremental'),
                            array('Revoked Grants' => 'numberOfRevokedGrantsIncremental',
                                  'Suspended Grants' => 'numberOfSuspendedGrantsIncremental'),
                            array('Failed Registrations' => 'numberOfFailedCbsdRegistrationsIncremental',
                                  'Unsuccessful SAS Connections' =>
                                  'numberOfFailedConnectionAttemptsWithSasIncremental')
        );
        $helpId = 'dp';
    } elseif ( $type == AC ) {
        $sectionTitle = 'Domain Proxy - Absolute Counts';
        $graphParams = array(
                            array('Valid Grants' => 'numberOfValidGrantsCount',
                                  'Maintained Grants' => 'numberOfMaintainedGrantsCount'),
                            array('Inactive Cells' => 'numberOfInactiveCellsCount',
                                  'Active Cells' => 'numberOfActiveCellsCount')
        );
        $helpId = 'ac';
    }

    return array( TITLE => $sectionTitle, 'TYPE' => $type, PARAMS => $graphParams, 'Help' => $helpId );
}

function generateGraph( $col, $title, $dbTable ) {
    global $date, $site;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id
              AND sites.name = '%s'
              AND $dbTable.serverid = servers.id";

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
              $dbTables,
              $where,
              array('site'),
              'servers.hostname'
              )
          ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    return $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 550, 320);
}

function plotGraph( $graphParams, $dbTable ) {
    $heading = $graphParams[TITLE];
    
    $graphTable = new HTML_Table("border=0");
    $row[] = generateGraph( $graphParams[COLS][0], $heading . ' averageExecutionTime(ms)', $dbTable );
    $row[] = generateGraph( $graphParams[COLS][1], $heading . ' methodInvocations', $dbTable );
    $graphTable->addRow($row);

    echo $graphTable->toHTML();
    echo addLineBreak();
}

function drawTables( $dbTable, $params ) {
    global $site, $date, $webargs;

    $tableParams = $params[PARAMS];
    $where = "$dbTable.siteid = sites.id
              AND sites.name = '$site'
              AND $dbTable.serverid = servers.id
              AND $dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              GROUP BY servers.hostname WITH ROLLUP";

    $builder = SqlTableBuilder::init()
             ->name($params['TYPE'])
             ->tables(array( $dbTable, StatsDB::SITES, StatsDB::SERVERS ))
             ->where($where)
             ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    foreach ($tableParams as $param) {
        foreach ($param as $key => $value) {
            $builder->addSimpleColumn("SUM($value)", $key);
        }
    }

    drawHeader($params[TITLE], 2, $params['Help']);
    echo $builder->ctxMenu(
        ACTION,
        true,
        array( $params['TYPE'] => 'Plot'),
        fromServer(PHP_SELF) . "?" . $webargs,
        'inst'
        )
        ->build()->getTable();
}

function plotTableGraphs( $selected, $params, $dbTable ) {
    global $date, $site, $statsDB;

    if ( strpos($selected, 'Totals') !== false ) {
        $where = "$dbTable.siteid = sites.id
                  AND sites.name = '%s'
                  AND $dbTable.serverid = servers.id";
    } else {
        $servArr = explode(",", $selected);
        $servs = "'" . implode("', '", $servArr) . "'";
        $where = "$dbTable.siteid = sites.id
                  AND sites.name = '%s'
                  AND servers.hostname IN (%s)
                  AND $dbTable.serverid = servers.id";
    }

    $width = 600;
    drawHeader( $params[TITLE], 2, $params['Help']);

    $graphParams = $params[PARAMS];
    $sqlParamWriter = new SqlPlotParam();

    $graphTable = new HTML_Table("border=0");
    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    foreach ( $graphParams as $graphRow ) {
        $row = array();
        foreach ( $graphRow as $title => $column ) {
            $sqlParam = SqlPlotParamBuilder::init()
                      ->title($title)
                      ->type(SqlPlotParam::STACKED_BAR)
                      ->barwidth(60)
                      ->yLabel("")
                      ->makePersistent()
                      ->forceLegend()
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array( $column => $title ),
                          $dbTables,
                          $where,
                          array('site', 'servs'),
                          'servers.hostname'
                      )
                      ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);

            $row[] = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                $width,
                320,
                "&servs=$servs"
            );
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow( $dbTable, $actions ) {
    global $date, $site, $statsDB;
    
    drawHeader('Domain Proxy', 1, '');
    if ( $statsDB->hasData($dbTable) ) {
        foreach ( $actions as $action ) {
            $tableParams =  tableParams($action);
            drawTables($dbTable, $tableParams);
        }
        drawHeader( 'Instrumentation Graphs', 2, 'instrGraphs');
        $graphParams =  graphParams();
        foreach ($graphParams as $graphParam) {
            plotGraph( $graphParam, $dbTable );
        }
    } else {
        echo "<H1>No Data Available In $dbTable For $date<H1>";
    }
}

$dbTable = 'enm_domainproxy_instr';
$actions = array(SAS, DPS, DP, AC);

if (isset($_REQUEST[ACTION])) {
    foreach ( $actions as $action ) {
        if ( $_REQUEST[ACTION] === $action ) {
            plotTableGraphs($_REQUEST['selected'], tableParams($action), $dbTable);
        }
    }
} else {
    mainFlow( $dbTable, $actions );
}

include_once PHP_ROOT . "/common/finalise.php";

