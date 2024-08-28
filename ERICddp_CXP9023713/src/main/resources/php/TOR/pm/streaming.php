<?php
$pageTitle = "Stream Termination and Parsing";

include "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

require_once 'HTML/Table.php';
const TITLE = "title";
const LABEL = "label";

$statsDB = new StatsDB();

function outputEventDistribution($totalEventCount) {
    global $site, $date;

    $json = json_decode(file_get_contents(dirname(__FILE__) . "/ebsl_event_map.json"), true);  // NOSONAR
    $map = array();
    foreach ( $json['events'] as $eventIdName ) {
        $map[$eventIdName['id']] = $eventIdName['name'];
    }
    $mapStr = json_encode($map, JSON_PRETTY_PRINT);
    echo <<<EOT
<script type="text/javascript">
var eventIdMap = $mapStr;

function getEventName(elCell, oRecord, oColumn, oData) {
    var eventId = oRecord.getData("eventid");
    if (eventId in eventIdMap) {
        elCell.innerHTML = eventIdMap[eventId];
    }
}

</script>

EOT;

    $where = "
 enm_esi_eventcounts.siteid = sites.id AND sites.name = '$site' AND
 enm_esi_eventcounts.date = '$date'";
    $outTable = SqlTableBuilder::init()
              ->name("enm_esi_eventcounts")
              ->where($where)
              ->tables(array( 'enm_esi_eventcounts', StatsDB::SITES))
              ->addColumn('eventid', 'eventid', 'Event Type Id' )
              ->addColumn('eventname', '""', 'Event Name', 'getEventName' )
              ->addColumn('percent', "ROUND( (eventcount*100/$totalEventCount),2)", 'Percent')
              ->paginate()
              ->sortBy('percent', DDPTable::SORT_DESC)
              ->build();

    drawHeaderWithHelp("LTE Event Type Distribution Table",2,"eventdistrib","DDP_Bubble_469_ENM_EVENT_TYPE_DISTRIBUTION");
    echo $outTable->getTable();
}

function outputEventDistributionNR($totalEventCount) {
    global $site, $date, $statsDB;

    $json = json_decode(file_get_contents(dirname(__FILE__) . "/nr_event_map.json"), true);  // NOSONAR
    $map = array();
    foreach ( $json['events'] as $eventIdName ) {
        $map[$eventIdName['id']] = $eventIdName['name'];
    }
    $mapStr = json_encode($map, JSON_PRETTY_PRINT);
    echo <<<ABC
<script type="text/javascript">
var eventIdMapNR = $mapStr;

function getEventNameNR(elCell, oRecord, oColumn, oData) {
    var eventIdNR = oRecord.getData("eventidNR");
    if (eventIdNR in eventIdMapNR) {
        elCell.innerHTML = eventIdMapNR[eventIdNR];
    }
}

</script>

ABC;
    $where = $statsDB->where('enm_nr_eventcounts', 'date', true);
    $outTableNR = SqlTableBuilder::init()
              ->name("enm_nr_eventcounts")
              ->where($where)
              ->tables(array( 'enm_nr_eventcounts', StatsDB::SITES))
              ->addColumn('eventidNR', "eventidNR", 'Event Type Id' )
              ->addColumn('eventname', '""', 'Event Name', 'getEventNameNR' )
              ->addColumn('percent', "ROUND( (eventcount*100/$totalEventCount),2)", 'Percent')
              ->paginate()
              ->sortBy('percent', DDPTable::SORT_DESC)
              ->build();

    drawHeader("NR Event Type Distribution Table", 2, "NR");
    echo $outTableNR->getTable();
}

function streamTableParams() {
    return array(
        "IFNULL(servers.hostname, 'Totals')" => "Instance",
        "SUM(events3)" => "LTE Events",
        "SUM(ROUND((kbytesProcessed3/1024),0))" => "LTE MBytes processed",
        "SUM(droppedConnections3)" => "LTE Dropped connections",
        "SUM(events2)" => "NR Events",
        "SUM(ROUND((kbytesProcessed2/1024),0))" => "NR MBytes processed",
        "SUM(droppedConnections2)" => "NR Dropped connections"
    );
}

