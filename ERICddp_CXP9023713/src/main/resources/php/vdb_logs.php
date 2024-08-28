<?php
$pageTitle = "Versant Database Logs";

$UI = false;

include "common/init.php";

if ( isset($_GET["vdbid"]) ) {
    $vdbid=$_GET["vdbid"];
}

$statsDB = new StatsDB();

if ( isset( $vdbid ) ) {
    $sql = "SELECT vdb_names.name as name, vdb_logs.data as data " .
        "FROM vdb_logs, vdb_names, sites " .
        "WHERE date = '" . $date . "' " .
        "AND vdb_logs.siteid = sites.id " .
        "AND sites.name = '" . $site . "'" .
        "AND vdb_logs.vdbid = vdb_names.id " .
        "AND vdb_names.id = " . $vdbid;

    $row = $statsDB->queryNamedRow($sql);

?>
    <h2>Log for <?= $row['name'] ?>: <?= $date ?></h2>
<pre>
<?= $row['data'] ?>
</pre>
<?php

} else {
    echo "<h2>Error: Missing parameter vdbid - please contact Administrator</h2>";
}

include "common/finalise.php";
?>
