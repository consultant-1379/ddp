<?php
$pageTitle = "Bulk CM Export";

 /* Disable the UI for non-main flow */
$DISABLE_UI_PARAMS = array( 'action' );
include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';

const TOTAL_MOS = 'total_mos';
const TYPEID = 'typeid';
const CM_EXPORT = 'cm_export';
const CM_EXPORT_TYPE = 'cm_export_types';
const EXPORT_ENDTIME = 'export_end_date_time';

$START_TIME_DB = 'IF(export_start_date_time=0,
                  from_unixtime(unix_timestamp(export_end_date_time)-cm_export.elapsedTime),
                  export_start_date_time)';

function dailyTotalsColumns() {
    return array(
                'COUNT(jobid)' => 'Total Jobs',
                'IF(export_start_date_time=0, SEC_TO_TIME(MAX(cm_export.elapsedTime)),
                 MAX(TIMEDIFF(export_end_date_time,export_start_date_time)))' => 'Max Duration',
                'IFNULL(status, \'NA\')' => 'Status',
                'IFNULL(cm_export_filter_choice_names.name, \'NA\')' => 'Filter',
                'SUM(total_mos)' => 'Total MOs',
                'MAX(ROUND((total_mos / IF(export_start_date_time=0,
                 IF(cm_export.elapsedTime = 0, 1,cm_export.elapsedTime),
                 IF(export_end_date_time = export_start_date_time,1,TIME_TO_SEC(TIMEDIFF(export_end_date_time,
                 export_start_date_time))))),2))' => 'Max MOs per Sec',
                'MIN(ROUND((total_mos / IF(export_start_date_time=0,
                 IF(cm_export.elapsedTime = 0,1,cm_export.elapsedTime),
                 IF(export_end_date_time = export_start_date_time,1,TIME_TO_SEC(TIMEDIFF(export_end_date_time,
                 export_start_date_time))))),2))' => 'Min MOs per Sec',
                'ROUND(AVG(total_mos / IF(export_start_date_time=0,
                 IF(cm_export.elapsedTime = 0,1,cm_export.elapsedTime),
                 IF(export_end_date_time = export_start_date_time,1,TIME_TO_SEC(TIMEDIFF(export_end_date_time,
                 export_start_date_time))))),2)' => 'Avg MOs per Sec',
                'IFNULL(cm_export_types.name, \'NA\')' => 'Type',
                'SUM(cm_export.expected_nodes)' => 'Nodes Expected',
                'SUM(cm_export.exported)' => 'Nodes Exported',
                'SUM(cm_export.not_exported)' => 'Nodes not Exported',
                'SUM(cm_export.nodes_no_match_found)' => 'Nodes no Match Found'
    );
}

function bulkExportColumns() {
    return array(
                'TIME(cm_export.export_end_date_time)' => 'End',
                'IF(export_start_date_time=0, SEC_TO_TIME(cm_export.elapsedTime),
                 TIMEDIFF(export_end_date_time,export_start_date_time))' => 'Duration',
                'TIME(cm_export.merge_start_time)' => 'Merge Start Time',
                'cm_export.merge_duration' => 'Merge Duration',
                'dpsReadDuration' => 'DPS Read Duration',
                'IFNULL(status, \'NA\')' => 'Status',
                 TOTAL_MOS => 'MOs',
                'ROUND((total_mos / IF(export_start_date_time=0,
                 IF(cm_export.elapsedTime = 0,1,cm_export.elapsedTime),
                 IF(export_end_date_time = export_start_date_time, 1,
                 TIME_TO_SEC(TIMEDIFF(export_end_date_time,export_start_date_time))))),0)' => 'MOs/sec',
                'IFNULL(cm_export_types.name, \'NA\')' => 'Type',
                'cm_export.expected_nodes' => 'Nodes Expected',
                'cm_export.exported' => 'Nodes Exported',
                'cm_export.not_exported' => 'Nodes Not Exported',
                'cm_export.nodes_no_match_found' => 'Nodes No Match Found',
                'cm_export.job_name' => 'Job Name',
                'IFNULL(cm_export_source_names.name, \'NA\')' => 'Source',
                'IFNULL(cm_export_filter_choice_names.name, \'NA\')' => 'Filter Choice',
                'IFNULL(cm_export.export_file, \'NA\')' => 'Export File',
                'IFNULL(cm_export.master_server_id, \'NA\')' => 'Master Server ID',
                'IFNULL(cm_export.export_non_synchronized_nodes, \'NA\')' => 'Export Non Synchronized Nodes',
                'IFNULL(cm_export.compression_type, \'NA\')' => 'Compression Type'
    );
}

