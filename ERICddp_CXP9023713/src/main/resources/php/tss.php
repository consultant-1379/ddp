<?php
if (isset($_GET['chart'])) $UI = false;

$pageTitle = "TSS Stats";
include "common/init.php";
include "common/graphs.php";
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();

function getOperationsStatGraph($title,$ylabel,$start,$end,$whatCol,$graphType = 'tsc')
{
  global $site;
  $sqlParam =
    array( 'title'      => $title,
           'ylabel'     => $ylabel,
           'useragg'    => 'true',
           'persistent' => 'true',
           'type'       => $graphType,
           'querylist' =>
           array(
                 array (
                        'timecol' => 'time',
                        'whatcol' => $whatCol,
                        'tables'  => "tss_instr_stats, sites",
                        'where'   => "tss_instr_stats.siteid = sites.id AND sites.name = '%s'",
                        'qargs'   => array( 'site' )
                        )
                 )
           );
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  return $sqlParamWriter->getImgURL( $id,
                                     "$start", "$end",
                                     true, 640, 480 );
}

$args = "date=" . $date . "&dir=" . $dir . "&oss=" . $oss . "&site=" . $site;
$start = $date . " 00:00:00";
$end = $date . " 23:59:59";

if (isset($_GET['start']) && isset($_GET['end'])) {
    $args .= "&start=" . $_GET['start'] . "&end=" . $_GET['end'];
    $start = $_GET['start'];
    $end = $_GET['end'];
}

echo "<h1>";
drawHelpLink("tssHelp");
echo "TSS Statistics</h1>";
drawHelp("tssHelp", "TSS (Telecom Security Services) Instrumentation Data", "TSS (Telecom Security Services) instrumentation data is displayed here, as collected by DDC instr via Self Management. Click on the link to navigate to the desired section on this page.");
?>
<li><h4><a href="#Auth">Authority Service</h4></a></h4></li>
<li><h4><a href="#Notif">Notification Handler</a></h4></li>
<li><h4><a href="#Pw">Password Service</a></h4></li>
<form name=range method="get">
Start: <input type=text name=start value="<?=$start?>" />
End: <input type=text name=end value="<?=$end?>" />
<input type=hidden name="date" value="<?=$date?>" />
<input type=hidden name="dir" value="<?=$dir?>" />
<input type=hidden name="oss" value="<?=$oss?>" />
<input type=hidden name="site" value="<?=$site?>" />
<input type=submit name=submit value="update" />
</form>

<?php
echo "<h2 id =\"Auth\">Authorization Service</h2>\n";

echo "<h3>Auth DB Stats</h3>\n";
echo getOperationsStatGraph('Connections','Count', $start,$end,
			    array (
				   "auth_db_acivityactsetconns" => "acivityActivitySetConnections",
				   "auth_db_actsetactsetconns" => "activitySetActivitySetConnections",
				   "auth_db_tgtgrptgtgrpconns" => "targetGroupTargetGroupConnections",
				   "auth_db_tgttgtgrpconns" => "targetTargetGroupConnections",
				   "auth_db_userroleconns" => "userRoleConnections"
				   ),
			    'sa' );
echo getOperationsStatGraph('Other','Count', $start,$end,
                             array (
                                    "auth_db_aclentries" => "aclEntries",
                                    "auth_db_aclgentries" => "aclgEntries",
                                    "auth_db_acts" => "activities",
                                    "auth_db_actsets" => "activitySets",
                                    "auth_db_roles" => "roles",
                                    "auth_db_tgtgrps" => "targetGroups",
                                    "auth_db_tgts" => "targets",
                                    "auth_db_users" => "users"
                                    ),
                             'sa' );
echo "<h3>Calls</h3>\n";
echo getOperationsStatGraph('Calls','Count', $start,$end,
                             array (
                                    "auth_getallwacts_calls" => "getAllowedActivities",
                                    "auth_getallwtgts_calls" => "getAllowedTargets",
                                    "auth_getauthtgtgrps_calls" => "getAuthorizedTargetGroups",
                                    "auth_getauthtgts_calls" => "getAuthorizedTargets",
                                    "auth_isauth_calls" => "isAuthorized",
                                    "auth_isauthbatch_calls" => "isAuthorizedBatch"
                                    ),
                             'sa' );
