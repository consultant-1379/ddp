<?php
$pageTitle = "PM Serv";

include_once "../../common/init.php";
include_once "pmFunctions.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const TITLE = 'title';
const FILES = 'Files';
const SERV_LIST = 'servers';
const FILES_SUCC = 'files_succ';
const FILES_FAIL = 'files_fail';
const HAS_DATA = 'HasData';
const ROP_TYPE = 'RopType';
const ROP_NAME = 'RopName';
const FILES_COLL = 'Files Collected';
const FILES_FAILED = 'Files Failed';
const ROP_TYPE_REGEX = '/(?<=[0-9])(?=[a-z]+)/i';
const SUBS = 'subscriptions';
const PMIC_ROP = 'enm_pmic_rop';
const FDS_TITLE = 'File Deletion Statistics';
const OFDS_TITLE = 'Orphan File Deletion Statistics';
const FLS_FDS_TITLE = 'FLSDB File Deletion Statistics';
const FDS = 'fileDeletionStats';
const OFDS = 'orphanFileDeletionStats';
const FLS_FDS = 'fileDeletionStatsFLS';
const LBN = 'largeBscNodes';

function combinedFileCollectionParams() {
    return array(
            array(
                TITLE => 'Data Volume Transferred (MB)',
                'cols' => array( 'mb_txfr' => 'MB' )
            ),
            array(
                TITLE => FILES_COLL,
                'cols' => array( FILES_SUCC => FILES_COLL )
            ),
            array(
                TITLE => FILES_FAILED,
                'cols' => array( FILES_FAIL => FILES_FAILED )
            ),
            array(
                TITLE => 'Data Volume Stored (MB)',
                'cols' => array( 'mb_stor' => 'MB' )
            )
    );
}

function pmServInstrParams( $baseParams ) {
    $additional = array(
            TITLE => 'Duration (s)', 'type' => 'tsc',
            'cols' => array( 'duration'  => 'Duration' )
    );
    array_unshift($baseParams, $additional);

    return $baseParams;
}

function ropTotalsPArams() {
    return array(
        "IFNULL(servers.hostname,'Totals')" => 'PMServ Instance',
        'ROUND(AVG(duration),0)' => 'Avg Duration (s)',
        'MAX(duration)' => 'Max Duration (s)',
        'SUM(files_succ)' => FILES_COLL,
        'SUM(files_fail)' =>  FILES_FAILED,
        'ROUND( SUM(mb_txfr) / 1024, 1)' => 'GB Transferred',
        'ROUND( SUM(mb_stor) / 1024, 1)' => 'GB Stored'
    );
}

function getRopTotals( $ropType ) {
    global $statsDB;
    $params = ropTotalsPArams();

    $where = $statsDB->where(PMIC_ROP, 'fcs');
    $where .= " AND enm_pmic_rop.type = '$ropType' AND
              enm_pmic_rop.serverid = servers.id
              GROUP BY servers.hostname WITH ROLLUP";

    $tables = array( PMIC_ROP, StatsDB::SITES, StatsDB::SERVERS );

    $table = SqlTableBuilder::init()
                ->name("pmicFileCollection_" . $ropType)
                ->tables($tables)
                ->where($where);

    foreach ( $params as $key => $value ) {
        $table->addSimpleColumn($key, $value);
    }

    return $table->build();
}

function getPMErrorTable($name, $table, $cols) {
    global $statsDB;

    $where = $statsDB->where($table, 'date', true);

    return SqlTableBuilder::init()
        ->name($name)
        ->tables(array( $table, StatsDB::SITES  ))
        ->where($where)
        ->addSimpleColumn( $cols[0], $cols[1] )
        ->addSimpleColumn( $cols[2], $cols[3] )
        ->paginate()
        ->build();
}

function showPmErrors() {

    /* Pm Error Table */
    $table = getPMErrorTable( 'PmErrors', 'pm_errors', array('errorMsg', 'Error', 'errorCount', 'Count') );
    echo $table->getTableWithHeader("PM Error Messages", 1, '');

    /*Pm Error Node */
    $table = getPMErrorTable( 'PmNodes', 'pm_error_nodes', array('nodeName', 'NodeName', 'nodeCount', 'NodeCount'));
    echo $table->getTableWithHeader("Failed Nodes", 1, '');
}

