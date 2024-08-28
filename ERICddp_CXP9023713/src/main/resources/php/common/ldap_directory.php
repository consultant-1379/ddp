<?

$ldapUserSearchBase = 'OU=ID,OU=Data,DC=ericsson,DC=se';

function getLdapConn() {
    global $ddpUseUser1;
    $ldapURL = 'ldaps://sesbiwegad0001.ericsson.se/';

    $ddpBindUser = 'CN=' . $_SERVER['DDP_LDAP_USERID'] . ',OU=CA,OU=SvcAccount,OU=P001,OU=ID,OU=Data,DC=ericsson,DC=se';
    $ddpBindPw = $_SERVER['DDP_LDAP_PASSWD'];

    $ldap_conn = ldap_connect($ldapURL);
    if ( ! $ldap_conn ) {
        error_log("Failed to connect to LDAP server");
        return NULL;
    }

    if (! ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
        error_log("Failed to set LDAP protocol version to 3");
        return NULL;
    }

    if ( ! ldap_bind($ldap_conn,
                     $ddpBindUser,
                     $ddpBindPw) ) {
        /* If the bind fails it logs to the error_log */
        ldap_close($ldap_conn);
        return NULL;
    }

    return $ldap_conn;
}

function getLdapUserInfo($ldap_conn,$user,$ldap_fields) {
    global $ldapUserSearchBase, $debug;

    return getLdapUserInfoWithFilter($ldap_conn, "CN=$user", $ldap_fields);
}

function getLdapUserInfoWithFilter($ldapConn, $ldapFilter, $ldapFields) {
    global $ldapUserSearchBase, $debug;

    $searchResults = ldap_search($ldapConn, $ldapUserSearchBase, $ldapFilter, $ldapFields );
    $entry = ldap_get_entries($ldapConn, $searchResults);
    if ( $entry == FALSE ) {
        return NULL;
    }
    debugMsg("getLdapUserInfoWithFilter: ldapFilter=$ldapFilter entry=", $entry);

    if ( $entry["count"] != 1 ) {
        return NULL;
    }

    $results = array();
    foreach ( $ldapFields as $ldap_field ) {
        if (array_key_exists($ldap_field, $entry[0])) {
            $results[$ldap_field] = $entry[0][$ldap_field][0];
        }
    }

    debugMsg("getLdapUserInfoWithFilter: ldapFilter=$ldapFilter results=", $results);

    return $results;
}

?>
