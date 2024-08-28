<?php

require_once PHP_ROOT . "/classes/QPlotQueryBuilder.php";

$action = isset($_GET["action"]) ? $_GET["action"] : "";
if ( $action != "getdata" ) {
    # This div can be updated via Javascript to display warnings generated later in the page.
    echo "<div id='graph-warning-box'></div>";

    function displayTooManyPointsWarning() {
        global $AGG_TYPE, $AGG_INTERVAL;
        # Build the urls for the max and average hourly links.
        $url=strtok($_SERVER["REQUEST_URI"],'?');
        $params = $_GET;
        $hourlyInterval = array_search('Hourly', $AGG_INTERVAL);
        $params['aggint'] = $hourlyInterval;

        $aggMax = array_search('MAX', $AGG_TYPE);
        $params['aggtype'] = $aggMax;
        $query = http_build_query($params);
        $hourlyMaxLink = "$url?$query";

        $aggAvg = array_search('AVG', $AGG_TYPE);
        $params['aggtype'] = $aggAvg;
        $query = http_build_query($params);
        $hourlyAvgLink = "$url?$query";

        # Build the message to display.
        $warningMessage = "<p>"
                          . "<b>Warning: Cannot display graph. Attempting to display too many points.</b><br>"
                          . "Use aggregation to reduce the number of points.</br></br>"
                          . "Aggregation Examples:"
                          . "<ul>"
                          . "  <li> <a href=\"$hourlyMaxLink\">Display the maximum values for each hour.</a> (Aggregation=MAX, Aggregation Interval=Hourly)"
                          . "  <li> <a href=\"$hourlyAvgLink\">Display the average values for each hour.</a> (Aggregation=AVG, Aggregation Interval=Hourly)"
                          . "</ul>"
                          . "</p><br>";

        # Add the message to the graph-warning-box which we created earlier in the page.
        echo "<script type=\"text/javascript\">
              document.getElementById('graph-warning-box').innerHTML = '" . $warningMessage . "';
              </script>";
    }

    function shutdown() {
        if ($error = error_get_last()) {
            if ($error['type'] ==  E_ERROR) {
                # If we have ran out of memory display an error that the user was trying to view too many data points.
                if (preg_match('/Allowed\s*memory\s*size\s*of\s*[0-9]*\s*bytes\s*exhausted/', $error['message']) ) {
                    displayTooManyPointsWarning();
                }
            }
        }
    }

    register_shutdown_function('shutdown');
}

class JsPlot {
    private static $loadJS = TRUE;

