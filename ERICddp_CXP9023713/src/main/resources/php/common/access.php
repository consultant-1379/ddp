<?php

require_once $php_common . '/ldap_directory.php';
require_once $php_common . '/oauth2.php';
require_once $php_common . '/functions.php';

$grpSearchBase = 'OU=GRP,OU=Data,DC=ericsson,DC=se';

$NESTED_GROUPS_CACHE_TIME = 7200;

$nestedGroupsFile = "/data/tmp/grps.txt";
const MEMBER = "member";
const RESULT = 'result';
const ERROR_MSG = 'error_msg';

$debugAccess = 0;
if ( isset($_REQUEST['debugaccess']) ) {
    $debugAccess = $_REQUEST['debugaccess'];
}

function debugAccessMsg($msg, $data = null, $level = 1) {
    global $debugAccess;

    if ( $debugAccess >= $level ) {
        $dataStr ="";
        if ( ! is_null($data) ) {
            $dataStr = " " . print_r($data, true);
        }
        printf("<pre>%s: %s%s</pre>\n", date("H:i:s"), $msg, $dataStr);
    }
}

function isLocalRequest() {
    return fromServer('HTTP_HOST') == '127.0.0.1' || fromServer('HTTP_HOST') == 'localhost';
}

function isAuthenticationRequired() {
    global $NOAUTH;

    return !(isset($NOAUTH) || isLocalRequest());
}

function requireHTTPS() {
    global $HTTP_Allowed;

    // HTTPS not required for internal requests
    if ( isLocalRequest() ) {
        return;
    }

    // HTTP access is disabled unless explictly enabled
    if ( fromServer('REQUEST_SCHEME') == 'http' ) {
        $httpsLink = "https://" . fromServer('HTTP_HOST') . fromServer('REQUEST_URI');
        echo <<<EOT
<H1>HTTP access is no longer supported</H1>
<p>Please use <a href="$httpsLink">HTTPS</a> instead and use your Corporate SIGNUM and Corporate password to login</p>
EOT;

        exit;
    }
}

function isSessionExipred() {
    global $useOAuth2;

    // If we're using OAuth2 and the session is exipred
    return (isset($useOAuth2) && $useOAuth2) &&  ((!isset($_SESSION['exp'])) || $_SESSION['exp'] < time());
}

function authenticateFromHeader() {
    // Check if we've an Authorization: Bearer header
    $authorizationHeader = fromServer('HTTP_AUTHORIZATION');
    if ( (! is_null($authorizationHeader)) && preg_match('/Bearer\s(\S+)/', trim($authorizationHeader), $matches)) {
        $idTokenStr = $matches[1];
        debugAccessMsg("isAuthenticated found Bearer token $idTokenStr");
        $username = getUserFromJWT($idTokenStr);
        debugAccessMsg("isAuthenticated username", $username);
        if ( is_null($username) ) {
            die("Invalid id_token");
        }
        $_SESSION['username'] = strtolower($username);
        return true;
    } else {
        return false;
    }
}

function isAuthenticated() {
    global $NOAUTH, $debugAccess;

    requireHTTPS();

    // Make sure we have a session
    if (!isset($_SESSION)) {
        session_start();
    }
    debugAccessMsg("isAuthenticated _SERVER", $_SERVER, 2); // NOSONAR
    debugAccessMsg("isAuthenticated _SESSION", $_SESSION);

    $authResult = null;
    if ( ! isAuthenticationRequired() ) {
        $authResult = array( RESULT => true );
    } elseif (isset($_SESSION['username'])) {
        // If username is already set in the SESSION then we already authenticated
        if ( isSessionExipred() ) {
            // Unset all of the session variables.
            $_SESSION = array();
            $authResult = array( 'result' => false, 'error_msg' => 'token expired');
        } else {
            $authResult = array( RESULT => true );
        }
    } elseif ( authenticateFromHeader() ) {
        $authResult = array( RESULT => true );
    } elseif ( is_null(fromServer('PHP_AUTH_USER')) ) {
        // If we haven't got a userid yet then return FALSE to request it
        debugAccessMsg("isAuthenticated PHP_AUTH_USER not set");
        $authResult = array( 'result' => false, 'error_msg' => 'No User Id');
    }

    if ( is_null($authResult) ) {
        $authResult = ldapAuthenicate();
        debugAccessMsg("isAuthenticated result", $authResult);
        if ( $authResult[RESULT] ) {
            $usr = fromServer('PHP_AUTH_USER');
            $_SESSION['username'] = strtolower( $usr );
        } else {
            error_log("Failed authenication for " . fromServer('PHP_AUTH_USER') . ": " . $authResult[ERROR_MSG]);
        }
    }

    return $authResult;
}

