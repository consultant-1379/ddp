<?php

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'ModelledUI.php';

/**
 * A bridge class to provide access to Modelled UI Graphs.
 *  See
 *  https://eteamspace.internal.ericsson.com/display/SMA/Modelled+UI
 * Example usage
 * $modelledGraph = new ModelledGraph('EO/assets');
 * echo $modelledGraph->getImage(array('types' => $types));
 */
class ModelledGraph extends ModelledUI {
    /*
     * Create an instance of ModelledGraph
     *
     * modelName should be the relative path to the model file (without the .xml)
     */
    public function __construct($modelName) {
        $this->graphdef = ModelledUI::sendRequest('graph', $modelName, array());
    }

    /*
     * Get a link for displaying this graph in the qplot page
     *
     * params: an array containing any parameter/args needed to draw the graph
     * from/to: Start and end time of the time range for the graph. Generally not
     *          required as it will auto generate this using the timespan attribute
     *          in the model
     */
    public function getLink($params = null, $from = null, $to = null) {
        list($from,$to) = $this->fromTo($from, $to);
        $extraArgs = $this->extraArgs($params);

        $sqlParamWriter = new SqlPlotParam();
        return $sqlParamWriter->getURL($this->graphdef['id'], $from, $to, $extraArgs);
    }

    /*
     * Get a link for displaying this graph as an image
     *
     * params: an array containing any parameter/args needed to draw the graph
     * from/to: Start and end time of the time range for the graph. Generally not
     *          required as it will auto generate this using the timespan attribute
     *          in the model
     * width/height: Width and Height of the graph. Generally not required as it is
     *          defined in the model
     */
    public function getImage($params = null, $from = null, $to = null, $width = null, $height = null) {
        list($from,$to) = $this->fromTo($from, $to);
        $extraArgs = $this->extraArgs($params);

        if ( is_null($width) ) {
            if (array_key_exists('size', $this->graphdef)) {
                $width = $this->graphdef['size']['width'];
            } else {
                die("No width provided or defined for graph");
            }
        }
        if ( is_null($height) ) {
            if (array_key_exists('size', $this->graphdef)) {
                $height = $this->graphdef['size']['height'];
            } else {
                die("No height provided or defined for graph");
            }
        }

        $sqlParamWriter = new SqlPlotParam();
        return $sqlParamWriter->getImgURL(
            $this->graphdef['id'],
            $from,
            $to,
            true,
            $width,
            $height,
            $extraArgs
        );
    }

    public function getParameterNames() {
        return $this->graphdef['parameters'];
    }

    private function fromTo($from, $to) {
        global $date;

        if ( is_null($from) ) {
            if ( $this->graphdef['timespan'] === 'day' ) {
                $from = "$date 00:00:00";
                $to = "$date 23:59:59";
            } elseif ( $this->graphdef['timespan'] === 'month' ) {
                $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
                $from = "$fromDate 00:00:00";
                $to = "$date 23:59:59";
            }
        }

        return array($from, $to);
    }

    private function extraArgs($params) {
        $result = null;
        if ( ! is_null($params) ) {
            $nameValues = array();
            foreach ( $params as $name => $value ) {
                $nameValues[] = $name . "=" . $value;
            }
            $result = implode("&", $nameValues);
        }
        return $result;
    }
}
