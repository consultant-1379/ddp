<?php
$pageTitle = "Network Elements";
include "common/init.php";

?>

<H1>ONRM</H1>
<table border>
 <tr><th>NE Type</th> <th>Version</th> <th>Count</th> <th>Num Connected</th> </tr>

<?php
require_once 'HTML/Table.php';

$statsDB = new StatsDB();
$rnsTable = getRnsTable($statsDB,$site,$date);

$sql = "
SELECT me_types.name, node_ver.name, count, connected 
FROM me_types, node_ver, onrm_ne_counts, sites
WHERE
 onrm_ne_counts.me_typeid = me_types.id AND
 onrm_ne_counts.node_verid = node_ver.id AND
 onrm_ne_counts.siteid = sites.id AND
 onrm_ne_counts.date = '$date' AND
 sites.name = '$site'
";

if ( $debug ) { echo "<p>sql = $sql</p>"; }
$statsDB->query($sql);
$totalNodes = 0;
$totalConnectedNodes = 0;
while($row = $statsDB->getNextRow()) {
  echo "<tr> <td>$row[0]</td>  <td>$row[1]</td> <td>$row[2]</td>  <td>$row[3]</td> </tr>\n";
  $totalNodes += $row[2];
  $totalConnectedNodes += $row[3];
 }
echo "<tr><td></td><td><b>Total:</b></td><td><b>" . $totalNodes . "</b></td><td><b>" . $totalConnectedNodes . "</b></td></tr>\n";

?>
</table>

<H1>AXE Based Node IO Types</H1>
<table border>
 <tr><th>ME Type</th> <th>IO Type</th> <th>Count</th> </tr>

<?php

$sql = "
SELECT DISTINCT met.name, iot.name, COUNT(*)
FROM onrm_node_list nl, sites s, me_types met, onrm_io_types iot
WHERE nl.me_typeid = met.id AND nl.siteid = s.id AND nl.io_typeid = iot.id AND io_typeid != 0
AND date = '$date' AND s.name = '$site'
GROUP BY nl.me_typeid, nl.io_typeid 
ORDER BY met.name
";

if ( $debug ) { echo "<p>sql = $sql</p>"; }
$statsDB->query($sql);
$totalAxeBasedNodeCnt = 0;
while($row = $statsDB->getNextRow()) {
  echo "<tr> <td>$row[0]</td>  <td>$row[1]</td> <td>$row[2]</td> </tr>\n";
  $totalAxeBasedNodeCnt += $row[2];
 }

if ($totalAxeBasedNodeCnt > 0) {
    echo "<tr><td></td><td><b>Total:</b></td><td><b>" . $totalAxeBasedNodeCnt . "</b></td></tr>\n";
}

?>
</table>

<H1>WRAN/LTE/TDRAN</H1>

<table>
<tr valign="top">  

<td>

<?php
$wranMim = new HTML_Table('border=1');
$wranMim->setCaption('NE MIM Version');

$sql = "
SELECT ne_types.name, ne_mim_ver.name, ne_mim.conn, ne_mim.dis, ne_mim.never
FROM ne_mim_ver, ne_mim, ne_types, sites
WHERE
 ne_mim.mimid = ne_mim_ver.id AND
 ne_mim.netypeid = ne_types.id AND
 ne_mim.siteid = sites.id AND
 ne_mim.date = '$date' AND
 sites.name = '$site' 
ORDER BY ne_types.name, ne_mim_ver.name
";

if ( $debug ) { echo "<p>sql = $sql</p>"; }
$statsDB->query($sql);
$numConnected = $numDisconnected = $numNeverConnected = $numNodes = 0;
$hasConnCounts = 0;
while($row = $statsDB->getNextRow()) {
  # For the first row figure out if we have the connection status filled in
  if ( $wranMim->getRowCount() == 0 ) {
    if ( ! is_null($row[3]) ) {
      $hasConnCounts = 1;
    }

    if ( $hasConnCounts ) {
      $wranMim->addRow( array( 'NE Type', 'NE MIM Ver', '# Connected', '# Disconnected', '# Never Connected', '# Total'), null, 'th' );
    } else {
      $wranMim->addRow( array( 'NE Type', 'NE MIM Ver', '# Nodes' ), null, 'th' );
    }
  }

  if ( $hasConnCounts ) {
      $total = $row[2] + $row[3] + $row[4];
    $wranMim->addRow( array( $row[0], $row[1], $row[2], $row[3], $row[4], $total ) );
    $numConnected += $row[2];
    $numDisconnected += $row[3];
    $numNeverConnected += $row[4];
  } else {
    $wranMim->addRow( array( $row[0], $row[1], $row[2] ) );
    $numNodes += $row[2];
  }
 }
if ( $hasConnCounts ) {
    $total = $numConnected + $numDisconnected + $numNeverConnected;
    $wranMim->addRow( array( "",
                "<b>Total:</b>",
                "<b>" . $numConnected . "</b>",
                "<b>" . $numDisconnected . "</b>",
                "<b>" . $numNeverConnected . "</b>",
                "<b>" . $total . "</b>"));
} else {
    $wranMim->addRow( array( "", "<b>Total:</b>", $numNodes));
}
echo $wranMim->toHTML();
?>

