<?php
$pageTitle = "Log Analysis";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

const SERVER = 'serverName';
const LBL = 'label';
const SORT_OPT = 'sortOptions';
const F_SORT_NUMS = 'forceSortAsNums';
const SORT_FUNCT = 'sortFunction';
const FORMATTER = 'formatter';
const FORM_NUM = 'ddpFormatNumber';
const MAX_NUM_DATE = 'maxNumberDate';
const MAX_SIZE_DATE = 'maxSizeDate';

$startDate = $_GET['start'];
$endDate   = $_GET['end'];

class topLoggersByNumber extends DDPObject {
    var $cols = array(
        array(
            'key' => SERVER,
            LBL => 'Server'
        ),
        array(
            'key' => 'totalNumber',
            LBL => 'Total Number',
            FORMATTER => FORM_NUM,
            SORT_OPT => array(
                SORT_FUNCT => F_SORT_NUMS
            )
        ),
        array(
            'key' => 'avgNumber',
            LBL => 'Avg Number',
            FORMATTER => FORM_NUM,
            SORT_OPT => array(
                SORT_FUNCT => F_SORT_NUMS
            )
        ),
        array(
            'key' => 'maxNumber',
            LBL => 'Max Number',
            FORMATTER => FORM_NUM,
            SORT_OPT => array(
                SORT_FUNCT => F_SORT_NUMS
            )
        ),
        array(
            'key' => MAX_NUM_DATE,
            LBL => 'Date of Max Number'
        ),
    );

    function __construct() {
        parent::__construct("topLoggersByNumber");
    }

    function getData() {
      global $startDate;
      global $endDate;
      global $site;
      global $php_webroot;
      global $oss;

      $sql = "
SELECT
  base_results.serverName AS serverName,
  IFNULL(number_results.totalNumber, 'NA') AS totalNumber,
  IFNULL(number_results.avgNumber, 'NA') AS avgNumber,
  IFNULL(number_results.maxNumber, 'NA') AS maxNumber,
  max(base_results.date) AS maxNumberDate
FROM
  ( SELECT
      IFNULL(servers.hostname, 'Unknown') AS serverName,
      enm_logs.entries AS entries,
      enm_logs.size AS size,
      enm_logs.date AS date
    FROM
      enm_logs
      JOIN sites
        ON enm_logs.siteid = sites.id
      LEFT JOIN servers
        ON enm_logs.serverid = servers.id
    WHERE
      sites.name = '$site'
      AND enm_logs.date between '$startDate' AND '$endDate'
  ) AS base_results
  JOIN
    ( SELECT
        IFNULL(servers.hostname, 'Unknown') AS serverName,
        SUM(enm_logs.entries) AS totalNumber,
        ROUND(AVG(enm_logs.entries)) AS avgNumber,
        MAX(enm_logs.entries) AS maxNumber
      FROM
        enm_logs
        JOIN sites
          ON enm_logs.siteid = sites.id
        LEFT JOIN servers
          ON enm_logs.serverid = servers.id
      WHERE
        sites.name = '$site'
        AND enm_logs.date BETWEEN '$startDate' AND '$endDate'
      GROUP BY serverName
    ) AS number_results
    ON number_results.serverName = base_results.serverName
    AND base_results.entries = number_results.maxNumber
GROUP BY serverName, totalNumber, avgNumber, maxNumber
ORDER BY cast(totalNumber as unsigned) DESC";
        # The final group by above is because there could be multiple dates with the same max number, so we pick the max date from them.
        $this->populateData($sql);

        $newData = array();
        foreach ($this->data as $key => $d) {
            $server = $d[SERVER];
            $maxNumberDate = $d[MAX_NUM_DATE];
            $dateArr = preg_split('/-/', $maxNumberDate);
            $dir = date('dmy', mktime(0,0,0,$dateArr[1],$dateArr[2],$dateArr[0]));
            $link = "<a href='$php_webroot/TOR/system/elasticsearch.php?site=$site&dir=$dir";
            $link .= "&date=$maxNumberDate&oss=$oss&logdir=enmlogs&host=$server'>$maxNumberDate</a>";
            $d[MAX_NUM_DATE] = $link;
            $newData[] = $d;
        }

        $this->data = $newData;

        $this->defaultOrderBy = "totalNumber";
        $this->defaultOrderDir = "DESC";
        return $this->data;
    }
}


