<?php

require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

const AGG = 'aggFunc';
const LBL = 'label';
const AVTP = 'avgthroughput';
const MNTP = 'minthroughput';

function printRncDetail($rnc)
{
  global $rootdir;

  echo "<table border>\n<tr> <th>MO Type</th> <th>Counters Per MO</th> <th>Num MO</th> <th>Total</th></tr>\n";
  include($rootdir . "/pms/rnc_counters/$rnc");
  echo "</table>";
}

function qPlot($site,$date,$plot)
{
  global $debug;

  $qplots["coltime"] = array( 'title' => 'Last Collection Time By NE/File type',
                              'ylabel' => 'Time',
                              'value' => 'TIME_TO_SEC( TIMEDIFF( lasttime, time ) )' );

  $qplots["colfiles"] = array( 'title' => 'Number of Files Collected By NE/File type',
                               'ylabel' => 'Files',
                               'value' => 'numfiles' );

  if ( $debug ) { echo "<p>qPlot: site=$site date=$date plot=$plot</p>\n"; }

  $queryList = array();
  $statsDB = new StatsDB();
  $statsDB->query("
SELECT ne_types.name, pms_filetransfer_rop.filetype
FROM pms_filetransfer_rop, sites, ne_types
WHERE
 pms_filetransfer_rop.siteid = sites.id AND sites.name = '$site' AND
 pms_filetransfer_rop.netypeid = ne_types.id AND
 pms_filetransfer_rop.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY pms_filetransfer_rop.netypeid, pms_filetransfer_rop.filetype");

  while($row = $statsDB->getNextRow()) {
    $queryList[] =
      array(
            'timecol' => 'time',
            'whatcol'    => array(  $qplots[$plot]['value'] => $row[0] . " ". $row[1]),
            'tables'  => "pms_filetransfer_rop, sites, ne_types",
            'where'   => "pms_filetransfer_rop.siteid = sites.id AND sites.name = '$site' AND pms_filetransfer_rop.netypeid = ne_types.id AND ne_types.name = '$row[0]' AND pms_filetransfer_rop.filetype = '$row[1]'"
            );
  }


  $sqlParam = array(
                    'title'      => $qplots[$plot]['title'],
                    'ylabel'     => $qplots[$plot]['ylabel'],
                    'useragg'    => 'true',
                    'querylist' => $queryList
                    );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);

  header("Location:" .  $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") );
}

function getTransferStatisticsGraphs($type) {
    global $date, $statsDB;

    $sqlParamWriter = new SqlPlotParam();

    $graphTable = new HTML_Table("border=0");
    $graphParams =
        array(
            'totalkb' => array(LBL=>'Total (Kb)',AGG=>'SUM'),
            AVTP => array(LBL=>'Avg. Throughput (Bytes)',AGG=>'AVG'),
            MNTP => array(LBL=>'Min. Throughput (Bytes)',AGG=>'MIN')
        );

    foreach ( $graphParams as $col => $label ) {
        $row = array();
        $where = "pms_filetransfer_rop.siteid = sites.id AND
                  sites.name = '%s' AND
                  pms_filetransfer_rop.netypeid = ne_types.id AND
                  ne_types.name ='%s'";

        $sqlParam = array(
            'title' => $label[LBL],
            'ylabel' => $label[LBL],
            'type' => 'tsc',
            'useragg' => 'true',
            'sb.barwidth' => '60',
            'persistent' => 'true',
            'presetagg'  => $label[AGG] . ':Per Minute',
            'querylist' => array(
                array(
                    'timecol' => 'time',
                    'whatcol' => array ( 'pms_filetransfer_rop.' . $col => $label[LBL]),
                    'tables' => "pms_filetransfer_rop, sites, ne_types",
                    'where' => $where,
                    SqlPlotParam::Q_ARGS => array( 'site', 'type' )
                )
            )
        );
        $extraArgs = "type=$type";
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 180, $extraArgs);
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function getMainTable($row)
{
  $mainStatTable = new HTML_Table('border=1');

  $mainStatTable->addRow( array( "Stats Total Collected", $row["collected"] ) );
  $mainStatTable->addRow( array( "Stats Total Should be Collected", $row["available"] ) );
  $mainStatTable->addRow( array( "Extra Collected", $row["extra"] ) );


  $successRatio = "0";
  if ( $row["available"] > 0 )  $successRatio = sprintf ("%.2f", ($row["collected"] / $row["available"]) * 100);
  $mainStatTable->addRow( array( "Stats Successful Collection", $successRatio ) );

  $mainStatTable->addRow( array( "UETR Collected", $row["uetr"] ) );
  $mainStatTable->addRow( array( "CTR Collected", $row["ctr"] ) );
  $mainStatTable->addRow( array( "GPEH Collected", $row["gpeh"] ) );

  $mainStatTable->addRow( array( "Average Time Taken to collect files for 15 Min ROP (sec)", $row["avgroptime"] ) );
  $mainStatTable->addRow( array( "Max Time Taken to collect files for 15 Min ROP (sec)", $row["maxroptime"] ) );
  if ( array_key_exists("avgroptime_one", $row ) ) {
    $mainStatTable->addRow( array( "Average Time Taken to collect files for 1 Min ROP (sec)", $row["avgroptime_one"] ) );
    $mainStatTable->addRow( array( "Max Time Taken to collect files for 1 Min ROP (sec)", $row["maxroptime_one"] ) );
  }

  $mainStatTable->addRow( array( "Total Data Volume Collected(MB)", $row["datavol"] ) );


  return $mainStatTable;
}

function getStatsCollectedByNeType($row,$webroot)
{
  $statColByType = new HTML_Table('border=1');

  if ( $row["rncavail"] != -1 ) {
    $NODE_TYPES = array( 'RNC', 'RBS', 'RXI', 'ERBS', 'PRBS','DSC' );

    $statColByType->addRow( array( "Node Type", "Available", "Not Collected", "Success Ratio %" ), null, "th" );


    foreach ($NODE_TYPES as $nodetype) {
      $availCol = strtolower($nodetype) . "avail";
      $missCol = strtolower($nodetype) . "miss";

      if ( $nodetype == "RNC" ) {
        $missing = sprintf ("<a href=\"%s\">%d</a>", $webroot . "/rncmissing.txt", $row[$missCol]);
      } else {
        $missing = sprintf ("%d", $row[$missCol] );
      }

      if ( $row[$availCol] > 0 ) {
        $sr = sprintf("%.2f", ($row[$availCol] - $row[$missCol]) / $row[$availCol] * 100);
      } else {
        $sr = "n/a";
      }

      $statColByType->addRow( array( $nodetype, $row[$availCol], $missing, $sr ) );
    }
  }

  return $statColByType;
}

function getScannerCountsTable($row)
{
  $scannerCounts = new HTML_Table('border=1');

  if ( ! is_null( $row["act_PREDEF"] ) ) {
    $scannerCounts->addRow( array('Type', 'Active', 'Suspended','Total'), null, 'th' );
    $totals = array( 'active' => 0, 'suspended' => 0 );
    $types = array( 'PREDEF' => 'Predefined Stats', 'USERDEF' => 'User defined Stats', 'GPEH' => 'GPEH', 'UETR' => 'UETR', 'CTR' => 'CTR' );

    foreach ( $types as $colName => $colLabel ) {
      $scannerCounts->addRow( array( $colLabel, $row['act_' . $colName], $row['sus_' . $colName], $row['act_' . $colName] + $row['sus_' . $colName] ) );
      $totals["active"] += $row['act_' . $colName];
      $totals["suspended"] += $row['sus_' . $colName];
    }
    $scannerCounts->addRow( array( 'Total', $totals["active"], $totals["suspended"], $totals["active"] + $totals["suspended"] ) );
  }
  return $scannerCounts;
}

function getStatsColFromFtp($statsDB,$site,$date) {

  $statColByType = new HTML_Table('border=1');
  $statsDB->query("
SELECT ne_types.name , pms_filetransfer_node.period,
    SUM(pms_filetransfer_node.available),
    SUM(pms_filetransfer_node.missing),
    ROUND( (100 * (SUM(pms_filetransfer_node.available) - SUM(pms_filetransfer_node.missing)) ) / SUM(pms_filetransfer_node.available), 1)
FROM sites, pms_filetransfer_node, ne, ne_types
WHERE
sites.id = pms_filetransfer_node.siteid AND
sites.name = '" . $site . "' AND
pms_filetransfer_node.neid = ne.id AND
ne.netypeid = ne_types.id AND
pms_filetransfer_node.date = '" . $date . "' AND
pms_filetransfer_node.filetype = 'STATS' AND
pms_filetransfer_node.available IS NOT NULL
GROUP BY ne.netypeid, pms_filetransfer_node.period");
  if ( $statsDB->getNumRows() > 0 ) {
    $totalAvailable = 0;
    $totalMissing   = 0;
    $statColByType->addRow( array( "Node Type", "ROP Period", "Available", "Not Collected", "Success Ratio %" ), null, "th" );
    while ($row = $statsDB->getNextRow()) {
      $statColByType->addRow($row);
      $totalAvailable += $row[2];
      $totalMissing   += $row[3];
    }
    if ( $totalAvailable > 0 ) {
      $overall = sprintf("%.1f", (100 * ($totalAvailable - $totalMissing))/ $totalAvailable);
      $statColByType->addRow( array("Overall", "", "", "", $overall) );
    }
  }

  return $statColByType;
}

function getOverallFtpTxStats($site,$date,$statsDB,&$nodeTypes) {
  $ftpTxStats = new HTML_Table('border=1');
  $statsDB->query("
SELECT
 ROUND( SUM(totalkb)/1024, 1) AS totalmb,
 period,
 TRUNCATE(AVG(avgthroughput), 0) AS avgthroughput, TRUNCATE(MIN(minthroughput), 0) AS minthroughput,
 ne_types.name AS nodetype, pms_filetransfer_node.filetype AS filetype,
 SUM(pms_filetransfer_node.files) AS numfiles
FROM pms_filetransfer_node,ne,ne_types,sites WHERE
pms_filetransfer_node.siteid = sites.id AND
sites.name = '" . $site . "' AND
date = '" . $date . "' AND
ne.id = pms_filetransfer_node.neid AND
ne.netypeid = ne_types.id
GROUP BY nodetype, filetype, period
ORDER BY nodetype, filetype, period");

  if ( $statsDB->getNumRows() > 0 ) {
    $ftpTxStats->addRow(array('Node Type', 'File Type', 'Period', 'Num Files', 'Total (Mb)', 'Average Throughput (B/s)', 'Min. Throughput (B/s)'), null, "th");
    while ($row = $statsDB->getNextNamedRow()) {
        $ftpTxStats->addRow(
            array(
                $row['nodetype'],
                $row['filetype'],
                $row['period'],
                $row['numfiles'],
                $row['totalmb'],
                $row[AVTP],
                $row[MNTP]
            )
        );
      $nodeTypes[$row['nodetype']] = 1;
    }
  }
  return $ftpTxStats;
}

function getFtpTxStatsByRns($site,$date,$statsDB) {
  $ftpTxStatsByRns = new HTML_Table('border=1');
  $statsDB->query("
SELECT rns.name, ne_types.name AS netype,
 TRUNCATE( (AVG(pms_filetransfer_node.avgthroughput) * 8) / 1024, 0) AS avgthroughput,
 TRUNCATE( (MIN(pms_filetransfer_node.minthroughput) * 8) / 1024, 0) AS minthroughput,
 SUM(pms_filetransfer_node.totalkb) AS totalkb
FROM sites, pms_filetransfer_node, ne, ne_types, rns WHERE
sites.id = pms_filetransfer_node.siteid AND
sites.name = '" . $site . "' AND
pms_filetransfer_node.neid = ne.id AND
ne.rnsid = rns.id AND
ne.netypeid = ne_types.id AND
pms_filetransfer_node.date = '" . $date . "'
GROUP BY ne.rnsid, ne.netypeid
ORDER BY avgthroughput
");
  if ($statsDB->getNumRows() > 0) {
    $ftpTxStatsByRns->addRow(array( "NE Type", "RNS Name", "Total (Kb)", "Average Throughput (kbit/s)", "Min. Throughput (kbit/s)"), null, "th");
    while ($row = $statsDB->getNextNamedRow()) {
      $ftpTxStatsByRns->addRow(array( $row['netype'], $row['name'], $row['totalkb'], $row[AVTP], $row[MNTP]));
    }
  }
  return $ftpTxStatsByRns;
}

function fileTxStatsByRop($site,$date,$statsDB,$period) {
  $params = array(
                  '15MIN' => array('title' => '15 Minute',
                                   'seconds' => 900,
                                   'bins' => array( '0->3', '3->6', '6->9', '9->12', '> 12'  ) ),
                  '1MIN'  => array('title' => 'One Minute',
                                   'seconds' => 60,
                                   'bins' => array( '0->12', '12->24', '24->36', '36->48', '> 48'  )),
                  '60MIN'  => array('title' => '60 Minute',
                                    'seconds' => 3600,
                                    'bins' => array( '0->12', '12->24', '24->36', '36->48', '> 48'  )),
                  '5MIN'  => array('title' => 'Five Minute',
                                   'seconds' => 300,
                                   'bins' => array( '0->1', '1->2', '2->3', '3->4', '> 4'  ))

                  );

  $transferStatisticsPerROP = "DDP_Bubble_124_OSS_PMS_File_Transfer_Statistics_per_ROP";
  drawHeaderWithHelp("File Collections Stats per " . $params[$period]['title'] . " ROP", 2, "transferStatisticsPerROP", $transferStatisticsPerROP);

  $txTimes = $statsDB->queryNamedRow("
SELECT ROUND(AVG(duration),0) AS avgtime, MAX(duration) AS maxtime FROM (
 SELECT MAX(TIME_TO_SEC(TIMEDIFF(lasttime,time))) AS duration
  FROM pms_filetransfer_rop, sites
  WHERE sites.name = '$site' AND sites.id = pms_filetransfer_rop.siteid AND
   pms_filetransfer_rop.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
   pms_filetransfer_rop.period = '$period' AND
   pms_filetransfer_rop.lasttime > pms_filetransfer_rop.time
   GROUP BY pms_filetransfer_rop.time) AS maxperrop");
  $table = new HTML_Table("border=0");
  $table->addRow(array('Avg Collection Time', $txTimes['avgtime']));
  $table->addRow(array('Max Collection Time', $txTimes['maxtime']));
  echo $table->toHTML();

  $where = "pms_filetransfer_rop.siteid = sites.id AND sites.name = '%s' AND pms_filetransfer_rop.netypeid = ne_types.id AND pms_filetransfer_rop.period = '%s'";
  /*
   * Data Volume Collected Graph
   */
  $sqlParam =
    array( 'title'      => 'MB data collected' ,
           'ylabel'     => 'MB',
           'useragg'    => 'true',
           'persistent' => 'true',
           'type'       => 'sb',
           'sb.barwidth'=> $params[$period]['seconds'],
           'querylist' =>
           array(
                 array (
                        'timecol' => 'time',
                        'multiseries'=> 'CONCAT(ne_types.name, " ", filetype)',
                        'whatcol' => array ( 'ROUND( totalkb/1024, 1 )' => 'MB' ),
                        'tables'  => "pms_filetransfer_rop, sites, ne_types",
                        'where'   => $where,
                        SqlPlotParam::Q_ARGS   => array( 'site', 'period' )
                        )
                 )
           );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  echo "<p>" . $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, "period=$period" ) . "</p>\n";

  /*
   *Num Files Collected Graph
   */
  $sqlParam =
    array( 'title'      => 'Number Files collected' ,
           'ylabel'     => 'Files',
           'useragg'    => 'true',
           'persistent' => 'true',
           'type'       => 'sb',
           'sb.barwidth'=>  $params[$period]['seconds'],
           'querylist' =>
           array(
                 array (
                        'timecol' => 'time',
                        'multiseries'=> 'CONCAT(ne_types.name, " ", filetype)',
                        'whatcol' => array ( 'numfiles' => 'Files' ),
                        'tables'  => "pms_filetransfer_rop, sites, ne_types",
                        'where'   => $where,
                        SqlPlotParam::Q_ARGS   => array( 'site', 'period' )
                        )
                 )
           );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  echo "<p>" . $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, "period=$period" ) . "</p>\n";

  /*
   * ROP Duration
   */
  $sqlParam =
    array( 'title'      => 'ROP Duration' ,
           'ylabel'     => 'Seconds',
           'useragg'    => 'true',
           'persistent' => 'false',
           'type'       => 'tsc',
           'querylist' =>
           array(
                 array (
                        'timecol' => 'time',
                        'multiseries'=> 'CONCAT(ne_types.name, " ", filetype)',
                        'whatcol' => array ( 'TIME_TO_SEC(TIMEDIFF(lasttime,time))' => 'Seconds' ),
                        'tables'  => "pms_filetransfer_rop, sites, ne_types",
                        'where'   => $where . " AND pms_filetransfer_rop.lasttime >= pms_filetransfer_rop.time",
                        SqlPlotParam::Q_ARGS   => array( 'site', 'period' )
                        )
                 )
           );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  echo "<p>" . $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, "period=$period" ) . "</p>\n";

  /*
   * Collection Distribution
   */
  $hasCollectionBins = $statsDB->queryRow("
SELECT COUNT(*)
 FROM pms_filetransfer_rop, sites
 WHERE
  pms_filetransfer_rop.siteid = sites.id AND sites.name = '$site' AND
  time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  period = '$period' AND
  collectedb0 IS NOT NULL");
  if ( $hasCollectionBins[0] > 0 ) {

    $sqlParam =
      array( 'title'      => 'Files Collection Distribution' ,
             'ylabel'     => 'Files',
             'useragg'    => 'false',
             'persistent' => 'true',
             'type'       => 'sb',
             'sb.barwidth'=> $params[$period]['seconds'],
             'querylist' =>
             array(
                   array (
                          'timecol' => 'time',
                          'whatcol' => array ( 'SUM(collectedb0)' => $params[$period]['bins'][0],
                                               'SUM(collectedb1)' => $params[$period]['bins'][1],
                                               'SUM(collectedb2)' => $params[$period]['bins'][2],
                                               'SUM(collectedb3)' => $params[$period]['bins'][3],
                                               'SUM(collectedb4)' => $params[$period]['bins'][4],
                                               ),
                          'tables'  => "pms_filetransfer_rop, sites",
                          'where'   => "pms_filetransfer_rop.siteid = sites.id AND sites.name = '%s' AND pms_filetransfer_rop.period = '%s' GROUP BY time",
                          SqlPlotParam::Q_ARGS   => array( 'site', 'period' )
                          )
                   )
             );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo "<p>" . $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, "period=$period" ) . "</p>\n";
  }

  /*
   * Late files
   */
  $sqlParam =
    array( 'title'      => 'Files Collected outside ROP' ,
           'ylabel'     => 'Files',
           'useragg'    => 'true',
           'persistent' => 'false',
           'type'       => 'sb',
           'sb.barwidth'=> $params[$period]['seconds'],
           'querylist' =>
           array(
                 array (
                        'timecol' => 'time',
                        'multiseries'=> 'CONCAT(ne_types.name, " ", filetype)',
                        'whatcol' => array ( 'filesoutsiderop' => 'Files' ),
                        'tables'  => "pms_filetransfer_rop, sites, ne_types",
                        'where'   => $where,
                        SqlPlotParam::Q_ARGS   => array( 'site', 'period' )
                        )
                 )
           );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  echo "<p>" . $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 480, "period=$period" ) . "</p>\n";
}

function notificationAnalysis($datadir,$rootdir,$webroot) {
  global $debug,$site,$dir,$oss,$php_webroot;

  $qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&qplot=";
  $plotdir = $datadir . "/pms_plots";
  $graphBase = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=pms_plots/";

  echo "<H1><a name=\"notif\">Notification Analysis"; drawHelpLink('notifhelp'); echo "</H1>\n";
  echo "<div id=notifhelp class=helpbox>\n";
  drawHelpTitle("1p1 Notification Analysis", "notifhelp");
  echo <<<EOS
<div class="helpbody">
Following graphs are based generated by analysising the 1p1 notifications generated by PMS
</div>
</div>
EOS;

  /* Files Successfully Collected */
  echo "<a name=\"success\"></a><h2>Reported Files Successfully Collected</h2>\n";
  $collectedPlotFile = $plotdir . "/pms_collected.txt";
  if ( $debug ) { echo "<p>collectedPlotFile=$collectedPlotFile</p>\n"; }
  if (file_exists($collectedPlotFile)) {
    echo "<a href=\"" . $graphBase . "pms_collected.txt\"><img src=\"$webroot/pms_collected.jpg\" alt=\"\"></a>\n";
  }
  else {
    echo "<a href=\"" .$qplotBase . "colfiles\"><img src=\"$webroot/pms_collected.jpg\" alt=\"\"></a>\n";
  }

  /* Files collected outside normal ROP */
  echo "<H2>Reported Files collected outside normal ROP"; drawHelpLink("filesoutsidehelp"); echo "</H2>\n";
  drawHelp("filesoutsidehelp", "Reported Files collected outside normal ROP",
           "This is a plot of files that PMS reported that collected outside of the normal ROP");
  echo <<<EOS
<img src="$webroot/pms_outside.jpg" alt="">;
<p><a href="$webroot/pms_OutSide.html">Click here</a> for a table that lists the nodes for which some PMS files were collected outside the normal ROP</p>
EOS;

  /* Files Not Collected */
  echo "<H2>Reported Files Not Collected"; drawHelpLink("repnotcollhelp"); echo "</H2>\n";
  drawHelp("repnotcollhelp", "Reported Files Not Collected",
           "This is a plot of files that PMS reported that it failed to collect, using NOTIFY_PERFORMANCE_FILE_NOT_COLLECTED events. This includes all file types (xml and bin)");
  echo <<<EOS
<img src="$webroot/pms_repnotcollected.jpg" alt="">

<p><a href="$webroot/pms_ReportedNotCollected.html">Click here</a> for a table that lists the nodes for which some PMS files were reported never collected</p>
EOS;

  /* Calculated Files Not Collected */
  echo "<H2>Calculated Files Not Collected"; drawHelpLink("calnotcollhelp"); echo "</H2>\n";
  drawHelp("calnotcollhelp", "Calculated Files Not Collected",
           "This is a plot of XML stats files that PMS failed to collect. It is based on the state of the scanners, i.e.
 if a scanner is active, then 96 files are expected to be collected for that node.");
  echo <<<EOS
<img src="$webroot/pms_calnotcollected.jpg">

<p><a href="$webroot/pms_CalNotCollected.html">Click here</a> for a table that lists nodes for which some PMS files were never collected based on the criteria described above</p>
EOS;

  /* Time to collect files for ROP Period */
  echo "<H2>Time to collect files for ROP Period" ; drawHelpLink("ropdurationhelp"); echo "</H2>\n";
  drawHelp("ropdurationhelp", "Time to collect files for ROP Period",
    "This is a plot of the length of time spent collecting files for a give ROP period");
  $plotFile = $plotdir . "/pms_ropduration.txt";
  if ( $debug ) { echo "<p>plotFile=$plotFile</p>\n"; }
  if (file_exists($plotFile)) {
    echo "<a href=\"" . $graphBase . "pms_ropduration.txt\"><img src=\"$webroot/pms_ropduration.jpg\" alt=\"\"></a>\n";
  } else {
    echo "<a href=\"" .$qplotBase . "coltime\"><img src=\"$webroot/pms_ropduration.jpg\" alt=\"\"></a>\n";
  }

  /* File Collection Distribution */
  if ( file_exists($rootdir . "/pms_colldistrib.jpg") ) {
    echo "<H2>File Collection Distribution"; drawHelpLink("colldistribhelp"); echo "</H2>\n";
    drawHelp("colldistribhelp", "File Collection Distribution",
        "This graph plots for each ROP, how many files are collected in a given 3 minute interval");
    echo '<img src="' . $webroot . '/pms_colldistrib.jpg">';
  }
}

function getProfileTable($statsDB,$site,$date) {
  global $oss;
  $proTable = new HTML_Table('border=1');

  $statsDB->query("
SELECT pms_profile.type, COUNT(*)
FROM pms_profile, sites
WHERE
   pms_profile.date = '$date' AND
   pms_profile.siteid = sites.id AND sites.name = '$site'
GROUP BY pms_profile.type
ORDER BY pms_profile.type
");
  if ( $statsDB->getNumRows() > 0 ) {
    $proTable->addRow( array( 'Type', 'Number of Profiles' ), null, 'th' );

    $url = "./pms_profile_detail.php?site=$site&date=$date&oss=$oss&ptype=";
    $total = 0;
    while($row = $statsDB->getNextRow()) {
      $proTable->addRow( array( '<a href="' . $url . $row[0] . '">' . $row[0] . '</a>', $row[1] ) );
      $total += $row[1];
    }
    $proTable->addRow( array('Total', $total ) );
  }

  return $proTable;

}

function rncCountersTable($statsDB,$site,$date) {
  $cntrTable = new HTML_Table('border=1');

  $statsDB->query("
SELECT rns.name, pms_rnc_counters.numCntr
FROM rns, pms_rnc_counters, sites
WHERE pms_rnc_counters.date = '$date' AND
   pms_rnc_counters.siteid = sites.id AND sites.name = '$site' AND
   pms_rnc_counters.rnsid = rns.id
ORDER BY pms_rnc_counters.numCntr DESC
");
  if ( $statsDB->getNumRows() > 0 ) {
    $cntrTable->addRow( array('RNC', 'Total Counters'), null, 'th');
    $request = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&rncdetail=";
    while($row = $statsDB->getNextRow()) {
      $cntrTable->addRow( array( '<a href="' . $request . $row[0] . '">' . $row[0] . '</a>', $row[1]) );
    }
  }

  return $cntrTable;
}

function listScannerGraphs($rootdir,$webroot) {
  $table = new HTML_Table('border=0');
  $fileList = array('lsttime','lstnum', 'TotalTimeToPerformLIST_RNC_SCANNERS',
                    'timesPerPollForLIST_RNC_SCANNERS', 'TotalTimeToPerformLIST_OTHER_WRAN_SCANNERS',
                    'timesPerPollForLIST_OTHER_WRAN_SCANNERS');
  foreach ( $fileList as $file ) {
    $filePath = $rootdir . "/pms_" . $file . ".jpg";
    if ( file_exists($filePath) ) {
      $imgLink = '<img src="' . $webroot . '/pms_' . $file . '.jpg"/>';
      $table->addRow( array($imgLink) );
    }
  }

  return $table;
}


function getPmsStatsRow($statsDB,$site,$date,$numRopPeriods) {
  global $debug;

  /*
   * Info Extracted from pms_stats table
   */
  $row = $statsDB->query("
SELECT
   ( (IFNULL(pms_stats.rncavail,0) + pms_stats.rbsavail + IFNULL(pms_stats.rxiavail,0) + IFNULL(pms_stats.erbsavail,0) + IFNULL(pms_stats.prbsavail,0) + IFNULL(pms_stats.dscavail,0)) - (IFNULL(pms_stats.rncmiss,0) + pms_stats.rbsmiss + IFNULL(pms_stats.rximiss,0) + IFNULL(pms_stats.erbsmiss,0) + IFNULL(pms_stats.prbsmiss,0) + IFNULL(pms_stats.dscmiss,0)) ) as collected,
   ( IFNULL(pms_stats.rncavail,0) + pms_stats.rbsavail + IFNULL(pms_stats.rxiavail,0) + IFNULL(pms_stats.erbsavail,0) + IFNULL(pms_stats.prbsavail,0) + IFNULL(pms_stats.dscavail,0) ) as available,
       pms_stats.avgroptime, pms_stats.maxroptime as maxroptime, pms_stats.uetr, pms_stats.ctr, pms_stats.gpeh, pms_stats.datavol,
       pms_stats.extra,
       IFNULL(pms_stats.rncavail,-1) as rncavail, pms_stats.rncmiss,
       pms_stats.rbsavail, pms_stats.rbsmiss,
       pms_stats.rxiavail, pms_stats.rximiss,
       pms_stats.erbsavail, pms_stats.erbsmiss,
       pms_stats.prbsavail, pms_stats.prbsmiss,
       pms_stats.dscavail, pms_stats.dscmiss,
       IFNULL(pms_stats.tzoffset, 'NA') as tzoffset,
       pms_stats.act_PREDEF, pms_stats.sus_PREDEF,
       pms_stats.act_USERDEF, pms_stats.sus_USERDEF,
       pms_stats.act_GPEH, pms_stats.sus_GPEH,
       pms_stats.act_UETR, pms_stats.sus_UETR,
       pms_stats.act_CTR, pms_stats.sus_CTR
FROM pms_stats, sites
WHERE pms_stats.siteid = sites.id AND sites.name = '$site' AND pms_stats.date = '$date'");
  if ( $statsDB->getNumRows() != 1 ) {
    if ( $debug > 0 ) { echo "<pre>getPmsStatsRow: No data available in pms_stats</pre>\n"; }
    return NULL;
  }
  $row = $statsDB->getNextNamedRow();

  if ( $numRopPeriods > 0 ) {
    $ftpTotalRow = $statsDB->queryRow("
SELECT ROUND( SUM(totalkb)/1024, 1) FROM pms_filetransfer_rop, sites
WHERE sites.name = '$site' AND sites.id = pms_filetransfer_rop.siteid AND
 pms_filetransfer_rop.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    $row["datavol"] = $ftpTotalRow[0];

    /*
     * Now that we have 1min ROP, the calculation of avgroptime and maxroptime from
     * the notifications is incorrect so we'll get them from the ftp stats
     */
    if ( $numRopPeriods > 1 ) {
      $ftp15MinTimes = $statsDB->queryNamedRow("
SELECT ROUND(AVG(duration),0) AS avgtime, MAX(duration) AS maxtime FROM (
 SELECT MAX(TIME_TO_SEC(TIMEDIFF(lasttime,time))) AS duration
  FROM pms_filetransfer_rop, sites
  WHERE sites.name = '$site' AND sites.id = pms_filetransfer_rop.siteid AND
   pms_filetransfer_rop.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
   pms_filetransfer_rop.period = '15MIN' AND
   pms_filetransfer_rop.lasttime > pms_filetransfer_rop.time
   GROUP BY pms_filetransfer_rop.time) AS maxperrop");
    $row["avgroptime"] = $ftp15MinTimes["avgtime"];
    $row["maxroptime"] = $ftp15MinTimes["maxtime"];

    $ftp1MinTimes = $statsDB->queryNamedRow("
SELECT ROUND(AVG(duration),0) AS avgtime, MAX(duration) AS maxtime FROM (
 SELECT MAX(TIME_TO_SEC(TIMEDIFF(lasttime,time))) AS duration
  FROM pms_filetransfer_rop, sites
  WHERE sites.name = '$site' AND sites.id = pms_filetransfer_rop.siteid AND
   pms_filetransfer_rop.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
   pms_filetransfer_rop.period = '1MIN' AND
   pms_filetransfer_rop.lasttime > pms_filetransfer_rop.time
   GROUP BY pms_filetransfer_rop.time) AS maxperrop");
    $row["avgroptime_one"] = $ftp1MinTimes["avgtime"];
    $row["maxroptime_one"] = $ftp1MinTimes["maxtime"];
    }
  }

  return $row;
}

function connectionGraphs($statsDB,$site,$date) {
  global $debug;

  $table = new HTML_Table('border=0');

  $row = $statsDB->queryRow("
SELECT COUNT(*) FROM pms_connectbytime,sites
WHERE
 pms_connectbytime.siteid = sites.id AND sites.name = '$site' AND
 pms_connectbytime.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] ) {
    $sqlParamWriter = new SqlPlotParam();

    $params = array(
		    'GetAcc' => array( 'title' => 'TSS Get Accounts',    'col' => 'tssGetAcc' ),
		    'GetPw'  => array( 'title' => 'TSS Get Password',    'col' => 'tssGetPw' ),
		    'Conn'   => array( 'title' => 'Node Connection',     'col' => 'nodeConn' ),
		    'Auth'   => array( 'title' => 'Node Authentication', 'col' => 'nodeAuth' )
		    );


    foreach ( $params as $key => $values ) {
      $row = array();
      foreach ( array( 'Avg' => 'Average', 'Max' => 'Maximum') as $avgMax => $label ) {
        $sqlParam =
          array( 'title'      => $values['title'] . ' ' . $label,
               'ylabel'     => 'ms',
               'useragg'    => 'true',
               'persistent' => 'false',
               'type'       => 'sb',
               'sb.barwidth'=> 60,
               'querylist' =>
               array(
                     array (
                            'timecol' => 'time',
                            'whatcol' => array ( $values['col'] . $avgMax => $label ),
			    'tables'  => "pms_connectbytime, sites",
			    'where'   => "pms_connectbytime.siteid = sites.id AND sites.name = '%s'",
			    SqlPlotParam::Q_ARGS   => array( 'site' )
			    )
		     )
	       );
	$id = $sqlParamWriter->saveParams($sqlParam);
	$row[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );
      }
      $table->addRow($row);
    }
  }
  return $table;
}

function mainFlow() {
  global $rootdir, $debug, $webroot, $site, $date, $datadir;

  $statsDB = new StatsDB();

  echo "<h1>PMS Statistics</h1>\n";

  if ( file_exists($rootdir . "/pms") ) {
    $webroot = $webroot . "/pms";
    $rootdir = $rootdir . "/pms";
  }


  /*
   * Info extracted from pms_filetransfer_node tables
   */
  $collectedNodeTypes = array();
  $ftpTxStats = getOverallFtpTxStats($site,$date,$statsDB,$collectedNodeTypes);
  $ftpTxStatsByRns = getFtpTxStatsByRns($site,$date,$statsDB);
  $ftpTxXML = getStatsColFromFtp($statsDB,$site,$date);
  $connStatsGraphs = connectionGraphs($statsDB,$site,$date);

  /* What ROP periods are available */
  $row = $statsDB->query("
SELECT DISTINCT(period) FROM pms_filetransfer_rop, sites
 WHERE
  pms_filetransfer_rop.siteid = sites.id AND sites.name = '$site' AND
  time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  $activePeriods = array();
  while ($row = $statsDB->getNextRow()) {
    $activePeriods[$row[0]] = 1;
  }
  if ( $debug > 0 ) { echo "<pre>activitiePeriods: " . print_r($activePeriods,true) . "</pre>\n"; }

  $profileTable = getProfileTable($statsDB,$site,$date);
  $rncCntrTable = rncCountersTable($statsDB,$site,$date);
  $scannerGraphTable = listScannerGraphs($rootdir,$webroot);
  /*
   * If the pms_filetransfer_node table has the available/missing info
   * then we display stats from there
   * Other wise use the "old" pms_stats table
   */
  if ( $ftpTxXML->getRowCount() == 0 ) {
    $row = getPmsStatsRow($statsDB,$site,$date,count($activePeriods));
    if ( isset($row) ) {
      $mainStatTable = getMainTable($row);
      $statColByType = getStatsCollectedByNeType($row,$webroot);
      $scannerCounts = getScannerCountsTable($row);
    } else {
      if ( $debug > 0 ) { echo "<pre>No data available in pms_stats</pre>\n"; }
      $mainStatTable = new HTML_Table('border=0');
      $statColByType = new HTML_Table('border=0');
      $scannerCounts = new HTML_Table('border=0');
    }
  }

  /*
   * Table of Contents
   */
  $pageTOC = array();
  $pageTOC[] = "<a href=\"#statColByType\">Stats Collection Success by Node Type</a>";

  if ( count($activePeriods) > 0 ) {
    $pageTOC[] = " <a href=\"#ftptxstats\">Per ROP Stats</a>";
  }

  if ( $connStatsGraphs->getRowCount() > 0 ) {
    $pageTOC[] = "<a href=\"#connStats\">Connection Stats</a>";
  }

  if ( $ftpTxXML->getRowCount() == 0 ) {
    $pageTOC[] = "<a href=\"#notif\">Notification Based Analysis</a>";
  }

  $pageTOC[] = "<a href=\"#lst\">ListScanners Execution</a>";

  if ( $ftpTxStats->getRowCount() > 0 ) {
    $queryString = $_SERVER['QUERY_STRING'];
    $pageTOC[] = <<<EOS
      Throughput Statistics:
      <ul>
      <li><a href="#ftpthruput">Per Node Type</a></li>
      <li><a href="pms_filetransfer.php?$queryString">Per Node</a></li>
      </ul>
EOS;
  }

  if ( $profileTable->getRowCount() > 0 ) {
    $pageTOC[] = "<a href=\"#profile\">Profiles</a>";
  }

  if ( isset($scannerCounts) && $scannerCounts->getRowCount() > 0 ) {
    $pageTOC[] = "<a href=\"#scannerCount\">Scanner Counts</a>";
  }

  if ( $rncCntrTable->getRowCount() > 0 ) {
    $pageTOC[] = "<a href=\"#rnc_counters\">Counters Per RNC</a>";
  }


  if ( $ftpTxXML->getRowCount() == 0 ) {
    echo "<h2>Totals</h2>\n";
    echo $mainStatTable->toHTML();
  }


  echo "<ul>\n";
  foreach ( $pageTOC as $entry ) {
    echo " <li>" . $entry . "</li>\n";
  }
  echo "</ul>\n";

  echo "<span><a name=\"#statColByType\"></span>";
  $connectionSuccessHelp = "DDP_Bubble_127_OSS_PMS_Stats_Collection_Success_by_Node_Type";
  drawHeaderWithHelp("Stats Collection Success by Node Type",2,"connectionSuccessHelp",$connectionSuccessHelp);
  if ( $ftpTxXML->getRowCount() > 0 ) {
    echo $ftpTxXML->toHTML();
  } else {
    echo $statColByType->toHTML();
  }

  /* ftpOutput stats by ROP */
  if ( count($activePeriods) > 0 ) {
    echo "<H1>File Transfer Statistics</H1>\n";
    echo "<span><a name=\"ftptxstats\"></span>\n";
    $fileTransferStatsHelp = "DDP_Bubble_126_OSS_PMS_File_Transfer_Statistics_per_Node_Type";
    drawHeaderWithHelp("FTP Transfer Statistics Per Node Type",2,"fileTransferStatsHelp",$fileTransferStatsHelp);
    echo $ftpTxStats->toHTML();

    $periods = array( '15MIN', '1MIN', '60MIN', '5MIN' );
    foreach ( $periods as $period ) {
      if ( $debug > 0 ) { echo "<pre>period=$period exists=" . array_key_exists($period,$activePeriods) . "</pre>\n"; }
      if ( array_key_exists($period,$activePeriods) ) {
        fileTxStatsByRop($site,$date,$statsDB,$period);
      }
    }
  }

  if ( $connStatsGraphs->getRowCount() > 0 ) {
    echo "<span id=\"connStats\"></span>";
    $connectionStatsHelp = "DDP_Bubble_125_OSS_PMS_Connection_Stats";
    drawHeaderWithHelp("TSS/Connection Stats",1,"connectionStatsHelp",$connectionStatsHelp);
    echo $connStatsGraphs->toHTML();
  }

  /* Notification based analysis */
  if ( $ftpTxXML->getRowCount() == 0 && $mainStatTable->getRowCount() > 0 ) {
    notificationAnalysis($datadir,$rootdir,$webroot);
  }

  /* Heap graphs */
$pmsregFileSize = round( filesize($rootdir . "/pms_reg_mem.jpg") );
$pmssegFileSize = round( filesize($rootdir . "/pms_seg_mem.jpg") );

if( (file_exists($rootdir . "/pms_reg_mem.jpg") && $pmsregFileSize > 0) || (file_exists($rootdir . "/pms_seg_mem.jpg") && $pmssegFileSize > 0) ) {
echo "<h2>JVM Heap Usage</h2>";
echo "<a name=\"mem\"></a>";
}
if( file_exists($rootdir . "/pms_reg_mem.jpg") && $pmsregFileSize > 0 ) {
echo <<<EOS
<h3>Region</h3>
<img src="$webroot/pms_reg_mem.jpg" alt="" >
EOS;
}
if( file_exists($rootdir . "/pms_seg_mem.jpg") && $pmssegFileSize > 0 ) {
echo <<<EOS
<h3>Segment</h3>
<img src="$webroot/pms_seg_mem.jpg" alt="" >
EOS;
}
  /* List Scanners Instrumentation */
  if ( $scannerGraphTable->getRowCount() > 0 ) {
    echo '<a name="lst"></a><h2>ListScanners Execution</h2>\n';
    echo $scannerGraphTable->toHTML();
  }

  /* Profiles */
  if ( $profileTable->getRowCount() > 0 ) {
    echo "<a name=\"profile\"></a><h1>Profiles</h1>\n";
    echo $profileTable->toHTML();
  }

  /* Scanners */
  if ( isset($scannerCounts) && $scannerCounts->getRowCount() > 0 ) {
    echo "<a name=\"scannerCount\"></a><h1>Scanner Counts</h3>\n";
    echo $scannerCounts->toHTML();
  }

  /* RNC Counters */
  if ( $rncCntrTable->getRowCount() > 0 ) {
    echo '<H1><a name="rnc_counters"></a>Counters Per RNC</H1>';
    echo $rncCntrTable->toHTML();
  }

  /* FTP Tx Stats per Node Type */
  if(sizeof($collectedNodeTypes) >0 ) {
    echo '<H1><a name="ftpthruput"></a>Transfer Statistics Per ROP</H1>';
  }
  foreach ( $collectedNodeTypes as $nodeType => $dummy ) {
    echo "<H1>$nodeType</H1>";
    getTransferStatisticsGraphs("$nodeType");
    echo "<br/>";
  }
}

if ( isset($_GET["rncdetail"]) ) {
  /* Display RNC counters Flow */
  $rnc=$_GET["rncdetail"];
  $pageTitle = "Counters per MOType for " . $rnc;
  include "common/init.php";
  printRncDetail($rnc);
  exit;
} else if ( isset($_GET["qplot"]) ) {
  /* QPlot Flow */
  $plot = $_GET["qplot"];
  $UI = false;
  $pageTitle = "NA";
  include "common/init.php";
  qPlot($site,$date,$plot);
  exit;
} else {
  $pageTitle = "PMS Statistics";
  include "common/init.php";
  mainFlow();
  include "common/finalise.php";
}

?>

