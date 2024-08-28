<?php

/*
Works with SqlPlotParam where SqlPlotParam is responsible for persisting the plot parameters

The plot parameters is an array with the following fields
Required
-----------
title      => String for plot title

ylabel     => String for label of Y-Axis

querylist  => array of arrays
              Each second level array describes the query used and contains the following fields
               timecol => name of column in table with the time
               whatcol => array of column_names (can also be DB functions, etc.) to "Display" names
               tables  => the tables in the query (i.e. what goes in the FROM clause)
               where   => The where clause (Note: this should not include the timecol column as qplot manages
                          part itsself.
                          See presistent queries below for more info
               qargs   => array of parameter value names who values are to get got from the qplot URL
                          See presistent queries below for more info
Optional
------------
targs      => Args for the title string, used for persistant plots in the same way as where & qargs are used.

type       => String specifying the type of plot to generate, defaults to 'tsc', values are
             'tsc' => Time Series collection which is a standard X-Y plot
             'sb'  => Stacked Bar chart. In a multiseries the series are "stacked" on top of each other, e.g. if series A has a value of 3
                      and series B has a value of 2, then the bar will show series A from 0->3 and series B 3->5
                      The width of the bar defaults to the width between time samples. This can be overridden using sb.barwidth
             'sa'  => Stacked Area chart.
             'tb'  => Time Bar chart, a bar chart where the width of the bar is returned in the query. The second entry in whatcol is used
                      as the bar width
             'cat' => Category bar chart, i.e. X-Axis is a "category", not a time series
             'xy' => X-Y scatter graph

sb.barwidth=> See the sb plot in type


useragg    => String containing true/false : If set to true then the Aggeration drop down boxes are shown in the applet, defaults to false

presetagg  => String containing the Aggregation function and Aggregation interval. Used if you want to plot aggregated data, e.g.
              the number of fm_sync per hour would have presetagg => 'COUNT:Hourly'

persistent => String containing true/false : If the qplot link must be externally referenced for more then a day, then set
              this to 'true'. See Persistent Queries below

multiseries => Column name of multi-series key. See MultiSeries plots below

forcelegend => true/false


Persistent Queries
======================
Normal qplot links (persistent not set or persistent = 'false') become invalid when the DDP maintance job runs.
This is because the parameter data is stored in a unique file in a temp dir and the maintance job deletes the
contents of this directory.

If you want a qplot URL to be valid post midnight you need to set persistent to 'true'. When using this, care must
be taken to ensure that is no site specific data in any of the queries in the querylist. Instead qargs must be used to supply
the site specific info.

The WHERE clause in the query is contructed using sprintf where the 'where' field is used as the format and the sprintf args
are built by extracting the value of each parameter named in qargs from the qplot URL in the order they are specified in qargs.
e.g. if
 where => 'table_a.siteid = sites.id AND sites.name = '%s' AND table_a.otherid = %d'
 and qargs => array( 'site', 'otherid' )
then you would end up with
 $whereClause = sprintf('table_a.siteid = sites.id AND sites.name = '%s' AND table_a.otherid = %d', array($_GET['site'],$_GET['otherid']));

Multi Series plots
======================
Multi series plots are where you want to plot the same column from a table, e.g. the blocks/s for all disks for a server.
You need to specify the table/column that contains the "key" of the series, e.g. the disk name


*/

$pageTitle = "Query Plotter";

$action = "appletplot";
if ( isset($_GET["action"]) ) {
    $action = $_GET["action"];
}
if ($action != "appletplot" && $action != "staticplot" && $action != "jsplot") {
    $UI = false;
}

/* We want to disable auth if it's the applet asking for the data */
if ( $action == "getdata" ) {
    $NOAUTH = true;
}

include "common/init.php";
require_once "SqlPlotParam.php";
require_once PHP_ROOT . "/classes/QPlotQueryBuilder.php";
require_once PHP_ROOT . "/classes/JsPlot.php";

const YLABEL = 'ylabel';

