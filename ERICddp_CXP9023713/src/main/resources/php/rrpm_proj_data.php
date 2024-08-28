<?php
$pageTitle = "RRPM";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$phpDir = dirname($_SERVER['PHP_SELF']);
$projectName = $_GET['projectName'];
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();

echo "<a href=\"$phpDir/rrpm.php?$webargs\">Return to RRPM Instrumentation Overall Project Data</a>\n";
echo "<h1>Project Name: " . $projectName . "<br /></h1>\n";
?>

<!-- 2. Project Phase Data -->

<H2>Project Phase Data</H2>
<table border>
<tr>
    <th>Phase Name</th>
    <th>Phase Start Time</th>
    <th>Phase End Time</th>
    <th>Phase Duration</th>
    <th>DP Enabled</th>
    <th>Number RBS Processed</th>
    <th>Number RBS Failed</th>
<tr>

 <?php
 require_once 'HTML/Table.php';

 $statsDB = new StatsDB();

 $sql = "
 SELECT PhaseName, PhaseStartTime, PhaseEndTime, PhaseDuration, DPEnabled, NumberRBSProcessed, NumberRBSFailed
 FROM rrpm_ppd,rrpm_project_names,sites
 WHERE sites.name = '$site' AND sites.id = rrpm_ppd.siteid
 AND rrpm_ppd.date = '$date' AND rrpm_ppd.nameid = rrpm_project_names.id AND rrpm_project_names.name = '$projectName'
      ";

    if ( $debug ) { echo "<p>sql = $sql</p>"; }

    $statsDB->query($sql);
    while($row = $statsDB->getNextRow()) {
        echo "<tr>
            <td>$row[0]</td>
            <td>$row[1]</td>
            <td>$row[2]</td>
            <td>$row[3]</td>
            <td>$row[4]</td>
            <td>$row[5]</td>
            <td>$row[6]</td>
        </tr>\n";
    }

?>
</table>


<!-- 3. Project RBS Data -->

<H2>Project RBS Data</H2>
<table border>
<tr>
    <th>RBS Name</th>
    <th>RBS Type</th>
    <th>RBS Status</th>
    <th>Source RNC</th>
    <th>TargetRNC</th>
    <th>Number Cells</th>
    <th>Number GSM Relations Created</th>
    <th>Number Coverage Relations Created</th>
    <th>Number Intra Utran Relations Created</th>
    <th>Number Inter Utran Relations Created</th>
    <th>Number Inter OSS Utran Relations Created</th>
    <th>Number EUtran Freq Relations Created</th>
    <th>Number Intra Utran Relations Updated</th>
    <th>Number Inter Utran Relations Updated</th>
    <th>Number GSM Relations Deleted</th>
    <th>Number Coverage Relations Deleted</th>
    <th>Number EUtran Freq Relations Deleted</th>
    <th>Number Intra Utran Relations Deleted</th>
    <th>Number Inter Utran Relations Deleted</th>
    <th>Number OSS Utran Relations Deleted</th>
    <th>Add Target RBS Duration</th>
    <th>Unmanage Source RBS Duration</th>
    <th>Manage Target RBS Duration</th>
    <th>Remove Source RBS Duration</th>
<tr>

 <?php
 require_once 'HTML/Table.php';

 $statsDB = new StatsDB();

 $sql = "
 SELECT RBSName, RBSType, RBSStatus, SourceRNC, TargetRNC, NumberCells, NumberGSMRelationsDeleted, NumberCoverageRelationsCreated,
        NumberIntraUtranRelationsCreated, NumberInterUtranRelationsCreated, NumberInterOSSUtranRelationsCreated, NumberEUtranFreqRelationsCreated,
        NumberIntraUtranRelationsUpdated, NumberInterUtranRelationsUpdated, NumberGSMRelationsDeleted, NumberCoverageRelationsDeleted, NumberEUtranFreqRelationsDeleted,
        NumberIntraUtranRelationsDeleted, NumberInterUtranRelationsDeleted, NumberInterOSSUtranRelationsDeleted, AddTargetRBSDuration, UnManageSourceRBSDuration,
        ManageTargetRBSDuration, RemoveSourceRBSDuration
 FROM rrpm_prd,rrpm_project_names,sites
 WHERE sites.name = '$site' AND sites.id = rrpm_prd.siteid
 AND rrpm_prd.date = '$date' AND rrpm_prd.nameid = rrpm_project_names.id AND rrpm_project_names.name = '$projectName'
      ";

    if ( $debug ) { echo "<p>sql = $sql</p>"; }

    $statsDB->query($sql);
    while($row = $statsDB->getNextRow()) {
        echo "<tr>
            <td>$row[0]</td>
            <td>$row[1]</td>
            <td>$row[2]</td>
            <td>$row[3]</td>
            <td>$row[4]</td>
            <td>$row[5]</td>
            <td>$row[6]</td>
            <td>$row[7]</td>
            <td>$row[8]</td>
            <td>$row[9]</td>
            <td>$row[10]</td>
            <td>$row[11]</td>
            <td>$row[12]</td>
            <td>$row[13]</td>
            <td>$row[14]</td>
            <td>$row[15]</td>
            <td>$row[16]</td>
            <td>$row[17]</td>
            <td>$row[18]</td>
            <td>$row[19]</td>
            <td>$row[20]</td>
            <td>$row[21]</td>
            <td>$row[22]</td>
            <td>$row[23]</td>

        </tr>\n";
    }

