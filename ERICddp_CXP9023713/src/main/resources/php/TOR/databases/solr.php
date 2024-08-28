<?php
$pageTitle = "Solr";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const SOLR = 'enm_solr';
const NUM_DOCS = 'searcherNumDocs';

function solrStatsParams() {
    return array(
            'enm_solr_core_names.name' => 'Core',
            'SUM(enm_solr.cacheInserts)' => 'Cache Inserts',
            'SUM(enm_solr.cacheLookups)' => 'Cache Lookups',
            'SUM(enm_solr.cacheHits)' => 'Cache Hits',
            'SUM(enm_solr.cacheEvictions)' => 'Cache Evictions',
            'SUM(enm_solr.selectRequests)' => 'Select Requests',
            'IF(SUM(enm_solr.selectRequests) > 0, ROUND( ( SUM(enm_solr.selectTime) /
            SUM(enm_solr.selectRequests) ), 2), 0)' => 'Average Select Time',
            'SUM(enm_solr.updateRequests)' => 'Update Requests',
            'IF(SUM(enm_solr.updateRequests) > 0, ROUND( ( SUM(enm_solr.updateTime) /
            SUM(enm_solr.updateRequests) ), 2), 0)' => 'Average Update Time',
            'SUM(enm_solr.updateJsonRequests)' => 'Update JSON Requests',
            'IF(SUM(enm_solr.updateJsonRequests) > 0, ROUND( ( SUM(enm_solr.updateJsonTime) /
            SUM(enm_solr.updateJsonRequests) ), 2), 0)' => 'Average Update JSON Time',
            'SUM(enm_solr.selectTimeouts)' => 'Select Timeouts',
            'SUM(enm_solr.selectErrors)' => 'Select Errors',
            'SUM(enm_solr.updateTimeouts)' => 'Update Timeouts',
            'SUM(enm_solr.updateErrors)' => 'Update Errors',
            'SUM(enm_solr.updateJsonTimeouts)' => 'Update JSON Timeouts',
            'SUM(enm_solr.updateJsonErrors)' => 'Update JSON Errors'
    );
}

function graphParams() {
    return array(
                array(
                    'enm_solr.cacheInserts' => 'Cache Inserts',
                    'enm_solr.cacheHits' => 'Cache Hits',
                    'enm_solr.cacheEvictions' => 'Cache Evictions'
                ),
                array(
                    'enm_solr.selectRequests' => 'Select Requests',
                    'IF(enm_solr.selectRequests > 0, enm_solr.selectTime / enm_solr.selectRequests, 0)' =>
                    'Average Select Time'
                ),
                array(
                    'enm_solr.updateRequests' => 'Update Requests',
                    'IF(enm_solr.updateRequests > 0, enm_solr.updateTime / enm_solr.updateRequests, 0)' =>
                    'Average Update Time'
                ),
                array(
                    'enm_solr.updateJsonRequests' => 'Update JSON Requests',
                    'IF(enm_solr.updateJsonRequests > 0, enm_solr.updateJsonTime /
                    enm_solr.updateJsonRequests, 0)' => 'Average Update JSON Time'
                ),
                array(
                    'enm_solr.selectTimeouts' => 'Select Timeouts',
                    'enm_solr.selectErrors' => 'Select Errors'
                ),
                array(
                    'enm_solr.updateTimeouts' => 'Update Timeouts',
                    'enm_solr.updateErrors' => 'Update Errors'
                ),
                array(
                    'enm_solr.updateJsonTimeouts' => 'Update JSON Timeouts',
                    'enm_solr.updateJsonErrors' => 'Update JSON Errors'
                ),
                array(
                    'enm_solr.searcherNumDocs' => 'NumDocs'
                )
    );
}

