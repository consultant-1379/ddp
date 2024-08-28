<?php

// Setup custom CSS if it exists
$ossVar = $_REQUEST['oss']; // NOSONAR
if ( isset($ossVar) ) {
    $customCssRelPath = strtoupper($ossVar) . "/index_inc.css";
    $customCssPath = dirname(__FILE__) . "/" . $customCssRelPath;
    if ( file_exists($customCssPath) ) {
        $CUSTOM_CSS = $customCssRelPath;
    }
}

include "common/init.php";

require_once 'HTML/Table.php';
require_once "classes/DDPCache.php";

function getServerListHTML() {
    $sl = new ServerList();
    if (count($sl->getData()) > 0 ) {
        $slHTML = $sl->getSortableHtmlTableStr();
    } else {
        $slHTML = "";
    }
    return $slHTML;
}

// If an error has occured processing data, show that error on all other days so the
// user is aware of the error. TODO: some way to "clear" errors
// Restrict the query to just look at the last month
$statsDB = new StatsDB();
$row = $statsDB->queryRow("
SELECT ms.filedate, ms.error, ms.uploaded
FROM
 ddpadmin.ddp_makestats AS ms, sites
WHERE
 sites.name = '$site' AND sites.id = ms.siteid AND
 ms.filedate >= (NOW() - INTERVAL 1 MONTH)
ORDER BY beginproc DESC
LIMIT 1");
if ( $row[1] ) {
  echo "<H1 style=\"color: red\">Problem processing data for $row[0] @ $row[2]</H1>\n";
  echo "<pre>$row[1]</pre>\n";
}

if (! valueExists($date)) {
    // No day selected - show some generic information here, and exit
    // However, we don't have a site overview for DDP
    if ( $oss != "ddp" ) {
        include "site_overview.php";
    } elseif ( $oss == "ddp") {
        echo "<H1>Please select a date!</H1>";
    }

    $statsDB->disconnect();
    include "common/finalise.php";
    return;
}

$webargs = "site=$site&dir=$dir&date=$date&oss=$oss";
$phpDir = dirname($_SERVER['PHP_SELF']);
if ($debug) { echo "<p>rootdir = $rootdir webroot=$webroot</p>\n"; }
if ($debug) { echo "<p>phpDir = " . $phpDir . "</p>\n"; }

/* Create DDP Cache obj */
$ddpCache = new DDPCache( $site, $date );

$refreshCache = 0;
if ( isset($_GET['refresh']) ) {
   $ddpCache->clear( array('servertable', 'appcontent') );
}

# Get data availability status for the TOR sites
$dataAvailabilityMsg = "";
if ( strtoupper($oss) == "TOR" ) {
  $dataAvailabilityMsg = getDataAvailabilityMsg();
}

# Include Application-specific data
$indexIncFile = PHP_ROOT . "/" . strtoupper($oss) . "/index_inc.php";
if ( file_exists($indexIncFile) ) {
    echo "<div class=dataset id=appl_dataset>\n";

    /*
       In new framework, the app specific index file should
       implement a function <oss>ContentGenerator which returns
       a string contains the div content
       For backwards compat, we assume that if the function doesn't
       exist then, the index page is directly echoing the content
       and so we can't cache it here
    */
    $appHTML = $ddpCache->get( "appcontent" );
    if ( is_null($appHTML) ) {
      include $indexIncFile;
      $appContentGenerator = $oss . 'GenerateContent';
      if ( function_exists($appContentGenerator) ) {
        $appHTML = call_user_func_array( $appContentGenerator, array());
        $ddpCache->set("appcontent", $appHTML);

      } else {
        $appHTML = "<!-- $appContentGenerator not implemented -->\n";
      }
    }

    # Include the uncached data availability status message for TOR sites
    if ( strtoupper($oss) == "TOR" ) {
      $dataAvailStatusPattern = "DATA_AVAILABILITY_STATUS";
      $dataAvailStatusPosition = strpos($appHTML, $dataAvailStatusPattern);
      if ($dataAvailStatusPosition !== false) {
        $appHTML = substr_replace($appHTML, $dataAvailabilityMsg, $dataAvailStatusPosition, strlen($dataAvailStatusPattern));
      }
    }

    echo $appHTML;
    echo "</div>\n\n <!-- div class=dataset id=appl_dataset -->\n\n";
}

# Include generic data
echo <<<EHTML
<script type="text/javascript" src="$php_webroot/index.js"></script>
<script type="text/javascript">
YAHOO.util.Event.addListener(window, "load", menuTreeEnhance);
</script>

<div onload='getCookie();' class=dataset id=generic_dataset>

EHTML;

require_once PHP_ROOT . "/classes/ServerList.php";
require_once PHP_ROOT . "/index_functions.php";

# Only use the cache where there is no sorting
if ( ! isset($_GET["serverlist_orderby"]) ) {
    $slHTML = $ddpCache->get( "servertable" );
    if ( is_null($slHTML) ) {
        $slHTML = getServerListHTML();
        $ddpCache->set( "servertable", $slHTML );
    } else {
        if ( $debug ) { echo "<p>Using cached table</p>\n"; }
    }
} else {
    $slHTML = getServerListHTML();
}
if ($slHTML != "") {
    echo "<h1>Servers</h1>\n";
    echo $slHTML;
}

/* Display any server reboots */
require_once PHP_ROOT . "/classes/DDPObject.class.php";
class RebootList extends DDPObject {
    var $title = "Reboots";
    var $cols = array (
        "hostname" => "Hostname",
        "time" => "Time",
        "duration" => "Duration"
    );

    var $defaultOrderBy = "time";

    function getData($site = SITE, $date = DATE) {
        $sql = "SELECT hostname, TIME(server_reboots.time) AS time, IFNULL(server_reboots.duration ,'') AS duration
            FROM server_reboots, servers, sites
            WHERE servers.siteid = sites.id AND sites.name = '$site' AND
            server_reboots.serverid = servers.id AND
            server_reboots.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
        $this->populateData($sql);
    }
}

$rl = new RebootList();
$rl->getData();
if (count($rl->data) > 0) {
    echo "<h3>Server Reboots"; drawHelpLink("reboothelp"); echo "</h3>\n";
    $rl->getSortableHtmlTable();
?>
<div id="reboothelp" class="helpbox">
<?php
     drawHelpTitle("Server Reboots", "reboothelp");
?>
<div class=helpbody>
This table simply indicates, for each reboot during the day, the time that the server came back up.
<p/>
If possible, the result of the command:
<p/>
<code>last reboot</code>
<p/>
is used.
<p/>
Occasionally this command does not work - long-running servers with unmaintained lastlogs will
eventually be unable to process the file. In these cases the time is calculated by
comparing the uptime of the server at 15-minute intervals. In this case, multiple reboots
during a 15-minute interval will not be detected.
<p/>
The Duration column displays (if available) the number of seconds between the <b>system down</b> and the <b>system boot</b>
</div>
</div>
<?php
}

// Display any external links that are set
displayExternalLinks();

echo "</div> <!-- div class=dataset id=generic_dataset -->\n\n";

$statsDB->disconnect();

include "common/finalise.php";
?>
