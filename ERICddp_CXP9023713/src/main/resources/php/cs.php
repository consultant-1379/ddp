<?php
$pageTitle = "CS Transaction Logs";
include "common/init.php";

$cs=$_GET["cs"];

$statsDB = new StatsDB();

$csWebroot = $webroot . "/cs/" . $cs;
$csRootdir = $rootdir . "/cs/" . $cs;



echo "<ul>\n";

if ( file_exists($csRootdir . "/totals.html") ) {
  echo <<<EOT
 <li><a href="#txcreate">Tx Create</a></li>
 <li><a href="#txcommit">Tx Commit</a></li>
 <li><a href="#txrollback">Tx Rollback</a></li>
EOT;
}

if (file_exists($csRootdir . "/ongoing_sessions.jpg")) {
  echo <<<EOT
 <li><a href="#sessions">Ongoing Sessions</a></li>
 <li><a href="#transactions">Ongoing Transactions</a></li>
EOT;
}
if (file_exists($csRootdir . "/dbtool_F.txt")) {
  echo "<li><a href=\"#DB\">DB Usage</a></li>\n";
 }
if (file_exists($csRootdir . "/locks.html")) {
  echo "<li><a href=\"#locks\">Lock Errors</a></li>\n";
 }

echo "</ul>\n";

  //
  // Extract stats for summary table, info will only be present if
  // if cs dir does not contain dbtool_F.txt (old behaviour before we
  // started storing the info in MySQL
  //
if ( file_exists($csRootdir . "/dbtool_F.txt")) {

  echo "<h2><a name=\"DB\"></a>Versant DB Usage</h2>\n";
  echo "<pre>\n";
  include($csRootdir . "/dbtool_F.txt");
  echo "</pre>\n";
}


//
// Print the info extracted from the CS event-log and status logs (Pre R5.2 feature)
//
if ( file_exists($csRootdir . "/totals.html") ) {
?>

<h2>Tx Usage by Tx Name</h2>
 <table border>
 <tr> <th>Tx Name</th> <th>Begin</th> <th>Commit</th> <th>Rollback</th> <th>Operations</th> <th>Total Duration (ms)</th> <th>CsTime (ms)</th> <th>MibAdapterTime (ms)</th> <th>MoPluginTime (ms)</th> </tr>
 <?php
 include($csRootdir . "/totals.html");
 ?>
 </table>

<h2><a name="txcreate"></a>Begin Tx/Per Min</h2>
<img src="<?=$csWebroot?>/create.jpg" alt="" >

<h2><a name="txcommit"></a>Commit Tx/Per Min</h2>
<img src="<?=$csWebroot?>/commit.jpg" alt="" >

 <h2><a name="txrollback"></a>Rollback Tx/Per Min</h2>
 <img src="<?=$csWebroot?>/rollback.jpg" alt="" >
<?php
} # End of file_exists($csRootdir . "/totals.html")
if (file_exists($csRootdir . "/ongoing_sessions.jpg")) {
  echo <<<EOT
<h2><a name="sessions"></a>Ongoing Sessions</h2>
<img src="$csWebroot/ongoing_sessions.jpg" alt="">

<h2><a name="transactions"></a>Ongoing Transactions</h2>
<img src="$csWebroot/ongoing_transactions.jpg" alt="">
EOT;
}
//
// Print the lock problems extracted from CIF Error log (Pre R5.2 feature)
//
if (file_exists($csRootdir . "/locks.html")) {
  echo "<h2><a name=\"locks\"></a>Lock Errors</h2>\n";
  echo "<p>This table lists the transactions which requested a lock on an MO but failed to aquire the lock as another transaction was holding the lock</p>\n";
  echo "<table border>\n <tr> <th>Lock Requester</th> <th>Lock Holder</th> <th>MO</th> </tr>\n";
  include($csRootdir . "/locks.html");
  echo "</table>\n";
 }


$statsDB->disconnect();
include "common/finalise.php";
?>
