<?php
$YUI_DATATABLE = true;
$pageTitle = "LV Logs";
include "common/init.php";

$statsDB = new StatsDB();

#
# Main Flow
#

require_once PHP_ROOT . "/classes/DDPObject.class.php";

class LVLogSummary extends DDPObject {
  var $cols = array('appname' => 'Application Name',
		    'total'   => 'Total Entries');
  var $title = "Daily Total";

  function getData() {
    global $date;
    global $site;
    $sql = "
SELECT lvlog_application_names.name AS appname, SUM(lvlog_entries_by_day.count) AS total
FROM  lvlog_entries_by_day, lvlog_application_names, sites 
WHERE 
 lvlog_entries_by_day.date = '$date' AND
 lvlog_entries_by_day.siteid = sites.id AND sites.name = '$site' AND 
 lvlog_entries_by_day.application_name = lvlog_application_names.id
GROUP BY lvlog_entries_by_day.application_name
ORDER BY total DESC";
	$this->populateData($sql);
	return $this->data;
    }
}

class LVLog extends DDPObject {
    var $allcols = array(
        "count" => "Count",
        "log_type" => "Log Type",
        "appname" => "Application Name",
        "command" => "Command",
        "type" => "Type",
        "severity" => "Severity",
        "addinfo" => "Additional Information"
    );

    var $cols = array();

    var $defaultOrderBy = "count";
    var $defaultOrderDir = "DESC";

    var $logType = "";

    // for certain log types we only want to see certain columns
    var $typeCols = array (
        "COMMAND" => array ("count","appname","command","addinfo"),
        "SYSTEM" => array("count","type","severity","addinfo"),
        "ERROR" => array("count","appname","command","type","severity","addinfo"),
        "SECURITY" => array("count","type","severity","addinfo"),
        "NETWORK" => array("count","appname","command","type","severity","addinfo")
    );

    function __construct($type = "", $showAll = false) {
        parent::__construct("lvlog");
        if ($type != "") {
            $this->logType = $type;
            if (! isset($this->typeCols[$type]) || $showAll) {
                $this->cols = $this->allcols;
            } else {
                foreach ($this->typeCols[$type] as $k) {
                    $this->cols[$k] = $this->allcols[$k];
                }
            }
        } else {
            $this->cols = $this->allcols;
        }
    }

    function getData() {
        global $site, $date;
        $sql = "
            SELECT log_type, lvlog_application_names.name AS appname, 
            lvlog_command_names.name AS command, lvlog_types.name AS type, severity, lvlog_additional_info.name AS addinfo, count
            FROM
            lvlog_entries_by_day, lvlog_application_names, lvlog_command_names, lvlog_types, lvlog_additional_info, sites
            WHERE
            siteid = sites.id AND sites.name = '" . $site . "' AND date = '" . $date . "' AND
            lvlog_application_names.id = application_name AND 
            lvlog_command_names.id = command_name AND lvlog_types.id = type AND lvlog_additional_info.id = additional_info
            ";
        if ($this->logType != "") $sql .= " AND log_type = '" . $this->statsDB->escape($this->logType) . "'";
        $this->populateData($sql);
        return $this->data;
    }
}

echo "<ul>\n";

$plotfile = $datadir . "/log_plots/lvlog.txt";
if ( file_exists($plotfile) ) {
  $graphURL = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=log_plots/lvlog.txt";
  echo " <li><a href=\"" . $graphURL . "\">Plot of log entries per MC/minute</a></li>\n";
}
echo " <li><a href=\"#totals\">Daily Totals</a></li>\n";
echo "</ul>\n";

$type = "";
if (isset($_GET['type_filter'])) $type = $_GET['type_filter'];
$showAll = false;
$lvl = new LVLog($type);
// TODO: move the filter definition to the DDPObject class???
?>
<form name=filter>
Log Type: <select name=filter onChange="changeURL(this.form, '?<?=$webargs?>', 'type');">
<?php
$colHelpInfo = "<ul>\n";
if ($type == "") echo "<option value='' selected>All Log Types</option>\n";
else echo "<option value=''>All Log Types</option>\n";
foreach ($lvl->typeCols as $key => $val) {
    if ($type == $key) echo "<option value=" . $key . " selected>" . ucfirst(strtolower($key)) . "</option>\n";
    else echo "<option value=" . $key . ">" . ucfirst(strtolower($key)) . "</option>\n";
    $colHelpInfo .= "<li>" . ucfirst(strtolower($key)) . ": " . implode(", ", $val) . "</li>\n";
}
$colHelpInfo .= "</ul>\n";
if ($type == "") $type = "All";
else $type = ucfirst(strtolower($type));
?>
</select>
</form>
<h1><?=$type;?> Logs<?drawHelpLink("loghelp");?></h1>
<?php
drawHelp("loghelp","Log Viewer Logs",
    "
    Log Viewer logs are retrieved from the Logs table in the lvlogdb database in Sybase. Several different types of logs are stored in the same
    table, and the applicable columns are different for each log type - although the OSS does not appear to enforce the use or otherwise of
    a specific column for a specific log type.
    <p/>
    Selecting a log type from the drop-down menu above will display log entries of only that type, and will restrict the columns visible to
    columns applicable to that log type. Available types and their columns are: " . $colHelpInfo);
$lvl->getSortableHtmlTable();

/* Totals */
echo "<a name=\"totals\"></a><H2>Daily Totals</H2>\n";
$totals = new LVLogSummary();
echo $totals->getClientSortableTableStr();

include "common/finalise.php";
?>
