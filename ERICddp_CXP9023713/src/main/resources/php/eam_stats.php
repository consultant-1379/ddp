<?php
$pageTitle = "EAM Statistics";
include "common/init.php";
require_once 'HTML/Table.php';
$statsDB = new StatsDB();
$rootdir = $rootdir;
require_once "classes/EAMStats.php";
$eamInitTbl = new EAMCommandInitiator();
$eamRespTbl = new EAMCommandResponder();
$eamTimeoutTbl = new EAMNETimeout();

?>

<h1>EAM Statistics</h1>

<a name="eamAXENE"></a>
<?php
$rowAXENE = $statsDB->queryRow("SELECT COUNT(*) FROM eai_esi_map_detail de, sites s, eam_ne_names ne WHERE de.siteid = s.id AND date = '$date' AND s.name = '$site' AND de.eam_neid = ne.id");
if ($rowAXENE[0] != 0) {
    echo "<h2>";
    drawHelpLink("eamAXENEHelp");
    echo "AXE NEs</h2>";
}
drawHelp("eamAXENEHelp", "EAM AXE NEs", "The link below is to the AXE NE data. The counts of the NEs by Initiator Type will be presented, with drill through to the actual NE names.");

$row = $statsDB->queryRow("SELECT COUNT(*) FROM eai_esi_map_detail de, sites s, eam_ne_names ne WHERE de.siteid = s.id AND date = '$date' AND s.name = '$site' AND de.eam_neid = ne.id");
if ( $row[0] > 0 ) {
  echo "<a href=\"eam_total_axe_nes.php?$webargs\">Total AXE NEs in Network per Initiator Type</a>\n";
}
?>

<a name="eamConnNE"></a>
<?php
$rowConnNE = $statsDB->queryRow("SELECT COUNT(*) FROM eam_connected_ne_detail de, sites s WHERE de.siteid = s.id AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND s.name = '$site'");
if ($rowConnNE[0] != 0) {
    echo "<h2>";
    drawHelpLink("eamConnNEHelp");
    echo "Connected NEs</h2>";
    drawHelp("eamConnNEHelp", "EAM Connected NEs", "The link below is to the Connected NE data. The counts of the NEs by Initiators per half hour time periods will be presented, with drill through to the NE names, and from there, further drill through to sessions, applications and commands.");
    echo "<a href=\"eam_conn_ne_counts.php?$webargs\">Connected NEs per Initiator Type</a>\n";
}
?>

<a name="eamAlarmProcTime"></a>
<?php
$rowAlarmProcTime = $statsDB->queryRow("SELECT COUNT(*) FROM  eam_alarm_details d, sites s, eam_datasets da, eam_node_fdn_names f WHERE d.siteid = s.id AND date = '$date' AND s.name = '$site' AND d.node_fdnid = f.id AND da.name IN ('EHIP', 'EHMS', 'EHM')");
if ($rowAlarmProcTime[0] != 0) {
    echo "<h2>";
    drawHelpLink("eamAlarmProcTimeHelp");
    echo "EAM Alarm Processing Time</h2>";
}
drawHelp("eamAlarmProcTimeHelp", "EAM Alarm Processing Time", "The links below are to the settings for the EHIP, EHMS and EHM alarm processing times. This data is retrieved from the ehip_alarm_times_instr.log, ehms_alarm_times_instr.log and ehm_alarm_times_instr.log files. The data is grouped as per the feed file.");

foreach (array("EHIP", "EHMS", "EHM") as $dataset) { 
    $row = $statsDB->queryRow("SELECT COUNT(*) FROM eam_alarm_details de, sites s, eam_datasets da WHERE de.siteid = s.id AND de.datasetid = da.id AND date = '$date' AND s.name = '$site' AND da.name = '$dataset'");
    if ( $row[0] > 0 ) {
        echo "<li><a href=\"eam_alarm_proc_time.php?$webargs#$dataset\">$dataset</a></li>\n";
    }
}
?>

<a name="eamSpCmdSettings"></a>
<?php
$rowSpCmd = $statsDB->queryRow("SELECT COUNT(*) FROM eam_sp_cmd_details de, sites s, eam_datasets da WHERE de.siteid = s.id AND de.datasetid = da.id AND date = '$date' AND s.name = '$site' AND da.name IN ('EHIP', 'EHT', 'EHM', 'EHAP')");
if ($rowSpCmd[0] != 0) {
    echo "<h2>";
    drawHelpLink("eamSpCmdSettingsHelp");
    echo "EAM Special Command Settings</h2>";
}
drawHelp("eamSpCmdSettingsHelp", "EAM Special Command Settings", "The links below are to the settings for the EHIP, EHT, EHM and EHAP special commands. This data is retrieved from the EHIP_command, EHT_command, EHM_command and EHAP_command files. The data is grouped as per the feed file.");

$row = $statsDB->queryRow("SELECT COUNT(*) FROM eam_sp_cmd_details de, sites s, eam_datasets da WHERE de.siteid = s.id AND de.datasetid = da.id AND date = '$date' AND s.name = '$site' AND da.name = 'EHIP'");
if ( $row[0] > 0 ) {
  echo "<li><a href=\"eam_sp_cmd_settings.php?$webargs&dataset=EHIP\">EHIP_Command</a></li>\n";
}

