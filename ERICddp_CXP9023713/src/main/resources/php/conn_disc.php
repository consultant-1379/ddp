<?php

$pageTitle = "Connection Statistics";
include "common/init.php";

$file=$_GET["file"];

$table = $stats_dir . "/" . $oss . "/" . $site . "/analysis/" . $dir . "/" . $file;

$debug=0;
if ( isset($_GET["debug"]) ) {
  $debug=$_GET["debug"];
 }
?>

<head>
  <title>Connection Statistics</title>
</head>
<body>

<h1>Connection Statistics</h1>

<table border>
 <tr> <td><b>Graph Position</b></td> <td><b>Node<b></td> <td><b>Number of Connects</b></td> <td><b>Number of Dis-connects</b></td> </tr>

<?php
if ( $debug ) { echo "<p>table=$table</p>\n"; }

include($table);
?>

</table>

<?php
include "common/finalise.php";
?>
