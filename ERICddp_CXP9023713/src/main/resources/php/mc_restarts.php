<?php
$pageTitle = "MC Restarts";
include "common/init.php";
$statsDB = new StatsDB();
require_once PHP_ROOT . "/classes/DDPObject.class.php";

echo "<H1>Managed Component Restarts"; echo "</H1>\n";
/* Display any Managed Component Restarts */
$statsDB->query(
    "SELECT mc_restarts.time, mc_names.name, mc_restarts.duration, mc_restart_types.type, mc_restarts.restart_reason, mc_restarts.restart_reason_txt, oss_users.name, mc_restarts.groupstatus FROM mc_restarts, mc_restart_types, mc_names, oss_users, sites WHERE mc_restarts.siteid = sites.id AND mc_restarts.typeid = mc_restart_types.id AND mc_restarts.nameid = mc_names.id AND mc_restarts.userid = oss_users.id AND sites.name = '$site' AND mc_restarts.time >= '$date 00:00:00' AND mc_restarts.time <= '$date 23:59:59' AND mc_restarts.ind_warm_cold = 'COLD' AND mc_restart_types.type <> 'SYSTEM_SHUTDOWN' AND mc_restarts.groupstatus <> 'GROUP_MEMBER' ORDER BY mc_restarts.time");
if ( $statsDB->getNumRows() > 0 ) {
    echo "<H2>Cold Restarts"; drawHelpLink("restarthelp"); echo "</H2>\n";
?>
<div id="restarthelp" class="helpbox">
<?php
    drawHelpTitle("Managed Component Restarts", "restarthelp");
?>
<div class=helpbody>
A "managed component cold restart" in this context is identified by a managed component going from "started"
to "stopped" and subsequently going from "stopped" to "started".
<p />
The table which is presented consists of seven columns:
<ul>
<li><b>Time:</b> The time that the MC went from "started" to "stopped"</li>
<li><b>MC:</b> The MC which restarted. Or in case of group the group name with keyword "GROUP". For example EBA GROUP will be presented if EBA group is cold restarted</li>
<li><b>Downtime (seconds):</b> The length of time in seconds that the MC was offline - i.e. the length
of time the MC was not in the "started" state</li>
<li><b>Reason:</b> The reason (according to the self management log file) that the MC went offline</li>
<li><b>Restart Reason:</b> In the case of a manual restart, the reason - as entered by the operator - why
the MC is being restarted. This is one of 'other', 'upgrade', 'application', 'planned', 'ha-failover'</li>
<li><b>Restart Reason Text:</b> In the case of a manual restart, a detailed reason for the restart as
entered by the operator</li>
<li><b>User:</b> The user who triggered the restart, in the case of a manual restart</li>
</ul>
Some events which may be expected to appear in this table will not. For example, if an MC fails, self management may
attempt to restart it and fail. In this case the MC will not appear in the list since it cannot be said to have "restarted" - it
is still in a non-started state. If at some later point in the same day the MC is started manually then the event,
and the total time spent offline, will be indicated in the table.
<p />
A limitation of the way the statistics are currently processed means that should a restart span across midnight it will
not appear in the list.
<p />
Restarts which occur as the result of a server reboot are not presented.
<p />
"Restart Reason", "Restart Reason Text" and "User" were added in OSS RC R6 for manual restarts of MCs. These fields will
be empty for non-manual restarts and for systems without this extra logging.
<p />
</div>
</div>
<?php

    class ColdRestartsIndex extends DDPObject {
        var $cols = array(
            "time" => "Time",
            "mc" => "MC",
            "duration" => "Downtime (Seconds)",
            "reason" => "Reason",
            "restart_reason" => "Restart Reason",
            "restart_reason_txt" => "Restart Reason Text",
            "user" => "User"
        );

        var $defaultOrderBy = "time";
        var $defaultOrderDir = "ASC";

        function __construct() {
            parent::__construct("sitelist");
        }

        function getData() {
            global $date, $site;
            $sql = "SELECT mc_restarts.time AS time,
                mc_names.name AS mc,
                mc_restarts.duration AS duration,
                mc_restart_types.type AS reason,
                mc_restarts.restart_reason AS restart_reason,
                mc_restarts.restart_reason_txt AS restart_reason_txt,
                oss_users.name AS user,
                mc_restarts.groupstatus
                FROM   mc_restarts,
                mc_restart_types,
                mc_names,
                oss_users,
                sites
                WHERE  mc_restarts.siteid = sites.id
                AND    mc_restarts.typeid = mc_restart_types.id
                AND    mc_restarts.nameid = mc_names.id
                AND    mc_restarts.userid = oss_users.id AND sites.name = '$site'
                AND    mc_restarts.time >= '$date 00:00:00'
                AND    mc_restarts.time <= '$date 23:59:59'
                AND    mc_restarts.ind_warm_cold = 'COLD'
                AND    mc_restart_types.type <> 'SYSTEM_SHUTDOWN'
                AND    mc_restarts.groupstatus <> 'GROUP_MEMBER'";

            $this->populateData($sql);
            return $this->data;
        }
    }

    $sidx = new ColdRestartsIndex();
    $sidx->getSortableHtmlTable();
}