class topLoggersBySize extends DDPObject {
    var $cols = array(
        array(
            'key' => SERVER,
            LBL => 'Server'
        ),
        array(
            'key' => 'totalSize',
            LBL => 'Total Size (KB)',
            FORMATTER => FORM_NUM,
            SORT_OPT => array(
                SORT_FUNCT => F_SORT_NUMS
            )
        ),
        array(
            'key' => 'avgSize',
            LBL => 'Avg Size (KB)',
            FORMATTER => FORM_NUM,
            SORT_OPT => array(
                SORT_FUNCT => F_SORT_NUMS
            )
        ),
        array(
            'key' => 'maxSize',
            LBL => 'Max Size (KB)',
            FORMATTER => FORM_NUM,
            SORT_OPT => array(
                SORT_FUNCT => F_SORT_NUMS
            )
        ),
        array(
            'key' => MAX_SIZE_DATE,
            LBL => 'Date of Max Size'
        )
    );

    function __construct() {
        parent::__construct("topLoggersBySize");
    }

    function getData() {
      global $startDate;
      global $endDate;
      global $site;
      global $php_webroot;
      global $oss;

      $sql = "
SELECT
  base_results.serverName AS serverName,
  IFNULL(size_results.totalSize, 'NA') AS totalSize,
  IFNULL(size_results.avgSize, 'NA') AS avgSize,
  IFNULL(size_results.maxSize, 'NA') AS maxSize,
  max(base_results.date) AS maxSizeDate
FROM
  ( SELECT
      IFNULL(servers.hostname, 'Unknown') AS serverName,
      enm_logs.entries AS entries,
      enm_logs.size AS size,
      enm_logs.date AS date
    FROM
      enm_logs
      JOIN sites
        ON enm_logs.siteid = sites.id
      LEFT JOIN servers
        ON enm_logs.serverid = servers.id
    WHERE
      sites.name = '$site'
      AND enm_logs.date between '$startDate' AND '$endDate'
  ) AS base_results
  JOIN
    ( SELECT
        IFNULL(servers.hostname, 'Unknown') AS serverName,
        SUM(enm_logs.size) AS totalSize,
        ROUND(AVG(enm_logs.size)) AS avgSize,
        MAX(enm_logs.size) AS maxSize
      FROM
        enm_logs
        JOIN sites
          ON enm_logs.siteid = sites.id
        LEFT JOIN servers
          ON enm_logs.serverid = servers.id
      WHERE
        sites.name = '$site'
        AND enm_logs.date BETWEEN '$startDate' AND '$endDate'
      GROUP BY serverName
    ) AS size_results
    ON size_results.serverName = base_results.serverName
    AND base_results.size = size_results.maxSize
GROUP BY serverName, totalSize, avgSize, maxSize
ORDER BY cast(totalSize as unsigned) DESC";
        # The final group by above is because there could be multiple dates with the same max size, so we pick the max date from them.
        $this->populateData($sql);

        $newData = array();
        foreach ($this->data as $key => $d) {
            $server = $d[SERVER];
            $maxSizeDate = $d[MAX_SIZE_DATE];
            $dateArr = preg_split('/-/', $maxSizeDate);
            $dir = date('dmy', mktime(0,0,0,$dateArr[1],$dateArr[2],$dateArr[0]));
            $link = "<a href='$php_webroot/TOR/system/elasticsearch.php?site=$site&dir=$dir";
            $link .= "&date=$maxSizeDate&oss=$oss&logdir=enmlogs&host=$server'>$maxSizeDate</a>";
            $d[MAX_SIZE_DATE] = $link;
            $newData[] = $d;
        }
        $this->data = $newData;

        $this->defaultOrderBy = "totalSize";
        $this->defaultOrderDir = "DESC";
        return $this->data;
    }
}

