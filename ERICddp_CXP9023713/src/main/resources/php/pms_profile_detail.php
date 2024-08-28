<?php

if ( isset($_GET["pid"]) ) {
  $title = "PMS Profile Contents";
  $pid = $_GET["pid"];
 } else if ( isset($_GET["ptype"]) ) {
  $ptype = $_GET["ptype"];

  $site=$_GET['site'];
  $date=$_GET["date"];

  $title = "PMS Profile List: $ptype";
 }

$pageTitle = $title;
include "common/init.php";
?>
<table border>

<?php
$statsDB = new StatsDB();
if ( isset($pid) ) {
  echo "<tr> <th>Type</th> <th>Content</th> </tr>\n";
  $row = $statsDB->queryRow("SELECT pms_profile_detail.list FROM pms_profile_detail WHERE pms_profile_detail.id = $pid");
  
  $list = preg_split("/,/", $row[0]);
  foreach ($list as $proto) {
    if ( $debug ) { echo "<p>$proto</p>\n"; }

    $fields = preg_split("/:/", $proto );
    $isFirst = 1;
    foreach ($fields as $field) {
      if ( $isFirst ) {
	echo "<tr> <td>$field</td> <td></td> </tr>\n";
	$isFirst = 0;
      } else {
	echo "<tr> <td></td> <td>$field</td> </tr>\n";
      }
    }
  }
 } else if ( isset($ptype) ) {
    echo "<tr> <th>Name</th> <th>Admin State</th> <th>Num Network Elements</th> </tr>\n";
  $statsDB->query("
SELECT 
   pms_profile.name, pms_profile.admin_state, pms_profile.numne, pms_profile.detailid
FROM
   pms_profile, sites
WHERE
   pms_profile.date = '$date' AND
   pms_profile.siteid = sites.id AND sites.name = '$site' AND
   pms_profile.type = '$ptype'
ORDER BY
   pms_profile.name");
  while ( $row = $statsDB->getNextRow() ) {
    echo " <tr> <td><a href=\"" . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'] . "&pid=" . $row[3] . "\">$row[0]</a></td> <td>$row[1]</td> <td>$row[2]</td> </tr>\n";
  }
 }
echo "</table>\n";
$statsDB->disconnect();
include "common/finalise.php";
?>
