<?php
$pageTitle = "DDP Status Report";

ob_start();

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

const SET_STRT = 'setStart';
const SET_END = 'setEnd';
const LOCATION = 'location: ';
const MAKESTATS_CAPACITY = 'Makestats Capacity';

function createForm() {
    global $date;

    $day = substr($date, 8);
    $month = substr($date, 5, 2);
    $year = substr($date, 0, 4);

    drawHeader("Select a date range", 2, "");
    if ( issetURLParam('invalidDates') ) {
        echo "Start Date Must Be Before End Date";
    }
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('getDateRange', 'POST', '?' . fromServer('QUERY_STRING') );

    $dateParams = array('format' => 'd-m-Y', 'minYear' => $year, 'maxYear' => $year-1);

    $start = $form->addElement('date', SET_STRT, null, $dateParams);
    $start->setLabel('Select Start Date:');
    $form->setDefaults(array(SET_STRT => array('Y' => $year, 'm' => $month, 'd' => $day)));

    $end = $form->addElement('date', SET_END, null, $dateParams);
    $end->setLabel('Select End Date:');
    $form->setDefaults(array(SET_END => array('Y' => $year, 'm' => $month, 'd' => $day)));

    $btn = $form->addElement('submit', 'null', 'Submit');
    $form->addGroup( array($btn), null, null, '&nbsp;' );

    if ($form->validate()) {
        $form->freeze();
        $form->process('processData', false);
    }
    $form->display();
}

function processData($values) {
    $start = formatDate( $values[SET_STRT] );
    $end = formatDate( $values[SET_END] );
    $sDt = strtotime($start);
    $eDt = strtotime($end);
    $reqUri = fromServer('REQUEST_URI');

    if ( $eDt < $sDt ) {
        if ( strpos($reqUri, 'invalidDates') === false ) {
            header(LOCATION . $reqUri . '&invalidDates');
        } else {
            header(LOCATION . $reqUri);
        }
    } else {
        $days = round(($eDt - $sDt) / (60 * 60 * 24)) + 1;
        header(LOCATION . $reqUri . '&start=' . $start . '&end=' . $end . '&days=' . $days);
    }
}

function formatDate( $parts ) {
    $day = $parts['d'];
    $month = $parts['m'];
    $year = $parts['Y'];
    if (strlen($day) === 1) {
        $day = "0$day";
    }
    if (strlen($month) === 1) {
        $month = "0$month";
    }
    return $year . "-" . $month . "-" . $day;
}

function slowQueries( $start, $end, $days, &$data ) {
    global $statsDB;

    $sql = "
SELECT
    SUM(count) AS 'SlowQueries',
    (SUM(count)/$days) AS 'DailyAverage'
FROM
    ddpadmin.slow_queries
WHERE
    date BETWEEN '$start' AND '$end'";

    $statsDB->query($sql);
    $row = $statsDB->getNextRow();

    $data[] = array( 'msg' => 'Total Slow Queries', 'cnt' => $row[0] );
    $data[] = array( 'msg' => 'Daily Average Slow Queries', 'cnt' => $row[1] );
}

function getVolIds( $srvId, $type ) {
    global $statsDB;

    $ids = array();

    $sql = "
SELECT
    DISTINCT(volumes.id)
FROM
    volumes,
    volume_stats
WHERE
    volumes.id = volume_stats.volid AND
    volume_stats.serverid = $srvId AND
    volumes.name = '$type'";

    $statsDB->query($sql);

    while ( $row = $statsDB->getNextRow() ) {
        $ids[$srvId] = $row[0];
    }

    return $ids;
}

function buildBaseQuery($date) {
    return "
SELECT
    tn.name AS tbl,
    ROUND( AVG(ts.data) / 1024 / 1024, 3) AS data,
    ROUND( AVG(ts.idx) / 1024 / 1024, 3) AS idx,
    ROUND( AVG(ts.avglen), 0 ) AS avgLen,
    ROUND( AVG(ts.data / ts.avglen), 0) AS 'approxRows',
    tn.id AS tableid
FROM
    ddpadmin.ddp_table_stats AS ts,
    ddpadmin.ddp_table_names AS tn
WHERE
    ts.tableid = tn.id AND ts.date = '$date'
GROUP BY ts.tableid";
}

