<?php

require_once PHP_ROOT . "/SqlPlotParam.php";
const IDX = 'index';

function showSyncStatusEvents( $instType ) {
    global $rootdir, $debug, $date;

    $phpSelf = fromServer('PHP_SELF');
    $queryString = fromServer('QUERY_STRING');

    $seriesFile = $rootdir . "/cm/syncStatus_" . $instType . "_event.json";
    $help = "syncStatus_Events_new";
    if ( !file_exists($seriesFile) ) {
        $seriesFile = $rootdir . "/cm/syncStatus_" . $instType . ".json";
        $help = "syncStatus_Events_old";
    }

    if ( $debug > 0 ) {
        debugMsg("showSyncStatusEvents seriesFile=$seriesFile");
    }

    if ( file_exists($seriesFile) ) {
        drawHeader("syncStatus Events", 1, $help);

        echo "<p>Click <a href=$phpSelf?$queryString&instType=$instType&shownodeindex=1>
                 here</a> to see the nodes corresponding to the position on vertical axis.</p>";

        $sqlParamWriter = new SqlPlotParam();
        $sqlParam = SqlPlotParamBuilder::init()
                            ->title("syncStatus Events")
                            ->type(SqlPlotParam::XY)
                            ->yLabel("Node")
                            ->disableUserAgg()
                            ->seriesFromFile($seriesFile)
                            ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        echo "<p>" . $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 400) . "</p>\n";
    }
}

function showNodeIndex( $instType ) {
    global $rootdir;

    $rowDataFile = $rootdir . "/cm/index_" . $instType . "_event.json";
    if ( !file_exists($rowDataFile) ) {
        $rowDataFile = $rootdir . "/cm/index_$instType.json";
    }
    $rowData = json_decode(file_get_contents($rowDataFile), true); //NOSONAR

    //Sort $rowData in the ascending order of the serial no.s (index)
    $rowDataAssArray = array();
    foreach ( $rowData as $row ) {
        $rowDataAssArray[$row[IDX]] = $row;
    }
    $rowData = array();
    ksort($rowDataAssArray, SORT_NUMERIC);
    foreach ( $rowDataAssArray as $row ) {
        $rowData[] = $row;
    }

    $table = new DDPTable(
        "nodeindex",
        array(
            array( DDPTable::KEY => IDX, DDPTable::LABEL => 'Serial No.', 'type' => 'int' ),
            array( DDPTable::KEY => 'ne', DDPTable::LABEL => 'NE' )
            ),
        array( 'data' => $rowData ),
        array(
            DDPTable::ORDER => array( 'by' => IDX, 'dir' => 'ASC' )
            )
    );
    drawHeader(strtoupper($instType)." Network Element Index", 2, 'Network_Element_Index');
    echo $table->getTable();
}

