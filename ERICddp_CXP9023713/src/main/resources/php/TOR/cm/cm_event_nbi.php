<?php
$pageTitle = "CM EVENT NBI";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

function getCMEventNbiDailyTotalsTable()
{
    global $site, $date;
    $cols = array(
        array( 'key' => 'impexpServInstance', 'db' => "IFNULL(servers.hostname,'Totals')", 'label' => 'Instance'),
        array( 'key' => 'cmEventsNbiNumError', 'db' => 'SUM(cmEventsNbiNumError)', 'label' => 'Total Num Error'),
        array( 'key' => 'cmEventsNbiNumQueries', 'db' => 'SUM(cmEventsNbiNumQueries)', 'label' => 'Total Num Queries'),
        array( 'key' => 'cmEventsNbiNumSuccess', 'db' => 'SUM(cmEventsNbiNumSuccess)', 'label' => 'Total Num Success'),
        array( 'key' => 'cmEventsNbiTotalDurationOfEvents', 'db' => 'SUM(cmEventsNbiTotalDurationOfEvents)', 'label' => 'Total Duration of Event'),
        array( 'key' => 'cmEventsNbiTotalNumberOfEvents', 'db' => 'SUM(cmEventsNbiTotalNumberOfEvents)', 'label' => 'Total Number of Events')
    );

    $where = "cm_event_nbi_instr.siteid = sites.id AND sites.name = '$site' AND cm_event_nbi_instr.serverid = servers.id AND cm_event_nbi_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY servers.hostname WITH ROLLUP";

    $table = new SqlTable("Daily_Totals",
        $cols,
        array( 'cm_event_nbi_instr', 'sites','servers'),
        $where,
        TRUE
    );
    echo $table->getTableWithHeader("Daily Totals", 1, "", "", "Daily_Totals");
}

function plotInstrGraphs($statsDB) {
    global $date, $site;

    $sqlParamWriter = new SqlPlotParam();

    /* E2E Instrumentation Graphs */

    $graphTable = new HTML_Table("border=0");
    $instrGraphParams = array(
        'cmEventsNbiNumError' => array(
            'title' => 'Number of Error Status Response',
            'ylabel' => 'Count',
            'cols' => array(
                'cmEventsNbiNumError' => 'Num Error status'
            )
        ),
        'cmEventsNbiNumQueries' => array(
            'title' => 'Number of Queries over NBI',
            'ylabel' => 'Count',
            'cols' => array(
                'cmEventsNbiNumQueries' => 'Num Queries'
            )
        ),
        'cmEventsNbiNumSuccess' => array(
            'title' => 'Number of Success Status Response',
            'ylabel' => 'Count',
            'cols' => array(
                'cmEventsNbiNumSuccess' => 'Num Success'
            )
        ),
        'cmEventsNbiTotalDurationOfEvents' => array(
            'title' => 'Duration of Events',
            'ylabel' => 'Duration (millisec)',
            'cols' => array(
                'cmEventsNbiTotalDurationOfEvents' => 'Num Duration'
            )
        ),
        'cmEventsNbiTotalNumberOfEvents' => array(
            'title' => 'Number of Events',
            'ylabel' => 'Count',
            'cols' => array(
                'cmEventsNbiTotalNumberOfEvents' => 'Num Events'
            )
        )

    );

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $row = array();
        $sqlParam = array(
            'title' => $instrGraphParam['title'],
            'type' => 'sb',
            'sb.barwidth' => 60,
            'ylabel' => $instrGraphParam['ylabel'],
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => $instrGraphParam['cols'],
                    'tables' => "cm_event_nbi_instr, sites, servers",
                    "multiseries"=> 'servers.hostname',
                    'where' => "cm_event_nbi_instr.siteid = sites.id AND sites.name = '%s' AND cm_event_nbi_instr.serverid = servers.id",
                    'qargs' => array('site')
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320)));
    }
    echo $graphTable->toHTML();
}

function mainFlow($statsDB) {
    /* Daily Totals table */
    getCMEventNbiDailyTotalsTable($statsDB);

    echo "<ul>\n";
    echo " <li>Generic JMX\n";
    echo "  <ul>\n";
    echo "   <li><a href=\"" . makeGenJmxLink("cmevents") . "\">CMEVENT</a></li>\n";
    echo "  </ul>\n";
    echo " </li>\n";
    echo "</ul>\n";

    drawHeaderWithHelp("CM EVENT NBI Instrumentation", 1, "CM_EVENT_NBI_Instrumentation");
    plotInstrGraphs($statsDB);
}

$statsDB = new StatsDB();
mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>

