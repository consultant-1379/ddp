<?php
$pageTitle = "UplinkSpectrum Analyser";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

function getDailyTotals()
{
    global $site,$date,$webargs;

    $cols = array(
                  array('key' => 'time', 'db' => 'DATE_FORMAT(from_unixtime((epochtime-total_time)/1000),"%H:%i:%s")','label' => 'Start Time'),
                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'source', 'db' => 'enm_ulsa_spectrum_analyser_logs.source', 'label' => 'Source'),
                  array('key' => 'sample', 'db' => 'enm_ulsa_spectrum_analyser_logs.sample','label' => 'Samples'),
                  array('key' => 'file_parsing_time', 'db' => 'enm_ulsa_spectrum_analyser_logs.file_parsing_time','label' => 'File Parsing Time(ms)'),
                  array('key' => 'fast_fourier_time', 'db' => 'enm_ulsa_spectrum_analyser_logs.fast_fourier_time', 'label' => 'Fast Fourier Time(ms)'),
                  array('key' => 'post_processing_time', 'db' => 'enm_ulsa_spectrum_analyser_logs.post_processing_time', 'label' => 'Post Processing Time(ms)'),
                  array('key' => 'chart_scaling_time', 'db' => 'enm_ulsa_spectrum_analyser_logs.chart_scaling_time','label' => 'Chart Scaling Time(ms)'),
                  array('key' => 'total_time', 'db' => 'enm_ulsa_spectrum_analyser_logs.total_time','label' => 'Total Time(ms)')
      );


      $where = "
enm_ulsa_spectrum_analyser_logs.siteid = sites.id AND sites.name = '$site' AND
enm_ulsa_spectrum_analyser_logs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_ulsa_spectrum_analyser_logs.serverid = servers.id";

      $pmUlsaSpectrumdailyTotals = new sqlTable("UplinkSpectrumDailyTotals",
                                        $cols,
                                        array('enm_ulsa_spectrum_analyser_logs', 'sites', 'servers'),
                                        $where,
                                        TRUE,
                                            array( 'order' => array( 'by' => 'time', 'dir' => 'ASC'),
                                                   'rowsPerPage' => 50,
                                                   'rowsPerPageOptions' => array(100,500,1000)
                                            )
                                        );

      return $pmUlsaSpectrumdailyTotals;
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;

        /* Daily Summary table */
        $dailyTotals = getDailyTotals();
        echo $dailyTotals->getTableWithHeader("Daily Totals", 2, "DDP_Bubble_394_ENM_PM_ULSA_Daily_Totals");


}

$statsDB = new StatsDB();
    mainFlow($statsDB);


include PHP_ROOT . "/common/finalise.php";
?>