</td>
<td>

<table border>
<CAPTION>NE Software Version</CAPTION>
 <tr> <th>NE Type</th> <th>Upgrade Package</th> <th>Num Nodes</th> </tr>
<?php
$sql = "
SELECT ne_types.name, ne_up_ver.name, ne_up.numne
FROM ne_up_ver, ne_up, ne_types, sites
WHERE
 ne_up.upid = ne_up_ver.id AND
 ne_up.netypeid = ne_types.id AND
 ne_up.siteid = sites.id AND
 ne_up.date = '$date' AND
 sites.name = '$site' 
ORDER BY ne_types.name, ne_up_ver.name
";

if ( $debug ) { echo "<p>sql = $sql</p>"; }
$statsDB->query($sql);
$total = 0;
while($row = $statsDB->getNextRow()) {
  echo "<tr> <td>$row[0]</td>  <td>$row[1]</td> <td>$row[2]</td> </tr>\n";
  $total += $row[2];
 }
echo "<tr> <td></td>  <td><b>Total:</b></td><td><b>" . $total . "</b></td></tr>\n";

?>
</table>

</td>
</tr>
</table>

<?php
# BSC versions if present
$sql = "SELECT COUNT(count) AS count FROM cna_bsc_counts,sites WHERE date = '" . $date . "' AND 
    siteid = sites.id AND sites.name = '" . $site . "'";
$row = $statsDB->queryNamedRow($sql);
if ($row['count'] > 0) {
    echo "<h1>BSC Versions</h1>\n"; 
    $bscTbl = new HTML_Table('border=1');
    $bscTbl->addRow(array("BSC Version", "Count"), null, "th");
    $sql = "SELECT bsc_ver.name AS version, cna_bsc_counts.count AS count FROM cna_bsc_counts,bsc_ver,sites WHERE
        cna_bsc_counts.siteid = sites.id AND sites.name = '" . $site . "' AND
        date = '" . $date . "' AND cna_bsc_counts.bsc_ver_id = bsc_ver.id ORDER BY version ASC";
    $statsDB->query($sql);
    $count = 0;
    while($row = $statsDB->getNextNamedRow()) {
        $bscTbl->addRow(array($row['version'], $row['count']));
        $count += $row['count'];
    }
    $bscTbl->addRow(array("<b>Total:</b>", $count));
    echo $bscTbl->toHTML();
}
?>
<H1>Cell Counts GSM/LTE/WRAN/TDRAN</H1>

<?php
$sql = "
SELECT mo_names.name AS mo, SUM(count - planned) AS num
FROM sites,mo,vdb_names,mo_names WHERE
sites.name = '" . $site . "' AND sites.id = mo.siteid AND date = '" . $date . "' AND
(mo_names.name = 'UtranCell' OR mo_names.name = 'EUtranCellFDD' OR mo_names.name = 'EUtranCellTDD')  AND planned IS NOT NULL AND
mo.vdbid = vdb_names.id AND vdb_names.name = 'WRAN_SUBNETWORK_MIRROR_CS' AND mo.moid = mo_names.id
GROUP BY mo
";
$statsDB->query($sql);
?>
<table border>
<tr><th>Cell Type</th><th>Count</th></tr>
<?php
while($row = $statsDB->getNextNamedRow()) {
    if ($row['mo'] == "UtranCell") {
        echo "<tr><td>WRAN/TDRAN</td><td>" . $row['num'] . "</td></tr>\n";
    } else if ($row['mo'] == "EUtranCellFDD") {
        echo "<tr><td>LTE FDD</td><td>" . $row['num'] . "</td></tr>\n";
    } else if ($row['mo'] == "EUtranCellTDD") {
        echo "<tr><td>LTE TDD</td><td>" . $row['num'] . "</td></tr>\n";
    }
}
# Get BSC cell count as provided by CNA ( from cnadb)
$sql = "SELECT SUM(count) AS count FROM cna_bsc_cell_counts,sites WHERE
    date = '" . $date . "' AND siteid = sites.id AND sites.name = '" . $site . "'";
$row = $statsDB->queryNamedRow($sql);
if ($row['count'] != null) {
    echo "<tr><td>BSC</td><td>" . $row['count'] . "</td></tr>\n";
}
?>
</table>
<?php
#
# Print table of nodes per RNS if available
#
 if ( $rnsTable->getRowCount() ) {
   echo "<H1><a name=\"rnslist\">RNS List</H1>\n";
   echo "<p>
The following table lists the number of nodes in each RNS. RANAGs (RXIs) are counted in a <b>fake</b> RNS called RANAG.
The <b>Number of Nodes</b> is the sum of the RNC plus the RBSs in the RNS, so the number of RBSs in an RNS
is <b>Number of Nodes - 1</b></p>\n";
   echo $rnsTable->toHTML();
 }

include "common/finalise.php";
?>
