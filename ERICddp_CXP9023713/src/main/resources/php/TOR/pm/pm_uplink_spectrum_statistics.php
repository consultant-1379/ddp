<?php
$pageTitle = "UplinkSpectrum FileCollection";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

$TABLE_PMSERV_INSTR = "enm_pmserv_uplink_instr";
const CLI_REQUEST = "cliRequest";
const REST_REQUEST = "restRequest";
const SCHEDULED_REQUEST = "scheduledRequest";
const SNAPSHOT = "Snapshot";
const SNAPSHOT_FAILED = "Snapshot Failed";
const CONTINUOUS = "Continuous";
const CONTINUOUS_FAILED = "Continuous failed";
const FILES_FAILED = "numberOfFilesFailed";
const FILES_RECOVERED = "numberOfFilesRecovered";
const TOTALCOUNT = "Count";
const BORDER = "border=0";


function params($type) {
    if ($type === CLI_REQUEST) {
        return array(
                    'numberOfCliStartSnapshotRequests' => SNAPSHOT,
                    'numberOfCliStartSnapshotFailedRequests' => SNAPSHOT_FAILED,
                    'numberOfCliStartContinuousRequests' => CONTINUOUS,
                    'numberOfCcliStartContinuousFailedRequests' => CONTINUOUS_FAILED,
                    'numberOfCliStartConditionalRequests' => 'Conditional',
                    'numberOfCliStartConditionalFailedRequests' =>  'Conditional failed',
                    'numberOfCliStartScheduledRequests' => 'Scheduled',
                    'numberOfCliStartScheduledFailedRequests' => 'Scheduled failed'
                );
    }
    if ($type === REST_REQUEST) {
        return array(
                    'numberOfRestStartSnapshotRequests' => SNAPSHOT,
                    'numberOfRestStartSnapshotFailedRequests' => SNAPSHOT_FAILED,
                    'numberOfRestStartContinuousRequests' => CONTINUOUS,
                    'numberOfRestStartContinuousFailedRequests' => CONTINUOUS_FAILED,
                    'numberOfRestStartConditionalRequests' => 'Conditional',
                    'numberOfRestStartConditionalFailedRequests' => 'Conditional failed',
                    'numberOfRestStartScheduledRequests' => 'Scheduled',
                    'numberOfRestStartScheduledFailedRequests' => 'Scheduled failed'
               );
    }
    if ($type === SCHEDULED_REQUEST) {
        return array(
                    'numberOfScheduledStartSnapshotRequests' => SNAPSHOT,
                    'numberOfScheduledStartSnapshotFailedRequests' => SNAPSHOT_FAILED,
                    'numberOfScheduledStartSnapshotDiscardedRequests' => 'Snapshot discarded',
                    'numberOfScheduledStartContinuousRequests' => CONTINUOUS,
                    'numberOfScheduledStartContinuousFailedRequests' => CONTINUOUS_FAILED,
                    'numberOfScheduledStartContinuousDiscardedRequests' => 'Continuous discarded'
               );
    }
}