function eventProducerDistributionNR($nrEventProducer, $totalEventCount) {
    global $site, $date, $statsDB;
    $rowData = array();
    $sql = "";
    foreach ( $nrEventProducer as $nrEvent => $nrValue ) {
        if ( !empty($sql) ) {
            $sql .="\nUNION ";
        }
        $sql .=
"SELECT
 '${nrValue}' as event,
 ROUND( ((sum(eventcount)/$totalEventCount)*100),3) AS count
FROM
 enm_nr_eventcounts,
 sites
WHERE
 enm_nr_eventcounts.siteid = sites.id AND
 sites.name = '$site' AND
 enm_nr_eventcounts.date = '$date' AND
 enm_nr_eventcounts.eventidNR LIKE '${nrEvent}%'";
    }

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }

    $table = new DDPTable(
        "nrEventProducerTable",
        array(
            array('key' => 'event', 'label' => 'Event Producer'),
            array('key' => 'count', 'label' => 'Percentage')
        ),
        array('data' => $rowData)
    );
    echo $table->getTable();
}

function streamGraphParams() {
     return array(
     array(
         'COL' => array(
             'events3' => 'LTE events',
             'events2' => 'NR events'),
         TITLE => "Events"
     ),
     array(
         'COL' => array(
             'kbytesProcessed3' => 'LTE KBytesProcessed',
             'kbytesProcessed2' => 'NR KBytesProcessed'),
         TITLE => "KBytes Processed"
     ),
     array(
         'COL' => array(
             'droppedConnections3' => 'LTE DroppedConnections',
             'droppedConnections2' => 'NR DroppedConnections'),
         TITLE => "Dropped Connections"
     ),
     array(
         'COL' => array(
             'activeConnections3' => 'LTE ActiveConnections',
             'activeConnections2' => 'NR ActiveConnections'),
         TITLE => "Active Connections"
     ),
     array(
         'COL' => array(
             'createdConnections3' => 'LTE CreatedConnections',
             'createdConnections2' => 'NR CreatedConnections'),
         TITLE => "Created Connections"
     )
 );

}

function makeTable($title, $params, $dbTable) {
    global $statsDB;

    $where = $statsDB->where($dbTable);
    $where .= " AND $dbTable.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $table = SqlTableBuilder::init()
        ->name($dbTable)
        ->tables(array( $dbTable, StatsDB::SITES, StatsDB::SERVERS))
        ->where($where);
        foreach ($params as $colName => $sql) {
            $table->addSimpleColumn($colName, $sql);
        }
        $table = $table->build();

        if ( $table->hasRows() ) {
            echo $table->getTableWithHeader("$title", 2, "", "", "");
        }
}

function makeGraphs($title, $graphParams, $dbTable) {
    global $date;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    echo addLineBreak();
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id";
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();
    $sqlParam = SqlPlotParamBuilder::init()
        ->title($title)
        ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
        ->yLabel($title)
        ->makePersistent()
        ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
        ->addQuery(
            SqlPlotParam::DEFAULT_TIME_COL,
            $graphParams,
            $dbTables,
            $where,
            array( 'site' )
        )
        ->build();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs[] = $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 800, 300);
    plotGraphs($graphs);
}

