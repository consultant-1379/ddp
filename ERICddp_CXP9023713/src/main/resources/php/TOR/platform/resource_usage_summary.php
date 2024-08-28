<?php
$pageTitle = "Resource Usage Summary";

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/JsPlot.php";

function showPm15MinRopDurationGraph($start, $end, $showJsPlot = false) {
    global $site, $date;

    $sqlParam = array(
                      'title' => "PM 15MIN ROP Duration",
                      'type' => 'sb',
                      'ylabel' => "15MIN ROP Duration (sec)",
                      'useragg' => 'false',
                      'persistent' => 'false',
                      'forecelegend' => 'true',
                      'querylist' => array(
                                           array(
                                                 'timecol' => "fcs",
                                                 'whatcol' => array('duration' => 'Duration'),
                                                 'multiseries' => "servers.hostname",
                                                 'tables' => "enm_pmic_rop, sites, servers",
                                                 'where' => "
    enm_pmic_rop.siteid = sites.id AND
    sites.name = '%s' AND
    enm_pmic_rop.serverid = servers.id AND
    enm_pmic_rop.type = '15MIN'",
                                                 'qargs' => array('site')
                                                 )
                                           )
                      );

    if ( $showJsPlot ) {
        echo '<div id="pm15minropduation" style="height: 400px"></div>' . "\n";
        $jsPlot = new JsPlot();
        $jsPlot->show($sqlParam, 'pm15minropduation', array(
                                                     'tstart' => "$start 00:00:00",
                                                     'tend' => "$end 23:59:59",
                                                     'aggType' => 0,
                                                     'aggInterval' => 0,
                                                     'aggCol' => ""
                                                     )
                      );
    } else {
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        echo $sqlParamWriter->getImgURL($id, "$start 00:00:00", "$end 23:59:59", true, 1000, 400);
    }
}

function showResourceUsageGraph($title, $ylabel, $seriesfilePrefix, $showJsPlot = false, $type = 'sb') {
    global $site, $date, $rootdir, $webargs;

    $seriesfilePath = $rootdir . "/resource_usage/" . $seriesfilePrefix . "_stats_topn.json";
    $sqlParam = array(
                      'title' => $title,
                      'type' => $type,
                      'ylabel' => $ylabel,
                      'useragg' => 'false',
                      'persistent' => 'false',
                      'seriesfile' => $seriesfilePath
                      );

    if ( $showJsPlot ) {
        $divId = strtolower( preg_replace('/[^0-9A-Za-z_]+/', '', $title) );
        echo '<div id="' . $divId . '" style="height: 400px"></div>' . "\n";
        $jsPlot = new JsPlot();
        $jsPlot->show($sqlParam, $divId, NULL);
    } else {
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $imgLink = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", false, 1000, 400);
        $jsPlotUrl = '?' . $webargs . '&title=' . urlencode($title) . '&type=' . urlencode($type) .
                     '&ylabel=' . urlencode($ylabel) . '&seriesfileprefix=' .
                     urlencode($seriesfilePrefix) . '&showqplot=1';
        echo "<a href=\"$jsPlotUrl\">$imgLink</a><br/>";
    }
}

function showResUsgJsPlot($title, $ylabel, $seriesfilePrefix, $type = 'sb') {
    global $site, $date, $rootdir, $webargs;

    $seriesfilePath = $rootdir . "/resource_usage/" . $seriesfilePrefix . "_stats_topn.json";
    $sqlParam = array(
                      'title' => $title,
                      'type' => $type,
                      'ylabel' => $ylabel,
                      'useragg' => 'false',
                      'persistent' => 'false',
                      'seriesfile' => $seriesfilePath
                      );

    $divId = strtolower( preg_replace('/[^0-9A-Za-z_]+/', '', $title) );
    $jsPlot = new JsPlot();
    $resUsgPageLink = "<a href=\"?$webargs\">Resource Usage Summary</a>";
    echo "<b>Note: </b>The <b>'Per Min'</b> resource usage statistics are only shown for the " .
         "given day since the sheer amounts of data that needs to be plotted is just too high. " .
         "For resource usage analysis over multiple days please refer to the links of " .
         "<b>'Per 15 Min'</b> graphs under '$resUsgPageLink' page.<br/><br/>\n";
    echo '<div id="' . $divId . '" style="height: 600px"></div>' . "\n";
    $jsPlot->show($sqlParam, $divId, NULL);
}