function displayForm($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp) {
  global $php_webroot;
  global $AGG_INTERVAL;
  global $AGG_TYPE;
  global $debug;

  // Display site and server name
  if( isset($_GET['server']) ) {
    echo "<h2>" . $_GET['server'] . "</h2>";
  }

  if ( $debug ) { echo "<pre>appletPlot: id=$id aggType=$aggType aggInterval=$aggInterval<pre>\n"; }

  $myURL = $_SERVER['PHP_SELF'];

  echo <<<EOT
    <form action="$myURL" method="get" name="timerange" id="timerange">
EOT;
  foreach ( $_GET as $name => $value ) {
    if ( $name != 'tstart' && $name != 'tend' && $name != 'aggtype' && $name != 'aggint' ) {
      echo "<input type=\"hidden\" name=\"$name\" value=\"$value\">\n";
    }
  }

  echo <<<EOT
    <table border="0">
    <tr>
    <td align="right" valign="top"><b>From:</b></td>
    <td valign="top" align="left">      <input size="20" maxlength="20" name="tstart" type="text" value="$tstart" /></td>

    <td align="right" valign="top"><b>To:</b></td>
    <td valign="top" align="left">      <input size="20" maxlength="20" name="tend" type="text" value="$tend" /></td>

EOT;

  if ( ( array_key_exists( 'useragg', $spp ) ) && ( $spp['useragg'] == 'true' ) ) {
      echo <<<EOT
        <td valign="top" align="right"><b>Aggregation:</b></td>
        <td valign="top" align="left">
        <select name="aggtype">
EOT;
      foreach ($AGG_TYPE as $value => $title) {
        $selected = "";
        if ( $value == $aggType ) {
          $selected = 'selected="selected"';
        }
        echo "<option $selected value=\"$value\">$title</option>\n";
      }
      echo <<<EOT
        </select>
        </td>
        <td valign="top" align="right"><b>Aggregation Interval:</b></td>
        <td valign="top" align="left">
        <select name="aggint">
EOT;
      foreach ($AGG_INTERVAL as $value => $title) {
        $selected = "";
        if ( $value == $aggInterval ) {
          $selected = 'selected="selected"';
        }
        echo "<option $selected value=\"$value\">$title</option>\n";
      }
      echo "\n</select>\n</td>\n";
  }

  echo <<<EOT
    <td align="right" valign="top"><b></b></td>
    <td valign="top" align="left">      <input value="Update" type="submit" /></td>
    </tr>
    </table>
    </form>

EOT;
}

function jsPlot($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp) {
    global $debug;
    global $AGG_INTERVAL;
    global $AGG_TYPE;
    global $UNIX_TIME_FORMAT;

    /* Figure out is this type is supported, if not, use staticplot to draw it */
    $SUPPORTED_TYPES = array( 'tsc', 'sb', 'sa','cat', 'xy' );
    $type = 'tsc';
    if ( array_key_exists( 'type', $spp ) == true ) {
        $type = $spp['type'];
    } else {
        $spp['type'] = $type;
    }

    $spp['title'] = getTitle($spp, true);

    if ( $debug ) {  echo "<pre>jsPlot type=$type in SUPPORTED_TYPES=" . in_array( $type, $SUPPORTED_TYPES ) . "(" . implode(",",$SUPPORTED_TYPES) . ")</pre>\n"; }
    if ( ! in_array( $type, $SUPPORTED_TYPES ) ) {
        $queryString = $_SERVER['QUERY_STRING'];
        $_SERVER['QUERY_STRING'] = str_replace('&action=jsplot','&action=staticplot',$_SERVER['QUERY_STRING']);
        staticPlot($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp);
        return;
    }

    # Only display the form is this is a query based plot
    if ( array_key_exists('querylist',$spp) ) {
        displayForm($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp);
        $queryArgs = array('tstart' => $tstart,
                           'tend'   => $tend,
                           'aggType' => $aggType,
                           'aggInterval' => $aggInterval,
                           'aggCol' => $aggCol);
    } else {
        $queryArgs = array('tstart' => $tstart, 'tend'   => $tend);
    }

    $jsPlot = new JsPlot();
    $jsPlot->show($spp, "chart", $queryArgs);

    echo '<div id="chart" style="height: 600px"></div>' . "\n";
}



function staticPlot($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp) {
  global $php_webroot;

  displayForm($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp);

  $queryArgs = str_replace('&action=staticplot','',$_SERVER['QUERY_STRING']);
  $plotServerletLnk = getPlotURL() . "?" .
    $queryArgs  . "&url=" . urlencode(PHP_WEBROOT . "/qplot.php") .
    '&width=1200&height=800';

  $appletPlotArgs = str_replace('&action=staticplot','&action=appletplot',$_SERVER['QUERY_STRING']);
  $appletPlotLnk = $_SERVER['PHP_SELF'] . '?' . $appletPlotArgs;

  echo '<a href="' . $appletPlotLnk . '"><img src="' . $plotServerletLnk . '" alt=""></a>';
}

