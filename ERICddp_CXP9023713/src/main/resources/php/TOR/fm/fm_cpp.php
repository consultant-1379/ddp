<?php
$pageTitle = "FM CPP";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

require_once 'HTML/Table.php';

function getFmMediationTotals() {
    global $date, $site;

    $where = "enm_msfm_instr.siteid = sites.id
             AND sites.name = '$site'
             AND enm_msfm_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
             AND enm_msfm_instr.serverid = servers.id
             GROUP BY servers.hostname WITH ROLLUP";

    $table = SqlTableBuilder::init()
        ->name('MSFM_DailyTotals')
        ->tables(array( 'enm_msfm_instr', 'sites', 'servers'))
        ->where($where)
        ->addSimpleColumn('IFNULL(servers.hostname,"Totals")', 'MSFM Instance')
        ->addSimpleColumn('SUM(cpp_totalAlarmCountSendFromMediation)', 'CPP Totals Alarms Sent')
        ->addSimpleColumn('SUM(cpp_failedAlarmCountSendFromMediation)', 'CPP Total Alarms Send Failed')
        ->addSimpleColumn('ROUND(AVG(cpp_nodesUnderSupervision),0)', 'Average CPP Nodes Supervised')
        ->addSimpleColumn('ROUND(AVG(cpp_nodeUnderNodeSuspendedState),0)', 'Average CPP Nodes Suspended')
        ->addSimpleColumn('ROUND(AVG(cpp_nodesUnderHeartBeatFailure),0)', 'Average CPP Nodes HB Failure')
        ->build();
    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader("MSFM DailyTotals", 2, "", "", "MSFM_DailyTotals");
    }
}

function getMsfmGraphs() {
    global $date;

    $sqlParamWriter = new SqlPlotParam();

    drawHeaderWithHelp("CPP Mediation", 2, "CPP_Mediation");

    $graphTable = new HTML_Table("border=0");
    $instrGraphParams = array(
        'cpp_totalAlarmCountSendFromMediation' => 'Alarms Sent',
        'cpp_failedAlarmCountSendFromMediation' => 'Alarms Failed to Send',
        'cpp_nodesUnderSupervision' => 'Supervised',
        'cpp_nodeUnderNodeSuspendedState' => 'Suspended',
        'cpp_nodesUnderHeartBeatFailure' => 'Heartbeat Failure'
    );
    $where = "enm_msfm_instr.siteid = sites.id AND sites.name = '%s' AND enm_msfm_instr.serverid = servers.id";
    foreach ( $instrGraphParams as $dbCol => $label ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($label)
            ->type(SqlPlotParam::STACKED_BAR)
            ->barWidth(60)
            ->makePersistent()
            ->addQuery(
                'time',
                array( $dbCol => $label ),
                array('enm_msfm_instr', 'sites', 'servers'),
                $where,
                array('site'),
                'servers.hostname'
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320)));
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    getFmMediationTotals();
    echo "<br/><li><a href=\"" . makeGenJmxLink("msfm") . "\">GenericJMX</a></li>\n";
    getMsfmGraphs();
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
