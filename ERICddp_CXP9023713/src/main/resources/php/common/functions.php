<?php

require_once "headerhelp.php";
require_once "htmlFunctions.php";

const COMMON_SERV_HOST = '/server/hostname';

function getYesterday($dateArr) {
    $mDay = $dateArr[0];
    $mMonth = $dateArr[1];
    $mYear = $dateArr[2];

    if (! isset($mDay)) {
        $mDay = date("d");
    }
    if (! isset($mMonth)) {
        $mMonth = date("m");
    }
    if (! isset($mYear)) {
        $mYear = date("Y");
    }
    if ($mYear - 2000 < 0) {
        $mYear += 2000;
    }

    // yesterday is 1 day before today
    $yDay = $mDay - 1;
    // assume month and year are the same ...
    $yMonth = $mMonth;
    $yYear = $mYear;
    if ($yDay < 1) {
        // Oops, we went to last month
        $yMonth = $mMonth - 1;
        if ($yMonth < 1) {
            // we went to last year
            $yYear = $mYear - 1;
            $yMonth = 12;
        }
        $yDay = date("t", mktime( 0, 0, 0, $yMonth, 1, $yYear ) );
    }
    return array( $yDay, $yMonth, $yYear );
}

function getTomorrow($dateArr) {
    $mDay = $dateArr[0];
    $mMonth = $dateArr[1];
    $mYear = $dateArr[2];

    if (! isset($mDay)) {
        $mDay = date("d");
    }
    if (! isset($mMonth)) {
        $mMonth = date("m");
    }
    if (! isset($mYear)) {
        $mYear = date("Y");
    }
    if ($mYear - 2000 < 0) {
        $mYear += 2000;
    }

    // tomorrow is the day after today ...
    $tDay = $mDay + 1;
    // assume month and year are the same ...
    $tMonth = $mMonth;
    $tYear = $mYear;
    if ($tDay > date("t", mktime(0, 0, 0, $mMonth, 1, $mYear))) {
        // We went to next month ...
        $tDay = 1;
        $tMonth++;
        if ($tMonth > 12) {
            // we went to next year ...
            $tYear++;
            $tMonth = 1;
        }
    }
    if ($tDay < 10) {
        $tDay = "0" . $tDay;
    }
    return array($tDay, $tMonth, $tYear);
}

function valueExists($val) {
    if (isset($val) && $val != "") {
        return true;
    }
    return false;
}

function logIt($string, $class = "") {
    error_log(date("Y-m-d H:j:s.u") . " : " . $class . " : " . $string . "\n", "/var/tmp/ddp.log");
}

function getDDPVer() {
    $ddpVer = "Undefined";
    $dirInfo = explode("/", __FILE__);
    foreach ($dirInfo as $dir) {
        if (preg_match("/^DDP_20[0-2][0-9]_[0-2][0-9]_[0-3][0-9]_[0-9][0-9]*$/", $dir)) {
            $rev = $dir;
            break;
        }

        // Update to catch new DDP Version following DDP Migration to GIT
        if (preg_match("/^DDP-[0-9].[0-9].[0-9][0-9]*$/", $dir)) {
            $rev = $dir;
            break;
        }
    }
    if (isset($rev)) {
        // Adding if clause to handle updated DDP Version format following DDP Migration to GIT
        if (preg_match("/^DDP_20[0-2][0-9]_[0-2][0-9]_[0-3][0-9]_[0-9][0-9]*$/", $rev)) {
            $a = explode("_", $rev);
            $ddpVer = $a[1] . "-" . $a[2] . "-" . $a[3] . " Rel. " . $a[4];
        } else {
            $a = explode("-", $rev);
            $ddpVer = $a[1];
        }
    }
    return $ddpVer;
}

function getSiteId($statsDB, $site) {
    $sqlquery = "SELECT id FROM sites WHERE sites.name = \"$site\"";
    $row = $statsDB->queryRow($sqlquery);
    return (int)$row[0];
}