function appletPlot($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp) {
  global $php_webroot;

  displayForm($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp);

  $plotDir = preg_replace('/\/php$/', '/plot', $php_webroot);
  $request =  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER["HTTP_HOST"] . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&action=getdata";
  echo <<<EOT
    <applet code="Plot"
    codebase="$plotDir"
    archive="Plot.jar"
    width="100%" height="600">
    <param name="codebase_lookup" value="false">
    <param name="cache_archive" value="jfreechart-1.0.13.jar,jcommon-1.0.16.jar">
    <param name="cache_version" value="1.0.13,1.0.16">
    <param name="data" value="$request">
    Your browser is completely ignoring the &lt;APPLET&gt; tag!
    </applet>
EOT;
}

function getTitle($spp, $longTitle = false) {
    global $debug;

    if ( $longTitle && array_key_exists('longtitle', $spp) ) {
      $title = $spp['longtitle'];
    } else {
      $title = $spp['title'];
    }

    if ( array_key_exists( 'targs', $spp ) == true ) {
        $tvals = array();
        foreach ( $spp['targs'] as $targ ) {
            if ( isset($_GET[$targ] ) ) {
                if ( $debug ) { echo "<pre>getTitle: found value for $targ</pre>\n"; }
                $tvals[] = $_GET[$targ];
            } else {
                echo "<pre>ERROR: Invalid URL, no value found for \"$targ\"</pre>";
                exit;
            }
        }
        if ( $debug ) { echo "<pre>getTitle: title='$title', tvals"; print_r($tvals); echo "</pre>\n"; }

        $title = vsprintf($title, $tvals);
    }

    return $title;
}

function getData($site,$tstart,$tend,$id,$aggType,$aggInterval,$aggCol,$spp) {
  global $debug;
  global $AGG_INTERVAL;
  global $AGG_TYPE;
  global $APPLET_TIME_FORMAT;

  if ( $aggType == 0 ) {
    $title = getTitle($spp);
  } else {
    $title = getTitle($spp) . " " . $AGG_INTERVAL[$aggInterval] . " " . $AGG_TYPE[$aggType];
  }

  $type = 'tsc';
  if ( array_key_exists( 'type', $spp ) == true ) {
    $type = $spp['type'];
  }

  $statsDB = new StatsDB();

  $dataSets = array();
  $dataSetIndex = -1;
  $queryBuilder = new QPlotQueryBuilder();
  foreach ($spp['querylist'] as $qp) {
    if ( array_key_exists( 'useragg', $spp ) || array_key_exists( 'presetagg', $spp ) ) {
      $sql = $queryBuilder->getQuery($tstart,$tend,$aggType,$aggInterval,$aggCol,$qp,$APPLET_TIME_FORMAT,$type);
    } else {
      $sql = $queryBuilder->autoAggQuery($tstart,$tend,$qp);
    }

    $statsDB->query($sql);

    $numCols = $statsDB->getNumFields();
    if ( $type == 'tsc' ) {
      $header = "tsc;second";
    } else if ( $type == "sb" ) {
      // For no aggregation, use the sb.barwidth from the query def
        if ( $aggInterval == 0 ) {
          if ( array_key_exists( 'sb.barwidth', $spp ) ) {
            $header = "tt;" . $spp['sb.barwidth'];
          } else {
            $header = "tt;second";
          }
        }
    // If we are doing aggregation, set the barwidth
        // to the aggregation period
        else if ( $aggInterval == 1 ) {
          $header = "tt;60";
        } else if ( $aggInterval == 2 ) {
          $header = "tt;3600";
        } else if ( $aggInterval == 3 ) {
          $header = "tt;86400";
        }
    } else if ( $type == 'tb' ) {
      $header = "tpvc";
    } else if ( $type == 'cat' ) {
      $header = 'cat';
    } else if ( $type == 'xy' ) {
        $header = "tsc;second";
    } else {
      $header = "tt;second";
    }

    // In a multiseries, the key will be used as series label
    $dataColIndex = 1;
    if ( array_key_exists("multiseries",$qp) ) {
      $dataColIndex++;
    } else {
      if ( $type == 'tb' ) {
        $numCols--;
      }

      // Otherwise use the name of the columns
      for ( $i = $dataColIndex; $i < $numCols; $i++ ) {
        $header = $header . ";" . $statsDB->getFieldName($i);
      }
      $dataSetIndex++;
      $dataSets[$dataSetIndex] = array();
      $dataSets[$dataSetIndex][] = $header;
    }

    while($row = $statsDB->getNextRow()) {
      $line = $row[0];
      for ( $i = $dataColIndex; $i < $numCols; $i++ ) {
        /* Deal with case where NULL has been stored for the column value
           Plot class requires a value for every column so for NULL output zero */
        if ( isset($row[$i]) ) {
          $value = $row[$i];
        } else {
          $value = 0;
        }
        $line = $line . " " . $value;
      }
      if ( $type == 'tb' ) {
        $line = $line . " " . $row[$numCols] * 1000;
      }

      if ( array_key_exists("multiseries",$qp) ) {
        if ( ! array_key_exists($row[1],$dataSets) ) {
          $dataSets[$row[1]] = array();
          /*
           * Need to be careful here, Plot class uses ; as a
           * seperator, so we need to make sure that the
           * label doesn't contain ;
           */
          $dataSets[$row[1]][] = $header . ";" . str_replace(";", ":", $row[1]);
        }
        $dataSets[$row[1]][] = $line;
      } else {
        $dataSets[$dataSetIndex][] = $line;
      }
    }
  }
  $statsDB->disconnect();

  if ( $debug ) { echo "<pre>\n"; print_r($dataSets); echo "</pre>\n"; }

  if ( $debug ) { echo "<pre>\n"; }
  foreach ( $dataSets as $key => $dataSet ) {
    foreach ( $dataSet as $line ) {
      echo $line . "\n";
    }
    echo "\n";
  }

  $forcelegend = "false";
  if ( array_key_exists( 'forcelegend', $spp ) ) {
    $forcelegend = $spp['forcelegend'];
  }
  $ylabel = '';
  if ( array_key_exists( YLABEL, $spp ) ) {
      $ylabel = $spp[YLABEL];
  }
  echo "plot;" . $type . ";" . $title . ";Time;" . $ylabel . ";" . $forcelegend . "\n";

  if ( $debug ) { echo "</pre>\n"; }
}

