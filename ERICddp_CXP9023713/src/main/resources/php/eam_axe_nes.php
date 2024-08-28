<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$initiator_type = $_GET['initiator_type'];
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>EAM AXE NEs for Initiator Type <I><?php echo $initiator_type ?></I></h1>
<?php

class EAMAXENEsByInitiatorTypeIndex extends DDPObject {
    var $cols = array(
        "ne_name" => "NE Name"
    );

    var $defaultOrderBy = "ne_name";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }
    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $initiator_type;
        $sql = "
            SELECT na.name AS 'ne_name'
            FROM   eai_esi_map_detail de,
                   sites s,
                   eam_initiator_responder_types t,
                   eam_ne_names na
            WHERE  de.siteid = s.id AND date = '" . $date . "'
            AND    s.name = '" . $site . "'
            AND    t.name = '" . $initiator_type . "'
            AND    de.initiatortypeid = t.id
            AND    de.eam_neid = na.id";
        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<h5><a href=\"$phpDir/eam_total_axe_nes.php?$webargs\">Return to Total AXE NEs page</a></h5>\n";
$sidx = new EAMAXENEsByInitiatorTypeIndex();
$dataCntCheck = $sidx->getData();
if (isset($dataCntCheck[0]['ne_name'])) {
    $sidx->getSortableHtmlTable();
} else {
    echo "<b><i>No AXE NEs for this Initiator Type</i></b>\n";
}

include "../php/common/finalise.php";
?>
