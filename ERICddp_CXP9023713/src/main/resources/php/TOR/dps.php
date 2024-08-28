<?php
$pageTitle = "DPS";

include "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/functions.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once 'HTML/Table.php';

const DPS_INSTR_TABLE = 'enm_dps_instr';
const FIND_MO = 'findMo';
const FIND_PO = 'findPo';
const QUERIES_KEY = 'queries';
const SUM_PREFIX = 'SUM(n_';
const DET_STATS = "Detailed Statistics";
const DAILY_STATS = "Daily Statistics";
const DPS_NEO4J = "enm_dps_neo4jtx";
const PATH = "/TOR/dps.php";
const DPS_NEO_CON = "dpsNeoCon";

$counters = array(
    FIND_MO, FIND_PO, 'createMo', 'createPo', 'deleteMo', 'deletePo',
    'setAttribute', 'addAssoc', 'removeAssoc',
);

$queryCounters = array(
    'changelogQueriesWithRestrictions', 'changelogQueriesWithoutRestrictions',
    'containmentQueriesWithRestrictions', 'containmentQueriesWithoutRestrictions',
    'groupQueriesWithRestrictions', 'groupQueriesWithoutRestrictions',
    'projectionsOnChangelogQueriesWithRestrictions', 'projectionsOnChangelogQueriesWithoutRestrictions',
    'projectionsOnContainmentQueriesWithRestrictions', 'projectionsOnContainmentQueriesWithoutRestrictions',
    'projectionsOnGroupQueriesWithRestrictions', 'projectionsOnGroupQueriesWithoutRestrictions',
    'projectionsOnTypeContainmentQueriesWithRestrictions', 'projectionsOnTypeContainmentQueriesWithoutRestrictions',
    'projectionsOnTypeQueriesWithRestrictions', 'projectionsOnTypeQueriesWithoutRestrictions',
    'queriesCount',
    'typeContainmentQueriesWithRestrictions', 'typeContainmentQueriesWithoutRestrictions',
    'typeQueriesWithRestrictions', 'typeQueriesWithoutRestrictions' );

$queryOptCounters = array(
    'qOptNone', 'qOptDescendantsAtMixedLevels', 'qOptDescendantsAtOneLevel',
    'qOptDirectPathExpression', 'qOptPathsWithRecursion'
);