function showFailedAuth() {
    global $php_webroot, $useOAuth2;

    if ( isset($useOAuth2) && $useOAuth2 ) {
        $location = getOAuth2Location();
    } else {
        $location = sprintf(
            "https://%s%s/common/loginPage.php?location=%s",
            fromServer('HTTP_HOST'),
            $php_webroot,
            urlencode(fromServer('REQUEST_URI'))
        );
    }
    header("Location: $location");
}

function isAccessAllowed() {
    global $site, $debugAccess,$AdminDB,$NOAUTH,$auth_user, $useLocalAuth;

    if ( $debugAccess ) { echo "<pre>isAccessAllowed NOAUTH=$NOAUTH</pre>\n"; }

    # If we don't have Authentication, then don't do access control
    if ( $NOAUTH ) {
        return true;
    }

    // If we using local authentication, then all users allowed access
    if ( isset($useLocalAuth) && $useLocalAuth ) {
        return true;
    }

    # If the request if from localhost, then access is always allowed
    if ( $debugAccess ) { echo "<pre>isAccessAllowed _SERVER\n"; print_r($_SERVER); echo "</pre>\n"; }
    if ( $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR'] ) {
        return TRUE;
    }

    # No site set, (e.g. on the site_index.php page), then access is allowed
    if ( ! isset($site) || $site == "" ) {
        return TRUE;
    }

    $siteGroups = array();
    $statsDB = new StatsDB();
    $statsDB->query("SELECT grp FROM $AdminDB.site_accessgroups, sites WHERE $AdminDB.site_accessgroups.siteid = sites.id AND sites.name = '$site'");
    while($row = $statsDB->getNextRow()) {
        $siteGroups[] = $row[0];
    }
    if ( $debugAccess ) { echo "<pre>isAccessAllowed siteGroups\n"; print_r($siteGroups); echo "</pre>\n"; }

    if ( $debugAccess ) { echo "<pre>isAccessAllowed _SESSION\n"; print_r($_SESSION); echo "</pre>\n"; }

    # Add default groups
    $defaultAllowedGroups = getDefaultGroups();
    foreach ( $defaultAllowedGroups as $group ) {
        $siteGroups[] = $group;
    }

    # The memberOf attribute only returns groups that the user is directly a member of, i.e
    # if John is a memberOf GroupA and GroupA is a memberOf of GroupB, John will not have
    # memberOf attribute point at GroupB. So we use getNestedGroups to return a map of
    # group -> all sub groups
    # Now iterate through the group map and add any groups that are nested groups of the allowed groups
    $groupMap = getNestedGroups();
    foreach ( $siteGroups as $group ) {
        if ( array_key_exists($group,$groupMap) ) {
            foreach ( $groupMap[$group] as $nestedGroup ) {
                $siteGroups[] = $nestedGroup;
            }
        }
    }
    if ( $debugAccess ) { echo "<pre>isAccessAllowed siteGroups after nested\n"; print_r($siteGroups); echo "</pre>\n"; }

    /* If refreshacl is in the URL don't use the cookie */
    if ( isset($_SESSION['memberof']) && (isset($_REQUEST["refreshacl"]) == FALSE) ) {
        /* Get the cached groups from the session */
        $userGroups = array();
        foreach ( explode(",",$_SESSION['memberof']) as $group ) {
            $userGroups[$group] = 1;
        }
        if ( $debugAccess ) { echo "<pre>isAccessAllowed from _SESSION userGroups\n"; print_r($userGroups); echo "</pre>\n"; }
    } else {
        $userGroups = getGroupsForUser($auth_user,$statsDB,$groupMap);
        if ( is_null($userGroups) ) {
            return FALSE;
        }

        /* Cache the result into the session */
        $groupsStr = implode(",",array_keys($userGroups));
        if ( $debugAccess ) { echo "<pre>isAccessAllowed groupStr=$groupsStr</pre>\n"; }
        $_SESSION['memberof'] = $groupsStr;
    }

    /* Now iterate through the groups for the site and see if the user has that group */
    $result = FALSE;
    foreach ( $siteGroups as $siteGroup ) {
        if ( $debugAccess ) { echo "<pre>isAccessAllowed siteGroup = '$siteGroup'</pre>\n"; }
        if ( array_key_exists($siteGroup,$userGroups) ) {
            if ( $debugAccess ) { echo "<pre>isAccessAllowed match found</pre>\n"; }
            $result = TRUE;
            break;
        }
    }

    if ( $debugAccess ) { echo "<pre>isAccessAllowed result=$result</pre>\n"; }

    if ( $result == FALSE ) {
        error_log("$auth_user does not have authorization to access $site");
    }

    return $result;
}

