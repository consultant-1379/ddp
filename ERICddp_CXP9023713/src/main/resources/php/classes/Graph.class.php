<?php

class Graph {
    // Image/Graph - specific objects
    var $graph;
    var $plotArea;
    var $legend;
    var $datasets;

    // DDP-specific variables
    var $startDate;
    var $endDate;
    var $data;

    var $startTime = 0;
    var $endTime = 0;
    var $increment;

    var $xAxisFormat = "H:m\nj M";

    var $type = "line";

    var $colours = array("red","yellow","blue","green","orange");

    var $yMax = "";
    var $yMin = "";
    var $yLabelInterval = "";

    var $barWidth = 2;

    function __construct($startDate = "", $endDate = "", $interval = "day") {
      $this->graph =& Image_Graph::factory("graph",array(640,400));

      if ( file_exists("/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf" ) ) {
	$fontPath = "/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf";
      } else if ( file_exists("/usr/share/fonts/liberation/LiberationSans-Regular.ttf") ) {
        $fontPath = "/usr/share/fonts/liberation/LiberationSans-Regular.ttf";
      }
      if ( isset($fontPath) ) {
        $font =& $this->graph->addNew("font",$fontPath);
        $font->setSize(8);
        $this->graph->setFont($font);
      }

      $this->graph->add(
            Image_Graph::vertical(
                $this->plotArea = Image_Graph::factory('plotarea', array('Image_Graph_Axis')),
                $this->legend = Image_Graph::factory('legend'),
                85
            )
        );
        $this->legend->setPlotArea($this->plotArea);
        $this->legend->setFillColor("#cccccc@0.5");
        $gridY =& $this->plotArea->addNew('line_grid', null, IMAGE_GRAPH_AXIS_Y);
        $gridY->setLineColor('gray@0.1');
        $gridX =& $this->plotArea->addNew('line_grid', null, IMAGE_GRAPH_AXIS_X);
        $gridX->setLineColor('gray@0.1');
        if ($startDate != "" && $endDate != "") {
            $this->startDate = $startDate;
            $this->endDate = $endDate;
            $this->interval = $interval;
            switch ($interval) {
            case "day":
                $this->increment = 86400;
                $this->xAxisFormat = "j M";
                break;
            case "hour":
                $this->increment = 3600;
                $this->xAxisFormat = "H:m\nj M";
                break;
            case "min":
                $this->increment = 60;
                $this->xAxisFormat = "H:m";
                break;
            default:
                $this->increment = 3600;
                break;
            }
        }
    }

    function addData($data, $zero = true) {
        // Temporarily remove GMT etc timezone info
        $tz = date_default_timezone_get();
        date_default_timezone_set("UTC");
        if (isset($this->startDate) && isset($this->endDate)) {
            $this->startTime = strtotime($this->startDate);
            $this->endTime = strtotime($this->endDate);
        }
        $colInc = 0;
        foreach ($data as $key => $d) {
            $this->datasets[$key] =& Image_Graph::factory('Image_Graph_Dataset_Trivial');
            $this->datasets[$key]->setName($key);
            $this->data[$key] = array();
            // Zero out values between the startTime and the endTime so
            // that the graph covers the full date range
            if ($this->startTime != 0 && $zero) {
                $origStart = $this->startTime;
                while ($this->startTime <= $this->endTime) {
                    $this->data[$key][$this->startTime] = 0;
                    $this->startTime += $this->increment;
                }
                $this->startTime = $origStart;
            }
            foreach($d as $tm => $val) {
                $this->data[$key][strtotime($tm)] = $val;
            }
            ksort($this->data[$key]);
            foreach($this->data[$key] as $tm => $val) {
                $this->datasets[$key]->addPoint($tm, $val);
            }
        }
        date_default_timezone_set($tz);
    }

    function setType($type) {
        if ($type == "stacked" || $type == "line" || $type == "bar" || $type == "filledline")
            $this->type = $type;
    }

    function setYAxisLimits($min, $max, $labelInterval = 10) {
        $this->yMax = $max;
        $this->yMin = $min;
        $this->yLabelInterval = $labelInterval;
    }