function getDailyTotals()
{
    global $site,$date,$webargs;

    $cols = array(

                  array('key' => 'inst', 'db' => 'servers.hostname','label' => 'Instance'),
                  array('key' => 'averageCollectionDuration', 'db' => 'AVG(enm_pmserv_uplink_instr.averageCollectionDuration)', 'label' => 'Average Collection Duration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'collectionDuration', 'db' => 'SUM(enm_pmserv_uplink_instr.collectionDuration)','label' => 'Collection Duration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'maximumCollectionDuration', 'db' => 'MAX(enm_pmserv_uplink_instr.maximumCollectionDuration)','label' => 'Maximum Collection Duration', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'numberOfFileTransferNotifications', 'db' => 'SUM(enm_pmserv_uplink_instr.numberOfFileTransferNotifications)', 'label' => 'Number Of File Transfer Notifications', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'numberOfFilesCollected', 'db' => 'SUM(enm_pmserv_uplink_instr.numberOfFilesCollected)', 'label' => 'Number Of Files Collected', 'formatter' => 'ddpFormatNumber'),
                  array('key' => FILES_FAILED, 'db' => 'SUM(enm_pmserv_uplink_instr.numberOfFilesFailed)',
                  'label' => 'Number Of Files Failed', 'formatter' => 'ddpFormatNumber'),
                  array('key' => FILES_RECOVERED, 'db' => 'SUM(enm_pmserv_uplink_instr.numberOfFilesRecovered)',
                  'label' => 'Number Of Files Recovered', 'formatter' => 'ddpFormatNumber')

      );

      $where = "
enm_pmserv_uplink_instr.siteid = sites.id AND sites.name = '$site' AND
enm_pmserv_uplink_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_pmserv_uplink_instr.serverid = servers.id
GROUP BY servers.hostname WITH ROLLUP";

      $pmUlsadailyTotals = new sqlTable("UplinkDailyTotals",
                                        $cols,
                                        array('enm_pmserv_uplink_instr', 'sites', 'servers'),
                                        $where,
                                        TRUE
                                        );

      return $pmUlsadailyTotals;
}

function getPmUlsaErrorMsgs()
{
    global $site,$date,$webargs;

    $cols = array(
        array('key' => 'errorMsg', 'db' => 'pm_uplink_errors.errorMsg', 'label' => 'ERROR'),
        array('key' => 'errorCount', 'db' => 'pm_uplink_errors.errorCount','label' => 'COUNT')
    );

    $where = "
pm_uplink_errors.siteid = sites.id AND sites.name = '$site' AND
pm_uplink_errors.date = '$date'";

    $pmUlsaErrorMsgsTable = new sqlTable("PmUlsaErrorMessages",
                                          $cols,
                                          array('pm_uplink_errors','sites'),
                                          $where,
                                          TRUE,
                                          array( 'order' => array( 'by' => 'errorCount', 'dir' => 'DESC'),

                                                  'rowsPerPage' => 25,

                                                  'rowsPerPageOptions' => array(50, 100, 1000, 10000)

                                               )
                                        );
    return $pmUlsaErrorMsgsTable;
}

function getPmUlsaErroredRes()
{
    global $site,$date,$webargs;

    $cols = array(
        array('key' => 'resourceName', 'db' => 'pm_uplink_errored_res.resourceName', 'label' => 'Resource',
        'formatter' => 'ddpFormatNumber'),
        array('key' => 'resCount', 'label' => TOTALCOUNT, 'db' => 'pm_uplink_errored_res.resCount',
        'formatter' => 'ddpFormatNumber')
    );

    $where = "
pm_uplink_errored_res.siteid = sites.id AND sites.name = '$site' AND
pm_uplink_errored_res.date = '$date'";

    $pmUlsaErroredResTable = new sqlTable("PmUlsaErroredResources",
                                          $cols,
                                          array('pm_uplink_errored_res','sites'),
                                          $where,
                                          TRUE,
                                          array( 'order' => array( 'by' => 'resCount', 'dir' => 'DESC'),

                                                  'rowsPerPage' => 25,

                                                  'rowsPerPageOptions' => array(50, 100, 1000, 10000)

                                               )
                                          );
    return $pmUlsaErroredResTable;
}

function showPmUlsaCollectionErrors() {
    global $debug, $webargs, $php_webroot;

    $MsgsURL = $_SERVER['PHP_SELF'] . "?" . $webargs;
    echo "<a href=\"$MsgsURL\">Return to Uplink Spectrum File Collection Summary</a>\n";

    /* Uplink Spectrum File Collection Error Messages */
    $pmUlsaErrorMsgsTable = getPmUlsaErrorMsgs();
    echo $pmUlsaErrorMsgsTable->getTableWithHeader("Uplink Spectrum File Collection Error Messages",2, "DDP_Bubble_304_ENM_PM_Uplink_Error_Msgs");

    /* Uplink Spectrum File Collection Errored Resources */

    $pmUlsaErroredResTable = getPmUlsaErroredRes();
    echo $pmUlsaErroredResTable->getTableWithHeader("Uplink Spectrum File Collection Errored Resources", 2, "DDP_Bubble_305_ENM_PM_Uplink_Errored_Res");
}

