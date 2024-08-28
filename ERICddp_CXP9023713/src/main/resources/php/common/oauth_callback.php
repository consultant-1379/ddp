<?php

require_once "functions.php";
require_once "oauth2.php";

if (!isset($_SESSION)) {
    session_start();
}

if ( requestValue('state') != $_SESSION['oauth_state'] ) {
    die("Invalid state");
}

// Need this for getOAuthCallbackUri to work
$php_webroot = dirname(dirname(fromServer(PHP_SELF)));

$response = sendRequest(
    fromServer('DDP_OAUTH2_TOKEN_ENDPOINT'),
    [
        'code' => requestValue('code'),
        'grant_type' => 'authorization_code',
        'client_id' => fromServer('DDP_OAUTH2_CLIENT_ID'),
        'client_secret' => fromServer('DDP_CLIENT_SECRET'),
        'redirect_uri' => getOAuthCallbackUri()
    ]
);
if ( $response['errno'] == 0 ) {
    $reply = json_decode($response['content'], true);

    $username = getUserFromJWT($reply['id_token']);
    if ( is_null($username) ) {
        die("Invalid id_token");
    }

    $_SESSION['username'] = strtolower($username);
    $redirect = $_SESSION['postlogin'];
    header("Location: " . $_SESSION['postlogin']);
} else {
    die("Failed to get token: " . $response['error'] . ", " . $response['content']);
}
