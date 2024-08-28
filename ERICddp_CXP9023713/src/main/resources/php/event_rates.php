<?php
$pageTitle = "Event Channels";
include "common/init.php";
$etype=-1;
if ( isset($_GET["etype"]) ) {
  $etype=$_GET["etype"];
 }

if ( $debug ) { echo "<p>etype = $etype</p>\n"; }

$webroot =  $webroot . "/event_rates";
$rootdir = $rootdir . "/event_rates";

if ( $etype == - 1 ) {
  $request= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
  $eventTotalsFile = $rootdir . "/Event_Totals_Table.html";

  if ( $debug ) { echo "<p>eventTotalsFile = $eventTotalsFile</p>\n"; }
?>
<H1>Event Rates</H1>
<ul>
    <li><a href="<?=$request?>&etype=1">Event Rates Per Category Per Second</a></li>
    <li><a href="<?=$request?>&etype=2">Event Rates Per Category Per Minute</a></li>
    <li><a href="<?=$request?>&etype=3">Event Rates Per Category Per Hour</a></li>
</ul>

<H1>Event Totals</H1>
<table border>
 <tr> <td><b>Category</b></td> <td><b>Total Event Count</b></td> </tr>
<?php
  include($eventTotalsFile);
  echo "</table>\n";
} else {

  if ( $etype == 1 ) {
    echo "<H1>Event Rates Per Channel Per Second</H1>\n";
    $pattern = '/^sec_(.+)\.jpg$/';
  // HQ76176: Get the Rate per minute as well. Changed the event type indexes accordingly as well. [BG 2013-01-04]
  } else if ( $etype == 2 ) {
    echo "<H1>Event Rates Per Channel Per Minute</H1>\n";
    $pattern = '/^min_(.+)\.jpg$/';
  } else if ( $etype == 3 ) {
    echo "<H1>Event Rates Per Channel Per Hour</H1>\n";
    $pattern = '/^hour_(.+)\.jpg$/';
  }

  if ( $debug ) { echo "<p>pattern = $pattern</p>\n"; }

  $catList = array();
  if ($dh = opendir($rootdir)) {
    while (($file = readdir($dh)) != false) {
      if ( $debug ) { echo "<p>file = $file</p>\n"; }

      if ( preg_match($pattern, $file, $matches) ) {
	$catList[$matches[1]] = $file;
      }
    }
    closedir($dh);
  }

  echo "<ul>\n";  
  foreach ($catList as $cat => $file ) {
    echo "<li><a href=\"#" . $cat . "\">" . $cat . "</a></li>\n";
  }
  echo "</ul>\n";  

  foreach ($catList as $cat => $file) {
?>
<a name="<?=$cat?>"></a>
<H2><?=$cat?></H2>
<img src="<?=$webroot?>/<?=$file?>" alt="" >
<?php
      }

 }
include "common/finalise.php";
?>

