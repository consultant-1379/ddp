<?php
$pageTitle = "GPI Events";
include "common/init.php";
require_once 'HTML/Table.php';

$statsDB = new StatsDB();

require_once PHP_ROOT . "/classes/DDPObject.class.php";
class GPIEvents extends DDPObject {
    var $cols = array(
        "start_time" => "Start Time",
        "end_time" => "End Time",
        "duration" => "Duration",
        "BTSadded" => "BTSs Added",
        "BTSremoved" => "BTSs Removed",
        "BTSmodified" => "BTSs Modified",
        "AssocCreated" => "Associations Created",
        "AssocRemoved" => "Associations Removed",
        "CabiCreated" => "Cabinet Groups Created",
        "CabiRemoved" => "Cabinet Groups Removed",
        "AffectedMOs" => "Total Affected MOs",
        "mos_sec" => "MOs per Second"
    );

    var $defaultOrderBy = "start_time";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("gpi_events");
    }

    function getData() {
        global $site, $date;
        $sql = "SELECT start_time,end_time,TIME_TO_SEC(TIMEDIFF(end_time,start_time)) AS duration,
            BTSadded,BTSremoved,BTSmodified,AssocCreated,AssocRemoved,CabiCreated,CabiRemoved,
            BTSadded + BTSremoved + BTSmodified + AssocCreated + AssocRemoved + CabiCreated + CabiRemoved AS AffectedMOs,
            ((BTSadded + BTSremoved + BTSmodified + AssocCreated + AssocRemoved + CabiCreated + CabiRemoved)
            / TIME_TO_SEC(TIMEDIFF(end_time,start_time))) AS mos_sec
            FROM sites, gpi_events WHERE sites.name = '" . $site . "' AND
            sites.id = gpi_events.siteid AND
            gpi_events.end_time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'";
        $this->populateData($sql);
        return $this->data;
    }
}

?>
    <h1>GPI Events</h1>

<?php
$gpiEvents = new GPIEvents();
$gpiEvents->getSortableHtmlTable();
include "common/finalise.php";
?>