function showServers( $serversQueryArg ) {
    global $counters, $queryCounters, $queryOptCounters, $site, $date, $debug;

    $statsDB = new StatsDB();
    $hasTime = $statsDB->hasData(DPS_INSTR_TABLE, 'time', false, '(t_findPo IS NOT NULL OR t_findMo IS NOT NULL)');

    $tableCols = array(array('key' => 'inst', 'db' => SqlPlotParam::SERVERS_HOSTNAME, DDPTable::LABEL => 'Instance'));
    foreach ( $counters as $op ) {
        $countCol = 'n_' . $op;
        $tableCols[] = array(  'key' => $countCol, 'db' => "SUM($countCol)", DDPTable::LABEL => "# $op" );

        if ( $hasTime && ($op === FIND_MO || $op === FIND_PO) ) {
            $durationCol = 't_' . $op;
            $tableCols[] = array(
                'key' => $durationCol,
                'db' => "ROUND( SUM($durationCol) / SUM($countCol), 1)",
                DDPTable::LABEL => "$op (msec)"
            );
        }
    }
    $tableCols[] = array( 'key' => QUERIES_KEY,
                          'db' => SUM_PREFIX . implode("+ n_", $queryCounters) . ")",
                          DDPTable::LABEL => "# Query" );

    $sqlTable = new SqlTable("dps_totals",
                             $tableCols,
                             array( DPS_INSTR_TABLE, 'sites', StatsDB::SERVERS ),
                             "enm_dps_instr.siteid = sites.id AND sites.name = '$site' AND " .
                             "enm_dps_instr.serverid =  servers.id AND servers.siteid = sites.id AND servers.hostname IN ('$serversQueryArg') AND " .
                             "enm_dps_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' GROUP BY enm_dps_instr.serverid",
                             TRUE,
                             array( 'order' => array( 'by' => 'inst', 'dir' => 'ASC'))
    );

    echo $sqlTable->getTableWithHeader( "Daily Summary", 2, "" );

    drawHeaderWithHelp( DET_STATS, 2, "detailedStatsHelp" );

    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    $allCounters = array_merge( $counters, $queryCounters, $queryOptCounters );

    $statsDB = new StatsDB();
    $totalCols = array();
    foreach ( $allCounters as $counter ) {
        $totalCols[] = SUM_PREFIX . $counter . ") AS $counter";
    }
    $totalQueryTemplate = <<<EOT
SELECT %s
FROM enm_dps_instr, sites, servers
WHERE %s AND
 enm_dps_instr.serverid = servers.id AND
 servers.siteid = sites.id AND
 servers.hostname IN ('%s')
EOT;
    $totalQuery = sprintf(
        $totalQueryTemplate,
        implode(",", $totalCols),
        $statsDB->where(DPS_INSTR_TABLE),
        $serversQueryArg
    );
    $totalsRow = $statsDB->queryNamedRow($totalQuery);

    foreach ( $allCounters as $op ) {
        if ( $debug ) {
            echo "<pre>showServers op=$op total=$totalsRow[$op]</pre>\n";
        }

        // Only plot graphs for "active" operations
        if ( $totalsRow[$op] == 0 ) {
            continue;
        }

        $countCol = 'n_' . $op;
        $where = <<<EOT
enm_dps_instr.siteid = sites.id AND sites.name = '%s' AND
enm_dps_instr.serverid =  servers.id AND servers.siteid = sites.id AND servers.hostname IN ('%s')
EOT;
        $sqlParam = array(
            'title' => "$op",
            'type' => 'sb',
            'ylabel' => "#",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'multiseries'=> SqlPlotParam::SERVERS_HOSTNAME,
                    'whatcol' => array( $countCol => '#' ),
                    'tables' => "enm_dps_instr, sites, servers",
                    'where' => $where,
                    'qargs' => array(
                        'site',
                        StatsDB::SERVERS
                    )
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $url = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            640,
            320,
            "servers=$serversQueryArg"
        );
        $graphTable->addRow(array($url));

        if ( $hasTime && ($op === FIND_MO || $op === FIND_PO) ) {
            $timeCol = 't_' . $op;
            $sqlParam = array(
                'title' => "Avg Time $op (msec)",
                'type' => 'sb',
                'ylabel' => "#",
                'useragg' => 'true',
                'persistent' => 'true',
                'querylist' => array(
                    array(
                        'timecol' => 'time',
                        'multiseries'=> SqlPlotParam::SERVERS_HOSTNAME,
                        'whatcol' => array( "$timeCol/$countCol" => '#' ),
                        'tables' => "enm_dps_instr, sites, servers",
                        'where' => $where . " AND $countCol > 0",
                        'qargs' => array(
                            'site',
                            StatsDB::SERVERS
                        )
                    )
                )
            );
            $id = $sqlParamWriter->saveParams($sqlParam);
            $url = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                640,
                320,
                "servers=$serversQueryArg"
            );
            $graphTable->addRow(array($url));
        }
    }
    echo $graphTable->toHTML();
}

function getColumns($hasTime) {
    global $counters, $queryCounters;

    $dbColumns = array( 'enm_dps_instr.serverid AS srvid' );
    $tableColumns = array(array('key' => 'sg', DDPTable::LABEL => 'Service Group'));

    foreach ( $counters as $op ) {
        $dbColumns[] = SUM_PREFIX . $op . ") AS " . $op;
        $tableColumns[] = array( 'key' => $op, DDPTable::LABEL => $op, 'type' => 'int' );


        if ($hasTime && ($op === 'findMo' || $op === 'findPo')) {
            $countCol = 'n_' . $op;
            $durationCol = 't_' . $op;
            $dbColumns[] = "ROUND( SUM($durationCol) / SUM($countCol), 1) AS $durationCol";
            $tableColumns[] = array(
                'key' => $durationCol,
                DDPTable::LABEL => "$op (msec)",
                'type' => 'float'
            );
        }
    }

    $dbColumns[] = SUM_PREFIX . implode(" + n_", $queryCounters) . ") AS queries";
    $tableColumns[] = array( 'key' => QUERIES_KEY, DDPTable::LABEL => 'Queries', 'type' => 'int' );

    return array($dbColumns, $tableColumns);
}

