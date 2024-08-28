<?php
echo "<div id=cal>";

$links = array (
    "sitemgt" => "Site Management",
    "accessmgt" => "Access Management",
    "createsite" => "Create Site",
    "createop" => "Create New Operator",
    "deploymentinfra" => "Create New Deployment Infrastructure",
    "service" => "DDP Service",
    "faq" => "F.A.Q.",
    "tables" => "Database Tables",
    "upgrades" => "Upgrade Logs",
    "upgrade" => "Perform Upgrade",
    "usermgt" => "User Management",
    "ddl" => "Database Definition File",
    "ddplogs"   =>  "DDP logs",
    "dbqueries" => "DB queries",
    "ddp_replication" => "DDP Replication",
    "autoincridmgt" => "Check DB Auto-ID Limits",
    "copytestdatamgt" => "Copy Test Data",
    "modelledinstr" => "Instrumentation Prototyping",
    "hcedit" => "Custom Health Checks",
    "reprocess" => "Reprocess Site",
    "runAlertMe" => "Reprocess HC",
    "dumpAdminDB" => "Get AdminDB Data",
    "updateDDPStatus" => "Update DDP Status",
    "hcAdmin" => "Custom HC Admin",
    "hcSubs" => "Custom HC Subs",
    "../php/index" => "DDP Server Stats",
    "../php/DDP/report" => "DDP Server Reports",
    "links" => "Link Management"
);

function getDdpSite() {
    global $statsDB;

    $sql = "SELECT name FROM sites WHERE name LIKE '%LMI_ddp%'";
    $res = $statsDB->queryRow($sql);
    return $res[0];
}

function displayLinks($links, $authPages, $thisPage) {
    asort($links);
    foreach ($links as $key => $val) {
        if (isset($authPages[$key]) && $authPages[$key] == 1) {
            $selected = "";
            if ($thisPage == $key) {
                $selected = " class=selected";
            }

            if ( $key == '../php/index' || $key == '../php/DDP/report' ) {
                $site = getDdpSite();
                $selected = "?oss=ddp&site=$site";
            }

            echo "<a href=" . $key . ".php" . $selected . ">" . $val . "</a><br/>\n";
        }
    }
}

displayLinks($links, $authPages, $thisPage);

echo "</div>";

