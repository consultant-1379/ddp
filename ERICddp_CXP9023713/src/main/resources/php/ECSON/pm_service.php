<?php
$pageTitle = "PM Services";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/common/kafkaFunctions.php";

function cellPipelineParams() {
    return array(
        array('eventsInPipeline', 'Events in Pipeline')
    );
}

function jdbcParams() {
    return array(
        array('jdbcUpdates', 'JDBC Updates'),
        array('failedJdbcUpdates', 'Failed Jdbc Updates')
    );
}

function pmEventsParams() {
    return array(
        array('incomingEvents', 'Incoming Events')
    );
}

function pmParsedEventsParams() {
    return array(
        array('parser_files', 'Parsed Files'),
        array('parser_events', 'Parsed Events')
    );
}

function pmGraphs($cmParams) {
    foreach ( $cmParams as $param ) {
        drawHeader($param[1], 2, $param[0]);
        $modelledGraph = new ModelledGraph("ECSON/pm/pm_" . $param[0]);
        plotgraphs( array( $modelledGraph->getImage() ) );
    }

}

function showLinks($serverIdsArr, $sg) {

    $links = array();
    $cellSubLinks = array();
    $jdbcSubLinks = array();
    $fileParserSubLinks = array();

    $cellSubTitle = makeAnchorLink('pmEventsCellPipeline', 'Cell Pipeline Events');
    $cellSubLinks[] =  makeAnchorLink("eventsInPipeline", 'Events in Pipeline');
    $links[] = $cellSubTitle. makeHTMLList($cellSubLinks);

    $jdbcSubTitle = makeAnchorLink('pmJdbcUpdates', 'JDBC Update Events');
    $jdbcSubLinks[] = makeAnchorLink('jdbcUpdates', 'JDBC Updates');
    $jdbcSubLinks[] = makeAnchorLink('failedJdbcUpdates', 'Failed Jdbc Updates');
    $links[] = $jdbcSubTitle. makeHTMLList($jdbcSubLinks);

    $parserSubTitle = makeAnchorLink('pmFileParserEvent', 'File Parser Events');
    $fileParserSubLinks[] = makeAnchorLink('parser_files', 'Parsed Files');
    $fileParserSubLinks[] = makeAnchorLink('parser_events', 'Parsed Events');
    $links[] = $parserSubTitle. makeHTMLList($fileParserSubLinks);

    $kafkalink = kafkaLinks($serverIdsArr, $sg);
    if ( $kafkalink ) {
        $links[] = $kafkalink;
    }

    echo makeHTMLList($links);

}

function mainFlow() {
    global $statsDB, $site, $date;

    $sg = array('eric-pm-events-processor-er', 'eric-event-data-collector');
    $srv = k8sGetServiceInstances( $statsDB, $site, $date, $sg);
    $serverIdsArr = array_values($srv);

    showLinks( $serverIdsArr, $sg );

    pmGraphs(pmEventsParams());

    $table = new ModelledTable( "ECSON/pm/pm_events_cell_pipeline", 'pmEventsCellPipeline' );
    echo $table->getTableWithHeader("Cell Pipeline Events");
    echo addLineBreak();

    pmGraphs(cellPipelineParams());

    $table = new ModelledTable( "ECSON/pm/pm_events_jdbcUpdates", 'pmJdbcUpdates' );
    echo $table->getTableWithHeader("JDBC Updates Events");
    echo addLineBreak();
    pmGraphs(jdbcParams());

    $table = new ModelledTable( "ECSON/pm/pm_file_parser_events", 'pmFileParserEvent' );
    echo $table->getTableWithHeader("File Parser Events");
    echo addLineBreak();

    pmGraphs(pmParsedEventsParams());

}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

