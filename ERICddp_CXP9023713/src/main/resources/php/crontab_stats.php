<?php

$YUI_DATATABLE = true;

require_once 'HTML/Table.php';
$pageTitle = "Crontab Jobs";
include "common/init.php";

require_once "classes/CrontabStats.php";

?>

<h1>CRONTAB Statistics</h1>

<h2> <?php drawHelpLink("helpct"); ?> Overall Crontab Commands Statistics</h2>

<?php

drawHelp("helpct", "Overall Crontab Commands Statistics",
    "
Overall Crontab Commands Statistics are parsed from the cron log.
This data is collected from:
<p />
<code>/var/cron/log</code>
<p />
");
$hostname="";
$hostname=$_GET["server"];
$cntbTbl = new CrontabStats($hostname);
$cntbTbl->getSortableHtmlTable();

include PHP_ROOT . "/common/finalise.php";

?>
