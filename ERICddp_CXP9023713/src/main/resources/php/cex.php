<?php
$pageTitle = "CEX";
include "common/init.php";

$webroot = $webroot . "/cex";
$rootdir = $rootdir . "/cex";

?>

<H2>CEX Heap/Threads</H2>
<p><img src="<?=$webroot?>/cex-heap.jpg"></p>
<p><img src="<?=$webroot?>/cex-thr.jpg"></p>

<H2>CEX Tasks</H2>
<p><img src="<?=$webroot?>/cex_RequestedTasks.jpg"></p>
<p><img src="<?=$webroot?>/cex_RunningTasks.jpg"></p>
<p><img src="<?=$webroot?>/cex_FinishedTasks.jpg"></p>

<H2>CEX Domain</H2>
<p><img src="<?=$webroot?>/cex_domain_ReceivedEvents.jpg"></p>
<p><img src="<?=$webroot?>/cex_domain_CreatedObjects.jpg"></p>
<p><img src="<?=$webroot?>/cex_domain_DeletedObjects.jpg"></p>


<H2>ActiveMQ CEX Broker</H2>
<?php
$cexBrokerGraphs = array( "TemporaryQueues", "Topics", "TotalConsumerCount", "TotalDequeueCount", "TotalEnqueueCount", "TotalMessageCount" );
foreach ($cexBrokerGraphs as $name ) {
  $fileName = "amq-cexbroker_" . $name . ".jpg";
  if ( $debug > 0 ) { echo "checking $rootdir $fileName\n"; }
  if ( file_exists($rootdir . "/" . $fileName) ) {
    echo "<p><img src=\"" . $webroot . "/" . $fileName . "\"></p>\n";
  }
}

?> 