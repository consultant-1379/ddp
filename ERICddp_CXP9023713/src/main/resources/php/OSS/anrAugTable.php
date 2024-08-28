<?php
$pageTitle = "ANR AUG";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

#------Make tables with aggregated counts-----------#
class BlacklistTotals extends DDPObject {
  var $cols = array(
            'moi'   =>  'Moi',
            'time' => 'Time',
            'remAllowedTrueCount' => 'isRemoveAllowed (False->True)',
            'remAllowedFalseCount'=> 'isRemoveAllowed (True->False)',
            'hoAllowedTrueCount'  => 'isHoAllowed (False->True)',
            'hoAllowedFalseCount' => 'isHoAllowed (True->False)'
            );

    var $title = "Blacklisted EUtranCellRelations";

    function __construct() {
        parent::__construct("Blacklisted_EUtranCellRelations");
    $this->defaultOrderBy = "moi";
    $this->defaultOrderDir = "DESC";
    }

    function getData() {

    global $fromDate;
    global $toDate;
    global $site;
    global $debug;

    if ( $fromDate == $toDate ) {
    $sql = "
SELECT
 son_anr_augmentation.moi AS moi,
 son_anr_augmentation.time AS time,
 son_anr_augmentation.remAllowedTrueCount AS remAllowedTrueCount,
 son_anr_augmentation.remAllowedFalseCount AS remAllowedFalseCount,
 son_anr_augmentation.hoAllowedTrueCount AS hoAllowedTrueCount,
 son_anr_augmentation.hoAllowedFalseCount AS hoAllowedFalseCount
FROM
 son_anr_augmentation, sites
WHERE
  son_anr_augmentation.moi <> 'N/A' AND son_anr_augmentation.siteid = sites.id AND sites.name = '$site'
  AND son_anr_augmentation.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
    } else {
      $sql = "
SELECT
 son_anr_augmentation.moi AS moi,
 son_anr_augmentation.time AS time,
 son_anr_augmentation.remAllowedTrueCount AS remAllowedTrueCount,
 son_anr_augmentation.remAllowedFalseCount AS remAllowedFalseCount,
 son_anr_augmentation.hoAllowedTrueCount AS hoAllowedTrueCount,
 son_anr_augmentation.hoAllowedFalseCount AS hoAllowedFalseCount
FROM
 son_anr_augmentation, sites
WHERE
  son_anr_augmentation.moi <> 'N/A' AND son_anr_augmentation.siteid = sites.id AND sites.name = '$site'
  AND son_anr_augmentation.time BETWEEN '$fromDate 00:00:00' AND '$fromDate 23:59:59'";
    }
    $this->populateData($sql);
    return $this->data;
    }
}
#-------------------------------------------------------#
if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}

echo "<a href=\"son.php?$webargs\">Return to main page</a>";
echo "<p></p>";

$anrAugTable = new BlacklistTotals();
echo "<div style = \"font-size:80%\" >";
echo $anrAugTable->getClientSortableTableStr();
echo "</div>";

include "../common/finalise.php";

?>