<?php
$pageTitle = "Physical Link Management";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once 'HTML/Table.php';

const ENM_PLMS_INSTR = 'enm_plms_instr';

function plmStatsTotals() {
    global $site, $date;
    $where = "enm_plms_instr.siteid = sites.id AND
              sites.name = '$site' AND
              enm_plms_instr.time BETWEEN '$date 00:00:00' AND
              '$date 23:59:59' AND
              enm_plms_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $table = SqlTableBuilder::init()
         ->name("plmstatsTotals")
         ->tables(array(ENM_PLMS_INSTR, StatsDB::SITES, StatsDB::SERVERS))
         ->where($where)
         ->addSimpleColumn("IFNULL(servers.hostname, 'Totals')", 'Instance')
         ->addSimpleColumn('ROUND(AVG(averageTimeTakenToCreateOneLink))', 'Time To Create Link(ms)')
         ->addSimpleColumn('ROUND(AVG(averageTimeTakenToDeleteOneLink))', 'Time To Delete Link(ms)')
         ->addSimpleColumn('ROUND(AVG(averageTimeTakenToImportOneFile))', 'Time To Import File(ms)')
         ->addSimpleColumn('ROUND(AVG(averageTimeTakenToListLink))', 'Time To List Link(ms)')
         ->addSimpleColumn('SUM(numberOfFailedLinkCreation)', 'Failed Link Creations' )
         ->addSimpleColumn('SUM(numberOfFailedLinkDeletion)', 'Failed Link Deletions' )
         ->addSimpleColumn('SUM(numberOfSuccessfulLinkCreation)', 'Successful Link Creations' )
         ->addSimpleColumn('SUM(numberOfSuccessfulLinkDeletion)', 'Successful Link Deletions')
         ->addSimpleColumn('SUM(numberOfSuccessfulLinkListed)', 'Successful Link Listed')
         ->addSimpleColumn('SUM(totalNumberOfCreateRequests)', 'Link Creation requests')
         ->addSimpleColumn('SUM(totalNumberOfDeleteRequests)', 'Link Deletion requests')
         ->addSimpleColumn('SUM(totalNumberOfImportFileRequests)', 'Import File Requests')
         ->addSimpleColumn('SUM(totalNumberOfImportLinkRequests)', 'No of Links To Import')
         ->addSimpleColumn('SUM(totalNumberOfListRequests)', 'Link List Requests')
         ->build();
    echo $table->getTableWithHeader("Physical Link Management statistics", 2, "", "", "PLMS_TOTALS");
}

function notificationStatistics() {
    $table = new ModelledTable( 'TOR/cm/enm_ald_management_statistics', 'aldNotifications' );
    echo $table->getTableWithHeader('TCIM / Alarm Notification and Alarm Link Count Statistics');
}

function plmStatsgraph() {
    global $date, $site;
    drawHeaderWithHelp("PLM Link Statistics", 2, "plm_stats");

    $sqlParamWriter = new SqlPlotParam();
    $columns = array('numberOfFailedLinkCreation' => 'Number Of Failed Link Creation',
                    'numberOfFailedLinkDeletion' => 'Number Of Failed Link Deletion',
                    'numberOfSuccessfulLinkCreation' => 'Number Of Successful Link Creation',
                    'numberOfSuccessfulLinkDeletion'=> 'Number Of Successful Link Deletion',
                    'numberOfSuccessfulLinkListed' => 'Number Of Successful Link Listed');
    $dbTables = array( ENM_PLMS_INSTR, StatsDB::SITES, StatsDB::SERVERS );
    $where = "enm_plms_instr.siteid = sites.id AND
    sites.name = '%s' AND enm_plms_instr.serverid = servers.id";

    foreach ( $columns as $column => $title ) {
        $sqlParam = SqlPlotParamBuilder::init()
                    ->title($title)
                    ->type(SqlPlotParam::STACKED_BAR)
                    ->barwidth(60)
                    ->yLabel("count")
                    ->forcelegend("true")
                    ->addQuery(
                        SqlPlotParam::DEFAULT_TIME_COL,
                        array($column => $title),
                        $dbTables,
                        $where,
                        array('site'),
                        "servers.hostname"
                    )
                    ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
    }
}

function notificationGraphs() {
    $instances = getInstances(ENM_PLMS_INSTR);

    drawHeader('Notification Statistics', 1, 'aldLinkStatistics');
    $aldLinkStatistics = new ModelledGraphSet('TOR/cm/enm_ald_link_statistics');
    drawGraphGroupByInstance($aldLinkStatistics->getGroup("ald"), $instances);

    $plmLinkStatistics = new ModelledGraphSet('TOR/cm/enm_plms_statistics');

    drawHeader('PLM Discovery State Link Statistics', 1, 'aldPLMDiscoveryStateLinkStatistics');
    drawGraphGroupByInstance($plmLinkStatistics->getGroup("PLMDiscoveryStateLinkStatistics"), $instances);

    drawHeader('PLM Link-State Link Statistics', 1, 'aldPLMLinkStateLinkStatistics');
    drawGraphGroupByInstance($plmLinkStatistics->getGroup("PLMLinkStateLinkStatistics"), $instances);

    drawHeader('PLM Link Type Link Statistics', 1, 'aldPLMLinkTypeLinkStatistics');
    drawGraphGroupByInstance($plmLinkStatistics->getGroup("PLMLinkTypeLinkStatistics"), $instances);

    $plmLinkStatisticsgraph = new ModelledGraph('TOR/cm/enm_plms_links');
    drawHeader('PLM Number of Links Statistics', 1, 'aldPLMNumberofLinksStatistics');
    $graph = $plmLinkStatisticsgraph->getImage();
    plotgraphs( array( $graph ) );
}

function mainFlow() {
    global $statsDB;

    $notificationData = '(enm_plms_instr.totalNumberOfCreateNotifications IS NOT NULL OR
        enm_plms_instr.totalNumberOfDeleteNotifications IS NOT NULL OR enm_plms_instr.totalNumberOfUpdateNotifications OR
        enm_plms_instr.totalNumberOfAlarmNotifications IS NOT NULL OR enm_plms_instr.totalNumberOfLinkAlarms)';
    $aldNotificationData = $statsDB->hasData(ENM_PLMS_INSTR, 'time', false, $notificationData);

    $modelURLs[] = makeAnchorLink("plmstatsTotals", "Physical Link Management statistics");
    $modelURLs[] = makeAnchorLink("plm_stats", "PLM Link Statistics");
    if ( $aldNotificationData ) {
        $modelURLs[] = makeAnchorLink("aldNotifications", 'TCIM/Alarm Notification and Alarm Link Count Statistics');
        $modelURLs[] = makeAnchorLink("aldLinkStatistics", "Notification Statistics");
        $modelURLs[] = makeAnchorLink("aldPLMDiscoveryStateLinkStatistics", "PLM Discovery State Link Statistics");
        $modelURLs[] = makeAnchorLink("aldPLMLinkStateLinkStatistics", "PLM Link State Link Statistics");
        $modelURLs[] = makeAnchorLink("aldPLMLinkTypeLinkStatistics", "PLM Link Type Link Statistics");
        $modelURLs[] = makeAnchorLink("aldPLMNumberofLinksStatistics", "PLM Number of Links Statistics");
    }
    echo makeHTMLList($modelURLs);

    plmStatsTotals();
    plmStatsgraph();
    if ( $aldNotificationData ) {
        notificationStatistics();
        notificationGraphs();
    }
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
