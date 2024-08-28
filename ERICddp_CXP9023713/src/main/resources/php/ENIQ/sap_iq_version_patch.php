<?php
$pageTitle = "SAP IQ Version-Patch";

include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();

class SAPIQVersionPatch extends DDPObject {
    var $cols = array(
                    'version' => 'Version',
                    'patch'   => 'Patch'
                );

    function __construct() {
        parent::__construct("Instance");
    }

    function getData() {
        global $site, $date;
        $sql = "
            SELECT
             version, patch
            FROM
             eniq_sap_iq_version_patch_details, sites
            WHERE
             sites.id = eniq_sap_iq_version_patch_details.siteid AND
             sites.name = '$site' AND
             date='$date'
            ";

        $this->populateData($sql);
        return $this->data;
    }
}

$versionPatchHelp = <<<EOT
This page displays information about the version and patch of SAP IQ for ENIQ deployment.
EOT;
drawHeaderWithHelp("SAP IQ Version", 1, "versionPatchHelp", $versionPatchHelp);

$sapiqVersionPatch = new SAPIQVersionPatch();
$sapiqVersionPatch->getHtmlTable();

include "../common/finalise.php";
?>