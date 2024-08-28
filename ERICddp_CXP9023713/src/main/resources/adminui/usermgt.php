<?php

include_once "init.php";
include_once "../php/common/countries.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

const CHECKED_TRUE = 'checked=true';
const SIGNUM = 'signum';
const LABEL = 'label';
const USE_SQL = 'use_sql';
const GET_UPGRADE_EMAIL = 'get_upgrade_emails';
const JOB_AREA = 'jobArea';
const MYSQL_KEY = 'mysql_passwd';
const USER_MGT = 'usermgt';
const CHECKBOX = "checkbox";
const ACCESS = 'access';
const PASS_KEY = 'passwd';
const ELEMENT_SIZE_50 = 'value="" size=50';
const MOD_USER = 'moduser';

function getUserList() {
    global $AdminDB, $statsDB;

    $rowData = array();
    $grpData = getAccessList();

    $statsDB->query("
SELECT
  ddpusers.signum AS signum,
  ddpusers.use_sql AS use_sql,
  ddpusers.get_upgrade_emails AS get_upgrade_emails
FROM
  $AdminDB.ddpusers AS ddpusers
GROUP BY signum, use_sql");

    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( array_key_exists( $row[SIGNUM], $grpData ) ) {
            $row['access_groups'] = $grpData[$row[SIGNUM]];
        } else {
            $row['access_groups'] = '';
        }
        $rowData[] = $row;
    }
    return $rowData;
}

function addLink( $rowData ) {
    // Add edit link to signum column
    foreach ($rowData as $key => $d) {
        $d[SIGNUM] = "<a href=\"?signum=" . $d[SIGNUM] . "\">". $d[SIGNUM] . "</a>";
        $rowData[$key] = $d;
    }
    return $rowData;
}

function drawTable( $data ) {
    $keys = array_keys($data[0]);
    $cols = array();

    foreach ( $keys as $col ) {
        $cols[] = array('key' => $col, LABEL => $col);
    }

    $table = new DDPTable(
        'User_Managment',
        $cols,
        array('data' => $data)
    );

    echo $table->getTable();
}

function getUserDataFromLdap($signum, $ldapCon) {
    $userInfo = getLdapUserInfo(
        $ldapCon,
        $signum,
        array("mail","title","co","displayname")
    );

    if ( is_null($userInfo) ) {
        return null;
    }

    if (array_key_exists('title', $userInfo)) {
        $jobArea = $userInfo["title"];
    } else {
        $jobArea = "Unknown";
    }
    return  array(
        'mail' => $userInfo['mail'],
        'name' => $userInfo['displayname'],
        JOB_AREA => $jobArea,
        COUNTRY => $userInfo["co"]
    );
}

function getUserData($signum) {
    global $statsDB, $AdminDB;
    $escSig = $statsDB->escape($signum);
    $sql = "SELECT signum, mysql_passwd, use_sql, get_upgrade_emails FROM $AdminDB.ddpusers WHERE signum = '$escSig'";
    $statsDB->query($sql);
    if ( $statsDB->getNumRows() != 1 ) {
        if ( $statsDB->getNumRows() == 0 ) {
            echo "<h1>No signum '" . $signum . "' exists</h1>\n";
        } else {
            echo "<h1>Found " . $statsDB->getNumRows() . " entries for signum '" . $signum . "'</h1>\n";
        }
        return null;
    }
    $data = $statsDB->getNextNamedRow();
    $user = array(
        SIGNUM => $escSig,
        USE_SQL => $data[USE_SQL],
        GET_UPGRADE_EMAIL => $data[GET_UPGRADE_EMAIL],
        MYSQL_KEY => $data[MYSQL_KEY],
        'name' => "",
        JOB_AREA => "",
        COUNTRY => "",
        'mail' => ""
    );
    $user[SIGNUM] = $escSig;

    $user[USE_SQL] = $data[USE_SQL];
    $ldapCon = getLdapConn();
    $ldapData = getUserDataFromLdap($signum, $ldapCon);
    ldap_close($ldapCon);

    if ( ! is_null($ldapData) ) {
        foreach ( array( 'mail', 'name', JOB_AREA, COUNTRY ) as $fieldName ) {
            $user[$fieldName] = $ldapData[$fieldName];
        }
    }

    return $user;
}

