<?php

$pageTitle = "CS Session Statistics";
include "common/init.php";

$cs=$_GET["cs"];

$webroot = "/" . $oss . "/" . $site . "/analysis/" . $dir . "/cs/" . $cs;
$rootdir = $stats_dir . $webroot;
?>

<html>
<head>
  <title>CS Session Statistics</title>
</head>
<body>

<ul>
 <li><a href="#BySessName">Session Operation Counts By Session Name</a></li>
 <li><a href="#Error">Session Error Counts By Session Name</a></li>
 <li><a href="#CreateByName">CREATE Operations Per Min By Session Name</a></li>
 <li><a href="#CREATE">CREATE Operations Per Min</a></li>
 <li><a href="#BEGIN">BEGIN Operations Per Min Number of Sessions</a></li>
 <li><a href="#COMMIT">COMMIT Operations Per Min</a></li>
 <li><a href="#ROLLBACK">ROLLBACK Operations Per Min</a></li>
 <li><a href="#GET_ID">GET_ID Operations Per Min</a></li>
 <li><a href="#END">END Operations Per Min</a></li>
 <li><a href="#DELETE">DELETE Operations Per Min</a></li>
</ul>

<h1><a name="BySessName"></a>Session Operation Counts By Session Name</h1>
<table border>
<tr> <td><b>Name</b></td> <td><b>CREATE</b></td> <td><b>BEGIN</b></td> <td><b>COMMIT</b></td> <td><b>ROLLBACK</b></td> <td><b>GET_ID</b></td> <td><b>END</b></td> <td><b>DELETE</b></td> <td><b>Session Time</b></td> </tr>
<?php include($rootdir . "/countBySessTable.html"); ?>
</table>


<h1><a name="Error"></a>Session Error Counts By Session Name</h1>
<table border>
<tr> <td><b>Session Name</b></td> <td><b>Count</b></td>  </tr>
<?php include($rootdir . "/session_error_table.html"); ?>
</table>

<h1><a name="CreateByName"></a>CREATE Operations Per Min By Session Name</h1>
<img src="<?=$webroot?>/create_by_sess.jpg" alt="" width="640" height="480">

<h1><a name="CREATE"></a>CREATE Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_CREATE.jpg" alt="" width="640" height="480">
<h1><a name="BEGIN"></a>BEGIN Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_BEGIN.jpg" alt="" width="640" height="480">
<h1><a name="COMMIT"></a>COMMIT Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_COMMIT.jpg" alt="" width="640" height="480">
<h1><a name="ROLLBACK"></a>ROLLBACK Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_ROLLBACK.jpg" alt="" width="640" height="480">
<h1><a name="GET_ID"></a>GET_ID Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_GET_ID.jpg" alt="" width="640" height="480">
<h1><a name="END"></a>END Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_END.jpg" alt="" width="640" height="480">
<h1><a name="DELETE"></a>DELETE Operations Per Min</h1>
<img src="<?=$webroot?>/CountByMin_DELETE.jpg" alt="" width="640" height="480">


<?php include "common/finalise.php"; ?>




