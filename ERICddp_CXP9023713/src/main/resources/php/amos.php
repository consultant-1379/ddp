<?php
$pageTitle = "AMOS";
if ( isset($_GET["qplot"]) ) {
    $UI = false;
 }
include "common/init.php";

require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();

echo "<h1>AMOS Metrics</h1>";

//
// Sessions Data
//
function qPlot($statsDB,$site,$date,$serverid,$metricid)
{
  $statsDB->query("SELECT name FROM amos_metrics WHERE id = $metricid");
  $row = $statsDB->getNextRow();

  $sqlParam =
    array( 'title'      => $row[0],
	   'ylabel'     => $row[0],
	   'useragg'    => 'true',
	   'persistent' => 'true',
	   'querylist' => 
	   array(
		 array(
                       'timecol' => 'time',
                       'whatcol'    => array( 'value'=> $row[0] ),
                       'tables'  => "amos_sessions, sites",
                       'where'   => "amos_sessions.siteid = sites.id AND sites.name = '%s' AND amos_sessions.serverid = %d AND metricid = %d",
                       'qargs'   => array( 'site', 'serverid', 'metricid' )
		       )
		 )
	   );

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);

  header("Location:" .  
	 $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") .
	 "&serverid=$serverid" . 
	 "&metricid=$metricid");
}

if ( isset($_GET["qplot"]) ) {
  qPlot($statsDB,$site,$date,$_GET["serverid"], $_GET["metricid"], $_GET["qplot"]);
  exit;
 }

$qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&qplot=";

$statsDB->query("
SELECT servers.hostname,
       amos_sessions.serverid,
       amos_metrics.name,
       amos_sessions.metricid,
       MIN(amos_sessions.value),
       ROUND( AVG(amos_sessions.value) ),
       MAX(amos_sessions.value)
FROM   amos_sessions, amos_metrics, sites, servers
WHERE  amos_sessions.siteid = sites.id AND sites.name = '$site' AND 
       amos_sessions.serverid = servers.id AND 
       amos_sessions.metricid = amos_metrics.id AND
       amos_sessions.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY serverid, metricid
ORDER BY amos_metrics.name");

$table = new HTML_Table("border=1");
$table->addRow( array ("Server", "Name","Min", "Avg", "Max"), null, 'th' );

while($row = $statsDB->getNextRow()) {
  $link = "<a href=\"" . $qplotBase . "&serverid=" . $row[1] . "&metricid=" . $row[3] . "\">$row[2]</a>";
  $table->addRow( array( $row[0], $link, $row[4], $row[5], $row[6] ) );
 }

echo "<h2>";
drawHelpLink("sessionsHelp");
echo "Sessions</h2>";
drawHelp("sessionsHelp", "AMOS Sessions", "AMOS sessions data, by server. Click on the metric to drill through to graphical data.");
echo $table->toHTML();

$statsDB->disconnect();

//
// Commands Data
//
echo "<h2>";
drawHelpLink("commandsHelp");
echo "Commands</h2>";
drawHelp("commandsHelp", "AMOS Commands", "AMOS command counts from the log viewer or command log data.");

class AMOSCommandsIndex extends DDPObject {
    var $cols = array(
        "command" =>"Command",
        "count" =>"Count"
    );

    var $defaultOrderBy = "command";
    var $defaultOrderDir = "ASC";

    function __construct() {
        parent::__construct("sitelist");
    }

    function getData() {
        global $date, $site, $statsDB, $phpDir, $webargs;
        $sql = "
            SELECT c.date AS date,
                   n.name AS command,
                   c.count AS count
            FROM   amos_commands c, sites s, amos_command_names n
            WHERE  c.date = '" . $date . "' AND c.siteid = s.id AND s.name = '" . $site . "' AND c.cmdid = n.id";
        $this->populateData($sql);
        return $this->data;
    }
}

$sidx = new AMOSCommandsIndex();
$sidx->getSortableHtmlTable();

include "common/finalise.php";
?>
