<?php
require_once '/usr/share/php-composer/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

function getOAuth2Location() {
    $_SESSION['oauth_state'] = hash('sha256', session_id());
    $_SESSION['postlogin'] = fromServer('REQUEST_URI');

    $location = fromServer('DDP_OAUTH2_AUTH_ENDPOINT') . '?' . http_build_query([
        'response_type' => 'code',
        'client_id' => fromServer('DDP_OAUTH2_CLIENT_ID'),
        'redirect_uri' => getOAuthCallbackUri(),
        'state' => $_SESSION['oauth_state'],
        'scope' => 'openid profile',
    ]);

    return $location;
}

function getOAuthCallbackUri() {
    global $php_webroot;
    return sprintf(
        "https://%s%s/common/oauth_callback.php",
        fromServer('HTTP_HOST'),
        $php_webroot
    );
}

function getUserFromJWT($jwt) {
    $secret = fromServer('DDP_CLIENT_SECRET');

    $keys_response = sendRequest(fromServer('DDP_OAUTH2_KEYS_ENDPOINT'));
    debugMsg("getUserFromJWT: keys_response", $keys_response);
    if ( $keys_response['errno'] ) {
        error_log("getUserFromJWT Failed to get OAuth keys: " . $keys_response['error']);
        return null;
    }
    $jwks = json_decode($keys_response['content'], true);

    try {
        $decoded = JWT::decode($jwt, JWK::parseKeySet($jwks));
    } catch (UnexpectedValueException $e) {
        error_log("getUserFromJWT decode failed " + $e);
        return null;
    }

    $decoded = json_decode(json_encode($decoded), true);
    debugMsg("getUserFromJWT: decoded", $decoded);

    $_SESSION['jwt'] = $jwt;
    $_SESSION['exp'] = $decoded['exp'];

    return $decoded['name'];
}
