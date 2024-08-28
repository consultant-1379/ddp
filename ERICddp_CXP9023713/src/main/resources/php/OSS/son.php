<?php
$pageTitle = "SON";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

#------Make tables with aggregated counts-----------#
class SonTotals extends DDPObject {
  var $cols = array(
            'moc'   =>  'MO Class',
            'created_anr' => 'CREATEDBY_ANR',
            'deleted_anr' => 'ANR_DELETE',
            'modified_anr'=> 'ANR_MODIFICATION',
            'created_x2'  => 'CREATEDBY_X2',
            'deleted_x2'  => 'X2_DELETE',
            'modified_x2' => 'X2_MODIFICATION ',
            );

    var $title = "SON Updates";

    function __construct() {
        parent::__construct("son");
    $this->defaultOrderBy = "created_anr";
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
 mo_names.name AS moc,
 son_mo.created_anr AS created_anr,
 son_mo.deleted_anr AS deleted_anr,
 son_mo.modified_anr AS modified_anr,
 son_mo.created_x2 AS created_x2,
 son_mo.deleted_x2 AS deleted_x2,
 son_mo.modified_x2 AS modified_x2
FROM
 mo_names, son_mo, sites
WHERE
 son_mo.siteid = sites.id AND sites.name = '$site' AND
 son_mo.date = '$fromDate' AND
 son_mo.moid = mo_names.id";
    } else {
      $sql = "
SELECT
 mo_names.name AS moc,
 SUM(son_mo.created_anr) AS created_anr,
 SUM(son_mo.deleted_anr) AS deleted_anr,
 SUM(son_mo.modified_anr) AS modified_anr,
 SUM(son_mo.created_x2) AS created_x2,
 SUM(son_mo.deleted_x2) AS deleted_x2,
 SUM(son_mo.modified_x2) AS modified_x2
FROM
 mo_names, son_mo, sites
WHERE
 son_mo.siteid = sites.id AND sites.name = '$site' AND
 son_mo.date BETWEEN '$fromDate' AND '$toDate' AND
 son_mo.moid = mo_names.id
GROUP BY son_mo.moid
";
    }
    $this->populateData($sql);
    return $this->data;
    }
}

class SONAdditionsTotals extends DDPObject{
   var $cols = array(
            'moc'   =>  'MO Class',
            'created_operator' => 'CREATEDBY_OPERATOR',
            'deleted_operator' => 'OPERATOR_DELETE',
            'modified_operator' => 'OPERATOR_MODIFICATION',
            'modified_not' => 'NOT_MODIFICATION',
            'modified_mro' => 'MRO _MODIFICATION',
            'modified_pci' => 'PCI_MODIFICATION',
            'modified_mlb' => 'MLB_MODIFICATION',
            'modified_rach_opt' => 'RACH_OPT_MODIFICATION',
            );

    var $title = "SONAdditionsTotals";

    function __construct() {
        parent::__construct("SONAdditionsTotals");
    $this->defaultOrderBy = "created_operator";
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
 mo_names.name AS moc,
 son_mo_additions.created_operator AS created_operator,
 son_mo_additions.deleted_operator AS deleted_operator,
 son_mo_additions.modified_operator AS modified_operator,
 son_mo_additions.modified_not AS modified_not,
 son_mo_additions.modified_mro AS modified_mro,
 son_mo_additions.modified_pci AS modified_pci,
 son_mo_additions.modified_mlb AS modified_mlb,
 son_mo_additions.modified_rach_opt AS modified_rach_opt
FROM
 mo_names, son_mo_additions, sites
WHERE
 son_mo_additions.siteid = sites.id AND sites.name = '$site' AND
 son_mo_additions.date = '$fromDate' AND
 son_mo_additions.moid = mo_names.id";
    } else {
      $sql = "
SELECT
 mo_names.name AS moc,
 SUM(son_mo_additions.created_operator) AS created_operator,
 SUM(son_mo_additions.deleted_operator) AS deleted_operator,
 SUM(son_mo_additions.modified_operator) AS modified_operator,
 SUM(son_mo_additions.modified_not) AS modified_not,
 SUM(son_mo_additions.modified_mro) AS modified_mro,
 SUM(son_mo_additions.modified_pci) AS modified_pci,
 SUM(son_mo_additions.modified_rach_opt) AS modified_rach_opt
FROM
 mo_names, son_mo_additions, sites
WHERE
 son_mo_additions.siteid = sites.id AND sites.name = '$site' AND
 son_mo_additions.date BETWEEN '$fromDate' AND '$toDate' AND
 son_mo_additions.moid = mo_names.id
GROUP BY son_mo_additions.moid
";
    }
    $this->populateData($sql);
    return $this->data;
    }
}

