<?php

if (isset($_GET["partitions"])) { //NOSONAR
    $UI = false;
} else {
    $topicsStr = $_GET['topics']; //NOSONAR
    $pageTitle = "Kafka";
}

include_once "../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

require_once 'HTML/Table.php';

const PARTNUM = 'partnum';
const HOSTNAME = 'servers.hostname';
const LABEL = 'label';
const TITLE = 'title';
const YLABEL = 'ylabel';
const USERAGG = 'useragg';
const FLS = 'false';
const PERSISTENT = ' persistent';
const QUERYLIST = 'querylist';
const TIMECOL = 'timecol';
const WHATCOL = 'whatcol';
const TABLES = 'tables';
const WHERE = 'where';
const QARGS = 'qargs';
const TOPIC = 'topic';
const LOGOFFSET = 'logOffset';
const ENM_KAFKA_SRV_SITES_AND_SERVERS = "enm_kafka_srv , sites, servers";


$colList = array(
    "MBytesIn", "MBytesOut", "MessagesIn",
    "TotalFetchRequests", "TotalProduceRequests",
    "FailedProduceRequests", "BytesRejected", "FailedFetchRequests"
);

function showTopicTable($topic, $site, $date) {
    global $colList;

    $where = "
enm_kafka_topic.siteid = sites.id AND sites.name = '$site' AND
enm_kafka_topic.serverid = servers.id AND
enm_kafka_topic.topicid = kafka_topic_names.id AND kafka_topic_names.name = '$topic' AND
enm_kafka_topic.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
    $tableCols = array(
        array( 'key' => 'server', 'db' => 'IFNULL(servers.hostname,"Totals")', LABEL => 'Instance' )
    );

    foreach ( $colList as $col ) {
        $tableCols[] =
                     array(
                         'key' => $col,
                         'db' => 'SUM(' . $col . ')',
                         LABEL => $col,
                         'formatter' => 'ddpFormatNumber'
                     );
    }

    $outTable = new SqlTable(
        'enm_kafka_topic_' . $topic,
        $tableCols,
        array( 'enm_kafka_topic', 'kafka_topic_names', 'sites', 'servers'),
        $where . " GROUP BY servers.hostname WITH ROLLUP ",
        true,
        array( )
    );
    drawHeaderWithHelp("Daily Totals", 3, "Daily_Totals");
    echo $outTable->getTable();
}

function showTopicGraphs($topic, $date) {

    $rows = array(
        array( "MessagesIn" ),
        array( "MBytesIn", "MBytesOut" ),
        array( "TotalProduceRequests", "TotalFetchRequests" ),
        array( "FailedProduceRequests", "BytesRejected", "FailedFetchRequests" )
    );
    $where = "
enm_kafka_topic.siteid = sites.id AND sites.name = '%s' AND
enm_kafka_topic.topicid = kafka_topic_names.id AND kafka_topic_names.name = '%s'";


    $sqlParamWriter = new SqlPlotParam();
    foreach ( $rows as $row ) {
        $tableRow = array();
        $width = 900 / count($row);
        foreach ( $row as $col ) {
            $sqlPlotParam =
                          array(
                              TITLE => "$col",
                              'type' => 'tsc',
                              YLABEL => $col,
                              USERAGG => FLS,
                              PERSISTENT => FLS,
                              'presetagg' => 'SUM:Per Minute',
                              USERAGG => 'true',
                              QUERYLIST => array(
                                  array(
                                      TIMECOL => 'time',
                                      WHATCOL => array( $col => $col ),
                                      TABLES  => "enm_kafka_topic, kafka_topic_names, sites",
                                      WHERE => $where,
                                      QARGS => array('site', TOPIC)
                                  )
                              )
                          );
            $id = $sqlParamWriter->saveParams($sqlPlotParam);
            $url =
                $sqlParamWriter->getImgURL(
                    $id,
                    $date . " 00:00:00",
                    $date . " 23:59:59",
                    true,
                    $width,
                    320,
                    "topic=$topic"
                );

            $tableRow[] = $url;
        }
        $graphs = new HTML_Table('border=0');
        $graphs->addRow($tableRow);
        echo $graphs->toHTML();
    }
}