function getServerId($statsDB, $site, $hostname) {
    $sqlquery = "SELECT servers.id FROM servers, sites WHERE sites.name = \"$site\" AND sites.id = servers.siteid";
    if (is_dir($hostname)) {
        $rootdir = $hostname;
        if ( file_exists($rootdir . "/hostname.php") ) {
            include_once($rootdir . "/hostname.php");
            if ( (! isset($hostname)) ||  (strlen($hostname) <= 0) ) {
                echo "<b>Failed to read hostname</b>\n";
                exit(1);
            } else {
                $sqlquery = $sqlquery . " AND hostname = \"$hostname\"";
            }
        }
    } else {
        // treate third parameter as a hostname
        $sqlquery = $sqlquery . " AND hostname = '" . $hostname . "'";
    }
    $row = $statsDB->queryRow($sqlquery);
    return (int)$row[0];
}

/**
 * Gets an array of server ids from an array of hostnames.
 *
 * @param Array $hostnames is the list of hostnames we need the server ids for.
 *
 * @return Array $serverIds a list of server ids.
 */
function getServIdsFromArray( $hostnames ) {
    global $statsDB, $site;

    $serverIds = array();
    foreach ( $hostnames as $hostname ) {
        $serverId = getServerId($statsDB, $site, $hostname);
        $serverIds[] = $serverId;
    }
    return $serverIds;
}

/**
 * Gets an array of SG names from an array of server ids.
 *
 * @param Array $serverIds is the list of server ids we need the SG names for.
 *
 * @return Array $sgs a list of SG names.
 */
function getSgsFromServIdsArray( $serverIds ) {
    $map = srvToSgMap();
    $sgs = array();

    foreach ( $serverIds as $serverId ) {
        $sg = $map[$serverId];
        $sgs[] = $sg;
    }

    return array_unique($sgs);
}

