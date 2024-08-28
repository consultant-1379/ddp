<?php
$pageTitle = "BCG Instrumentation";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$webargs = "site=$site&dir=$dir&date=$date&oss=$oss";

$statsDB = new StatsDB();
?>
<h1>Bulk Configuration General (BCG) Instrumentation</h1>
<?php

class BCGImportIndex extends DDPObject {
    var $cols = array(
        "activityid" => "Activity ID",
        "start_time" => "Start Time",
        "end_time" => "End Time",
        "num_commands" => "Total No. of Commands",
        "num_successful_commands" => "Total No. of Successful Commands",
        "num_failed_commands" => "Total No. of Failed Commands ",
        "num_import_trans" => "No. of Transactions",
        "num_retries_trans" => "No. of Transactions for retries",
        "num_retries_for_locks" => "No. of retries for locks",
        "overall_import_status" => "Overall Import Status"
    );

    var $defaultOrderBy = "activityid";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT activityid AS activityid,
                   start_time AS start_time,
                   end_time AS end_time,
                   num_commands AS num_commands,
                   num_successful_commands AS num_successful_commands,
                   num_failed_commands AS num_failed_commands,
                   num_import_trans AS num_import_trans,
                   num_retries_trans AS num_retries_trans,
                   num_retries_for_locks AS num_retries_for_locks,
                   overall_import_status AS overall_import_status
            FROM   bcg_instr_import, sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        // Add edit link to activityid column, but the query is based on the
        // the unique keys on the table of date, siteid, actityid
        $webargs0 = $webargs;
        foreach ($this->data as $key => $d) {
            $webargs = $webargs0 . "&operation=import&activityid=" . $d['activityid'];
            if ($d['overall_import_status'] != 'FAILURE') {
                $d['activityid'] = "<a href=\"$phpDir/bcg_instr_other_operations.php?$webargs\">" . $d['activityid'] . "</a>\n";
            }
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGExportIndex extends DDPObject {
    var $cols = array(
        "activityid" => "Activity ID",
        "start_time" => "Start Time",
        "end_time" => "End Time",
        "total_num_mo_exports" => "Total No. of MO Exports",
        "num_mo_successful_exports" => "Total No. of nodes exported successfully",
        "num_mo_failed_exports" => "Total No. of nodes not exported successfully",
        "mo_per_sec_export" => "MO/Second exported"
    );

    var $defaultOrderBy = "activityid";
    var $defaultOrderDir = "ASC";

//    function __construct() {
 //       parent::__construct("sitelist");
  //  }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT activityid AS activityid,
                   start_time AS start_time,
                   end_time AS end_time,
                   total_num_mo_exports AS total_num_mo_exports,
                   num_mo_successful_exports AS num_mo_successful_exports,
                   num_mo_failed_exports AS num_mo_failed_exports,
                   mo_per_sec_export AS mo_per_sec_export
            FROM   bcg_instr_export, sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        // Add edit link to activityid column, but the query is based on the
        // the unique keys on the table of date, siteid, actityid
        $webargs0 = $webargs;
        foreach ($this->data as $key => $d) {
            $webargs = $webargs0 . "&operation=export&activityid=" . $d['activityid'];
            $d['activityid'] = "<a href=\"$phpDir/bcg_instr_other_operations.php?$webargs\">" . $d['activityid'] . "</a>\n";
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

?>
<h2>Import</h2>
<?php

$sidxImport = new BCGImportIndex();
$sidxImport->getSortableHtmlTable();

?>
<h2>Export</h2>
<?php

$sidxExport = new BCGExportIndex();
$sidxExport->getSortableHtmlTable();

include "../php/common/finalise.php";
return;
?>
