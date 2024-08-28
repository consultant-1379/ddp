<?php
$pageTitle = "PA Imports/Activations";
include "common/init.php";
include "classes/pa.php";
require_once 'HTML/Table.php';

class NeadInstr extends DDPObject {

    var $title = "NEAD Instrumentation";
    var $cols = array(
		      'ne' => 'NE',
		      'ttotal' => 'TAP',
		      'tread' => 'TRP',
		      'tfinalact' => 'UP',
		      'tnecommit' => 'CMT',
		      'tnemibtl' => 'CNEMIBTL',
		      'tnesync' => 'SAT',
		      'tx2prox' => 'X2PC',
		      'tnode' => 'NA',
		      'tcs' => 'MA',
		      'tother' => 'OTHER',
		      'nMoCreated' => 'MO Created',
		      'nMoDeleted' => 'MO Deleted',
		      'nMoModified' => 'MO Modified',
		      'nProxCreated' => 'Proxy Created',
		      'nProxDeleted' => 'Proxy Deleted'    
		      );

    var $actId;

    function __construct($actId) {
      parent::__construct("act_" . $actId);
      $this->actId = $actId;
    }

    function getData() {
      $sql = "
SELECT 
 ne.name AS ne, ttotal, tread, tfinalact, tnecommit, tnemibtl, tnesync,
 tx2prox, tnode, tcs, tother,
 nMoCreated, nMoDeleted, nMoModified,
 nProxCreated, nProxDeleted
FROM pa_activation_nead, ne
WHERE
 actid = $this->actId AND
 pa_activation_nead.neid = ne.id";
        $this->populateData($sql);
        return $this->data;
    }
};

