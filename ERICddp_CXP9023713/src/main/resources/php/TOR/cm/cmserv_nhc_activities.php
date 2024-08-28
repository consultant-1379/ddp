<?php
$pageTitle = "Cmserv NHC Activities";

/* Disable the UI for non-main flow */
$DISABLE_UI_PARAMS = array( 'getdata' );

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const SERVERS_HOSTNAME = 'servers.hostname';
const SITES = 'sites';
const SERVERS = 'servers';
const TOTAL_REQUESTS = 'Total Requests';
function getCmServNHCActivity() {
    global $site, $date;

    $where = "enm_nhc_logs.acceptanceCriteriaID = enm_nhc_acceptance_criteria.id
              AND enm_nhc_logs.usrID = enm_nhc_users.id
              AND enm_nhc_logs.siteid = sites.id
              AND sites.name = '$site'
              AND enm_nhc_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $table = SqlTableBuilder::init()
        ->name('NHC_Activity')
        ->tables(array('enm_nhc_logs', 'enm_nhc_users','enm_nhc_acceptance_criteria', SITES))
        ->addSimpleColumn("enm_nhc_logs.activityID", 'NHC Activity ID')
        ->addSimpleColumn('enm_nhc_users.user', 'User ID')
        ->addColumn('start_time', 'enm_nhc_logs.startTime', 'Start Time')
        ->addSimpleColumn('enm_nhc_logs.stopTime', 'Stop Time')
        ->addSimpleColumn('enm_nhc_logs.nodeNo', 'Node No')
        ->addSimpleColumn('enm_nhc_logs.checkList', 'Check List')
        ->addSimpleColumn('enm_nhc_acceptance_criteria.acceptance_criteria_name', 'Acceptance Criteria Name')
        ->addSimpleColumn('enm_nhc_logs.requestNo', 'Request No')
        ->addSimpleColumn('enm_nhc_logs.responseNo', 'Response No')
        ->addSimpleColumn('enm_nhc_logs.reportName', 'Report Name')
        ->paginate()
        ->sortBy('start_time', DDPTable::SORT_ASC)
        ->where($where)
        ->build();

    echo $table->getTableWithHeader("NHC Activity", 2, "", "", "NHC_Activity");
}

function getNhcAcModifications() {
    global $site, $date;

    $where = "enm_nhc_ac_modifications.acceptance_criteria_id = enm_nhc_acceptance_criteria.id
              AND enm_nhc_ac_modifications.userid = enm_nhc_users.id
              AND enm_nhc_ac_modifications.siteid = sites.id
              AND enm_nhc_ac_modifications.serverid = servers.id
              AND servers.siteid = sites.id
              AND sites.name = '$site'
              AND enm_nhc_ac_modifications.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

    $table = SqlTableBuilder::init()
        ->name('Acceptance_Criteria_Modifications')
        ->tables(array( 'enm_nhc_ac_modifications','enm_nhc_users','enm_nhc_acceptance_criteria', SERVERS, SITES))
        ->addColumn('time', 'enm_nhc_ac_modifications.time', 'Time')
        ->addSimpleColumn(SERVERS_HOSTNAME, 'Instance')
        ->addSimpleColumn('enm_nhc_acceptance_criteria.acceptance_criteria_name', 'Acceptance Criteria Name')
        ->addSimpleColumn('enm_nhc_users.user', 'User ID')
        ->where($where)
        ->sortBy('time', DDPTable::SORT_ASC)
        ->build();

    echo $table->getTableWithHeader("Acceptance Criteria (AC) Modifications", 2, "", "", "Acceptance_Criteria_Modifications");
}


function plotCMServNHCInstrGraphs() {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    drawHeaderWithHelp("NHC Cmserv events Instrumentation", 1, "NHC_Cmserv_Events_Instrumentation");
    $graphTable = new HTML_Table("border=0");

    $instrGraphParams = array(
        array(
            SqlPlotParam::TITLE => TOTAL_REQUESTS,
            SqlPlotParam::TYPE => 'sb',
            'cols' => array(
                'numberOfRequests'  => TOTAL_REQUESTS
            )
        ),
        array(
            SqlPlotParam::TITLE => 'Total Responses',
            SqlPlotParam::TYPE => 'sb',
            'cols' => array(
                'numberOfResponses'  => 'Total Responses'
            )
        )
    );

    $where = "enm_cmserv_nhcinstr.siteid = sites.id
              AND sites.name = '%s'
              AND enm_cmserv_nhcinstr.serverid = servers.id";

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($instrGraphParam[SqlPlotParam::TITLE])
            ->type($instrGraphParam[SqlPlotParam::TYPE])
            ->yLabel('Count')
            ->makePersistent()
            ->barWidth(60)
            ->addQuery(
                'time',
                $instrGraphParam['cols'],
                array('enm_cmserv_nhcinstr', SITES, SERVERS),
                $where,
                array( 'site' ),
                SERVERS_HOSTNAME
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(
            array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320))
        );
    }
    echo $graphTable->toHTML();
}

function plotMscmNHCInstrGraphs() {
    global $date, $site;
    /* Graphs  */
    $sqlParamWriter = new SqlPlotParam();
    drawHeaderWithHelp("NHC Mscm events Instrumentation", 1, "NHC_Mscm_Events_Instrumentation");
    $graphTable = new HTML_Table("border=0");

    $instrGraphParams = array(
        array(
            SqlPlotParam::TITLE => TOTAL_REQUESTS,
            'cols' => array(
                'numberOfRequests'  => TOTAL_REQUESTS
            )
        )
    );

    $where = "enm_mscm_nhcinstr.siteid = sites.id
              AND sites.name = '%s'
              AND enm_mscm_nhcinstr.serverid = servers.id";
    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($instrGraphParam[SqlPlotParam::TITLE])
            ->type(SqlPlotParam::STACKED_BAR)
            ->barWidth(60)
            ->yLabel('Count')
            ->makePersistent()
            ->addQuery(
                'time',
                $instrGraphParam['cols'],
                array('enm_mscm_nhcinstr', SITES, SERVERS),
                $where,
                array('site'),
                SERVERS_HOSTNAME
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320)));
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    getCmServNHCActivity();
    getNhcAcModifications();

    plotCMServNHCInstrGraphs();
    plotMscmNHCInstrGraphs();
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

