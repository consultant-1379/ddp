<?php
$pageTitle = "BCG Instrumentation";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$activityid = $_GET['activityid'];
$operation = $_GET['operation'];
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>BCG Instrumentation: Other Operations</h1>
<?php

class BCGImportOtherOpsTimeSumsIndex extends DDPObject {
    var $cols = array(
        "operation" => "Operation",
        "total_time" => "Total Time"
    );

    var $defaultOrderBy = "operation";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid;
        $sql = "
            SELECT 'CS' AS 'operation', SUM(bcg_instr_other_operations.total_time) AS 'total_time'
            FROM bcg_instr_other_operations, bcg_instr_operation_types, sites
            WHERE bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id
            AND bcg_instr_operation_types.name LIKE '%CS%Operation'
            AND date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            GROUP BY date, siteid, activityid
            UNION
            SELECT 'JMS' AS 'operation', SUM(bcg_instr_other_operations.total_time) AS 'total_time'
            FROM bcg_instr_other_operations, bcg_instr_operation_types, sites
            WHERE bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id
            AND bcg_instr_operation_types.name LIKE '%JMS%Operation'
            AND date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            GROUP BY date, siteid, activityid";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGImportOtherOperationsCSIndex extends DDPObject {
    var $cols = array(
        "operation" => "Operation",
        "num_hits" => "No. of Hits",
        "calls_per_sec" => "Calls/Second",
        "total_time" => "Total Time"
    );

