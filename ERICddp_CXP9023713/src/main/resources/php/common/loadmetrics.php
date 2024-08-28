<?php

require_once "init.php";

$doLoad = requestValue('doload');
if ( ! is_null($doLoad) && $doLoad === '1' ) {
    $userInfo = getLdapUserInfo(getLdapConn(), $auth_user, array("mail"));
    if ( is_null($userInfo) ) {
        echo "<p>ERROR: No match found for ". $auth_user . "</br>";
    } else {
        $email = $userInfo['mail'];
        if ( callGenericPhpToRootWrapper('loadMetrics', "$site $dir $email") == 0 ) {
            echo "<p>Loading job created. You will receive an email once loading has been completed</p>\n";
        } else {
            echo "<p>ERROR: Failed to start job to load metrics</p>\n";
        }
    }
} else {
    $link = makeLinkForURL(makeSelfLink() . "&doload=1", "here");
    echo <<<EOS
<p>Data has not been loaded for this date. Click $link if you want to load data for this date.
This will trigger a job in DDP that will load the data.
<p>
EOS;
}

include_once PHP_ROOT . "/common/finalise.php";