$row = $statsDB->queryRow("SELECT COUNT(*) FROM eam_sp_cmd_details de, sites s, eam_datasets da WHERE de.siteid = s.id AND de.datasetid = da.id AND date = '$date' AND s.name = '$site' AND da.name = 'EHT'");
if ( $row[0] > 0 ) {
  echo "<li><a href=\"eam_sp_cmd_settings.php?$webargs&dataset=EHT\">EHT_Command</a></li>\n";
}

$row = $statsDB->queryRow("SELECT COUNT(*) FROM eam_sp_cmd_details de, sites s, eam_datasets da WHERE de.siteid = s.id AND de.datasetid = da.id AND date = '$date' AND s.name = '$site' AND da.name = 'EHM'");
if ( $row[0] > 0 ) {
  echo "<li><a href=\"eam_sp_cmd_settings.php?$webargs&dataset=EHM\">EHM_Command</a></li>\n";
}

$row = $statsDB->queryRow("SELECT COUNT(*) FROM eam_sp_cmd_details de, sites s, eam_datasets da WHERE de.siteid = s.id AND de.datasetid = da.id AND date = '$date' AND s.name = '$site' AND da.name = 'EHAP'");
if ( $row[0] > 0 ) {
  echo "<li><a href=\"eam_sp_cmd_settings.php?$webargs&dataset=EHAP\">EHAP_Command</a></li>\n";
}
?>

<a name="eamSpRep"></a>
<?php
$query = "SELECT COUNT(*) FROM eam_spr_details de, sites s WHERE de.siteid = s.id AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND s.name = '$site' AND de.is_spontaneous = 1";
$row = $statsDB->queryRow($query);
if ($row[0] != 0) {
    echo "<h2>";
    drawHelpLink("eamSpRepHelp");
    echo "EAM Spontaneous Reports</h2>";

    drawHelp("eamSpRepHelp", "EAM Spontaneous Reports", "The link below is to the EAM Spontaneous Report counts. The data is presented in half hour intervals, with drill through to the details broken down by ME.");
    echo "<a href=\"eam_spr.php?$webargs\">Spontaneous Reports</a>\n";
}

?>

<a name="eamError"></a>
<h2><?php drawHelpLink("eamErrorHelp"); ?>EAM Error Statistics</h2>
<?php
drawHelp("eamErrorHelp", "EAM Error Statistics",
"This data is parsed from error log. The general Error Log statistics are present on main Error Log page.
This page further divide the EAM specific errors into different category. The categories are based on EAM Error Number ranges.
Based on the eam error number range, the origin of EAM errors is presented. For the convenience of user a link is also provided to refer to the main Error in
 the Error Log page");
?>

<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <td><b>EAM Part</b></td> <td><b>Origin</b></td>  <td><b>Count</b></td></tr>
<?php if ( file_exists($rootdir . "/eam_error_table.html") ) { 
		     $lines = file($rootdir . "/" . "eam_error_table.html");
			 foreach ($lines as $line_num => $line) {
				parse_str($line);
				$errorurl = "log.php?$webargs&log=error#$origin";
				echo "<tr> <td>$part</td> <td><a href=$errorurl>$origin</a></td>  <td>$count</td></tr>";
			 }
	  }
?>
  </tbody>
</table>
<a name="eamInit"></a>
<h2><?php drawHelpLink("eamInitHelp"); ?>EAM Initiator Statistics</h2>
<?php
drawHelp("eamInitHelp", "EAM Initiator Statistics",
"This data is collected from tapdb database. Below link present the data about the number of NEs connected to a particular EAM initiator for a particular half an hour period.
Each EAM initiator is then subdivided into number of sessions. And then for each session it displays the command and command count.");
echo "<a href=\"eam_initiator.php?$webargs\">EAM INIT</a>\n";
?>

<a name="cmdInit"></a>
<h2><?php drawHelpLink("cmdInitHelp"); ?>EAM Command Initiator</h2>

<?php

drawHelp("cmdInitHelp", "EAM Initiator Configuration",
"This data is collected from various EAM MC XML files. Below table displays all the EAM Command Initiator names and the number of configured initiators.
");
$eamInitTbl->getSortableHtmlTable();
?>

<a name="cmdResp"></a>
<h2><?php drawHelpLink("cmdRespHelp"); ?>EAM Command Responder</h2>

<?php

drawHelp("cmdRespHelp", "EAM Responder Configuration",
"This data is collected from various EAM MC XML files. Below table displays all the EAM Command Responder names and the number of configured responders.");
$eamRespTbl->getSortableHtmlTable();
?>

<a name="neTimeout"></a>
<h2><?php drawHelpLink("neTimeoutHelp"); ?>Network Element Timeout</h2>

<?php
drawHelp("neTimeoutHelp", "Network Element Timeout",
"This data is collected from the eac_esi_map parameter configuration file. This data is unlikely to be changed.
Each line within the map parameter configuration file contains the name of NE and its various configured timeout settings.
The timeout is shown as SECONDS.
");
$eamTimeoutTbl->getSortableHtmlTable();
?>

<?php
$statsDB->disconnect();
include "common/finalise.php";
?>