function showTopicPartitions($topic, $site, $date) {
    global $statsDB, $webargs;

    $where = "
enm_kafka_topic_partitions.siteid = sites.id AND sites.name = '$site' AND
enm_kafka_topic_partitions.topicid = kafka_topic_names.id AND kafka_topic_names.name = '$topic' AND
enm_kafka_topic_partitions.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
";
    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_kafka_topic_partitions, sites, kafka_topic_names
WHERE $where");
    $hasPartitionStats = $row[0] > 0;
    if ( ! $hasPartitionStats ) {
        return;
    }

    drawHeaderWithHelp("Partitions", 3, "Partitions");

    $sqlParamWriter = new SqlPlotParam();
    $graphWhere = "
enm_kafka_topic_partitions.siteid = sites.id AND sites.name = '%s' AND
enm_kafka_topic_partitions.topicid = kafka_topic_names.id AND kafka_topic_names.name = '%s'";
    $sqlParam =
              array(
                  TITLE => 'Partition Distribution',
                  'type' => 'cat',
                  YLABEL => '',
                  USERAGG => FLS,
                  'presetagg' => 'SUM:Per Minute',
                  PERSISTENT => FLS,
                  QUERYLIST => array(
                      array(
                          TIMECOL => 'time',
                          'cat.column' => PARTNUM,
                          WHATCOL => array( LOGOFFSET => LOGOFFSET ),
                          TABLES => "enm_kafka_topic_partitions, sites, kafka_topic_names",
                          WHERE => $graphWhere,
                          QARGS => array( 'site', TOPIC )
                      ),
                  )
              );
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 960, 500, "topic=$topic");

    $row = $statsDB->queryRow("
SELECT SUM(logOffset)
FROM enm_kafka_topic_partitions, kafka_topic_names, sites
WHERE $where");
    $total = $row[0];
    $outTable = new SqlTable(
        $topic . "_partitions",
        array(
            array( 'key' => PARTNUM, 'db' => PARTNUM, LABEL => 'Partition' ),
            array(
                'key' => 'logOffsetPercent',
                'db' => "ROUND((SUM(logOffset)*100)/$total, 1)",
                LABEL => 'Total logOffset %'
            ),
            array( 'key' => LOGOFFSET, 'db' => 'SUM(logOffset)', LABEL => 'Total logOffset' )
        ),
        array('enm_kafka_topic_partitions', 'kafka_topic_names', 'sites'),
        $where . " GROUP BY partnum",
        true,
        array(
            'rowsPerPage' => 10,
            'rowsPerPageOptions' => array( 1000 ),
            'ctxMenu' => array('key' => 'partitions',
                               'multi' => true,
                               'menu' => array( 'plot' => 'Plot' ),
                               'url' => fromServer('PHP_SELF') . "?" . $webargs . "&topic=$topic",
                               'col' => PARTNUM
            )
        )
    );
    echo $outTable->getTable();
}

function plotPartitions($topic, $partitions) {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $graphWhere = "
enm_kafka_topic_partitions.siteid = sites.id AND sites.name = '%s' AND
enm_kafka_topic_partitions.topicid = kafka_topic_names.id AND kafka_topic_names.name = '%s' AND
enm_kafka_topic_partitions.partnum IN (%s)";
    $sqlPlotParam =
                  array(
                      TITLE => "logOffset",
                      'type' => 'tsc',
                      YLABEL => "",
                      USERAGG => 'true',
                      PERSISTENT => FLS,
                      QUERYLIST
                      => array(
                          array(
                              TIMECOL => 'time',
                              SqlPlotParam::MULTI_SERIES => PARTNUM,
                              WHATCOL =>
                              array(
                                  LOGOFFSET => LOGOFFSET,
                              ),
                              TABLES => "enm_kafka_topic_partitions, kafka_topic_names, sites",
                              WHERE => $graphWhere,
                              QARGS => array('site', TOPIC, 'pids')
                          )
                      )
                  );
    $id = $sqlParamWriter->saveParams($sqlPlotParam);
    $url = $sqlParamWriter->getURL($id, $date . " 00:00:00", $date . " 23:59:59", "topic=$topic&pids=$partitions");
    header("Location:" . $url);
    exit;
}

