<?php
$pageTitle = "Notification Service";
include "common/init.php";

$ns=$_GET["ns"];

$webroot = $webroot . "/ns/" . $ns;
$rootdir = $stats_dir . $webroot;
echo "<h1>" . $ns . "</h1>\n";
?>

<ul>
 <li><a href="#events">Events</a></li>
 <li><a href="#suppcon">Suppilers/Consumers</a></li>
 <li><a href="#memory">Heap</a></li>
</ul>

<a name="events"></a>
<H1>Events</H1>
<p><img src="<?=$webroot?>/events_rec.jpg"></p>
<p><img src="<?=$webroot?>/events_del.jpg"></p>
<p><img src="<?=$webroot?>/events_await.jpg"></p>

<?php
if ( file_exists($rootdir . "/current_events.jpg") ) {
   echo "<p><img src=\"$webroot/current_events.jpg\"></p>\n"; 
}
?>

<a name="suppcon"></a>
<H1>Suppilers/Consumers</H1>
<p><img src="<?=$webroot?>/supp.jpg"></p>
<p><img src="<?=$webroot?>/consum.jpg"></p>

<a name="memory"></a>
<H1>Heap</H1>
<p><img src="<?=$webroot?>/heap.jpg"></p>

<?php
include "common/finalise.php";
?>