/* Display any Managed Component Warm Restarts */
$statsDB->query(
    "SELECT mc_restarts.time, mc_names.name, mc_restart_types.type, mc_restarts.restart_reason, mc_restarts.restart_reason_txt, oss_users.name FROM mc_restarts, mc_restart_types, mc_names, oss_users, sites WHERE mc_restarts.siteid = sites.id AND mc_restarts.typeid = mc_restart_types.id AND mc_restarts.nameid = mc_names.id AND mc_restarts.userid = oss_users.id AND sites.name = '$site' AND mc_restarts.time >= '$date 00:00:00' AND mc_restarts.time <= '$date 23:59:59' AND mc_restarts.ind_warm_cold = 'WARM' AND mc_restart_types.type <> 'SYSTEM_SHUTDOWN' AND mc_restarts.groupstatus <> 'GROUP_MEMBER' ORDER BY mc_restarts.time");
if ( $statsDB->getNumRows() > 0 ) {
    echo "<H2>Warm Restarts"; drawHelpLink("warmrestarthelp"); echo "</H2>\n";
?>
<div id="warmrestarthelp" class="helpbox">
<?php
    drawHelpTitle("Managed Component Warm Restarts", "warmrestarthelp");
?>
<div class=helpbody>
A "managed component warm restart" (also known as a "soft reboot") in this context is identified by a managed
component being restarted without powering off, but just refreshing its environment. As there is no
"down time" associated with this, it is not displayed.
<p />
The table which is presented consists of six columns:
<ul>
<li><b>Time:</b> The time that the MC underwent the warm restart</li>
<li><b>MC:</b> The MC which underwent the warm restart. These are all STANDALONE events, i.e. no grouping.
<li><b>Reason:</b> The reason (according to the self management log file) that the MC went offline</li>
<li><b>Restart Reason:</b> In the case of a manual restart, the reason - as entered by the operator - why the MC is being warm restarted. This is one of 'other', 'upgrade', 'application', 'planned', 'ha-failover'</li>
<li><b>Restart Reason Text:</b> In the case of a manual warm restart, a detailed reason for the restart as entered by the operator</li>
<li><b>User:</b> The user who triggered the warm restart, in the case of a manual restart</li>
</ul>
<p />
</div>
</div>
<?php

    class WarmRestartsIndex extends DDPObject {
        var $cols = array(
            "time" => "Time",
            "mc" => "MC",
            "reason" => "Reason",
            "restart_reason" => "Restart Reason",
            "restart_reason_txt" => "Restart Reason Text",
            "user" => "User"
        );

        var $defaultOrderBy = "time";
        var $defaultOrderDir = "ASC";

        function __construct() {
            parent::__construct("sitelist");
        }

        function getData() {
            global $date, $site;
            $sql = "SELECT mc_restarts.time AS time,
                mc_names.name AS mc,
                mc_restart_types.type AS reason,
                mc_restarts.restart_reason AS restart_reason,
                mc_restarts.restart_reason_txt AS restart_reason_txt,
                oss_users.name AS user
                FROM   mc_restarts,
                mc_restart_types,
                mc_names,
                oss_users,
                sites
                WHERE  mc_restarts.siteid = sites.id
                AND    mc_restarts.typeid = mc_restart_types.id
                AND    mc_restarts.nameid = mc_names.id
                AND    mc_restarts.userid = oss_users.id AND sites.name = '$site'
                AND    mc_restarts.time >= '$date 00:00:00'
                AND    mc_restarts.time <= '$date 23:59:59'
                AND    mc_restarts.ind_warm_cold = 'WARM'
                AND    mc_restart_types.type <> 'SYSTEM_SHUTDOWN'
                AND    mc_restarts.groupstatus <> 'GROUP_MEMBER'";

            $this->populateData($sql);
            foreach ($this->data as $key => $d) {
                $this->data[$key] = $d;
            }
            return $this->data;
        }
    }

    $sidx = new WarmRestartsIndex();
    $sidx->getSortableHtmlTable();

}

include "common/finalise.php";
?>