function initResult($sg, $allColumns) {
    $result = array( 'sg' => $sg, 'inst' => 0);
    foreach ( $allColumns as $op ) {
        $result[$op] = 0;
    }

    return $result;
}

function getResults($statsDB, $srvToSG, $querySql, $hasTime) {
    global $counters;

    $allColumns = $counters;
    $allColumns[] = QUERIES_KEY;
    if ( $hasTime ) {
        foreach ( array('t_findMo', 't_findPo') as $op ) {
            $allColumns[] = $op;
        }
    }

    $dataBySg = array();
    $statsDB->query($querySql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $sg = $srvToSG[$row['srvid']];
        if ( ! array_key_exists($sg, $dataBySg) ) {
            $dataBySg[$sg] = initResult($sg, $allColumns);
        }
        $dataBySg[$sg]['inst']++;

        foreach ( $allColumns as $op ) {
            $dataBySg[$sg][$op] += $row[$op];
        }
    }

    $results = array_values($dataBySg);
    if ( $hasTime ) {
        foreach ( $results as &$result ) {
            foreach ( array('t_findMo', 't_findPo') as $op ) {
                $result[$op] = $result[$op] / $result['inst'];
            }
        }
    }

    return $results;
}

function showSummary() {
    global $site, $date, $statsDB;

    /*
    The orignal query had very poor performance

    SELECT
     enm_servicegroup_names.name AS sg,
     SUM(n_findMo) AS column_1, ...
    FROM
     enm_dps_instr FORCE INDEX(siteTimeIdx),sites,enm_servicegroup_instances,enm_servicegroup_names
    WHERE
     enm_dps_instr.siteid = sites.id AND sites.name = 'LMI_ENM420' AND
     enm_dps_instr.time BETWEEN '2019-02-20 00:00:00' AND '2019-02-20 23:59:59' AND
     enm_dps_instr.serverid = enm_servicegroup_instances.serverid AND
     enm_dps_instr.siteid = enm_servicegroup_instances.siteid AND
     enm_servicegroup_instances.date = '2019-02-20' AND
     enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
    GROUP BY sg

    EXPLAIN indicated everything should be okay

    | id   | select_type | table                      | type   | possible_keys   | key         | key_len | ref                                          | rows  | Extra                           |
    +------+-------------+----------------------------+--------+-----------------+-------------+---------+----------------------------------------------+-------+---------------------------------+
    |    1 | SIMPLE      | sites                      | const  | PRIMARY,nameIdx | nameIdx     | 130     | const                                        |     1 | Using temporary; Using filesort |
    |    1 | SIMPLE      | enm_dps_instr              | range  | siteTimeIdx     | siteTimeIdx | 10      | NULL                                         | 31611 | Using where                     |
    |    1 | SIMPLE      | enm_servicegroup_instances | ref    | siteDate        | siteDate    | 5       | func,const                                   |   280 | Using where                     |
    |    1 | SIMPLE      | enm_servicegroup_names     | eq_ref | PRIMARY         | PRIMARY     | 2       | statsdb.enm_servicegroup_instances.serviceid |     1 |                                 |

    However, the query is taking ~ 80 seconds to execute and the slow query log
    has a very different story for number of rows

    Time: 190219 20:42:13
    User@Host: statsadm[statsadm] @ localhost [127.0.0.1]
    Thread_id: 436573718  Schema: statsdb  QC_hit: No
    Query_time: 77.866463  Lock_time: 0.000296  Rows_sent: 40  Rows_examined: 15784432
    SET timestamp=1550608933;
    SELECT
     enm_servicegroup_names.name AS sg,SUM(n_findMo) AS column_1,SUM(n_findPo) AS column_2,SUM(n_createMo) AS column_3,SUM(n_createPo) AS column_4,SUM(n_deleteMo)
     AS column_5,SUM(n_deletePo) AS column_6,SUM(n_setAttribute) AS column_7,SUM(n_addAssoc) AS column_8,SUM(n_removeAssoc) AS column_9,SUM(n_queries) AS column_10
    FROM enm_dps_instr,enm_servicegroup_instances,enm_servicegroup_names
    WHERE
     enm_dps_instr.siteid = 19 AND
     enm_dps_instr.time BETWEEN '2019-02-19 00:00:00' AND '2019-02-19 23:59:59' AND
     enm_servicegroup_instances.siteid = 19 AND enm_servicegroup_instances.date = '2019-02-19' AND
     enm_dps_instr.serverid = enm_servicegroup_instances.serverid AND
     enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
    GROUP BY enm_servicegroup_names.id;

    So for now just the the totals per serverid and aggregate per service group
    in the php code
    */
    $srvToSG = srvToSgMap();
    $hasTime = $statsDB->hasData(DPS_INSTR_TABLE, 'time', false, '(t_findPo IS NOT NULL OR t_findMo IS NOT NULL)');

    list($dbColumns, $tableColumns) = getColumns($hasTime);

    $dbColumnsStr = implode(",", $dbColumns);
    $querySql = <<<EOT
SELECT $dbColumnsStr
FROM
 enm_dps_instr,sites
WHERE
 enm_dps_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_dps_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY enm_dps_instr.serverid
EOT;


    $totalTable = new DDPTable(
        "totals",
        $tableColumns,
        array( 'data' => getResults($statsDB, $srvToSG, $querySql, $hasTime) ),
        array(
            'ctxMenu' => array('key' => 'clientDpsUsage',
                               'multi' => false,
                               'menu' => array( 'plot' => 'Plot' ),
                               'url' => makeSelfLink(),
                               'col' => 'sg'
            )
        )
    );

    drawHeaderWithHelp("DPS Usage Analysis By Service Group", 2, "totalbysg");
    echo $totalTable->getTable();
}