?>
</table>


<!-- 4. Removed RBS Data -->

<H2>Removed RBS Data</H2>
<table border>
<tr>
    <th>RBS Name</th>
    <th>Phase Removed</th>
    <th>Remove RBS Start Time</th>
    <th>Remove RBS End Time</th>
    <th>Remove RBS Duration</th>
<tr>
 <?php
 require_once 'HTML/Table.php';

 $statsDB = new StatsDB();

 $sql = "
 SELECT RBSName, PhaseRemoved, RemoveRBSStartTime, RemoveRBSEndTime, RemoveRBSDuration
 FROM rrpm_rrd,rrpm_project_names,sites
 WHERE sites.name = '$site' AND sites.id = rrpm_rrd.siteid
 AND rrpm_rrd.date = '$date' AND rrpm_rrd.nameid = rrpm_project_names.id AND rrpm_project_names.name = '$projectName'
      ";

    if ( $debug ) { echo "<p>sql = $sql</p>"; }

    $statsDB->query($sql);
    while($row = $statsDB->getNextRow()) {
        echo "<tr>
            <td>$row[0]</td>
            <td>$row[1]</td>
            <td>$row[2]</td>
            <td>$row[3]</td>
            <td>$row[4]</td>
        </tr>\n";
    }

?>
</table>


<!-- 5. Project PreCheck Data -->

<H2>Project PreCheck Data</H2>
<table border>
<tr>
    <th>PreCheck Name</th>
    <th>PreCheck Start Time</th>
    <th>PreCheck End Time</th>
    <th>PreCheck Duration</th>
<tr>

 <?php
 require_once 'HTML/Table.php';

 $statsDB = new StatsDB();

 $sql = "
 SELECT preCheckName, preCheckStartTime, preCheckEndTime, preCheckDuration
 FROM rrpm_ppcd,rrpm_project_names,sites
 WHERE sites.name = '$site' AND sites.id = rrpm_ppcd.siteid
 AND rrpm_ppcd.date = '$date' AND rrpm_ppcd.nameid = rrpm_project_names.id AND rrpm_project_names.name = '$projectName'
      ";

    if ( $debug ) { echo "<p>sql = $sql</p>"; }

    $statsDB->query($sql);
    while($row = $statsDB->getNextRow()) {
        echo "<tr>
            <td>$row[0]</td>
            <td>$row[1]</td>
            <td>$row[2]</td>
            <td>$row[3]</td>
        </tr>\n";
    }

?>
</table>


<!-- 6. KPI Data -->

<H2>KPI Data</H2>
<table border>
<tr>
    <th>KPI Name</th>
<tr>

 <?php
 require_once 'HTML/Table.php';

 $statsDB = new StatsDB();

 $sql = "
 SELECT kpiName
 FROM rrpm_kd,rrpm_project_names,sites
 WHERE sites.name = '$site' AND sites.id = rrpm_kd.siteid
 AND rrpm_kd.date = '$date' AND rrpm_kd.nameid = rrpm_project_names.id AND rrpm_project_names.name = '$projectName'
      ";

    if ( $debug ) { echo "<p>sql = $sql</p>"; }

    $statsDB->query($sql);
    while($row = $statsDB->getNextRow()) {
        echo "<tr>
            <td>$row[0]</td>
        </tr>\n";
    }

?>
</table>

<?php
include "common/finalise.php";
?>

