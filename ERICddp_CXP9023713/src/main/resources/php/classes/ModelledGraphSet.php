<?php

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'ModelledUI.php';
require_once PHP_ROOT . "/classes/ModelledGraph.php";

/**
 * A bridge class to provide access to Modelled UI Graphs Sets.
 *  See
 *  https://eteamspace.internal.ericsson.com/display/SMA/Modelled+UI
 */
class ModelledGraphSet extends ModelledUI {
    /*
     * Create an instance of ModelledGraph
     *
     * modelName should be the relative path to the model file (without the .xml)
     */
    public function __construct($modelName) {
        $this->setdef = ModelledUI::sendRequest('graphset', $modelName, array());

        $this->groupsByName = array();
        if ( array_key_exists('groups', $this->setdef) ) {
            foreach ( $this->setdef['groups'] as $group) {
                $this->groupsByName[$group['name']] = $group;
            }
        }
    }

    /*
     * Get the named ModelledGraph
     *
     * name: The name of the graph
     */
    public function getGraph($name) {
        if ( array_key_exists($name, $this->setdef['graphs']) ) {
            return $this->makeModelledGraph($name);
        } else {
            exit("graph $name doesn't exist");
        }
    }

    /*
     * Get the named group of ModelledGraphs
     *
     * name: The name of the group
     */
    public function getGroup($name) {
        $group = array(
            'graphs' => array()
        );
        foreach ( $this->groupsByName[$name]['members'] as $graphName) {
            $group['graphs'][] = $this->makeModelledGraph($graphName);
        }
        return $group;
    }

    /*
     * Get the named group of ModelledGraph
     *
     * name: The name of the graph
     */
    private function makeModelledGraph($name) {
        if ( ! array_key_exists($name, $this->setdef['graphs']) ) {
            exit("graph $name doesn't exist");
        }

        $graphdef = array(
            'id' => $this->setdef['graphs'][$name]['id'],
            'timespan' => $this->setdef['timespan'],
            'parameters' => $this->setdef['parameters']
        );
        return new ModelledGraphFromSet($graphdef);
    }
}

class ModelledGraphFromSet extends ModelledGraph {
    public function __construct($graphdef) {
        debugMsg("ModelledGraphFromSet: __construct graphdef", $graphdef);
        $this->graphdef = $graphdef;
    }
}
