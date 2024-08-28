<?php
$pageTitle = "URL Access Analysis";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/ENIQ/SumTableDisplay.class.php";

$statsDB = new StatsDB();
$fromDate = $date;
$toDate = $date;

if ( isset($_GET['start']) ) {
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
}

class UrlInfoTable extends DDPObject {
    var $cols = array(
        'url'            => 'URL',
        'accessCount'    => 'Total Requests',
        'urlshare'       => 'URL Share(%)',
        'success'        => 'Success',
        'successPercent' => 'Success Ratio(%)',
        'failure'        => 'Failures',
        'failurePercent' => 'Failure Ratio(%)'
        );
    var $totalCount = 0;

    function __construct($totalCount) {
        parent::__construct("Url_instance");
        $this->totalCount = $totalCount;
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        $sql = "
            SELECT
             resource_urls.url as url,
             IFNULL(glassfish_url_aggregation.total_request,0) as accessCount,
             FORMAT(((glassfish_url_aggregation.total_request/$this->totalCount)*100),2) as urlshare,
             IFNULL(glassfish_url_aggregation.success,0) as success,
             FORMAT(((glassfish_url_aggregation.success/glassfish_url_aggregation.total_request)*100),2) as successPercent,
             IFNULL(glassfish_url_aggregation.failure,0) as failure,
             FORMAT(((glassfish_url_aggregation.failure/glassfish_url_aggregation.total_request)*100),2) as failurePercent
            FROM
             glassfish_url_aggregation, resource_urls, sites
            WHERE
             date = '$date' AND
             sites.id = glassfish_url_aggregation.siteid AND
             sites.name='$site' AND
             resource_urls.id=glassfish_url_aggregation.url
            GROUP BY
             glassfish_url_aggregation.url";

        $this->populateData($sql);

        foreach ($this->data as &$urlDataRow) {
            $params = $_GET;
            $paramString = http_build_query($params);

            if (strcmp($urlDataRow['url'], "TOTALS") !== 0) {
                $pageURL = PHP_WEBROOT . "/ENIQ/url_analysis.php?" . $webargs. "&url=" . $urlDataRow['url'];
                $link = sprintf("<a href=\"%s\">%s</a>",$pageURL,$urlDataRow['url']);
            } else {
                $link = $urlDataRow['url'];
            }

            $urlDataRow['url'] = $link;
        }
        return $this->data;
    }
}

function createGraph($date, $title, $whereClause){
    $sqlParamWriter = new SqlPlotParam();
    $graphs = new HTML_Table('border=0;overflow : auto;');
    $sqlParam = array(
        'title'       => $title,
        'ylabel'      => "Count",
        'type'        => 'sb',
        'sb.barwidth' => '3600',
        'presetagg'   => "COUNT:Hourly",
        'persistent'  => 'false',
        'useragg'     => 'false',
        'querylist'   =>
            array(
                array (
                    'timecol' => 'time',
                    'whatcol' => array('time'),
                    'tables'  => "glassfish_stats,sites",
                    'where'   => $whereClause,
                    'qargs'   => array( 'site')
                )
            )
        );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
    echo $graphs->toHTML();
}

$URLStatisticsHelp = <<<EOT
This page shows URL access statistics for ENIQ EVENTS UI.
EOT;
drawHeaderWithHelp("URL Access Analysis", 2, 'URLStatisticsHelp', $URLStatisticsHelp);

$dailyTotalsHelp = <<<EOT
<div>
Below tables shows the daily count for all and each URL.<br/>

<b>Table 1:</b><br/>
<ul>
    <li><b>Successful Requests : </b>Number of successful access to URLs on ENIQ EVENTS UI.</li>
    <li><b>Failed Requests : </b>Number of failed access to URLs on ENIQ EVENTS UI.</li>
    <li><b>Total Requests : </b>"Successful Requests" + "Failed Requests"</li>
</ul>
<br/><b>Table 2:</b><br/>
For each URL following below data is shown.<br/>
<ul>
    <li><b>URL : </b>The URL accessed.</li>
    <li><b>Total Requests : </b>Number of times the URL is accessed.</li>
    <li><b>URL Share(%) : </b>Percentage share for the URL over all URLs.</li>
    <li><b>Success : </b>Number of successful access to the URL.</li>
    <li><b>Success Ratio(%) : </b>Success / Total Requests</li>
    <li><b>Failures : </b>Number of failed access to the URL.</li>
    <li><b>Failure Ratio(%) : </b>Failures / Total Requests</li>
</ul>
</div>
EOT;
drawHeaderWithHelp("Daily Totals", 2, 'dailyTotalsHelp', $dailyTotalsHelp);

$dailyTotals = new SumTableDisplay();
$dailyTotals->aggTable = "glassfish_url_aggregation";
$dailyTotals->getData();
$dailyTotals->createRowBasedTable();
echo "<br/>";
echo "<br/>";
$dailyTotalsPerUrl = new UrlInfoTable($dailyTotals->getCount());
$dailyTotalsPerUrl->defaultOrderBy = "accessCount";
$dailyTotalsPerUrl->defaultOrderDir = "desc";
echo "<div style=\"background : #ffffff; color : #000000; padding : 1px; height : 400px; width :1050px; overflow : auto;\">";
$dailyTotalsPerUrl->getSortableHtmlTable();
echo "</div>";

if(isset($_GET['url'])){
    $url=$_GET['url'];
    drawHeaderWithHelp("Hourly Totals", 2, "hourlyTotalsHelp",
             "Below hourly charts shows total requests, total successful requests and total failed requests for the URL.");
    echo "<h2>$url</h2>";

    $statsDB->query("
        SELECT
         resource_urls.id
        FROM
         resource_urls
        WHERE
         resource_urls.url='$url'
        ");

    $urlIdRow = $statsDB->getNextRow();
    $urlId = $urlIdRow[0];

    createGraph($date, "Total Requests", "glassfish_stats.siteid = sites.id AND sites.name = '%s' AND glassfish_stats.urlid = $urlId");
    createGraph($date, "Successful Requests", "glassfish_stats.siteid = sites.id AND sites.name = '%s' AND glassfish_stats.urlid = $urlId AND glassfish_stats.response_status IN ('200')");
    createGraph($date, "Failed Requests", "glassfish_stats.siteid = sites.id AND sites.name = '%s' AND glassfish_stats.urlid = $urlId AND glassfish_stats.response_status NOT IN ('200')");
}

include "../common/finalise.php";
?>
