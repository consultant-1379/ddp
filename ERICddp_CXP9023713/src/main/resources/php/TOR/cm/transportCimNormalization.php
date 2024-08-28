<?php
$pageTitle = "TransportCimNormalization";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const ENM_NODE_NORMALIZATION = "enm_node_tcim_normalization";
const CM_TRANSNORMALIZATION_INSTR = "cm_transportcimnormalization_instr";

function dailyTotalsParams() {
    return array(
                'SUM(tcimNormalizationNumSuccess)' => 'Success Normalizations',
                'SUM(tcimNormalizationNumFailure)' => 'Failure Normalizations',
                'IFNULL(ROUND(SUM(tcimNormalizationTotalNumberOfMoNormalized)/
                 SUM(tcimNormalizationNumSuccess)),0)' => 'Avg Normalized MOs/Normalizations',
                'IFNULL(SUM(tcimNormalizationTotalNumberOfMoNormalized)/
                 (SUM(tcimNormalizationTotalDurationOfNormalization)/1000),0)' => 'Avg Normalized MOs/Sec',
                'SUM(tcimNormalizationTotalDurationOfNormalization)/1000' => 'Total Normalization Duration',
                'SUM(tcimNormalizationTotalNumberOfMoNormalized)'=> 'Total MOs Normalized'
    );
}

function getExportDailyTotals() {
    global  $statsDB;
    $params = dailyTotalsParams();
    $where  = $statsDB->where( CM_TRANSNORMALIZATION_INSTR );
    $where .= ' AND cm_transportcimnormalization_instr.serverid = servers.id
               GROUP BY servers.hostname WITH ROLLUP';

    $table = SqlTableBuilder::init()
            ->name( CM_TRANSNORMALIZATION_INSTR )
            ->tables(array( CM_TRANSNORMALIZATION_INSTR, StatsDB::SERVERS, StatsDB::SITES))
            ->where($where)
            ->addSimpleColumn("IFNULL(servers.hostname,'Totals')", 'Instance');
    foreach ($params as $key => $value) {
        $table->addSimpleColumn($key, $value);
    }
    $table = $table->build();

    if ( $table->hasRows() ) {
        echo drawHeader("Daily Totals", HEADER_2, "CmTransportNormalisation");
        echo $table->getTable();
    }
 }

function tcimNormalization() {
    global  $statsDB;

    $startTime = "TIMEDIFF(time(time), SEC_TO_TIME(ROUND(tcimNormalizedMoDurationPerNode/1000)))";

    $where  = $statsDB->where(ENM_NODE_NORMALIZATION);
    $where .= ' AND enm_node_tcim_normalization.neid = enm_ne.id
               AND enm_node_tcim_normalization.netypeid = ne_types.id';

    $table = SqlTableBuilder::init()
        ->name(ENM_NODE_NORMALIZATION)
        ->tables(array(ENM_NODE_NORMALIZATION, 'enm_ne', 'ne_types', StatsDB::SITES))
        ->where($where)
        ->addSimpleColumn($startTime, 'Start Time')
        ->addSimpleColumn("enm_ne.name", 'TCIM node name')
        ->addSimpleColumn("ne_types.name", 'NeType')
        ->addSimpleColumn("tcimNormalizedNodeState", "TCIM node state")
        ->addSimpleColumn("tcimInterfacesCount", "TCIM normalized Interfaces per node")
        ->addSimpleColumn("tcimNumberOfFailedMos", "TCIM Failure MOs per node")
        ->addSimpleColumn("tcimNormalizedMoPerNode", "TCIM normalized MO per node")
        ->addSimpleColumn("tcimNormalizedMoDurationPerNode", "TCIM normalization duration per node (msec)")
        ->paginate()
        ->dbScrolling()
        ->build();
    if ( $table->hasRows() ) {
        echo drawHeader("Nodes Model Normalization", HEADER_2, "enm_node_tcim_normalization");
        echo $table->getTable();
    }
}

function mainFlow() {
    getExportDailyTotals();
    $normalizationlink = makeAnchorLink('enm_node_tcim_normalization_anchor', "Nodes Model Normalization" );
    echo makeHTMLList(array($normalizationlink));
    echo addLineBreak();
    tcimNormalization();
 }

mainFlow();
include PHP_ROOT . "/common/finalise.php";

