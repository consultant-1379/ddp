<?php
require_once 'TOR/util.php';

$CAL = false; // disables the calendar
$SHOW_CAL = false; // disables the calendar div - sets it to hidden

// preferences.
// _GET params override _POST which overrides cookies
// _POST params replace cookies, _GET params do not.
// So, you can have "default" (cookie) preferences for a filter
// of Vodafone live sites, but a bookmark for T-Mobile UK.
//
// If you use POST, the GET params should be excluded from the query

$sidxcols = array();
$sidxcountry = "";
$sidxoper = "";
$sidxstatus = "";
$attrs = array('sidxcountry','sidxoper','sidxstatus', 'sidxactive', 'scountry', 'opname');

// default view is OSS
$siteTypeView = "OSS";

// 1. Pull cookie data if any
// get columns which are used to decide what to show
if (isset($_COOKIE['sidxcols']) && $_COOKIE['sidxcols'] != "") {
    $sidxcols = array_unique(explode(",", $_COOKIE['sidxcols']));
}
// get the sidx attrs which are used to decide user-defined filters
foreach ($attrs as $a) {
    if (isset($_COOKIE[$a]) && $_COOKIE[$a] != "") {
        ${$a} = $_COOKIE[$a];
    }
}
// get the site_type which is used to decide the typeView to present
if (isset($_COOKIE['site_type']) && $_COOKIE['site_type'] != "") {
    $siteTypeView = $_COOKIE['site_type'];
}
if (isset($_GET['debug'])) {
    echo "COLS:<br/>\n";
    foreach ($sidxcols as $key => $val) {
        echo "&nbsp;nbsp;" . $key . " => " . $val . "<br/>\n";
    }
    foreach ($attrs as $a) {
        if ( isset(${$a}) ) {
            echo $a . " => " . ${$a} . "<br/>\n";
        } else {
            echo $a . " => undef<br/>\n";
        }

    }
    echo "<br/>\n";
}

// 2. Pull $_POST data and overwrite $_COOKIE data
if (isset($_POST['submit'])) {
    $sidxcols = array();
    foreach ($_POST as $name => $val) {
        if ( (strcmp("c_", substr($name, 0, 2)) == 0) && (strcmp($val, "on") == 0) ) {
            $sidxcols[] = substr($name, 2);
        }
    }
    setcookie('sidxcols', implode(",", $sidxcols), time() + 2 * 365 * 24 * 60 * 60, "/");
    foreach ($attrs as $a) {
        if (isset($_POST[$a])) {
            ${$a} = $_POST[$a];
            setcookie($a, $_POST[$a], time() + 2 * 365 * 24 * 60 * 60, "/");
        }
    }
}

// 3. Temporarily override any params with $_GET
if (isset($_GET['sidxcols']) && $_GET['sidxcols'] != "") {
    $sidxcols = array_unique(explode(",", $_GET['sidxcols']));
}
foreach ($attrs as $a) {
    if (isset($_GET[$a]) && $_GET[$a] != "") {
        ${$a} = $_GET[$a];
    }
}
if (isset($_GET['site_type']) && $_GET['site_type'] != "") {
    $siteTypeView = $_GET['site_type'];
    // Default to this site type
    setcookie('site_type', $siteTypeView, time() + 2 * 365 * 24 * 60 * 60, "/");
}

$NOREDIR = true;
$YUI_DATATABLE = true;

include "common/init.php";
include "common/countries.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

abstract class SiteIndex extends DDPObject {
    var $statsDBs = NULL;

    /* Columns that all site types will use. Extra cols can be appended as required */
    var $allcols = array(
        "site" => "Site",
        "sitever" => "Version",
        "utilver" => "DDC Version",
        "lastupload_date" => "Last Upload<br>(yyyy-mm-dd hh:mm:ss)",
        "operator" => "Operator",
        "country" => "Country",
        "site_status" => "Site Status",
    );