function printSeries($series,$header) {
    global $debug;

    echo $header . ";" . $series['name'] . "\n";

    if ( $debug ) { echo "printSeries: series"; print_r($series); echo "\n"; }

    foreach ( $series['data'] as $point ) {
        if ( $header == 'cat' ) {
            echo $point[0] . " " . $point[1] . "\n";
        } else {
            echo date("Y-m-d:H:i:s", $point[0]/1000) . " " . $point[1] . "\n";
        }
    }
    echo "\n";
}

function getDataFromJSONFile($spp,$file) {

    $title = getTitle($spp);
    $type = 'tsc';
    if ( array_key_exists( 'type', $spp ) == true ) {
        $type = $spp['type'];
    }

    if ( $type == 'tsc' ) {
      $header = "tsc;second";
    } else if ( $type == "sb" ) {
        if ( array_key_exists( 'sb.barwidth', $spp ) ) {
            $header = "tt;" . $spp['sb.barwidth'];
        } else {
            $header = "tt;second";
        }
    } else if ( $type == 'tb' ) {
      $header = "tpvc";
    } else if ( $type == 'cat' || $type == 'pie') {
      $header = 'cat';
    } else if ( $type == 'xy' ) {
        $header = "tsc;second";
    } else {
      $header = "tt;second";
    }

    $firstChar = '';
    $handle = fopen($spp['seriesfile'], "r");
    if ( $handle ) {
        $firstChar = fgetc($handle);
        fclose($handle);
    }

    if ( $firstChar == '[' ) {
        $seriesList = json_decode(file_get_contents($spp['seriesfile']), true);
        $tz = date_default_timezone_get();
        date_default_timezone_set("UTC");
        foreach ($seriesList as $series) {
            if ( array_key_exists('name', $series) ) {
                printSeries($series, $header);
            }
        }
        date_default_timezone_set($tz);
    } else {
        $handle = fopen($spp['seriesfile'], "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $series = json_decode($line,true);
                # New format has each series in it's own line
                if ( array_key_exists('name', $series) ) {
                    printSeries($series,$header);
                } else {
                    foreach ( $series as $oneSeries ) {
                        printSeries($oneSeries,$header);
                    }
                }
            }
        }
        fclose($handle);
    }

    echo "plot;" . $type . ";" . $title . ";Time;" . $spp[YLABEL] . "\n";
}