    function show($spp,$divName,$queryArgs) {
        global $debug, $date;

        if ( self::$loadJS ) {
            echo <<<EOT

<script src="/jquery/jquery-3.0.0.js"></script>
<script src="/highcharts/js/highcharts.js"></script>
<script src="/highcharts/js/modules/exporting.js"></script>
<script src="/highcharts/js/modules/offline-exporting.js"></script>

EOT;
            self::$loadJS = FALSE;
        }

        $type = $spp['type'];

        if ( array_key_exists('querylist',$spp) ) {
            $seriesList = $this->getSeriesFromDB($spp,$type,$queryArgs);
        } else if ( array_key_exists('seriesfile',$spp) ) {
            $seriesList = $this->getSeriesFromFile($spp);
        }

        if ( empty($seriesList) ) {
            echo "<H2>INFO: No data exists for the given date.</H2>\n";
            return;
        }

        $xlabel = "";
        if ( isset($spp['xlabel']) ) {
            $xlabel = $spp['xlabel'];
        }
        $ylabel = "";
        if ( isset($spp['ylabel']) ) {
            $ylabel = $spp['ylabel'];
        }

        $xAxisType = "datetime";
        $zoomType = 'x';
        if ( $type == 'tsc' ) {
            $hcType = 'line';
        } else if ( $type == 'sb' ) {
            $hcType = 'column';
        } else if ( $type == 'sa' ) {
            $hcType = 'area';
        } else if ( $type == 'xy' ) {
            $hcType = 'scatter';
            $zoomType = 'xy';
        } else if ( $type == 'cat' ) {
            $hcType = 'column';
            $xAxisType = "category";
            $category_names = array_pop($seriesList);
        }

        $tzOffset = $this->getUtcOffset($queryArgs);
        /*
          As part of TORF-184427 a new color was added #AEB640 instead of the color yellow #FFFF55
          as yellow is not visible under white bachground. The color will be yellow itself in qplot graphs
          and the change is reflected only in highcharts.
        */
        $chartDef = array(
            'colors' => array( '#FF5555', '#5555FF', '#55FF55', '#AEB640',
                               '#FF55FF', '#55FFFF', '#FFC0CB', '#808080',
                               '#C00000', '#0000C0', '#C0C000', '#C000C0'
            ),
            'chart' => array(
                'zoomType' => $zoomType,
                'type' =>  $hcType
            ),
            'title' => array(
                'text' => $spp['title']
            ),
            'xAxis' => array(
                'type' => $xAxisType,
                'title' => array(
                    'text' => $xlabel
                )
            ),
            'yAxis' => array(
                'title' => array(
                    'text' => $ylabel
                )
            ),
            'series' => $seriesList
        );

        if ( $type == 'sb' || $type == 'sa' || $type == 'cat' ) {
            $chartDef['plotOptions'] = array(
                'series' => array(
                    'stacking' => 'normal'
                )
            );
            /* Highchart seems to plot in reverse order  */
            $chartDef['yAxis']['reversedStacks'] = false;
        }

        if ( $type == 'xy' ) {
            $chartDef['tooltip'] = array(
                'pointFormat' => 'x: <b>{point.x:%H:%M:%S}</b><br/>y: <b>{point.y}</b><br/>'
            );
        }

        if ( $type === 'cat' ) {
            $chartDef['xAxis']['categories'] = $category_names;
        }

        echo <<<EOT
            <script type="text/javascript">
\$(function () {
 Highcharts.setOptions({
        global: {
            timezoneOffset: $tzOffset
        },
        credits: {
            enabled: false
        }
    });
 $('#$divName').highcharts(

EOT;

        echo json_encode($chartDef, JSON_PRETTY_PRINT);

        echo <<<EOT

 );
})
</script>

EOT;
    }

    function getSeriesFromFile($spp) {
        global $debug, $dir;

        $seriesFile = $spp['seriesfile'];
        if ( preg_match('/\/\d\d\d\d\d\d\//', $seriesFile) &&
             preg_match('/^\d\d\d\d\d\d$/', $dir) ) {
            $seriesFile = preg_replace('/\/\d\d\d\d\d\d\//', "/$dir/", $seriesFile);
        }

        if ( $debug > 1 ) { echo "<pre>getSeriesFromFile seriesfile:" . $seriesFile . "</pre>\n"; }

        $handle = fopen($seriesFile, "r");
        if ( ! $handle ) {
            return array();
        }

        $firstChar = fgetc($handle);
        fclose($handle);

        if ( $firstChar == '[' ) {
            $seriesList = json_decode(file_get_contents($seriesFile), true);
        } else {
            $handle = fopen($seriesFile, "r");
            while (($line = fgets($handle)) !== false) {
                $series = json_decode($line,true);
                $seriesList[] = $series;
            }
            fclose($handle);
        }

        if ( $debug > 2 ) { echo "<pre>getSeriesFromFile seriesList:"; print_r($seriesList); echo "</pre>\n"; }
        return $seriesList;
    }