    /* Columns that need to be custom-formatted under the given YUI datatable */
    var $columnFormatters = array();

    /* the default columns to be presented - if you want your extra ones here append them */
    var $defaultCols = array("site","sitever","utilver","lastupload_date","operator","country","site_status");

    var $genericFilter = "AND sites.name != 'Temp' AND sites.name NOT LIKE 'RAN%' AND sites.name NOT LIKE 'SPD\_%' AND sites.name NOT LIKE 'Test%'";

    var $defaultOrderBy = "site";
    var $defaultOrderDir = "ASC";

    var $filter = "";

    function __construct($cols = array()) {
        parent::__construct("sitelist");
        if (count($cols) == 0) {
            $cols = $this->defaultCols;
        }
        $this->cols = array();
        // iterate through allcols first to maintain a predictable order
        foreach ($this->allcols as $c => $t) {
            foreach ($cols as $ec) {
                if ( $c == $ec ) {
                    $colDef = array('key' => $c, 'label' => $t);
                    if ( array_key_exists($c, $this->columnFormatters) ) {
                        if ( array_key_exists('forceSortAsNums', $this->columnFormatters[$c]) ) {
                           $colDef['sortOptions'] = array('sortFunction' => 'forceSortAsNums');
                        }
                        if ( array_key_exists('ddpFormatNumber', $this->columnFormatters[$c]) ) {
                           $colDef['formatter'] = 'ddpFormatNumber';
                        }
                        if ( array_key_exists('ddpFormatTime', $this->columnFormatters[$c]) ) {
                           $colDef['formatter'] = 'ddpFormatTime';
                        }
                    }
                    $this->cols[] = $colDef;
                }
            }
        }
    }

    /*
     * abstract method to be overridden by the child - the implementation of this
     * should basically pass an SQL query to populateData($sql), or whatever it takes
     * to populate the $data array.
     */
    abstract function getSiteData();

    /*
     * override of abstract method in parent
     */
    function getData() {
        $this->getSiteData();
        $this->sortAndFilter();

        debugMsg("SiteIndex::getData data", $this->data);

        return $this->data;
    }

    function sortAndFilter() {
        global $sidxactive;
        global $countries;
        global $debug;
        global $php_webroot;

        // Add link to site column
        $newData = array();
        $urlDomain = NULL;
        foreach ($this->data as $key => $d) {
            $dateArr = explode('-', $d['lastupload_date']);

            if (count($dateArr) != 3) {
                $dir = "";
                $date = "";

            } else {
                $month = $dateArr[1];
                $day =  explode(' ', $dateArr[2])[0];
                $year = $dateArr[0];
                $timestamp = mktime(0,0,0,$month,$day,$year);

                $dir = date('dmy', $timestamp);
                $date = date("Y-m-d", $timestamp);

            }
            $site = $d['site'];
            /* assume we always have a site_type - this depends on child class including it in SQL */
            $oss = strtolower($d['site_type']);
            if ( $d['ddp'] == '') {
                $indexPageURL = "index.php";
            } else {
                if ( is_null($urlDomain) ) {
                    $domainParts = explode(".",$_SERVER['SERVER_NAME']);
                    array_shift($domainParts);
                    $urlDomain = implode(".",$domainParts);
                }
                $indexPageURL = $_SERVER['REQUEST_SCHEME'] . "://" . $d['ddp'] . "." . $urlDomain . $php_webroot . "/index.php";
            }
            $d['site'] = "<a href=\"" . $indexPageURL . "?site=" .
                       $site . "&oss=" . $oss . "&dir=" . $dir . "&date=" . $date . "\" " .
                       ">". $site . "</a>";

            if ( isset($d['country']) && array_key_exists($d['country'],$countries) ) {
                if ( $debug ) { echo "<pre>sortAndFilter: country=\"" . $d['country'] . "\"<pre>\n"; }
                $d['country'] = $countries[$d['country']];
            }

            // Filter out in-active sites
            $showRow = true;
            if ( ( $sidxactive == '' || $sidxactive == 'active' ) && ( isset($d['site_status']) && $d['site_status'] == 'inactive' ) ) {
                $showRow = false;
            }

            if ( $showRow ) {
                $newData[] = $d;
            }
        }

        $this->data = $newData;
        return $this->data;
    }

