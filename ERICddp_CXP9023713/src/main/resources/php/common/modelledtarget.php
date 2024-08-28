<?php

require_once "./functions.php";

const MODEL_NAME = 'modelname';
const MODEL_TYPE = 'modeltype';

$modeltype = requestValue(MODEL_TYPE);
if ( $modeltype === 'graph' || $modeltype === 'graphset') {
    // For graphs we're going to redirect so turn off the UI
    $UI = false;
    $NOREDIR = true;
}

$pageTitle = "Modelled Target";

require_once "init.php";

require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function quoteSelected() {
    $selected = requestValue('selected');
    $selectedValues = explode(",", $selected);
    if (count($selectedValues) > 1) {
        $allNumeric = true;
        foreach ($selectedValues as $selectedValue) {
            if (! is_numeric($selectedValue)) {
                $allNumeric = false;
            }
        }
        if ($allNumeric) {
            $valueStr = implode(",", $selectedValues);
        } else {
            $valueStr = implode("','", $selectedValues);
        }
    } else {
        $valueStr = $selected;
    }
    return $valueStr;
}

debugMsg("modeltype: ", $modeltype);

$model = requestValue(MODEL_NAME);

if ( $modeltype === 'graph' || $modeltype === 'graphset') {
    if ( $modeltype === 'graph' ) {
        $modelledGraph = new ModelledGraph($model);
    } else {
        list($graphName, $modelOnly) = explode("@", $model);
        debugMsg("graphName", $modelOnly);
        debugMsg("modelOnly", $modelOnly);
        $modelledGraphSet = new ModelledGraphSet($modelOnly);
        $modelledGraph = $modelledGraphSet->getGraph($graphName);
    }

    $params = array();
    foreach ( $modelledGraph->getParameterNames() as $paramName ) {
        $value = requestValue($paramName);
        if ( is_null($value) ) {
            // Assume this is the selected id
            $params[$paramName] = quoteSelected();
        } else {
            $params[$paramName] = $value;
        }
    }
    debugMsg("params", $params);
    $link = $modelledGraph->getLink($params);
    debugMsg("link", $link);
    if ( $debug == 0 ) {
        header("Location:" .  $link);
    }
} elseif ( $modeltype === 'table' ) {
    $modelledTable = new ModelledTable($model, 'table');
    echo $modelledTable->getTable();
}