function mainFlow($statsDB) {
    global $date, $site, $webargs;

    $getserverIds = enmGetServiceInstances($statsDB, $site, $date, "eventparserdef");
    $serverIds = implode(",", array_values($getserverIds));
    $row = $statsDB->queryRow("
SELECT SUM(eventcount)
FROM
 enm_esi_eventcounts, sites
WHERE
 enm_esi_eventcounts.siteid = sites.id AND sites.name = '$site' AND
 enm_esi_eventcounts.date = '$date'"
);
   $totalEventCount = $row[0];

   $rowNR = $statsDB->queryRow("
SELECT SUM(eventcount)
FROM
 enm_nr_eventcounts, sites
WHERE
 enm_nr_eventcounts.siteid = sites.id AND sites.name = '$site' AND
 enm_nr_eventcounts.date = '$date'"
);
   $totalEventCountNR = $rowNR[0];

 $links = array();
 $links[] = makeAnchorLink("streamterm", "Stream Termination");
 $links[] = makeAnchorLink("parsing", "Parsing");
 $links[] = makeLink('/common/kafka.php', "Kafka", array('topics' => 'raw,decoded,celltrace5g'));
 $links[] = makeAnchorLink("eventdistrib_anchor", "LTE Event Type Distribution table");
 $links[] = makeAnchorLink("NR_anchor", "NR Event Type Distribution table");
 $links[] = makeAnchorLink("nrEventProducer", "NR Event Producer Distribution table");
 echo makeHTMLList($links);

 echo "<H1 id='streamterm'>Stream Termination </H1>\n";

 $streamTableinstrParams = streamTableParams();
 makeTable( "Daily Totals", $streamTableinstrParams, "enm_str_msstr" );
 $streamGraphinstrParams = streamGraphParams();
 foreach ($streamGraphinstrParams as $params) {
     $title = $params[TITLE];
     $graphParams = $params['COL'];
     makeGraphs( $title, $graphParams, "enm_str_msstr");
 }

  echo "<H1 id='parsing'>Parsing</H1>\n";
  $cols = array(
      'apeps' => array(
          array( 'key' => 'eventsIn',
                 'db' => 'eventsIn',
                 LABEL => 'Events Received'
          ),
          array( 'key' => 'eventsProcessed',
                 'db' => 'eventsProcessed',
                 LABEL => 'Events Processed'
          ),
          array( 'key' => 'eventsOut',
                 'db' => 'eventsOut',
                 LABEL => 'OutputAdapter Events Received'
          )
      )
  );

  $helpBubbles = array(
      'apeps' => 'DDP_Bubble_357_ENM_Streaming_apeps_Instr_Help'
  );

  foreach ( $cols as $type => $param ) {
      echo "<H1 id=\"$type\">$type</H1>\n";
      $dbTable = "enm_str_" . $type;
      $where = "
$dbTable.siteid = sites.id AND sites.name = '$site' AND
$dbTable.jvmid = enm_str_jvm_names.id AND
$dbTable.serverid = servers.id AND
$dbTable.serverid IN($serverIds) AND
$dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

      $tableCols = array(
          array(
              'key' => 'server',
              'db' => 'IFNULL(servers.hostname,"Totals")',
              LABEL => 'Instance'
          ),
          array(
              'key' => 'jvm',
              'db' => 'IFNULL( IF(enm_str_jvm_names.jvm_name = "", "NA", enm_str_jvm_names.jvm_name), "All" )',
              LABEL => 'JVM'
          )
      );
      foreach ( $param as $column ) {
          $tableCols[] = array(
              'key' => $column['key'],
              'db' => 'SUM(' . $column['db'] . ')',
              LABEL => $column[LABEL],
              'formatter' => 'ddpFormatNumber'
          );
      }

      $outTable = new SqlTable(
          "$dbTable",
          $tableCols,
          array( $dbTable, 'enm_str_jvm_names', 'sites', 'servers'),
          $where . " GROUP BY servers.hostname, enm_str_jvm_names.jvm_name WITH ROLLUP HAVING enm_str_jvm_names.jvm_name IS NOT NULL OR servers.hostname IS NULL ",
          TRUE,
          array( )
      );
      echo $outTable->getTableWithHeader("Daily Totals", 2, $helpBubbles[$type]);

      echo "<br>\n";

      $sqlParamWriter = new SqlPlotParam();
      $graphTable = new HTML_Table('border=0');

      $where = "$dbTable.siteid = sites.id AND
              servers.id = enm_str_apeps.serverid AND
              sites.name = '%s' AND
              servers.id IN (%s)";

      $tbls = "$dbTable, sites, servers";

      foreach ( $param as $column ) {
          $dbCol = $column['db'];
          $label = $column['label'];

          $sqlPlotParam =
                        array(
                            'title' => $label,
                            'type' => 'tsc',
                            'ylabel' => $label,
                            'useragg' => 'false',
                            'persistent' => 'false',
                            'presetagg' => 'SUM:Per Minute',
                            'useragg' => 'true',
                            'querylist'
                            => array(
                                array(
                                    'timecol' => 'time',
                                    'whatcol' => array( $dbCol => $label ),
                                    'tables' => $tbls,
                                    'where' => $where,
                                    'qargs' => array('site', 'servid')
                                )
                            )
                        );
          $id = $sqlParamWriter->saveParams($sqlPlotParam);
          $url =  $sqlParamWriter->getImgURL( $id,
                                              $date . " 00:00:00", $date . " 23:59:59",
                                              TRUE,
                                              800, 300,
                                              "servid=$serverIds"
          );
          $graphTable->addRow( array( $url ) );
      }
      echo $graphTable->toHTML();
      echo "<br/>\n";
  }

  if ( $totalEventCount > 0 ) {
      outputEventDistribution($totalEventCount);
  }
  if ( $totalEventCountNR > 0 ) {
      outputEventDistributionNR($totalEventCountNR);
      $nrEventProducer = array(2 => 'Du', 3 => 'CuCp', 4 => 'CuUp');
      drawHeader("NR Event Producer Distribution Table", 2, "nrEventProducer");
      eventProducerDistributionNR($nrEventProducer, $totalEventCountNR);
  }

}

mainFlow($statsDB);

include PHP_ROOT . "/common/finalise.php";
