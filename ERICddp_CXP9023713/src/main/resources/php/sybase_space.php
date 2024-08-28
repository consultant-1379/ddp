<?php
$pageTitle = "Sybase Sizes";
include "common/init.php";
?>
<h1>Database Usage</h1>
<table border>


 <?php

if (file_exists($rootdir . "/sybase_sybinfo_table.html")) {
  echo "<tr> <th>Name</th> <th>Size(MB)</th> <th>Used(MB)<th> </tr>\n";
  include($rootdir . "/sybase_sybinfo_table.html");
 } else if (file_exists($rootdir . "/sybase_dbinfo_table.html")) {
  echo "<tr> <td><b>Database Name</b></td> <td><b>Data Size(MB)</b></td> <td><b>Data Used(MB)</b></td> <td><b>Log Size(MB)</b></td> <td><b>Log Used(MB)</b></td></tr>\n";
  include($rootdir . "/sybase_dbinfo_table.html");
} else {
  echo "<tr> <th>Name</th> <th>Database Size(MB)</th> <th>Data Size(MB)</th> <th>Data Free(MB)</th> <th>Data Used(%)</th> </tr>\n";

  $statsDB = new StatsDB();

  $sqlquery = "SELECT sybase_dbnames.name, sybase_dbspace.dbsize, sybase_dbspace.datasize, sybase_dbspace.datafree, ROUND(((sybase_dbspace.datasize -sybase_dbspace.datafree)*100) / sybase_dbspace.datasize) FROM sybase_dbnames, sybase_dbspace, sites WHERE sybase_dbspace.dbid = sybase_dbnames.id AND sybase_dbspace.siteid = sites.id AND sites.name = '$site' AND sybase_dbspace.date = '$date' ORDER BY sybase_dbnames.name";
  if ( $debug ) { echo "<p>sqlquery=$sqlquery</p>\n"; }

  $statsDB->query($sqlquery);
  while($row = $statsDB->getNextRow()) {
    echo "<tr> <td>" . $row[0] ."</td> <td align=\"right\">" . $row[1] . "</td> <td align=\"right\">" . $row[2] . "</td> <td align=\"right\">" . $row[3] . "</td> <td align=\"right\">" . $row[4]. "</td> </tr>";
  }
  $statsDB->disconnect();
 }
?>

</table>

<?php
include "common/finalise.php";
?>