function getAllowedGroups() {
    global $site, $AdminDB, $debugAccess;

    $siteGroups = array();
    $statsDB = new StatsDB();
    $statsDB->query("SELECT grp FROM $AdminDB.site_accessgroups, sites WHERE $AdminDB.site_accessgroups.siteid = sites.id AND sites.name = '$site'");
    while($row = $statsDB->getNextRow()) {
        $siteGroups[] = $row[0];
    }

    if ( $debugAccess ) { echo "<pre>getAllowedGroups: "; print_r($siteGroups); echo "</pre>\n"; }
    return $siteGroups;
}

function ldapAuthenicate() {
    global $ldapURL,$ddpBindUser,$ddpBindPw,$ldapUserSearchBase,$debugAccess;

    $ldap_conn = getLdapConn();
    if ( is_null($ldap_conn) ) {
        return array(RESULT => false, ERROR_MSG => 'Failed to connect to LDAP Server');
    }

    # Now we get the DN for the user
    $query = "(&(CN=" . $_SERVER['PHP_AUTH_USER'] . ")(objectclass=user))";
    $search_results = ldap_search($ldap_conn, $ldapUserSearchBase, $query, array("dn") );
    $entries = ldap_get_entries($ldap_conn,$search_results);

    if ( $entries["count"] != 1 ) {
        ldap_close($ldap_conn);
        return array(RESULT => false, ERROR_MSG => 'Could not find user in LDAP directory');
    }

    $userDN = $entries[0]["dn"];
    # Bind with the users dn and the given password to validate the password
    if ( ldap_bind($ldap_conn, $userDN, $_SERVER['PHP_AUTH_PW'] ) ) {
        $result = array( RESULT => true );
    } else {
        $result = array(RESULT => false, ERROR_MSG =>  'Password validation failed');
    }

    ldap_close($ldap_conn);

    if ( $debugAccess ) { echo "<pre>ldapAuthenicate result=$result dn=$userDN</pre>\n"; }

    return $result;
}

function isValidGroup($group) {
    global $grpSearchBase;

    $ldap_conn = getLdapConn();
    if ( is_null($ldap_conn) ) {
        return FALSE;
    }

    $search_results = ldap_search($ldap_conn, $grpSearchBase, "CN=$group", array("dn") );
    $entries = ldap_get_entries($ldap_conn,$search_results);

    if ( $entries["count"] == 1 ) {
        return TRUE;
    } else {
        return FALSE;
    }
}