function getDataFromFile($file) {
    echo file_get_contents($file);
}

function redirectToPlot() {
  // Strip out the action args
  $queryArgs = str_replace('&action=imgplot','',$_SERVER['QUERY_STRING']);
  $plotServerletLnk = getPlotURL() . "?" .
    $queryArgs  . "&url=" . urlencode(PHP_WEBROOT . "/qplot.php");
  header("Location:" . $plotServerletLnk);
}

function imagePlot($tstart,$tend,$aggType,$aggInterval,$aggCol,$spp) {
    global $debug;
    global $AGG_INTERVAL;
    global $AGG_TYPE;
    global $UNIX_TIME_FORMAT;

    $tMin = strtotime($tstart);
    $tMax = strtotime($tend);
    $tPeroid = ($tMax - $tMin) + 1;
    if ( $debug ) { echo "<pre>imagePlot: tMax=$tMax tMin=$tMin, tPeroid=$tPeroid<pre>\n"; }

    $type = 'tsc';
    if ( array_key_exists( 'type', $spp ) == true ) {
        $type = $spp['type'];
    }

    /* Load the data sets */
    $statsDB = new StatsDB();
    $dataSets = array();
    $queryBuilder = new QPlotQueryBuilder();
    foreach ($spp['querylist'] as $qp) {
        $sql = $queryBuilder->getQuery($tstart,$tend,$aggType,$aggInterval,$aggCol,$qp,$UNIX_TIME_FORMAT,$type);
        $statsDB->query($sql);

        /* Figure out where the data columns start */
        if ( $type == 'cat' ) {
            /* No time column in cat plots */
            $dataColIndex = 0;
        } else {
            $dataColIndex = 1;
        }
        if ( array_key_exists("multiseries",$qp) ) {
            $dataColIndex++;
        }

        $numCols = $statsDB->getNumFields();
        if ( $type == 'tb' ) {
            $numCols--;
        }

        $colNames = $statsDB->getColumnNames();
        if ( $debug ) { echo "<pre>colNames\n"; print_r($colNames); echo "</pre>\n"; }

        /*
        Allocate the datasets. Note if we using multiseries,
        the "series" are allocated on the fly
        */
        if ( ! array_key_exists("multiseries",$qp) ) {
            for ( $i = $dataColIndex; $i <  $numCols; $i++ ) {
                $dataSets[$colNames[$i]] =& Image_Graph::factory('dataset');
                $dataSets[$colNames[$i]]->setName($statsDB->getFieldName($i));
                if ( $debug ) { echo "<pre>added dataSet for $colNames[$i]\n"; print_r($dataSets[$colNames[$i]]); echo "</pre>\n"; }
            }
        }

        while($row = $statsDB->getNextRow()) {
            if ( array_key_exists("multiseries",$qp) ) {
                $seriesId = $row[$dataColIndex-1];
                if ( ! array_key_exists($seriesId,$dataSets) ) {
                    $dataSets[$seriesId] = & Image_Graph::factory('dataset');
                    $dataSets[$seriesId]->setName($seriesId);
                }

                if ( $type == 'cat' ) {
                    for ( $i = $dataColIndex; $i <  $numCols; $i++ ) {
                        if ( $debug ) { echo "<pre>imagePlot: cat plot data for $colNames[$i] i=$i $row[0] $row[$i]</pre>\n"; }
                        $dataSets[$seriesId]->addPoint( $colNames[$i], $row[$i] );
                    }
                } else {
                    $dataSets[$row[1]]->addPoint($row[0],$row[$dataColIndex]);
                }
            } else if ( array_key_exists("type", $spp) && $spp["type"] == 'tb' ) {
          // Step chart draws "backwards", i.e. point draws backwards at the
          // Y value until the previous point is reached
          $dataSets[$colNames[$dataColIndex]]->addPoint($row[0],0);
          $dataSets[$colNames[$dataColIndex]]->addPoint($row[0] + $row[2],$row[$dataColIndex]);
            } else {
                for ( $i = $dataColIndex; $i <  $numCols; $i++ ) {
                    if ( $debug ) { echo "<pre>imagePlot: data for $colNames[$i] i=$i $row[0] $row[$i]</pre>\n"; }
                    $dataSets[$colNames[$i]]->addPoint( $row[0], $row[$i] );
                }
            }
        }
    }
    $statsDB->disconnect();

    if ( $debug ) { echo "<pre>dataSets\n"; print_r($dataSets); echo "</pre>\n"; }
    /* Create the Graph */
    $width=640;
    if ( isset($_GET['width']) ) {
        $width = $_GET['width'];
    }
    $height=480;
    if ( isset($_GET['height']) ) {
        $height = $_GET['height'];
    }
    $graph =& Image_Graph::factory('graph', array($width, $height));

    $graph->displayErrors();

    $graph->setBackgroundColor('white');  /* Explicitly set background to white */
    $graph->setBorderColor('white');

    $xAxisType = 'Image_Graph_Axis';
    if ( $type == 'cat' ) {
        $xAxisType = 'Image_Graph_Axis_Category';
    }
    $plotarea =& Image_Graph::factory('plotarea', array( $xAxisType,'Image_Graph_Axis'));
    $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
    $plotarea->setBorderColor('white');

    /* If we have multiple datasets add a legend */
    if ( count($dataSets) > 1 ) {
        $legend =& Image_Graph::factory('legend');
        $legend->setPlotArea($plotarea);

        $graph->add( Image_Graph::vertical(
                     Image_Graph::factory('title', array(getTitle($spp), 11)),
                     Image_Graph::vertical( $plotarea, $legend, 80 ),
                     8)
                   );
    } else {
        $graph->add( Image_Graph::vertical( Image_Graph::factory('title', array(getTitle($spp), 11)),
                     $plotarea,
                     8)
                   );
    }


    /* Font */
    if ( file_exists("/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf" ) ) {
        $fontPath = "/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf";
    } else if ( file_exists("/usr/share/fonts/liberation/LiberationSans-Regular.ttf") ) {
        $fontPath = "/usr/share/fonts/liberation/LiberationSans-Regular.ttf";
        /* Adding extra fontpath as other paths not accessible on atrnstats2 [2012-07-13 RK] */
    } else if ( file_exists("/usr/share/fonts/truetype/ttf-liberation/LiberationSans-Regular.ttf") ) {
        $fontPath = "/usr/share/fonts/truetype/ttf-liberation/LiberationSans-Regular.ttf";
    }

    $font =& $graph->addNew("font",$fontPath);
    $font->setSize(8);
    $graph->setFont($font);


    if ( $type == 'cat' ) {
    } else {
        /* X-Axis is time */
        $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
        $xAxis->forceMinimum($tMin);
        $xAxis->forceMaximum($tMax);
        /* If the time range is less then a data, label X axis with Hour:Min */
        /* else use Month/day/hour */
        if ( ($tPeroid) <= (24 * 60 * 60) ) {
            $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
        } else {
            $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('jS M'));
        }
        $xAxis->setDataPreProcessor($dateFormatter);
        if ( $width >= 640 ) {
            $xAxis->setLabelInterval($tPeroid/12,1);
        } else {
            $xAxis->setLabelInterval($tPeroid/6,1);
        }
    }


    /* Y-Axis in plot params */
    $yAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_Y);
    $yAxis->setTitle($spp[YLABEL]);
    $numberFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Function',"number_format");
    $yAxis->setDataPreProcessor($numberFormatter);


    /* Graph Type */
    if ( $type == "tsc" ) {
        $plot =& $plotarea->addNew('Image_Graph_Plot_Line', array($dataSets, "line"));
    } else if ( $type == "sa" ) {
        $plot =& $plotarea->addNew('Image_Graph_Plot_Step', array($dataSets, 'stacked'));
    } else if ( $type == "sb" ) {
        $plot =& $plotarea->addNew('Image_Graph_Plot_Bar', array($dataSets, "stacked"));
        $barWidthSec = 1;
        if ( array_key_exists("sb.barwidth", $spp) ) {
            $barWidthSec = $spp["sb.barwidth"];
        }
        $barWidthPercent = ($barWidthSec / ($tMax - $tMin)) * 100;
        $plot->setBarWidth($barWidthPercent,"%");
    } else if ( $type == "tb" ) {
        $plot =& $plotarea->addNew('Image_Graph_Plot_Step', array($dataSets, 'normal'));
    } else if ( $type == 'cat' ) {
        $plot =& $plotarea->addNew('Image_Graph_Plot_Bar', array($dataSets));
    }

    /* Colours for fill & lines */
    $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
    $lines =& Image_Graph::factory('Image_Graph_Line_Array');

    $colours = array("red","orange","blue","green","indigo","violet","teal");
    $numColours = count($colours);
    $index = 0;
    $numDataSets = count($dataSets);
    foreach ( $dataSets as $dataSetName => $dataSet ) {
        $colour = $colours[$index % $numColours];
        if ( $index >= $numColours ) {
            if ( $numDataSets < 21 ) {
                $colourWeight = 8 - (2 * round($index/$numColours));
            } else {
                $colourWeight = 8 -  round($index/$numColours);
            }
            $colour = $colour . "@0." . $colourWeight;
        }

        if ( $debug ) { echo "<pre>imagePlot: dataSetName=$dataSetName colour=$colour</pre>\n"; }
        $fill->addColor($colour, $dataSetName);
        $lines->addColor($colour, $dataSetName);

        $index++;
    }

    $plot->setFillStyle($fill);
    $plot->setLineStyle($lines);

    if ( ! $debug ) { $graph->done(); }
}