    function populateData($sql, $doSystemStatus = false) {
        foreach ( $this->statsDBs as $ddp => $statsDB ) {
            $statsDB->query($sql);
            if ( count($this->columnTypes) == 0 ) {
                $this->columnTypes = $statsDB->getColumnTypes();
            }
            while ( $row = $statsDB->getNextNamedRow() ) {
                $row['ddp'] = $ddp;
                $this->data[] = $row;
            }
        }

        usort($this->data, function($a, $b)
        {
            if ( $this->defaultOrderBy === 'ASC' ) {
                return strcmp($b[$this->defaultOrderBy], $a[$this->defaultOrderBy]);
            } else {
                return strcmp($a[$this->defaultOrderBy], $b[$this->defaultOrderBy]);
            }
        });
    }
}

class OSSSiteIndex extends SiteIndex {
    function __construct($cols = array()) {
        $this->columnFormatters['wran_net_size']  = array('forceSortAsNums' => 1);
        $this->columnFormatters['gsm_net_size']   = array('forceSortAsNums' => 1);
        $this->columnFormatters['core_net_size']  = array('forceSortAsNums' => 1);
        $this->columnFormatters['lte_net_size']   = array('forceSortAsNums' => 1);
        $this->columnFormatters['tdran_net_size'] = array('forceSortAsNums' => 1);

        # Add OSS-specific columns
        $this->allcols['ossver'] = "OSS Version (Full)";
        $this->allcols['wran_net_size'] = "WRAN net. size";
        $this->allcols['gsm_net_size'] = "GRAN net. size";
        $this->allcols['core_net_size'] = "Core net. size";
        $this->allcols['lte_net_size'] = "LTE net. size";
        $this->allcols['tdran_net_size'] = "TDRAN net. size";
        foreach (array('wran_net_size','gsm_net_size','core_net_size','lte_net_size','tdran_net_size') as $colName) {
            $this->defaultCols[] = $colName;
        }
        parent::__construct($cols);
    }

    function getSiteData() {
        $sql = "
            SELECT
                sites.name AS site,
                site_type,
                utilver,
                lastupload AS lastupload_date,
                country,
                site_status,
                operators.name AS operator,
                IFNULL( oss_ver.wran_net_size, '' ) AS wran_net_size,
                IFNULL( oss_ver.gsm_net_size, '' ) AS gsm_net_size,
                IFNULL( oss_ver.core_net_size, '' ) AS core_net_size,
                IFNULL( oss_ver.lte_net_size, '' ) AS lte_net_size,
                IFNULL( oss_ver.tdran_net_size, '' ) AS tdran_net_size,
                oss_ver_names.name AS ossver,
                oss_ver_names.name AS sitever
            FROM
                sites,
                operators,
                oss_ver,
                oss_ver_names
            WHERE
                site_type = 'OSS' AND
                operators.id = sites.oper_id AND
                sites.id = oss_ver.siteid AND
                oss_ver.date = DATE(sites.lastupload) AND
                oss_ver_names.id = oss_ver.verid
            " . $this->genericFilter . $this->filter;
        $this->populateData($sql);
        /* rewrite the ossver to make a version string shortened */
        foreach ($this->data as $key => $d) {
            // Strip extraneous text from the OSS version
            // TODO: merge into one call with arrays
            $d['sitever'] = preg_replace('/OSSRC_/', '', $d['ossver']);
            $d['sitever'] = preg_replace('/_Shipment_/i', ' Sh. ', $d['sitever']);
            $d['sitever'] = preg_replace('/AOM_[0-9][0-9][0-9][0-9][0-9][0-9].*/', '', $d['sitever']);
            $d['sitever'] = preg_replace('/_/', '.', $d['sitever']);
            $this->data[$key] = $d;
        }
    }
}