function getGroupsForUser($user,$statsDB,$groupMap) {
    global $debugAccess,$AdminDB,$ldapURL,$ddpBindUser,$ddpBindPw,$ldapUserSearchBase;

    $statsDB->query("SELECT DISTINCT(grp) FROM $AdminDB.site_accessgroups WHERE grp != ''");
    $usedAccessGroups = array();
    while($row = $statsDB->getNextRow()) {
        $usedAccessGroups[$row[0]] = 1;
    }
    # Add default groups
    $defaultAllowedGroups = getDefaultGroups();
    foreach ( $defaultAllowedGroups as $group ) {
        $usedAccessGroups[$group] = 1;
    }
    # Add any sub group of the used accessgroups/default groups
    foreach ( array_keys($usedAccessGroups) as $group ) {
        if ( array_key_exists($group,$groupMap) ) {
            foreach ( $groupMap[$group] as $nestedGroup ) {
                $usedAccessGroups[$nestedGroup] = 1;
            }
        }
    }
    if ( $debugAccess ) { echo "<pre>getGroupsForUser: usedAccessGroups\n"; print_r($usedAccessGroups); echo "</pre>\n"; }

    $ldap_conn = getLdapConn();
    if ( is_null($ldap_conn) ) {
        return NULL;
    }

    $search_results = ldap_search($ldap_conn, $ldapUserSearchBase, "CN=$user", array("memberOf") );
    $entries = ldap_get_entries($ldap_conn,$search_results);
    ldap_close($ldap_conn);

    if ( $entries["count"] != 1 ) {
        error_log("Failed to find $user in to LDAP directory");
        return NULL;
    }
    if ( $debugAccess ) { echo "<pre>getGroupsForUser: entries\n"; print_r($entries); echo "</pre>\n"; }

    $groupsForUser = array();
    foreach ( $entries[0]["memberof"] as $ldapGroup ) {
        if ( $debugAccess ) { echo "<pre>getGroupsForUser: processing ldap group $ldapGroup</pre>\n"; }
        if ( substr( $ldapGroup, 0, 2 ) === "CN" ) {
            $grpParts = explode(",",$ldapGroup);
            list($dummy,$group) = explode("=",$grpParts[0]);
            if ( $debugAccess ) { echo "<pre>getGroupsForUser: processing group=$group</pre>\n"; }
            if ( array_key_exists($group,$usedAccessGroups) ) {
                $groupsForUser[$group] = 1;
            }
        }
    }

    if ( $debugAccess ) { echo "<pre>getGroupsForUser: groupsForUser\n"; print_r($groupsForUser); echo "</pre>\n"; }
    return $groupsForUser;
}

function getNestedGroups() {
    global $nestedGroupsFile,$NESTED_GROUPS_CACHE_TIME,$debugAccess;

    $useFileCache = FALSE;
    if ( (!isset($_REQUEST["refreshacl"])) && file_exists($nestedGroupsFile) ) {
        $fileAge = time() - filectime($nestedGroupsFile);
        if ( $debugAccess ) { echo "<pre>getNestedGroups: fileAge=$fileAge</pre>\n"; }
        if ( $fileAge < $NESTED_GROUPS_CACHE_TIME ) {
            $useFileCache = TRUE;
        }
    }
    if ( $debugAccess ) { echo "<pre>getNestedGroups: useFileCache=$useFileCache</pre>\n"; }

    if ( $useFileCache ) {
        $groupMap = unserialize( file_get_contents($nestedGroupsFile) );
    } else {
        # We don't want other pages executing at the same time triggering
        # an update of the nestedGroupsFile, so update the ctime
        if ( file_exists($nestedGroupsFile) ) {
            touch($nestedGroupsFile);
        }

        $ldapGroupMap = getNestedGroupsFromLDAP();
        if ( !is_null($ldapGroupMap) ) {
            $groupMap = array();
            foreach ( $ldapGroupMap as $groupDN => $subGroups ) {
                $groupCN = getCnFromDn($groupDN);
                $subGroupCNs = array();
                foreach ( array_keys($subGroups) as $subGroupDN ) {
                    $subGroupCNs[] = getCnFromDn($subGroupDN);
                }
                $groupMap[$groupCN] = $subGroupCNs;
            }
            file_put_contents( $nestedGroupsFile, serialize($groupMap) );
        }
    }

    if ( $debugAccess ) { echo "<pre>getNestedGroups: groupMap"; print_r($groupMap); echo "</pre>\n"; }

    return $groupMap;
}