function getNumCpus($statsDB, $site, $hostname, $date) {
    $row = $statsDB->queryRow("
SELECT
 SUM( servercpu.num * IFNULL(cputypes.cores, 1) * IFNULL(cputypes.threadsPerCore, 1) )
FROM sites, servers, cputypes, servercpu, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.hostname = '$hostname' AND
 servercfg.serverid = servers.id AND
 servercfg.date = '$date' AND
 servercpu.cfgid = servercfg.cfgid AND
 servercpu.typeid = cputypes.id");
    return $row[0];
}

function enmGetServiceInstances($statsDB, $site, $date, $service) {
  $results = array();
  $statsDB->query("
SELECT
 servers.hostname AS hostsvr, servers.id AS srvid
FROM enm_servicegroup_instances, enm_servicegroup_names, servers, sites
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serverid = servers.id AND
 enm_servicegroup_instances.serviceid = enm_servicegroup_names.id AND enm_servicegroup_names.name = '$service'
ORDER BY servers.hostname");
  while ( $row = $statsDB->getNextRow() ) {
    $results[$row[0]] = $row[1];
  }
  return $results;
}

function k8sGetServiceInstances($statsDB, $site, $date, $service) {
    $results = array();

    $serverid = implode("','", $service);
    $statsDB->query("
SELECT
 servers.hostname AS hostsvr, servers.id AS srvid
FROM k8s_pod, k8s_pod_app_names, servers, sites
WHERE
 k8s_pod.siteid = sites.id AND sites.name = '$site' AND
 k8s_pod.date = '$date' AND
 k8s_pod.serverid = servers.id AND
 k8s_pod.appid = k8s_pod_app_names.id AND k8s_pod_app_names.name IN ('$serverid')
ORDER BY servers.hostname");
    while ( $row = $statsDB->getNextRow() ) {
       $results[$row[0]] = $row[1];
    }
    return $results;
}

function getSfsNodeTypes($statsDB, $site, $date) {
  $sfsNodeTypes = array();
  $row = $statsDB->query("
SELECT
 DISTINCT servers.type
FROM
 servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 (servers.type = 'SFS' OR servers.type = 'ACCESSNAS')");

  while ($row = $statsDB->getNextRow()) {
    $sfsNodeTypes[$row[0]] = 1;
  }

  return $sfsNodeTypes;
}

// RNS Table - originally on NEAD page, now displayed both there and on nodes.php
function getRnsTable($statsDB,$site,$date) {
    $rnsTable = new HTML_Table("border=1");
    $sqlquery = "
SELECT
   rns.name, rns_list.numne
FROM
   rns, rns_list, sites
WHERE
   rns_list.date = '$date' AND
   rns_list.siteid = sites.id AND sites.name = '$site' AND
   rns_list.rnsid = rns.id
ORDER BY
   rns.name
";
    $statsDB->query($sqlquery);
    if ( $statsDB->getNumRows() > 0 ) {
        $rnsTable->addRow( array("RNS Name", "Number of Nodes"), null, 'th' );
        while($row = $statsDB->getNextRow()) {
            $rnsTable->addRow($row);
        }
    }
    return $rnsTable;
}

function getDataAvailTimeFromDB() {
    global $site, $date, $statsDB;

    $siteId = getSiteId($statsDB, $site);
    $sql = "SELECT TIME(dataAvailabilityTime) FROM site_data WHERE siteid = '$siteId' AND date = '$date'";
    $row = $statsDB->queryRow($sql);

    return $row[0];
}


function getDataAvailabilityMsg() {
    global $date, $datadir;

    $dataAvailabilityTime = getDataAvailTimeFromDB();
    if ( is_null($dataAvailabilityTime) ) {
        $dataAvailabilityTime = getCollectionStartTime($datadir);
        if ( is_null($dataAvailabilityTime) ) {
            $dataAvailabilityTime = getHostnameTime($date, $datadir);
        }
    }

    if ( is_null($dataAvailabilityTime) ) {
        $dataAvailabilityMsg = '';
    } elseif ( file_exists($datadir . "/processing.flag") ) {
        $dataAvailabilityMsg = " (Processing of data up to " . $dataAvailabilityTime . " in progress...)";
    } else {
        $dataAvailabilityMsg =  " (Data available up to " . $dataAvailabilityTime . ")";
    }

    return $dataAvailabilityMsg;
}

function getCollectionStartTime($datadir) {
    global $date;

    $collectionStartTime = null;
    $collectionStartFile = $datadir . "/COLLECTION_START";
    debugMsg("getCollectionStartTime: collectionStartFile", $collectionStartFile);
    if ( file_exists($collectionStartFile) ) {
        $content = file_get_contents($collectionStartFile); // NOSONAR
        if ( strlen($content) > 0 ) {
            $collectionStartTime = $content;
            // Strip the date from collectionStartTime
            list($csDate, $csTime) = explode(' ', $collectionStartTime);
            if ( $csDate == $date ) {
                $collectionStartTime = $csTime;
            }
        }
    }
    return $collectionStartTime;
}

function getHostnameTime($date, $datadir) {
    $dataAvailabilityTime = null;
    list($timezoneAbbr, $utcOffsetInSecs) = getTimezoneNUtcOffset($date, $datadir);

    if ( empty($timezoneAbbr) ) {
        $timezoneAbbr = ($utcOffsetInSecs == 0 ? 'GMT' : 'hrs');
    }
    if ( file_exists($datadir . COMMON_SERV_HOST) ) {
        $dataAvailabilityDate = gmdate("Y-m-d", (filemtime($datadir . COMMON_SERV_HOST) + $utcOffsetInSecs) );
        if ( $dataAvailabilityDate > $date ) {
            $dataAvailabilityTime = "23:59 $timezoneAbbr" ;
        } else {
            $dataAvailabilityTime =
                gmdate("H:i", (filemtime($datadir . COMMON_SERV_HOST) + $utcOffsetInSecs) ) . " $timezoneAbbr";
        }
    }
    return $dataAvailabilityTime;
}

function getTimezoneNUtcOffset($date, $datadir) {
    # Get 'Timezone' and 'UTC Offset' information from 'tz.txt' file
    $tzTxtFile = "";
    if ( file_exists($datadir . "/server/tz.txt") ) {
        $tzTxtFile = $datadir . "/server/tz.txt";
    } elseif ( file_exists($datadir . "/TOR/tz.txt")  ) {
        $tzTxtFile = $datadir . "/TOR/tz.txt";
    }

    $tzStr = "";
    if ( ! empty($tzTxtFile) ) {
        $tzFh = fopen($tzTxtFile, 'r');
        $tzStr = rtrim( fgets($tzFh) );
        fclose($tzFh);
    }

    # The older TZ strings (i.e., prior to DDC 3.13.3, ISO 1.17.26) under ENM 'tz.txt' files not only lack proper
    #   UTC offset but also contain timezone abbreviations (eg: 'IST') given by 'date +%Z' command instead of full
    #   location-based timezone names (eg: 'Europe/Dublin'). These abbreviations can sometimes be ambiguous. For
    #   instance 'IST' may represent Irish, Israel, Iran and Indian Standard Times. There is no sure way to retrieve
    #   UTC offset using such abbreviations. The below list represents such ambiguous timezones for which we try to
    #   return timezone and offset in GMT, if possible.
    # Note: Please don't bother to update this list with new ambiguous timezones in future as the new 'tz.txt' files
    #   from DDC 3.13.3 will have proper UTC offsets and location-based timezones. To be more exact, 'IST' (Irish
    #   Standard Time, UTC+01:00) is the only ambiguous timezone I observed under all DDPs prior to 'DDC 3.13.3'.
    $ambiguousTzAbbrs = array("ACT"=>"1", "ADT"=>"1", "AMST"=>"1", "AMT"=>"1", "AST"=>"1", "BDT"=>"1",
        "BST"=>"1", "CDT"=>"1", "CST"=>"1", "ECT"=>"1", "EST"=>"1", "FKST"=>"1", "GST"=>"1", "IST"=>"1",
        "LHST"=>"1", "MST"=>"1", "PST"=>"1", "PYT"=>"1", "SST"=>"1", "WST"=>"1");

    $timezoneAbbr = '';
    $utcOffsetInSecs = 0;
    if ( ! empty($tzStr) ) {
        $timezone = preg_replace('/^([^:]*)::.*$/', '$1', $tzStr);
        $hasUtcOffset = False;
        # TORF-228082: Commenting out this code as we can't trust the UTC offset
        # under 'tz.txt' on the day of DST transition as it is now getting collected
        # only during DDC 'START' but not in DDC 'MAKETAR'/'STOP'. For now, get the
        # UTC offset from the location-based timezone instead. A more standard
        # solution is to collect 'tz.txt' during DDC 'MAKETAR'/'STOP' as well
        #if ( preg_match('/^.*::([+-])(\d{2})(\d{2})/', $tzStr, $matches) ) {
        #    $utcOffsetInSecs = $matches[1] . ($matches[2] * 3600 + $matches[3] * 60);
        #    $hasUtcOffset = True;
        #    $timezoneAbbr = $timezone;
        #}
        try {
            if ( ! isset($ambiguousTzAbbrs[$timezone]) ) {
                $dateTime = new DateTime("$date 13:00:00", new DateTimeZone($timezone));
                $timezoneAbbr = $dateTime->format('T');
                # TORF-236789: Handle the timezones like 'America/Sao_Paulo' which have
                # timezone abbreviations like '-02'
                if ( ! preg_match('/^[A-Za-z]+$/', $timezoneAbbr) ) {
                    $timezoneAbbr = $timezone;
                }
                $utcOffsetInSecs = ( $hasUtcOffset ? $utcOffsetInSecs : $dateTime->getOffset() );
            }
        } catch (Exception $exception) {
            error_log( $exception->getMessage() );
        }
    }

    return array($timezoneAbbr, $utcOffsetInSecs);
}

function doOpenSSL($operation, $input) {
    $encryptionMethod = "AES-256-CBC";
    $encryptionKey = hash('sha256', "_enCryptMeT00!");
    $encryptionIV = substr(hash('sha256', "_enCryptIVT00!"), 0, 16);

    if ( $operation == 'encrypt' ) {
        return base64_encode( openssl_encrypt($input, $encryptionMethod, $encryptionKey, 0, $encryptionIV) );
    }
    else if ($operation == 'decrypt') {
        return openssl_decrypt( base64_decode($input), $encryptionMethod, $encryptionKey, 0, $encryptionIV );
    }
}

/**
 *Returns a list of all active Service Groups
 *
 *@author Patrick O Connor
 *
 */
function getServiceGroupList() {
    global $statsDB, $site, $date;

    $srvcGrps = array();
    $statsDB->query("
SELECT
    DISTINCT enm_servicegroup_names.name
FROM
    enm_servicegroup_names,
    enm_servicegroup_instances,
    servers,
    sites
WHERE
    enm_servicegroup_instances.siteid = sites.id AND
    sites.name = '$site' AND
    enm_servicegroup_instances.date = '$date' AND
    enm_servicegroup_instances.serverid = servers.id AND
    enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
    ");

    while ( $row = $statsDB->getNextRow() ) {
        $srvcGrps[] = $row[0];
    }
    return $srvcGrps;
}

/**
 *Returns a list of all active servers for a specified Service Group
 *
 *@author Patrick O Connor
 *
 *@param string $service This is the service to get a server list for
 *@param boolean $ids This is to toggle between returning hostnames & returning server ids
 *
 */
function makeSrvList($service, $ids=false) {
    global $date, $site, $statsDB;

    $srvList = enmGetServiceInstances($statsDB, $site, $date, $service);
    $res = array();
    foreach ( $srvList as $server => $serverid ) {
        if ( !$ids ) {
            $res[] = $server;
        } else {
            $res[] = $serverid;
        }
    }
    return implode(",", $res);
}

/**
 *Returns an array of distinct sever hostnames from a table
 *
 *@author Lorcan Williamson
 *
 *@param string $table The name of the table
 *@param string $timeCol The name of the time column of the table, time by default
 *@param string $extraWhere Any extra where statements, should start with AND
 *@param string $siteCol The name of the site column of the table, siteid by default
 *@param string $serverCol The name of the server column of the table, serverid by default
 *
**/
function getInstances( $table, $timeCol = 'time', $extraWhere = null, $siteCol = 'siteid', $serverCol = 'serverid' ) {
    global $date, $site, $statsDB;

    $instances = array();
    $statsDB->query("
SELECT
  DISTINCT(servers.hostname) AS instance
FROM
  $table, sites, servers
WHERE
  $table.$siteCol = sites.id AND sites.name = '$site' AND
  $table.$serverCol = servers.id AND
  $table.$timeCol BETWEEN '$date 00:00:00' AND '$date 23:59:59' "
  . $extraWhere . " ORDER BY instance"
    );

    while ( $row = $statsDB->getNextRow() ) {
        $instances[] = $row[0];
    }
    return $instances;
}

/**
 * Print "debug" line if the debug level is enabled
 * @param string $msg the message to print
 * @param mixed $data optional variable to print
 * @param int $level debug level to print, defaults to 1
 **/
function debugMsg($msg, $data = null, $level = 1) {
    global $debug;

    if ( $debug >= $level ) {
        $time = date("H:i:s");
        echo "<pre>$time: $msg";
        if ( ! is_null($data) ) {
            echo ": ";
            print_r($data);
        }
        echo "</pre>";
    }
}

/**
 *Returns true if $_REQUEST is set, false otherwise
 *
 *@author Patrick O Connor
 *
 *@param string $arg This is the argument to pass to $_REQUEST
 *
 */
function issetURLParam( $arg ) {
    if ( isset($_REQUEST[ $arg ]) ) { //NOSONAR
        return true;
    }
    return false;
}

/**
 * Returns the value of the then named arg from _REQUEST, return null if it's not found
 *
 */
function requestValue($key) {
    if ( issetURLParam( $key ) ) {
        return $_REQUEST[$key]; //NOSONAR
    } else {
        return null;
    }
}

function assignRequestValue($key, $value) {
    return $_REQUEST[$key] = $value; //NOSONAR
}

function fromServer($arg) {
    if ( isset($_SERVER[$arg]) ) { //NOSONAR
        return $_SERVER[$arg]; //NOSONAR
    }
    return null;
}

// To retrieve all the sent information using GET method.
// Use requestValue($key) Instead.
function getArgs($getargs) {
    if ( isset($_GET[$getargs]) ) { //NOSONAR
        return $_GET[$getargs]; //NOSONAR
    }
    return null;
}

/**
  * Returns the full path to a file specified in the current request
  *
  *
  * Uses the site, oss, dir, pathtype and replpath params to build the full path
  *
  * @return string path to file or null if the path couldn't be determined
  */
function getPathFromArgs() {
    global $site, $datadir, $rootdir;

    $pathType = requestValue("pathtype");
    $relPath = html_entity_decode(requestValue("relpath"));
    $fullPath = null;
    if ( $pathType === "data" ) {
        $fullPath = $datadir . "/" . $relPath;
    } elseif ( $pathType === "analysis" ) {
        $fullPath = $rootdir . "/" . $relPath;
    } elseif ( $pathType === "s_t" ) {
        $fullPath = "/data/stats/temp/$relPath";
    } elseif ( empty($site) ) {
        $fullPath = "/data/ddp/$pathType/$relPath";
    }

    return $fullPath;
}

/**
  * Split from getUrlForFile() to reduce cognitive complexity
  *
  *@param string $fullPath the file path
  *
  *@return array containing $pathtype & $relpath
  */
function getPaths( $fullPath ) {
    global $stats_dir;
    $pathtype = null;
    $relpath = null;

    if ( substr($fullPath, 0, strlen($stats_dir)) === $stats_dir ) {
        $basedir = "/data/stats/temp";
        if ( substr($fullPath, 0, strlen($basedir)) === $basedir ) {
            $pathtype = "s_t";
            $relpath = substr($fullPath, strlen($basedir) + 1);
        }
    } else {
        // If we don't have a site set, then we're looking at a DDP admin file
        foreach ( array("log", "upgrade") as $ddptype ) {
            $basedir = "/data/ddp/$ddptype";
            if ( substr($fullPath, 0, strlen($basedir)) === $basedir ) {
                $pathtype = $ddptype;
                $relpath = substr($fullPath, strlen($basedir) + 1);
            }
        }
    }
    return array( $pathtype, $relpath );
}

/**
  * Returns URL to get the specfied file
  *
  *
  * Uses the site, oss, dir, pathtype and replpath params to build the full path
  *
  * @param string fullPath the file path
  * @param boolean forceDownload force download link
  * @return string URL to filedownload/filedisplay which will get the file
  */
function getUrlForFile($fullPath, $forceDownload = false) {
    global $site, $oss, $dir, $datadir, $rootdir, $php_webroot, $webargs, $debug;

    $relpath = null;
    if ( empty($site) ) {
        $paths = getPaths( $fullPath );
        $pathtype = $paths[0];
        $relpath = $paths[1];
    } elseif ( substr($fullPath, 0, strlen($datadir)) === $datadir ) {
        $pathtype = "data";
        $relpath = substr($fullPath, strlen($datadir) + 1);
    } elseif ( substr($fullPath, 0, strlen($rootdir)) === $rootdir ) {
        $pathtype = "analysis";
        $relpath = substr($fullPath, strlen($rootdir) + 1);
    }

    if ( is_null($relpath) ) {
        return null;
    }

    if ( $forceDownload || preg_match("/\.gz$|btmp$|lastlog$|wtmp$|\.zip$/", $relpath) ) {
        $handler = "filedownload.php";
    } else {
        $handler = "filedisplay.php";
    }

    $result = sprintf(
        "%s/common/%s?pathtype=%s&relpath=%s",
        $php_webroot,
        $handler,
        $pathtype,
        htmlentities($relpath)
    );
    if ( ! empty($site) ) {
        $result .= "&" . $webargs;
    }

    return $result;
}

/**
 *Sorts the data structure that will be used by DDPTable
 *
 *@author Patrick O Connor
 *
 *@param Array $data The data to be displayed
 *@param String $sortOn The attribute to sort on
 *@param String $dir The direct to sort
 *
 */
function sortDDPTableData( $data, $sortOn, $dir ) {
    $sortKeys = array();
    foreach ($data as $key => $row) {
        $sortKeys[$key] = $row[$sortOn];
    }
    array_multisort($sortKeys, $dir, $data);
    return $data;
}

/**
 *  Gets the list of JVMs that have generic JMX stats available
 *
 *  @return string an array of arrays, each entry in has 'serverid', 'servername', 'jvmid', 'jvmname'
 */
function getGenJmxJvms() {
    global $statsDB, $site, $date;

    // First try an get it from the summary table
    $results = array();
    $statsDB->query("
SELECT
    sum_generic_jmx_stats.serverid AS serverid,
    servers.hostname AS servername,
    sum_generic_jmx_stats.nameid AS jvmid,
    jmx_names.name AS jvmname
FROM sum_generic_jmx_stats
JOIN servers ON sum_generic_jmx_stats.serverid = servers.id
JOIN jmx_names ON sum_generic_jmx_stats.nameid = jmx_names.id
JOIN sites ON sum_generic_jmx_stats.siteid = sites.id
WHERE
 sum_generic_jmx_stats.date = '$date' AND
 sites.name = '$site'");
    while ($row = $statsDB->getNextNamedRow()) {
        $results[] = $row;
    }

    // If there's no data, then we'll have to query the raw table, should only happen for
    // data loaded before sum_generic_jmx_stats was created
    if ( count($results) == 0 ) {
        $statsDB->query("
SELECT DISTINCT
 generic_jmx_stats.serverid AS serverid,
 servers.hostname AS servername,
 generic_jmx_stats.nameid AS jvmid,
 jmx_names.name AS jvmname
FROM generic_jmx_stats FORCE INDEX(siteTimeIdx)
JOIN sites ON generic_jmx_stats.siteid = sites.id
JOIN servers ON generic_jmx_stats.serverid = servers.id
JOIN jmx_names ON generic_jmx_stats.nameid = jmx_names.id
WHERE
 sites.name = '$site' AND
 generic_jmx_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
        while ($row = $statsDB->getNextNamedRow()) {
            $results[] = $row;
        }
    }

    return $results;
}

/**
 *  Gets a list of all available serverIds mapped to their SG
 *
 *  @return Array of serverId to SG Mapping
 */
function srvToSgMap() {
    global $statsDB, $site, $date;
    $srvToSG = array();

    $sql = <<<EOT
SELECT
    enm_servicegroup_instances.serverid,
    enm_servicegroup_names.name
FROM
    sites,
    enm_servicegroup_instances,
    enm_servicegroup_names
WHERE
    enm_servicegroup_instances.siteid = sites.id AND
    sites.name = '$site' AND
    enm_servicegroup_instances.date = '$date' AND
    enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
EOT;

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextRow() ) {
        $srvToSG[$row[0]] = $row[1];
    }

    return $srvToSG;
}

/**
 *  Subtract n days from the $date
 *
 *  @param $days The number of days to subtract
 *
 *  @return String the date after subtraction
 */
function subDate( $days ) {
    global $date;
    $d = new DateTime($date);
    $d->sub(new DateInterval("P{$days}D"));
    return $d->format('Y-m-d');
}

/**
 * Sends HTTP request.
 *
 * @param if provided and not null, will be used as the data for a POST request
 *
 * @return Array content of reply, info (see curl_getinfo), errno (zero = okay), error string describing error
 */
function sendRequest($url, $data = null) {
    $curl = curl_init();
    if (!$curl) {
        die("Couldn't initialize a cURL handle");
    }

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    if ( ! is_null($data) ) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $result = [
        'content' => curl_exec($curl),
        'info' => curl_getinfo($curl),
        'errno' => curl_errno($curl),
        'error' => curl_error($curl)
    ];

    curl_close($curl);

    return $result;
}


/**
 *Calls genericPhpToRootWrapper to run perl scripts with root access and source /data/stats/config
 *
 * @param $scriptId The id used by genericPhpToRootWrapper to identify the script
 * @param $opts If provided, The options the perl script will use
 * @param $out If provided, The file that the perl script will output to
 *
 * @return Returns the $retVal of the script run
 */
function callGenericPhpToRootWrapper( $scriptId, $opts = null, $out = null ) {
    global $ddp_dir;

    $script = "$ddp_dir/server_setup/genericPhpToRootWrapper";

    $scriptFlags = "--scriptid '$scriptId'";

    if ( ! is_null( $opts ) ) {
        $scriptFlags .= " --options '$opts'";
    }
    if ( ! is_null( $out ) ) {
        $scriptFlags .= " --outputfile '$out'";
    }

    system("sudo $script $scriptFlags", $RetVal);

    if ($RetVal != 0) {
        echo addLineBreak();
        echo "genericPhpToRootWrapper: $scriptId failed to start";
        echo addLineBreak();
    }
    return $RetVal;
}

?>
