<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>EAM Spontaneous Reports Summary</h1>
<?php

class EAMSpontaneousReportsIndex extends DDPObject {
    var $cols = array(
        "time" => "Time",
        "count" => "No. of Spontaneous Reports"
    );

    var $defaultOrderBy = "time";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT CONCAT(p.start_time, ' - ',  p.end_time) AS 'time',
                   periodid AS 'periodid',
                   COUNT(de.is_spontaneous) AS 'count'
            FROM   eam_spr_details de,
                   sites s,
                   eam_spr_periods p
            WHERE  de.siteid = s.id AND s.name = '" . $site . "' AND time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'
            AND    de.periodid = p.id AND p.len = 'HALFHOUR'
            AND    de.is_spontaneous = 1
            GROUP BY de.siteid, de.periodid, de.is_spontaneous";

        $this->populateData($sql);
        // Populate the data object
        $webargs0 = $webargs;
        foreach ($this->data as $key => $d) {
            $webargs = $webargs0 . "&periodid=" . $d['periodid'];
            $d['count'] = "<a href=\"$phpDir/eam_spr_ne.php?$webargs\">" . $d['count'] . "</a>\n";
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<h5><a href=\"$phpDir/eam_stats.php?$webargs\">Return to EAM Statistics main page</a></h5>\n";
$sidx = new EAMSpontaneousReportsIndex();
$dataCntCheck = $sidx->getData();
if (isset($dataCntCheck[0]['time'])) {
    $sidx->getSortableHtmlTable();
} else {
    echo "<b><i>No Spontaneous Reports for this date</i></b>\n";
}

include "../php/common/finalise.php";
?>