    function setYAxisTitle($title) {
        $this->yAxisTitle = $title;
    }

    function display() {
        $xAxis =& $this->plotArea->getAxis(IMAGE_GRAPH_AXIS_X);
        $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array($this->xAxisFormat));
        $xAxis->setDataPreProcessor($dateFormatter);
        if ($this->increment == 3600) $xAxis->setTitle("\nTime");
        else $xAxis->setTitle("Time");
        $xAxis->setLabelInterval($this->increment * 5,1);
        $xAxis->setTickOptions(-3,0,1);
        $xAxis->setLabelInterval($this->increment,2);
        $xAxis->setTickOptions(-1,0,2);
        $xAxis->setLabelOption('showtext',false,2);

        $yAxis =& $this->plotArea->getAxis(IMAGE_GRAPH_AXIS_Y);
        if (isset($this->yAxisTitle)) {
            $yAxis->setTitle($this->yAxisTitle, "vertical");
        }
        if (is_numeric($this->yMax)) $yAxis->forceMaximum($this->yMax);
        if (is_numeric($this->yMin)) $yAxis->forceMinimum($this->yMin);
        if (is_numeric($this->yLabelInterval)) $yAxis->setLabelInterval($this->yLabelInterval);

        if ($this->type == "bar") {
            $plot =& $this->plotArea->addNew('bar', array($this->datasets));
            $plot->setBarWidth($this->barWidth,"px");
            $fillArr =& Image_Graph::factory('Image_Graph_Fill_Array');
            $colourCount = 0;
            foreach ($this->datasets as $d) {
                $fillArr->addColor($this->colours[$colourCount % count($this->colours)] . "@0.8");
                $colourCount++;
            }
            $plot->setLineColor("white");
            $plot->setFillStyle($fillArr);
        } else if ($this->type == "line") {
            $plot =& $this->plotArea->addNew('Image_Graph_Plot_Area',array($this->datasets, "normal"));
            $lineArr =& Image_Graph::factory('Image_Graph_Line_Array');
            $colourCount = 0;
            foreach ($this->datasets as $d) {
                $lineArr->addColor($this->colours[$colourCount % count($this->colours)]);
                $colourCount++;
            }
            $plot->setLineStyle($lineArr);
            $plot->setFillColor("white@0.0");
        } else if ($this->type == "filledline") {
            $plot =& $this->plotArea->addNew('Image_Graph_Plot_Area',array($this->datasets, "normal"));
            $lineArr =& Image_Graph::factory('Image_Graph_Line_Array');
            $fillArr =& Image_Graph::factory('Image_Graph_Fill_Array');
            $colourCount = 0;
            foreach ($this->datasets as $d) {
                $fillArr->addColor($this->colours[$colourCount % count($this->colours)] . "@0.2");
                $lineArr->addColor($this->colours[$colourCount % count($this->colours)]);
                $colourCount++;
            }
            $plot->setLineStyle($lineArr);
            $plot->setFillStyle($fillArr);
        } else if ($this->type == "stacked") {
            $plot =& $this->plotArea->addNew('Image_Graph_Plot_Area',array($this->datasets, "stacked"));
            $fillArr =& Image_Graph::factory('Image_Graph_Fill_Array');
            $colourCount = 0;
            foreach ($this->datasets as $d) {
                $fillArr->addColor($this->colours[$colourCount % count($this->colours)] . "@0.2");
                $colourCount++;
            }
            $plot->setLineColor("gray");
            $plot->setFillStyle($fillArr);
        }

        if ($this->type == "bar") {

        }
        
        $this->graph->setPadding(15);
        if (isset($_GET['debug'])) $this->printData();
        else $this->graph->done();
    }

    function printData() {
        echo "<pre>\n";
        foreach($this->data as $name => $d) {
            echo "NAME: " . $name . "\n";
            foreach ($d as $key => $val) {
                echo date("Y-m-d H:i:s", $key) . " => " . $val . "\n";
            }
        }
        echo "</pre>\n";
    }
}
?>