function showHistorSummGraph($title, $ylabel, $seriesfilePrefix, $start, $end,
                             $jsplot = true, $height = 600, $type = 'sb') {
    global $site, $date, $rootdir, $ddp_dir, $webargs, $last_n_days, $debug;

    $filename = $rootdir . "/resource_usage/" . $seriesfilePrefix . "_analysis_last_{$last_n_days}.json";
    if ( ! file_exists($filename) ) {
        echo "<br\>";
        echo "<h3>Unable to find any pregenerated resource usage analysis graphs. So generating the " .
             "graphs from the scratch for the last {$last_n_days} days...</h3>";
        echo "<h3>Please wait as this may take some time...</h3>";
        ob_flush();
        flush();

        $opts = "--site {$site} --date {$date} --analysisOut {$rootdir}/resource_usage";
        callGenericPhpToRootWrapper( 'parseResUsgLastNDays', $opts );

        $histAnalyAllLink = '?' . $_SERVER['QUERY_STRING'];
        echo <<<EOS
<script type="text/javascript">
    window.location.href = "$histAnalyAllLink";
</script>
<noscript>
    <meta http-equiv="refresh" content="0; url='$histAnalyAllLink'">
</noscript>
EOS;
    }

    $sqlParam = array(
                      'title' => $title,
                      'type' => $type,
                      'ylabel' => $ylabel,
                      'useragg' => 'false',
                      'persistent' => 'false',
                      'seriesfile' => $filename
                      );

    if ( $jsplot ) {
        $divId = strtolower( preg_replace('/[^0-9A-Za-z_]+/', '', $title) );
        $jsPlot = new JsPlot();
        echo '<div id="' . $divId . '" style="height: ' . $height . 'px"></div>' . "\n";
        $jsPlot->show($sqlParam, $divId, NULL);
    } else {
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $imgLink = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", false, 1000, $height);
        $jsPlotUrl = '?' . $webargs . '&title=' . urlencode($title) . '&type=' . urlencode($type) .
                     '&ylabel=' . urlencode($ylabel) . '&seriesfileprefix=' .
                     urlencode($seriesfilePrefix) . '&show15minmax=1&tstart=' .
                     urlencode($start) . '&tend=' . urlencode($end);
        echo "<a href=\"$jsPlotUrl\">$imgLink</a><br/>";
    }
}

