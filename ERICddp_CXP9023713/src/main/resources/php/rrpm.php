<?php
$pageTitle = "RRPM";
include "common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$webargs = "site=$site&dir=$dir&date=$date&oss=$oss";

$statsDB = new StatsDB();

?>

<!-- 1. Overall Project Data -->

<?php
#=================================================================================
# Function for populating Overall Project data from rrpm_opd table
# This is for drill down capablilities
#================================================================================
class rrpmOverallProjectData extends DDPObject {
    private $drilldown = 0;

    function setDrillDown($value){
        $this->drilldown = $value;
    }

    var $cols = array(
        "projectName" => "Project Name",
        "projectStartTime" => "Project Start Time",
        "projectEndTime" => "Project End Time",
        "projectDuration" => "Project Duration",
        "numberNodes" => "Number Nodes",
        "numberNodesSuccessful" => "Number Nodes Successful",
        "numberNodesFailed" => "Number Nodes Failed",
        "numberNodesRemoved" => "Number Nodes Removed",
        "numberNeighbourRNC" => "Number Neighbour RNC",
        "numberCells" => "Number Cells",
        "numberRelation" => "Number Relation",
        "lockingPolicy" => "Locking Policy",
        "numberKPIROPs" => "Number KPI ROPs"
    );

    var $defaultOrderBy = "projectName";
    var $defaultOrderDir = "ASC";

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT projectName AS projectName,
                   projectStartTime AS projectStartTime,
                   projectEndTime AS projectEndTime,
                   projectDuration AS projectDuration,
                   numberNodes AS numberNodes,
                   numberNodesSuccessful AS numberNodesSuccessful,
                   numberNodesFailed AS numberNodesFailed,
                   numberNodesRemoved AS numberNodesRemoved,
                   numberNeighbourRNC AS numberNeighbourRNC,
                   numberCells AS numberCells,
                   numberRelation AS numberRelation,
                   lockingPolicy AS lockingPolicy,
                   numberKPIROPs AS numberKPIROPs
            FROM   rrpm_opd,sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);

	// If drilldown variable is set we can avail of the drill down updates. Else we are usinging legacy data.
        if ( $this->drilldown == 1) {
            // Add edit link to activityid column, but the query is based on the
            // the unique keys on the table of date, siteid, actityid
            $webargs0 = $webargs;
            foreach ($this->data as $key => $d) {
                $webargs = $webargs0 . "&projectName=" . $d['projectName'];
                $d['projectName'] = "<a href=\"$phpDir/rrpm_proj_data.php?$webargs\">" . $d['projectName'] . "</a>\n";
                $this->data[$key] = $d;
            }
            return $this->data;
        }
        else {
            return $this->data;
        }
    }

}

#=================================================================================
# Function for populating Project Phase data from rrpm_ppd table
#================================================================================
class rrpmProjectPhaseData extends DDPObject{
    var $cols = array(
        "PhaseName" => "Phase Name",
        "PhaseStartTime" => "Phase Start Time",
        "PhaseEndTime" => "Phase End Time",
        "PhaseDuration" => "Phase Duration",
        "DPEnabled" => "DP Enabled",
        "NumberRBSProcessed" => "Number RBS Processed",
        "NumberRBSFailed" => "Number RBS Failed"
    );

    var $defaultOrderBy = "PhaseName";
    var $defaultOrderDir = "ASC";


    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT PhaseName AS PhaseName,
                   PhaseStartTime AS PhaseStartTime,
                   PhaseEndTime AS PhaseEndTime,
                   PhaseDuration AS PhaseDuration,
                   DPEnabled AS DPEnabled,
                   NumberRBSProcessed AS NumberRBSProcessed,
                   NumberRBSFailed AS NumberRBSFailed
            FROM   rrpm_ppd,sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
    }
}

