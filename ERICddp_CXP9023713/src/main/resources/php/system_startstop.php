<html>
<body>

<?php
$pageTitle = "System Start/Stop";

$UI = false;
include "common/init.php";
require_once 'HTML/Table.php';

$ssId = $_GET['ssid'];

$statsDB = new StatsDB();
$row = $statsDB->queryRow("SELECT TIME(begintime), TIME(endtime),type FROM system_startstop WHERE id = $ssId");

echo "<H1>System $row[2] From $row[0] To $row[1]</H1>\n";

$table = new HTML_Table('border=1');
$table->addRow( array( "Time", "ManagedComponet", "Duration" ), null, 'th' );


$row = $statsDB->query("
SELECT TIME(system_startstop_details.eventtime), mc_names.name, system_startstop_details.eventduration
FROM system_startstop_details, mc_names 
WHERE system_startstop_details.ssid = $ssId AND
      system_startstop_details.mcid = mc_names.id
ORDER BY system_startstop_details.eventtime");
while($row = $statsDB->getNextRow()) {
  $table->addRow($row);
}
      
echo $table->toHTML();

?>

</body>
</html>