function showCombinedFileCollection() {
    global $date;

    drawHeader('Combined File Collection Instrumentation', 1, 'combinedFileCollectionInstr');

    $params = combinedFileCollectionParams();

    $sqlParamWriter = new SqlPlotParam();

    $where = "enm_pmic_filecollection.siteid = sites.id AND sites.name = '%s' AND
              enm_pmic_filecollection.serverid = servers.id";

    $graphs = array();

    foreach ( $params as $param ) {
        $sqlParam = SqlPlotParamBuilder::init()
              ->title($param[TITLE])
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel('')
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $param['cols'],
                  array('enm_pmic_filecollection', StatsDB::SITES, StatsDB::SERVERS),
                  $where,
                  array('site'),
                  'servers.hostname'
                  )
              ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 300 );
    }

    plotGraphs($graphs);
}

function showPmServInstr($ropType) {
    global $date;

    drawHeader('ROP Stats', 2, 'PmicRopStats');
    $graphs = array();
    $baseParams = combinedFileCollectionParams();
    $params = pmServInstrParams($baseParams);
    $sqlParamWriter = new SqlPlotParam();
    $where = "enm_pmic_rop.siteid = sites.id AND sites.name = '%s' AND
              enm_pmic_rop.serverid = servers.id AND enm_pmic_rop.type = '%s'";

    foreach ( $params as $param ) {
        $sqlParam = SqlPlotParamBuilder::init()
              ->title($param[TITLE])
              ->yLabel('')
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  'fcs',
                  $param['cols'],
                  array(PMIC_ROP, StatsDB::SITES, StatsDB::SERVERS),
                  $where,
                  array('site', 'roptype'),
                  'servers.hostname'
              )
              ->build();

        if ( array_key_exists('type', $param) ) {
            $sqlParam['type'] = $param['type'];
        } else {
            $sqlParam['type'] = 'sb';
            $sqlParam['sb.barwidth'] = 900;
        }

        $id = $sqlParamWriter->saveParams($sqlParam);
        $endArgs = "roptype=" . $ropType;
        $graphs[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 300, $endArgs );
    }
    plotGraphs($graphs);
}

function showFlsTable($rop, $transferType) {
    global $date,$site,$webargs;
    drawHeader ("PULL", 2, "pullbased");
    $where = "
enm_pmic_rop_fls.siteid = sites.id AND sites.name = '$site' AND
enm_pmic_rop_fls.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_pmic_rop_fls.rop = '$rop' AND
enm_pmic_rop_fls.transfertype = '$transferType' AND
enm_pmic_rop_fls.netypeid = ne_types.id AND
enm_pmic_rop_fls.datatypeid = enm_pmic_datatypes.id
GROUP BY ne_types.id, enm_pmic_datatypes.id";

    $url = fromServer(PHP_SELF) . "?" . $webargs . "&rop=$rop&transfertype=$transferType";

    return SqlTableBuilder::init()
        ->name("fls_" . $rop . $transferType)
        ->tables(array( 'enm_pmic_rop_fls', StatsDB::SITES, 'enm_pmic_datatypes', 'ne_types' ) )
        ->where($where)
        ->addHiddenColumn( 'id', 'CONCAT(ne_types.id,":",enm_pmic_datatypes.id)' )
        ->addSimpleColumn( 'ne_types.name', 'NE Type' )
        ->addSimpleColumn( 'enm_pmic_datatypes.name', 'Data Type' )
        ->addSimpleColumn( 'SUM(files)', 'Files' )
        ->addSimpleColumn( 'SUM(volumekb)', 'Stored Volume(KB)' )
        ->addSimpleColumn( 'SUM(outside)', 'File Outside ROP' )
        ->ctxMenu(
            'fls',
            true,
            array( 'plot' => 'Plot'),
            $url,
            'id'
          )
        ->build();
}

function getRopName($ropType) {
    if ($ropType == '60MIN' || $ropType == '720MIN' || $ropType == '1440MIN' ) {
        return preg_split(ROP_TYPE_REGEX, $ropType)[0]/60 . ' Hour ROP';
    } else {
        return preg_split(ROP_TYPE_REGEX, $ropType)[0] . " " . ucwords(strtolower(
                         preg_split(ROP_TYPE_REGEX, $ropType)[1])) . ' ROP';
    }
}

function getFlsRopTypes($statsDB) {
    global $date, $site;

    $flsRopTypes = array();
    $statsDB->query("
SELECT
    DISTINCT rop, transfertype
FROM
    enm_pmic_rop_fls, sites
WHERE
    enm_pmic_rop_fls.siteid = sites.id AND sites.name = '$site' AND
    enm_pmic_rop_fls.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_pmic_rop_fls.transfertype = 'PULL'
ORDER BY rop, transfertype");

    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( ! array_key_exists($row['rop'], $flsRopTypes) ) {
            $flsRopTypes[$row['rop']] = array();
        }
        $flsRopTypes[$row['rop']][] = $row['transfertype'];
    }
    return $flsRopTypes;
}

