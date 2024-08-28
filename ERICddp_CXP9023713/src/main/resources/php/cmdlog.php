<?php
$pageTitle = "Command Log";
include "common/init.php";

echo "<h1>Command Log Summary</h1>\n";

if ( isset($_GET["date"]) ) {
  $date=$_GET["date"];
  $sqlquery="SELECT cmd_mc.name, cmd_names.name, SUM(cmds.count) FROM cmd_mc, cmd_names, cmds, sites WHERE sites.name = '$site' AND sites.id = cmds.siteid AND cmds.mcid = cmd_mc.id AND cmds.cmdid = cmd_names.id AND date = \"$date\" GROUP BY cmds.mcid, cmds.cmdid ORDER BY cmd_mc.name";
 } else {
  $startdate = $_GET['start'];
  $enddate   = $_GET['end'];
  $sqlquery="SELECT cmd_mc.name, cmd_names.name, SUM(cmds.count) FROM cmd_mc, cmd_names, cmds, sites WHERE sites.name = '$site' AND sites.id = cmds.siteid AND cmds.mcid = cmd_mc.id AND cmds.cmdid = cmd_names.id AND date >= \"$startdate\" AND date <= \"$enddate\" GROUP BY cmds.mcid, cmds.cmdid ORDER BY cmd_mc.name";
 }

$statsDB = new StatsDB();

if ($debug) { echo "<p>$sqlquery</p>"; }
$statsDB->query($sqlquery);

$currMc = "";
while($row = $statsDB->getNextRow()) {
  if ( $row[0] != $currMc ) {       
    if ( $currMc != "" ) {
      echo "</table>\n";
    }
    
    $currMc = $row[0];    
    echo "<H4>$currMc</H4>\n";
    echo "<table cellpadding=\"2\" cellspacing=\"2\" border=\"1\">\n";
    echo "<tr> <th>Command</th> <th>Number</th> <tr>\n";
  }


  echo " <tr>  <td>$row[1]</td> <td>$row[2]</td>\n";  
 }

if ( $currMc != "" ) {
  echo "</table>\n";
}

?>

<?php
$statsDB->disconnect();
include "common/finalise.php";
?>