class CIOTotals extends DDPObject{
   var $cols = array(
            'cellIndividualOffsetEUtran' => 'cellIndividualOffsetEUtran',
            'modified_operator' => 'OPERATOR_MODIFICATION',
            'modified_mro' => 'MRO_MODIFICATION',
            'modified_other'=> 'OTHER_MODIFICATION',
            );

    var $title = "CIO Changes";

    function __construct() {
        parent::__construct("CIOTotals");
    $this->defaultOrderBy = "cellIndividualOffsetEUtran";
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
 son_cio_changes.modified_operator AS modified_operator,
 son_cio_changes.modified_mro AS modified_mro,
 son_cio_changes.modified_other AS modified_other,
 son_cio_changes.cellIndividualOffsetEUtran AS cellIndividualOffsetEUtran
FROM
 son_cio_changes, sites
WHERE
 son_cio_changes.siteid = sites.id AND sites.name = '$site' AND
 son_cio_changes.date = '$fromDate'";
    } else {
      $sql = "
SELECT
 SUM(son_cio_changes.modified_operator) AS modified_operator,
 SUM(son_cio_changes.modified_mro) AS modified_mro,
 SUM(son_cio_changes.modified_other) AS modified_other
FROM
son_cio_changes, sites
WHERE
 son_cio_changes.siteid = sites.id AND sites.name = '$site' AND
 son_cio_changes.date BETWEEN '$fromDate' AND '$toDate'
GROUP BY son_cio_changes.cellIndividualOffsetEUtran
";
    }
    $this->populateData($sql);
    return $this->data;
    }
}

class qOffsetTotals extends DDPObject{
   var $cols = array(
            'qOffsetCellEUtran' => 'qOffsetCellEUtran',
            'modified_operator' => 'OPERATOR_MODIFICATION',
            'modified_mro' => 'MRO_MODIFICATION',
            'modified_other'=> 'OTHER_MODIFICATION',
            );

    var $title = "qOffsetCellEUtran Changes";

    function __construct() {
        parent::__construct("qOffsetTotals");
    $this->defaultOrderBy = "qOffsetCellEUtran";
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
 son_qOffset_changes.modified_operator AS modified_operator,
 son_qOffset_changes.modified_mro AS modified_mro,
 son_qOffset_changes.modified_other AS modified_other,
 son_qOffset_changes.qOffsetCellEUtran AS qOffsetCellEUtran
FROM
 son_qOffset_changes, sites
WHERE
 son_qOffset_changes.siteid = sites.id AND sites.name = '$site' AND
 son_qOffset_changes.date = '$fromDate'";
    } else {
      $sql = "
SELECT
 SUM(son_qOffset_changes.modified_operator) AS modified_operator,
 SUM(son_qOffset_changes.modified_mro) AS modified_mro,
 SUM(son_qOffset_changes.modified_other) AS modified_other
FROM
son_qOffset_changes, sites
WHERE
 son_qOffset_changes.siteid = sites.id AND sites.name = '$site' AND
 son_qOffset_changes.date BETWEEN '$fromDate' AND '$toDate'
GROUP BY son_qOffset_changes.qOffsetCellEUtran
";
    }
    $this->populateData($sql);
    return $this->data;
    }
}

class TiltTotals extends DDPObject {
  var $cols = array(
            'moi'   =>  'RetSubunit',
            'time' => 'Time',
            'old_value' => 'Old Value',
            'new_value'=> 'New Value',
            'tilt_difference'  => 'Tilt Difference',
            );

    var $title = "Electrical Tilt Totals";