    var $defaultOrderBy = "total_time";
    var $defaultOrderDir = "DESC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid, $MOType;
        $sql = "
            SELECT activityid AS activityid,
                   bcg_instr_operation_types.name AS 'operation',
                   num_hits AS num_hits,
                   calls_per_sec AS calls_per_sec,
                   total_time AS total_time
            FROM   bcg_instr_other_operations,
                   sites,
                   bcg_instr_operation_types,
                   mo_names
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            AND    bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id
            AND    bcg_instr_other_operations.moid = mo_names.id
            AND    mo_names.name = '" . $MOType . "'";


        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGImportOtherOperationsIndex extends DDPObject {
    var $cols = array(
        "operation" => "Operation",
        "num_hits" => "No. of Hits",
        "calls_per_sec" => "Calls/Second",
        "total_time" => "Total Time"
    );

    var $defaultOrderBy = "total_time";
    var $defaultOrderDir = "DESC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid, $otherOperation;
        $sql = "
            SELECT activityid AS activityid,
                   bcg_instr_operation_types.name AS 'operation',
                   num_hits AS num_hits,
                   calls_per_sec AS calls_per_sec,
                   total_time AS total_time
            FROM   bcg_instr_other_operations,
                   sites,
                   bcg_instr_operation_types
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            AND    bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id
            AND    bcg_instr_other_operations.system_moid = 0
            AND    bcg_instr_operation_types.name LIKE '%" . $otherOperation . "%'";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGMOTypesIndex extends DDPObject {
    var $cols = array(
        "mo_type" => "MO Type"
    );

    var $defaultOrderBy = "mo_type";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid;
        $sql = "
            SELECT DISTINCT mo_names.name AS 'mo_type'
            FROM   bcg_instr_mo_types,
                   sites,
                   mo_names
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            AND    bcg_instr_mo_types.moid = mo_names.id";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGOtherOperationsIndex extends DDPObject {
    var $cols = array(
        "other_operation" => "Other Operation"
    );

    var $defaultOrderBy = "other_operation";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid;
        $sql = "
            SELECT bcg_instr_operation_types.name AS 'other_operation'
            FROM   bcg_instr_other_operations,
                   sites,
                   bcg_instr_operation_types
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            AND    bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id
            AND    bcg_instr_other_operations.moid = 0 AND bcg_instr_other_operations.system_moid = 0";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGImportOtherOperationsSysIndex extends DDPObject {
    var $cols = array(
        "name" => "MO Type",
        "operation" => "Operation",
        "num_hits" => "No. of Hits",
        "calls_per_sec" => "Calls/Second",
        "total_time" => "Total Time"
    );

    var $defaultOrderBy = "total_time";
    var $defaultOrderDir = "DESC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid, $otherOperation;
        $sql = "
            SELECT bcg_instr_system_mo_names.name AS name,
                   bcg_instr_operation_types.name AS 'operation',
                   num_hits AS num_hits,
                   calls_per_sec AS calls_per_sec,
                   total_time AS total_time
            FROM   bcg_instr_other_operations,
                   bcg_instr_system_mo_names,
                   sites,
                   bcg_instr_operation_types
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            AND    bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id
            AND    bcg_instr_other_operations.system_moid = bcg_instr_system_mo_names.id
            AND    bcg_instr_other_operations.moid = 0 AND bcg_instr_other_operations.system_moid != 0";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class BCGExportOtherOperationsIndex extends DDPObject {
    var $cols = array(
        "operation" => "Operation",
        "num_hits" => "No. of Hits",
        "calls_per_sec" => "Calls/Second",
        "total_time" => "Total Time",
        "fdn" => "FDN",
        "cs_export_xml_file" => "CS Export XML File"
    );

    var $defaultOrderBy = "operation";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $activityid;
        $sql = "
            SELECT activityid AS activityid,
                   bcg_instr_operation_types.name AS 'operation',
                   num_hits AS num_hits,
                   calls_per_sec AS calls_per_sec,
                   total_time AS total_time,
                   fdn AS fdn,
                   cs_export_xml_file AS cs_export_xml_file
            FROM   bcg_instr_other_operations,
                   sites,
                   bcg_instr_operation_types
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "' AND activityid = " . $activityid . "
            AND    bcg_instr_other_operations.other_oper_id = bcg_instr_operation_types.id";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

echo "<a href=\"$phpDir/bcg_instr.php?$webargs\">Return to BCG Instrumentation Parent Data</a>\n";
echo "<h3>Activity ID: " . $activityid . " (" . $operation . ")</h3>\n";

if ($operation == 'import') {
    // Display the Import Other Operations Data
    $MOTypesIdx = new BCGMOTypesIndex();
    $r_MOTypes = $MOTypesIdx->getData();
    if (isset($r_MOTypes[0]['mo_type'])) {
        foreach ($r_MOTypes as $ind => $MOTypeArr) {
            $MOType = $MOTypeArr["mo_type"];
            $sidx = new BCGImportOtherOperationsCSIndex();
            echo "<h5><i>$MOType</i></h5>\n";
            $sidx->getSortableHtmlTable();
        }
    }

    // Display the CS Commit & JMS data
    $OtherOperationsIdx = new BCGOtherOperationsIndex();
    $r_otherOperations = $OtherOperationsIdx->getData();

    foreach ($r_otherOperations as $ind => $otherOperations) {
        $otherOperation = $otherOperations['other_operation'];
        $sidx = new BCGImportOtherOperationsIndex();
        $dataCntCheck = $sidx->getData();
        if (isset($dataCntCheck[0]['operation'])) {
            echo "<h5><i>$otherOperation</i></h5>\n";
            $sidx->getSortableHtmlTable();
        }
    }

    // Display the Other Operations Data for System generated MO Types
    $sysMOTypesIdx = new BCGImportOtherOperationsSysIndex();
    $sysMOTypeDataCntCheck = $sysMOTypesIdx->getData();
    if (isset($sysMOTypeDataCntCheck[0]['name'])) {
        echo "<h4>System Generated MO Types</h4>\n";
        $sysMOTypesIdx->getSortableHtmlTable();
    }

    // Display the Total Time Sums for the CS Operations
    $sidxTot = new BCGImportOtherOpsTimeSumsIndex();
    $sidxTotCntCheck = $sidxTot->getData();
    if (isset($sidxTotCntCheck[0]['operation'])) {
        echo "<h3>Total Time Summary</h3>\n";
        $sidxTot->getSortableHtmlTable();
    }
} else {
    // Display the Export Other Operations Data
    $sidx = new BCGExportOtherOperationsIndex();
    $dataCntCheck = $sidx->getData();
    if (isset($dataCntCheck[0]['operation'])) {
        $sidx->getSortableHtmlTable();
    } else {
        echo "<b><i>No Other Operations for Activity ID: " . $activityid . " (" . $operation . "). </i></b>\n";
    }
}

include "../php/common/finalise.php";
?>
