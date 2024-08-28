<?php

$pageTitle = "Network Privileged Access Management";

include_once "../../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

function mainFlow() {
    global $statsDB;

    drawHeader('NPAM Job Details', 1, "npamjob");
    $table = new ModelledTable('TOR/security/npam_job_details', 'npamjob');
    echo $table->getTable();
    echo addLineBreak();

    /* Get the graphs */
    if ( $statsDB->hasData( 'enm_npam_instr' )) {
        drawHeader('Metrics for RemoteManagement AVC Events Received', 2, 'npamavc');
        getGraphsFromSet('npamavc', $graphs, 'TOR/security/npam_instr');
        plotGraphs($graphs);
    }
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