function getAvailableAccessGroups() {
    global $statsDB, $AdminDB;

    $sql = "SELECT column_type FROM information_schema.columns WHERE table_schema = '"
           . $AdminDB . "' AND table_name = 'ddpuser_group' AND column_name = 'grp'";
    $statsDB->query($sql);
    $result = $statsDB->getNextNamedRow();
    $matches;
    preg_match("/^enum\(\'(.*)\'\)$/", $result['column_type'], $matches);
    return explode("','", $matches[1]);
}

function getUserAccessGroups($signum) {
    global $statsDB, $AdminDB;
    $userAccessGroups = array();

    $sql = "SELECT grp FROM " . $AdminDB . ".ddpuser_group where signum = '$signum'";
    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow()) {
        array_push($userAccessGroups, $row['grp']);
    }

    return $userAccessGroups;
}

function createModifyForm($data) {
    global $statsDB;
    // Instantiate the HTML_QuickForm object
    $queryString = fromServer(QUERY_STRING) ? fromServer(QUERY_STRING) : "signum=" . $data[SIGNUM];
    $form = new HTML_QuickForm(USER_MGT,'POST','?' . $queryString);

    $elements = array (
        SIGNUM => "Signum:",
        "name" => "Name:",
        JOB_AREA => "Job Area:",
        COUNTRY => "Country:"
    );
    foreach ($elements as $key => $title) {
        $form->addElement('text', $key, $title, 'value="' . $data[$key] . '" size=50 disabled class=disabled');
    }

    $sqlCB = "";
    if ( $data[USE_SQL] ) {
        $sqlCB = CHECKED_TRUE;
    }
    $form->addElement(CHECKBOX, USE_SQL, "Can use SQL:", null, $sqlCB);

    $upgradeCB = "";
    if ( $data[GET_UPGRADE_EMAIL] ) {
        $upgradeCB = CHECKED_TRUE;
    }
    $form->addElement(CHECKBOX, GET_UPGRADE_EMAIL, "Upgrade Emails:", null, $upgradeCB);

    $accessGroups = getAvailableAccessGroups();
    $userAccessGroups = getUserAccessGroups($data[SIGNUM]);
    $accessCheckboxes = array();
    foreach ( $accessGroups as $accessGroup ) {
        $accessCB = "";
        if ( in_array($accessGroup, $userAccessGroups) ) {
            $accessCB = CHECKED_TRUE;
        }
        $accessCheckboxes[] = HTML_QuickForm::createElement(CHECKBOX, $accessGroup, null, $accessGroup, $accessCB);
    }
    $form->addGroup($accessCheckboxes, ACCESS, 'User Group(s):');

    $form->addElement('password', PASS_KEY, 'Password:', ELEMENT_SIZE_50);

    $form->addElement(SUBMIT, MOD_USER, 'Update ...');
    $form->addElement(SUBMIT, "removeuser", 'DELETE USER');

    return $form;
}

function createAddForm() {
    global $statsDB;
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm(USER_MGT,'POST','?' . $_SERVER[QUERY_STRING]);

    $form->addElement('text', SIGNUM, "Signum:", ELEMENT_SIZE_50);
    $form->addElement('password', PASS_KEY, "Password:", ELEMENT_SIZE_50);
    $form->addElement(SUBMIT, "adduser", 'Create ...');

    return $form;
}

function createUser($signum) {
    global $AdminDB,$debug;

    echo "<b>Creating user ... ";

    $editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $editDB->exec("use $AdminDB"); // Don't use db.table, breaks replication

    $encryptedPasswd = md5($_REQUEST[PASS_KEY]);

    $alreadyExists = FALSE;
    if ( $editDB->queryRow("SELECT COUNT(*) FROM ddpusers WHERE signum = '$signum'")[0] > 0 ){
        $alreadyExists = TRUE;
    }

    if ( $alreadyExists ) {
        echo "Error $signum already exists</b>\n";
    } else {
        $sql = sprintf("INSERT INTO ddpusers (signum,passwd,mysql_passwd) VALUES ('%s','%s',PASSWORD('%s'))",
                       $signum,$encryptedPasswd,$_REQUEST[PASS_KEY]);
        $editDB->exec($sql);
        echo "Done</b>\n";
        mgtlog("User $signum created");
    }
}

