<?php
$pageTitle = "EAM Statistics";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$dataset = $_GET['dataset'];
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();
?>
<h1>EAM Special Commands Settings</h1>
<?php

class EAMSettingsIndex extends DDPObject {
    var $cols = array(
        "command" => "Command",
        "setting" => "Setting",
        "value" => "Value"
    );

    var $defaultOrderBy = "command";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $dataset, $group;
        $sql = "
            SELECT da.name AS 'dataset',
                   g.name AS 'group',
                   c.name AS 'command',
                   t.description AS 'setting',
                   de.setting_value AS 'value'
            FROM   eam_sp_cmd_details de,
                   sites s,
                   eam_datasets da,
                   eam_sp_cmd_groups g,
                   eam_sp_cmd_names c,
                   eam_sp_cmd_settings t
            WHERE  date = '" . $date . "' AND siteid = s.id AND s.name = '" . $site . "' AND da.name = '" . $dataset . "' AND g.name = '" . $group . "'
            AND    de.siteid = s.id
            AND    de.datasetid = da.id
            AND    de.grpid = g.id
            AND    de.cmdid= c.id
            AND    de.settingid = t.id";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

class EAMGroupsIndex extends DDPObject {
    var $cols = array(
        "id" => "groupid",
        "name" => "group"
    );

    var $defaultOrderBy = "id";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs, $dataset;
        $sql = "
            SELECT DISTINCT g.id AS 'id',
                   g.name AS 'group'
            FROM   eam_sp_cmd_details de,
                   sites s,
                   eam_datasets da,
                   eam_sp_cmd_groups g
            WHERE  date = '" . $date . "' AND siteid = s.id AND s.name = '" . $site . "' AND da.name = '" . $dataset . "'
            AND    de.siteid = s.id
            AND    de.datasetid = da.id
            AND    de.grpid = g.id";

        $this->populateData($sql);
        // Populate the data object
        foreach ($this->data as $key => $d) {
            $this->data[$key] = $d;
        }
        return $this->data;
    }
}

// Display the Data
echo "<a href=\"$phpDir/eam_stats.php?$webargs\">Return to EAM Statistics main page</a>\n";
$groupsIdx = new EAMGroupsIndex();
$r_groups = $groupsIdx->getData();
if (isset($r_groups[0]['group'])) {
    echo "<h2><i>${dataset}_Command</i></h2>\n";
    foreach ($r_groups as $ind => $groupArr) {
        $group = $groupArr["group"];
        $sidx = new EAMSettingsIndex();
        echo "<h4><i>$group</i></h4>\n";
        $sidx->getSortableHtmlTable();
    }
}

include "../php/common/finalise.php";
?>
