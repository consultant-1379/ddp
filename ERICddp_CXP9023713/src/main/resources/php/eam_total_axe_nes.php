<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>Total AXE NEs in Network per Initiator Type</h1>
<?php

class EAMTotalAXENEsIndex extends DDPObject {
    var $cols = array(
        "initiator_type" => "Initiator Type",
        "count" => "No. of AXE NEs"
    );

    var $defaultOrderBy = "initiator_type";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "SELECT t.name AS 'initiator_type', COUNT(*) AS 'count' " .
               "FROM eai_esi_map_detail de, sites s, eam_initiator_responder_types t " .
               "WHERE de.siteid = s.id AND date = '$date' AND s.name = '$site' AND de.initiatortypeid = t.id " .
               "GROUP BY t.name";

        $this->populateData($sql);
        // Populate the data object
        $webargs0 = $webargs;
        foreach ($this->data as $key => $d) {
            $webargs = $webargs0 . "&initiator_type=" . $d['initiator_type'];
            $d['count'] = "<a href=\"$phpDir/eam_axe_nes.php?$webargs\">" . $d['count'] . "</a>\n";
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<h5><a href=\"$phpDir/eam_stats.php?$webargs\">Return to EAM Statistics main page</a></h5>\n";
$sidx = new EAMTotalAXENEsIndex();
$dataCntCheck = $sidx->getData();
if (isset($dataCntCheck[0]['initiator_type'])) {
    $sidx->getSortableHtmlTable();
} else {
    echo "<b><i>No AXE NEs for this date</i></b>\n";
}

include "../php/common/finalise.php";
?>