function drawGraphGroup($group, $graphParam) {
    $graphs = array();
    getGraphsFromSet( $group, $graphs, 'common/enm_kafka_srv', $graphParam, 640, 320 );
    plotgraphs( $graphs );
}

function showBrokers($serverIds) {
    $serverIdsStr = implode(",", $serverIds);
    drawHeaderWithHelp("Brokers", 1, "Brokers");
    $graphParam = array( 'serverids' => $serverIdsStr );
    drawGraphGroup('msg', $graphParam);
    drawGraphGroup('requestHandlerAvgIdlePercent', $graphParam);
    drawGraphGroup('networkProcessorAvgIdlePercent', $graphParam);
}

function validTopics( $topics ) {
    global $statsDB, $date, $site;
    $validTopics = array();

    foreach ( $topics as $topic ) {
        $query = "
SELECT
    COUNT(*)
FROM
    enm_kafka_topic, kafka_topic_names, sites
WHERE
    enm_kafka_topic.siteid = sites.id AND
    sites.name = '$site' AND
    enm_kafka_topic.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
    enm_kafka_topic.topicid = kafka_topic_names.id AND
    kafka_topic_names.name = '$topic'";
        $count = $statsDB->queryRow($query);

        if ( $count[0] > 0 ) {
            $validTopics[] = $topic;
        }
    }

    return $validTopics;
}

function mainFlow($statsDB, $topics) {
    global $site, $date;

    $firstTopic = $topics[0];

    $statsDB->query("
SELECT DISTINCT(enm_kafka_topic.serverid)
FROM enm_kafka_topic, sites, kafka_topic_names
WHERE
 enm_kafka_topic.siteid = sites.id AND sites.name = '$site' AND
 enm_kafka_topic.topicid = kafka_topic_names.id AND kafka_topic_names.name = '$firstTopic' AND
 enm_kafka_topic.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    $serverIds = array();
    while ($row = $statsDB->getNextRow()) {
        $serverIds[] = $row[0];
    }

    $serverIdsStr = implode(",", $serverIds);
    if (!$serverIdsStr) {
       return;
    }
    $row = $statsDB->queryRow("
SELECT COUNT(*) FROM enm_kafka_srv, sites
WHERE
 enm_kafka_srv.siteid = sites.id AND sites.name = '$site' AND
 enm_kafka_srv.serverid IN ( $serverIdsStr ) AND
 enm_kafka_srv.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    $hasSrv = $row[0] > 0;

    if ( $hasSrv ) {
        showBrokers($serverIds);
    }

    $validTopics = validTopics( $topics );

    echo "<H1>Topics</H1>\n";
    $topicURLs = array();
    if ( count($validTopics) > 1 ) {
      foreach ( $validTopics as $topic ) {
          $topicURLs[] = makeAnchorLink($topic, ucfirst($topic));
      }
    }
    echo makeHTMLList($topicURLs);

    foreach ( $validTopics as $topic ) {
        echo "<H2 id=\"$topic\">" . ucfirst($topic) . "</H2>\n";
        showTopicTable($topic, $site, $date);
        echo addLineBreak();
        showTopicGraphs($topic, $date);
        showTopicPartitions($topic, $site, $date);
    }
}

$partitions = getArgs('partitions');

if ( $partitions ) {
    $topic = getArgs(TOPIC);
    $selected = getArgs('selected');
    plotPartitions($topic, $selected);
} else {
    $topics = explode(",", $topicsStr);
    mainFlow($statsDB, $topics);
}

include_once PHP_ROOT . "/common/finalise.php";