function showAllHistorSummGraphs($statsDB, $start, $end) {
    global $site, $date, $rootdir, $webargs, $last_n_days, $debug;

    # Display the page heading and purpose
    $pagePurposeText = getHelpTextFromDB('DDP_Bubble_413_ENM_Res_Usage_Last_N_Days_Purpose');
    if ( ! is_null($pagePurposeText) && $pagePurposeText != "Help description not found" ) {
        echo "<H1>Resource Usage Summary - Last {$last_n_days} day Analysis</H1>\n";
        echo "$pagePurposeText<br/>\n";
    }

    # Display 'VM IOPS' graph
    $seriesfilePrefix = "vm_iops";
    drawHeaderWithHelp("VM IOPS", 2, "vmIopsHelp", "DDP_Bubble_414_Res_Usage_Last_N_Days_VM_IOPS");
    showHistorSummGraph("VM IOPS", "Sum of Max IOPS for All VMs (per 15 Min)",
                        $seriesfilePrefix, $start, $end, false, 400);

    # Display 'VM CPU Usage' graph
    $seriesfilePrefix = "vm_cpu_usage";
    drawHeaderWithHelp("VM CPU Usage", 2, "vmCpuUsageHelp", "DDP_Bubble_415_Res_Usage_Last_N_Days_VM_CPU_Usage");
    showHistorSummGraph("VM CPU Usage", "Sum of Max CPU Usage (min) for All VMs (per 15 Min)",
                        $seriesfilePrefix, $start, $end, false, 400);

    # Display 'VM CPU Steal' graph
    $seriesfilePrefix = "vm_cpu_steal";
    drawHeaderWithHelp("VM CPU Steal", 2, "vmCpuStealHelp", "DDP_Bubble_416_Res_Usage_Last_N_Days_VM_CPU_Steal");
    showHistorSummGraph("VM CPU Steal", "Sum of Max CPU Steal (min) for All VMs (per 15 Min)",
                        $seriesfilePrefix, $start, $end, false, 400);

    # Display 'VM Memory Usage' graph
    $seriesfilePrefix = "vm_mem_usage";
    drawHeaderWithHelp("VM Memory Usage", 2, "vmMemUsageHelp", "DDP_Bubble_417_Res_Usage_Last_N_Days_VM_Memory_Usage");
    showHistorSummGraph("VM Memory Usage", "Sum of Max Memory Usage (MB) for All VMs (per 15 Min)",
                        $seriesfilePrefix, $start, $end, false, 400);

    # Display 'VM NIC Usage (RX)' graph
    $seriesfilePrefix = "vm_nic_usage_rx";
    drawHeaderWithHelp("VM NIC Usage (RX)", 2, "vmNicRxUsageHelp", "DDP_Bubble_418_Res_Usage_Last_N_Days_VM_NIC_Usage_RX");
    showHistorSummGraph("VM NIC Usage (RX)", "Sum of Max MB Received/Sec for All VMs (per 15 Min)",
                        $seriesfilePrefix, $start, $end, false, 400);

    # Display 'VM NIC Usage (TX)' graph
    $seriesfilePrefix = "vm_nic_usage_tx";
    drawHeaderWithHelp("VM NIC Usage (TX)", 2, "vmNicTxUsageHelp", "DDP_Bubble_419_Res_Usage_Last_N_Days_VM_NIC_Usage_TX");
    showHistorSummGraph("VM NIC Usage (TX)", "Sum of Max MB Transmitted/Sec for All VMs (per 15 Min)",
                        $seriesfilePrefix, $start, $end, false, 400);

    # Display 'PM 15MIN ROP Duration'
    $ropDurationRow = $statsDB->queryRow("
SELECT
    duration AS 'Duration'
FROM
    enm_pmic_rop,
    sites
WHERE
    enm_pmic_rop.siteid = sites.id AND
    enm_pmic_rop.type = '15MIN' AND
    sites.name = '$site' AND
    fcs BETWEEN '$start 00:00:00' AND '$end 23:59:59'
LIMIT 1");
    if ( ! empty($ropDurationRow) ) {
        drawHeaderWithHelp("PM 15MIN ROP Duration", 2, "pm15MinRopDurationHelp", "DDP_Bubble_420_Res_Usage_Last_N_Days_PM_15MIN_ROP_Duration");
        showPm15MinRopDurationGraph($start, $end);
    }
}

function showDailyResUsgGraphs($statsDB) {
    global $site, $date, $rootdir, $webargs, $last_n_days, $debug;

    $showJsPlot = false;
    if ( isset($_GET['showjsplot']) && $_GET['showjsplot'] ) {
        $showJsPlot = true;
    }

    # Display the page heading and purpose
    $pagePurposeText = getHelpTextFromDB('DDP_Bubble_373_ENM_Resource_Usage_Summary_Purpose');
    if ( ! is_null($pagePurposeText) && $pagePurposeText != "Help description not found" ) {
        echo "<H1>Resource Usage Summary</H1>\n";
        echo "$pagePurposeText\n";
    }

    # Display the link to the page with all historical analysis graphs
    $last_n = $last_n_days - 1;
    $start = date( 'Y-m-d', strtotime($date . "-$last_n days") );
    $end = $date;
    $histAnalyAllLink = '?' . $webargs . '&showallhistdata=1&tstart=' . urlencode($start) .
                        '&tend=' . urlencode($end);
    echo "<ul>\n";
    echo " <li><a href=\"$histAnalyAllLink\">Last {$last_n_days} Day Summary (Sum of Max per 15 Min)</a></li>\n";
    echo "</ul><br/>\n";

    # Display 'VM IOPS' graph
    $seriesfilePrefix = "vm_iops";
    if ( file_exists("{$rootdir}/resource_usage/{$seriesfilePrefix}_stats_topn.json") ) {
        drawHeaderWithHelp("VM IOPS", 2, "vmIopsHelp", "DDP_Bubble_348_Resource_Usage_Summary_VM_IOPS_V2");
        showResourceUsageGraph("VM IOPS", "Total IOPS for All VMs", $seriesfilePrefix, $showJsPlot);
    }

    # Display 'VM CPU Usage' graph
    $seriesfilePrefix = "vm_cpu_usage";
    if ( file_exists("{$rootdir}/resource_usage/{$seriesfilePrefix}_stats_topn.json") ) {
        drawHeaderWithHelp("VM CPU Usage", 2, "vmCpuUsageHelp", "DDP_Bubble_366_Resource_Usage_Summary_VM_CPU_Usage");
        showResourceUsageGraph("VM CPU Usage", "Total CPU Usage (min) for All VMs", $seriesfilePrefix, $showJsPlot);
    }

    # Display 'VM CPU Steal' graph
    $seriesfilePrefix = "vm_cpu_steal";
    if ( file_exists("{$rootdir}/resource_usage/{$seriesfilePrefix}_stats_topn.json") ) {
        drawHeaderWithHelp("VM CPU Steal", 2, "vmCpuStealHelp", "DDP_Bubble_367_Resource_Usage_Summary_VM_CPU_Steal");
        showResourceUsageGraph("VM CPU Steal", "Total CPU Steal (min) for All VMs", $seriesfilePrefix, $showJsPlot);
    }

    # Display 'VM Memory Usage' graph
    $seriesfilePrefix = "vm_mem_usage";
    if ( file_exists("{$rootdir}/resource_usage/{$seriesfilePrefix}_stats_topn.json") ) {
        drawHeaderWithHelp("VM Memory Usage", 2, "vmMemUsageHelp", "DDP_Bubble_368_Resource_Usage_Summary_VM_Memory_Usage");
        showResourceUsageGraph("VM Memory Usage", "Total Memory Usage (MB) for All VMs", $seriesfilePrefix, $showJsPlot);
    }

    # Display 'VM NIC Usage (RX)' graph
    $seriesfilePrefix = "vm_nic_usage_rx";
    if ( file_exists("{$rootdir}/resource_usage/{$seriesfilePrefix}_stats_topn.json") ) {
        drawHeaderWithHelp("VM NIC Usage (RX)", 2, "vmNicRxUsageHelp", "DDP_Bubble_402_Resource_Usage_Summary_VM_NIC_Usage_RX");
        showResourceUsageGraph("VM NIC Usage (RX)", "Total MB Received per Second for all VMs", $seriesfilePrefix, $showJsPlot);
    }
    # Display 'VM NIC Usage (TX)' graph
    $seriesfilePrefix = "vm_nic_usage_tx";
    if ( file_exists("{$rootdir}/resource_usage/{$seriesfilePrefix}_stats_topn.json") ) {
        drawHeaderWithHelp("VM NIC Usage (TX)", 2, "vmNicTxUsageHelp", "DDP_Bubble_403_Resource_Usage_Summary_VM_NIC_Usage_TX");
        showResourceUsageGraph("VM NIC Usage (TX)", "Total MB Transmitted per Second for all VMs", $seriesfilePrefix, $showJsPlot);
    }

    # Display 'PM 15MIN ROP Duration'
    $ropDurationRow = $statsDB->queryRow("
SELECT
    duration AS 'Duration'
FROM
    enm_pmic_rop,
    sites
WHERE
    enm_pmic_rop.siteid = sites.id AND
    enm_pmic_rop.type = '15MIN' AND
    sites.name = '$site' AND
    fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59'
LIMIT 1");
    if ( ! empty($ropDurationRow) ) {
        drawHeaderWithHelp("PM 15MIN ROP Duration", 2, "pm15MinRopDurationHelp", "DDP_Bubble_369_Resource_Usage_Summary_PM_15MIN_ROP_Duration");
        showPm15MinRopDurationGraph($date, $date, $showJsPlot);
    }
}

function mainFlow() {
    global $site, $date, $webargs, $last_n_days;

    $statsDB = new StatsDB();
    if ( isset($_GET['showqplot']) ) {
        showResUsgJsPlot(urldecode($_GET['title']), urldecode($_GET['ylabel']),
                         urldecode($_GET['seriesfileprefix']), urldecode($_GET['type']));
    } else if ( isset($_GET['show15minmax']) ) {
        showHistorSummGraph(urldecode($_GET['title']), urldecode($_GET['ylabel']),
                            urldecode($_GET['seriesfileprefix']), urldecode($_GET['tstart']),
                            urldecode($_GET['tend']), true, 600, urldecode($_GET['type']));
    } else if ( isset($_GET['showallhistdata']) ) {
        # Check if the user has clicked a date in the calendar under left pane and then
        # adjust the last 'n' days period accordingly
        if ( preg_match("/(\d\d\d\d-\d\d-\d\d)/", $_GET['tstart'], $tstartMatches) &&
             preg_match("/(\d\d\d\d-\d\d-\d\d)/", $_GET['tend'], $tendMatches) &&
             ($tstartMatches[1] == $tendMatches[1]) ) {
                $last_n = $last_n_days - 1;
                $start = date( 'Y-m-d', strtotime($date . "-$last_n days") );
                $end = $date;
                $histAnalyAllLink = '?' . $webargs . '&showallhistdata=1&tstart=' .
                                    urlencode($start) . '&tend=' . urlencode($end);
                echo <<<EOS
<script type="text/javascript">
    window.location.href = "$histAnalyAllLink";
</script>
<noscript>
    <meta http-equiv="refresh" content="0; url='$histAnalyAllLink'">
</noscript>
EOS;
        }
        showAllHistorSummGraphs($statsDB, urldecode($_GET['tstart']), urldecode($_GET['tend']));
    } else {
        showDailyResUsgGraphs($statsDB);
    }
}

$last_n_days = 31;
mainFlow();

include PHP_ROOT . "/common/finalise.php";
?>
