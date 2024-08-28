<?php
$pageTitle = "Custom Health Check Editor";

include_once "init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";

function main( $selfLink ) {
    $table = new ModelledTable(
        'DDP/cust_hc_list',
        'chc',
        $selfLink
    );
    echo $table->getTableWithHeader("Custom Health Checks");
}

function showSub( $selfLink ) {
    $table = new ModelledTable(
        'DDP/sub_list',
        'chc',
        $selfLink
    );
    echo $table->getTableWithHeader("Subscriptions");
}

function removeAllSubs( $repId, $selfLink, $editDB ) {
    global $AdminDB;

    $sql = "DELETE FROM ddp_alert_subscriptions WHERE reportid = $repId;";
    $res = $editDB->exec($sql);

    showSub( $selfLink );
    if ( $res === 0 ) {
        echo "Failed to delete from ddp_alert_subscriptions for ReportId: $repId";
    }
}

function removeIndividualSub( $repId, $siteId, $selfLink, $editDB ) {
    global $AdminDB;

    $sql = "DELETE FROM ddp_alert_subscriptions WHERE reportid = $repId AND siteid = $siteId;";
    $res = $editDB->exec($sql);

    showSub( $selfLink );
    if ( $res === 0 ) {
        echo "Failed to delete from ddp_alert_subscriptions for SiteId: $siteId, ReportId: $repId";
    }
}

function splitSelected( &$siteId, &$repId, $sel ) {
    $parts = explode(',', $sel);
    $siteId = $parts[0];
    $repId = $parts[1];
}

function deleteReportForm( $repId ) {
// Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('hcAdmin', 'POST', "?selected=$repId");
    $form->addElement('submit', "delRep", "Confirm Deletion of Report");
    $form->addElement('submit', "main", "Go back");
    return $form;
}

function deleteReport( $repId, $editDB ) {
    global $AdminDB, $statsDB;

    $sql = "SELECT reportname FROM $AdminDB.ddp_custom_reports WHERE id = $repId;";
    $statsDB->query($sql);
    $repName = $statsDB->getNextRow()[0];

    $sub = '';
    $dis = '';

    if ( hasValidData( $repId, 'ddp_alert_subscriptions' ) ) {
        $sql = "DELETE FROM ddp_alert_subscriptions WHERE reportid = $repId;";
        $sub = $editDB->exec($sql);
    }
    if ( hasValidData( $repId, 'ddp_report_display' ) ) {
        $sql = "DELETE FROM  ddp_report_display WHERE reportid = $repId;";
        $dis = $editDB->exec($sql);
    }
    $sql = "DELETE FROM ddp_custom_reports WHERE id = $repId;";
    $rep = $editDB->exec($sql);

    $msg = addLineBreak(3);
    if ( $rep === 1 ) {
        $msg .= "Custom Report $repName deleted";
        mgtlog($msg);
    } else {
        $msg .= "Error deleting Custom Report $repName";
    }

    $msg .= addLineBreak(2);
    if ( $sub === 1 ) {
        $msg .= "Subscriptions for Custom Report $repName deleted";
    } elseif ( $sub === 0 ) {
        $msg .= "Error deleting Subscriptions for Custom Report $repName";
    }

    $msg .= addLineBreak(2);
    if ( $dis === 1 ) {
        $msg .= "Display flag for Custom Report $repName deleted";
    } elseif ( $dis === 0 ) {
        $msg .= "Error deleting Display flag for Custom Report $repName";
    }

    echo $msg;
}

function hasValidData( $repId, $table ) {
    global $AdminDB, $statsDB;

    $sql = "SELECT COUNT(*) FROM $AdminDB.$table WHERE reportid = $repId;";
    $statsDB->query($sql);
    return $statsDB->getNextRow()[0];
}

$action = requestValue('action');
$sel = requestValue('selected');
$selfLink = array( ModelledTable::URL => makeSelfLink() );
$siteId = '';
$repId = '';

$editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
// Don't use db.table, breaks replication
$editDB->exec("use $AdminDB");

if ( $action === 'seeSub' ) {
    showSub( $selfLink );
} elseif ( $action === 'unsubInst' ) {
    splitSelected( $siteId, $repId, $sel );
    removeIndividualSub( $repId, $siteId, $selfLink, $editDB );
} elseif ( $action === 'unsubAll' ) {
    splitSelected( $siteId, $repId, $sel );
    removeAllSubs( $repId, $selfLink, $editDB );
} elseif ( $action === 'deleteReport' ) {
    $form = deleteReportForm( $sel );
    $form->display();
} elseif ( issetURLParam('delRep') ) {
    deleteReport( $sel, $editDB );
} elseif ( issetURLParam('main')) {
    main( $selfLink );
} else {
    main( $selfLink );
}

include_once PHP_ROOT . "/common/finalise.php";

