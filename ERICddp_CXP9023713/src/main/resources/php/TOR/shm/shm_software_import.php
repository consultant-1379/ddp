<?php
$pageTitle = "SHM Software Import";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';

const ENM_SHM_IMPORT_SOFTWARE_LOG = 'enm_shm_import_software_package_log';
const SW_PACKAGE = 'ne_up_ver';
const NE_TYPES = 'ne_types';
const NE_TYPES_NAME = 'ne_types.name';
const PACKAGE_NAME = 'ne_up_ver.name';
const CNT  = 'Count';
const COL_CNT = 'count';
const IMPORTED_SOFTWARE_PACKAGE_FILE_SIZE = 'Imported Software Package FileSize';
const IMPORTED_META_INFORMATION_FILES = 'Imported Meta Information Files';
const IMPORTED_SOFTWARE_PACKAGE = "importedSoftwarePackage";
const IMPORTED_SOFTWARE_PACKAGE_PER_NODE_TYPE = "importedSoftwarePackagePerNodeType";
const DOWNLOAD_RELEASE_NOTES = 'Downloaded Release Notes';

function fileSizeParams() {
    return array(
        SqlPlotParam::TITLE => IMPORTED_SOFTWARE_PACKAGE_FILE_SIZE,
        SqlPlotParam::Y_LABEL => 'FileSize(MB)',
        'cols' => array('fileSize' => 'filesize')
    );
}

function metaDataFileCountParams() {
    return array(
        SqlPlotParam::TITLE => IMPORTED_META_INFORMATION_FILES,
        SqlPlotParam::Y_LABEL => CNT,
        'cols' => array(COL_CNT => COL_CNT)
    );
}

function releaseNoteCountParams() {
    return array(
        SqlPlotParam::TITLE => DOWNLOAD_RELEASE_NOTES,
        SqlPlotParam::Y_LABEL => CNT,
        'cols' => array(COL_CNT => COL_CNT)
    );
}

function plotShmImportGraphs( $cols, $dbtable ) {
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    $dbTables = array( $dbtable, NE_TYPES, StatsDB::SITES );
    $where = "$dbtable.siteid = sites.id AND sites.name = '%s' AND ne_types.id = $dbtable.netypeid";

    $title = $cols[SqlPlotParam::TITLE];
    $sqlParam = SqlPlotParamBuilder::init()
        ->title('%s')
        ->titleArgs(array('title'))
        ->forcelegend('true')
        ->type(SqlPlotParam::STACKED_BAR)
        ->yLabel($cols[SqlPlotParam::Y_LABEL])
        ->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            $cols['cols'],
            $dbTables,
            $where,
            array('site'),
            NE_TYPES_NAME
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 320, "title=$title");
    plotGraphs($row);
}

function importedSoftwarePackageTable( $dbtable ) {
    global $statsDB;
    $where = $statsDB->where( $dbtable );
    $where .= " AND $dbtable.packageid = ne_up_ver.id AND $dbtable.netypeid = ne_types.id";
    $tables = array( $dbtable, SW_PACKAGE, NE_TYPES, StatsDB::SITES );

    $table = SqlTableBuilder::init()
        ->name(IMPORTED_SOFTWARE_PACKAGE)
        ->tables($tables)
        ->where($where)
        ->addSimpleColumn(NE_TYPES_NAME, 'Node Type')
        ->addSimpleColumn(PACKAGE_NAME, 'Package Name')
        ->addSimpleColumn('TIMEDIFF(time(time), SEC_TO_TIME(ROUND(totalTime/1000)))', 'Start Time')
        ->addSimpleColumn('time(time)', 'End Time')
        ->addColumn('time', 'totalTime', 'Total Time taken', DDPTable::FORMAT_MSEC)
        ->addSimpleColumn('fileSize', 'Software Package Size(MB)')
        ->addSimpleColumn('result', 'Result')
        ->addSimpleColumn('importingFrom', 'Location')
        ->paginate()
        ->dbScrolling()
        ->build();

    if ( $table->hasRows() ) {
        echo drawHeader("Imported Software Package", HEADER_1, IMPORTED_SOFTWARE_PACKAGE);
        echo $table->getTable();
    }
}

