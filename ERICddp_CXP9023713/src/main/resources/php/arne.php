<?php
$pageTitle = "ARNE";
include "common/init.php";

require_once 'HTML/Table.php';

function showPlansForImport($statsDB,$importId) {
  echo "<H1>Import Plans</H1>\n";
  
  $statsDB->query("
SELECT 
 plan, TIME(created), TIME(deleted), TIMEDIFF(modified,created), 
 IF( pistart IS NULL, NULL, TIMEDIFF(ldap,pistart) ),
 IF( pistart IS NULL, NULL, TIMEDIFF(utran,pistart) ),
 IF( pistart IS NULL, NULL, TIMEDIFF(geran,pistart) ),
 IF( piend IS NULL, NULL, TIMEDIFF(updated,piend) ),
 IF( piend IS NULL, NULL, TIMEDIFF(deleted,updated) ),
 IF( piend IS NULL, NULL, TIMEDIFF(deleted,created) )
FROM arne_import_detail
WHERE arne_import_detail.importid = $importId
ORDER BY created");

  $table = new HTML_Table("border=1");
  $table->addRow(array('Created', 'Deleted', 'Populate', 'Ldap', 'Utran', 'Geran', 'Update','Delete','Total'), null, 'th');
  $planCreateTimes = array();
  while($row = $statsDB->getNextRow()) {
    $plan = array_shift($row);
    $planCreateTimes[$plan] = $row[0];
    $row[0] = '<a href="#' . $plan . "\">$row[0]</a>";
    $table->addRow($row);
  }
  $row = $statsDB->queryRow("
SELECT 
 'Total','',
 SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(modified,created)))), 
 SEC_TO_TIME(SUM(TIME_TO_SEC(IF( pistart IS NULL, NULL, TIMEDIFF(ldap,pistart) )))),
 SEC_TO_TIME(SUM(TIME_TO_SEC(IF( pistart IS NULL, NULL, TIMEDIFF(utran,pistart) )))),
 SEC_TO_TIME(SUM(TIME_TO_SEC(IF( pistart IS NULL, NULL, TIMEDIFF(geran,pistart) )))),
 SEC_TO_TIME(SUM(TIME_TO_SEC(IF( piend IS NULL, NULL, TIMEDIFF(updated,piend) )))),
 SEC_TO_TIME(SUM(TIME_TO_SEC(IF( piend IS NULL, NULL, TIMEDIFF(deleted,updated) )))),
 SEC_TO_TIME(SUM(TIME_TO_SEC(IF( piend IS NULL, NULL, TIMEDIFF(deleted,created) ))))
FROM arne_import_detail
WHERE arne_import_detail.importid = $importId
");
  $table->addRow($row);

  echo $table->toHTML();

  $planTables = array();
  $statsDB->query("
SELECT 
 arne_import_content.plan, mo_names.name,
 arne_import_content.creates, arne_import_content.updates, arne_import_content.deletes 
FROM arne_import_content, mo_names 
WHERE 
 arne_import_content.importid = $importId AND 
 arne_import_content.moid = mo_names.id");
  while($row = $statsDB->getNextRow()) {
    $plan = array_shift($row);
    if ( array_key_exists($plan,$planTables) ) {
      $planTable = $planTables[$plan];
    } else {
      $planTable = new HTML_Table("border=1");
      $planTable->addRow(array("MO Type", "Create", "Update", "Delete"), null, 'th');
      $planTables[$plan] = $planTable;
    }
    $planTable->addRow($row);
  }

  foreach ( $planCreateTimes as $plan => $createTime ) {
    echo "<a name=\"$plan\"><H2>$createTime</H2>\n";    
    echo $planTables[$plan]->toHTML();
  }
}

function showImportOverview($statsDB) {
  global $site, $date, $webroot;

  echo "<H1>Imports</H1>\n";

  $table = new HTML_Table("border=1");

  $table->getHeader()->addRow( array(''),null,'th');
  $table->getHeader()->setCellAttributes(0,0,'colspan="2"');
  $table->getHeader()->setCellAttributes(0,2,'colspan="3"');
  $table->getHeader()->setCellContents( 0, 2, 'ManagedElement', 'th');
  $table->getHeader()->setCellAttributes( 0, 5,'colspan="3"' );
  $table->getHeader()->setCellContents( 0, 5, 'MO', 'th');


  $table->getHeader()->addRow( array('Start Time', 'Duration', 'Create', 'Update', 'Delete', 'Create', 'Update', 'Delete'), null, 'th' );

  $statsDB->query("SELECT arne_import.id FROM arne_import, sites WHERE arne_import.siteid = sites.id AND sites.name = '$site' AND arne_import.start BETWEEN '$date 00:00:00' AND '$date 23:59:59' ORDER BY arne_import.start");
  
  $importIds = array();
  while($row = $statsDB->getNextRow()) {
    $importIds[] = $row[0];
  }

  foreach ( $importIds as $importId ) {
    $row = $statsDB->queryRow("SELECT start, TIMEDIFF(end,start) FROM arne_import WHERE id = $importId");
    $outRow = array();
    $outRow[] = '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&importid=$importId" . "\">$row[0]</a>";
    $outRow[] = $row[1];
    
    $row = $statsDB->queryRow("
SELECT 
 SUM(arne_import_content.creates), SUM(arne_import_content.updates), SUM(arne_import_content.deletes) 
FROM arne_import_content, mo_names 
WHERE 
 arne_import_content.importid = $importId AND
 arne_import_content.moid = mo_names.id AND mo_names.name = 'ManagedElement'");
    $outRow[] = $row[0];
    $outRow[] = $row[1];
    $outRow[] = $row[2];

    $row = $statsDB->queryRow("
SELECT SUM(arne_import_content.creates), SUM(arne_import_content.updates), SUM(arne_import_content.deletes)
FROM arne_import_content WHERE importid = $importId");
    $outRow[] = $row[0];
    $outRow[] = $row[1];
    $outRow[] = $row[2];
    
    $table->addRow($outRow);
  }
  echo $table->toHTML();

  $webroot = $webroot . "/arne";  
  echo <<<EOF
<H1>ARNE Heap</H1>
<img src="$webroot/arne_heap.jpg" alt="">
<H1>MAF Heap</H1>
<img src="$webroot/maf_heap.jpg" alt="">
EOF;

}


$statsDB = new StatsDB();

if ( isset($_GET['importid']) ) {
  showPlansForImport($statsDB,$_GET['importid']);
} else {
  showImportOverview($statsDB);
}

$statsDB->disconnect();

include "common/finalise.php";

?>