<?php
require_once 'HTML/Table.php';
$pageTitle = "OPS Stats";
include "common/init.php";
?>
<h1>OPS Statistics</h1>
<?php
require_once "classes/OpsStats.php";
$opsTbl = new OpsStats();

?>

<h2><?php drawHelpLink("helpct"); ?>Individual OPS Script Statistics</h2>
<?php

drawHelp("helpct", "Individual OPS Script Statistics",
    "
Individual OPS Script Statistics are parsed from the ops.xml file.
This data is collected from:
<p />
<code>/var/opt/ericsson/nms_ops_server/logs/</code>
<p />
The field CPU Usage is the SUM of user & system time in seconds. If XML file cannot 
find the value for CPU Usage it will record NaN in log which will be represented here as 0
");
$opsTbl->getSortableHtmlTable();

include "common/finalise.php";
?>
