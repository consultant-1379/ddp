<?php
$pageTitle = "Log Analysis";
include "common/init.php";

$log=$_GET["log"];

$plotfile = $datadir . "/log_plots/" . $log . ".txt";
$graphURL = $php_webroot . "/graph.php?site=$site&dir=$dir&oss=$oss&file=log_plots/" . $log . ".txt";

$meAddInfo=0;
if ( isset($_GET["meaddinfo"]) ) {
  $meAddInfo = 1;
 }

$webroot = $webroot . "/logs";
$rootdir = $rootdir . "/logs"; 

if ( $meAddInfo ) {
?>
<h2>Additional Info By Network Element</h2>
<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <td><b>Network Element</b></td> <td><b>Count</b></td> <td><b>Additional Information</b></td>   <tr>

<?php 
    readfile($rootdir . "/" . $log . "_meAddInfoTable.html");
?>

 </tbody>
</table>
<?php
} else { 
 $request= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&meaddinfo=1";
?>
    <h1><?= ucwords($log); ?> Log Analysis</h1>
<ul>
  <li><a href="#numMC">Number of Entires By Managed Component</a></li>
  <li><a href="#addInfoMC">Additional Info By Managed Component</a></li>
  <li><a href="#numME">Number of Entires By Network Element</a></li>
  <li><a href="<?=$request?>">Additional Info By Network Element</a></li>
</ul>

<?php  
if ( file_exists($plotfile) ) {
  echo "<H2>Log Rate</H2>\n";
  echo "<p>Click <a href=\"" . $graphURL . "\">here</a> for plot of log entries per MC/minute</p>\n";
 }
?>

<a name="numMC"></a>
<h2>Number of Entries By Managed Component</h2>
<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <td><b>Count</b></td> <td><b>Managed Component</b></td>  <tr>
<?php readfile($rootdir . "/" . $log . "_mcCountTable.html"); ?>
  </tbody>
</table>

<a name="addInfoMC"></a>
<h2>Additional Info By Managed Component</h2>
<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <td><b>Managed Component</b></td> <td><b>Count</b></td> <td><b>Additional Information</b></td>   <tr>
<?php readfile($rootdir . "/" . $log . "_mcAddInfoTable.html"); ?>
  </tbody>
</table>

<a name="numME"></a>
<h2>Number of Entries By Network Element</h2>
<table cellpadding="2" cellspacing="2" border="1">
  <tbody>
    <tr> <td><b>Count</b></td> <td><b>Network Element</b></td>  <tr>
<?php readfile($rootdir . "/" . $log . "_meCountTable.html"); ?>
  </tbody>
</table>

<?php
      } # End of if ($meAddInfo)
include "common/finalise.php";
?>
