<?php
/*
* This is the initialisation page to be used by every
* user-exposed PHP page in the DDP UI. Here we will:
* 1. Parse common variables from $_GET
* 2. Set up the database connections etc.
* 3. call "common/top.php" which generates the top of the page
*/

const ENV_PHP = '/env.php';
const SLASH_ANALYSIS = '/analysis';
const FORMAT = 'format';


//HK67460: Adding cache expire header. The following two header tags will ensure that page
////is reloaded, this is to avoid the situation when data is uploaded but the page still displays NO DATA UPLOADED
// HTTP/1.1
header("Cache-Control: no-cache, must-revalidate");
// Date in the past
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// The HEAD method is identical to GET except that the server
// MUST NOT return a message-body in the response.
// Basically, if it's a HEAD request just exit

$php_common = dirname(__FILE__);
define("PHP_COMMON", $php_common);
// Up one from this script's directory
$php_root = dirname($php_common);
require_once $php_common . "/functions.php";
if (stripos(fromServer('REQUEST_METHOD'), 'HEAD') !== false) {
exit();
}
$DDP_TIME_START = microtime(true);

//
// Find the URL "path" the the php dir
$php_webroot = dirname(fromServer(PHP_SELF));
$myDir = basename($php_webroot);

$IS_ADMIN_UI = false;
if ($myDir == "adminui" || $myDir == "usermgt") {
  $IS_ADMIN_UI = true;
  $php_webroot = dirname($php_webroot);
  if ($php_webroot == "/") {
      $php_webroot = "";
  }
  $php_webroot .= "/php";
} else {
  $dir = $php_webroot;
  while ( basename($dir) != 'php' ) {
    $dir = dirname($dir);
  }
  $php_webroot = $dir;
}

define("PHP_WEBROOT", $php_webroot);
define("PHP_ROOT", $php_root);

$doDayCalendar = true;
if ( strpos(fromServer(PHP_SELF), '/monthly/') !== false ) {
  $isThisAMonthlyReport = true;
} else {
  $isThisAMonthlyReport = false;
}

/*
 Include some settings
 Order of precedence
   env.php in php_root
   env.php in php_root/../..
   env.default.php in php_root
*/
include_once $php_root . "/env.default.php";

$server_env = dirname(dirname($php_root)) . ENV_PHP;
if ( file_exists($server_env) ) {
  include_once($server_env);
 }
# If a local config file exists, include that as well.
if (file_exists($php_root . ENV_PHP)) {
    include_once $php_root . ENV_PHP;
}

require_once $php_root . "/StatsDB.php";

// Do authentication
require_once $php_common . "/access.php";
$authResult = isAuthenticated();
if ( ! $authResult[RESULT] ) {
    showFailedAuth();
    exit;
} else {
    if ( isset($_SESSION['username']) ) {
        $auth_user = strtolower($_SESSION['username']);
    }
}

// Common query parameters
foreach ( array('site','date','dir','oss','year','month','debug') as $varname ) {
    if ( isset($_REQUEST[$varname]) ) {
        $$varname = $_REQUEST[$varname];  //NOSONAR
    } else {
        $$varname = "";  //NOSONAR
    }
}

