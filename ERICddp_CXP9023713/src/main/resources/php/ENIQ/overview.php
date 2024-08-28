<?php
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';

$statsDB = new StatsDB();

class ServerDetailsTable {
    public $tableAttributes;
    public $prev_day_date;
    public $prev_week_date;

    public function getServerDetails($startDate, $endDate, $column, &$serverData) {
        global $site, $statsDB;
        $result = $statsDB->query("
            SELECT
             $column
            FROM
             servers, sites, hires_server_stat
            WHERE
             servers.siteid = sites.id AND
             sites.name = '$site' AND
             hires_server_stat.serverid = servers.id AND
             hires_server_stat.siteid = sites.id AND
             hires_server_stat.time BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
            GROUP BY
             servers.id
            ");

        while( $result = $statsDB->getNextNamedRow() ) {
            $server_id = array_shift($result);
            $serverData[$server_id] = $result;
        }
    }

   public function getNetanDetails($startDate, $endDate, $column, &$serverData) {
        global $site, $statsDB;
        $result = $statsDB->query("
            SELECT
             $column
            FROM
             servers, windows_processor_details, sites
            WHERE
             windows_processor_details.serverid = servers.id AND
             windows_processor_details.time BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' AND
             servers.siteid = sites.id AND
             sites.name = '$site'
            GROUP BY
             servers.id
            ");

        while ($result = $statsDB->getNextNamedRow() ) {
            $server_id = array_shift($result);
            if (isset ($server_id) ) {
                $serverData[$server_id] = $result;
            }
        }
    }
}

$serverDetails = new ServerDetailsTable();
$serverDetails->tableAttributes = array (
                                  'width' => '300',
                                  'style' => 'text-align:center'
                                  );
$serverDetails->prev_day_date = date('Y-m-d', strtotime($date .' -1 day'));
$serverDetails->prev_week_date = date('Y-m-d', strtotime($date .' -1 week'));

$serverTableTitleHelp = <<<EOT
The following table displays average CPU utilization for all the blades with a indication of trend comparison to previous day and week(last 7 days) average.
EOT;
drawHeaderWithHelp("System Performance Trend", 1, 'serverTableTitleHelp', $serverTableTitleHelp);

$tableServerDetails = new HTML_Table($serverDetails->tableAttributes);
$tableServerDetails->addRow( array('<b>Server</b>', '<b>CPU Avg</b>', '<b>Comparison with previous day Avg</b>', '<b>Comparison with previous week Avg</b>') );

$requiredColumn = "servers.id as server_id, ROUND(AVG(user+sys+iowait)) AS cpu_avg, servers.hostname AS hostname, CAST(servers.type AS char) AS type";
$currentDay = array();
$serverDetails->getServerDetails($date, $date, $requiredColumn, $currentDay);
$netanColumn = "servers.id as server_id, ROUND( AVG(processorTimePercent + userTimePercent)) AS cpu_avg, servers.hostname AS hostname, CAST(servers.type AS char) AS type";
$serverDetails->getNetanDetails($date, $date, $netanColumn, $currentDay);

$commonColumn ="servers.id as server_id, ROUND(AVG(user+sys+iowait)) AS cpu_avg";
$prevDay = array();
$serverDetails->getServerDetails($serverDetails->prev_day_date, $serverDetails->prev_day_date, $commonColumn, $prevDay);
$netanColumn = "servers.id as server_id, ROUND( AVG(processorTimePercent + userTimePercent)) as cpu_avg";
$serverDetails->getNetanDetails($serverDetails->prev_day_date, $serverDetails->prev_day_date, $netanColumn, $prevDay);

$prevWeek = array();
$serverDetails->getServerDetails($serverDetails->prev_week_date, $serverDetails->prev_day_date, $commonColumn, $prevWeek);
$serverDetails->getNetanDetails($serverDetails->prev_week_date, $serverDetails->prev_day_date, $netanColumn, $prevWeek);

foreach($currentDay as $serverId => $details) {
    if ( $details['cpu_avg'] > $prevDay[$serverId]['cpu_avg']) {
        $cpu_avg_day_status = "&#8679"; #Displaying trend using arrows.
        if ( ! isset($details['cpu_avg'])) {
            $details['cpu_avg']=null;
        }
        $day_delta = $details['cpu_avg'] - $prevDay[$serverId]['cpu_avg'];
    } elseif ( $details['cpu_avg'] == $prevDay[$serverId]['cpu_avg']) {
        $cpu_avg_day_status = "&#8660";
        $day_delta = '';
    } else {
        $cpu_avg_day_status = "&#8681";
        $day_delta = $prevDay[$serverId]['cpu_avg'] - $details['cpu_avg'];
    }
    if ( $details['cpu_avg'] > $prevWeek[$serverId]['cpu_avg']) {
        $cpu_avg_week_status = "&#8679";
        $week_delta = $details['cpu_avg'] - $prevWeek[$serverId]['cpu_avg'];
    } elseif ( $details['cpu_avg'] == $prevWeek[$serverId]['cpu_avg']) {
        $cpu_avg_week_status =  "&#8660";
        $week_delta = '';
    } else {
        $cpu_avg_week_status = "&#8681";
        $week_delta = $prevWeek[$serverId]['cpu_avg'] - $details['cpu_avg'];
    }
    $avg = $details['cpu_avg'];
    if ($details['type'] == "NetAnServer" || $details['type'] == "BIS") {
        $server = "<a href='" . PHP_WEBROOT . "/ENIQ/WindowsServer.php?site=" . $site . "&date=" . $date . "&oss=" . $oss . "&server=" . $details['hostname'] . "'>$avg%</a>";
    } else {
        $server = "<a href='" . PHP_WEBROOT . "/server.php?site=" . $site . "&date=" . $date . "&oss=" . $oss . "&server=" . $details['hostname'] . "'>$avg%</a>";
    }
    $tableServerDetails->addRow( array($details['hostname'], $server, $day_delta . '<font size="4">' . htmlspecialchars_decode($cpu_avg_day_status) .'</font>', $week_delta  . '<font size="4">' . htmlspecialchars_decode($cpu_avg_week_status) .'</font>') );
}

echo $tableServerDetails->toHTML();
$statsDB->disconnect();
include "../common/finalise.php";
?>