class SOSiteIndex extends SiteIndex {
    function __construct($cols = array()) {
        parent::__construct($cols);
    }

    function getSiteData() {
        $sql = "
            SELECT
                sites.name AS site,
                site_type,
                utilver,
                lastupload AS lastupload_date,
                \"ServiceOn version info not collected\" AS sitever,
                country,
                site_status,
                operators.name AS operator
            FROM
                sites,
                operators
            WHERE
                site_type = 'SERVICEON' AND
                operators.id = sites.oper_id
            " . $this->genericFilter . $this-> filter;
        $this->populateData($sql);
    }
}

class TORSiteIndex extends SiteIndex {

    function __construct($cols = array()) {
        $this->columnFormatters['node_count']  = array('forceSortAsNums' => 1, 'ddpFormatNumber' => 1);

        # Add TOR-specific columns
        $this->allcols['node_count'] = "Node Count";
        $this->allcols['cell_count'] = "Cell Count";
        $this->allcols['deployment_type'] = "Deployment Type";
        $this->allcols['deployinfra'] = "Deployment Infrastructure";
        foreach (array('node_count', 'cell_count', 'deployment_type', 'deployinfra') as $colName) {
            $this->defaultCols[] = $colName;
        }
        parent::__construct($cols);
    }

    function getSiteData() {
        $sql = "
SELECT
 sites.name AS site,
 site_type,
 utilver,
 lastupload AS lastupload_date,
 country,
 site_status,
 deploy_infra.name AS deployinfra,
 operators.name AS operator,
 tor_ver_names.name AS sitever,
 SUM( IFNULL(enm_network_element_details.count, 0) ) AS node_count,
 IFNULL(enm_site_info.cellcount, 0) AS cell_count,
 enm_site_info.deployment_type AS deployment_type
FROM
 sites
LEFT JOIN tor_ver ON
 tor_ver.siteid = sites.id AND
 tor_ver.date = DATE(sites.lastupload)
LEFT JOIN tor_ver_names ON
 tor_ver_names.id = tor_ver.verid
JOIN operators ON
 operators.id = sites.oper_id
LEFT OUTER JOIN deploy_infra ON
 deploy_infra.id = sites.infra_id
LEFT JOIN enm_network_element_details ON
 enm_network_element_details.siteid = sites.id AND
 enm_network_element_details.date = DATE(sites.lastupload)
LEFT OUTER JOIN enm_site_info ON
 enm_site_info.siteid = sites.id AND
 enm_site_info.date = DATE(sites.lastupload)
WHERE
 site_type = 'TOR' AND
 sites.lastupload IS NOT NULL " .
             $this->genericFilter . $this->filter .
             " GROUP BY site, site_type, utilver, lastupload_date, country, site_status, operator, sitever,
              deployinfra";
        $this->populateData($sql);

        foreach ($this->data as $key => $value) {
            // Insert contextual text and hyperlink
            $value['sitever'] = formatTorVersion($value['sitever']);
            $this->data[$key] = $value;
        }
    }
}

class ENIQSiteIndex extends SiteIndex {
    function __construct($cols = array()) {
        parent::__construct($cols);
    }

    function getSiteData() {
        $sql = "
            SELECT
                sites.name AS site,
                site_type,
                utilver,
                lastupload AS lastupload_date,
                country,
                site_status,
                operators.name AS operator,
                eniq_ver_names.name AS sitever
            FROM
                sites,
                operators,
                eniq_ver,
                eniq_ver_names
            WHERE
                site_type = 'ENIQ' AND
                eniq_ver.siteid = sites.id AND
                eniq_ver.date = DATE(sites.lastupload) AND
                eniq_ver_names.id = eniq_ver.verid AND
                operators.id = sites.oper_id
            " . $this->genericFilter . $this->filter;
        $this->populateData($sql);
    }
}

