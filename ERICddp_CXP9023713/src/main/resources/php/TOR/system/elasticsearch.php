<?php
$pageTitle = "Elasticsearch Logs";

include "../../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/JsPlot.php";

const LOG_FILE = 'logfile';

function showGetLog($statsDB) {
    global $site, $date;
    $row = $statsDB->queryRow("
SELECT
 ROUND( compressedKB / 1024, 1 ) AS compressedMB,
 ROUND( uncompressedKB / 1024, 1 ) AS uncompressedMB,
 lastError
FROM
 sites, enm_elasticsearch_getlog
WHERE
 enm_elasticsearch_getlog.siteid = sites.id AND sites.name = '$site' AND
 enm_elasticsearch_getlog.date = '$date'");
    if ( ! is_null($row) ) {
        $table = new HTML_Table('border=1');
        $table->addRow(array("Compressed Size (MB)", $row[0]));
        $table->addRow(array("Uncompressed Size (MB)", $row[1]));
        if ( ! is_null($row[2]) ) {
            $table->addRow(array("Last Error", "<B>" . $row[2] . "</B>"));
        }
        echo $table->toHTML();
    }
}

function getElasticSearchLogs($statsDB, $logdir)
{
    global $date, $site, $php_webroot;

    $statsDB->query("
    SELECT
     log_name AS logfile,
     time(log_end_time) AS logendtime,
     log_size AS logsize
    FROM
     enm_elasticsearch_logs,
     sites
    WHERE
     enm_elasticsearch_logs.siteid = sites.id
     AND sites.name = '$site'
     AND enm_elasticsearch_logs.date='$date'
    ");

    $rowData = array();
    while ($row = $statsDB->getNextNamedRow()) {
        $rowData[] = $row;
    }

    $logNamesInDB = array();
    foreach ($rowData as $key => $d) {
        $filePath                = $logdir . "/" . $d[LOG_FILE];
        $fileName                = basename($filePath);
        $logNamesInDB[$fileName] = 1;
        if (file_exists($filePath)) {
            $rowData[$key][LOG_FILE] = makeLinkForURL(getUrlForFile($filePath), $fileName);
        } else {
            # Ignore the elasticsearch logs with records under database but no corresponding
            #  files under '/data/stats/tor/<SITE_NAME>/analysis/<DDMMYY>/enmlogs/' directory.
            unset($rowData[$key]);
        }
    }
    if (sizeOf($rowData) < 25) {
        $table = new DDPTable("ElasticSearch", array(
            array(
                DDPTable::KEY => LOG_FILE,
                'label' => 'Log File'
            ),
            array(
                'key' => 'logendtime',
                'label' => 'Log End Time'
            ),
            array(
                'key' => 'logsize',
                'label' => 'File Size(MB)',
                'sortOptions' => array('sortFunction' => 'forceSortAsNums')
            )
        ), array(
            'data' => $rowData
        ));
    } else {
        $table = new DDPTable("ElasticSearch", array(
            array(
                DDPTable::KEY => LOG_FILE,
                'label' => 'Log File'
            ),
            array(
                'key' => 'logendtime',
                'label' => 'Log File End Time'
            ),
            array(
                'key' => 'logsize',
                'label' => 'File Size(MB)',
                'sortOptions' => array('sortFunction' => 'forceSortAsNums')
            )
        ), array(
            'data' => $rowData
        ), array(
            'rowsPerPage' => 25,
            'rowsPerPageOptions' => array(
                50,
                100,
                250,
                500,
                1000
            )
        ));
    }
    echo $table->getTableWithHeader("ElasticSearch Logs", 2, "", "", "ElasticSearch");
}

function getServerLogAnalysis()
{
    global $date;
    global $site;

    $baseURL =  fromServer(PHP_SELF) . '?' . fromServer('QUERY_STRING') . "&host=";
    echo <<<EOS
<script type="text/javascript">
function esFormatHost(elCell, oRecord, oColumn, oData) {
 elCell.innerHTML =
      "<a href=\"$baseURL" +
      encodeURI(oRecord.getData("host")) +
      "\">" + oRecord.getData("host") +
      "</a>";
}
</script>
EOS;

    $where = <<<EOS
enm_logs.siteid = sites.id AND sites.name = '$site' AND
enm_logs.serverid = servers.id AND
enm_logs.date = '$date'
EOS;
    $table = SqlTableBuilder::init()
           ->name("enm_logs")
           ->tables(array("enm_logs", StatsDB::SITES, StatsDB::SERVERS))
           ->where($where)
           ->addColumn("host", "servers.hostname", "Host", "esFormatHost")
           ->addSimpleColumn("enm_logs.entries", "Log Entries")
           ->addColumn("size", "enm_logs.size", "Log Size(KB)")
           ->sortBy("size", DDPTable::SORT_DESC)
           ->paginate()
           ->build();
    echo $table->getTableWithHeader("Server Log Analysis", 2);
}

function displayLogsForHost($host)
{
    global $debug, $rootdir;
    $logdir = getLogDir();

    $handle          = NULL;
    $messagesZipFile = $logdir . "/hosts.zip";
    debugMsg("displayLogsForHost: host=$host messagesZipFile=$messagesZipFile");
    if (file_exists($messagesZipFile)) {
        $hostMessageFile = 'zip://' . $messagesZipFile . '#' . $host . '.json';
        $handle          = fopen($hostMessageFile, 'r');
    } else {
        debugMsg("displayLogsForHost: messagesZipFile doesn't exist");
    }

    if (is_null($handle) || !$handle) {
        echo "<H2>Cannot find messages file for $host</H2>\n";
        return;
    }


    drawHeaderWithHelp("Messages for $host", 2, "MessagesHelp", "DDP_Bubble_21_MessagesForHost");

    $lines    = array();
    $maxCount = 1000;
    if ($handle) {
        $count = 0;
        while (($line = fgets($handle)) !== false && $count < $maxCount) {
            $lines[] = $line;
            $count++;
            if ($debug) {
                echo "<pre>count=$count</pre>\n";
            }
        }
        fclose($handle);

        if ($count >= $maxCount) {
            echo "<b>Warning: Too many rows to display, only the first $maxCount rows will be displayed</b>\n";
        }
    } else {
        echo "<b>Failed to open $hostMessageFile</b>\n";
        return;
    }

    $table   = new HTML_Table('border=1');
    $hrAttrs = array(
        'style' => 'vertical-align:top'
    );

    # Check if the file has the new JSON structure or the old one
    $hasNewJsonStructure = true;
    $rows                = array();
    if (count($lines) == 1) {
        $rows = json_decode($lines[0], true);
        if (!array_key_exists('count', $rows)) {
            $hasNewJsonStructure = false;
        }
    }

    /* To try and manage memory better, we now write each row in seperate lines */
    if ($hasNewJsonStructure) {
        $table->addRow(array(
            'Program',
            'Severity',
            'Count',
            'First Time',
            'Last Time',
            'Message'
        ), null, 'th');
        foreach ($lines as $line) {
            $row               = json_decode($line, true);
            $message           = $row['message'];
            $seperatorLocation = strpos($message, "STACKTRACE_TAG");
            if ($seperatorLocation) {
                $stack   = substr($message, $seperatorLocation + strlen("STACKTRACE_TAG"));
                $msg     = substr($message, 0, $seperatorLocation);
                $message = "<p>" . $msg . "</p><pre>" . $stack . "</pre>";
            }
            $table->addRow(array(
                $row['program'],
                $row['severity'],
                $row['count'],
                array_key_exists('firstTime', $row) ? $row['firstTime'] : "",
                array_key_exists('lastTime', $row) ? $row['lastTime'] : "",
                $message
            ), $hrAttrs, 'td', true);
        }
    } else {
        /* This the old branch, where all the rows are encoded in a single JSON line */
        if ($debug) {
            echo "<pre>";
            var_dump($rows);
            echo "\n<pre>\n";
        }

        if (array_key_exists('program', $rows[0])) {
            $table->addRow(array(
                'Program',
                'Severity',
                'Count',
                'Message'
            ), null, 'th');
            $hasProgram = TRUE;
        } else {
            $table->addRow(array(
                'Count',
                'Message'
            ), null, 'th');
            $hasProgram = FALSE;
        }
        foreach ($rows as $row) {
            if ($hasProgram) {
                $table->addRow(array(
                    $row['program'],
                    $row['severity'],
                    $row['count'],
                    $row['message']
                ), $hrAttrs, 'td', true);
            } else {
                $table->addRow(array(
                    $row['count'],
                    $row['message']
                ), $hrAttrs, 'td', true);
            }
        }
    }
    echo $table->toHTML();
}

function drawLinks() {
    global $php_webroot, $webargs, $date, $statsDB;

    $args = explode("&", $webargs);
    $lastDate = date('Y-m-d', strtotime($date . '-31 days'));

    $links = array();
    $links[] = makeAnchorLink('enm_logs_anchor', 'Server Log Analysis');
    $links[] = makeAnchorLink('ElasticSearch_anchor', 'Elasticsearch Logs');
    $url = "$php_webroot/monthly/TOR/log_analysis.php?$args[0]&start=$lastDate&end=$date&$args[3]";
    $links[] = makeLinkForURL($url, 'Last 31 Day Summary');

    if ( $statsDB->hasData('enm_vm_hc') ) {
        $links[] = makeLink('/TOR/misc/serviceHc.php', 'Service Health Status');
    }
    echo makeHTMLList($links);
}

function getLogDir() {
    global $rootdir;
    if (issetURLParam('logdir')) {
        $log = requestValue('logdir');
        $logdir = $rootdir . "/" . $log;
    } elseif (issetURLParam('logdirs')) {
        $logdirs = requestValue('logdirs');
        $logdirArr = explode(",", $logdirs);

        foreach ($logdirArr as $log) {
            if ( is_dir($rootdir . "/$log") ) {
                $logValue = $log;
            }
        }
        if (isset($logValue)) {
            $logdir = $rootdir . "/" . $logValue;
        } else {
            $logdir = " ";
        }
    }
    return $logdir;
}

function mainFlow() {
    global $debug, $rootdir, $site, $dir, $date;

    $statsDB = new StatsDB();

    drawHeaderWithHelp("Logs", 1, "enmlogs");
    drawLinks();

    showGetLog($statsDB);
    $logdir = getLogDir();

    $plotFile = $logdir . "/plot.json";
    debugMsg("mainFlow: plotFile=$plotFile");
    if (file_exists($plotFile)) {
        echo '<div id="lograte" style="height: 400px"></div>' . "\n";
        $sqlParam = array(
            'title' => "Entries Per Host/Minute",
            'type' => 'sb',
            'ylabel' => "Log Entries",
            'useragg' => 'false',
            'persistent' => 'false',
            'seriesfile' => $plotFile
        );
        $jsPlot   = new JsPlot();
        $jsPlot->show($sqlParam, 'lograte', NULL);
    }

    getServerLogAnalysis();
    getElasticSearchLogs($statsDB, $logdir);
}

if (isset($_GET["host"])) {
    displayLogsForHost($_GET["host"]);
} else {
    mainFlow();
}

include PHP_ROOT . "/common/finalise.php";
?>
