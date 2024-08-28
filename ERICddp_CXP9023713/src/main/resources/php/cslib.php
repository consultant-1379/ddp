<?php
$pageTitle = "CSLIB Statistics";
include "common/init.php";
$cslibDir=$_GET["cslibdir"];
$cs=$_GET['cs'];

$cslib_webroot = $webroot . "/" . $cslibDir;
$cslib_rootdir = $rootdir . "/" . $cslibDir;

?>
<H2><?=$cs?> CSLIB Statistics</H2>

<?php
$graphs = array (
	"ConfigurationManagerCount.jpg" => "Configuration Manager Count",
	"PersistenceManagerCount.jpg" => "Persistence Manager Count",
	"OngoingCsTransactionCount.jpg" => "Ongoing CS Transaction Count",
	"ActiveJdoTransactionCount.jpg" => "Active JDO Transaction Count",
	"CacheStatistics.jpg" => "Cache Statistics",
	"MemoryUsage.jpg" => "Memory Usage (Kb)",
	"Transactions.jpg" => "Transactions",
	"PmOpen.jpg" => "PM Open",
	"PmIdleInPool.jpg" => "PM Idle In Pool",
	"PmCreated.jpg" => "PM Created",
	"PmClosedDuringTx.jpg" => "PM Closed During Tx");

$indexStr = "<a name=menu /><ul>\n";
$contentStr = "";

foreach ($graphs as $graph => $title) {
	if (file_exists($cslib_rootdir . "/" . $graph)) {
		$indexStr .= "<li><a href=\"#" . $graph . "\">" . $title . "</a></li>\n";
		$contentStr .= "<a name=\"" . $graph . "\" /><h3>" . $title . "</h3>\n" .
			"<img src=\"" . $cslib_webroot . "/" . $graph . "\" alt=\"\" />\n" .
			"<p><a href=#menu>back to top</a></p>\n";
	}
}

if ( file_exists($cslib_rootdir . "/totals.html") ) {
  $indexStr .= "<li><a href=\"#txlog\">Tx Usage by Tx Name</a></li>\n";
 }
$indexStr .= "</ul>\n";

echo $indexStr;
echo $contentStr;

if ( file_exists($cslib_rootdir . "/totals.html") ) {
?>

<a name="txlog" />
<h2>Tx Usage by Tx Name</h2>
 <table border=1>
 <tr> <th>Tx Name</th> <th>Begin</th> <th>Commit</th> <th>Rollback</th> <th>Operations</th> <th>Total Duration (ms)</th> <th>CsTime (ms)</th> <th>MibAdapterTime (ms)</th> <th>MoPluginTime (ms)</th> </tr>
 <?php
 include($cslib_rootdir . "/totals.html");
 ?>
 </table>

<h2><a name="txcreate"></a>Begin Tx/Per Min</h2>
<img src="<?=$cslib_webroot?>/create.jpg" alt="" >

<h2><a name="txcommit"></a>Commit Tx/Per Min</h2>
<img src="<?=$cslib_webroot?>/commit.jpg" alt="" >

 <h2><a name="txrollback"></a>Rollback Tx/Per Min</h2>
<img src="<?=$cslib_webroot?>/rollback.jpg" alt="" >

<?php
} # End of file_exists($cslib_webroot . "/totals.html")
include "common/finalise.php";
?>
