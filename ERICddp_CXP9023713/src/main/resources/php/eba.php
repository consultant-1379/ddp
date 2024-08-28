<?php

$appName = $_GET['app'];

switch ($appName) {
case "rpmo":
    $appTable = "eba_rpmo";
    $nodeTable = "eba_rpmo_bsc";
    break;
case "ebss":
    $appTable = "eba_ebss";
    $nodeTable = "eba_ebss_sgsn";
    break;
case "ebsw":
    $appTable = "eba_ebsw";
    $nodeTable = "eba_ebsw_rnc";
    break;
default:
    $pageTitle = "EBA";
    include "common/init.php";
    echo "No such application:" . $appName;
    include "common/finalise.php";
    exit;
}

$pageTitle = "EBA " . strtoupper($appName);
include "common/init.php";
$statsDB = new StatsDB();

require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

function getEbswOverallGraphs($nodeTable,$date) {
  $graphURLs = array();

  $sqlParamWriter = new SqlPlotParam();

  $totalMbParam =
    array( 'title'      => "Total MB Read",
           'ylabel'     => "MB",
           'useragg'    => 'false',
           'presetagg'  => 'SUM:eba_mdc.id',
           'persistent' => 'false',
           'querylist' =>
           array(
                 array (
                        'timecol' => 'eba_mdc.begin_time',
                        'whatcol' => array( '(NumberOfBytesRead/1048576)' => 'MB Read' ),
                        'tables'  => "$nodeTable, eba_mdc, sites",
                        'where'   => "$nodeTable.mdc_id = eba_mdc.id AND sites.id = eba_mdc.siteid AND sites.name = '%s'",
                        'qargs'   => array( 'site' )
                        )
                 )
           );
  $id = $sqlParamWriter->saveParams($totalMbParam);
  $graphURLs[] = $sqlParamWriter->getImgURL( $id,
                                             "$date 00:00:00", "$date 23:59:59",
                                             true, 640, 240 );


  $maxTimeParam =
    array( 'title'      => "MaxRopParsingTime",
           'ylabel'     => "Seconds",
           'useragg'    => 'false',
           'presetagg'  => 'MAX:eba_mdc.id',
           'persistent' => 'false',
           'querylist' =>
           array(
                 array (
                        'timecol' => 'eba_mdc.begin_time',
                        'whatcol' => array( 'MaxRopParsingTime' => 'Max ROP Parsing Time' ),
                        'tables'  => "$nodeTable, eba_mdc, sites",
                        'where'   => "$nodeTable.mdc_id = eba_mdc.id AND sites.id = eba_mdc.siteid AND sites.name = '%s'",
                        'qargs'   => array( 'site' )
                       )
                 )
           );
  $id = $sqlParamWriter->saveParams($maxTimeParam);
  $graphURLs[] = $sqlParamWriter->getImgURL( $id,
                                             "$date 00:00:00", "$date 23:59:59",
                                             true, 640, 240 );

  return $graphURLs;
}

if (isset($_GET['node'])) {
    $node = $_GET['node'];
} else {
    $node = "";
}

function getStatCols($statsDB,$table) {
    $statsDB->query("DESCRIBE $table");
    $fields = array();
    while($row = $statsDB->getNextNamedRow()) {
      if ($row['Field'] != "mdc_id" && $row['Field'] != "moid_id") {
        $fields[] = $row['Field'];
      }
    }

    return $fields;
}

function getStatData($table, $node = "") {
    global $statsDB, $date, $appName, $dir, $oss, $site;
    $graphURLs = array();
    $graphURLs['links'] = array();
    $graphURLs['graphs'] = array();

    $sqlParamWriter = new SqlPlotParam();
    $fields = getStatCols($statsDB,$table);

    $filter = "";
    if ($node != "") {
        $filter = " AND " . $table . ".moid_id = eba_moid.id AND eba_moid.name = '" . $node . "'";
        $table = "$table" . "," . "eba_moid";
    }
    $filterForField="neun='" . $appName . "' AND siteid=sites.id AND mdc_id = eba_mdc.id AND sites.name = '%s'" . "$filter";

    foreach ($fields as $field) {
        // Do we have any metrics?
        $sql = "SELECT COUNT(" . $field . ") FROM " . $table . ",eba_mdc,sites WHERE begin_time BETWEEN " .
            "'" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND mdc_id = eba_mdc.id " . $filter .
            " AND sites.id = eba_mdc.siteid AND sites.name = '" . $site . "'";

        $row = $statsDB->queryRow($sql);
        if ($row[0] > 0) {

            $totalField =
              array( 'title'      => "$field",
                     'ylabel'     => "COUNT",
                     'useragg'    => 'false',
                     'persistent' => 'false',
                     'querylist' =>
                     array(
                           array (
                                  'timecol' => 'eba_mdc.begin_time',
                                  'whatcol' => array( "$field" => "$field" ),
                                  'tables'  => "$table, eba_mdc, sites",
                                  'where'   => "$filterForField",
                                  'qargs'   => array( 'site' )
                                  )
                           )
                     );
            $id = $sqlParamWriter->saveParams($totalField);
            $graphURLs['graphs'][] = $sqlParamWriter->getImgURL( $id,
                                                                 "$date 00:00:00", "$date 23:59:59",
                                                                 true, 640, 240 );
            $graphURLs['links'] [] = $field;
        }
    }
    return $graphURLs;
}