function getExportDailyTotals($cols) {
    global $statsDB;

    $where  = $statsDB->where(CM_EXPORT, EXPORT_ENDTIME);
    $where .= "AND cm_export.typeid = cm_export_types.id
               AND cm_export.filter_choice_nameid = cm_export_filter_choice_names.id
               GROUP BY cm_export_types.name, status, cm_export_filter_choice_names.name";

    $reqBind = SqlTableBuilder::init()
              ->name('Daily_Totals')
              ->tables(array(CM_EXPORT, CM_EXPORT_TYPE, 'cm_export_filter_choice_names', StatsDB::SITES))
              ->where($where);
              foreach ($cols as $key => $value) {
                 $reqBind->addSimpleColumn($key, $value);
              }

    echo $reqBind->build()->getTableWithHeader("Daily Totals", 2, "", "", "Daily_Totals");
 }

function getBulkCMExport($cols) {
    global $START_TIME_DB, $statsDB;

    $where  = $statsDB->where(CM_EXPORT, EXPORT_ENDTIME);
    $where .= "AND cm_export.typeid = cm_export_types.id
               AND cm_export.source_nameid = cm_export_source_names.id
               AND cm_export.filter_choice_nameid = cm_export_filter_choice_names.id";

    $tables = array( CM_EXPORT, 'cm_export_source_names', CM_EXPORT_TYPE,
                    'cm_export_filter_choice_names', StatsDB::SITES );
    $reqBind = SqlTableBuilder::init()
             ->name("Bulk_CM_Export")
             ->tables($tables)
             ->where($where)
             ->addSimpleColumn('jobid', 'Job Id')
             ->addColumn('start', $START_TIME_DB, 'Start', DDPTable::FORMAT_TIME);
             foreach ($cols as $key => $value) {
                 if ( $value == "DPS Read Duration" ) {
                     $reqBind->addColumn($key, 'dpsReadDuration', $value, DDPTable::FORMAT_MSEC);
                 } else {
                     $reqBind->addSimpleColumn($key, $value);
                 }
             }
             $reqBind->sortBy('start', DDPTable::SORT_ASC);
             $reqBind->paginate();

    echo $reqBind->build()->getTableWithHeader("Bulk CM Export", 2, "", "", "Bulk_CM_Export");
}

function getExportTypeName($typeId) {
    global $statsDB;
    $row = $statsDB->queryRow("SELECT name FROM cm_export_types WHERE id = $typeId");
    return $row[0];
}

function showMOsDrillDownTable($typeId) {
    global $webargs, $statsDB;

    if ( $typeId != '' ) {
        echo "<p align='center'><a href='" . fromServer(PHP_SELF) . "?" . $webargs . "'>View All Types</a></p>";
    }

    $typeSelectWithURL = "CONCAT('<!--', cm_export_types.name, '--><a href=\"" . fromServer(PHP_SELF) . "?" .
                          $webargs . "&typeid=', cm_export_types.id , '\">', cm_export_types.name, '</a>')";

     $where  = $statsDB->where(CM_EXPORT, EXPORT_ENDTIME);
     $where .= "AND cm_export.typeid = cm_export_types.id
                AND cm_export.total_mos IS NOT NULL
                GROUP BY cm_export_types.name";

     $reqBind = SqlTableBuilder::init()
              ->name("DownTable")
              ->tables(array(CM_EXPORT, CM_EXPORT_TYPE, StatsDB::SITES))
              ->where($where)
              ->addColumn('type', $typeSelectWithURL, 'Types')
              ->addSimpleColumn('COUNT(cm_export_types.name)', 'Count')
              ->sortBy('type', DDPTable::SORT_ASC)
              ->paginate()
              ->build();
    echo $reqBind->getTable();
}

