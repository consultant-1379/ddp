<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
require_once PHP_ROOT . "/classes/DDPObject.class.php";
$initType = $_GET['initiator_type'];
$initTypeId = $_GET['initiator_typeid'];
$periodid = $_GET['periodid'];

$statsDB = new StatsDB();
?>
<?php echo "<h1>" . $initType . " Sessions per NE</h1>" ?>
<?php

class EAMConnectedNESessionsIndex extends DDPObject {
    var $cols = array(
        "ne" => "NE",
        "count" => "No. of Sessions"
    );

    var $defaultOrderBy = "ne";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $initType, $initTypeId, $periodid, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT eam_neid AS 'eam_neid',
                   ne.name AS 'ne',
                   COUNT(eam_neid) AS 'count'
            FROM   eam_connected_ne_detail d,
                   sites s,
                   eam_spr_periods p,
                   eam_ne_names ne,
                   eam_initiator_responder_types i
            WHERE  d.siteid = s.id AND s.name = '" . $site . "' AND d.time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
            AND    d.eam_neid = ne.id
            AND    d.periodid = p.id
            AND    i.id = " . $initTypeId . " AND p.id = " . $periodid . "
            AND    d.initiatortypeid = i.id
            GROUP BY eam_neid";

        $this->populateData($sql);
        // Populate the data object
        $webargs0 = $webargs;

        foreach ($this->data as $key => $d) {
            $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid . "&eam_neid=" . $d['eam_neid'] . "&dataset=apps";
            $d['ne'] = "<a href=\"$phpDir/eam_conn_ne_apps_cmds.php?$webargs\">" . $d['ne'] . "</a>\n";
            $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid . "&eam_neid=" . $d['eam_neid'] . "&dataset=cmds";
            $d['count'] = "<a href=\"$phpDir/eam_conn_ne_apps_cmds.php?$webargs\">" . $d['count'] . "</a>\n";
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<h5><a href=\"$phpDir/eam_conn_ne_counts.php?$webargs\">Return to Connected NEs per Initiator page</a></h5>\n";
$sidx = new EAMConnectedNESessionsIndex();
$dataCntCheck = $sidx->getData();
if (isset($dataCntCheck[0]['ne'])) {
    $sidx->getSortableHtmlTable();
} else {
    echo "<b><i>No AXE NEs for this date</i></b>\n";
}

include "../php/common/finalise.php";
?>
