<?php

$pageTitle = "RSD";
include "common/init.php";


$webroot = $webroot . "/rsd";
$rootdir = $rootdir . "/rsd";
?>

<html>
<head>
  <title>RSD</title>
</head>
<body>

<h1>Node Data Loaded</h1>
<h2>RNC</h2>
<img src="<?=$webroot?>/rnc_loaded.jpg">
<h2>RBS</h2>
<img src="<?=$webroot?>/rbs_loaded.jpg">

<h1>RNC Data Not Loaded</h1>
<table border>
 <tr> <th>Time</th> <th>RNC</th> <tr>
<?php include ($rootdir . "/RncNotLoaded.html"); ?>
</table>

<?php include "common/finalise.php"; ?>
