<?php
$pageTitle = "HA Events";
include "common/init.php";

$statsDB = new StatsDB();

$eventTypes = array(
	"G" => "HA Group",
	"R" => "HA Resource"
);

// SQL for user events
$sql = "SELECT sites.name AS site," .
	"servers.hostname AS host," .
	"halog_resources.name AS resource," .
	"halog_cmds.restype AS restype," .
	"halog_cmdnames.name AS cmd," .
	"oss_users.name AS user," .
	"halog_cmds.time AS time " .
	"FROM sites,servers,halog_resources,halog_cmds,halog_cmdnames,oss_users " .
	"WHERE sites.id = halog_cmds.siteid " .
    "AND sites.name = \"" . $site . "\" " .
	"AND servers.id = halog_cmds.host " .
	"AND halog_resources.id = halog_cmds.resource " .
	"AND halog_cmdnames.id = halog_cmds.cmd " .
	"AND oss_users.id = halog_cmds.user " .
	"AND halog_cmds.time BETWEEN \"" . $date . " 00:00:00\" AND \"" . $date . " 23:59:59\" " .
	"ORDER BY time";

$statsDB->query($sql);

if ($statsDB->getNumRows() > 0) {
?>
<h1>HA Events</h1>
<h2>User Commands</h2>
<table border=1>
<tr><th>Resource</th><th>Res. Type</th><th>Command</th><th>User</th><th>Time</th><th>Server</th></tr>
<?php
	while($row = $statsDB->getNextNamedRow()) {
		echo "<tr>" .
			"<td>" . $row['resource'] . "</td>" .
			"<td>" . $row['restype'] . "</td>" .
			"<td>" . $row['cmd'] . "</td>" .
			"<td>" . $row['user'] . "</td>" .
			"<td>" . $row['time'] . "</td>" .
			"<td>" . $row['host'] . " </td>" .
			"</tr>\n";
		/*echo "<tr><td colspan=8>\n";
		echo "<pre>\n";
		print_r($row);
		echo "</pre></td></tr>\n";*/
	}
?>
</table>
<?php
}

foreach ($eventTypes as $key => $evt) {
	$sql = "SELECT sites.name AS site," .
		"servers.hostname AS host," .
		"halog_resources.name AS resource," .
		"halog_events.restype AS restype," .
		"halog_status.name AS status," .
		"halog_events.reason AS reason," .
		"oss_users.name AS owner," .
		"halog_groups.name AS grp," .
		"halog_events.time AS time " .
		"FROM sites,servers,halog_resources,halog_events,halog_status,oss_users,halog_groups " .
        "WHERE sites.id = halog_events.siteid " .
        "AND sites.name = \"" . $site . "\" " .
		"AND servers.id = halog_events.host " .
		"AND halog_resources.id = halog_events.resource " .
		"AND halog_status.id = halog_events.status " .
		"AND oss_users.id = halog_events.owner " .
		"AND halog_groups.id = halog_events.grp " .
		"AND halog_events.time BETWEEN \"" . $date . " 00:00:00\" AND \"" . $date . " 23:59:59\" " .
		"AND halog_events.restype = \"" . $key . "\" " .
		"ORDER BY time";
	$statsDB->query($sql);
	if ($statsDB->getNumRows() > 0) {
?>
	<h2><?=$evt; ?> Events</h2>
<table border=1>
<tr><th>Resource</th><th>Owner</th><th>Group</th><th>Status</th><th>Reason</th><th>Time</th><th>Server</th></tr>
<?php
		while($row = $statsDB->getNextNamedRow()) {
			echo "<tr>" .
				"<td>" . $row['resource'] . "</td>" .
				"<td>" . $row['owner'] . "&nbsp;</td>" .
				"<td>" . $row['grp'] . "&nbsp;</td>" .
				"<td>" . $row['status'] . "</td>" .
				"<td>" . $row['reason'] . "&nbsp;</td>" .
				"<td>" . $row['time'] . "</td>" .
				"<td>" . $row['host'] . " </td>" .
				"</tr>\n";
		}
?>
</table>
<?php
	}
}

include "common/finalise.php";
?>
