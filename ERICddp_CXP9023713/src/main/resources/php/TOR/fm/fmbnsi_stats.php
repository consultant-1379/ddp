<?php
$pageTitle = "FM BNSI Stats";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

require_once 'HTML/Table.php';

function fmBnsiGraphs() {
  global $debug, $webargs, $php_webroot, $date, $site;

    $sqlParamWriter = new SqlPlotParam();

    /* E2E Instrumentation Graphs */
    $e2eInstrumentationHelp = "DDP_Bubble_22_fmbnsi_Stats_E2E_Help";

    drawHeaderWithHelp("FM BNSI Instrumentation", 2, "e2eInstrumentationHelp", $e2eInstrumentationHelp);


    $graphTable = new HTML_Table("border=0");
    $instrGraphParams = array(
        'totalDelay' => array(
            'title' => 'Total Delay',
            'ylabel' => 'Time (MilliSec)',
            'cols' => array(
                'totalDelay' => 'Total Delay'
            )
        ),
        'totalDelayOnlyBnsi' => array(
            'title' => 'Total Delay BNSI',
            'ylabel' => 'Time (MilliSec)',
            'cols' => array(
                'totalDelayOnlyBnsi' => 'Total Delay BNSI'
            )
                ),
        'counterOverTimeMax' => array(
            'title' => 'Counter OverTime Max',
            'ylabel' => 'Count',
            'cols' => array(
                'counterOverTimeMax' => 'Counter OverTime Max'
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
            'persistent' => 'false',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => $instrGraphParam['cols'],
                    'tables' => "fm_bnsi_instr, sites, servers",
                    "multiseries"=> "servers.hostname",
                    'where' => "fm_bnsi_instr.siteid = sites.id AND sites.name = '%s' AND fm_bnsi_instr.serverid = servers.id",
                    'qargs' => array('site')
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320)));
    }
    echo $graphTable->toHTML();

    /* changes for new graph */
    $graphTable = new HTML_Table("border=0");
    $instances = getInstances("fm_bnsi_instr");

    $row = array();
    foreach ( $instances as $instance ) {
        $header[] = $instance;
        $sqlParam =
            array( 'title' => 'Alarms Translated',
                'ylabel' => 'Count',
                'type' => 'sb',
                'useragg' => 'true',
                'sb.barwidth' => '100',
                'persistent' => 'false',
                'querylist' =>
                    array(
                       array(
                         'timecol' => 'time',
                         'whatcol' => array ( 'alarmsTranslated' => 'alarmsTranslated', 'apsAlarmsTranslated' => 'apsAlarmsTranslated' ),
                         'tables' => "fm_bnsi_instr, sites, servers",
                         'where' => "fm_bnsi_instr.siteid = sites.id AND  sites.name = '%s' AND fm_bnsi_instr.serverid = servers.id AND servers.hostname = '$instance'",
                         'qargs' => array( 'site' )
                         )
                    )
                );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);

        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, "inst=$instance");
    }
    $graphTable->addRow($header, null, 'th');
    $graphTable->addRow($row);
    /* changes for new graph */


    echo $graphTable->toHTML();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site;

    echo "<ul>\n";

    echo " <li>Generic JMX\n";
    echo "  <ul>\n";
    echo "   <li><a href=\"" . makeGenJmxLink("nbibnsifm") . "\">FM BNSI</a></li>\n";
    echo "  </ul>\n";
    echo " </li>\n";

    echo "</ul>\n";

    fmBnsiGraphs();

}

mainFlow();
include PHP_ROOT . "/common/finalise.php";

?>

