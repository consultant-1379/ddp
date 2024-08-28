<?php
$pageTitle = "Workload Profiles";

include "../../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

const PROFILE_NUM_PARAM = "pn";
const PROFILE_CATID_PARAM = "cid";
const PROFILE_CAT_PARAM = "category";
const LOG_TABLE = "enm_profilelog";
const CATEGORY_TABLE = "enm_workload_profile_category";
const ERRORS_TABLE = "enm_workload_profile_errors";
const LOG_TABLE_TIME_COL = "date";
const PROFILE_LABEL = "Profile";
const CATEGORY_LABEL = "Category";
const PROFILE_KEY = "profile";
const PROFILE_NUM_KEY = "profilenum";
const ERR_PROFILE_NAME_DB = "CONCAT(enm_workload_profile_category.name, '_', enm_workload_profile_errors.profilenum)"; // NOSONAR
const LOG_PROFILE_NAME_DB = "CONCAT(enm_workload_profile_category.name, '_', enm_profilelog.profilenum)"; // NOSONAR
const LOG_PROFILE_STATE_DB = "enm_profilelog.state";
const THIS_PAGE = "/TOR/rv/workload.php";
const FORMAT_LINK_FN = "formatLink";

class ENMProfileLogsUtilityVersion extends DDPObject {
    var $cols = array(
        'version' => 'torutilities internal version (on Workload VM Server)'
    );
    var $title = "TORUTILITIES VERSION";

    function __construct() {
        parent::__construct("torutilitiesVersion");
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        global $php_webroot;
        $sql = "
SELECT
 torutilitiesVersion AS version
FROM enm_profilelog_utilversion, sites
WHERE
 enm_profilelog_utilversion.siteid = sites.id AND sites.name = '$site' AND
 enm_profilelog_utilversion.date = '$date'";
        $this->populateData($sql);
        return $this->data;
    }
}

class ProfileCategories extends DDPObject {
    var $cols = array(
        PROFILE_CAT_PARAM => 'Profile Category'
    );

    function __construct() {
        parent::__construct("Profile_Categories");
    }

    function getData() {
        global $date, $site;
        global $php_webroot, $webargs;

        $sql="
SELECT DISTINCT
  enm_workload_profile_category.name AS category,
  enm_profilelog.categoryid AS cid
FROM
  enm_profilelog, enm_workload_profile_category, sites
WHERE
  enm_profilelog.siteid = sites.id AND sites.name = '$site' AND
  enm_profilelog.date BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  enm_profilelog.categoryid = enm_workload_profile_category.id
ORDER BY category";

        $this->populateData($sql);
        foreach ($this->data as &$row) {
             $row[PROFILE_CAT_PARAM] = makeLink(
                 THIS_PAGE,
                 $row[PROFILE_CAT_PARAM],
                 array(
                     PROFILE_CATID_PARAM => $row[PROFILE_CATID_PARAM]
                 )
             );
        }
        return $this->data;
    }
}