echo getOperationsStatGraph('Exception Calls','Count', $start,$end,
                             array (
                                    "auth_getallwacts_exc_calls" => "getAllowedActivities",
                                    "auth_getallwtgts_exc_calls" => "getAllowedTargets",
                                    "auth_getauthtgtgrps_exc_calls" => "getAuthorizedTargetGroups",
                                    "auth_getauthtgts_exc_calls" => "getAuthorizedTargets",
                                    "auth_isauth_exc_calls" => "isAuthorized",
                                    "auth_isauthbatch_exc_calls" => "isAuthorizedBatch"
                                    ),
                             'sa' );

echo "<h3>Execution Time</h3>\n";
echo getOperationsStatGraph('Total Execution Time','Count', $start,$end,
                             array (
                                    "auth_getallwacts_totexectime" => "getAllowedActivities",
                                    "auth_getallwtgts_totexectime" => "getAllowedTargets",
                                    "auth_getauthtgtgrps_totexectime" => "getAuthorizedTargetGroups",
                                    "auth_getauthtgts_totexectime" => "getAuthorizedTargets",
                                    "auth_isauth_totexectime" => "isAuthorized",
                                    "auth_isauthbatch_totexectime" => "isAuthorizedBatch"
                                    ),
                             'sa' );
echo getOperationsStatGraph('Exception Total Execution Time','Count', $start,$end,
                             array (
                                    "auth_getallwacts_exc_totexectime" => "getAllowedActivities_Exception",
                                    "auth_getallwtgts_exc_totexectime" => "getAllowedTargets_Exception",
                                    "auth_getauthtgtgrps_exc_totexectime" => "getAuthorizedTargetGroups_Exception",
                                    "auth_getauthtgts_exc_totexectime" => "getAuthorizedTargets_Exception",
                                    "auth_isauth_exc_totexectime" => "isAuthorized_Exception",
                                    "auth_isauthbatch_exc_totexectime" => "isAuthorizedBatch_Exception"
                                    ),
                             'sa' );
echo "<h3>Sizes</h3>\n";
echo getOperationsStatGraph('Sizes','Count', $start,$end,
                             array (
                                    "auth_getallwacts_sizes" => "getAllowedActivities_Sizes",
                                    "auth_getallwtgts_sizes" => "getAllowedTargets_Sizes",
                                    "auth_getauthtgtgrps_sizes" => "getAuthorizedTargetGroups_Sizes",
                                    "auth_getauthtgts_sizes" => "getAuthorizedTargets_Sizes",
                                    "auth_isauthbatch_sizes" => "isAuthorizedBatch_Sizes"
                                    ),
                             'sa' );

/* Notification Handler */
echo "<h2 id = \"Notif\">Notification Handler</h2>\n";

echo "<h3>Calls</h3>\n";
echo getOperationsStatGraph('Calls','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_calls" => "ACLEntryIsCreated",
                                    "notif_aclentryisdel_calls" => "ACLEntryIsDeleted",
                                    "notif_tgtgrpsaddtotgtgrp_calls" => "TargetGroupsAddedToTargetGroup",
                                    "notif_tgtgrpsremfromtgtgrp_calls" => "TargetGroupsRemovedFromTargetGroup",
                                    "notif_tgtsaddtotgtgrp_calls" => "TargetsAddedToTargetGroup",
                                    "notif_tgtsremfromtgtgrp_calls" => "TargetsRemovedFromTargetGroup",
                                    "notif_usersaddtorole_calls" => "UsersAddedToRole",
                                    "notif_usersdel_calls" => "UsersDeleted",
                                    "notif_usersremfromrole_calls" => "UsersRemovedFromRole"
                                    ),
                             'sa' );