#=================================================================================
# Function for populating Project Pre Check Data from rrpm_ppcd table
#================================================================================
class rrpmProjectPreCheckData extends DDPObject{
    var $cols = array(
        "preCheckName" => "PreCheck Name",
        "preCheckStartTime" => "PreCheck Start Time",
        "preCheckEndTime" => "PreCheck End Time",
        "preCheckDuration" => "PreCheck Duration"
    );

    var $defaultOrderBy = "preCheckStartTime";
    var $defaultOrderDir = "ASC";


    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT preCheckName AS preCheckName,
                   preCheckStartTime AS preCheckStartTime,
                   preCheckEndTime AS preCheckEndTime,
                   preCheckDuration AS preCheckDuration
            FROM   rrpm_ppcd,sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
    }

}

#=================================================================================
# Function for populating Project RBS data from rrpm_prd table
#================================================================================
class rrpmProjectRBSData extends DDPObject{
    var $cols = array(
        "RBSName" => "RBS Name",
        "RBSType" => "RBS Type",
        "RBSStatus" => "RBS Status",
        "SourceRNC" => "Source RNC",
        "TargetRNC" => "TargetRNC",
        "NumberCells" => "Number Cell",
        "NumberGSMRelationsCreated" => "Number GSM Relations Created",
        "NumberCoverageRelationsCreated" => "Number Coverage Relations Created",
        "NumberIntraUtranRelationsCreated" => "Number Intra Utran Relations Created",
        "NumberInterUtranRelationsCreated" => "Number Inter Utran Relations Created",
        "NumberInterOSSUtranRelationsCreated" => "Number Inter OSS Utran Relations Created",
        "NumberEUtranFreqRelationsCreated" => "Number EUtran Freq Relations Created",
        "NumberIntraUtranRelationsUpdated" => "Number Intra Utran Relations Updated",
        "NumberInterUtranRelationsUpdated" => "Number Inter Utran Relations Updated",
        "NumberGSMRelationsDeleted" => "Number GSM Relations Deleted",
        "NumberCoverageRelationsDeleted" => "Number Coverage Relations Deleted",
        "NumberEUtranFreqRelationsDeleted" => "Number EUtran Freq Relations Deleted",
        "NumberIntraUtranRelationsDeleted" => "Number Intra Utran Relations Deleted",
        "NumberInterUtranRelationsDeleted" => "Number Inter Utran Relations Deleted",
        "NumberInterOSSUtranRelationsDeleted" => "Number OSS Utran Relations Deleted",
        "AddTargetRBSDuration" => "Add Target RBS Duration",
        "UnManageSourceRBSDuration" => "Unmanage Source RBS Duration",
        "ManageTargetRBSDuration" => "Manage Target RBS Duration",
        "RemoveSourceRBSDuration" => "Remove Source RBS Duration"
    );

    var $defaultOrderBy = "RBSName";
    var $defaultOrderDir = "ASC";


    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT RBSName AS RBSName,
                   RBSType AS RBSType,
                   RBSStatus AS RBSStatus,
                   SourceRNC AS SourceRNC,
                   TargetRNC AS TargetRNC,
                   NumberCells AS NumberCells,
                   NumberGSMRelationsCreated AS NumberGSMRelationsCreated,
                   NumberCoverageRelationsCreated AS NumberCoverageRelationsCreated,
                   NumberIntraUtranRelationsCreated AS NumberIntraUtranRelationsCreated,
                   NumberInterUtranRelationsCreated AS NumberInterUtranRelationsCreated,
                   NumberInterOSSUtranRelationsCreated AS NumberInterOSSUtranRelationsCreated,
                   NumberEUtranFreqRelationsCreated AS NumberEUtranFreqRelationsCreated,
                   NumberIntraUtranRelationsUpdated AS NumberIntraUtranRelationsUpdated,
                   NumberInterUtranRelationsUpdated AS NumberInterUtranRelationsUpdated,
                   NumberGSMRelationsDeleted AS NumberGSMRelationsDeleted,
                   NumberCoverageRelationsDeleted AS NumberCoverageRelationsDeleted,
                   NumberEUtranFreqRelationsDeleted AS NumberEUtranFreqRelationsDeleted,
                   NumberIntraUtranRelationsDeleted AS NumberIntraUtranRelationsDeleted,
                   NumberInterUtranRelationsDeleted AS NumberInterUtranRelationsDeleted,
                   NumberInterOSSUtranRelationsDeleted AS NumberInterOSSUtranRelationsDeleted,
                   AddTargetRBSDuration AS AddTargetRBSDuration,
                   UnManageSourceRBSDuration AS UnManageSourceRBSDuration,
                   ManageTargetRBSDuration AS ManageTargetRBSDuration,
                   RemoveSourceRBSDuration AS RemoveSourceRBSDuration
            FROM   rrpm_prd,sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
    }

}