function drawElasticFSUsedGraph() {
    global $site;
    global $startDate;
    global $endDate;
    global $year;
    global $month;

    $sqlParamWriter = new SqlPlotParam($year, $month);
    $graphTable = new HTML_Table("border=0");
    $sqlParam = array(
                      'title' => 'Elasticsearch Filesystem Usage (%)',
                      'ylabel' => "%",
                      'type' => 'sb',
                      'sb.barwidth' => 60,
                      'useragg' => 'true',
                      'persistent' => 'true',
                      'forcelegend' => 'true',
                      'querylist' => array(
                                           array(
                                                 'timecol' => 'date',
                                                 'whatcol' => array( 'volume_stats.used / volume_stats.size * 100' => 'Used (%)'),
                                                 'tables' => "sites, volume_stats, volumes, servers",
                                                 'where' => "servers.id = volume_stats.serverid"
                                                            . " AND sites.id = servers.siteid"
                                                            . " AND sites.name = '%s'"
                                                            . " AND volume_stats.volid = volumes.id"
                                                            . " AND volumes.name IN ('elasticsearchvol', 'elastic_fs')",
                                               'qargs' => array('site')
                                                 )
                                           )
                      );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphTable->addRow(array($sqlParamWriter->getImgURL($id, "$startDate 00:00:00", "$endDate 23:59:59", true, 640, 320)));

    echo $graphTable->toHTML();
}

function plotLogTrend($plotType, $serverNames, $startDate, $endDate, $year, $month) {
    global $site;
    global $oss;

    $unknownExists = false;
    $servers = explode(',', $serverNames);
    foreach ($servers as &$serverName) {
        if ( $serverName == 'Unknown' ) {
            $unknownExists = true;
        }
        $serverName = "'" . $serverName . "'";
    }
    $serverStr = implode(',', $servers);

    $title = '';
    $ylabel = '';
    $whatcol = array();
    if ( $plotType == 'log_count' ) {
        $title = 'Log Count Trend';
        $ylabel = 'Log Count';
        $whatcol['enm_logs.entries'] = 'Log Count';
    } else {
        $title = 'Log Size Trend';
        $ylabel = 'Log Size (KB)';
        $whatcol['enm_logs.size'] = 'Log Size (KB)';
    }

    $multiseries = '';
    $tables = '';
    $whereSql = '';
    if ( ! $unknownExists ) {
        # If the selected servers doesn't include 'Unknown' then the MySQL query is
        #  pretty straightfoward
        $multiseries = 'servers.hostname';
        $tables = "enm_logs, servers, sites";
        $whereSql = "enm_logs.siteid = sites.id AND
                     enm_logs.serverid = servers.id AND
                     servers.siteid = sites.id AND
                     servers.hostname IN (%s) AND
                     sites.name = '%s'";
    } else {
        # If the selected servers include 'Unknown' also then the query needs a few
        #  joins which, in my opinion, are too 'hack'ish to forward to qplot.php
        $multiseries = 'IFNULL(servers.hostname, "Unknown")';
        $tables = "enm_logs INNER JOIN sites ON
                       enm_logs.siteid = sites.id
                   LEFT OUTER JOIN servers ON
                       enm_logs.serverid = servers.id";
        $whereSql = "(servers.hostname IN (%s) OR servers.hostname IS NULL) AND
                     sites.name = '%s'";
    }

    $logTrendPlotParam = array(
                               'title'      => $title,
                               'type'       => 'tsc',
                               'ylabel'     => $ylabel,
                               'useragg'    => 'true',
                               'persistent' => 'true',
                               'querylist'  => array(
                                                     array(
                                                           'timecol' => 'enm_logs.date',
                                                           'multiseries' => $multiseries,
                                                           'whatcol' => $whatcol,
                                                           'tables' => $tables,
                                                           'where' => $whereSql,
                                                           'qargs' => array('servers', 'site')
                                                           )
                                                     )
                               );
    $logTrendPlotParamWriter = new SqlPlotParam($year, $month);
    $id = $logTrendPlotParamWriter->saveParams($logTrendPlotParam);
    $jsPlotUrl = $logTrendPlotParamWriter->getURL($id, "$startDate 00:00:00", "$endDate 23:59:59", "servers=$serverStr");
    echo <<<EOS
<script type="text/javascript">
    window.location.href = "$jsPlotUrl";
</script>
<noscript>
    <meta http-equiv="refresh" content="0; url='$jsPlotUrl'">
</noscript>
EOS;
}

