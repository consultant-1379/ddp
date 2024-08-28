<?php
$pageTitle = "Upgrade Information";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';

if ( isset($_GET['site']) ) {
    $site = $_GET['site'];
}
if ( isset($_GET['date']) ) {
    $date = $_GET['date'];
}

if ( isset($_GET['eniqShipmentType']) ) {
    $eniqShipmentType = $_GET['eniqShipmentType'];
    $pattern='/^ENIQ_(\w+)_Shipment.*/';
    preg_match($pattern, $eniqShipmentType, $matches);
    $eniqShipmentType = $matches[1];
}

$statsDB = new StatsDB();
$row = $statsDB->queryRow("
    SELECT
     max(date)
    FROM
     eniq_upgrade_timing_detail, sites
    WHERE
     eniq_upgrade_timing_detail.siteId = sites.id AND
     sites.name = '$site' AND
     eniq_upgrade_timing_detail.date <= '$date'
");
$maxDate = $row[0];

function getUpgradeData($requiredColumn, $requiredTable, &$key, $upgradeFlag, &$upgradeArray) {
    global $statsDB, $site, $maxDate;
    $result = $statsDB->query("
        SELECT
         $requiredColumn
        FROM
         $requiredTable, sites
        WHERE
         sites.name = '$site' AND
         $requiredTable.siteId = sites.id AND
         $requiredTable.date = '$maxDate'
    ");
    while( $result = $statsDB->getNextNamedRow() ) {
        $key = array_shift($result);
        if($key == "ENIQ platform Upgrade") {
            $key = "ENIQ Platform Upgrade";
        }
        if ($upgradeFlag) {
            $upgradeArray[$key] = $result;  //In this case $key represents Sub Section.
        }
    }
    if ($key == 1) {
        $key = "Standard";  //In this case $key represents upgrade Type Id.
    }
    else {
        $key = "Rolling";
    }
}
$upgradeTimingHelp = <<<EOT
<p>
    This page shows information about the latest upgrade performed on the ENIQ Events/Stats server.
<p>
    It shows details about the upgrade type, features upgraded and execution time of each stage.
<p>
    If upgrade fails in between due to some unexpected reason then partial information will be available.
EOT;
drawHeaderWithHelp("Upgrade Information", 1, "upgradeTimingHelp", $upgradeTimingHelp);
echo "<br>";
$requiredColumn = "upgradeTypeId";
$requiredTable = "eniq_feature_upgrade_list";
$upgradeType = "";
getUpgradeData($requiredColumn, $requiredTable, $upgradeType, false, $placeHolder = null);
$table = new HTML_Table("border=1");
$table->addRow( array('<b>Upgrade Type<b>') );
$table->addRow( array($upgradeType) );
$upgradeTypeHelp = <<<EOT
    This table shows the type of upgrade performed on the server.
EOT;
drawHeaderWithHelp("Upgrade Type", 2, "upgradeTypeHelp", $upgradeTypeHelp);
echo $table->toHTML();
echo "<br>";
static $rowspancount = 0;
$upgradeTimingDetails = array();
$upgradeMissingLogDetails = array();
if ($eniqShipmentType == "Events") {
    $rowUpgradeTypeInfo = $statsDB->queryRow("
        SELECT
         upgradeType
        FROM
         eniq_upgrade_type_detail, sites
        WHERE
         eniq_upgrade_type_detail.siteId = sites.id AND
         sites.name = '$site' AND
         eniq_upgrade_type_detail.date = '$maxDate'
        ");
    $rowUpgradeType = $rowUpgradeTypeInfo[0];
    if(strcmp($rowUpgradeType, "FDM Full Upgrade") == 0) {
        $upgradeStageArray = array(
                             "Solaris Live Upgrade"        => array ('Live Upgrade'),
                             "Create Snapshot"             => array ('Create Snapshot Coordinator'),
                             "SAP ASA And IQ Upgrade"      => array ('SAP ASA Upgrade','SAP IQ Upgrade'),
                             "ENIQ Platform Upgrade"       => array ('Feature Provisioning Steps','ENIQ Platform Upgrade'),
                             "ENIQ Event Features Upgrade" => array ('Features Upgrade',
                                                                     'Opengeo Upgrade')
                             );
    }
    elseif(strcmp($rowUpgradeType, "Feature only") == 0) {
        $upgradeStageArray = array(
                             "Solaris Live Upgrade"        => array ('Live Upgrade'),
                             "Create Snapshot"             => array ('Create Snapshot Coordinator'),
                             "SAP ASA And IQ Upgrade"      => array ('SAP ASA Upgrade','SAP IQ Upgrade'),
                             "ENIQ Platform Upgrade"       => array ('ENIQ Platform Upgrade'),
                             "ENIQ Event Features Upgrade" => array ('Feature Provisioning Steps (applicable for feature only upgrade)',
                                                                     'Features Upgrade',
                                                                     'Opengeo Upgrade')
                            );
    }
    else {
        $upgradeStageArray = array(
                             "Solaris Live Upgrade"        => array ('Live Upgrade'),
                             "Create Snapshot"             => array ('Create Snapshot Coordinator'),
                             "SAP ASA And IQ Upgrade"      => array ('SAP ASA Upgrade','SAP IQ Upgrade'),
                             "ENIQ Event Features Upgrade" => array ('ENIQ Platform Upgrade',
                                                                     'Feature Provisioning Steps',
                                                                     'Opengeo Upgrade',
                                                                     'Features Upgrade')
                             );
    }
}
else {
    $upgradeStageArray = array(
                         "RHEL Live Upgrade"           => array ('Live Upgrade'),
                         "Create Snapshot"             => array ('Create Snapshot Coordinator'),
                         "SAP ASA And IQ Upgrade"      => array ('SAP ASA Upgrade','SAP IQ Upgrade'),
                         "ENIQ Stats Features Upgrade" => array ('ENIQ Platform Upgrade','Features Upgrade')
                         );
}
$table = new HTML_Table("border=1");
$table->addRow( array('<b>Upgrade Stages<b>', '<b>Upgrade Sub-Stages<b>', '<b>Upgrade Start Time<b>', '<b>Upgrade End Time<b>', '<b>Execution Time<b>', '<b>Remarks<b>') );

$requiredColumn = "upgradeStage, upgradeStartTime, upgradeEndTime, upgradeExecutionTime";
$requiredTable = "eniq_upgrade_timing_detail";
getUpgradeData($requiredColumn, $requiredTable, $upgrade_type, true, $upgradeTimingDetails);
$requiredColumn = "upgradeStage, upgradeFailureMessage";
$requiredTable = "eniq_missing_upgrade_detail";
getUpgradeData($requiredColumn, $requiredTable, $upgrade_type, true, $upgradeMissingLogDetails);

foreach ($upgradeStageArray as $upgradeStageSection => $upgradeStageSubSection) {
    $startTime = "";
    $endTime = "";
    $executionTime = "";
    $remark = "";
    $rowspancount++;
    $size = sizeof($upgradeStageArray[$upgradeStageSection], 1);
    foreach ($upgradeStageSubSection as $subSection) {
        $subStage = $subSection;
        if ($subSection == "Feature Provisioning Steps") {
            $subSection = "Mediation Gateway Workflow Auto-Provisioning";
        }
        if ($subSection == "Create Snapshot Coordinator") {
            $subStage = "Create Snapshot";
        }
        if ( array_key_exists("$subSection", $upgradeTimingDetails) ) {
            $startTime = $upgradeTimingDetails[$subSection]['upgradeStartTime'];
            $endTime = $upgradeTimingDetails[$subSection]['upgradeEndTime'];
            $executionTime = $upgradeTimingDetails[$subSection]['upgradeExecutionTime'];
            $remark = $subStage . " was successful";
            if ($executionTime == "00:00:00") {
                $executionTime = "--:--:--";
                $remark = "Upgrade End Time is invalid";
            }
        }
        if ( array_key_exists("$subSection", $upgradeMissingLogDetails) ) {
            $remark = $upgradeMissingLogDetails[$subSection]['upgradeFailureMessage'];
        }
        $table->addRow( array($upgradeStageSection, $subStage, $startTime, $endTime, $executionTime, $remark) );
        $startTime = "";
        $endTime = "";
        $executionTime = "";
        $remark = "";
    }
    $table->setCellAttributes($rowspancount, 0, "rowspan='$size'");
    $rowspancount+= $size-1;
}
$upgradeFeaturesDetailsHelp = <<<EOT
    This table shows the following information for upgrade stages of ENIQ Events/Stats Upgrade :
<p>
<ol>
    <li><b>Upgrade Start Time</b>:Execution start time of the upgrade sub-stage.</li>
    <li><b>Upgrade End Time</b>:Execution end time of the upgrade sub-stage.</li>
    <li><b>Execution Time</b>:Total execution time of the upgrade sub-stage.</li>
    <li><b>Remarks</b>:It shows the cause for a particular upgrade sub-stage execution time not being calculated.</li>
</ol>
<p>
    <b>Note</b>:In case of inappropriate Upgrade End Time, Execution Time is shown as --:--:--.
EOT;
drawHeaderWithHelp("Upgrade Timing Details", 2, "upgradeFeaturesDetailsHelp", $upgradeFeaturesDetailsHelp);
echo $table->toHTML();
include "../common/finalise.php";
?>