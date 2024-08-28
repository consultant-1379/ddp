<?php

$pageTitle = "neo4j";

$DISABLE_UI_PARAMS = array( 'disableUI' );

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';

const SITES = 'sites';
const MODEL_NAMES = 'model_names.name';
const MO_NAMES = 'mo_names.name';
const LIVEMOUNT = 'livemocount';
const IDS_IN_USE = 'Ids In Use';
const NEO4JCLUSTER = 'neo4jcluster';
const ENM_NEO4J_SRV = 'enm_neo4j_srv';
const ENM_NEO4J_SRV_LR = 'enm_neo4j_srv_lr';
const MOCNTS = 'mocounts';

function showRaftEventsTable($statsDB,$srvArr) {
    global $date, $site;

    $srvIdToName = array();

    $raftWhere = "
enm_neo4j_raftevents.siteid = sites.id AND sites.name = '$site' AND
enm_neo4j_raftevents.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    foreach ( $srvArr as $hostname => $serverid ) {
        $srvIdToName[$serverid] = $hostname;
    }
    $raftRows = array();
    $statsDB->query("
SELECT
 enm_neo4j_raftevents.time AS time, enm_neo4j_raftevents.type AS type,
 enm_neo4j_raftevents.leaderid AS leaderid, enm_neo4j_raftevents.serverid AS memberid,
 enm_neo4j_raftevents.dbname AS dbname
FROM enm_neo4j_raftevents, sites
WHERE $raftWhere
ORDER BY enm_neo4j_raftevents.time, enm_neo4j_raftevents.seqno");
    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( isset($row['leaderid']) ) {
            $leader = $srvIdToName[$row['leaderid']];
        } else {
            $leader = '';
        }

        $raftRow = array( 'time' => $row['time'],
                          'leader' => $leader,
                          'dbname' => $row['dbname']
                   );
        foreach ( $srvArr as $hostname => $serverid ) {
            if ( $serverid == $row['memberid'] ) {
                $raftRow[$hostname] = $row['type'];
            } else {
                $raftRow[$hostname] = '';
            }
        }

        $raftRows[] = $raftRow;
    }
    $columns =
             array(
                 array( DDPTable::KEY => 'time', DDPTable::LABEL => 'Time', DDPTable::FORMATTER => 'ddpFormatTime' ),
                 array( DDPTable::KEY => 'leader', DDPTable::LABEL => 'Current Leader'),
                 array( DDPTable::KEY => 'dbname', DDPTable::LABEL => 'Database Name')
             );
    foreach ( $srvArr as $hostname => $serverid ) {
        $columns[] = array( DDPTable::KEY => $hostname, DDPTable::LABEL => $hostname );
    }
    $raftTable =
               new DDPTable("raft",
                            $columns,
                            array('data' => $raftRows),
                            array('rowsPerPage' => 10,'rowsPerPageOptions' => array(50, 100, 250, 500, 1000))
               );

    drawHeaderWithHelp("Raft Events", 2, "raft");
    echo $raftTable->getTable();
}

