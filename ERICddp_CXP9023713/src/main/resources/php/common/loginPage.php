<?php

function showForm($errorMsg) {
    $debugAccess = 0;
    if ( isset($_REQUEST['debugaccess']) ) {
        $debugAccess = $_REQUEST['debugaccess'];
    }

    $location = "";
    if ( isset($_GET['location']) ) {
        $location = htmlspecialchars($_GET['location']);
    }

    if ( ! is_null($errorMsg) ) {
        $errorStr = '<div class="error">' . $errorMsg . "</div>\n";
    } else {
        $errorStr = "\n";
    }
?>

<link rel="stylesheet" href="./normalize.css" type="text/css">
<link rel="stylesheet" href="./loginStyle.css" type="text/css">

<body style="background-color: #F8F8F8;font-size: 10pt;">
    <div id="page_container" class="header">
        <div style="CLEAR: right;min-HEIGHT: 63px;HEIGHT: 63px;position: relative;top: 36px;font-size: 11px;">
            <div style="bottom: .2em;float: left;">
                <a title="Ericsson" href="http://www.ericsson.com/" target="_blank">
                    <div style="margin-right: 20px;background: url(../common/images/elogo.png);width: 71px;height: 63px;border-width: 0;"></div>
                </a>
            </div>
            <div style="margin-left: 0;margin-bottom: 7px;float: left;width: 338px;height: 24px;">
                <p style="font-family: EricssonCapitalTT;font-size: 30px">DDP LOGIN</p>
            </div>
        </div>
    </div>
    <div class="bottomgrad"></div>
    <section class="loginform cf">
        <form name="login" action="" method="POST" accept-charset="utf-8">
            <input type="hidden" name="location" value="<?=$location?>">
            <input type="hidden" name="debugaccess" value="<?=$debugAccess?>">

            <div style="margin-bottom: 30px;">
                <h2 style="font-size: 18px;font-weight: normal;color: #00285e;">Sign on using your user ID and password</h2>
            </div>

            <div style="width: 435px;margin-bottom: 20px;height: 29px;">
                <label style="line-height: 30px;float:left" for="usermail">User ID:</label>
                <input style="float:right;font-size: 9pt;width: 350px;margin-right: 0px;" type="text" name="userID" required>
            </div>
            <div style="width: 435px;margin-bottom: 10px;height: 29px;">
                <label style="line-height: 30px;float:left" for="password">Password:</label>
                <input style="float:right;font-size: 9pt;width: 350px;margin-right: 0px;" type="password" name="password" required>
            </div>

            <?=$errorStr?>

            <div style="width: 435px;margin-bottom: 50px;height: 29px;">
                <input id="loginBtn" type="submit" value="Login">
            </div>
            <div>
                <p>Log-in is only allowed for authorized users. If you are not an authorized user, please exit immediately. In accordance with requirements of data protection laws, we hereby inform you that personally identifiable information will be handled in log files for legal, security and costs reasons.</p>
            </div>
        </form>
    </section>
    <div class="bottomgrad"></div>
</body>

<?php
}

if ( $_SERVER["REQUEST_METHOD"] == "GET" ) {
    showForm(null);
} else {
    $_SERVER['PHP_AUTH_USER'] = $_POST['userID'];
    $_SERVER['PHP_AUTH_PW'] = $_POST['password'];

    # access.php need php_common set. As we're not using init.php
    # we have to set php_common here
    $php_common = dirname(__FILE__);
    require_once "./access.php";
    $authResult = isAuthenticated();
    if ( $authResult[RESULT] ) {
        if ( isset($_REQUEST['location']) ) {
            $redirect = $_REQUEST['location'];
        } else {
            $redirect = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . "/php/site_index.php";
        }
        header("Location: " . $redirect);
    } else {
        showForm($authResult[ERROR_MSG]);
    }
}

?>
