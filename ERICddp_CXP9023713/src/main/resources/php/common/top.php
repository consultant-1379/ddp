<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
 <head>
<?php
# Work out title content <host server (ddp/ddpi) - Site - Date - Page Title
$hostArr = explode(".", $_SERVER['HTTP_HOST']);
$title = $hostArr[0];
$topTitle = $title;
//$title = "<a href=site_index.php>site index</a>";
$link = $php_webroot . "/index.php?site=" . $site . "&oss=" . $oss;
if (isset($site)) {
    //$title .= " - <a href=\"" . $link . "\">" . $site . "</a>";
    $topTitle .= " - " . $site;
}
if (isset($date)) {
    //$title .= " - <a href=\"" . $link . "&date=" . $date . "&dir=" . $dir . "\">" . $date . "</a>";
    $topTitle .= " - " . $date;
} else if (isset($year) && isset($month) && $myDir == "monthly") {
    $topTitle .= " - " . $year . "/" . $month;
}
if (isset($pageTitle)) {
    //$title .= " - " . $pageTitle;
    $topTitle .= " - " . $pageTitle;
}
echo "  <title>" . $topTitle . "</title>\n";

$styleSheets = array();
$scripts = array();

# Display calander?
$styleLink = "$php_webroot/common/style_css.php";
if (isset($SHOW_CAL) && $SHOW_CAL == false) {
    $styleLink .= "?menu=hidden";
}
$styleSheets[] = $styleLink;

$styleSheets[] = "$php_webroot/common/menu.css";
$scripts[] = "$php_webroot/common/script.js";

/* "Import" YUI */
$yuiDir = "/yui";
$type = "min";
if ( isset($_GET['debug']) ) {
    $type = "debug";
}

$styleSheets[] = "$yuiDir/build/treeview/assets/skins/sam/treeview.css";
$scripts[] = "$yuiDir/build/yahoo-dom-event/yahoo-dom-event.js";
$scripts[] = "$yuiDir/build/treeview/treeview-$type.js";

$styleSheets[] = "$yuiDir/build/datatable/assets/skins/sam/datatable.css";
$scripts[] = "$yuiDir/build/element/element-$type.js";
$scripts[] = "$yuiDir/build/datasource/datasource-$type.js";
$scripts[] = "$yuiDir/build/datatable/datatable-$type.js";

$styleSheets[] = "$yuiDir/build/menu/assets/skins/sam/menu.css";
$scripts[] = "$yuiDir/build/container/container_core-min.js";
$scripts[] = "$yuiDir/build/menu/menu-min.js";

$scripts[] = "$yuiDir/build/json/json-$type.js";
$scripts[] = "$yuiDir/build/connection/connection_core-min.js";

$scripts[] = "$yuiDir/build/logger/logger-min.js";
$styleSheets[] = "$yuiDir/build/logger/assets/skins/sam/logger.css";

$scripts[] = "$yuiDir/build/paginator/paginator-$type.js";
$styleSheets[] = "$yuiDir/build/paginator/assets/skins/sam/paginator.css";

$scripts[] = "$yuiDir/build/animation/animation-$type.js";
$scripts[] = "$yuiDir/build/autocomplete/autocomplete-$type.js";
$styleSheets[] = "$yuiDir/build/autocomplete/assets/skins/sam/autocomplete.css";

if ( isset($CUSTOM_CSS) ) {
    $styleSheets[] = $php_webroot . "/" . $CUSTOM_CSS;
}

foreach ($styleSheets as $styleSheet) {
    echo "  <link rel=\"stylesheet\" href=\"$styleSheet\" type=\"text/css\">\n";
}
foreach ($scripts as $script) {
    echo "  <script type=\"text/javascript\" src=\"$script\"></script>\n";
}

echo <<<EOS
 </head>

 <body onload="checkShowMenu()">
  <div id=wrapper>
   <div id=header>
    <div id=title>

EOS;

include $php_common . "/menu.php";
echo "   </div>\n";

echo "  </div>\n";

$siteStatusFile = "/data/tmp/site_status.php";
if ( (!isset($UI) || $UI) && file_exists( $siteStatusFile ) ) {
    // Do we have a cookie set to hide this?
    echo "<div id=notice>\n";
    echo "<div id=noticecontent>\n";
    include_once $siteStatusFile ;
    echo "<p/><a href=?" . $_SERVER['QUERY_STRING'] . " onclick=\"return setCookie('notice','hidden',1);\">Hide this box</a>\n";
    echo "</div>\n</div>\n";
}

# Display notice to say that http is going away
if ( $_SERVER['REQUEST_SCHEME'] == 'http' && isset($HTTP_ExpireDate) ) {
    $httpsLink = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    echo <<<EOT
<div id=notice>
 <div id=noticecontent>
  <p>HTTP access to this site is being discontinued and will not be available after $HTTP_ExpireDate</p>
  <p>Please use <a href="$httpsLink">HTTPS</a> instead and use your Corporate SIGNUM and Corporate password to login</p>
 </div>
</div>
EOT;
}

# Display notice to say that user does not have access when in permissive mode
if ( $ACCESS_ALLOWED == FALSE ) {
    $allowedGroups = getAllowedGroups();
    $allowedGroupMsg = "";
    if ( count($allowedGroups) > 0 ) {
        $allowedGroupMsg = "<p>To be granted access, you need to be a member of the one of the following group(s)</p>\n<ul>\n";
        foreach ( $allowedGroups as $group ) {
            $allowedGroupMsg = $allowedGroupMsg . " <li>" . $group . "</li>\n";
        }
        $allowedGroupMsg = $allowedGroupMsg . "</ul>\n";
    }
    $link = "https://eteamspace.internal.ericsson.com/display/SMA/DDP+Access+Control";
    echo <<<EOT
<div id=notice>
 <div id=noticecontent>
  <p>You do not have authorization to access this site.</p>
  $allowedGroupMsg
  <p>See this <a href="$link">link</a> for more information.</p>
  <p>Temporary access has been granted <b>which will only be available until 15th February 2017.</b></p>
 </div>
</div>
EOT;
}

if (! isset($CAL) || $CAL != false) {
?>
<a href=# onclick="return hideMenu();">Show / hide menu</a>
<?php
    echo "<div id=cal>\n";
    include $php_common . "/cal.php";
    echo "</div>\n";
    echo "<div id=content>\n";
    /*if (! $dateIsValid && isset($date) && $date != "") {
        echo "<h2 class=error>There is no valid data for " . $date . "</h2>\n";
    } else if ( ! $dateIsValid && ! isset($date) || $date == "" && isset($CAL) && $CAL != false) {
        echo "<h2 class=error>Please select a date from the menu on the left</h2>\n";
    }*/
} else {
    echo "<br/>";
}

if (valueExists($date) && valueExists($site)) {
    include PHP_ROOT . "/StatsDB.php";
    $statsDB = new StatsDB();
    $row = $statsDB->queryRow("SELECT COUNT(*) FROM site_data, sites WHERE site_data.date = '$date' AND site_data.siteid = sites.id AND sites.name = '$site'");
    if ( $row[0] == 0 ) {
        if ( $debug ) { echo "<p>date=$date site=$site rootdir=$rootdir</p>\n"; }
        echo "<h2 class=error>There is no data for this date. Please select a different date.</h2>";
        include "/data/ddp/current/php/common/finalise.php";
        exit;
    }
}
?>
