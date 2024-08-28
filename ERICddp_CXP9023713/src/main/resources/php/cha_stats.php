<?php
$pageTitle = "CHA Script Statistics";
include "common/init.php";

require_once 'HTML/Table.php';
?>


<h1>CHA Statistics</h1>

<?php
require_once "classes/ChaStats.php";
$chaAllTbl = new ChaOverallStats();
$chaIndividualTbl = new ChaIndividualStats();
?>

<h2><?php drawHelpLink("helpcmp"); ?>CHA Overall Statistics</h2>
<?php

drawHelp("helpcmp", "CHA Overall Stats", 
"This data (cha_scf.log & cha_cfh.log) is collected from:
<p /> 
<code>/var/opt/ericsson/nms_axe_cha_cha/nms_axe_cha_cha/log/</code>
<p />
Each line in cha_cfh.log represents one record for CHA Command. 
The log file cha_scf.log contains records for System Command. 
These system commands run in the form of different primitives e.g. CHFStart, CFHStop, CFHRouting, CFHSend, CFHConnect. 
All of these primitves are grouped with a same PPID in cha_scf.log file.");
$chaAllTbl->getSortableHtmlTable();
?>

<h2><?php drawHelpLink("helpsup"); ?>CHA Individual Statistics</h2>
<?php

drawHelp("helpsup", "CHA Individual Stats", 
"Two types of commands are presented COMMAND or SYSTEM_COMMAND.<br><br>
COMMAND data is logged on a per line basis, it is presented as it is.
<br><br>SYSTEM_COMMAND statistics are grouped together in the form of different primitives.
CHFStart process runs in background and then in parallel other primitives run sequentially.
<br><br>Therefore, total pr & rss memory is calculated as follows:<br>
CHFStart pr/rss memory + largest value of pr/rss memory in other primitives in a single record.
<br><br>CPU usage is added for all primitives as it is user and system time in seconds.");
$chaIndividualTbl->getSortableHtmlTable();
include "common/finalise.php";
?>