function grantDBAccess( $DBName, $signum, $data, $replDB_List ) {
    global $debug;
    $sql = "GRANT SELECT ON " . $DBName . ".* TO '" . $signum . "'@'%' IDENTIFIED BY PASSWORD '" . $data[MYSQL_KEY] . "'";
    foreach ( $replDB_List as $replDB ) {
        $replDB->exec($sql);
    }
    if ( $debug ) {
        echo "<br/>* added grant privilege to repl DB";
    }
    mgtlog("Granted privileges to $signum for repl DB");
}

function revokeDBAccess( $signum, $replDB_List ) {
    global $debug;
    $sql = "DROP USER IF EXISTS'" . $signum . "'";
    foreach ( $replDB_List as $replDB ) {
        $replDB->exec($sql);
    }
    if ( $debug ) {
    echo "* revoked privileges from repl DB";
    }
    mgtlog("Revoked privileges from $signum for repl DB");
}

function getReplDbList( $editDB ) {
    $editDB->query("SELECT host, port, cert FROM db_replicas");
    $replDB_List = array();
    while ($row = $editDB->getNextNamedRow()) {
        if ( $debug ) {
            echo "<br/>creating repldb for " . $row['host'] . ":" . $row['port'] . "<br/>\n";
        }
        # from http://php.net/manual/en/function.mysql-connect.php
        # whenever you specify "localhost" or "localhost:port" as server, the mysql client library will override this
        # and try to connect to a local socket (named pipe on windows). if you want to use tcp/ip, use "127.0.0.1" instead of "localhost".
        # if the mysql client library tries to connect to the wrong local socket,
        # you should set the correct path as in your php configuration and leave the server field blank.
        #
        # so make use we use the ip address
        $replDB_List[] = new StatsDB(
            StatsDB::ACCESS_REPLICATION,
            gethostbyname($row['host']),
            $row['port'],
            $row['cert']
        );
    }
    return $replDB_List;
}