function showRopTables( $ropData, $flsRopTypes ) {
    foreach ( $ropData as $data ) {
        if ( $data[HAS_DATA] == 1 ) {
            $ropType = $data[ROP_TYPE];
            $ropName = $data[ROP_NAME];
            echo "<H1 id=\"rop_$ropType\">$ropName</H1>\n";
            echo $data['Table']->getTableWithHeader('Daily Totals', 2, 'DDP_Bubble.pm_med.PmicRopDailyTotals');
            echo "<br>\n";
            if ( isset( $flsRopTypes[$ropType] ) ) {
                foreach ( $flsRopTypes[$ropType] as $transferType ) {
                    $flsTable = showFlsTable($ropType, $transferType);
                    echo $flsTable->getTable();
                }
            }
            showPmServInstr($ropType);
            echo "<br><br>\n";
        }
    }
}

function getSubscriptions() {
    global $statsDB, $webargs;

    $where = $statsDB->where('enm_pmic_subs', 'date', true);

    $url = fromServer(PHP_SELF) . "?" . $webargs;

    return SqlTableBuilder::init()
        ->name(SUBS)
        ->tables(array('enm_pmic_subs', StatsDB::SITES) )
        ->where($where)
        ->addColumn( 'contentid', 'enm_pmic_subs.name', 'Name' )
        ->addSimpleColumn( 'enm_pmic_subs.type', 'Type' )
        ->addSimpleColumn( 'enm_pmic_subs.cellTypes', 'CellTrace Category' )
        ->addSimpleColumn( 'enm_pmic_subs.administrationState', 'administrationState' )
        ->addSimpleColumn( 'enm_pmic_subs.numberOfNodes', '#Nodes' )
        ->addSimpleColumn( 'enm_pmic_subs.scannerStates', 'Scanner States' )
        ->addSimpleColumn( 'enm_pmic_subs.scannerErrorCodes', 'Scanner Error Codes: Count' )
        ->ctxMenu(
            'subs',
            false,
            array( 'content' => 'Content'),
            $url,
            'contentid'
          )
        ->build();
}

function showSubContent( $contentid ) {
    global $rootdir;
    $contentFile = $rootdir . "/pm/subscription_content.zip";
    debugMsg("showSubContent: contentid=\"$contentid\" contentFile=$contentFile");
    if (file_exists($contentFile)) {
        $contentPath = 'zip://' . $contentFile . '#' . $contentid;
        $handle = fopen($contentPath, 'r');
    } else {
        debugMsg("showSubContent: contentFile doesn't exist");
    }

    if (is_null($handle) || !$handle) {
        echo "<H2>Cannot find subscription content for $contentid</H2>\n";
        return;
    }

    $contentStr = fgets($handle);
    fclose($handle);

    $data = array();
    foreach ( explode(",", $contentStr) as $entry ) {
        $data[] = array('entry' => $entry);
    }

    $contentTable = new DDPTable(
        'content',
        array(array('key' => 'entry', 'label' => 'Counter/Event')),
        array('data' => $data)
    );
    echo $contentTable->getTable();
}

function getRopData( $flsRopTypes ) {
    $ropTypes = array("1MIN", "5MIN", "15MIN", "30MIN", "60MIN", "720MIN", "1440MIN");

    $ropData = array();
    foreach ( $ropTypes as $ropType ) {
        $hasData = 0;
        $ropName = getRopName($ropType);
        $ropTable = getRopTotals( $ropType );
        if ( $ropTable->hasRows() || array_key_exists($ropType, $flsRopTypes) ) {
            $hasData = 1;
        }
        $ropData[] = array(ROP_TYPE => $ropType, ROP_NAME => $ropName, 'Table' => $ropTable, HAS_DATA => $hasData );
    }
    return $ropData;
}