function showPmServUplinkInstr()
{
    global $date, $site;

    $graphTable = new HTML_Table(BORDER);

    $sqlParamWriter = new SqlPlotParam();

    drawHeaderWithHelp("Uplink Spectrum File Collection Stats", 2, "pmUplinkInstrGraphHelp","DDP_Bubble_303_ENM_PM_Uplink_Instr_Graphs");

    $instrGraphParams = array(
                'averageCollectionDuration' => array(
                'title'  => 'Duration',
                'ylabel' => 'ms',
                'cols' => array('averageCollectionDuration'=>'Duration'),
                ),
                'numberOfFilesCollected' => array(
                'title' => 'Files Collected',
                'ylabel' => 'count',
                'cols' => array('numberOfFilesCollected'=>'Filescollected'),
                ),
                FILES_FAILED => array(
                'title' => 'Files Failed',
                'ylabel' => TOTALCOUNT,
                'cols' => array(FILES_FAILED=>'Filesfailed'),
                ),
                FILES_RECOVERED => array(
                'title' => 'Files Recovered',
                'ylabel' => TOTALCOUNT,
                'cols' => array(FILES_RECOVERED=>'Files Recovered')
                )

    );

  foreach ( $instrGraphParams as $instrParams ) {
        $row = array();
        $sqlParam = array(
                'title' => $instrParams['title'],
                'ylabel' => $instrParams['ylabel'],
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => 'sb',
                'sb.barwidth' => 60,
                'forcelegend' => 'true',
                'querylist' => array(
                      array (
                        'timecol' => 'time',
                        'whatcol' => $instrParams['cols'],
                        'multiseries' => 'servers.hostname',
                        'tables' => "enm_pmserv_uplink_instr, sites, servers",
                        'where' => "enm_pmserv_uplink_instr.siteid = sites.id AND sites.name = '%s' AND enm_pmserv_uplink_instr.serverid = servers.id",
                'qargs' => array( 'site' )
                )
            )
         );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400);
        $graphTable->addRow($row);
   }
        echo $graphTable->toHTML();
}

function showULSATable($statsDB) {
    global $date,$site,$webargs;
    $where = "
enm_pmic_rop_ulsa.siteid = sites.id AND sites.name = '$site' AND
enm_pmic_rop_ulsa.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_pmic_rop_ulsa.netypeid = ne_types.id AND
enm_pmic_rop_ulsa.neid = enm_ne.id AND
enm_ne.siteid = sites.id AND
enm_ne.netypeid = ne_types.id
GROUP BY ne_types.id, enm_ne.id, radiounit, port";

 $table =
   new SqlTable(
       "file_collection_port",
       array(
           array( 'key' => 'id', 'db' => 'CONCAT(ne_types.id,":",enm_ne.id,":",
                                   radiounit,":",rfport)', 'visible' => false, 'label' => 'key' ),
           array( 'key' => 'ne', 'db' => 'enm_ne.name', 'label' => 'Network Element' ),
           array( 'key' => 'netype', 'db' => 'ne_types.name', 'label' => 'NE Type' ),
           array( 'key' => 'radiounit', 'db' => 'enm_pmic_rop_ulsa.radiounit',
                           'label' => 'Radio Unit' ),
           array( 'key' => 'port', 'db' => 'enm_pmic_rop_ulsa.rfport', 'label' => 'Port' ),
           array( 'key' => 'files', 'db' => 'COUNT(*)', 'label' => 'Files' ),
       ),
       array( 'enm_pmic_rop_ulsa', 'sites', 'enm_ne', 'ne_types' ),
       $where,
       TRUE,
       array(
           'ctxMenu' => array('key' => "fls",
                              'multi' => true,
                              'menu' => array( 'plot' => 'Plot' ),
                              'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                              'col' => 'id'
                        )
       )
   );
    echo $table->getTableWithHeader("Uplink Spectrum File Collection for port", 2, "", "", "file_collection_port");
}