function getPlotURL() {
   return "/plotsrv/";
}

$tstart=trim($_GET['tstart']);
$tend=trim($_GET['tend']);
$site=$_GET['site'];

if ( ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $tstart) ||
     ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $tend) ) {
    echo "<H2>ERROR: Please make sure that the 'from' & 'to' dates are in 'YYYY-mm-dd HH:MM:SS' format.</H2>\n";
    return;
}

$id=$_GET['id'];
$sqlPlotParam = new SqlPlotParam();
$spp = $sqlPlotParam->load($id);
if ( is_null($spp) ) {
    echo "<H1>ERROR: Cannot load plot parameters, has this link expired?</H1>\n";
    exit;
}


$aggtype=0;
$aggint =0;
$aggcol = "xaxis";
if ( isset($_GET['aggtype']) ) {
    $aggtype=$_GET['aggtype'];
    $aggint=$_GET['aggint'];
} else if ( array_key_exists('presetagg',$spp) ) {
    $manAggParam = explode(":", $spp['presetagg']);
    foreach ($AGG_TYPE as $key => $val) {
        if ( $manAggParam[0] == $val ) {
            $aggtype = $key;
        }
    }
    foreach ($AGG_INTERVAL as $key => $val) {
        if ( $manAggParam[1] == $val ) {
            $aggint = $key;
        }
    }
    if ( $aggint == 0 ) {
        $aggcol = $manAggParam[1];
    }
}

