<?php
$pageInfo = explode(".", basename($_SERVER['PHP_SELF']));
$thisPage = $pageInfo[0];

$CAL = false; // disables the calendar
$NOREDIR = true; // prevent redirection to site_index
require_once "../php/common/init.php";
require_once "../php/common/ldap_directory.php";
require_once "functions.php";
$DBuser = "statsadm";
$DBpass = "_sadm";
require_once PHP_ROOT . "/StatsDB.php";

const UPGRADE = 'upgrade';

function mgtlog($msg) {
    global $auth_user;
    global $stats_dir;
    $logfile = "/data/ddp/log/sitemgt.log";
    error_log(date("Y-m-d H:i:s") . " : " . $auth_user . " : " . $msg . "\n", 3, $logfile);
}

function execSiteMgt($options) {
    global $ddp_dir;
    $output = array();
    $retval = 0;
    # hardcode the path for the production server
    $cmd = $ddp_dir . "/sitemgt/siteMgt";
    # Handle a custom setup for the dev server
    if (! file_exists($cmd)) {
        $cmd = realpath(PHP_ROOT . "/../sitemgt/siteMgt");
    }
    mgtlog($cmd . " " . $options);
    exec($cmd . " " . $options . " 2>&1", $output, $retval);
    foreach ($output as $op) {
        mgtlog($op);
    }
    return $retval;
}

function execCopyTestDataMgt($options) {
    global $ddp_dir;
    $output = array();
    $retval = 0;
    # hardcode the path for the production server
    $cmd = $ddp_dir . "/sitemgt/copyTestDataMgt";
    # Handle a custom setup for the dev server
    if (! file_exists($cmd)) {
        $cmd = realpath(PHP_ROOT . "/../sitemgt/copyTestDataMgt");
    }
    mgtlog($cmd . " " . $options);
    exec($cmd . " " . $options . " 2>&1", $output, $retval);
    foreach ($output as $op) {
        mgtlog($op);
    }
    return $retval;
}

$statsDB = new StatsDB();

$groups = array();

$sql = sprintf("SELECT DISTINCT grp FROM %s.ddpuser_group WHERE signum = '%s'" , $AdminDB, $statsDB->escape($auth_user));
$statsDB->query($sql);
while ($row = $statsDB->getNextNamedRow()) {
    $groups[$row['grp']] = 1;
}

$authPages = array (
    "createsite" => 0,
    "createop" => 0,
    "deploymentinfra" => 0,
    "index" => 0,
    "service" => 0,
    "sitemgt" => 0,
    "tables" => 0,
    UPGRADE => 0,
    "upgrades" => 0,
    "usermgt" => 0,
    "ddplogs"  => 0,
    "dbqueries" => 0,
    "ddp_replication" => 0,
    "autoincridmgt" => 0,
    "copytestdatamgt" => 0,
    "modelledinstr" => 0,
    "hcedit" => 0,
    "reprocess" => 0,
    "runAlertMe" => 0,
    "dumpAdminDB" => 0,
    "updateDDPStatus" => 0,
    "hcAdmin" => 0,
    "hcSubs" => 0,
    "../php/index" => 0,
    "../php/DDP/report" => 0,
    "links" => 0
);

// Everyone can view service status information
$authPages['service'] = 1;
$authPages['index'] = 1;
$authPages['faq'] = 1;
$authPages['hcedit'] = 1;
$authPages['runAlertMe'] = 1;
$authPages['hcSubs'] = 1;

if (isset($groups['linkadm'])) {
    $authPages['links'] = 1;
}

if (isset($groups['accadm'])) {
    $authPages['sitemgt'] = 1;
    $authPages['accessmgt'] = 1;
    $authPages['createsite'] = 1;
    $authPages['createop'] = 1;
    $authPages['deploymentinfra'] = 1;
    $authPages['ddp_replication'] = 1;
    $authPages['reprocess'] = 1;
    $authPages['dumpAdminDB'] = 1;
}

if (isset($groups['ddpadm'])) {
    $authPages['tables'] = 1;
    $authPages['upgrades'] = 1;
    $authPages['usermgt'] = 1;
    $authPages['ddplogs'] = 1;
    $authPages['dbqueries'] = 1;
    $authPages['ddp_replication'] = 1;
    $authPages['autoincridmgt'] = 1;
    $authPages['copytestdatamgt'] = 1;
    $authPages['updateDDPStatus'] = 1;
    $authPages['hcAdmin'] = 1;
    $authPages['../php/index'] = 1;
    $authPages['../php/DDP/report'] = 1;

    if (isset($instr_prototype_dir)) {
        $authPages['modelledinstr'] = 1;
    }
}

if ( array_key_exists(UPGRADE, $groups) ) {
    $authPages[UPGRADE] = 1;
    $authPages[UPGRADE] = 1;
}

$row = $statsDB->queryNamedRow(sprintf("SELECT use_sql FROM %s.ddpusers WHERE signum = '%s'",$AdminDB, $auth_user));
if ($row['use_sql'] != 1) {
    $CAN_USE_SQL = false;
} else {
    $CAN_USE_SQL = true;
}
if ($CAN_USE_SQL == 1) {
    $authPages['ddl'] = 1;
}

if ( ! isset($UI) || $UI ) {
    include "menu.php";
?>
<div id=content>
<?php
    if (! isset($authPages[$thisPage]) || $authPages[$thisPage] != 1) {
        echo "<h1>You do not have permission to view this page</h1>\n";
        include "../php/common/finalise.php";
        exit;
    }
}
?>