function getImportDetails( $statsDB, $importId )
{
  $statsDB->query("
SELECT mo_names.name, pa_import_details.created, 
       pa_import_details.deleted, pa_import_details.updated
 FROM pa_import_details, mo_names
 WHERE pa_import_details.importid = $importId AND
       pa_import_details.moid = mo_names.id");
  
  $table = new HTML_Table('border=1');
  $table->addRow( array('MO', 'Created', 'Deleted', 'Updated'), null, 'th' );
  while($row = $statsDB->getNextRow()) {
    $table->addRow( $row );
  }

  return $table;
}

function getActivationContent( $statsDB, $actId )
{
  $statsDB->query("
SELECT mo_names.name, pa_activation_content.created, 
       pa_activation_content.deleted, pa_activation_content.updated
 FROM pa_activation_content, mo_names
 WHERE pa_activation_content.actid = $actId AND
       pa_activation_content.moid = mo_names.id");
  
  $table = new HTML_Table('border=1');
  $table->addRow( array('MO', 'Created', 'Deleted', 'Updated'), null, 'th' );
  while($row = $statsDB->getNextRow()) {
    $table->addRow( $row );
  }

  return $table;
}

function getActivationActions( $statsDB, $actId )
{
  $statsDB->query("
SELECT mo_names.name, actions.action, 
       actions.tTOTAL, actions.nTOTAL,
       actions.tFINDMO, actions.nFINDMO,
       actions.tCSCALL, actions.nCSCALL,
       actions.tGETPLAN, actions.nGETPLAN
 FROM pa_activation_pca_actions AS actions, mo_names
 WHERE actions.actid = $actId AND actions.moid = mo_names.id");
  
  $table = new HTML_Table('border=1');
  $table->addRow( array('MO', 'Action', 'Total Time', 'Total Number', 'FindMO Time', 'FindMO Number', 'CS Call Time', ' CS Call Number', 'GetPlan Time', 'Get Plan Number' ), null, 'th' );
  while($row = $statsDB->getNextRow()) {
    $table->addRow( $row );
  }

  return $table;
}


$imports = new PAImports();
$activations = new PAActivations();

if (isset($_GET['format']) && $_GET['format'] == "xls") {
    $excel = new ExcelWorkbook();
    $excel->addObject($imports);
    $excel->addObject($activations);
    $excel->write();
    exit;
}

$impPerfLink =  $php_webroot . "/import_perf.php?" . $webargs;
$rootdir = $rootdir . "/bulkcm/";

?>
<a href="?<?=$_SERVER['QUERY_STRING']?>&format=xls">Export to Excel</a>
<h1>Planned Area Imports / Activations</h1>

<a name="top"></a>

<ul>
 <li><a href="#imports">Imports</a></li>
 <li><a href="#act">Activations</a></li>
 <li><a href="#importdetails">Import Details</a></li>
 <li><a href="#actdetails">Activation Details</a></li>
 <li><a href="<?=$impPerfLink?>">Import Performance By MO Type</a></li>
<?php
  if ( file_exists($rootdir . "/actplot.txt") ) {
    $graphURL = $php_webroot . 
    "/graph.php?site=$site&dir=$dir&oss=$oss&target=analysis&file=bulkcm/actplot.txt&full=1";
    $windowName = "bulkcm_" . $site . $dir;
    $jsCode = " return popupWindow( '$graphURL', '$windowName', 640, 480 )";
    echo "<li><a href=\"#\" onclick=\" $jsCode \">Import/Activation Activity Plot</a></li>\n";
  }
?>
</ul>

<a name="imports"></a> 
<h1><?=$imports->title?></h1>
<?php
$imports->getHtmlTable(true);
?>

<br><a href="#top">Back to Top</a>

<a name="act"></a> 
<H1><?=$activations->title?></H1>
<?php
$activations->getHtmlTable();
?>
<br><a href="#top">Back to Top</a>

<a name="importdetails"></a>
<H1>Import Details</H1>
<?php

$statsDB = new StatsDB();
$singleTypeImports = array();
while($row = $imports->getNext()) {
    $table = getImportDetails( $statsDB, $row['id'] );
    if ( $table->getRowCount() > 1 ) {
        $time = date("H:i", strtotime($row['start']));
        echo "<a name=\"" . $imports->arrayPointer . "\"></a>\n";
        echo "<h2>" . $time . " " . $row['file'] . "</h2>\n";

        echo $table->toHTML();
        if ( $table->getRowCount() == 2 ) {
            $singleTypeImports[] = $row['id'];
        }
    } else {
        $time = date("H:i", strtotime($row['start']));
        $tableFile = $rootdir . "opcounts_" . $time . ".html";
        if ( $debug ) { echo "<p>tableFile=$tableFile</p>\n"; }
        if ( file_exists($tableFile) ) {
            readfile($tableFile);
        }
    }
}

?>
<br><a href="#top">Back to Top</a>

<a name="actdetails"></a>
<H1>Activation Details</H1>
<?php
while($row = $activations->getNext()) {
    $contentTable = getActivationContent($statsDB,$row['id']);
    if ( $contentTable->getRowCount() > 1 ) {
        $time = date("H:i", strtotime($row['start']));
        echo "<h2>" . $time . " " . $row['pa'] . "</h2>\n";
        echo "<H3>Content</H3>\n";
        echo $contentTable->toHTML();
    }

    if ( $row['type'] == 'pca' ) {
      $instrRow = $statsDB->queryRow("SELECT numActions, tTotal, tAlgo, tReadActions, tUnPlan, numTxCommit, tTxCommit, numJmsSend, tJmsSend FROM pa_activation_pca WHERE actid = " . $row['id']);
      if ( $instrRow ) {
	echo "<H3>PCA Instrumentation</H3>";
	$table = new HTML_Table("border=0");
	$table->addRow( array("Number Of Actions", $instrRow[1]) );
	$table->addRow( array("Total Time", $instrRow[1]) );
	$table->addRow( array("Algorithm Time", $instrRow[2]) );
	$table->addRow( array("Read Action Time", $instrRow[3]) );
	$table->addRow( array("Unplan Time", $instrRow[4]) );
	$table->addRow( array("Number Of Tx Commits", $instrRow[5]) );
	$table->addRow( array("Tx Commits Time", $instrRow[6]) );
	$table->addRow( array("Number Of JMS Sends", $instrRow[7]) );
	$table->addRow( array("JMS Send Time", $instrRow[8]) );
	echo $table->toHTML();

	echo "<H4>Processing Actions</H4>";

	$actionTable = getActivationActions($statsDB,$row['id']);
	echo $actionTable->toHTML();
      }
    }

    $neadInstr = new NeadInstr($row['id']);
    if ( count($neadInstr->getData()) > 0 ) {
      echo "<H3>NEAD Instrumentation</H3>\n";
      echo $neadInstr->getHtmlTableStr();
    }
}	
?>
<br><a href="#top">Back to Top</a>
<?php
include "common/finalise.php";
?>