function modifyUser($data) {
    global $AdminDB,$debug,$DBName;

    $signum = $data[SIGNUM];

    echo "<b>Editing user ... ";
    // update database
    if ( $debug ) {
        echo "<br/>creating editDB";
    }

    $editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $editDB->exec("use $AdminDB"); // Don't use db.table, breaks replication
    if ( $debug ) {
        echo "<br/>creating replDB";
    }

    $replDB_List = getReplDbList( $editDB );

    if ( ! issetURLParam(USE_SQL) ) {
        $use_sql  = "false";
        $postUseSQL = false;
    } else {
        $use_sql  = "true" ;
        $postUseSQL = true;
    }

    $actions = "";

    # Handle changed password.
    $pass = requestValue(PASS_KEY);
    if ( $pass != "" ) {
        $sql = sprintf("UPDATE ddpusers SET passwd = '%s', mysql_passwd = PASSWORD('%s') WHERE signum = '%s'",
                       md5($_REQUEST[PASS_KEY]), $_REQUEST[PASS_KEY], $signum);
        $editDB->exec($sql);

        # Update the passwd in data[] for further operations to work correctly.
        $sql = "SELECT mysql_passwd FROM ddpusers WHERE signum = '" . $signum . "'";
        $sqlPasswd = $editDB->queryRow($sql)[0];
        $data[MYSQL_KEY] = $sqlPasswd;

        # If sql access hasn't changed, and the user has sql access update the DB grants.
        if ( $postUseSQL == $data[USE_SQL] && $data[USE_SQL] > 0) {
            revokeDBAccess( $signum, $replDB_List );
            grantDBAccess( $DBName, $signum, $data, $replDB_List );
        }

        $actions = "<li>Password successfully updated</li>" . $actions;
        unset($_POST[PASS_KEY]);
        mgtlog("Changed password for $signum");
    }

    # Handle changed user groups.
    $newAccessGroups = array();
    if ( issetURLParam(ACCESS) ) {
        foreach($_POST[ACCESS] as $key => $value) {
            array_push($newAccessGroups, $key);
        }
    }
    $oldAccessGroups = getUserAccessGroups($data[SIGNUM]);
    sort($newAccessGroups);
    sort($oldAccessGroups);
    if ( $newAccessGroups != $oldAccessGroups ) {
        $delete_sql = "DELETE FROM " . $AdminDB . ".ddpuser_group where signum = '" . $data[SIGNUM] . "'";
        $editDB->exec($delete_sql);
        $insert_sql = "INSERT INTO " . $AdminDB . ".ddpuser_group(signum, grp) VALUES ";
        $loopCount = 0;
        foreach ( $newAccessGroups as $accessGroup ) {
            if ( $loopCount > 0 ) {
                $insert_sql = $insert_sql . ", ";
            }
            $insert_sql = $insert_sql . "('" . $data[SIGNUM] . "', '" . $accessGroup . "')";
            $loopCount++;
        }
        if ( $loopCount > 0 ) {
            $editDB->exec($insert_sql);
        }
        mgtlog("Changed access groups for $signum; Old groups: [" . implode(' ,', $oldAccessGroups)
               . "] New groups: [" . implode(' ,', $newAccessGroups) . "]");
        $actions = "<li>User access groups modified.</li>" . $actions;
    }

    # Handle changed Upgrade Email subscrition.
    $postGetUpgradeEmails = issetURLParam(GET_UPGRADE_EMAIL);
    if ( $postGetUpgradeEmails != $data[GET_UPGRADE_EMAIL] ) {
        $get_upgrade_emails = $postGetUpgradeEmails ? 'true' : 'false';
        $sql = "UPDATE ddpusers SET get_upgrade_emails = " . $get_upgrade_emails . " WHERE signum = '" . $signum . "'";
        $editDB->exec($sql);
        $actions = "<li>Upgrade email subscription modified.</li>\n" . $actions;
        mgtlog("Upgrade email subscription for $signum modified");
    }

    # Handle changed SQL access.
    if ( $postUseSQL != $data[USE_SQL] ) {
        mgtlog("changing DB access permissions for '" . $signum . "' from '" . $data[USE_SQL] . "' to '" . $postUseSQL . "'");
        if ( $debug ) {
            echo "<br/>changing DB access permissions for '" . $signum . "' from '" . $data[USE_SQL] . "' to '" . $postUseSQL . "'";
        }
        $sql = "UPDATE ddpusers SET use_sql = " . $use_sql . " WHERE signum = '" . $signum . "'";
        $editDB->exec($sql);
        if ( $debug ) {
            echo "<br/>* updated admin db";
        }
        mgtlog("* updated admin db");
        if ( $postUseSQL ) {
            grantDBAccess( $DBName, $signum, $data, $replDB_List );
        } else {
            revokeDBAccess( $signum, $replDB_List );
        }
        $actions = "<li>SQL access modified.</li>\n" . $actions;
    }

    if ( $actions != '') {
        echo "<ul>";
        echo $actions;
        echo "</ul></b>\n";
    } else {
        echo "Nothing to do!</b>";
    }
}

function deleteUser($signum) {
    global $AdminDB;

    $delUser = "DELETE FROM ddpusers WHERE signum = '$signum';";
    $countGroups = "SELECT COUNT(*) FROM ddpuser_group where signum = '$signum';";
    $delGroups = "DELETE FROM ddpuser_group where signum = '$signum';";
    $delEmails = "DELETE FROM ddp_alert_subscriber_emails where signum = '$signum';";
    $delSubs = "DELETE FROM ddp_alert_subscriptions where signum = '$signum';";
    $delDisplay = "DELETE FROM ddp_report_display where signum = '$signum';";

    $editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $editDB->exec("use $AdminDB"); // Don't use db.table, breaks replication

    $replDB_List = getReplDbList( $editDB );

    revokeDBAccess( $signum, $replDB_List );

    $rmUser = deleteFromTable( $delUser, $editDB );
    if ( $rmUser == 1 ) {
        $cnt = $editDB->exec($countGroups);
        if ( $cnt > 0) {
            $rmGrp = deleteFromTable( $delGroups, $editDB );
            if ( $rmGrp > 0 ) {
                echo addLineBreak();
                $deletedGroups = "User data deleted: $rmGrp Groups for user($signum) have been deleted!";
                echo $deletedGroups;
                mgtlog($deletedGroups);
            }
        }

        deleteFromTable($delEmails, $editDB );
        deleteFromTable($delSubs, $editDB );
        deleteFromTable($delDisplay, $editDB );
    } else {
        mgtlog("Failed to remove user: $signum");
    }
    createPage();
}