function getLinks( $ropData, $hasSubs ) {
    global $statsDB;

    $ropAnchorLinks = array();

    foreach ( $ropData as $data ) {
        if ( $data[HAS_DATA] == 1 ) {
            $ropAnchorLinks[] =  makeAnchorLink( 'rop_' . $data[ROP_TYPE], $data[ROP_NAME] );
        }
    }

    $errorLink = makeLink('/TOR/pm/pmserv.php', 'PM Error Summary', array('showPmErrors' => 1));
    $dpsLink = makeLink( '/TOR/dps.php', 'DPS', array(SERV_LIST => makeSrvList("pmservice") ) );
    $jmsLink = makeFullGenJmxLink( 'pmservice', 'GenJmx' );
    $ropLinks = "ROP Stats" . makeHTMLList( $ropAnchorLinks );

    $linkList = array( $errorLink, $ropLinks, $jmsLink, $dpsLink );

    if ( $hasSubs ) {
        $linkList[] = makeAnchorLink(SUBS, "Subscriptions");
    }

    if ( $statsDB->hasData('enm_large_bsc_nodes') ) {
        $linkList[] = makeAnchorLink( LBN, "Large BSC Nodes" );
    }

    $linkList[] = makeAnchorLink( 'FSU', "PM File System Usage" );

    $pfdLinks = array();
    $pfdLinks[] = makeAnchorLink(FLS_FDS.'PFD', FLS_FDS_TITLE);
    $pfdLinks[] = makeAnchorLink(FDS.'PFD', FDS_TITLE);
    $pfdLinks[] = makeAnchorLink(OFDS.'PFD', OFDS_TITLE);
    $linkList[] = "Periodic File Deletion" . makeHTMLList( $pfdLinks );

    $opfdLinks = array();
    $opfdLinks[] = makeAnchorLink(FLS_FDS.'OPFD', FLS_FDS_TITLE);
    $opfdLinks[] = makeAnchorLink(FDS.'OPFD', FDS_TITLE);
    $opfdLinks[] = makeAnchorLink(OFDS.'OPFD', OFDS_TITLE);
    $linkList[] = "Overload Protection File Deletion" . makeHTMLList( $opfdLinks );

    echo makeHTMLList( $linkList );
    echo addLineBreak();
}

function plotFdsTypeGraphs( $types, $file, $title, $help, $week = false, $filter = null ) {
    global $date;

    $startTime = $date . " 00:00:00";
    if ( $week ) {
        $startTime = subDate(7) . " 00:00:00";
    }
    $endTime = $date . " 23:59:59";
    $types = explode( ",", $types );

    drawHeader($title, 1, $help);
    $params = array();
    if ( $filter ) {
        $params = array( 'filter' => $filter );
    }
    getGraphsFromSet('all', $graphs, $file . "_all", $params, 640, 320, $startTime, $endTime);
    plotgraphs( $graphs );

    foreach ( $types as $type ) {
        drawHeader($type, 2, '');
        $params = array();
        if ( $filter ) {
            $params = array( 'type' => $type, 'filter' => $filter );
        } else {
            $params = array( 'type' => $type );
        }
        $graphs = array();
        getGraphsFromSet('all', $graphs, $file, $params, 640, 320, $startTime, $endTime);
        plotgraphs( $graphs );
    }
}

function showFileDelStats( $filter ) {
    global $statsDB;

    $params = array( ModelledTable::URL => makeSelfLink(), 'filter' => $filter );

    $title = 'Periodic File Deletion';
    if( $filter === 'OPFD' ) {
        $title = 'Overload Protection File Deletion';
    }
    drawHeader($title, 1, '');

    drawHeader(FLS_FDS_TITLE, 2, FLS_FDS.$filter);
    $table = new ModelledTable( "TOR/pm/flsFDS", FLS_FDS.$filter, $params );
    echo $table->getTable();
    echo addLineBreak();

    drawHeader(FDS_TITLE, 2, FDS.$filter);
    if ( $statsDB->hasData('enm_pm_file_del_stats_instr') ) {
        $table = new ModelledTable( "TOR/pm/fds_instr_totals", FDS."_INSTR".$filter, $params );
    } else {
        $table = new ModelledTable( "TOR/pm/fds_totals", FDS.$filter, $params );
    }

    echo $table->getTable();
    echo addLineBreak();

    drawHeader(OFDS_TITLE, 2, OFDS.$filter);
    $table = new ModelledTable( "TOR/pm/ofds_totals", OFDS.$filter, $params );
    echo $table->getTable();
    echo addLineBreak();
}

function plotFsGraphs() {
    drawHeader('PM File System Usage', 1, '');
    $graphs = array();
    getGraphsFromSet('all', $graphs, 'TOR/pm/fs_usage');
    plotgraphs( $graphs );
}

function showFsUsage() {
    echo addLineBreak(2);
    drawHeader('PM File System Usage', 2, 'FSU');
    $params = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable( "TOR/pm/file_system_usage", 'file_system_usage', $params );
    echo $table->getTable();
    echo addLineBreak(2);
}

