<?php

// Disable auth so we can include init.php without triggering the default
// auth behaviour
$NOAUTH = true;
$UI = false;
require_once "init.php";

$auth_user = strtolower(requestValue('user'));
$site = requestValue('site');

debugMsg("auth_user=$auth_user, site=$site");

// Now switch back on auth so the user in $auth_user will be checked
$NOAUTH = false;
// We also need to change the _SERVER['REMOTE_ADDR'] to force a proper
// check as isAccessAllowed will return true when the request
// comes from local host
$_SERVER['REMOTE_ADDR'] = ''; // NOSONAR

$accessAllowed = isAccessAllowed();

$result = array( array( 'site' => $site, 'allowed' => $accessAllowed));

header('Content-Type: application/json');
echo json_encode($result);