function solrStats( $coreWhere ) {

    $cols = solrStatsParams();
    $tables = array( SOLR, 'enm_solr_core_names', StatsDB::SITES );

    $table = SqlTableBuilder::init()
        ->name("solr_stats")
        ->tables($tables)
        ->where($coreWhere);

    foreach ( $cols as $key => $val ) {
        $table->addSimpleColumn($key, $val);
    }

    $table->addColumn(NUM_DOCS, 'MAX(enm_solr.searcherNumDocs)', 'Max NumDocs');

    drawHeader("Solr Statistics", 2, "solrStats");
    echo $table->sortBy(NUM_DOCS, 'DESC')->build()->getTable();
}

function solrIndexSize() {
    global $site, $date, $statsDB;

    $where = $statsDB->where('enm_solr_daily', 'date', true);

    $where .= " AND enm_solr_daily.coreid = enm_solr_core_names.id AND
                enm_solr_latest.coreid = enm_solr.coreid AND
                enm_solr_latest.latestTime = enm_solr.time AND
                enm_solr_daily.coreid = enm_solr.coreid AND
                enm_solr_daily.siteid = enm_solr.siteid
                GROUP BY enm_solr_daily.coreid";

    //Sub Query used to find the latest entry for each core
    $latest = "(
                SELECT
                    enm_solr.coreid AS coreid,
                    max(enm_solr.time) AS latestTime
                FROM
                    enm_solr, sites
                WHERE
                    enm_solr.siteid = sites.id AND
                    time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
                    sites.name = '$site'
                GROUP BY enm_solr.coreid
              ) AS enm_solr_latest";

    $tables = array( 'enm_solr_daily', SOLR, 'enm_solr_core_names', 'sites as sites', $latest );

    $table = SqlTableBuilder::init()
        ->name("core_daily_table")
        ->tables($tables)
        ->where($where)
        ->addSimpleColumn('enm_solr_core_names.name', 'Core')
        ->addColumn(NUM_DOCS, 'MAX(enm_solr.searcherNumDocs)', 'Max NumDocs')
        ->addSimpleColumn('ROUND(enm_solr_daily.indexSizeBytes / POW(1024, 3), 2)', 'Index Size (GB)');

    drawHeader("Solr Index Size", 2, "coreDailyStats");
    echo $table->sortBy(NUM_DOCS, 'DESC')->build()->getTable();
}

function drawGraphs($coreWhere) {
    global $date, $statsDB;

    $statsDB->query("
SELECT
    DISTINCT(enm_solr_core_names.name)
FROM
    enm_solr,
    enm_solr_core_names,
    sites
WHERE
    $coreWhere
    ");

    $graphRowParams = graphParams();

    $where = "enm_solr.coreid = enm_solr_core_names.id AND
              enm_solr_core_names.name = '%s' AND
              enm_solr.siteid = sites.id AND sites.name = '%s'";

    while ($row = $statsDB->getNextRow()) {
        $core = $row[0];
        $end = "core=$core";

        drawHeader($core, 2, "solrStats");

        $graphTable = new HTML_Table('border=0');
        foreach ( $graphRowParams as $graphParams ) {
            $row = array();
            foreach ( $graphParams as $column => $title ) {
                $sqlParamWriter = new SqlPlotParam();
                $sqlPlotParam =
                          array('title' => $title,
                                'ylabel' => "",
                                'useragg' => 'true',
                                'persistent' => 'true',
                                'querylist' => array(
                                                array('timecol' => 'time',
                                                      'whatcol'=> array($column => $title),
                                                      'tables' => "enm_solr, enm_solr_core_names, sites",
                                                      'where' => $where,
                                                      'qargs' => array('core','site')
                                                )
                                )
                          );
                $id = $sqlParamWriter->saveParams($sqlPlotParam);
                $url =  $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 480, 250, $end);
                $row[] = $url;
            }
            $graphTable->addRow($row);
        }
        echo $graphTable->toHTML();
    }
}

function main() {
    global $statsDB;

    $coreWhere = $statsDB->where(SOLR);
    $coreWhere .= " AND enm_solr.coreid = enm_solr_core_names.id GROUP BY enm_solr.coreid";

    solrStats($coreWhere);
    solrIndexSize();
    drawGraphs($coreWhere);
}

main();

include_once PHP_ROOT . "/common/finalise.php";