class NavigatorSiteIndex extends SiteIndex {
    function __construct($cols = array()) {
        parent::__construct($cols);
    }

    function getSiteData() {
        $sql = "
            SELECT
                sites.name AS site,
                site_type,
                utilver,
                lastupload AS lastupload_date,
                country,
                site_status,
                operators.name AS operator,
                'No Version Information Available' AS sitever
            FROM
                sites,
                operators
            WHERE
                site_type = 'NAVIGATOR' AND
                operators.id = sites.oper_id
            " . $this->genericFilter . $this->filter;
        $this->populateData($sql);
    }
}

class EoSiteIndex extends SiteIndex {
    function __construct($cols = array()) {
        # Add EO-specific columns
        $this->allcols['deployinfra'] = "Deployment Infrastructure";
        foreach (array('deployinfra') as $colName) {
            $this->defaultCols[] = $colName;
        }
        parent::__construct($cols);
    }

    public function getSiteData() {
        $sql = "
SELECT
    sites.name AS site,
    site_type,
    utilver,
    lastupload AS lastupload_date,
    country,
    site_status,
    deploy_infra.name AS deployinfra,
    operators.name AS operator,
    eo_ver_names.name AS sitever
FROM sites
JOIN operators ON operators.id = sites.oper_id
LEFT OUTER JOIN deploy_infra ON deploy_infra.id = sites.infra_id
LEFT OUTER JOIN eo_ver ON eo_ver.siteid = sites.id AND eo_ver.date = DATE(sites.lastupload)
LEFT OUTER JOIN eo_ver_names ON eo_ver.verid = eo_ver_names.id
WHERE
    sites.site_type = 'EO' AND
    sites.lastupload IS NOT NULL "
        . $this->genericFilter . $this->filter;
        $this->populateData($sql);
    }
}

class GenericSiteIndex extends SiteIndex {
    function __construct($cols = array()) {
        # Add GENERIC-specific columns
        $this->allcols['deployinfra'] = "Deployment Infrastructure";
        foreach (array('deployinfra') as $colName) {
            $this->defaultCols[] = $colName;
        }
        parent::__construct($cols);
    }

    public function getSiteData() {
        $sql = "
        SELECT
            sites.name AS site,
            site_type,
            utilver,
            lastupload AS lastupload_date,
            country,
            site_status,
            deploy_infra.name AS deployinfra,
            operators.name AS operator,
            generic_ver_names.name AS sitever
       FROM sites
           JOIN operators ON operators.id = sites.oper_id
           LEFT OUTER JOIN deploy_infra ON deploy_infra.id = sites.infra_id
           LEFT OUTER JOIN generic_ver ON generic_ver.siteid = sites.id AND generic_ver.date = DATE(sites.lastupload)
           LEFT OUTER JOIN generic_ver_names ON generic_ver.verid = generic_ver_names.id
       WHERE
           sites.site_type = 'GENERIC'"
       . $this->genericFilter . $this->filter;
        $this->populateData($sql);
    }
}

class BasicSiteIndex extends SiteIndex {
    public function __construct($siteType, $cols = array()) {
        parent::__construct($cols);
        $this->siteType = $siteType;
    }

    public function getSiteData() {
        $sql = "
            SELECT
                sites.name AS site,
                site_type,
                utilver,
                lastupload AS lastupload_date,
                country,
                site_status,
                operators.name AS operator,
                'No Version Information Available' AS sitever
            FROM
                sites,
                operators
            WHERE
                operators.id = sites.oper_id AND
                site_type = '$this->siteType'
            " . $this->genericFilter . $this->filter;
        $this->populateData($sql);
    }
}