function splitParams( $selected, &$filter, &$types ) {
    $parts = explode(',', $selected);
    $typesArr = array();
    foreach ($parts as $part) {
        $part = explode(':', $part);
        $filter = $part[0];
        $typesArr[] = $part[1];
    }
    $types = implode(',', $typesArr);
}

$ropPeriods = array( '300', '900', '1800', '3600' );

function largeBscParams( $rop ) {
    if ( $rop === '300' ) {
        $title = "5 MIN ROP";
    }elseif ( $rop == '900' ) {
        $title = "15 MIN ROP";
    } elseif ( $rop == '1800' ) {
        $title = "30 MIN ROP";
    } elseif ( $rop == '3600' ) {
        $title = "1 HR ROP";
    }
    return $title;
}

function largeBscGraphs() {
    global $ropPeriods;

    drawHeader( "Large BSC Nodes ROP Graphs", 1, LBN );
    foreach ( $ropPeriods as $ropPeriod ) {
        $title = largeBscParams( $ropPeriod );
        drawHeader( $title, 2, "" );
        $graphs = array();
        $graphParam = array( 'ropPeriod' => $ropPeriod );
        getGraphsFromSet( 'nodes', $graphs, 'TOR/pm/large_bsc_nodes', $graphParam );
        plotgraphs( $graphs );
    }
}

function largeBscTables() {
    global $ropPeriods;

    drawHeader( 'Large BSC Nodes', 1, LBN );
    foreach ( $ropPeriods as $ropPeriod ) {
        $params = array( ModelledTable::URL => makeSelfLink(), 'ropPeriod' => $ropPeriod );
        $title = largeBscParams( $ropPeriod );
        drawHeader( $title, 2, LBN );
        $table = new ModelledTable( "TOR/pm/large_bsc_nodes", LBN.$ropPeriod, $params );
        echo $table->getTable();
        echo addLineBreak();
   }
}

function mainFlow() {
    global $statsDB;

    $flsRopTypes = getFlsRopTypes($statsDB);
    $ropData = getRopData( $flsRopTypes );
    $hasSubs = false;

    $subscriptionsTable = getSubscriptions();

    if ( $subscriptionsTable->hasRows() ) {
        $hasSubs = true;
    }

    getLinks( $ropData, $hasSubs );

    if ( $statsDB->hasData( 'enm_pmic_filecollection' ) ) {
        showCombinedFileCollection();
        echo addLineBreak(2);
    }

    showRopTables( $ropData, $flsRopTypes );

    if ( $subscriptionsTable->hasRows() ) {
        drawHeaderWithHelp("Subscriptions", 2, SUBS);
        echo $subscriptionsTable->getTable();
    }

    largeBscTables();

    showFsUsage();

    echo addLineBreak(2);
    foreach ( array( 'PFD', 'OPFD' ) AS $filter ) {
        showFileDelStats( $filter );
    }
}

$selected = requestValue('selected');
$action = requestValue('action');
$filter = '';
$types = '';
splitParams( $selected, $filter, $types );

if ( issetURLParam('showPmErrors') ) {
    showPmErrors();
} elseif ( isset($_REQUEST['subs']) ) {
    if ( $selected != '' ) {
        showSubContent($selected);
    } else {
        echo "<b>No content available</b>";
    }
} elseif ( isset($_REQUEST['fls']) ) {
    plotFls($_REQUEST['rop'], $_REQUEST['transfertype'], $_REQUEST['selected']);
} elseif ( $action == 'filterFdsByType' ) {
    plotFdsTypeGraphs(
        $selected,
        'TOR/pm/file_deletion_stats',
        FDS_TITLE,
        FDS
    );
} elseif ( $action == 'filterOfdsByType' ) {
    plotFdsTypeGraphs(
        $types,
        'TOR/pm/orphan_file_deletion_stats',
        OFDS_TITLE,
        OFDS,
        true,
        $filter
    );
} elseif ( $action == 'filterFLSFdsByType' ) {
    plotFdsTypeGraphs(
        $types,
        'TOR/pm/flsdb_file_deletion_stats',
        FLS_FDS_TITLE,
        FDS_TITLE,
        false,
        $filter
    );
} elseif ( $action == 'showFsGraphs' ) {
    plotFsGraphs();
} elseif ( $action == 'fdsGraphs' ) {
    drawHeader( 'File System Deletion Statistics', 2, '' );
    $params = array( 'filter' => $selected );
    $graphs = array();
    getGraphsFromSet( 'all', $graphs, 'TOR/pm/file_deletion_stats_instr', $params );
    plotgraphs( $graphs );
} elseif ( $action === 'plotNode' ) {
    largeBscGraphs( $selected );
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