function getTableModel( $modelName, $hasSummaryData ) {
    $result = 'TOR/platform/';
    if ( $hasSummaryData ) {
        $result .= "sum_";
    }
    $result .= $modelName;
    return $result;
}

function dpsTransactionAnalysis( $hasSummaryData ) {
    $model = getTableModel( 'enm_dps_neo4jtx_transaction_analysis', $hasSummaryData );
    $params = array( ModelledTable::URL => makeSelfLink() );
    $tbl = new ModelledTable( $model, "dpsTranstable", $params );
    echo $tbl->getTableWithHeader( "DPS Transaction Analysis By Service Group", 1 );
}

function dpsTransactionPermit( $hasSummaryData ) {
    $model = getTableModel( 'enm_dps_neo4jtx_transaction_permit', $hasSummaryData );
    $params = array( ModelledTable::URL => makeSelfLink() );
    $tbl = new ModelledTable( $model, "dpsPermittable", $params );
    echo $tbl->getTableWithHeader( "DPS Transaction Permit By Service Group", 1 );
}

function dpsTransactionAnalysisForSg( $sids, $hasSummaryData ) {
    $params = array( 'sids' => $sids );
    $model = getTableModel( 'enm_dps_neo4jtx_transaction_analysis_instances', $hasSummaryData );
    $tbl = new ModelledTable( $model, "dpsTranstable", $params );
    echo $tbl->getTableWithHeader( DAILY_STATS, 1 );

    $graphs = array();
    drawHeader( DET_STATS, 1, 'dpsPermitgraph' );
    getGraphsFromSet( 'analysis', $graphs, 'TOR/platform/enm_dps_neo4jtx', $params, 800, 400 );
    plotGraphs($graphs);
}

