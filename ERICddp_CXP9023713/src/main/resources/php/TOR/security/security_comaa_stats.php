<?php

$pageTitle = "COM AA Access Control Stats";

include "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function mainFlow() {

    /* Daily Summary table */
    drawHeaderWithHelp("Daily Totals", 1, "dailyTotalHelp");
    $table = new ModelledTable('TOR/security/enm_secserv_comaa_instr_daily_totals', 'dailyTotalHelp');
    echo $table->getTable("Daily Totals");
    echo addLineBreak();

    /* Get the graphs */
    drawHeaderWithHelp("COMAA Access Control Instrumentation Graphs", 1, "instrGraphs");
    getGraphsFromSet('all', $graphs, 'TOR/security/enm_secserv_comaa_instr_graphs');
    plotGraphs($graphs);

}

mainFlow();

include PHP_ROOT . "/common/finalise.php";

?>


