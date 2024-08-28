<?php
$pageTitle = "Health Check Subscriptions";

include_once "init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function getSubs( $signum ) {
    global $statsDB;

    $data = array();

    $sql = "
SELECT
  name,
  reportname,
  CONCAT(sites.id, ':', rep.id) AS id
FROM
  sites,
  ddpadmin.ddp_alert_subscriptions AS sub,
  ddpadmin.ddp_custom_reports AS rep
WHERE
  sub.signum = '$signum' AND
  sites.id = sub.siteid AND
  rep.id = sub.reportid";

    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow() ) {
        $data[] = $row;
    }

    $sql = "
SELECT
  name,
  'Default' AS reportname,
  CONCAT(sites.id, ':', '0') AS id
FROM
  sites,
  ddpadmin.ddp_alert_subscriptions AS sub
WHERE
  sub.signum = '$signum' AND
  sites.id = sub.siteid AND
  sub.reportid = 0
";

    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow() ) {
        $data[] = $row;
    }

    return $data;
}

function showSubs( $data, $user ) {
    $link = makeSelfLink() . "?&user=$user";

    $table = new DDPTable(
        "Subs",
        array(
            array(DDPTable::KEY => 'name', DDPTable::LABEL => 'Site Name'),
            array(DDPTable::KEY => 'reportname', DDPTable::LABEL => 'Report Name'),
            array(DDPTable::KEY => 'id', DDPTable::VISIBLE => false)
        ),
        array('data' => $data),
        array (
            DDPTable::CTX_MENU => array(
                DDPTable::KEY => 'view',
                DDPTable::MULTI => false,
                DDPTable::MENU => array( 'unSub' => 'Unsubscribe'),
                DDPTable::URL => $link,
                DDPTable::COL => 'id')
        )
    );

    echo $table->getTable();
}

function removeSub( $siteId, $repId, $user ) {
    global $AdminDB, $statsDB, $DBName;

    $statsDB->exec("use $AdminDB");
    $sql = "DELETE FROM ddp_alert_subscriptions WHERE signum = '$user' AND siteid = $siteId AND reportid = $repId";
    $res = $statsDB->exec($sql);
    mgtlog("Health Check Alert Me Unsubscribed:: Signum: $user SiteId: $siteId ReportId: $repId Result: $res");
    $statsDB->exec("use $DBName");
}

function main( $user ) {
    drawHeader( "Health Check Subscriptions For $user", 1, "hcSubs" );
    $data = getSubs( $user );
    showSubs( $data, $user );
}

global $auth_user;

$user = requestValue('user');
if ( ! $user ) {
    $user = $auth_user;
}

if ( requestValue('view') == 'unSub' ) {
    $sel = requestValue('selected');
    $params = explode(':', $sel);
    removeSub( $params[0], $params[1], $user );
}

main( $user );

include_once "../php/common/finalise.php";