function linksList( &$srvArr, &$hasNeo4jLowRes, &$hasNeo4jChkPnts, &$hasMoCounts, &$hasBoltRaft, &$hasOrphanMO ) {
    global $site, $date, $debug, $webargs, $statsDB;

    $srvArr = array();

    $statsDB->query("
SELECT DISTINCT servers.hostname, servers.id
FROM servers, sites, enm_neo4j_srv
WHERE
 enm_neo4j_srv.siteid = sites.id AND sites.name = '$site' AND
 enm_neo4j_srv.serverid = servers.id AND
 enm_neo4j_srv.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY servers.hostname");

    while ( $row = $statsDB->getNextRow() ) {
        $srvArr[$row[0]] = $row[1];
    }

# Figure out what instr we have available

    $hasNeo4jLowRes = $statsDB->hasData("enm_neo4j_srv_lr");
    $hasNeo4jChkPnts = $statsDB->hasData("enm_neo4j_chkpnts", 'start');
    $hasBoltRaft = $statsDB->hasData('enm_neo4j_srv', 'time', false, 'enm_neo4j_srv.boltProcTime IS NOT NULL');

    $countsRow = $statsDB->queryRow("
SELECT
 (IFNULL(SUM(enm_neo4j_mocounts.total), 0) - IFNULL(SUM(enm_neo4j_mocounts.nonlive), 0)) AS totallive,
 SUM(enm_neo4j_mocounts.nonlive) AS totalnonlive
FROM enm_neo4j_mocounts, sites
WHERE
 enm_neo4j_mocounts.siteid = sites.id AND sites.name = '$site' AND
 enm_neo4j_mocounts.date = '$date'
");
    $hasMoCounts = isset($countsRow[0]) && $countsRow[0] > 0;

    $hasNeo4jTotalSize = $statsDB->hasData("enm_neo4j_srv_lr");

    $hasOrphanMO = $statsDB->hasData("enm_neo4j_orphan_mo_count");

    $links = array();
    echo "<h1>Neo4j</h1>\n";
    $serverNames = array();
    foreach ( $srvArr as $hostname => $serverid ) {
        $serverNames[] = $hostname . ",neo4j";
    }

    $links[] = makeLink('/genjmx.php', "JVM Stats", array('names' => implode(";", $serverNames)));
    $links[] = makeAnchorLink("transactions_anchor", "Transactions");
    $links[] = makeAnchorLink("pagecache_anchor", "Page Cache");

    if ( $hasBoltRaft ) {
        $links[] = makeAnchorLink("bolt_anchor", "Bolt");
        $links[] = makeAnchorLink("raft_anchor", "Raft");
    }

    if ( $hasNeo4jLowRes ) {
        $links[] = makeAnchorLink("storesizes_anchor", "Store Sizes");
        $links[] = makeAnchorLink("idsinuse_anchor", IDS_IN_USE);
    }

    if ( $hasNeo4jChkPnts ) {
        $links[] = makeAnchorLink("checkpoints_anchor", "Check Points");
    }

    if ( $hasMoCounts ) {
        $links[] = makeLink( "/TOR/databases/neo4j.php", "MO Counts", array("show" => MOCNTS) );
        $links[] = makeLink( "/TOR/databases/neo4j.php", "MO Ratio Analysis", array("show" => "moratioanalysis"));
    }

    echo makeHTMLList($links);
}

function drawTableforInstance($graphParams, $srvArr) {

    $graphWidth = 320;
    if ( count($srvArr) == 1 ) {
        $graphWidth = 640;
    }
    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow( array_keys($srvArr), null, 'th' );
    foreach ( $graphParams as $mod ) {
        $modelledGraph = new ModelledGraph('TOR/databases/neo4j_' . $mod);
        $graphs = array();
        foreach ( array_values($srvArr) as $sid ) {
            $params = array( "inst" => $sid );
            $graphs[] = array( $modelledGraph->getImage($params, null, null, $graphWidth, 240) );
        }
        $graphTable->addRow($graphs);
    }
    echo $graphTable->toHTML();
}

function drawGraphGroup($group, $srvArr) {
    $graphWidth = 320;

    if ( count($srvArr) == 1 ) {
        $graphWidth = 640;
    }
    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow( array_keys($srvArr), null, 'th' );
    foreach ( $group['graphs'] as $modelledGraph ) {
        $row = array();
        foreach ( array_keys($srvArr) as $inst ) {
            $params = array( "inst" => $inst );
            $row[] = array( $modelledGraph->getImage($params, null, null, $graphWidth, 240) );
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function mainFlow() {
    global $site, $date, $debug, $webargs, $statsDB;

    $srvArr = array();
    $hasNeo4jLowRes;
    $hasNeo4jChkPnts;
    $hasMoCounts;
    $hasBoltRaft;
    $hasOrphanMO;

    linksList( $srvArr, $hasNeo4jLowRes, $hasNeo4jChkPnts, $hasMoCounts, $hasBoltRaft, $hasOrphanMO );

    if ( $hasMoCounts ) {
        $dailyTotals = new ModelledTable( 'TOR/databases/neo4j_totalmocount', "totalmocount" );
        echo $dailyTotals->getTableWithHeader("Total MO Counts");
    }

    if ( $hasOrphanMO ) {
        drawHeader("Orphan MO Count", 2, "orphanmo");
        $modelledGraph = new ModelledGraph( 'TOR/databases/neo4j_orphan_mo' );
        $graphs[] = array( $modelledGraph->getImage() );
        plotGraphs($graphs);
    }

    if ( $statsDB->hasData('enm_neo4j_leader') ) {
        $dailyTotals = new ModelledTable( 'TOR/databases/neo4j_cluster', NEO4JCLUSTER );
        echo $dailyTotals->getTableWithHeader("Causal Cluster Leader");
    }

    showRaftEventsTable($statsDB, $srvArr);

    debugMsg("mainFlow: srvArr:", $srvArr);

    drawHeader("Transactions", 2, "transactions");
    if ( count($srvArr) > 1 ) {
        $modelledGraph = new  ModelledGraph('TOR/databases/neo4j_translastcommitted');
        plotgraphs( array( $modelledGraph->getImage() ) );
    }
    $neo4jSrvGraphSet = new ModelledGraphSet('TOR/databases/enm_neo4j_srv');
    drawGraphGroup($neo4jSrvGraphSet->getGroup("tx"), $srvArr);

    drawHeader("Page Cache", 2, "pagecache");
    drawGraphGroup($neo4jSrvGraphSet->getGroup("pagecache"), $srvArr);

    if ( $hasBoltRaft ) {
        drawHeader("Bolt", 2, "bolt");
        drawGraphGroup($neo4jSrvGraphSet->getGroup("bolt"), $srvArr);

        if ( count($srvArr) > 1 ) {
            drawHeader("Raft", 2, "raft");
            $modelledGraph = new  ModelledGraph('TOR/databases/neo4j_raftappend');
            plotgraphs( array( $modelledGraph->getImage() ) );
            # Bit of a hack here. The delta graph needs an extra condition on the where to ensure that
            # the subtraction always is >= zero. So we have a seperate ModelledGraph for this.
            # We then insert this graph into the raft group so they are all plotted together
            $raftGroup = $neo4jSrvGraphSet->getGroup("raft");
            array_unshift( $raftGroup['graphs'], new  ModelledGraph('TOR/databases/neo4j_raftdelta') );
            drawGraphGroup($raftGroup, $srvArr);
        }
    }

    if ( $hasNeo4jLowRes ) {
        drawHeader("Disk Space Usage", 2, "storesizes");
        $modelledGraph = new ModelledGraph("TOR/databases/neo4j_totalmb");
        plotgraphs( array( $modelledGraph->getImage() ) );
    }

    drawHeader(IDS_IN_USE, 2, "idsinuse");
    $srlLrArray = array('srv_lr');
    drawTableforInstance($srlLrArray, $srvArr);

    if ( $hasNeo4jChkPnts ) {
        drawHeader("Check Points", 2, "checkpoints");
        if ( count($srvArr)  > 1 ) {
            $modelledGraph = new ModelledGraph('TOR/databases/neo4j_checkpoints_servers');
            plotgraphs( array( $modelledGraph->getImage() ) );
        } else {
            $modelledGraph = new ModelledGraph( 'TOR/databases/neo4j_checkpoints', NEO4JCLUSTER);
            plotgraphs( array( $modelledGraph->getImage() ) );
        }
    }
}

function showMoRatioAnalysis($statsDB,$mdl,$live_cell_count_per_mdl,$mos_to_be_filtered) {

    global $site,$date,$debug,$webargs;

     if ( $live_cell_count_per_mdl > 0 ) {
           $cols = array(
                        array(
                            DDPTable::KEY => 'modelname',
                            DDPTable::DB => MODEL_NAMES,
                            DDPTable::LABEL => 'Model Name'
                        ),
                        array(
                            DDPTable::KEY => 'moname',
                            DDPTable::DB => MO_NAMES,
                            DDPTable::LABEL => 'MO Name'
                        ),
                        array(
                            DDPTable::KEY => LIVEMOUNT,
                            DDPTable::DB => 'IF(enm_neo4j_mocounts.total < enm_neo4j_mocounts.nonlive,
                                                0,
                                                enm_neo4j_mocounts.total - enm_neo4j_mocounts.nonlive)',
                            DDPTable::LABEL => 'Live MO Count',
                            DDPTable::FORMATTER => DDPTable::FORMAT_NUM
                        ),
                        array(
                            DDPTable::KEY => 'moratio',
                            DDPTable::DB => "TRUNCATE(
                                               IFNULL(
                                                 enm_neo4j_mocounts.total - enm_neo4j_mocounts.nonlive,\"\"
                                                )/$live_cell_count_per_mdl,2
                                             )",
                            DDPTable::LABEL => 'Live MO Count/Live cell count',
                            DDPTable::FORMATTER => DDPTable::FORMAT_NUM
                        )
                              );
        } else {
            $cols = array(
                    array(DDPTable::KEY => 'modelname', DDPTable::DB => MODEL_NAMES, DDPTable::LABEL => 'Model Name'),
                    array(DDPTable::KEY => 'moname', DDPTable::DB => MO_NAMES, DDPTable::LABEL => 'MO Name'),
                    array(
                        DDPTable::KEY => LIVEMOUNT,
                        DDPTable::DB => 'IFNULL(enm_neo4j_mocounts.total - enm_neo4j_mocounts.nonlive,"")',
                        DDPTable::LABEL => 'Live MO Count',
                        DDPTable::FORMATTER => DDPTable::FORMAT_NUM
                    ),
                    array(
                        DDPTable::KEY => 'moratio',
                        DDPTable::DB => '""',
                        DDPTable::LABEL => 'Live MO Count / Live cell count',
                        DDPTable::FORMATTER => DDPTable::FORMAT_NUM
                    )
            );
        }

    $where = "
enm_neo4j_mocounts.siteid = sites.id AND sites.name = '$site' AND
enm_neo4j_mocounts.date = '$date' AND
enm_neo4j_mocounts.namespaceid = model_names.id AND
model_names.name = '$mdl' AND
enm_neo4j_mocounts.motypeid = mo_names.id AND
mo_names.name IN ($mos_to_be_filtered)";

    $ratioAnalysisTable =
                 new SqlTable("moratioanalysisfor$mdl",
                              $cols,
                              array( 'enm_neo4j_mocounts', SITES, 'model_names', 'mo_names' ),
                              $where,
                              TRUE,
                              array(
                                  'order' => array( 'by' => LIVEMOUNT, 'dir' => 'DESC'),
                                  'rowsPerPage' => 20,
                                  'rowsPerPageOptions' => array(50, 100, 1000, 10000)
                              )
                 );
    echo $ratioAnalysisTable->getTableWithHeader("MO Ratio Analysis for $mdl", 2, "DDP_Bubble_437_ENM_Mo_Ratio_Analysis");
}

function moratioPerModel() {

    global $statsDB, $site, $date;

    foreach ( array("ERBS_NODE_MODEL","Lrat","RNC_NODE_MODEL") as $mdl ) {

        if ( $mdl === "ERBS_NODE_MODEL" || $mdl === "Lrat" ) {

            $mos_to_be_filtered = "'EUtranCellFDD','EUtranCellTDD','EUtranCellRelation','EUtranFreqRelation','ExternalENodeBFunction','ExternalEUtranCellFDD','GeranCellRelation','GeranFreqGroupRelation','GeranFrequency','RetSubUnit','SectorCarrier','TermPointToENB'";
            $row = $statsDB->queryRow("SELECT SUM(IFNULL(enm_neo4j_mocounts.total-enm_neo4j_mocounts.nonlive,\"\")) AS livecellcount FROM enm_neo4j_mocounts, sites, mo_names, model_names WHERE enm_neo4j_mocounts.date = '$date' AND enm_neo4j_mocounts.siteid = sites.id AND sites.name = '$site' AND enm_neo4j_mocounts.motypeid = mo_names.id AND mo_names.name IN ('EUtranCellFDD','EUtranCellTDD') AND enm_neo4j_mocounts.namespaceid = model_names.id AND model_names.name = '$mdl'");
            if ( $row[0] > 0 || $row[0] === "" ) {
                $live_cell_count_per_mdl = $row[0];
                showMoRatioAnalysis($statsDB,$mdl,$live_cell_count_per_mdl,$mos_to_be_filtered);
            }
        } elseif ( $mdl === "RNC_NODE_MODEL" ) {

            $mos_to_be_filtered = "'UtranCell','CoverageRelation','EutranFreqRelation','GsmRelation','UtranRelation'";

            $row = $statsDB->queryRow("SELECT IFNULL(enm_neo4j_mocounts.total-enm_neo4j_mocounts.nonlive,\"\") AS livecellcount FROM enm_neo4j_mocounts, sites, mo_names, model_names WHERE enm_neo4j_mocounts.date = '$date' AND enm_neo4j_mocounts.siteid = sites.id AND sites.name = '$site' AND enm_neo4j_mocounts.motypeid = mo_names.id AND mo_names.name = 'UtranCell' AND enm_neo4j_mocounts.namespaceid = model_names.id AND model_names.name = '$mdl'");
            if ( $row[0] > 0 || $row[0] === "" ) {
                $live_cell_count_per_mdl = $row[0];
                showMoRatioAnalysis($statsDB,$mdl,$live_cell_count_per_mdl,$mos_to_be_filtered);
            }
        }
    }
}

function plotMOCounts($sets) {
    global $date;

    $statsDB = new StatsDB();
    $from = subDate( '31' );
    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = SqlPlotParamBuilder::init()
        ->title('MO Counts For Last 31 Days')
        ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
        ->yLabel('Count')
        ->disableUserAgg()
        ->forceLegend()
        ->makePersistent();
    $tables = array('enm_neo4j_mocounts', 'sites', 'model_names', 'mo_names');

    $row = " IF(enm_neo4j_mocounts.total < enm_neo4j_mocounts.nonlive, 0,
        enm_neo4j_mocounts.total - enm_neo4j_mocounts.nonlive)";

    foreach ( $sets as $set ) {
        $data = explode(":", $set);
        $modelName = $statsDB->queryRow("SELECT name FROM model_names WHERE id = $data[0]");
        $moName = $statsDB->queryRow("SELECT name FROM mo_names WHERE id = $data[1]");
        $lbl = "$modelName[0]:$moName[0]";

        $where = "
enm_neo4j_mocounts.siteid = sites.id AND
enm_neo4j_mocounts.namespaceid = model_names.id AND
enm_neo4j_mocounts.motypeid = mo_names.id AND
sites.name = '%s' AND
enm_neo4j_mocounts.namespaceid = $data[0] AND
enm_neo4j_mocounts.motypeid = $data[1]";

        $sqlParam = $sqlParam->addQuery(
            'date',
            array( $row => $lbl ),
            $tables,
            $where,
            array('site')
        );
    }
    $sqlParam = $sqlParam->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$from 00:00:00", "$date 23:59:59"));
}

$show = requestValue('show');

if ( requestValue('disableUI') == 'plotMonthMOs' ) {
    $input = requestValue('selected');
    $sets = explode(",", $input);

    if ( count($sets) > 20 ) {
        echo "Max selecion of 20 items exceded!";
    } else {
        plotMOCounts($sets);
    }
} elseif ( $show == MOCNTS ) {
    $args = array( ModelledTable::URL => makeSelfLink() );
    $tbl = new ModelledTable( 'TOR/databases/neo4j_mocount', MOCNTS, $args );
    echo $tbl->getTableWithHeader("MO Counts");
} elseif ( $show == "moratioanalysis" ) {
    moratioPerModel();
} else {
    mainFlow();
}

include PHP_ROOT . "/common/finalise.php";