function showProfilesGraph($cid, $pnum) {
    global $php_webroot, $webargs, $date, $statsDB;

    if ( ! is_null($cid) ) {
        $row = $statsDB->queryRow("SELECT name FROM enm_workload_profile_category WHERE id = $cid");
        $category = $row[0];
    }

    $tables = array(LOG_TABLE, CATEGORY_TABLE, "sites");
    $where = <<<ESQL
enm_profilelog.siteid = sites.id AND sites.name = '%s' AND
enm_profilelog.categoryid = enm_workload_profile_category.id
ESQL;
    $qargs = array('site');
    $targs = array();
    $extraArgs = array();
    if ( ! is_null($pnum) ) {
        $targs[] = PROFILE_CAT_PARAM;
        $targs[] = PROFILE_NUM_PARAM;
        $qargs[] = PROFILE_CATID_PARAM;
        $qargs[] = PROFILE_NUM_PARAM;
        $extraArgs[PROFILE_NUM_PARAM] = $pnum;
        $extraArgs[PROFILE_CATID_PARAM] = $cid;
        $extraArgs[PROFILE_CAT_PARAM] = $category;
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title("%s_%02d Profile")
                  ->titleArgs($targs)
                  ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                  ->yLabel("Sleeping:1. Starting:2. Stopping:3. Running:4. Error:5. Dead:6. Completed:7.")
                  ->addQuery(
                      LOG_TABLE_TIME_COL,
                      array( "state+0" => "State" ),
                      $tables,
                      $where . " AND enm_workload_profile_category.id = %d AND enm_profilelog.profilenum = %d",
                      $qargs
                  )
                  ->build();
        $height = 400;
    } elseif ( ! is_null($cid) ) {
        $targs[] = PROFILE_CAT_PARAM;
        $extraArgs[PROFILE_CAT_PARAM] = $category;
        $qargs[] = PROFILE_CATID_PARAM;
        $extraArgs[PROFILE_CATID_PARAM] = $cid;
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title("%s Profiles")
                  ->titleArgs($targs)
                  ->type(SqlPlotParam::XY)
                  ->yLabel(PROFILE_LABEL)
                  ->presetAgg(SqlPlotParam::AGG_SUM, SqlPlotParam::AGG_MINUTE)
                  ->addQuery(
                      LOG_TABLE_TIME_COL,
                      array( PROFILE_NUM_KEY => PROFILE_LABEL ),
                      $tables,
                      $where . " AND enm_workload_profile_category.id = %d",
                      $qargs,
                      LOG_PROFILE_STATE_DB
                  )
                  ->build();
        $targs[] = PROFILE_CAT_PARAM;
        $where = $where . " AND enm_workload_profile_category.id = %d";
        $height = 400;
    } else {
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title("All Profiles")
                  ->type(SqlPlotParam::XY)
                  ->yLabel(PROFILE_LABEL)
                  ->addQuery(
                      LOG_TABLE_TIME_COL,
                      array( "(categoryid*100) + profilenum" => PROFILE_LABEL ),
                      $tables,
                      $where,
                      $qargs,
                      LOG_PROFILE_STATE_DB
                  )
                  ->build();
        $height = 400;
    }

    drawHeaderWithHelp("Profile States", 2, "profilesGraphHelp", "DDP_Bubble_241_ENM_Workload_Profiles_Graph");

    $extraArgsStrs = array();
    foreach ( $extraArgs as $name => $value ) {
        $extraArgsStrs[] = $name . "=" . $value;
    }
    $extraArgsStr = implode("&", $extraArgsStrs);

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $link = $sqlParamWriter->getImgURL(
        $id,
        "$date 00:00:00",
        "$date 23:59:59",
        true,
        800,
        $height,
        $extraArgsStr
    );
    echo "<p>" . $link . "</p>\n";
}

function showProfileNumbers() {
    global $site, $date;

    $where = <<<ESQL
enm_profilelog.siteid = sites.id AND sites.name = '$site' AND
enm_profilelog.date BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_profilelog.categoryid = enm_workload_profile_category.id
ESQL;

    $table = SqlTableBuilder::init()
           ->name("profile_numbers")
           ->tables(array(LOG_TABLE, CATEGORY_TABLE, StatsDB::SITES))
           ->where($where)
           ->addColumn(
               PROFILE_NUM_KEY,
               "DISTINCT (enm_workload_profile_category.id*100) + enm_profilelog.profilenum",
               "Number"
           )
           ->addSimpleColumn(LOG_PROFILE_NAME_DB, PROFILE_LABEL)
           ->sortBy(PROFILE_NUM_KEY, DDPTable::SORT_ASC)
           ->paginate()
           ->build();
    echo $table->getTable();
}

function showProfileErrors() {
    global $site, $date;

    $where = <<<ESQL
enm_workload_profile_errors.siteid = sites.id AND sites.name = '$site' AND
enm_workload_profile_errors.date = '$date' AND
enm_workload_profile_errors.categoryid = enm_workload_profile_category.id
ESQL;

    $table = SqlTableBuilder::init()
           ->name("profile_errors")
           ->tables(array(ERRORS_TABLE, CATEGORY_TABLE, StatsDB::SITES))
           ->where($where)
           ->addSimpleColumn(ERR_PROFILE_NAME_DB, PROFILE_LABEL)
           ->addColumn("errorcount", "enm_workload_profile_errors.errcount", "Error Count")
           ->sortBy("errorcount", DDPTable::SORT_DESC)
           ->paginate()
           ->build();
    echo $table->getTable();
}

function writeFormatFunction() {
    $baseURL = makeSelfLink();
    echo <<<ESCRIPT
<script type="text/javascript">
function formatLink(elCell, oRecord, oColumn, oData) {
    var cid = oRecord.getData("cid");
    var refLink = "$baseURL" + "&cid=" + cid;
    if ( oColumn.field == "profile" ) {
        var pn = oRecord.getData("pn");
        refLink = refLink + "&pn=" + pn;
    }
    elCell.innerHTML = "<a href=\"" + refLink + "\">" + oData + "</a>";
}
</script>
ESCRIPT;
}

