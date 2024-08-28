<?php
$pageTitle = "PCI Metrics";
$YUI_DATATABLE = true;
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class InitialisationServiceIndex extends DDPObject {
    var $cols = array(
        "time" => "Time",
        "identity" => "Identity",
        "user" => "User ID",
        "current_number_of_ongoing_threads" => "Current No. of Ongoing Threads",
        "time_taken_to_initialise_the_pci_service" => "Time Taken to Initialise the PCI Service",
        "time_taken_to_initialise_the_handlers_facade" => "Time Taken to Initialise the Handlers Facade",
        "time_taken_to_initialise_the_cache_manager" => "Time Taken to Initialise the Cache Manager"
    );

    var $defaultOrderBy = "time";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("InitialisationServiceIndex");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT iss.time AS 'time',
                   iss.identity AS 'identity',
                   u.name AS 'user',
                   iss.current_number_of_ongoing_threads AS 'current_number_of_ongoing_threads',
                   iss.time_taken_to_initialise_the_pci_service AS 'time_taken_to_initialise_the_pci_service',
                   iss.time_taken_to_initialise_the_handlers_facade AS 'time_taken_to_initialise_the_handlers_facade',
                   iss.time_taken_to_initialise_the_cache_manager AS 'time_taken_to_initialise_the_cache_manager'
            FROM   pci_cif_log_initialisation_of_service_stats iss, sites, app_user_names u
            WHERE  iss.time BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND    iss.siteid = sites.id AND sites.name = '" . $site . "'
            AND    iss.user_idid = u.id";

        $this->populateData($sql);

        return $this->data;
    }
}

class NotificationsIndex extends DDPObject {
    var $cols = array(
        "time" => "Time",
        "user" => "User ID",
        "interval_start" => "Interval Start",
        "interval_end" => "Interval End",
        "number_of_notifications_received" => "No. of Notifications Received",
        "current_number_of_buffered_notifications" => "No. of Notifications Received",
        "peak_number_of_buffered_notifications" => "Peak No. of Buffered Notifications",
        "number_of_notifications_introduced" => "No. of Notifications Introduced",
        "number_of_invalid_notifications" => "No. of Invalid Notifications",
        "number_of_cache_updates" => "No. of Cache Updates",
        "number_of_cache_builds_ordered" => "No. of Cache Builds Reordered"
    );

    var $defaultOrderBy = "time";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("NotificationsIndex");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT n.time AS 'time',
                   u.name AS 'user',
                   n.interval_start AS 'interval_start',
                   n.interval_end AS 'interval_end',
                   n.number_of_notifications_received AS 'number_of_notifications_received',
                   n.current_number_of_buffered_notifications AS 'current_number_of_buffered_notifications',
                   n.peak_number_of_buffered_notifications AS 'peak_number_of_buffered_notifications',
                   n.number_of_notifications_introduced AS 'number_of_notifications_introduced',
                   n.number_of_invalid_notifications AS 'number_of_invalid_notifications',
                   n.number_of_cache_updates AS 'number_of_cache_updates',
                   n.number_of_cache_builds_ordered AS 'number_of_cache_builds_ordered'
            FROM   pci_cif_log_notification_stats n, sites, app_user_names u
            WHERE  n.time BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND    n.siteid = sites.id AND sites.name = '" . $site . "'
            AND    n.user_idid = u.id";

        $this->populateData($sql);

        return $this->data;
    }
}

class BuildCacheIndex extends DDPObject {
    var $cols = array(
        "time" => "Time",
        "identity" => "Identity",
        "user" => "User ID",
        "current_number_of_ongoing_threads" => "Current No. of Ongoing Threads",
        "time_taken_to_build_the_cache" => "Time to Build Cache",
        "time_taken_to_read_from_the_cs" => "Time to Read from CS",
        "time_taken_to_populate_all_cache_maps" => "Time to Populate All Cache Maps",
        "time_taken_to_create_celltosubunit_map" => "Time to Create CellToSubUnit Map",
        "time_taken_to_create_celltoantennagain_map" => "Time to Create CellToAntennaGain Map",
        "time_taken_to_create_celltoantennabearing_map" => "Time to Create CellToAntennaBearing Map",
        "time_taken_to_populate_enodeb_map" => "Time to Populate eNodeB Map",
        "time_taken_to_populate_cells_map" => "Time to populate Cells Map"
    );

    var $defaultOrderBy = "time";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("BuildCacheIndex");
    }

    function getData() {
        global $date, $site, $statsDB;
        $sql = "
            SELECT b.time AS 'time',
                   b.identity AS 'identity',
                   u.name AS 'user',
                   b.current_number_of_ongoing_threads AS 'current_number_of_ongoing_threads',
                   b.time_taken_to_build_the_cache AS 'time_taken_to_build_the_cache',
                   b.time_taken_to_read_from_the_cs AS 'time_taken_to_read_from_the_cs',
                   b.time_taken_to_populate_all_cache_maps AS 'time_taken_to_populate_all_cache_maps',
                   b.time_taken_to_create_celltosubunit_map AS 'time_taken_to_create_celltosubunit_map',
                   b.time_taken_to_create_celltoantennagain_map AS 'time_taken_to_create_celltoantennagain_map',
                   b.time_taken_to_create_celltoantennabearing_map AS 'time_taken_to_create_celltoantennabearing_map',
                   b.time_taken_to_populate_enodeb_map AS 'time_taken_to_populate_enodeb_map',
                   b.time_taken_to_populate_cells_map AS 'time_taken_to_populate_cells_map'
            FROM   pci_cif_log_build_cache_stats b, sites, app_user_names u
            WHERE  b.time BETWEEN '$date" . " 00:00:00' AND '" . $date . " 23:59:59'
            AND    b.siteid = sites.id AND sites.name = '" . $site . "'
            AND    b.user_idid = u.id";

        $this->populateData($sql);

        return $this->data;
    }
}

$statsDB = new StatsDB();
echo "<h1>PCI Metrics</h1>\n";

echo "<h2>Initialisation of Service</h2>\n";
$sidxInitialisationService = new InitialisationServiceIndex();
$sidxInitialisationService->getSortableHtmlTable();

echo "<<h2>Notifications</h2>\n";
$sidxNotifications = new NotificationsIndex();
$sidxNotifications->getSortableHtmlTable();

echo "<h2>Build Cache Stats</h2>\n";
$sidxBuildCache = new BuildCacheIndex();
$sidxBuildCache->getSortableHtmlTable();

include "../php/common/finalise.php";
?>
