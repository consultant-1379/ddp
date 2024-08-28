<?php
$pageTitle = "";
$pageType = "";

if ( isset( $_GET['type'] ) ) {
    if ( $_GET['type'] == 'cli' ) {
        $pageTitle = "Bulk CM Import";
        $pageType = "cli";
    } else if ( $_GET['type'] == 'ui' ) {
        $pageTitle = "Bulk CM Import UI";
        $pageType = "ui";
    }
}

include "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

function defineFormatNTN() {
    echo <<<EOF
<script type="text/javascript">
function formatNTN(elCell, oRecord, oColumn, oData) {
    elCell.innerHTML = "No";
    var jobid = oRecord.getData("jobid");
    for (var i = 0; i < ntn_tableParam.data.length; i++ ) {
        if ( ntn_tableParam.data[i].jobid == jobid ) {
           elCell.innerHTML = "<a href=\"#ntn_anchor\">Yes</a>";
           return;
        }
    }
}
</script>
EOF;
}

function mainFlow() {
    global $pageType;

    if ( $pageType == 'cli' ) {
        $dailyTotals = 'TOR/cm/import_daily_totals';
        $dailyHelp = 'Daily_Totals';
        $dailyName = 'Daily Totals';
        $bulkTableName = 'Bulk CM Import';
        $modname = 'Bulk_CM_Import';
        $modTable = 'TOR/cm/import_details';
        $importGraphName = 'Import Performance';
        $modGraph = 'TOR/cm/import_mo_sec';
        $importHelp = 'import_perf';
        $ntnTable = 'TOR/cm/import_ntn';
        $ntnHelp = 'ntn';
        $ntnName = 'Non Transaction Node Imports';
    } elseif ( $pageType == 'ui') {
        $dailyTotals = 'TOR/cm/import_ui_daily_totals';
        $dailyHelp = 'bulkcmimportuidaily';
        $dailyName = 'Daily Totals';
        $bulkTableName = 'Bulk CM Import UI';
        $modname = 'bulkcmimportui';
        $modTable = 'TOR/cm/bulk_import_ui';
        $importGraphName = 'Import Performance';
        $modGraph = 'TOR/cm/import_mo_sec';
        $importHelp = 'import_perf';
        $executedGraphs = 'TOR/cm/bulk_import_ui';
        $executedName = 'Executed Jobs';
        $executedHelp = 'import_ui_graph';
    }

    $dailyTotals = new ModelledTable( $dailyTotals , $dailyHelp );
    echo $dailyTotals->getTableWithHeader($dailyName);

    $dps = makeLink( '/TOR/dps.php', 'DPS', array('servers' => makeSrvList('importexportservice') ) );
    $jmx = makeFullGenJmxLink('importexportservice', 'GenJmx');
    echo makeHTMLList( array($dps, $jmx) );

    defineFormatNTN();

    if ( $pageType == "ui" ) {
        drawHeader( $executedName, 2, $executedHelp );
        $modelledGraph = new ModelledGraph( $executedGraphs, $executedHelp );
        $graphs[] = $modelledGraph->getImage();
        plotGraphs($graphs);
    }

    $details = new ModelledTable( $modTable, $modname );
    if ( $details->hasRows() ) {
        drawHeader( $importGraphName, 2, $importHelp );
        $modelledGraph = new ModelledGraph( $modGraph );
        $graph = $modelledGraph->getImage();
        plotgraphs( array( $graph ) );
        echo $details->getTableWithHeader( $bulkTableName );
    }

    if ( $pageType == "cli" ) {
        $clintnTable = new ModelledTable( $ntnTable, $ntnHelp );
        echo $clintnTable->getTableWithHeader( $ntnName );
    }
}

mainFlow();

include PHP_ROOT . "/common/finalise.php";