function getNodeList() {
    global $statsDB, $site, $date, $nodeTable;
    $sql = "SELECT eba_moid.name,count(begin_time) AS count " .
        "FROM " . $nodeTable . ",eba_mdc,eba_moid,sites " .
        "WHERE begin_time BETWEEN '" . $date . " 00:00:00' AND " . "'" . $date . " 23:59:59' " .
        "AND " . $nodeTable . ".mdc_id = eba_mdc.id AND " . $nodeTable . ".moid_id = eba_moid.id " .
        "AND sites.name = '" . $site . "' AND sites.id = eba_mdc.siteid GROUP BY name";
    $nodes = array();
    $statsDB->query($sql);
    while ($row = $statsDB->getNextNamedRow()) {
        if ($row['count'] > 0) {
            $nodes[] = $row['name'];
        }
    }
    return $nodes;
}

function getNodeStatsTable($statsDB,$site,$date,$nodeTable,$webargs) {
  $fields = getStatCols($statsDB,$nodeTable);
  $columns = array();
  $columns[] = "eba_moid.name AS Node";
  foreach ( $fields as $field ) {
    if ( preg_match("/^NumberOf(.*)/",$field,$matches) ) {
      $columns[] = "SUM($nodeTable.$field) AS 'Total " . $matches[1] . "'";
    } if ( $field == "MaxRopParsingTime" ) {
      $columns[] = "MAX($nodeTable.MaxRopParsingTime) AS MaxRopParsingTime";
    }
  }
  $columnStr = implode(",", $columns);


  $statsDB->query("
SELECT $columnStr
 FROM $nodeTable ,eba_mdc,eba_moid,sites
 WHERE begin_time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
       $nodeTable.mdc_id = eba_mdc.id AND $nodeTable.moid_id = eba_moid.id AND
       sites.name = '$site' AND sites.id = eba_mdc.siteid
 GROUP BY eba_moid.id
 ORDER BY Node");

  $htmlTable = new HTML_Table("border=1");
  $htmlTable->addRow( $statsDB->getColumnNames(), null, 'th');
  while ( $row = $statsDB->getNextRow() ) {
    $row[0] = "<a href=\"?$webargs&node=$row[0]\">$row[0]</a>";
    $htmlTable->addRow($row);
  }

  return $htmlTable;
}


if (isset($_GET['node'])) {
    $node = $_GET['node'];
    $nodeArr = explode("=", $node);
    $nodeName = $nodeArr[1];
    echo "<a name=top><h1>EBA " . strtoupper($appName) . " Statistics for " . $nodeName . "</h1>\n";
    $sql = "SELECT id FROM eba_moid WHERE name = '" . $node . "'";
    $row = $statsDB->queryRow($sql);
    if (! is_numeric($row[0])) {
        echo "No such node: " . $node;
        $moid = -1;
    } else $moid = $row[0];
    $nodeData = getStatData($nodeTable, $node);
    echo "<ul>\n";
    if (count($nodeData['links']) > 0) {
        for ($i = 0; $i < count($nodeData['links']); ++$i) {
            $link= "<li><a href=#" . $nodeData['links'][$i] . ">" . $nodeData['links'][$i] . "</a></li>\n";
            echo $link;
        }
    }

     else {
        echo "<li>No node data for " . $date . "</li>\n";
    }
    echo "<li><a href=?date=" . $date . "&dir=" . $dir . "&oss=" . $oss . "&site=" . $site .
        "&app=" . $appName . ">Back to Application-level data for " . strtoupper($appName) . "</a></li>\n";
    echo "</ul>\n";
    for ($i = 0; $i < count($nodeData['links']); ++$i) {
        echo "\n<br/><a name=" . $nodeData['links'][$i] . "><h3>" . $nodeData['links'][$i] . "</h3>\n";
        echo $nodeData['graphs'][$i] . "\n";
        echo "\n<br/><a href=#top>back to top</a><br/>\n";
    }
} else {
    echo "<a name=top><h1>EBA " . strtoupper($appName) . " Statistics</h1>\n";

    if ( $appName == "ebsw" ) {
      $graphURLs = getEbswOverallGraphs($nodeTable,$date);
      echo "<H2>Total MB</H2>\n";
      echo $graphURLs[0] . "\n";

      echo "<H2>MaxRopParsingTime</H2>\n";
      echo $graphURLs[1] . "\n";
    }

    $graphTitle=getStatData($appTable);
    $graphURLs2 = getStatData($appTable);

    echo "<ul>\n";
    if (count($graphTitle) > 0) {
        for ($i = 0; $i < count($graphURLs2['links']); ++$i) {

            $link1= "<li><a href=#" . $graphTitle['links'][$i] . ">" . $graphTitle['links'][$i] . "</a></li>\n";
            echo $link1;
        }
    } else {
        echo "<li>No application data</li>\n";
    }

    echo '<li><a href="#nodes">Nodes</a><li\n';
    echo "</ul>\n";


    for ($i = 0; $i < count($graphURLs2['graphs']); ++$i) {
        echo "\n<br/><a name=" . $graphTitle['links'][$i] . "><h3>" . $graphTitle['links'][$i] . "</h3>\n";
        echo $graphURLs2['graphs'][$i] . "\n";
        echo "\n<br/><a href=#top>back to top</a><br/>\n";
    }



    foreach ($appData['graphs'] as $graph) {
        echo $graph;
    }

    echo "<a name=nodes><H3>Nodes</H3>\n";
    $nodeHtmlTable = getNodeStatsTable($statsDB,$site,$date,$nodeTable,$webargs . "&app=$appName");
    echo $nodeHtmlTable->toHTML();

}

include "common/finalise.php";
?>
