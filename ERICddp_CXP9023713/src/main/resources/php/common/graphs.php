<?php

// Charting utility functions. All x-axis values are assumed to be time.
function getStackedGraph($dataSets, $forceLimits, $yAxisTitle, $width = 640, $height = 400) {
    global $date;

    if ( file_exists("/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf" ) ) {
        $fontPath = "/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf";
    } else if ( file_exists("/usr/share/fonts/liberation/LiberationSans-Regular.ttf") ) {
        $fontPath = "/usr/share/fonts/liberation/LiberationSans-Regular.ttf";
    } else if ( file_exists("/usr/share/fonts/truetype/ttf-liberation/LiberationSans-Regular.ttf")) {
        $fontPath = "/usr/share/fonts/truetype/ttf-liberation/LiberationSans-Regular.ttf";
    }
    $graph =& Image_Graph::factory("graph",array($width,$height));
    $font =& $graph->addNew("font",$fontPath);
    $font->setSize(8);
    $graph->setFont($font);

    $plotarea =& Image_Graph::factory('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis'));
    $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
    $plotarea->setBorderColor('white');  /* Explicitly set background to white */

    $legend =& Image_Graph::factory('legend');
    $legend->setPlotArea($plotarea);
    $graph->add( Image_Graph::vertical( $plotarea, $legend, 80 ) );

    $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
    $xAxis->setTitle("Time");
    $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
    $xAxis->setDataPreProcessor($dateFormatter);
    if ($forceLimits) {
        $xAxis->forceMinimum(strtotime($date));
        $xAxis->forceMaximum(strtotime($date) + ( (24*60*60) - 1));
    }
    $plot =& $plotarea->addNew('Image_Graph_Plot_Step', array(array_values($dataSets), 'stacked'));
    $fill =& Image_Graph::factory('Image_Graph_Fill_Array');
    $lines =& Image_Graph::factory('Image_Graph_Line_Array');
    $colours = array("red","green","blue","orange","yellow","indigo","violet");
    foreach ( $colours as $colour ) {
        $fill->addColor($colour . '@0.8', $colour);
        $lines->addColor($colour . '@0.8', $colour);
    }
    $plot->setFillStyle($fill);
    $plot->setLineStyle($lines);

    $yAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_Y);
    $yAxis->setTitle($yAxisTitle, 'vertical');
    #$yAxis->forceMaximum($yMax);
    $graph->setPadding(10);
    $graph->displayErrors();
    return $graph;
}

function getGraph($type, $dataSets, $forceLimits, $yAxisTitle, $width = 640, $height = 400, $valRange = array()) {
    global $date;

    if ( file_exists("/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf" ) ) {
        $fontPath = "/usr/openwin/lib/X11/fonts/TrueType/LiberationSans-Regular.ttf";
    } else if ( file_exists("/usr/share/fonts/liberation/LiberationSans-Regular.ttf") ) {
        $fontPath = "/usr/share/fonts/liberation/LiberationSans-Regular.ttf";
    } else if ( file_exists("/usr/share/fonts/truetype/ttf-liberation/LiberationSans-Regular.ttf")) {
        $fontPath = "/usr/share/fonts/truetype/ttf-liberation/LiberationSans-Regular.ttf";
    }
    $graph =& Image_Graph::factory("graph",array($width,$height));
    $font =& $graph->addNew("font",$fontPath);
    $font->setSize(8);
    $graph->setFont($font);

    $plotarea =& Image_Graph::factory('plotarea', array( 'Image_Graph_Axis','Image_Graph_Axis'));
    $plotarea->setBackgroundColor('white');  /* Explicitly set background to white */
    $plotarea->setBorderColor('white');  /* Explicitly set background to white */

    $legend =& Image_Graph::factory('legend');
    $legend->setPlotArea($plotarea);
    $graph->add( Image_Graph::vertical( $plotarea, $legend, 80 ) );

    $xAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
    $xAxis->setTitle("Time");
    $dateFormatter =& Image_Graph::factory('Image_Graph_DataPreprocessor_Date',array('H:i'));
    $xAxis->setDataPreProcessor($dateFormatter);
    if ($forceLimits) {
        $xAxis->forceMinimum(strtotime($date));
        $xAxis->forceMaximum(strtotime($date) + ( (24*60*60) - 1));
    }
    $plot =& $plotarea->addNew('Image_Graph_Plot_Line', array(array_values($dataSets), $type));
    $lineColours =& Image_Graph::factory('Image_Graph_Line_Array');
    $colours = array("red","green");
    foreach ( $colours as $colour ) {
        $lineColours->addColor($colour . '@1.0');
    }
    $plot->setLineStyle($lineColours);

    $yAxis =& $plotarea->getAxis(IMAGE_GRAPH_AXIS_Y);
    $yAxis->setTitle($yAxisTitle, 'vertical');
    foreach ($valRange as $k => $v) {
        switch ($k) {
        case "min":
            $yAxis->forceMinimum($v);
            break;
        case "max":
            $yAxis->forceMaximum($v);
            break;
        }
    }
    $graph->setPadding(10);
    $graph->displayErrors();
    return $graph;
}

function getLineGraph($dataSets, $forceLimits, $yAxisTitle, $width = 640, $height = 400, $valRange = array()) {
    return getGraph("line", $dataSets, $forceLimits, $yAxisTitle, $width, $height, $valRange);
}

function getSteppedGraph($dataSets, $forceLimits, $yAxisTitle, $width = 640, $height = 400, $valRange = array()) {
    return getGraph("step", $dataSets, $forceLimits, $yAxisTitle, $width, $height, $valRange);
}

#
# JavaScripty Graphs
#
function getJSGraph() {
    global $FLOT_INCLUDED;
    if ($FLOT_INCLUDED == false) {
?>
<!--[if IE]><script language="javascript" type="text/javascript" src="flot/excanvas.min.js"></script><![endif]-->
    <script language="javascript" type="text/javascript" src="flot/jquery.js"></script>
    <script language="javascript" type="text/javascript" src="flot/jquery.flot.js"></script>
    <script language="javascript" type="text/javascript" src="flot/jquery.flot.selection.js"></script>
    <script language="javascript" type="text/javascript" src="flot/jquery.flot.stack.js"></script>
<?php
        $FLOT_INCLUDED = true;
    }
}