function showProfileEventCounts() {
    global $site, $date;

    writeFormatFunction();

    $where = <<<ESQL
enm_profilelog.siteid = sites.id AND sites.name = '$site' AND
enm_profilelog.date BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_profilelog.categoryid = enm_workload_profile_category.id
ESQL;

    $table = SqlTableBuilder::init()
           ->name("profile_event_counts")
           ->tables(array(LOG_TABLE, CATEGORY_TABLE, StatsDB::SITES))
           ->where($where)
           ->addColumn(PROFILE_CAT_PARAM, "enm_workload_profile_category.name", CATEGORY_LABEL, FORMAT_LINK_FN)
           ->addColumn(PROFILE_KEY, LOG_PROFILE_NAME_DB, PROFILE_LABEL, FORMAT_LINK_FN)
           ->addColumn("count", "COUNT(*)", "#Events")
           ->addHiddenColumn(PROFILE_CATID_PARAM, "enm_profilelog.categoryid")
           ->addHiddenColumn(PROFILE_NUM_PARAM, "enm_profilelog.profilenum")
           ->groupBy(array(PROFILE_CATID_PARAM, PROFILE_NUM_PARAM))
           ->sortBy("count", DDPTable::SORT_DESC)
           ->paginate()
           ->build();
    echo $table->getTable();
}

function showProfileEvents() {
    global $site, $date;

    writeFormatFunction();

    $where = <<<ESQL
enm_profilelog.siteid = sites.id AND sites.name = '$site' AND
enm_profilelog.date BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_profilelog.categoryid = enm_workload_profile_category.id
ESQL;

    $cid = requestValue("cid");
    $pn = requestValue("pn");
    if ( ! is_null($cid) ) {
        $where .=  " AND enm_profilelog.categoryid = $cid";
    }
    if ( ! is_null($pn) ) {
        $where .= " AND enm_profilelog.profilenum = $pn";
    }

    $table = SqlTableBuilder::init()
           ->name("profile_events")
           ->tables(array(LOG_TABLE, CATEGORY_TABLE, StatsDB::SITES))
           ->where($where)
           ->addColumn("time", "enm_profilelog.date", "Time", DDPTable::FORMAT_TIME)
           ->addColumn(PROFILE_CAT_PARAM, "enm_workload_profile_category.name", CATEGORY_LABEL, FORMAT_LINK_FN)
           ->addColumn(PROFILE_KEY, LOG_PROFILE_NAME_DB, PROFILE_LABEL, FORMAT_LINK_FN)
           ->addHiddenColumn(PROFILE_CATID_PARAM, "enm_profilelog.categoryid")
           ->addHiddenColumn(PROFILE_NUM_PARAM, "enm_profilelog.profilenum")
           ->addSimpleColumn(LOG_PROFILE_STATE_DB, "State")
           ->sortBy("time", DDPTable::SORT_ASC)
           ->paginate()
           ->build();
    echo $table->getTable();
}

function mainFlow() {
    global $php_webroot, $webargs;

    drawHeaderWithHelp("Workload", 2, "ProfilesLogsHelp", "DDP_Bubble_148_ENM_Logs_Profiles_Log");

    $torUtilityVersion = new ENMProfileLogsUtilityVersion();
    echo $torUtilityVersion->getClientSortableTableStr();
    echo "<br/>";

    echo makeLink("/TOR/rv/workload.php", "Profile Number Mapping", array("action"=>"shownum"));
    showProfilesGraph(null, null);

    drawHeaderWithHelp("Profile State Changes", 2, "statechanges");
    showProfileEventCounts();

    drawHeaderWithHelp("Profile Errors", 2, "errors");
    showProfileErrors();
}

$cid = requestValue(PROFILE_CATID_PARAM);
$pn = requestValue(PROFILE_NUM_PARAM);
$action = requestValue("action");
if ( (! is_null($action)) && $action === "shownum" ) {
    showProfileNumbers();
} elseif ( (! is_null($pn)) || (! is_null($cid)) ) {

  echo "<p><a href='$php_webroot/TOR/rv/workload.php?$webargs'>Back to Workload page</a></p>";

  # We use a simple html table to show the categories beside the graph.
  echo '<table><tr><td valign="top">';
  drawHeaderWithHelp("Categories", 2, "ProfileCategoriesHelp", "DDP_Bubble_242_ENM_Workload_Profile_Categories");
  $ProfileCategories = new ProfileCategories();
  echo $ProfileCategories->getClientSortableTableStr();

  echo '</td><td valign="top">';
  showProfilesGraph( $cid, $pn );
  echo "</td></tr></table>";

  drawHeaderWithHelp("Profiles Log", 2, "ProfilesInfoHelp", "DDP_Bubble_150_Profiles_Info_Log");
  showProfileEvents();
} else {
  mainFlow();
}

include PHP_ROOT . "/common/finalise.php";