function plotULSA($statsDB, $selectedStr) {
    global $date,$site,$webargs;

    $graphTable = new HTML_Table(BORDER);
    $instrGraphParams = array(
            'files' => array(
                'title' => 'Files',
                'type' => 'sb',
                'cols' => array(
                'count(*)'  => 'Files'
            )
        )
    );

    $where = "
enm_pmic_rop_ulsa.siteid = sites.id AND sites.name = '%s' AND
enm_pmic_rop_ulsa.netypeid = ne_types.id AND
enm_pmic_rop_ulsa.neid = enm_ne.id AND
enm_ne.siteid = sites.id AND
enm_ne.netypeid = ne_types.id AND
CONCAT(enm_pmic_rop_ulsa.netypeid,':',enm_pmic_rop_ulsa.neid,':',radiounit,':',rfport) IN ( %s )
GROUP BY xaxis, ne_types.id, enm_ne.id, enm_pmic_rop_ulsa.radiounit, enm_pmic_rop_ulsa.rfport ";
    $quotedIds = "'" . implode("','",explode(",",$selectedStr)) . "'";
    $sqlParamWriter = new SqlPlotParam();
    foreach ( $instrGraphParams as $instrGraphParam ) {
        $row = array();
        $sqlParam = array(
            'title' => $instrGraphParam['title'],
            'type' => $instrGraphParam['type'],
            'ylabel' => "",
            'useragg' => false,
            'persistent' => false,
            'forcelegend'=> 'true',
            'querylist' => array(
                array(
                    'timecol' => 'fcs',
                    'multiseries'=> 'CONCAT(ne_types.name,":",enm_ne.name,":",radiounit,":",rfport)',
                    'whatcol' => $instrGraphParam['cols'],
                    'tables'  => "enm_pmic_rop_ulsa, enm_ne, ne_types, sites",
                    'where'   => $where,
                    'qargs'   => array( 'site', 'ids' ),
                )
            )
        );

        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL( $id,
                                             "$date 00:00:00", "$date 23:59:59",
                                             true,
                                             800, 300,
                                             "&ids=" . $quotedIds
        );
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function drawTable($params, $statsDB, $title, $type) {
       global $TABLE_PMSERV_INSTR;
            $where =  $statsDB->where($TABLE_PMSERV_INSTR) .
                              'AND enm_pmserv_uplink_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP';
            $table = SqlTableBuilder::init()
                  ->name($TABLE_PMSERV_INSTR.$type)
                 ->tables(array($TABLE_PMSERV_INSTR, StatsDB::SITES, StatsDB::SERVERS))
                  ->where($where)
                  ->addSimpleColumn( "IFNULL(servers.hostname, 'Totals')", 'Instance');

            foreach ($params as $key => $value) {
                        $table->addSimpleColumn("SUM($key)", $value);
            }

            echo $table->build()->getTableWithHeader("$title", 2, "", "", "$type");
}
function instrGraphs() {

    showGraphs(
        'Start requests from CLI',
        array(
            array('CLI Snapshot' => 'numberOfCliStartSnapshotRequests',
                  'CLI Snapshot Failed' => 'numberOfCliStartSnapshotFailedRequests'),
            array('CLI Continuous' => 'numberOfCliStartContinuousRequests',
                  'CLI Continuous Failed' => 'numberOfCcliStartContinuousFailedRequests'),
            array('CLI Conditional' => 'numberOfCliStartConditionalRequests',
                  'CLI Conditional Failed' => 'numberOfCliStartConditionalFailedRequests'),
            array('CLI Scheduled' => 'numberOfCliStartScheduledRequests',
                  'CLI Scheduled Failed' => 'numberOfCliStartScheduledFailedRequests')

        ),
        2,
        'clirequests'
    );
    showGraphs(
        'Start requests from Rest interface',
        array(
            array('Rest Snapshot' => 'numberOfRestStartSnapshotRequests',
                  'Rest Snapshot Failed' => 'numberOfRestStartSnapshotFailedRequests'),
            array('Rest Continuous' => 'numberOfRestStartContinuousRequests',
                  'Rest Continuous Failed' => 'numberOfRestStartContinuousFailedRequests'),
            array('Rest Conditional' => 'numberOfRestStartConditionalRequests',
                   'Rest Conditional Failed' => 'numberOfRestStartConditionalFailedRequests'),
            array('Rest Scheduled' => 'numberOfRestStartScheduledRequests',
                  'Rest Scheduled Failed' => 'numberOfRestStartScheduledFailedRequests')
        ),
        2,
        'restrequests'
    );
    showGraphs(
        'Start requests from Scheduled',
        array(
            array('Scheduled Snapshot' => 'numberOfScheduledStartSnapshotRequests',
                  'Scheduled Snapshot Failed' => 'numberOfScheduledStartSnapshotFailedRequests',
                  'Scheduled Snapshot Discarded' => 'numberOfScheduledStartSnapshotDiscardedRequests'),
            array('ScheduledContinuous' => 'numberOfScheduledStartContinuousRequests',
                  'Scheduled Continuous Failed' => 'numberOfScheduledStartContinuousFailedRequests',
                  'Scheduled Continuous Discarded' => 'numberOfScheduledStartContinuousDiscardedRequests')
        ),
        3,
        'scheduledrequests'
    );
    showGraphs(
        'Start Sampling Request Average Duration',
        array(
             array('Average Duration' => 'averageStartCommandDuration')
        ),
        1,
        'averageDuration'
    );
    showGraphs(
        'Start Sampling Request Max Duration',
        array(
            array('Max Duration' => 'maximumStartCommandDuration')
        ),
        1,
        'maximumDuration'
    );
    showGraphs(
        'Start Sampling Request Min Duration',
        array(
            array('Min Duration' => 'minimumStartCommandDuration')
        ),
        1,
        'minimumDuration'
    );

}
function showGraphs($sectionTitle, $graphParams, $colCount, $helpBubbleName) {
    global $date, $TABLE_PMSERV_INSTR;

    if ( $colCount == 3 ) {
        $width = 400;
    } else {
        $width = 600;
    }

    drawHeaderWithHelp($sectionTitle, 2, $helpBubbleName);

    $sqlParamWriter = new SqlPlotParam();

    $graphTable = new HTML_Table(BORDER);
    $where = "$TABLE_PMSERV_INSTR.siteid = sites.id AND sites.name = '%s' AND
              $TABLE_PMSERV_INSTR.serverid = servers.id";
    $dbTables = array( $TABLE_PMSERV_INSTR, StatsDB::SITES, StatsDB::SERVERS );
    foreach ( $graphParams as $graphRow ) {
        $row = array();
        $ylabel = "Count";
        foreach ( $graphRow as $title => $column ) {
            $sqlParam = SqlPlotParamBuilder::init()
                      ->title($title)
                      ->type(SqlPlotParam::STACKED_BAR)
                      ->yLabel($ylabel)
                      ->forceLegend()
                      ->makePersistent()
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array( $column => $title ),
                          $dbTables,
                          $where,
                          array('site'),
                          SqlPlotParam::SERVERS_HOSTNAME
                      )
                      ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, $width, 320);
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;
    $uplinkDailyTotals = makeAnchorLink('UplinkDailyTotals_header_anchor', "Uplink Spectrum File
        Collection" );
    $uplinkCollectionStats = makeAnchorLink('pmUplinkInstrGraphHelp_anchor', "Uplink Spectrum File
        Collection Stats" );
    $uplinkCLIrequest = makeAnchorLink('enm_pmserv_uplink_instrcliRequest_anchor', "Uplink Spectrum
        Start Requests from CLI" );
    $uplinkRestrequest = makeAnchorLink('enm_pmserv_uplink_instrrestRequest_anchor', "Uplink Spectrum
        Start Requests from Rest Interface" );
    $uplinkScheduledrequest = makeAnchorLink('enm_pmserv_uplink_instrscheduledRequest_anchor', "Uplink Spectrum
        Start Requests from Scheduled"
    );
    $cliSnapshot = makeAnchorLink('clirequests_anchor', "Start requests from CLI" );
    $restSnapshot = makeAnchorLink('restrequests_anchor', "Start requests from Rest interface" );
    $scheduledSnapshot = makeAnchorLink('scheduledrequests_anchor', "Start requests from Scheduled"
    );
    $avgDuration = makeAnchorLink('averageDuration_anchor', "Start Sampling Request Average Duration" );
    $maxDuration = makeAnchorLink('maximumDuration_anchor', "Start Sampling Request Max Duration" );
    $minDuration = makeAnchorLink('minimumDuration_anchor', "Start Sampling Request Min Duration" );

    echo makeHTMLList(array($uplinkDailyTotals, $uplinkCollectionStats, $uplinkCLIrequest, $uplinkRestrequest,
        $uplinkScheduledrequest, $cliSnapshot, $restSnapshot, $scheduledSnapshot, $avgDuration,
            $maxDuration, $minDuration ));


         /* Daily Summary table */
        $dailyTotals = getDailyTotals();
        echo $dailyTotals->getTableWithHeader(
            "Uplink Spectrum File Collection",
            2,
            "DDP_Bubble_302_ENM_PM_Uplink_Daily_Totals"
        );


        echo "<ul>\n";
        $MsgsURL = $_SERVER['PHP_SELF'] . "?" . $webargs . "&showPmUlsaCollectionErrors=1";
        echo " <li><a href=\"$MsgsURL\">Uplink Spectrum File Collection Error Summary</a></li>\n";
        echo "  </ul>\n";

        $row = $statsDB->queryRow("
SELECT COUNT(*) FROM enm_pmic_rop_ulsa, sites
WHERE
 enm_pmic_rop_ulsa.siteid = sites.id AND sites.name = '$site' AND
 enm_pmic_rop_ulsa.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

        $hasULSAFileCollectionStats = $row[0] > 0;

        if ( $hasULSAFileCollectionStats ) {
            showULSATable($statsDB);
           echo "<br/><br/>\n";
        }
        showPmServUplinkInstr();
        $params = params(CLI_REQUEST);
        drawTable($params, $statsDB, 'Uplink Spectrum Start Requests from CLI', CLI_REQUEST);
        $params = params(REST_REQUEST);
        drawTable($params, $statsDB, 'Uplink Spectrum Start Requests from Rest Interface', REST_REQUEST);
        $params = params(SCHEDULED_REQUEST);
        drawTable($params, $statsDB, 'Uplink Spectrum Start Requests from Scheduled', SCHEDULED_REQUEST);
        instrGraphs();
}

$statsDB = new StatsDB();
if (isset($_GET['showPmUlsaCollectionErrors'])) {
    showPmUlsaCollectionErrors();
} else if ( isset($_REQUEST['fls']) ) {
    plotULSA($statsDB, $_REQUEST['selected']);
} else {
    mainFlow($statsDB);
}

include PHP_ROOT . "/common/finalise.php";
?>
