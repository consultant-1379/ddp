<?php
$pageTitle = "SAP IQ Disk Utilization";
$YUI_DATATABLE = true;

include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();

class SAPIQDbSpace extends DDPObject {
    var $cols = array(
        'space' => 'DB Space',
        'size' => 'Size (GB)',
        'used' => 'Used (%)',
        'files' => 'File(s)'
        );

    function __construct() {
        parent::__construct("Instance");
    }

    function getData() {
        global $site;
        global $date;
        $sql = "
            SELECT
             iq_dbspaces.space, iq_dbspaces.size, iq_dbspaces.used, iq_dbspaces.files
            FROM
             iq_dbspaces, sites
            WHERE
             sites.id = iq_dbspaces.siteid AND
             sites.name = '" . $site . "' AND
             date='" . $date . "'
             ";

         $this->populateData($sql);
         return $this->data;
    }
}

$help = <<<EOT
This page displays SAP IQ disk utilization for ENIQ.
EOT;
drawHeaderWithHelp("SAP IQ Disk Utilization", 1, "Help", $help);

$sapiqdbspace = new SAPIQDbSpace();
$sapiqdbspace->getHtmlTable();

include "../common/finalise.php";
?>