echo "<h3>Exception Calls</h3>\n";
echo getOperationsStatGraph('Exception Calls','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_exc_calls" => "Notif_ACLEntryIsCreated",
                                    "notif_aclentryisdel_exc_calls" => "Notif_ACLEntryIsDeleted",
                                    "notif_tgtgrpsaddtotgtgrp_exc_calls" => "Notif_TargetGroupsAddedToTargetGroup",
                                    "notif_tgtgrpsremfromtgtgrp_exc_calls" => "Notif_TargetGroupsRemovedFromTargetGroup",
                                    "notif_tgtsaddtotgtgrp_exc_calls" => "Notif_TargetsAddedToTargetGroup",
                                    "notif_tgtsremfromtgtgrp_exc_calls" => "Notif_TargetsRemovedFromTargetGroup",
                                    "notif_usersaddtorole_exc_calls" => "Notif_UsersAddedToRole",
                                    "notif_usersdel_exc_calls" => "Notif_UsersDeleted",
                                    "notif_usersremfromrole_exc_calls" => "Notif_UsersRemovedFromRole"
                                    ),
                             'sa' );

echo "<h3>Impacted Targets</h3>\n";
echo getOperationsStatGraph('Impacted Targets','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_imp_tgts" => "ACLEntryIsCreated",
                                    "notif_aclentryisdel_imp_tgts" => "ACLEntryIsDeleted",
                                    "notif_usersaddtorole_imp_tgts" => "UsersAddedToRole",
                                    "notif_usersdel_imp_tgts" => "UsersDeleted",
                                    "notif_usersremfromrole_imp_tgts" => "UsersRemovedFromRole",
				    "notif_tgtgrpsaddtotgtgrp_imp_tgts" => "TargetGroupsAddedToTargetGroup",
                                    "notif_tgtgrpsremfromtgtgrp_imp_tgts" => "TargetGroupsRemovedFromTargetGroup",
                                    "notif_tgtsaddtotgtgrp_imp_tgts" => "TargetsAddedToTargetGroup",
                                    "notif_tgtsremfromtgtgrp_imp_tgts" => "TargetsRemovedFromTargetGroup"
                                    ),
                             'sa' );
echo "<h3>Impacted Users</h3>\n";
echo getOperationsStatGraph('Impacted Users','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_imp_users" => "ACLEntryIsCreated",
                                    "notif_aclentryisdel_imp_users" => "ACLEntryIsDeleted",
                                    "notif_usersaddtorole_imp_users" => "UsersAddedToRole",
                                    "notif_usersdel_imp_users" => "UsersDeleted",
                                    "notif_usersremfromrole_imp_users" => "UsersRemovedFromRole",
                                    "notif_tgtgrpsaddtotgtgrp_imp_users" => "TargetGroupsAddedToTargetGroup",
                                    "notif_tgtgrpsremfromtgtgrp_imp_users" => "TargetGroupsRemovedFromTargetGroup",
                                    "notif_tgtsaddtotgtgrp_imp_users" => "TargetsAddedToTargetGroup",
                                    "notif_tgtsremfromtgtgrp_imp_users" => "TargetsRemovedFromTargetGroup"
                                    ),
                             'sa' );
?>

<h3>Total Execution Time</h3>
<?php
echo getOperationsStatGraph('ACL Entry & General Total Execution Time','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_exc_totexectime" => "Notif_ACLEntryIsCreated_Exception",
                                    "notif_aclentryisctd_totexectime" => "Notif_ACLEntryIsCreated",
                                    "notif_aclentryisdel_exc_totexectime" => "Notif_ACLEntryIsDeleted_Exception",
                                    "notif_aclentryisdel_totexectime" => "Notif_ACLEntryIsDeleted",
                                    "notif_general_exc_totexectime" => "Notif_General_Exception",
                                    "notif_general_totexectime" => "Notif_General"
                                    ),
                             'sa' );
?>
<?php
echo getOperationsStatGraph('Target Groups Execution Time','Count', $start,$end,
                             array (
                                    "notif_tgtgrpsaddtotgtgrp_exc_totexectime" => "Notif_TargetGroupsAddedToTargetGroup_Exception",
                                    "notif_tgtgrpsaddtotgtgrp_totexectime" => "Notif_TargetGroupsAddedToTargetGroup",
                                    "notif_tgtgrpsremfromtgtgrp_exc_totexectime" => "Notif_TargetGroupsRemovedFromTargetGroup_Exception",
                                    "notif_tgtgrpsremfromtgtgrp_totexectime" => "Notif_TargetGroupsRemovedFromTargetGroup",
                                    "notif_tgtsaddtotgtgrp_exc_totexectime" => "Notif_TargetsAddedToTargetGroup_Exception",
                                    "notif_tgtsaddtotgtgrp_totexectime" => "Notif_TargetsAddedToTargetGroup",
                                    "notif_tgtsremfromtgtgrp_exc_totexectime" => "Notif_TargetsRemovedFromTargetGroup_Exception",
                                    "notif_tgtsremfromtgtgrp_totexectime" => "Notif_TargetsRemovedFromTargetGroup"
                                    ),
                             'sa' );
