<?php


$UI = false;
$NOREDIR = true;

require_once "init.php";

$oss_targets = array(
    'generic' => 'd/fBuz4Xw4k/eic-overview',
    'tor' => 'd/ssEa3GX4k/enm-overview'
);

$statsDB = new StatsDB();
if ( $statsDB->hasData("$AdminDB.external_store", 'date', true) ) {
    $location = $grafanaURL;
    if ( array_key_exists($oss, $oss_targets) ) {
        $args = array(
            "var-ds=" . gethostname(),
            "var-site=" . $site,
            "from=" . DateTime::createFromFormat("Y-m-d H:i:s", $date . " 00:00:00")->getTimestamp() * 1000,
            "to=" . DateTime::createFromFormat("Y-m-d H:i:s", $date . " 23:59:59")->getTimestamp() * 1000
        );
        $location = $location . "/" . $oss_targets[$oss] . "?" . implode("&", $args);
    }
} else {
    $location = "./loadmetrics.php?" . $webargs;
}

header("Location: $location");
