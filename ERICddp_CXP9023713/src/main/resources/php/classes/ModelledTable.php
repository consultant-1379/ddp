<?php

require_once 'DDPTable.php';
require_once 'ModelledUI.php';

/**
 * A bridge class to provide access to Modelled UI Tables.
 *  See
 *  https://eteamspace.internal.ericsson.com/display/SMA/Modelled+UI
 * Example usage
 *     $table = new ModelledTable(
 *      'EO/assets',
 *       'assets',
 *       array(
 *           ModelledTable::SITE => $site,
 *           ModelledTable::DATE => $date,
 *           )
 *   );
 *   echo $table->getTableWithHeader(ASSETS);
 */
class ModelledTable extends ModelledUI {
    const SITE = 'site';
    const DATE = 'date';
    const URL = 'url';
    const WEBARGS = 'webargs';

    const TABLE = 'table';

    /*
     * Create an instance of ModelledGraph
     *
     * modelName: should be the relative path to the model file (without the .xml)
     * instanceName: Identifies this instance of the table, must be unique with the page
     * parameter: Optional parameter which is an array containing values for any parameters
     *            defined in the model. Note the following args are automatically added
     *              site
     *              date
     *              webargs
     */
    public function __construct($modelName, $instanceName, $parameters = null) {
        $this->instanceName = $instanceName;

        $allParameters = array();
        // Set default parameters
        foreach ( array('site', 'date', 'webargs') as $key) {
            if (array_key_exists($key, $GLOBALS)) {
                $allParameters[$key] = $GLOBALS[$key];
            }
        }
        // Add provided parameters
        if (! is_null($parameters)) {
            foreach ($parameters as $key => $value) {
                $allParameters[$key] = $value;
            }
        }
        debugMsg("ModelledTable::__construct: allParameters", $allParameters);

        $contentArray = array('name' => $instanceName, 'param' => $allParameters);
        $this->result = ModelledUI::sendRequest(self::TABLE, $modelName, $contentArray);
    }

    /*
     * Return the HTML/Javascript that will display the table instance with a header
     *
     * headerText: The content of the header
     * headerLevel: The heading level used, generally not required
     */
    public function getTableWithHeader($headerText, $headerLevel = 2) {
        $headerHTML = getHeader($headerText, $headerLevel, $this->instanceName);
        return $headerHTML . DDPTable::loadJavaScript() . "\n" . $this->result[self::TABLE];
    }

    /*
     * Return the HTML/Javascript that will display the table instance
     */
    public function getTable() {
        return DDPTable::loadJavaScript() . "\n" . $this->result[self::TABLE];
    }

    /*
     * Return true if the table has any rows
     */
    public function hasRows() {
        return $this->result['numrows'] > 0;
    }

    /*
     * Return URL that will display this table
     */
    public static function getTargetLink($modelName, $title) {
        return makeLink(
            "/common/modelledtarget.php",
            $title,
            array(
                'modeltype' => 'table',
                'modelname' => $modelName
            )
        );
    }
}