function dbTableGrowth( $start, $end, $showZero ) {
    global $statsDB;
    $data = array();

    $startQuery = buildBaseQuery($start);
    $endQuery = buildBaseQuery($end);

    $sql = "
SELECT
    start.tbl AS tbl,
    (start.data - end.data) AS diffDataSize,
    (start.idx - end.idx) AS diffIndexSize,
    (start.avgLen - end.avgLen) AS diffAvgLenSize,
    (start.approxRows - end.approxRows) AS diffApproxRowSize
FROM
    ($startQuery) AS start,
    ($endQuery) AS end
WHERE
    start.tableid = end.tableid
";
    if ( $showZero ) {
        $sql .= "HAVING diffApproxRowSize = 0";
    } else {
        $sql .= "HAVING diffApproxRowSize != 0";
    }

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $data[] = $row;
    }
    drawDBGrowthTable($data);
}

function drawDBGrowthTable($data) {

    $cols = array(
        array( DDPTable::KEY => 'tbl', DDPTable::LABEL => 'Table' ),
        array( DDPTable::KEY => 'diffDataSize', DDPTable::LABEL => 'Data Growth (Mb)', 'type' => 'int' ),
        array( DDPTable::KEY => 'diffIndexSize', DDPTable::LABEL => 'Index Growth (Mb)', 'type' => 'int' ),
        array( DDPTable::KEY => 'diffAvgLenSize', DDPTable::LABEL => 'Avg Row Length Growth (B)', 'type' => 'int'),
        array( DDPTable::KEY => 'diffApproxRowSize', DDPTable::LABEL => '# of Rows Growth (Approx)', 'type' => 'int' )
    );
    $rpp = array(
        'rowsPerPage' => 10,
        'rowsPerPageOptions' => array(
            25,
            50,
            100
        )
    );
    $data = sortDDPTableData( $data, 'tbl', SORT_ASC );
    $table = new DDPTable(
        "dbTableGrowth",
        $cols,
        array( 'data' => $data ),
        $rpp
    );
    drawHeader("DB Table Growth", 1, '');
    echo $table->getTable();
}

function dataTable( $data ) {
    $cols = array(
        array( DDPTable::KEY => 'msg', DDPTable::LABEL => 'Message' ),
        array( DDPTable::KEY => 'cnt', DDPTable::LABEL => 'Count' )
    );
    $table = new DDPTable(
        "data",
        $cols,
        array( 'data' => $data )
    );
    echo $table->getTable();
}

function plotModelledGraphs( $params, $startTime, $endTime ) {
    $graphs = array();
    foreach ( $params as $param ) {
        $modelledGraph = new ModelledGraph('DDP/' . $param);
        $graphs[] = $modelledGraph->getImage(array(), $startTime, $endTime);
    }
    plotGraphs( $graphs );
}

function serverGraphs( $startTime, $endTime, $type ) {
    global $statsDB, $site;

    $graphs = array();

    $str = str_replace('LMI_', '', $site);
    if ( $type === 'data/db') {
        $str .= "-db";
    }
    drawHeader("$str", 1, '');

    $srvId = getServerId($statsDB, $site, $str);
    $volIds = getVolIds( $srvId, $type );

    if ( isset($volIds[$srvId]) ) {
        $params = array( 'serverid' => $srvId, 'volids' => $volIds[$srvId] );
        $volumeUsedGraph = new ModelledGraph('common/volume_used');
        $volumePercentGraph = new ModelledGraph('common/volume_freeInpercent');
        $graphs[] = $volumeUsedGraph->getImage($params, $startTime, $endTime);
        $graphs[] = $volumePercentGraph->getImage($params, $startTime, $endTime);
    }

    $params = array( 'serverid' => $srvId);
    $modelledGraph = new ModelledGraph('common/cpu_usage_daily_avg');
    $graphs[] = $modelledGraph->getImage($params, $startTime, $endTime);

    plotgraphs($graphs);
}