#=================================================================================
# Function for populating Removed RBS data from rrpm_rrd table
#================================================================================
class rrpmRemovedRBSData extends DDPObject {
    var $cols = array(
        "RBSName" => "RBS Name",
        "PhaseRemoved" => "Phase Removed",
        "RemoveRBSStartTime" => "Remove RBS Start Time",
        "RemoveRBSEndTime" => "Remove RBS End Tim",
        "RemoveRBSDuration" => "Remove RBS Duration"
    );

    #var $defaultOrderBy = "";
    #var $defaultOrderDir = "ASC";


    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT RBSName AS RBSName,
                   PhaseRemoved AS PhaseRemoved,
                   RemoveRBSStartTime AS RemoveRBSStartTime,
                   RemoveRBSEndTime AS RemoveRBSEndTime,
                   RemoveRBSDuration AS RemoveRBSDuration
            FROM   rrpm_rrd,sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
    }

}

#=================================================================================
# Function for populating KPI  data from rrpm_kd table
#================================================================================
class rrpmKPIData extends DDPObject {
    var $cols = array(
        "kpiName" => "KPI Name"
    );

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT kpiName AS kpiName
            FROM   rrpm_kd,sites
            WHERE  date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
        $this->populateData($sql);
        return $this->data;
    }

}
?>

<H2>Overall Project Data</H2>

<?php
$statsDB->query("SELECT rrpm_opd.nameid  FROM rrpm_opd, sites WHERE rrpm_opd.siteid = sites.id AND  sites.name = '$site' AND rrpm_opd.date = '$date'");
$row = $statsDB->getNextRow();

if ( $row[0] != 0 && $statsDB->getNumRows() != 0 ) {
    #Overall Project Data Drill Down
    $sidxOverall = new rrpmOverallProjectData();
    $sidxOverall->setDrillDown(1);
    $sidxOverall->getSortableHtmlTable();
}
else {
    #Overall Project Data
    $sidxOverall = new rrpmOverallProjectData();
    $sidxOverall->getSortableHtmlTable();

    echo "<p><br/><h2>Project Phase Data</h2>\n";
    #Project Phase Data
    $sidxPhase = new rrpmProjectPhaseData();
    $sidxPhase->getSortableHtmlTable();

    #Project Pre Check Data
    echo "<p><br/><h2>Project Pre Check Data</h2>\n";
    $sidxPreCheck = new rrpmProjectPreCheckData();
    $sidxPreCheck->getSortableHtmlTable();

    #Project RBS Data
    echo "<p><br/><h2>Project RBS Data</h2>\n";
    $sidxProjectRBS = new rrpmProjectRBSData();
    $sidxProjectRBS->getSortableHtmlTable();

    #Removed RBS Data
    echo "<p><br/><h2>Removed RBS Data</h2>\n";
    $sidxRemovedRBS = new rrpmRemovedRBSData();
    $sidxRemovedRBS->getSortableHtmlTable();

    #KPI Data
    echo "<p><br/><h2>KPI Data</h2>\n";
    $sidxKPI = new rrpmKPIData();
    $sidxKPI->getSortableHtmlTable();
}

include "common/finalise.php";
?>