/* if forcelegend is in the query string overwrite the forcelegend value in spp with it */
if ( isset($_GET['forcelegend']) ) {
    $spp['forcelegend'] = $_GET['forcelegend'];
}

/* Ensure that if aggtype is None, then aggint is zero */
if ( $aggtype == 0 ) {
    $aggint = 0;
}

$request= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

if ( $debug > 0 ) { echo "<pre>action=$action</pre>\n"; }

if ( $action == "appletplot" ) {
  appletPlot($site,$tstart,$tend,$id,$aggtype,$aggint,$aggcol,$spp);
} else if ( $action == "staticplot" ) {
  staticPlot($site,$tstart,$tend,$id,$aggtype,$aggint,$aggcol,$spp);
} else if ( $action == "jsplot" ) {
    jsPlot($site,$tstart,$tend,$id,$aggtype,$aggint,$aggcol,$spp);
} else if ( $action == "getdata" ) {
    if ( array_key_exists('file',$spp) ) {
        getDataFromFile($spp['file']);
    } else if ( array_key_exists('seriesfile',$spp) ) {
        getDataFromJSONFile($spp,$spp['seriesfile']);
    } else {
        getData($site,$tstart,$tend,$id,$aggtype,$aggint,$aggcol,$spp);
    }
} else if ( $action == "imgplot" ) {
  // imagePlot($tstart,$tend,$aggtype,$aggint,$aggcol,$spp);
  // Redirect to the plot serverlet
  redirectToPlot();
}

include "common/finalise.php";

?>
