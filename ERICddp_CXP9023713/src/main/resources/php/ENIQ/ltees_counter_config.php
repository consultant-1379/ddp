<?php
$pageTitle = "LTE-ES Counter Configuration";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}
if ( isset($_GET['date']) ) {
    $date = $_GET['date'];
}

class CounterConfig extends DDPObject {
    var $cols;
    var $maxDatetime;

    function __construct() {
        parent::__construct("CounterConfig");
    }

    function getData() {
        global $site;
        global $requiredColumn;
        global $additionalWhereClause;
        unset($this->data);
        $sql = "
            SELECT
             $requiredColumn
            FROM
             eniq_ltees_counter_details, sites
            WHERE
             sites.name = '$site' AND
             siteid = sites.id
             $additionalWhereClause
        ";
        $this->populateData($sql);
        if (strpos($requiredColumn, datetime) != false) {
            $this->maxDatetime = $this->data[0][$requiredColumn];
        }
        return $this->data;
    }
}
$counterConfigurationHelp = <<<EOT
    This page shows information about LTE-ES Counter Configuration Time and list of enabled counters.
EOT;
drawHeaderWithHelp("LTE-ES Counter Configuration", 1, "counterConfigurationHelp", $counterConfigurationHelp);
$requiredColumn = "max(datetime)";
$additionalWhereClause = "AND eniq_ltees_counter_details.datetime <= '$date 23:59:59'";
$counterConfig = new CounterConfig();
$counterConfig->cols = array('max(datetime)' => 'Counter Configuration Time');
echo $counterConfig->getHtmlTable();
echo "<br>";

$requiredColumn = "IF(counter_name = ' ','No counters are enabled',counter_name) as counterName";
$additionalWhereClause = "AND eniq_ltees_counter_details.datetime = '$counterConfig->maxDatetime'";
$counterConfig->cols = array('counterName' => 'List of Enabled Counters');
$counterConfig->getData();
echo $counterConfig->getClientSortableTableStr();

include "../common/finalise.php";
?>

