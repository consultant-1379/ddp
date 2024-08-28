<?php
include "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";

function processUpgrade() {
    global $file, $ddp_dir, $auth_user;

    if ( $file->isUploadedFile() ) {
        // Move the upgrade file to /data/tmp
        $uploadParam = $file->getValue();
        $filename = $uploadParam['name'];
        $file->moveUploadedFile('/data/tmp');
        chmod( '/data/tmp/' . $filename  , 0666 );

        $cmd = $ddp_dir . "/server_setup/upgrade -f /data/tmp/$filename";

        $ldap_conn = getLdapConn();
        $emails = fetchDDPAdminEmails($ldap_conn);
        $upgraderEmail = getEmailFromLdap($ldap_conn,$auth_user);
        ldap_close($ldap_conn);

        // Place the upgrade requestor as the first email for TORF-436557
        array_unshift($emails, $upgraderEmail);

        if ( count($emails) > 0 ) {
            $cmd = $cmd . " -e " . implode(',', $emails);
        }

        $cmd = $cmd . " >> /data/tmp/upgradelog.txt 2>&1 &";
        system($cmd);

        echo "<b><P>Upgrade started for $filename.</P></b>\n";
        echo "<P>Upgrade results will be sent to:</P>\n";
        echo makeHtmlList( $emails );
    } else {
        echo "Failed to upload your upgrade package";
    }
}

function getEmailFromLdap( $ldap_conn, $user ) {
    $userInfo = getLdapUserInfo($ldap_conn,$user,array("mail"));
    if ( is_null($userInfo) ) {
        echo "<p>ERROR: No match found for ". $user . "</B>";
        mgtlog( "$user not found in Ldap, Please remove from upgrade emails list" );
        return NULL;
    }
    $email = $userInfo['mail'];

    return $email;
}

function fetchDDPAdminEmails($ldap_conn) {
    global $statsDB, $AdminDB;
    $adminUserEmails = array();
    $sql = "SELECT signum FROM " . $AdminDB . ".ddpusers where get_upgrade_emails = TRUE";
    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow()) {
        $userMail = getEmailFromLdap( $ldap_conn,$row['signum'] );
        if ( ! is_null($userMail) ) {
            array_push( $adminUserEmails, $userMail );
        }
    }

    return $adminUserEmails;
}

function createUpgradeForm( &$file ) {
    global $debug;
    $form = new HTML_QuickForm('upgradeform', 'POST');

    $file = $form->addElement('file', 'upgradefile', "Upgrade file");
    $form->addRule('upgradefile', 'You must select an upgrade file', 'required');
    $form->addElement('submit', null, 'Upgrade');
    $form->addElement('hidden', 'debug', $debug );

    return $form;
}

function createDisableForm() {
    $form = new HTML_QuickForm('disableForm', 'POST', '?disable=true');
    $form->addElement('submit', null, 'Disable Upgrades');
    return $form;
}

function createEnableForm() {
    $form = new HTML_QuickForm('enableForm', 'POST', '?enable=true');
    $form->addElement('submit', null, 'Enable Upgrades');
    return $form;
}

function whoDisabled( $filePath ) {
    if ( file_exists($filePath) ) {
        $f = fopen($filePath, "r") or die("Unable to open file!");
        echo fread($f, filesize($filePath));
        fclose($f);
    }
}

function getStatus() {
    $statuscmd = "ps -ef | grep -i [u]pgrade | grep [s]tatsadm | grep [s]udo";
    $result = exec($statuscmd);
    if ( preg_match("/upgrade/", $result) ) {
        $result = preg_split('/\s+/', $result);
        $upgradepid = $result[1];
    } else {
        $upgradepid = "NOT RUNNING";
    }
    return $upgradepid;
}

function displayForms( $upgradeForm, $disableForm, $enableForm ) {
    global $auth_user;
    $filePath = '/data/tmp/disable_upgrades';

    if ( file_exists($filePath) && ! issetURLParam('disable') && ! issetURLParam('enable') ) {
        $enableForm->display();
        whoDisabled( $filePath );
    } else {
        if ( issetURLParam('disable') ) {
            // If file dosn't exist, create it
            if ( ! file_exists($filePath) ) {
                $f = fopen($filePath, "w") or die("Unable to open file!");
                $time = date('Y-m-d H:i:s');
                $msg = "DDP Upgrades Disabled by $auth_user";
                fwrite($f, $msg . " on $time");
                fclose($f);
                mgtlog($msg);
            }
            // Display the enable form & print who disabled upgrades
            $enableForm->display();
            whoDisabled( $filePath );
        } else {
            // If file exists & enable is clicked deltet the file
            if ( issetURLParam('enable') && file_exists($filePath) ) {
                unlink( $filePath );
                mgtlog("DDP Upgrades Enabled by $auth_user");
            }
            // Display the upgrade form & the disable form
            $upgradeForm->display();
            $disableForm->display();
        }
    }
}

function main() {
    global $file, $auth_user;

    $upgradepid = getStatus();

    // Create the forms
    $upgradeForm = createUpgradeForm( $file );
    $disableForm = createDisableForm();
    $enableForm = createEnableForm();
 
    if ($upgradeForm->validate()) {
        $upgradeForm->freeze();
        $upgradeForm->process('processUpgrade', false);
    } else {
        drawHeader('Upgrade DDP', 1, '');

        if ( $upgradepid !== 'NOT RUNNING' ) {
            echo "Upgrade in progress. Please wait until it has completed";
        } else {
            displayForms( $upgradeForm, $disableForm, $enableForm );
        }
    }
}

$file;
main();

include "../php/common/finalise.php";