function deleteFromTable( $query, $editDB ) {
    $delete = $editDB->exec($query);
    if ( $delete > 0 ) {
        $msg = "User data deleted: $query";
        echo addLineBreak();
        echo $msg;
        mgtlog($msg);
    }
    return $delete;
}

function confirmDeleteForm($signum) {
    global $statsDB;
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm(USER_MGT,'POST','?' . $_SERVER[QUERY_STRING]);

    $form->addElement(SUBMIT, "deleteuser", "Confirm Deletion of user : $signum!");
    $form->addElement(SUBMIT, "canceldeletion", "Cancel Deletion of user : $signum!");

    return $form;
}

function checkValidityOfUsersForm() {
    $form = new HTML_QuickForm( USER_MGT, 'POST', '?' . fromServer(QUERY_STRING) );
    $form->addElement(SUBMIT, "isUserValid", "Check validity of users?");

    return $form;
}

function getAccessList() {
    global $statsDB, $AdminDB;

    $list = array();

    $sql = "SELECT * FROM " . $AdminDB . ".ddpuser_group";
    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow() ) {
        $list[] = $row;
    }

    $listBySignum = array();

    foreach ( $list as $item ) {
        $sig = $item[SIGNUM];
        $grp = $item['grp'];
        if ( isset($listBySignum[$sig]) ) {
            $listBySignum[$sig] = $listBySignum[$sig] . ", " . $grp;
        } else {
            $listBySignum[$sig] =  $grp;
        }
    }

    return $listBySignum;
}

function createPage() {
    // We're displaying the add user form and the list of existing users
    echo "<H2>Add User</H2>\n";
    $form = createAddForm();
    $form->display();
    echo "<H2>Existing Users</H2>\n";
    $data = getUserList();
    $data = addLink($data);
    drawTable($data);

    echo addLineBreak();

    $form = checkValidityOfUsersForm();
    $form->display();
}

echo "<h1>User Management </h1>\n";
if ( fromServer('REQUEST_METHOD') == "GET" ) {
    if (issetURLParam(SIGNUM)) {
        $signum = $_REQUEST[SIGNUM];
        // We're display the mod user form
        $data = getUserData($signum);
        $form = createModifyForm($data);
        $form->display();
    } else {
        createPage();
    }
} else {
    $signum = '';
    if ( issetURLParam(SIGNUM) ) {
        $signum = strtolower(requestValue(SIGNUM));
    }
    if ( issetURLParam(MOD_USER) ) {
        // We're processing a moduser
        $data = getUserData($signum);
        if ( ! is_null($data) ) {
            modifyUser($data);

            $form = createModifyForm($data);
            $form->display();
        }
    } elseif ( issetURLParam('adduser') ) {
        createUser($signum);
        unset($_POST[PASS_KEY]);

        $data = getUserData($signum);
        $form = createModifyForm($data);
        $form->display();
    } elseif ( issetURLParam('deleteuser') ) {
        $data = getUserData($signum);
        if ( ! is_null($data) ) {
            deleteUser($signum);
        }
    } elseif ( issetURLParam('removeuser') ) {
        $form = confirmDeleteForm($signum);
        $form->display();
    } elseif ( issetURLParam("canceldeletion") ) {
        createPage();
    } elseif ( issetURLParam('isUserValid') ) {
        $data = getUserList();
        $ldapCon = getLdapConn();
        foreach ($data as $key => $value) {
            $value['in_ldap'] = !is_null(getUserDataFromLdap($value[SIGNUM], $ldapCon));
            $data[$key] = $value;
        }
        ldap_close($ldapCon);
        $data = addLink($data);
        drawTable($data);
    }
}


include "../php/common/finalise.php";