$statsDBs = array();
$statsDBs[''] = new StatsDB();

if ( isset($clusteredOverview) ) {
    foreach ( $clusteredOverview as $host ) {
        if ( $debug ) { echo "<pre>Adding $host</pre>\n"; }
        $statsDBs[$host] = new StatsDB(StatsDB::ACCESS_READ_ONLY, $host);
    }
}

$_COOKIE['site_type'] = $siteTypeView;
switch ($siteTypeView) {
case "OSS":
    $sidx = new OSSSiteIndex($sidxcols);
    break;
case "ENIQ":
    $sidx = new ENIQSiteIndex($sidxcols);
    break;
case "TOR":
    $sidx = new TORSiteIndex($sidxcols);
    break;
case "SERVICEON":
    $sidx = new SOSiteIndex($sidxcols);
    break;
case "NAVIGATOR":
    $sidx = new NavigatorSiteIndex($sidxcols);
    break;

case "EO":
    $sidx = new EoSiteIndex($sidxcols);
    break;

case "GENERIC":
    $sidx = new GenericSiteIndex($sidxcols);
    break;
default:
    $sidx = new BasicSiteIndex($siteTypeView, $sidxcols);
}


if (isset($sidx) && is_object($sidx)) {
    if ($sidxoper != "") {
        $sidx->filter .= " AND operators.name = \"" . $statsDBs['']->escape($sidxoper) . "\"";
    }
    if ($sidxcountry != "") {
        $sidx->filter .= " AND country = '" . $statsDBs['']->escape($sidxcountry) . "'";
    }
    if ($sidxstatus != "") {
        $sidx->filter .= " AND site_status = '" . $statsDBs['']->escape($sidxstatus) . "'";
    }

    if (isset($opname)) {
        $sidx->filter .= " AND operators.name LIKE '" . $statsDBs['']->escape($opname) . "'";
    }

    $sidx->statsDBs = $statsDBs;
}
?>
<div id=custom class=custom>
<div class=custombody>
<form name=customise method=POST>
<fieldset>
<legend>Filter</legend>
<label for=sidxoper>Operator</label>
<select name=sidxoper>
<option value="">-- All Operators --</option>
<?php
$operators = array();
foreach ( $statsDBs as $statsDB ) {
    $statsDB->query("SELECT name FROM operators order by name asc");
    while ($row = $statsDB->getNextNamedRow()) {
        $operators[$row['name']] = 1;
    }
}
$operators = array_keys($operators);
foreach ( $operators as $name ) {
    $selected = "";
    if ( isset($sidxoper) && $name == $sidxoper) {
        $selected = " selected";
    }

    if ($name == "") {
        $display = "-- No Operator Defined --";
    } else {
        $display = $name;
    }

    echo "<option value='" . $name . "'" . $selected . ">" . $display . "</option>\n";
}
?>
</select>
<label for=sidxcountry>Country</label>
<select name=sidxcountry>
<option value="">-- Any Country --</option>
<?php
// Filter only by countries in use
$operfilter = "";
if ($sidxoper != "") {
    $operfilter = " AND operators.name = \"" . $sidxoper . "\"";
}
$used_countries = array();
foreach ( $statsDBs as $statsDB ) {
    $querySql = <<<ESQL
SELECT DISTINCT country
FROM sites, operators
WHERE
 sites.oper_id = operators.id AND country != ''
 $operfilter
ORDER BY country
ESQL;
    $statsDB->query($querySql);
    while ($row = $statsDB->getNextNamedRow()) {
        $used_countries[$row['country']] = 1;
    }
}
$used_countries = array_keys($used_countries);
$sortedarray = array();
foreach ( $used_countries as $country ) {
    $sortedarray[$country] = $countries[$country];
}
asort($sortedarray);
foreach ( $sortedarray as $countryid => $value ) {
    $selected = "";
    if ( isset($sidxcountry) && $countryid == $sidxcountry) {
        $selected = " selected";
    }
    if ($countryid == null) {
        $text = "-- No Country Defined --";
    } else {
        $text = $value;
    }
    echo "<option value='" . $countryid . "'" . $selected . ">" . $text . "</option>\n";
}
?>
</select>
<label for=sidxstatus>Site Status</label>
<select name=sidxstatus>
<?php
$statuses = array('', 'live','lab');
foreach ($statuses as $status) {
    $selected = "";
    if ( isset($sidxstatus) && $status == $sidxstatus) {
        $selected = " selected";
    }
    echo "<option value='" . $status . "'" . $selected . ">" . $status . "</option>\n";
}
?>
</select>
<label for=sidxactive>Site Active</label>
<select name=sidxactive>
<?php
$activeChoices = array('active' => 'Active Only', 'all' => 'All');
foreach ($activeChoices as $activeValue => $activeLabel) {
    $selected = "";
    if ( isset($sidxactive) && $activeValue == $sidxactive) {
        $selected = " selected";
    }
    echo " <option value='" . $activeValue . "'" . $selected . ">" . $activeLabel . "</option>\n";
}
?>
</select>
</fieldset>
<fieldset>
<legend>Show Columns</legend>
<?php
foreach ($sidx->allcols as $col => $title) {
    if (isset($sidx->cols[$col])) $selected = " checked";
    else $selected = "";
    echo "<label for='c_" . $col . "'><input type=checkbox name=\"c_" . $col . "\"" . $selected . ">" . $title . "</label>\n";
    //echo "<input type=checkbox name=\"c_" . $col . "\"" . $selected . "><label for='c_" . $col . "'>" . $title . "</label>\n";
}
?>
<br/>Some columns will be empty depending on the DDC version installed on the server, and / or the last time data was processed.
</fieldset>
<input type=submit name=submit value="Update View ...">
</form>
</div></div>
<div id=content>
<?php
if (isset($sidx) && is_object($sidx)) {
?>
<a href=# onClick="document.getElementById('custom').style.display = 'block';">(Customise View)</a>
<?php
}
?>
<div id=site_type_menu>
<ul id=site_type_list>
<?php
$activeSiteTypes = array();
foreach ( $statsDBs as $statsDB ) {
    $statsDB->query("SELECT DISTINCT site_type FROM sites");
    while ($row = $statsDB->getNextRow()) {
        $activeSiteTypes[$row[0]] = 1;
    }
}
$activeSiteTypes = array_keys($activeSiteTypes);
if ( $debug > 0 ) { echo "<pre>activeSiteTypes:\n"; print_r($activeSiteTypes); echo "</pre>\n"; }
$types = array(
    "OSS" => "OSS",
    'ENIQ' => "ENIQ",
    'TOR' => 'ENM',
    'NAVIGATOR' => 'Navigator',
    'SERVICEON' => "Service On",
    'EO' => 'EO',
    'ECSON' => 'ECSON',
    "GENERIC" => "Generic"
);
foreach ($types as $k => $v) {
  if ( in_array($k,$activeSiteTypes) ) {
    $class = " class=\"normal\"";
    if ($siteTypeView == $k) $class = " class=\"selected\"";
    echo "<li " . $class . "><a href=\"?site_type=" . $k . "\" " . $class . ">" . $v . "</a></li>\n";
  }
}

echo <<<EOT
</ul>
</div>
<div id=site_list>
EOT;

if (isset($sidx) && is_object($sidx)) {
    if (count($sidx->getData()) > 0) {
        echo $sidx->getClientSortableTableStr();
    } else {
        echo "<h2>No sites for this site type</h2>\n";
    }
} else {
    echo <<<EOT
<h2>Undefined site type</h2>
<p /><b>Please choose a site type to view from the menu above</b>
EOT;
}

echo "</div>\n";

include "common/finalise.php";

