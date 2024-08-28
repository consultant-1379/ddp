<?php
$pageTitle = "ESMON";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const ESM_METRIC = "esm_performance_aggregation_metrics";
const ESM_ALERT_DEF = "esm_alert_def";
const ALERT_DEFINITIONS = "Alert Definitions";

function esmonAlertTable() {
    global $statsDB;

    $where  = $statsDB->where(ESM_ALERT_DEF, 'date', true);
    $where .= " AND esm_alert_def.typeId = esm_alert_types.id
                GROUP BY esm_alert_types.name";

    $reqAlert = SqlTableBuilder::init()
              ->name("EsmonAlertTable")
              ->tables(array(ESM_ALERT_DEF, 'esm_alert_types', StatsDB::SITES))
              ->where($where)
              ->addColumn('def', 'esm_alert_types.name', ALERT_DEFINITIONS)
              ->addSimpleColumn('COUNT(typeId)', 'Count')
              ->sortBy('def', DDPTable::SORT_ASC)
              ->paginate()
              ->build();
    echo $reqAlert->getTableWithHeader("Esmon Alert Definitions", 2, "");
}

function esmonPerformance($col, $colTitle, $title, $datecol) {
    global $site, $date;

    $where = "esm_performance_aggregation_metrics.siteid = sites.id AND
              sites.name = '$site' AND
              esm_performance_aggregation_metrics.$datecol BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              GROUP BY esm_performance_aggregation_metrics.$datecol";
    $time ="esm_performance_aggregation_metrics.".$datecol;
    $table = SqlTableBuilder::init()
              ->name($col)
              ->tables(array(ESM_METRIC, StatsDB::SITES))
              ->where($where)
              ->addColumn("time", "TIME($time)", "Time")
              ->addSimpleColumn("esm_performance_aggregation_metrics.".$col, $colTitle)
              ->sortBy("time", DDPTable::SORT_ASC)
              ->paginate();
    echo $table->build()->getTableWithHeader("$title", 2, "");
}

function showAggregationGraph($datecol, $column, $label) {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();
    $dbTables = array(ESM_METRIC, StatsDB::SITES);
    $where = "esm_performance_aggregation_metrics.siteid = sites.id AND
              sites.name = '%s'";

    $sqlParam = SqlPlotParamBuilder::init()
        ->title($label)
        ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
        ->yLabel('ms')
        ->makePersistent()
        ->forceLegend()
        ->addQuery(
            $datecol,
            array( $column => $label ),
            $dbTables,
            $where,
            array( 'site' )
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 600, 300);
    plotGraphs($graphs);
}

function showLinks() {
    $esmURLs = array();
    $esmURLs[] = makeAnchorLink("esmonperformance", "ESMON Performance Metrics");
    $esmURLs[] = makeAnchorLink("esmonAggregation", 'ESMON Aggregation Metrics');
    echo makeHTMLList($esmURLs);
}

function esmonPerformanceTables() {
        $dbcol = "databaseMaintenanceCompleted";
        $dbTitle = "Database Maintenance Completed (ms)";
        drawHeader("ESMON Performance Metrics", 1, "esmonperformance");
        esmonPerformance('alertDataPurged', 'Alert Data Purged (ms)', 'Alert Data', 'alertDate');
        esmonPerformance('eventDataPurged', 'Event Data Purged (ms)', 'Event Data', 'eventDate');
        esmonPerformance('alertdefinitionspurged', ALERT_DEFINITIONS." (ms)", ALERT_DEFINITIONS, 'aletDefDate');
        esmonPerformance($dbcol, $dbTitle, 'Database Maintenance', 'dbMaintainDate');
        esmonPerformance('dataPurgeJob', 'Alert Data Purged (ms)', 'Data Purge', 'dataPurgeDate');
        esmonPerformance('serverCount', 'Server Count', 'Server Count', 'serverDate');
        esmonPerformance('serviceCount', 'Service Count', 'Service count', 'serviceDate');
        esmonPerformance('platformCount', 'Platform Count', 'Platform Count', 'platformDate');
}

function aggregationGraphParam() {
    showAggregationGraph('rawDataDate', 'rawDataCount', 'Raw Data Aggregation');
    showAggregationGraph('oneHourDate', 'onehourDataCount', 'One Hour Data Aggregation');
    showAggregationGraph('sixHourDate', 'sixhourDataCount', 'Six Hour Data Aggregation');
}

function mainFlow() {
    global $statsDB, $site, $date;

    $siteId = getSiteId($statsDB, $site);
    $row = $statsDB->queryRow("
    SELECT
        id
    FROM
        servers
    WHERE
        type = 'MONITORING' AND
        siteid = $siteId
        ");
    $serverId = $row[0];
    $dataexists = $statsDB->hasData( ESM_METRIC );
    if ( $dataexists  ) {
       showLinks();
    }
    if ( $statsDB->hasData('enm_postgres_stats_db', 'time', false, "enm_postgres_stats_db.serverid = $serverId") ) {
        $esmPostgres = makeLink(
            "/TOR/databases/postgres.php",
            'ESMON Postgres Statistics',
            array('serverid' => $serverId)
        );
        echo makeHTMLList(array($esmPostgres));
    }

    if ( $statsDB->hasData(ESM_ALERT_DEF, 'date', true)) {
        esmonAlertTable();
    }
    if ( $dataexists ) {
        esmonPerformanceTables();
    }
    echo addLineBreak();
    drawHeader("ESMON Aggregation Metrics", 1, "esmonAggregation");
    aggregationGraphParam();
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