function importedSoftwarePackagePerNodeTypeTable( $dbtable ) {
    global $statsDB;
    $where = $statsDB->where( $dbtable );
    $where .= " AND $dbtable.netypeid = ne_types.id GROUP BY ne_types.name";
    $tables = array( $dbtable, NE_TYPES, StatsDB::SITES );

    $table = SqlTableBuilder::init()
        ->name(IMPORTED_SOFTWARE_PACKAGE_PER_NODE_TYPE)
        ->tables($tables)
        ->where($where)
        ->addSimpleColumn(NE_TYPES_NAME, 'Node Type')
        ->addSimpleColumn(StatsDB::ROW_COUNT, CNT)
        ->addSimpleColumn('MIN(fileSize)', 'File Min Size(MB)')
        ->addSimpleColumn('MAX(fileSize)', 'File Max Size(MB)')
        ->addSimpleColumn('AVG(fileSize)', 'Average file size in MB')
        ->addColumn('time', 'AVG(totalTime)', 'Average Time taken', DDPTable::FORMAT_MSEC)
        ->build();

    if ( $table->hasRows() ) {
        echo drawHeader("Imported Software Package Per Node Type", HEADER_1, IMPORTED_SOFTWARE_PACKAGE_PER_NODE_TYPE);
        echo $table->getTable();
    }
}

function showLinks($hasImpSofLog, $hasMetaLog, $hasReleaseNoteLog, $hasHousekeepingTimings, $hasHousekeepingDetails) {
    $links = array();
    if ( $hasImpSofLog ) {
        $links[] = makeAnchorLink(IMPORTED_SOFTWARE_PACKAGE, "Imported Software Package");
        $links[] = makeAnchorLink(IMPORTED_SOFTWARE_PACKAGE_PER_NODE_TYPE, "Imported Software Package Per Node Type");
        $links[] = makeAnchorLink("shmImportPackageFileSize", IMPORTED_SOFTWARE_PACKAGE_FILE_SIZE);
    }
    if ( $hasMetaLog ) {
        $links[] = makeAnchorLink("shmImportMetaCount", IMPORTED_META_INFORMATION_FILES);
    }
    if ( $hasReleaseNoteLog ) {
        $links[] = makeAnchorLink("shmImportReleaseNotes", DOWNLOAD_RELEASE_NOTES);
    }
    if ( $hasHousekeepingTimings ) {
        $links[] = makeAnchorLink("housekeeping_timings", "Housekeeping Function Timings");
    }
    if ( $hasHousekeepingDetails ) {
        $links[] = makeAnchorLink("housekeeping_details", "Housekeeping Function Details");
    }
    echo makeHTMLList($links);
}

function mainFlow() {
    global $statsDB;
    $hasImpSofLog = $statsDB->hasData(ENM_SHM_IMPORT_SOFTWARE_LOG);
    $hasMetaLog = $statsDB->hasData(enm_shm_metadatafilecount_log);
    $hasReleaseNoteLog = $statsDB->hasData(enm_shm_releasenotecount_log);
    $hasHousekeepingTimings = $statsDB->hasData(enm_housekeeping_function_timings);
    $hasHousekeepingDetails = $statsDB->hasData(enm_housekeeping_function_details);

    showLinks($hasImpSofLog, $hasMetaLog, $hasReleaseNoteLog, $hasHousekeepingTimings, $hasHousekeepingDetails);

    importedSoftwarePackageTable( ENM_SHM_IMPORT_SOFTWARE_LOG );
    importedSoftwarePackagePerNodeTypeTable( ENM_SHM_IMPORT_SOFTWARE_LOG );
    if ( $hasImpSofLog ) {
        drawHeader( IMPORTED_SOFTWARE_PACKAGE_FILE_SIZE, HEADER_2, 'shmImportPackageFileSize' );
        $importFileSize = fileSizeParams();
        plotShmImportGraphs( $importFileSize, ENM_SHM_IMPORT_SOFTWARE_LOG );
    }
    if ( $hasMetaLog ) {
        drawHeader( IMPORTED_META_INFORMATION_FILES, HEADER_2, 'shmImportMetaCount' );
        $importMetaDataCount = metaDataFileCountParams();
        plotShmImportGraphs( $importMetaDataCount, 'enm_shm_metadatafilecount_log' );
    }
    if ( $hasReleaseNoteLog ) {
        drawHeader( DOWNLOAD_RELEASE_NOTES, HEADER_2, 'shmImportReleaseNotes' );
        $importReleaseNoteCount = releaseNoteCountParams();
        plotShmImportGraphs( $importReleaseNoteCount, 'enm_shm_releasenotecount_log' );
    }
    if ( $hasHousekeepingTimings ) {
        $table = new ModelledTable( "TOR/shm/enm_housekeeping_function_timings", 'housekeeping_timings' );
        echo $table->getTableWithHeader("Housekeeping Function Timings");
    }
    if ( $hasHousekeepingDetails ) {
        $table = new ModelledTable( "TOR/shm/enm_housekeeping_function_details", 'housekeeping_details' );
        echo $table->getTableWithHeader("Housekeeping Function Details");
    }
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