?>
<?php
echo getOperationsStatGraph('Target Groups Execution Time','Count', $start,$end,
                             array (
                                    "notif_usersaddtorole_exc_totexectime" => "Notif_UsersAddedToRole_Exception",
                                    "notif_usersaddtorole_totexectime" => "Notif_UsersAddedToRole",
                                    "notif_usersdel_exc_totexectime" => "Notif_UsersDeleted_Exception",
                                    "notif_usersdel_totexectime" => "Notif_UsersDeleted",
                                    "notif_usersremfromrole_exc_totexectime" => "Notif_UsersRemovedFromRole_Exception",
                                    "notif_usersremfromrole_totexectime" => "Notif_UsersRemovedFromRole"
                                    ),
                             'sa' );
?>
<h3>Real Execution Time</h3>
<?php
echo getOperationsStatGraph('ACL Entry & General Real Execution Time','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_exc_realexectime" => "Notif_ACLEntryIsCreated_Exception_RealExecutionTime",
                                    "notif_aclentryisctd_realexectime" => "Notif_ACLEntryIsCreated_RealExecutionTime",
                                    "notif_aclentryisdel_exc_realexectime" => "Notif_ACLEntryIsDeleted_Exception_RealExecutionTime",
                                    "notif_aclentryisdel_realexectime" => "Notif_ACLEntryIsDeleted_RealExecutionTime",
                                    "notif_general_exc_realexectime" => "Notif_General_Exception_RealExecutionTime",
                                    "notif_general_realexectime" => "Notif_General_RealExecutionTime"
                                    ),
                             'sa' );
?>
<?php
echo getOperationsStatGraph('Target Groups Real Execution Time','Count', $start,$end,
                             array (
                                    "notif_tgtgrpsaddtotgtgrp_exc_realexectime" => "Notif_TargetGroupsAddedToTargetGroup_Exception_RealExecutionTime",
                                    "notif_tgtgrpsaddtotgtgrp_realexectime" => "Notif_TargetGroupsAddedToTargetGroup_RealExecutionTime",
                                    "notif_tgtgrpsremfromtgtgrp_exc_realexectime" => "Notif_TargetGroupsRemovedFromTargetGroup_Exception_RealExecutionTime",
                                    "notif_tgtgrpsremfromtgtgrp_realexectime" => "Notif_TargetGroupsRemovedFromTargetGroup_RealExecutionTime",
                                    "notif_tgtsaddtotgtgrp_exc_realexectime" => "Notif_TargetsAddedToTargetGroup_Exception_RealExecutionTime",
                                    "notif_tgtsaddtotgtgrp_realexectime" => "Notif_TargetsAddedToTargetGroup_RealExecutionTime",
                                    "notif_tgtsremfromtgtgrp_exc_realexectime" => "Notif_TargetsRemovedFromTargetGroup_Exception_RealExecutionTime",
                                    "notif_tgtsremfromtgtgrp_realexectime" => "Notif_TargetsRemovedFromTargetGroup_RealExecutionTime"
                                    ),
                             'sa' );
?>
<?php
echo getOperationsStatGraph('Users Real Execution Time','Count', $start,$end,
                             array (
                                    "notif_usersaddtorole_exc_realexectime" => "Notif_UsersAddedToRole_Exception_RealExecutionTime",
                                    "notif_usersaddtorole_realexectime" => "Notif_UsersAddedToRole_RealExecutionTime",
                                    "notif_usersdel_exc_realexectime" => "Notif_UsersDeleted_Exception_RealExecutionTime",
                                    "notif_usersdel_realexectime" => "Notif_UsersDeleted_RealExecutionTime",
                                    "notif_usersremfromrole_exc_realexectime" => "Notif_UsersRemovedFromRole_Exception_RealExecutionTime",
                                    "notif_usersremfromrole_realexectime" => "Notif_UsersRemovedFromRole_RealExecutionTime"
                                    ),
                             'sa' );
