<?php

$pageTitle = "CS Resource Statistics";
include "common/init.php";

$cs=$_GET["cs"];

$webroot = $webroot . "/cs/" . $cs;
$rootdir = $rootdir . "/cs/" . $cs;
?>

<html>
<head>
  <title>CS Resource Statistics</title>
</head>
<body>

<ul>

<?php
if ( file_exists($rootdir . "/dbtool_F.txt") ) {
  echo "<li><a href=\"#dbtoolF\">Versant DB Usage</a></li>\n";
 }
?>

 <li><a href="#Memory">Memory</a></li>
 <li><a href="#Threads">Threads</a></li>
 <li><a href="#NumSess">Number of Sessions</a></li>

<?php
if ( file_exists($rootdir . "/session_dead.jpg") ) {
  echo "<li><a href=\"#Dead\">Sessions Awaiting Delete</a></li>\n";
 }
?>

 <li><a href="#RO">Read Only Transaction Pool</a></li>
 <li><a href="#RW">Read Write Transaction Pool</a></li>

<?php
if ( file_exists($rootdir . "/session_dead.jpg") ) {
  echo "<li><a href=\"#Dead\">Sessions Awaiting Delete</a></li>\n";
 }

if ( file_exists($rootdir . "/setbulk_time.jpg") ) {
  echo "<li><a href=\"#SetBulk\">Set Bulk</a></li>\n";
 }

if ( file_exists($rootdir . "/purge_sess_time.jpg") ) {
  echo "<li><a href=\"#PurgeSess\">Purge Sessions</a></li>\n";
  echo "<li><a href=\"#PurgeTrans\">Purge Transactions</a></li>\n";
 }

if ( file_exists($rootdir . "/mocache_hit.jpg") ) {
  echo "<li><a href=\"#Cache\">Cache Hit Rate</a></li>\n";
}

echo "</ul>\n";

//
// Start of content
//
if ( file_exists($rootdir . "/dbtool_F.txt") ) {
  echo "<h1><a name=\"dbtoolF\"></a>Versant DB Usage</h1>\n";
  echo "<pre>";
  include($rootdir . "/dbtool_F.txt");
  echo "</pre>\n";
}
?>


<h1><a name="Memory"></a>Memory</h1>
<img src="Memory.jpg" alt="" width="640" height="480">
<h1><a name="Threads"></a>Threads</h1>
<img src="Thread.jpg" alt="" width="640" height="480">

<h1><a name="NumSess"></a>Number Of Sessions</h1>
<img src="NumSess.jpg" alt="" width="640" height="480">

<?php
if ( file_exists($rootdir . "/session_dead.jpg") ) {
  echo <<<EOS
<h1><a name="Dead"></a>Sessions Awaiting Delete</h1>
<img src="$rootdir/session_dead.jpg">
EOS;
}


if ( file_exists($rootdir . "/purge_sess_time.jpg") ) {
?>
<h1><a name="PurgeSess"></a>Purge Sessions</h1>
<h2></a>Time</h2>
<img src="<?=$webroot?>/purge_sess_time.jpg" alt="" width="640" height="480"><br> -->
<h2></a>Count</h2>
<img src="<?=$webroot?>/pugre_sess_count.jpg" alt="" width="640" height="480"><br> -->

<h1><a name="PurgeTrans"></a>Purge Transactions</h1>
<h2></a>Time</h2>
<img src="<?=$webroot?>/purge_trans_time.jpg" alt="" width="640" height="480">
<h2></a>Count</h2>
<img src="<?=$webroot?>/purge_trans_count.jpg" alt="" width="640" height="480">
<?php
    } // end of file_exists purge_sess_time
?>


<h1><a name="RO"></a>Read Only Transaction Pool</h1>
<img src="RO.jpg" alt="" width="640" height="480"><br>
<h1><a name="RW"></a>Read Write Transaction Pool</h1>
<img src="RW.jpg" alt="" width="640" height="480"><br>

<?php
if ( file_exists($rootdir . "/setbulk_time.jpg") ) {
?>
<h1><a name="SetBulk"></a>Set Bulk</h1>
<h2></a>Time</h2>
<img src="<?=$webroot?>/setbulk_time.jpg">
<h2></a>Set Bulk Count</h2>
<img src="<?=$webroot?>/setbulk_count.jpg">
<?php
} // end of file_exists setbulk_time

if ( file_exists($rootdir . "/mocache_hit.jpg") ) {
?>
<h1><a name="Cache"></a>Cache</h1>
<h2></a>MO Cache</h2>
<img src="<?=$webroot?>/mocache_hit.jpg">
<h2></a>LDAP Cache</h2>
<img src="<?=$webroot?>/ldap_hit.jpg">
<?php
    } // end of file_exists mocache_hit.jpg
?>

<?php include "common/finalise.php"; ?>
