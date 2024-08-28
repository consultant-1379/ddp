<?php
$pageTitle = "Socket Usage";

include "common/init.php";

$serverDir=$_GET["serverdir"];
$webroot = $webroot . "/" . $serverDir;
?>

<?php
 drawHeaderWithHelp("Internal Sockets", 2, "internalSocket", "DDP_Bubble_460_ENM_INTERNAL_SOCKET");
?>
<img src="<?=$webroot?>/internal.jpg" alt="">

<?php
 drawHeaderWithHelp("External Outgoing Sockets", 2, "externalOutgoingSockets", "DDP_Bubble_461_ENM_EXTERNAL_OUTGOING_SOCKET");
?>
<img src="<?=$webroot?>/extout.jpg" alt="">

<?php
 drawHeaderWithHelp("External Incoming Sockets", 2, "externalIncomingSockets", "DDP_Bubble_462_ENM_EXTERNAL_INCOMING_SOCKET");
?>
<img src="<?=$webroot?>/extin.jpg" alt="">

<?php
 drawHeaderWithHelp("Anonymous Sockets", 2, "anonymousSockets", "DDP_Bubble_463_ENM_ANONYMOUS_SOCKET");
?>
<img src="<?=$webroot?>/totalanon.jpg" alt="">

<?php
 drawHeaderWithHelp("Sockets States", 2, "socketsStates", "DDP_Bubble_464_ENM_SOCKETS_STATES");
?>
<img src="<?=$webroot?>/states.jpg" alt="">

<?php
include "common/finalise.php";
?>
