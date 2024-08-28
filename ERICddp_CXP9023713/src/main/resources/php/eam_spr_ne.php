<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$periodid = $_GET['periodid'];
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>EAM Spontaneous Reports Detail</h1>
<?php

class EAMSpontaneousReportsNEIndex extends DDPObject {
    var $cols = array(
        "ne" => "NE Name",
        "count" => "No. of Spontaneous Reports"
    );

    var $defaultOrderBy = "ne";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }
    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $periodid;
        $sql = "
            SELECT CONCAT(p.start_time, ' - ',  p.end_time) AS 'time',
                   ne.name AS 'ne',
                   COUNT(de.is_spontaneous) AS 'count'
            FROM   eam_spr_details de,
                   sites s,
                   eam_spr_periods p,
                   eam_ne_names ne
            WHERE  de.siteid = s.id AND s.name = '" . $site . "' AND time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
            AND    de.periodid = p.id AND de.periodid = " . $periodid . "
            AND    p.len = 'HALFHOUR'
            AND    de.is_spontaneous = 1
            AND    de.eam_neid = ne.id
            GROUP BY de.siteid, de.periodid, de.is_spontaneous, de.eam_neid";
        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<h5><a href=\"$phpDir/eam_spr.php?$webargs\">Return to EAM Spontaneous Reports Summary page</a></h5>\n";
$sidx = new EAMSpontaneousReportsNEIndex();
$dataCntCheck = $sidx->getData();
if (isset($dataCntCheck[0]['time'])) {
    $sidx->getSortableHtmlTable();
} else {
    echo "<b><i>No Spontaneous Reports for this period</i></b>\n";
}

include "../php/common/finalise.php";
?>