function getMOsGraphQueryList($typeId) {
    $queryList = array();

    $sources = array(
        'CLI',
        'NBI',
        'SCHEDULED'
    );

    //  Set up a base verison of the query that we can alter and add to.
    $whatBase = 'ROUND( total_mos / TIME_TO_SEC( TIMEDIFF( export_end_date_time,IF(export_start_date_time=0,
                  from_unixtime(unix_timestamp(export_end_date_time)-cm_export.elapsedTime),
                  export_start_date_time) ) ), 0)';

    $queryBase = array (
        SqlPlotParam::TIME_COL => 'export_end_date_time',
        SqlPlotParam::WHAT_COL => array( $whatBase => 'label'),
        SqlPlotParam::TABLES => "cm_export, sites, cm_export_source_names, cm_export_filter_choice_names",
        SqlPlotParam::WHERE => "cm_export.siteid = sites.id
                    AND sites.name = '%s'
                    AND cm_export.source_nameid = cm_export_source_names.id
                    AND cm_export.filter_choice_nameid = cm_export_filter_choice_names.id
                    AND cm_export.total_mos IS NOT NULL",
        'qargs' => array( 'site')
    );

    if ( $typeId != '' ) {
        $queryBase[SqlPlotParam::WHERE] .= " AND cm_export.typeid = %s";
        $queryBase['qargs'] = array( 'site', TYPEID);
    }

    // Add a Filtered and No filter query for each source.
    foreach ( $sources as $source ) {
        $filteredQuery = $queryBase;
        $filteredQuery[SqlPlotParam::WHAT_COL][$whatBase] = "$source Filtered";
        $filteredQuery[SqlPlotParam::WHERE] .= "
                                  AND cm_export_source_names.name = '$source'
                                  AND cm_export_filter_choice_names.name NOT IN ('NO_FILTER', 'NA')";
        $queryList[] = $filteredQuery;

        $noFilterQuery = $queryBase;
        $noFilterQuery[SqlPlotParam::WHAT_COL][$whatBase] = "$source No Filter";
        $noFilterQuery[SqlPlotParam::WHERE] .= "
                                  AND cm_export_source_names.name = '$source'
                                  AND cm_export_filter_choice_names.name IN ('NO_FILTER', 'NA')";
        $queryList[] = $noFilterQuery;
    }

    // Add queries for other sources.
    $filteredQuery = $queryBase;
    $filteredQuery[SqlPlotParam::WHAT_COL][$whatBase] = "Other Filtered";
    $filteredQuery[SqlPlotParam::WHERE] .= "
                                 AND cm_export_source_names.name NOT IN ('" . implode('\', \'', $sources) . "')
                                 AND cm_export_filter_choice_names.name NOT IN ('NO_FILTER', 'NA')";
    $queryList[] = $filteredQuery;

    $noFilterQuery = $queryBase;
    $noFilterQuery[SqlPlotParam::WHAT_COL][$whatBase] = "Other No Filter";
    $noFilterQuery[SqlPlotParam::WHERE] .= "
                                 AND cm_export_source_names.name NOT IN ('" . implode('\', \'', $sources) . "')
                                 AND cm_export_filter_choice_names.name IN ('NO_FILTER', 'NA')";
    $queryList[] = $noFilterQuery;

    return $queryList;
}

function showMOsTypeFilterGraph($typeId) {
    global $date;
    $sqlParam = array(
        'title' => "MOs/Sec",
        'type' => 'xy',
        'ylabel' => "MOs/Sec",
        'useragg' => 'true',
        'persistent' => 'true',
        'querylist' => getMOsGraphQueryList($typeId)
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo "<p>" .
            $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                800,
                400,
                "typeid=$typeId"
            ) .
        "</p>\n";
}

function mainFlow() {

    getExportDailyTotals(dailyTotalsColumns());
    $typeId = requestValue(TYPEID);

    $links['jmxLink'] = makeFullGenJmxLink('importexportservice', 'JMX');
    $links['dpsLink'] = makeLink( '/TOR/dps.php', 'DPS', array('servers' => makeSrvList('importexportservice') ) );
    echo makeHTMLList($links);

    drawHeader("Export Performance", HEADER_2, "Export_Performance");
    echo '<table><tr><td valign="top">';
    echo '<div class="drill-down-table">';
    showMOsDrillDownTable($typeId);
    echo '</div>';
    echo '</td><td valign="top">';
    if ($typeId != '') {
        $typeName = getExportTypeName($typeId);
        echo "<p><b>Currently Showing Type: $typeName</b></p>";
    }

    showMOsTypeFilterGraph($typeId);
    echo "</td></tr></table>";

    /* Bulk CM Export */
    getBulkCMExport(bulkExportColumns());
}

$statsDB = new StatsDB();
mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

?>