?>
<h3>Wait Time</h3>
<?php
echo getOperationsStatGraph('ACL Entry & General','Count', $start,$end,
                             array (
                                    "notif_aclentryisctd_exc_waittime" => "Notif_ACLEntryIsCreated_Exception_WaitTime",
                                    "notif_aclentryisctd_waittime" => "Notif_ACLEntryIsCreated_WaitTime",
                                    "notif_aclentryisdel_exc_waittime" => "Notif_ACLEntryIsDeleted_Exception_WaitTime",
                                    "notif_aclentryisdel_waittime" => "Notif_ACLEntryIsDeleted_WaitTime",
                                    "notif_general_exc_waittime" => "Notif_General_Exception_WaitTime",
                                    "notif_general_waittime" => "Notif_General_WaitTime"
                                    ),
                             'sa' );
?>
<?php
echo getOperationsStatGraph('Target Group Wait Time','Count', $start,$end,
                             array (
                                    "notif_tgtgrpsaddtotgtgrp_exc_waittime" => "Notif_TargetGroupsAddedToTargetGroup_Exception_WaitTime",
                                    "notif_tgtgrpsaddtotgtgrp_waittime" => "Notif_TargetGroupsAddedToTargetGroup_WaitTime",
                                    "notif_tgtgrpsremfromtgtgrp_exc_waittime" => "Notif_TargetGroupsRemovedFromTargetGroup_Exception_WaitTime",
                                    "notif_tgtgrpsremfromtgtgrp_waittime" => "Notif_TargetGroupsRemovedFromTargetGroup_WaitTime",
                                    "notif_tgtsaddtotgtgrp_exc_waittime" => "Notif_TargetsAddedToTargetGroup_Exception_WaitTime",
                                    "notif_tgtsaddtotgtgrp_waittime" => "Notif_TargetsAddedToTargetGroup_WaitTime",
                                    "notif_tgtsremfromtgtgrp_exc_waittime" => "Notif_TargetsRemovedFromTargetGroup_Exception_WaitTime",
                                    "notif_tgtsremfromtgtgrp_waittime" => "Notif_TargetsRemovedFromTargetGroup_WaitTime"
                                    ),
                             'sa' );
?>
<?php
echo getOperationsStatGraph('Users','Count', $start,$end,
                             array (
                                    "notif_usersaddtorole_exc_waittime" => "Notif_UsersAddedToRole_Exception_WaitTime",
                                    "notif_usersaddtorole_waittime" => "Notif_UsersAddedToRole_WaitTime",
                                    "notif_usersdel_exc_waittime" => "Notif_UsersDeleted_Exception_WaitTime",
                                    "notif_usersdel_waittime" => "Notif_UsersDeleted_WaitTime",
                                    "notif_usersremfromrole_exc_waittime" => "Notif_UsersRemovedFromRole_Exception_WaitTime",
                                    "notif_usersremfromrole_waittime" => "Notif_UsersRemovedFromRole_WaitTime"
                                    ),
                             'sa' );
?>

<a name="Pw"></a>
<h2>Password Service</h2>
<h3>General Status</h3>
<?php
echo getOperationsStatGraph('General Status','Time', $start,$end,
                             array (
                                    "pw_general_status" => "Pw_General_Status"
                                    ),
                             'sa' );
?>
<h3>Calls</h3>
<?php
echo getOperationsStatGraph('Calls','Time', $start,$end,
                             array (
                                    "pw_getpassword_calls" => "Pw_getPassword",
                                    "pw_getpassword_exc_calls" => "Pw_getPassword_Exception"
                                    ),
                             'sa' );
?>
<h3>Execution Time</h3>
<?php
echo getOperationsStatGraph('Execution Time','Time', $start,$end,
                             array (
                                    "pw_getpassword_exc_totexectime" => "Pw_getPassword_Exception",
                                    "pw_getpassword_totexectime" => "Pw_getPassword",
                                    ),
                             'sa' );
?>

<?php
include "common/finalise.php";
?>
