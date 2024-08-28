<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>Connected NEs per Initiator</h1>
<?php

class EAMConnectedNEsIndex extends DDPObject {
    var $cols = array(
        "period" => "Time",
        "count" => "No. of Connected NEs"
    );

    var $defaultOrderBy = "period";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $initTypeId;
        $sql = "
            SELECT CONCAT(p.start_time, ' - ',  p.end_time) AS 'period',
                   periodid AS 'periodid',
                   CONCAT(i.name, '', d.initiator_index) AS 'initiator_type',
                   COUNT(eam_neid) AS 'count'
            FROM   eam_connected_ne_detail d,
                   sites s,
                   eam_spr_periods p,
                   eam_ne_names ne,
                   eam_initiator_responder_types i
            WHERE  d.siteid = s.id AND s.name = '" . $site . "' AND d.time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
            AND    i.id = " . $initTypeId . "
            AND    d.eam_neid = ne.id
            AND    d.periodid = p.id
            AND    d.initiatortypeid = i.id
            GROUP BY periodid, initiatortypeid";

        $this->populateData($sql);
        // Populate the data object
        $webargs0 = $webargs;
        foreach ($this->data as $key => $d) {
            $webargs = $webargs0 . "&initiator_type=" . $d['initiator_type'] . "&initiator_typeid=" . $initTypeId . "&periodid=" . $d['periodid'];
            $d['count'] = "<a href=\"$phpDir/eam_conn_ne_sessions.php?$webargs\">" . $d['count'] . "</a>\n";
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class EAMInitTypesIndex extends DDPObject {
    var $cols = array(
        "id" => "initiator_typeid",
        "name" => "initiator_type"
    );

    var $defaultOrderBy = "id";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $dataset;
        $sql = "
            SELECT DISTINCT i.id,
                   CONCAT(i.name, '', d.initiator_index) AS 'initiator_type'
            FROM   eam_connected_ne_detail d,
                   sites s,
                   eam_spr_periods p,
                   eam_initiator_responder_types i
            WHERE  d.siteid = s.id AND s.name = '" . $site . "' AND d.time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
            AND    d.periodid = p.id
            AND    d.initiatortypeid = i.id";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}


// Display the Data
echo "<h5><a href=\"$phpDir/eam_stats.php?$webargs\">Return to EAM Statistics main page</a></h5>\n";
$inittypesidx = new EAMInitTypesIndex();
$r_initiatortypes = $inittypesidx->getData();
if (isset($r_initiatortypes[0]['initiator_type'])) {
    foreach ($r_initiatortypes as $ind => $initTypesArr) {
        $initType = $initTypesArr["initiator_type"];
        $initTypeId = $initTypesArr["id"];
        echo "<h2><i>$initType</i></h2>\n";
        $sidx = new EAMConnectedNEsIndex();
        $dataCntCheck = $sidx->getData();
        if (isset($dataCntCheck[0]['initiator_type'])) {
            $sidx->getSortableHtmlTable();
        } else {
            echo "<b><i>No AXE NEs for this date</i></b>\n";
        }
    }
}

include "../php/common/finalise.php";
?>