function getNestedGroupsFromLDAP() {
    global $debugAccess,$AdminDB,$ldapURL,$ddpBindUser,$ddpBindPw,$grpSearchBase;

    # Get custom groups
    $statsDB = new StatsDB();
    $statsDB->query("SELECT DISTINCT(grp) FROM $AdminDB.site_accessgroups");
    $usedAccessGroups = array();
    while($row = $statsDB->getNextRow()) {
        $usedAccessGroups[$row[0]] = 1;
    }
    # Add default groups
    $defaultAllowedGroups = getDefaultGroups();
    foreach ( $defaultAllowedGroups as $group ) {
        $usedAccessGroups[$group] = 1;
    }

    $ldap_conn = getLdapConn();
    if ( is_null($ldap_conn) ) {
        return NULL;
    }

    $ldapGroupMap = array();
    foreach ( array_keys($usedAccessGroups) as $group ) {
        $search_results = ldap_search($ldap_conn, $grpSearchBase, "CN=$group", array("dn") );
        $entries = ldap_get_entries($ldap_conn,$search_results);
        if ( $entries["count"] == 1 ) {
            if ( $debugAccess ) { echo "<pre>getNestedGroupsFromLDAP: group=$group entries\n"; print_r($entries[0]); echo "</pre>\n"; }
            $groupDN = $entries[0]['dn'];
            $subGroups = array();
            addChildGroups($groupDN,$ldap_conn,$subGroups);
            $ldapGroupMap[$groupDN] = $subGroups;
        } else {
            error_log("getNestedGroups: Failed to find group $group in to LDAP directory");
        }
    }

    ldap_close($ldap_conn);
    if ( $debugAccess ) { echo "<pre>getNestedGroupsFromLDAP: ldapGroupMap: "; print_r($ldapGroupMap); echo "</pre>"; }
    return $ldapGroupMap;
}

function addChildGroups($group,$ldap_conn,&$childGrps) {
    global $debugAccess,$grpSearchBase;

    $searchresults = ldap_read($ldap_conn, $group, "(objectclass=*)", array(MEMBER) );
    $entries = ldap_get_entries($ldap_conn, $searchresults);

    if ( $entries["count"] != 1 ) {
        error_log("addChildGroups: Failed to find group $group in to LDAP directory");
        return;
    }

    if ( ! array_key_exists(MEMBER, $entries[0]) ) {
        debugMsg("addChildGroups: No members attribute in $group");
        return;
    }

    if ( $debugAccess ) { echo "<pre>addChildGroups: group=$group entries\n"; print_r($entries); echo "</pre>\n"; }

    $grpSearchBaseLen = strlen($grpSearchBase);
    foreach ( $entries[0][MEMBER] as $member ) {
        # If the member is a group
        if ( substr($member, -$grpSearchBaseLen) === $grpSearchBase ) {
            if ( array_key_exists($member,$childGrps) ) {
                error_log("addChildGroups: Nested group $groupCN in group $group is already in $allGroups");
            } else {
                $childGrps[$member] = 1;
                addChildGroups($member,$ldap_conn,$childGrps);
            }
        }
    }
}

function getCnFromDn($dn) {
    $parts = explode(",",$dn);
    list($dummy,$cn) = explode("=",$parts[0]);
    return $cn;
}

function getDefaultGroups() {
    global $php_root, $serverDefaultGroups;

    include $php_root . "/common/access_defaultgroups.php";

    if ( isset($serverDefaultGroups) ) {
        $defaultAllowedGroups = array_merge($defaultAllowedGroups,$serverDefaultGroups);
    }

    return $defaultAllowedGroups;
}

