<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
require_once PHP_ROOT . "/classes/DDPObject.class.php";
$initType = $_GET['initiator_type'];
$initTypeId = $_GET['initiator_typeid'];
$periodid = $_GET['periodid'];
$eam_neid = $_GET['eam_neid'];
$dataset = $_GET['dataset'];

$statsDB = new StatsDB();

if ( $dataset == 'apps') {
    class EAMNEsAppsCmdsDataIndex extends DDPObject {
        var $cols = array(
            "session_index" => "Session",
            "application" => "Application"
        );

        var $defaultOrderBy = "session_index";
        var $defaultOrderDir = "ASC";

        function __construct() {
            parent::__construct("sitelist");
        }

        function getData() {
            global $date, $site, $initTypeId, $periodid, $eam_neid, $statsDB, $phpDir, $webargs;
            $sql = "
                SELECT 1 AS 'session_index',
                       a.name AS 'application'
                FROM   eam_connected_ne_detail d,
                       sites s,
                       eam_spr_periods p,
                       eam_ne_names ne,
                       eam_initiator_responder_types i,
                       eam_app_names a
                WHERE  d.siteid = s.id AND s.name = '" . $site . "' AND d.time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
                AND    d.eam_neid = ne.id AND d.periodid = p.id AND d.initiatortypeid = i.id AND d.appid = a.id
                AND    i.id = " . $initTypeId . " AND p.id = " . $periodid . " AND d.eam_neid = " . $eam_neid;

            $this->populateData($sql);
            // Populate the data object
            $webargs0 = $webargs;

            $sessionIdx = 1;
            foreach ($this->data as $key => $d) {
                $d['session_index'] = $sessionIdx;
                $this->data[$key] = $d;
                $sessionIdx++;
            }
            return $this->data;
        }
    }
    // Display the Data
    $sidx = new EAMNEsAppsCmdsDataIndex();
    $dataCntCheck = $sidx->getData();
    if (isset($dataCntCheck[0]['application'])) {
        echo '<h1>Applications</h1>';
        $webargs0 = $webargs;
        $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid;
        echo "<h5><a href=\"$phpDir/eam_conn_ne_sessions.php?$webargs\">Return to Connected NE sessions page</a></h5>\n";
        $sidx->getSortableHtmlTable();
    } else {
        $webargs0 = $webargs;
        $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid;
        echo "<h5><a href=\"$phpDir/eam_conn_ne_sessions.php?$webargs\">Return to Connected NE sessions page</a></h5>\n";
        echo "<b><i>No Applications data for selected AXE NE for this date</i></b>\n";
    }
} elseif ( $dataset == 'cmds') {
    class EAMNEsAppsCmdsDataIndex extends DDPObject {
        var $cols = array(
            "command" => "Command",
            "count" => "No. of times sent"
        );

        var $defaultOrderBy = "command";
        var $defaultOrderDir = "ASC";

        function __construct() {
            parent::__construct("sitelist");
        }

        function getData() {
            global $date, $site, $initType, $initTypeId, $periodid, $eam_neid, $statsDB, $phpDir, $webargs;
            $sql = "
                SELECT c.name AS 'command',
                       COUNT(commandid) AS 'count'
                FROM   eam_connected_ne_detail d,
                       sites s,
                       eam_spr_periods p,
                       eam_ne_names ne,
                       eam_initiator_responder_types i,
                       eam_cmd_names c
                WHERE  d.siteid = s.id AND s.name = '" . $site . "' AND d.time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
                AND    d.eam_neid = ne.id AND d.periodid = p.id AND d.initiatortypeid = i.id AND d.commandid = c.id
                AND    i.id = " . $initTypeId . " AND p.id = " . $periodid . " AND d.eam_neid = " . $eam_neid . "
                GROUP BY commandid";

            $this->populateData($sql);
            // Populate the data object
            $webargs0 = $webargs;

            foreach ($this->data as $key => $d) {
                $this->data[$key] = $d;
            }
            return $this->data;
        }
    }
    // Display the Data
    $sidx = new EAMNEsAppsCmdsDataIndex();
    $dataCntCheck = $sidx->getData();
    if (isset($dataCntCheck[0]['command'])) {
        echo '<h1>Commands</h1>';
        $webargs0 = $webargs;
        $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid;
        echo "<h5><a href=\"$phpDir/eam_conn_ne_sessions.php?$webargs\">Return to Connected NE sessions page</a></h5>\n";
        $sidx->getSortableHtmlTable();
    } else {
        $webargs0 = $webargs;
        $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid;
        echo "<h5><a href=\"$phpDir/eam_conn_ne_sessions.php?$webargs\">Return to Connected NE sessions page</a></h5>\n";
        echo "<b><i>No Commands data for selected AXE NE for this date</i></b>\n";
    }
} else {
    $webargs0 = $webargs;
    $webargs = $webargs0 . "&initiator_type=" . $initType . "&initiator_typeid=" . $initTypeId . "&periodid=" . $periodid;
    echo "<h5><a href=\"$phpDir/eam_conn_ne_sessions.php?$webargs\">Return to Connected NE sessions page</a></h5>\n";
    echo "<h2><i>No further drilldown available for selected AXE NE for this date<i></h2>";
}

include "../php/common/finalise.php";
?>