function dpsTransactionPermitForSg( $sids, $hasSummaryData ) {
    $params = array( 'sids' => $sids );
    $model = getTableModel( 'enm_dps_neo4jtx_transaction_permit_instances', $hasSummaryData );
    $tbl = new ModelledTable( $model, "dpsPermittable", $params );
    echo $tbl->getTableWithHeader( DAILY_STATS, 1 );

    $graphs = array();
    drawHeader( DET_STATS, 1, 'dpsPermitgraph' );
    getGraphsFromSet( 'permit', $graphs, 'TOR/platform/enm_dps_neo4jtx', $params, 800, 400 );
    plotGraphs($graphs);
}

function dpsNeoCon() {
    $params = array( ModelledTable::URL => makeSelfLink() );
    $tbl = new ModelledTable( 'TOR/platform/dps_neo_con', "tbl", $params );

    drawHeader("DPS Neo4j Client Connection Metrics By Service Group", 1, DPS_NEO_CON);
    echo $tbl->getTable();
}

function dpsNeoConBySg( $servers ) {
    $params = array( ModelledTable::URL => makeSelfLink(), 'servers' => $servers );
    $tbl = new ModelledTable( 'TOR/platform/dps_neo_con_by_sg', "tbl", $params );
    if ( $tbl->hasRows() ) {
        drawHeader(DAILY_STATS, 1, DPS_NEO_CON);
        echo $tbl->getTable();
    }
}

function plotNeoConBySgGraphs( $sids ) {
    drawHeader(DET_STATS, 1, DPS_NEO_CON);

    $graphSet = new ModelledGraphSet('TOR/platform/dps_neo_con_by_sg');
    $params = array( 'sids' => $sids );
    $group = $graphSet->getGroup('all');

    drawGraphGroup( $group, $params );
}

function drawGraphGroup( $group, $params ) {
    $graphs = array();
    foreach ( $group['graphs'] as $modelledGraph ) {
        $graphs[] = array( $modelledGraph->getImage($params, null, null, 640, 240) );
    }
    plotgraphs( $graphs );
}

function mainFlow() {
    $links = array();
    $links[] = makeLink( PATH, 'DPS Usage Analysis', array('showSummary'=> '1') );
    $links[] = makeLink( PATH, 'DPS Transaction Analysis', array('dpsTransactionAnalysis'=> '1') );
    $links[] = makeLink( PATH, 'DPS Transaction Permit Analysis', array('dpsTransactionPermit'=> '1') );
    $links[] = makeLink( PATH, 'DPS Neo4j Client Connection Metrics', array(DPS_NEO_CON=> '1') );
    echo makeHTMLList($links);
}

$sg = requestValue('selected');
$inst = enmGetServiceInstances($statsDB, $site, $date, $sg);
$servers = array_keys( $inst );
$sids = array_values( $inst );
$servers = implode("','", $servers);
$sids = implode(",", $sids);
$hasSummaryData = $statsDB->hasData( 'sum_enm_dps_neo4jtx', 'date', true );

if ( issetURLParam('clientDpsUsage') ) {
    showServers($servers);
} elseif ( issetURLParam('showSummary') ) {
    showSummary();
} elseif ( issetURLParam('dpsTransactionAnalysis') ) {
    dpsTransactionAnalysis( $hasSummaryData );
} elseif ( issetURLParam('clientDpsTrans') ) {
    dpsTransactionAnalysisForSg( $sids, $hasSummaryData );
} elseif ( issetURLParam('dpsTransactionPermit') ) {
    dpsTransactionPermit( $hasSummaryData );
} elseif ( issetURLParam('clientDpsPermit') ) {
    dpsTransactionPermitForSg( $sids, $hasSummaryData );
} elseif ( issetURLParam(DPS_NEO_CON) ) {
    if ( requestValue(DPS_NEO_CON) === 'plot') {
        dpsNeoConBySg( $servers );
        plotNeoConBySgGraphs( $sids );
    } else {
        dpsNeoCon();
    }
} else {
    mainFlow();
}

include PHP_ROOT . "/common/finalise.php";