    function __construct() {
        parent::__construct("ElectricalTiltTotals");
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
 son_electrical_tilt_rate.moi AS moi,
 son_electrical_tilt_rate.time AS time,
 son_electrical_tilt_rate.old_value AS old_value,
 son_electrical_tilt_rate.new_value AS new_value,
 son_electrical_tilt_rate.tilt_difference AS tilt_difference
FROM
 son_electrical_tilt_rate, sites
WHERE
  son_electrical_tilt_rate.moi <> 'N/A' AND son_electrical_tilt_rate.siteid = sites.id AND sites.name = '$site'
  AND son_electrical_tilt_rate.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
    } else {
      $sql = "
SELECT
 son_electrical_tilt_rate.moi AS moi,
 son_electrical_tilt_rate.time AS time,
 son_electrical_tilt_rate.old_value AS old_value,
 son_electrical_tilt_rate.new_value AS new_value,
 son_electrical_tilt_rate.tilt_difference AS tilt_difference
FROM
 son_electrical_tilt_rate, sites
WHERE
  son_electrical_tilt_rate.moi <> 'N/A' AND son_electrical_tilt_rate.siteid = sites.id AND sites.name = '$site'
  AND son_electrical_tilt_rate.time BETWEEN '$fromDate 00:00:00' AND '$fromDate 23:59:59'";
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

#--------Get Managed Object Classes from Database------#
$statsDB = new StatsDB();

function getDistinctMocs($statsDB, $site) {

$sql = "SELECT DISTINCT moType FROM son_moc_rate, sites WHERE son_moc_rate.siteid = sites.id AND sites.name = '$site'";
    $statsDB->query($sql);
    $values = array();
    while($row = $statsDB->getNextRow()) {
        $values[] = $row[0];
    }
    return $values;
}

$mocValues = array ();
$mocValues = getDistinctMocs($statsDB, $site);
$statsDB->disconnect();

#---------------------------------------#

#------------SON INDEX -----------------#

echo "<a name = \"backToTop\"></a>";

drawHeaderWithHelp("SON Index", 2, "SON Index", "DDP_Bubble_136_OSS_SON_Index_Graphs");

echo "<ul>";
echo "<li><a href =\"#cioBreakpoint\">cellIndividualOffsetEUtran Changes</a></li>";
echo "<li><a href =\"#qOffsetBreakpoint\">qOffsetCellEUtran Changes</a></li>";

for ($i=0; $i<count($mocValues); $i++){
echo "<li><a href = \"#$mocValues[$i]\">$mocValues[$i]</a></li>";
}

echo "<li><a href =\"#tableBreakpoint\">Tables for SON totals</a></li>";

echo "<li><a href =\"#tiltBreakpoint\">Electrical Antenna Tilts</a></li>";
echo "<li><a href =\"#anrBreakpoint\">Blacklisted EUtranCellRelations</a></li>";
echo "<li><a href=\"anrAugTable.php?$webargs\">Blacklisted EUtranCellRelations Table</a></li>";
echo "</ul>";
#-----------------------------------------#

#--------Used to make graphs for SON (Total) Updates and CIO/qOffset----------#
function makeGraph($title, $ylabel, $whatCol, $whatTables, $mainTable, $fromDate, $toDate){
$sqlParam =
     array(
         'title'      => $title,
         'ylabel'     => $ylabel,
         'type'       => 'sb',
         'sb.barwidth'=> 500,
         'useragg'    => 'true',
         'persistent' => 'false',
         'querylist' =>
                     array(
                         array (
                         'timecol' => 'time',
                         'whatcol' => $whatCol,
                         'tables'  => $whatTables,
                         'where'   => "$mainTable.siteid = sites.id AND sites.name = '%s'",
                         'qargs'   => array( 'site' )
                         )
                     )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURLSON( $id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 1600, 800);
}

#------------Tilt Graph Function------------------#

function makeTiltANRGraph($title, $ylabel, $whatCol, $whatTables, $mainTable, $fromDate, $toDate, $extraArgument){
$sqlParam =
     array(
         'title'=> $title,
         'ylabel'=> $ylabel,
         'type'=> 'sb',
         'sb.barwidth'=> 500,
         'useragg'=> 'true',
         'persistent'=> 'false',
         'querylist'=>
                     array(
                         array(
                         'timecol' => 'time',
                         'whatcol' => $whatCol,
                         'tables'  => $whatTables,
                         'where'   => "$mainTable.siteid = sites.id AND sites.name = '%s' $extraArgument",
                         'qargs'   => array( 'site' )
                         )
                     )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURLSON( $id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 1600, 800);
}

#--------------------------------------------------#

#--------SON (Total) Updates-----------#
drawHeaderWithHelp("SON MOC Totals", 2, "SON MOC Totals", "DDP_Bubble_135_OSS_SON_MOC_Totals");

makeGraph("SON (MOC) Updates", 'Count', array ( 'created_anr' => 'CREATEDBY_ANR', 'created_x2' => 'CREATEDBY_X2',
                         'deleted_anr' => 'ANR_DELETE', 'deleted_x2' => 'X2_DELETE',
                         'modified_anr' => 'ANR_MODIFICATION', 'modified_x2' => 'X2_MODIFICATION'), "son_rate, sites", "son_rate", $fromDate, $toDate);

makeGraph ("SON (MOC) Updates", 'Count', array (
                'created_operator' => 'CREATEDBY_OPERATOR',
                'deleted_operator' => 'OPERATOR_DELETE',
                'modified_operator' => 'OPERATOR_MODIFICATION',
                'modified_not' => 'NOT_MODIFICATION',
                'modified_mro' => 'MRO_MODIFICATION',
                'modified_pci' => 'PCI_MODIFICATION',
                'modified_mlb' => 'MLB_MODIFICATION',
                'modified_rach_opt' => 'RACH_OPT_MODIFICATION'), "son_rate_additions, sites", "son_rate_additions", $fromDate, $toDate);
#-------------------------------------#

#-----------------SON MOC Graphs-------------#

function mocGraph($whatCol, $fromDate, $toDate, $moc){
 $sqlParam =
  array( 'title'      => "SON Updates ('$moc')",
     'ylabel'     => 'Count',
     'type'       => 'sb',
     'sb.barwidth'=> 500,
     'useragg'    => 'true',
     'persistent' => 'false',
     'querylist' =>
      array(
       array (
            'timecol' => 'time',
            'whatcol' => $whatCol,
            'tables'  => "son_moc_rate, sites",
            'where'   => "son_moc_rate.siteid = sites.id AND sites.name = '%s' AND son_moc_rate.moType = '$moc'",
            'qargs'   => array( 'site' )
            )
         )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURLSON( $id, "$fromDate 00:00:00", "$toDate 23:59:59", true, 1600, 800);
}

function returnParagraphBreak(){
    echo "<p></p>";
}


drawHeaderWithHelp("SON (MOC) Graphs", 2, "SON (MOC Graphs)", "DDP_Bubble_135_OSS_SON_MOC_Totals");

for ($i=0; $i<count($mocValues); $i++){

     echo "<a name=\"$mocValues[$i]\"></a>";

     mocGraph(array ( 'created_anr' => 'CREATEDBY_ANR',
                 'created_x2' => 'CREATEDBY_X2',
                 'deleted_anr' => 'ANR_DELETE',
                 'deleted_x2' => 'X2_DELETE',
                 'modified_anr' => 'ANR_MODIFICATION',
                 'modified_x2' => 'X2_MODIFICATION'), $fromDate, $toDate, $mocValues[$i]);

     returnParagraphBreak();

     mocGraph(array ( 'created_operator' => 'CREATEDBY_OPERATOR',
                'deleted_operator' => 'OPERATOR_DELETE',
                'modified_operator' => 'OPERATOR_MODIFICATION',
                'modified_not' => 'NOT_MODIFICATION',
                'modified_mro' => 'MRO_MODIFICATION',
                'modified_pci' => 'PCI_MODIFICATION',
                'modified_mlb' => 'MLB_MODIFICATION',
                'modified_rach_opt' => 'RACH_OPT_MODIFICATION'), $fromDate, $toDate, $mocValues[$i]);

     returnParagraphBreak();
}
#---------------------------------------------#



#----------CIO/qOffset Changes------#
drawHeaderWithHelp("cellIndividualOffsetEUtran/qOffsetCellEUtran (EUtranCellRelation) Changes", 2, "cellIndividualOffsetEUtran/qOffsetCellEUtran (EUtranCellRelation) Changes", "DDP_Bubble_137_OSS_SON_EUtranCellRelation_Changes");


echo "<a name =\"cioBreakpoint\"></a>";
makeGraph("CellIndividualOffsetEUtran (EUtranCellRelation) Changes", 'Count', array ('cio_modified_operator' => 'OPERATOR_MODIFICATION',
                     'cio_modified_mro' => 'MRO_MODIFICATION',
                     'cio_modified_other' => 'OTHER_MODIFICATION',
                    ), "son_cio_qOffset_rate, sites", "son_cio_qOffset_rate", $fromDate, $toDate);

returnParagraphBreak();

echo "<a name =\"qOffsetBreakpoint\"></a>";
makeGraph("qOffsetCellEUtran (EUtranCellRelation) Changes", 'Count', array ('qOffset_modified_operator' => 'OPERATOR_MODIFICATION',
                     'qOffset_modified_mro' => 'MRO_MODIFICATION',
                     'qOffset_modified_other' => 'OTHER_MODIFICATION',
                    ), "son_cio_qOffset_rate, sites", "son_cio_qOffset_rate", $fromDate, $toDate);

returnParagraphBreak();
#------------------------------------------#

#-------------Tilt Graph--------------------#
echo "<a name = \"tiltBreakpoint\"></a>";
drawHeaderWithHelp("Electrical Antenna Tilts", 2, "Electrical Antenna Tilts", "DDP_Bubble_187_OSS_SON_Electrical_Antenna_Tilts");
makeTiltANRGraph('Electrical Antenna Tilts', 'No. of Tilts', array ( 'SUM(down_tilt)' => 'Down Tilt', 'SUM(up_tilt)' => 'Up Tilt',
                         'SUM(neutral_tilt)' => 'Neutral Tilt'), 'son_electrical_tilt_rate, sites', 'son_electrical_tilt_rate', $fromDate, $toDate, 'GROUP BY time');
#-------------------------------------------#

returnParagraphBreak();

#-------------ANR AUG Graph------------------#
echo "<a name = \"anrBreakpoint\"></a>";
drawHeaderWithHelp("Blacklisted EUtranCellRelations", 2, "Blacklisted EUtranCellRelations", "DDP_Bubble_188_OSS_SON_ANR_AUG");
makeTiltANRGraph('Blacklisted EUtranCellRelations', 'No. of Blacklisted Adjancencies', array ( 'SUM(remAllowedTrueCount)' => 'isRemoveAllowed (False->True)', 'SUM(remAllowedFalseCount)' => 'isRemoveAllowed (True->False)',
                         'SUM(hoAllowedTrueCount)' => 'isHoAllowed (False->True)', 'SUM(hoAllowedFalseCount)' => 'isHoAllowed (True->False)'), 'son_anr_augmentation, sites', 'son_anr_augmentation', $fromDate, $toDate, 'GROUP BY time');

#--------------------------------------------#

##-------------Tables------------##
returnParagraphBreak();

drawHeaderWithHelp("Electrical Antenna Tilts Totals", 2, "Electrical Antenna Tilts", "DDP_Bubble_190_OSS_SON_Tilt_Totals");

$retTable = new TiltTotals();
echo $retTable->getClientSortableTableStr();

returnParagraphBreak();

drawHeaderWithHelp("createdBy and lastModification enums for MO Classes", 2, "createdBy and lastModification enums for MO Classes", "DDP_Bubble_138_OSS_SON_MO_Classes");

echo "<a name =\"tableBreakpoint\"></a>";

$totalsTable = new SonTotals();
echo $totalsTable->getClientSortableTableStr();

returnParagraphBreak();

$totalsTable4 = new SONAdditionsTotals();
echo $totalsTable4->getClientSortableTableStr();

returnParagraphBreak();

drawHeaderWithHelp("CIO/qOffset (EUtranCellRelation) Totals", 2, "CIO/qOffset (EUtranCellRelation) Totals", "DDP_Bubble_139_OSS_SON_qOffset_CIO_Changes");

$totalsTable2 = new CIOTotals();
echo $totalsTable2->getClientSortableTableStr();

returnParagraphBreak();

$totalsTable3 = new qOffsetTotals();
echo $totalsTable3->getClientSortableTableStr();

returnParagraphBreak();

echo "<p><li><a href =\"#backToTop\">Go back to start of page</a></li></p>";

include "../common/finalise.php";

?>