// This sets a load of global variables for uses by the normal (non-adminui) pages
// So only do this for the "normal" (non-adminui) pages
if ( ! $IS_ADMIN_UI ) {
  // Try to fetch arguments used to access the latest date for the given site
  if ( valueExists($site) && (!valueExists($oss)) ) {
      $statsDB = new StatsDB();
      $row = $statsDB->queryNamedRow("SELECT lastupload AS date, site_type AS type FROM sites WHERE name = '$site'");
      if ( valueExists($row['date']) ) {
          $date = explode(" ", $row['date'])[0];
          $yyyymmdd = explode("-", $date);
          $dir = $yyyymmdd[2] . $yyyymmdd[1] . substr($yyyymmdd[0], 2);
      }

      if ( valueExists($row['type']) ) {
          $oss = strtolower($row['type']);
      }
  }
  $webargs = "site=$site&dir=$dir&date=$date&oss=$oss";

  /* The directory where the analysis files are accessible from
    via the webserver
    e.g. http://ddpi/<webroot_base>/<dir>
  */
  $webroot_base = "/" . $oss . "/" . $site . SLASH_ANALYSIS;

  // The directory where the analysis files are accessible from via the filesystem - the full path
  $rootdir_base = $stats_dir . "/" . $webroot_base;

  // Are we looking at archive data?
  $view_archive = false;
  if (isset($_GET['archive']) && $_GET['archive'] == "true") {
    $view_archive = true;

    if (isset($_GET['archive_year']) ) {
      $archive_year = $_GET['archive_year'];
    }
  }

  // Code to handle links with are pointing at archived data
  if ( ($dir != "") && ($date != "") && (!is_dir($rootdir_base . "/" . $dir)) ) {
    list($dyear,$dmon,$dday) = explode('-', $date);
    $newArchivedPath = $nas_archive_dir . "/" . $dyear . "/" . $oss . "/" . $site . SLASH_ANALYSIS . $dir;
    $oldArchivedPath = $archive_dir . "/" . $oss . "/" . $site . SLASH_ANALYSIS . $dir;
    if ( $debug ) { echo "<p>newarchivedPath=$newArchivedPath oldArchivedPath=$oldArchivedPath</p>\n"; }
    if ( is_dir($newArchivedPath) ) {
      $view_archive = true;
      $archive_year = $dyear;

      $stats_dir = $nas_archive_dir . "/" . $dyear;
      $rootdir_base = $stats_dir . "/" . $oss . "/" . $site . SLASH_ANALYSIS;
      $webroot_base = "/archive/" . $archive_year . "/" . $oss . "/" . $site . SLASH_ANALYSIS;
    } elseif ( is_dir($oldArchivedPath) ) {
      $view_archive = true;

      $stats_dir = $archive_dir;
      $rootdir_base = $stats_dir . "//" . $oss . "/" . $site . SLASH_ANALYSIS;
      $webroot_base = "/archive//" . $oss . "/" . $site . SLASH_ANALYSIS;
    }
  }

  /* The directory where the analysis files are accessible from
  via the webserver for that day
  http://ddpi/<webroot_base>/<dir> == http://ddpi/<webroot>
  */
  $webroot = $webroot_base . "/" . $dir;

  // The directory where the data files are accessible from via the filesystem - the full path
  $datadir_base = realpath($rootdir_base . "/../data");

  // Directories where the analysis and data files are accessible from for this day
  $rootdir = $rootdir_base . "/" . $dir;
  $datadir = $datadir_base . "/" . $dir;

  # make some constants
  define("DATE", $date);
  define("SITE", $site);
  define("DATADIR", $datadir);
  define("WEBROOT", $webroot);
  define("ROOTDIR", $rootdir);

  $myPage = basename(fromServer(PHP_SELF));
  if (! isset($NOREDIR) && ! isset($_REQUEST["site"]) && $myPage != "site_index.php" && $myDir != "changelogs") {
      $siteIndexURL = $_SERVER['REQUEST_SCHEME'] . "://" .  $_SERVER["SERVER_NAME"] .
                    $php_webroot . "/site_index.php";
      header("Location: $siteIndexURL");
      exit;
  }
}

if ( valueExists($site) && (!isset($UI) || $UI) && ! $IS_ADMIN_UI) {
    $statsDB = new StatsDB();
    $row = $statsDB->queryNamedRow("SELECT name FROM sites WHERE name = '$site'");
    if ( $row == null ) {
        $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER["SERVER_NAME"] . $php_webroot . "/site_index.php";//NOSONAR
        echo <<<EOT
<p>Site $site does not exist.</p>
<p><a href="$url">Return to site index page</a></p>
EOT;
        exit;
    }
}


/*
 * Implement access control, i.e. is this user allowed access this site
 */
$ACCESS_ALLOWED = true;
if ( ! isset($ACCESS_CONTROL) ) {
    $ACCESS_CONTROL = 'enforcing';
}
if ( ($ACCESS_CONTROL != 'disabled') && (! isAccessAllowed()) ) {
    if ( $ACCESS_CONTROL == 'enforcing' ) {
        $allowedGroups = getAllowedGroups();
        $allowedGroupMsg = "";
        if ( count($allowedGroups) > 0 ) {
            $allowedGroupMsg = "<p>To be granted access, you need to be a member of the one of the following group(s)
                               </p>\n<ul>\n";
            foreach ( $allowedGroups as $group ) {
                $allowedGroupMsg = $allowedGroupMsg . " <li>" . $group . "</li>\n";
            }
            $allowedGroupMsg = $allowedGroupMsg . "</ul>\n";
        }
        echo <<<EOT
<p>You do not have authorization to access this site.</p>
$allowedGroupMsg
<p>See this <a href="https://eteamspace.internal.ericsson.com/display/SMA/DDP+Access+Control">link</a> for
 more information.</p>
EOT;
        exit;
    } else {
        $ACCESS_ALLOWED = false;
    }
}

// Detect cases where we automatically turn off the UI
// - Output Excel file
// - Calendar has been disabled
// - Arg listed in DISABLE_UI_PARAMS is presented in the calling URL
if (getArgs(FORMAT) == "xls" || getArgs(FORMAT) == "img") {
  $UI = false;
  include_once PHP_ROOT . "/classes/ExcelWorkbook.php";
} elseif (isset($_GET['showcal']) && $_GET['showcal'] == "false") {
  $UI = false;
} elseif ( isset($DISABLE_UI_PARAMS) ) {
    foreach ($DISABLE_UI_PARAMS as $PARAM_NAME) {
        if (issetURLParam($PARAM_NAME)) {
            $UI = false;
        }
    }
}

if (! isset($UI) || $UI != false) {
  require_once $php_common . "/top.php";
}
