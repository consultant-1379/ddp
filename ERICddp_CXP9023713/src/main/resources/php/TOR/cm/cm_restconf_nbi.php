<?php
$pageTitle = "RESTCONF NBI";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function tableParams() {
    return array(
        array('enm_cm_restconf_daily_totals', 'restconfDailyTotals', 'Restconf Daily Totals'),
        array('enm_cm_restconf_request_method_totals', 'restconfReqMethodTotals', 'Restconf Request Method Totals'),
        array('enm_cm_restconf_request_type_totals', 'restconfReqTypeTotals', 'Restconf Request Type Totals'),
        array('enm_cm_restconf_yang_module_requests_totals', 'restconfYang', 'Restconf Yang Module Requests Totals')
    );
}

function drawGraphGroup($group, $title, $help) {
    drawHeader($title, 1, $help);
    $graphs = array();
    getGraphsFromSet($group, $graphs, 'TOR/cm/enm_cm_restconf_nbi');
    plotGraphs($graphs);
}

function mainFlow() {
    $params = tableParams();
    $links = array();
    $tables = array();

    foreach ($params as $param) {
        $table = new ModelledTable( "TOR/cm/$param[0]", $param[1] );
        if ( $table->hasRows() ) {
            $links[] = makeAnchorLink($param[1], $param[2]);
            $tables[] = $table->getTableWithHeader($param[2]);
        }
    }
    $links[] = makeAnchorLink('req_totals', 'RestConf Request Data Totals');
    $links[] = makeAnchorLink('res_totals', 'RestConf Response Data Totals');
    $links[] = makeAnchorLink('mo_totals', 'RestConf DPS MO Totals');

    echo makeHTMLList($links);
    foreach ( $tables as $tbl ) {
        echo $tbl;
    }

    drawGraphGroup('req', 'RestConf Request Data Totals', 'req_totals');
    drawGraphGroup('res', 'RestConf Response Data Totals', 'res_totals');
    drawGraphGroup('mos', 'RestConf DPS MO Totals', 'mo_totals');
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