function makestatsCapacity($startTime, $endTime) {
    global $AdminDB;

    drawHeaderWithHelp(MAKESTATS_CAPACITY, 1, "makestats_capacity");
    $msEnd = "$AdminDB.ddp_makestats.endproc";
    $msStart = "$AdminDB.ddp_makestats.beginproc";
    $msDur = "SUM( TIME_TO_SEC( TIMEDIFF( $msEnd, $msStart ) ) )";
    $maintDur = "$AdminDB.ddp_maintenance_times.duration";
    $potentailMsTime = "( (24 * 60 * 60) * $AdminDB.workers.max_jobs)";
    $col = "$msDur / ( $potentailMsTime - $maintDur )";
    $dbTables = array( "$AdminDB.ddp_makestats", "$AdminDB.workers", "$AdminDB.ddp_maintenance_times" );
    $where = "DATE($AdminDB.ddp_makestats.beginproc) =
              DATE($AdminDB.ddp_maintenance_times.startTime)
              AND $AdminDB.ddp_makestats.endproc IS NOT NULL
              GROUP BY DATE($AdminDB.ddp_makestats.beginproc)";
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();

    $sqlParam = SqlPlotParamBuilder::init()
              ->title(MAKESTATS_CAPACITY)
              ->type('sb')
              ->barwidth(60)
              ->yLabel("%")
              ->makePersistent()
              ->addQuery(
                  'beginproc',
                  array($col => MAKESTATS_CAPACITY),
                  $dbTables,
                  $where,
                  array()
              )
              ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs[] = $sqlParamWriter->getImgURL( $id, "$startTime", "$endTime", true, 640, 320 );

    plotGraphs($graphs);
}

function mainFlow( $start, $end, $days ) {
    global $statsDB, $site;

    $startTime = $start . " 00:00:00";
    $endTime = $end . " 23:59:59";

    $tableData = array();
    slowQueries( $start, $end, $days, $tableData );
    dataTable($tableData);
    echo addLineBreak(2);

    serverGraphs( $startTime, $endTime, 'data' );
    echo addLineBreak();
    serverGraphs( $startTime, $endTime, 'data/db' );
    echo addLineBreak(2);

    drawHeader( "MakeStats Content", 2, "makeStats");
    $makeStatsParams = array(
        'proc_dur_daily_avg',
        'proc_dur_daily_max',
        'proc_delay_daily_avg',
        'file_size_daily_sum',
        'files_uploaded_daily_cnt'
    );

    plotModelledGraphs( $makeStatsParams, $startTime, $endTime );

    makestatsCapacity($startTime, $endTime);

    drawHeader( "Upgrade Time", 2, "upgradeTime");
    plotModelledGraphs( array("upgrade_time"), $startTime, $endTime );

    drawHeader( "Maintenance Time", 2, "maintenanceTime");
    plotModelledGraphs( array("maintenance_time"), $startTime, $endTime );

    drawHeader( "Slow queries", 2, "slowqueries");
    plotModelledGraphs( array("slow_queries"), $startTime, $endTime );

    drawHeader( "Active Sites", 2, "activesites");
    plotModelledGraphs( array("active_sites"), $startTime, $endTime );

    dbTableGrowth( $start, $end, null );
}

if ( !issetURLParam('dir') || !issetURLParam('date') ) {
    echo "ERROR: Please select a date from the calendar.";
} elseif ( issetURLParam('start') && issetURLParam('end') ) {
    $start = requestValue('start');
    $end = requestValue('end');
    $days = requestValue('days');

    if ( issetURLParam('staticTables') ) {
        dbTableGrowth( $start, $end, 'true' );
    } else {
        mainFlow( $start, $end, $days );
    }
} else {
    createForm();
}

include_once PHP_ROOT . "/common/finalise.php";