$menuCode = '
function contextMenuHandler(p_sType, p_aArgs, p_dataTableObj) {
    var ctxMenuItem = p_aArgs[1];

    if ( ! ctxMenuItem ) {
        YAHOO.log("Returning as no ContextMenuItem object has been found", "info", "contextMenuHandler");
        return;
    }

    YAHOO.log("ctxMenuItem.groupIndex=" + ctxMenuItem.groupIndex  + ", ctxMenuItem.index=" + ctxMenuItem.index, "info", "contextMenuHandler");

    var selectedRows = p_dataTableObj.getSelectedRows();
    if ( selectedRows.length < 1 ) {
        YAHOO.log("Invalid number of rows selected: " + selectedRows.length, "warn", "contextMenuHandler");
        return;
    }

    var selectedServers = Array();
    var plotType = "";
    for ( var i = 0; i < selectedRows.length; i++ ) {
        var recordObj = p_dataTableObj.getRecord(selectedRows[i]);
        selectedServers.push(recordObj.getData("serverName"));
        if ( recordObj.getData("totalNumber") != null ) {
            plotType = "log_count";
        } else if ( recordObj.getData("totalSize") != null  ) {
            plotType = "log_size";
        }
    }

    url = esLogTrendUrl + "&plot=" + plotType + "&servers=" + selectedServers.join();
    YAHOO.log("URL=" + url, "info", "rgCtxMenuHdlr");
    var logTrendForm = document.createElement("form");
    logTrendForm.method = "POST";
    logTrendForm.action = url;
    logTrendForm.target = "_blank";
    document.body.appendChild(logTrendForm);
    logTrendForm.submit();
}

function setupMenu(dataTableObj) {
    YAHOO.log("MyDataTable=" + dataTableObj.getContainerEl().id, "info", "setupMenu");

    dataTableObj.subscribe("rowMouseoverEvent", dataTableObj.onEventHighlightRow);
    dataTableObj.subscribe("rowMouseoutEvent", dataTableObj.onEventUnhighlightRow);
    dataTableObj.subscribe("rowClickEvent", dataTableObj.onEventSelectRow);

    var contextMenu = new YAHOO.widget.ContextMenu(dataTableObj.getContainerEl().id + "_ctxMenu", {trigger:dataTableObj.getTbodyEl()});
    contextMenu.addItem("Plot Log Trend");
    contextMenu.render(dataTableObj.getContainerEl());
    contextMenu.clickEvent.subscribe(contextMenuHandler, dataTableObj);
}
';


function main() {
    global $debug, $site, $startDate, $endDate, $year, $month, $oss, $menuCode;

    if ( $debug > 1 ) {
        echo <<<EOS
<div id="myLogger" class="yui-log-container yui-log"/>
<script type="text/javascript">
    var myLogReader = new YAHOO.widget.LogReader("myLogReader", {verboseOutput:false});
</script>
EOS;
    }

    $esLogTrendUrl = $_SERVER['PHP_SELF'] . "?site={$site}&start={$startDate}&end={$endDate}&year={$year}&month={$month}&oss={$oss}";
    echo <<<EOS
<script type="text/javascript">
    var esLogTrendUrl = "$esLogTrendUrl";
    $menuCode
</script>
EOS;

    echo "<ul>";
    echo "<li><a href=\"#TopLoggersNumberHelp_anchor\">Server Log Line Number Statistics</a></li>";
    echo "<li><a href=\"#TopLoggersSizeHelp_anchor\">Server Log Size Statistics</a></li>";
    echo "<li><a href=\"#elasticFSUsedHelp_anchor\">Elasticsearch Filesystem Usage (%)</a></li>";
    echo "</ul>";

    drawHeaderWithHelp("Server Log Line Number Statistics", 2, "TopLoggersNumberHelp", "DDP_Bubble_218_ENM_Monthly_Server_Log_Statistics_Number");
    $topNumberLoggers = new topLoggersByNumber();
    echo $topNumberLoggers->getClientSortableTableStr(20, array(50, 100, 1000), 'setupMenu');

    drawHeaderWithHelp("Server Log Size Statistics", 2, "TopLoggersSizeHelp", "DDP_Bubble_218_ENM_Monthly_Server_Log_Statistics_Size");
    $topSizeLoggers = new topLoggersBySize();
    echo $topSizeLoggers->getClientSortableTableStr(20, array(50, 100, 1000), 'setupMenu');

    drawHeaderWithHelp("Elasticsearch Filesystem Usage (%)", 2, "elasticFSUsedHelp", "DDP_Bubble_218_ENM_Monthly_Server_Elastic_FS_Used");
    drawElasticFSUsedGraph();
}


if ( isset($_GET["plot"]) ) {
    plotLogTrend($_GET["plot"], $_GET["servers"], $_GET["start"], $_GET["end"], $_GET["year"], $_GET["month"]);
} else {
    main();
}

include PHP_ROOT . "/common/finalise.php";
?>