    function getSeriesFromDB($spp,$type,$queryArgs) {
        global $debug, $UNIX_TIME_FORMAT;

        $tstart = $queryArgs['tstart'];
        $tend = $queryArgs['tend'];
        $aggType = $queryArgs['aggType'];
        $aggInterval = $queryArgs['aggInterval'];
        $aggCol = $queryArgs['aggCol'];

        $tMin = strtotime($tstart);
        $tMax = strtotime($tend);
        $tPeroid = ($tMax - $tMin) + 1;
        if ( $debug ) { echo "<pre>imagePlot: tMax=$tMax tMin=$tMin, tPeroid=$tPeroid<pre>\n"; }

        /* Load the data sets */
        $statsDB = new StatsDB();
        $dataSets = array();
        $queryBuilder = new QPlotQueryBuilder();
        foreach ($spp['querylist'] as $qp) {
            $sql = $queryBuilder->getQuery($tstart,$tend,$aggType,$aggInterval,$aggCol,
                                           $qp,$UNIX_TIME_FORMAT,$type);
            $statsDB->query($sql);

            /* Figure out where the data columns start */
            $dataColIndex = 1;
            if ( array_key_exists("multiseries",$qp) ) {
                $dataColIndex++;
            }

            $numCols = $statsDB->getNumFields();
            if ( $type == 'tb' ) {
                $numCols--;
            }

            $colNames = $statsDB->getColumnNames();
            if ( $debug ) { echo "<pre>colNames\n"; print_r($colNames); echo "</pre>\n"; }
            $colTypes = $statsDB->getColumnTypes();
            if ( $debug ) { echo "<pre>colTypes\n"; print_r($colTypes); echo "</pre>\n"; }


            /*
              Allocate the datasets. Note if we using multiseries,
              the "series" are allocated on the fly
            */
            if ( ! array_key_exists("multiseries",$qp) ) {
                for ( $i = $dataColIndex; $i <  $numCols; $i++ ) {
                    $dataSets[$colNames[$i]] = array( 'name' => $colNames[$i], 'data' => array() );
                }
            }

            while($row = $statsDB->getNextRow()) {
                if ( array_key_exists("multiseries",$qp) ) {
                    $seriesId = $row[$dataColIndex-1];
                    if ( ! array_key_exists($seriesId,$dataSets) ) {
                        $dataSets[$seriesId] = array( 'name' => $seriesId, 'data' => array() );
                    }

                    $dataSets[$seriesId]['data'][] = array( $this->getValue($row[0],$colTypes[$colNames[0]]),
                                                            $this->getValue($row[$dataColIndex],$colTypes[$colNames[$dataColIndex]]) );
                } else {
                    for ( $i = $dataColIndex; $i <  $numCols; $i++ ) {
                        if ( $debug ) { echo "<pre>jsPlot: data for $colNames[$i] i=$i $row[0] $row[$i]</pre>\n"; }
                        $dataSets[$colNames[$i]]['data'][] = array( $this->getValue($row[0],$colTypes[$colNames[0]]),
                                                                    $this->getValue($row[$i],$colTypes[$colNames[$i]]) );
                    }
                }
            }
        }
        $statsDB->disconnect();

        $seriesList = array();
        if ( $type == "cat" ) {
            $categories = array();
            foreach ( array_values($dataSets) as $dataSet ) {
                foreach ( $dataSet['data'] as $xy ) {
                    $categories[$xy[0]] = 1;
                }
            }
            $category_names = array_keys($categories);
            sort($category_names);
            $category_index = array();
            $index = 0;
            foreach ( $category_names as $category_name ) {
                $category_index[$category_name] = $index;
                $index++;
            }
            $numCategories = count($category_names);

            foreach ( array_values($dataSets) as $dataSet ) {
                $data = array_fill(0,$numCategories,0);
                foreach ( $dataSet['data'] as $xy ) {
                    $data[$category_index[$xy[0]]] = $xy[1];
                }
                $seriesList[] = array(
                    'name' => $dataSet['name'],
                    'data' => $data
                );
            }

            $seriesList[] = $category_names;
        } else {
            foreach ( array_values($dataSets) as $dataSet ) {
                $data = array();
                foreach ( $dataSet['data'] as $xy ) {
                    $data[] = array( $xy[0]*1000, $xy[1] );
                }
                $seriesList[] = array(
                    'name' => $dataSet['name'],
                    'data' => $data
                );
            }
        }

        return $seriesList;
    }


    function getValue($value, $type) {
        /* Need to fix up numeric data types as the driver returns them as strings */
        if ( $type == 'int' || $type == 'float' || $type == 'real' ) {
            $result = $value + 0;
        } else {
            $result = $value;
        }
        return $result;
    }

    function getUtcOffset($queryArgs) {
        global $date, $debug;

        /* By default highcharts expects time in UTC but we store in "localtime" of the DDP server, so we need to tell highcharts what our offset is */

        if ( !is_null($queryArgs) ) {
            $startDateTime = DateTime::createFromFormat('Y-m-d G:i:s', $queryArgs['tstart']);
        } else {
            $startDateTime = DateTime::createFromFormat('Y-m-d', $date);
        }
        $tzOffset = 0 - ($startDateTime->getOffset() / 60);

        if ( $debug ) {echo "<pre>tstart=$tstart startDateTime\n"; print_r($startDateTime); echo ", tzOffset=$tzOffset</pre>\n";}

        return $tzOffset;
    }
}

?>
