<?php

$pageTitle = "RNC Events";
include "common/init.php";


$webroot = "/" . $oss . "/" . $site . "/analysis/" . $dir . "/cms";
$rootdir = $stats_dir . $webroot;

?>

<html>
<ul>
  <li><a href="#node">Events Per RNC</a></li>
  <li><a href="#overflow">Overflow Per RNC</a></li>
  <li><a href="#moc">Events Per MO Type</a></li>
  <li><a href="#moi">Events Per MO Instance</a></li>
 </ul>

<a name="node"></a>
<h1>Events Per RNC</h1>
<p>The graph below shows the number of ConfigurationIRP events 
received from each RNC(for the top 8) per hour. The event rates
are <b>stacked</b> on top of each other, e.g. if RNC1 shows 
between 0 and 1000, and RNC2 shows between 1000 and 1500, then 
RNC1 generated 1000 events and RNC2 generated 500 events.</p>

<p>
<img src="<?=$webroot?>/rnc_eventrate.jpg" alt="" width="640" height="480">
</p>
<table border>
 <tr> <th>RNC</th> <th>Event Count</th> </tr>
<?php include($rootdir . "/nodetable.html"); ?>
</table>

<a name="overflow"></a>
<h1>Overflow Per RNC</h1>
<table border>
 <tr> <th>RNC</th> <th>Event Count</th> </tr>
<?php include($rootdir . "/overflow.html"); ?>
</table>

<a name="moc"></a>
<h1>Events Per MO Type</h1>
<table border>
 <tr> <th>MO Type</th> <th>Event Count</th> </tr>
<?php include($rootdir . "/moctable.html"); ?>
</table>

<a name="moi"></a>
<h1>Events Per MO Instance</h1>
<p>Note: Only MOs with > 10 events are shown</p>
<table border>
 <tr> <th>MO</th> <th>Event Count</th> </tr>
<?php include($rootdir . "/moitable.html"); ?>
</table>

<?php include "common/finalise.php"; ?>
