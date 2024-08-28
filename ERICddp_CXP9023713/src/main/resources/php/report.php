<?php
$pageTitle = "MultiPlot";

require_once "common/functions.php";
require_once "./classes/QFAdaptor.php";
require_once 'HTML/Table.php';

if ( file_exists('/data/ruleinst') ) {
  $ruleInstDir = '/data/ruleinst';
} else {
  $ruleInstDir = '/tmp';
}

function loadReport( $report ) {
  global $ruleInstDir;
  global $debug;

  $reportFile = $ruleInstDir . "/" . $report . ".xml";
  if ( file_exists($reportFile) ) {
    $xml = simplexml_load_file($reportFile);
  } else {
    $xml = simplexml_load_string("<?xml version='1.0'?><report/>");
  }

  if ( $debug > 0 ) {
    echo "<pre>loadReport xml\n"; print_r($xml); echo "</pre>\n";
  }

  return $xml;
}

function saveReport($report,$xml) {
  global $ruleInstDir, $debug;
  if ( $debug > 0 ) { echo "<pre>saveReport: ruleInstDir=$ruleInstDir report=$report</pre>\n"; }

  $doc = new DOMDocument('1.0');
  if ( $debug > 0 ) { echo "<pre>saveReport: doc\n"; print_r($doc); echo "</pre>\n"; }

  $doc->formatOutput = true;
  $domnode = dom_import_simplexml($xml);
  if ( $debug > 0 ) { echo "<pre>saveReport: domnode after dom_import_simplexml\n"; print_r($domnode); echo "</pre>\n"; }
  $domnode = $doc->importNode($domnode, true);
  $domnode = $doc->appendChild($domnode);
  if ( $debug > 0 ) { echo "<pre>saveReport: domnode\n"; print_r($domnode); echo "</pre>\n"; }

  file_put_contents( $ruleInstDir . "/" . $report . ".xml", $doc->saveXML() );
}


function makeEditForm( $report ) {
  global $debug;

  $form = new HTML_QuickForm('main', 'POST');
  $form->addElement('header', null, $report);
  $frmName = $form->addElement('hidden','form','editMain');
  $frmName->setValue('editMain');
  $form->addElement('hidden', 'report', $report );
  $form->addElement('hidden', 'debug', $debug );

  $reportXML = loadReport($report);
  $graphList = array();
  foreach ($reportXML->graph as $graph) {
    $label = (string)$graph->label;
    $graphList[] = $label;
  }

  $graphSelect = $form->addElement('select', 'graph_id', 'Graphs:', $graphList, array( 'style' => "width: 60em" ));
  $graphSelect->setMultiple(true);
  $graphSelect->setSize(5);

  $buttons[] = &HTML_QuickForm::createElement('submit', 'btnAdd', 'Add');
  $buttons[] = &HTML_QuickForm::createElement('submit', 'btnRemove', 'Remove');
  $buttons[] = &HTML_QuickForm::createElement('submit', 'btnExport', 'Export');
  $buttons[] = &HTML_QuickForm::createElement('submit', 'btnImport', 'Import');
  $buttons[] = &HTML_QuickForm::createElement('submit', 'btnDone', 'Done');

  $form->addGroup($buttons, null, null, '&nbsp;');

  return $form;
}

function processEditForm($report) {
  global $ruleInstDir;

  $form = makeEditForm($report);
  $values = $form->exportValues();

  if ( isset($values['btnAdd']) ) {
    $addForm = makeEditAddForm($report);
    $addForm->display();
  } else if ( isset($values['btnRemove']) ) {
    $reportXML = loadReport($report);
    $graphId = (int)$values['graph_id'][0];
    unset($reportXML->graph[$graphId]);

    saveReport($report,$reportXML);

    $form = makeEditForm($report);
    $form->display();
  } else if ( isset($values["btnExport"]) ) {
    header( "content-type: text/xml" );
    header("Cache-Control: private, must-revalidate"); // HTTP/1.1
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    header("Content-Disposition: attachment; filename=\"$report.xml\"");

    readfile($ruleInstDir . "/" . $report . ".xml");
  } else if ( isset($values["btnImport"]) ) {
    $form = makeImportForm($report);
    $form->display();
  } else if ( isset($values["btnDone"]) ) {
    outputTimeForm( '','',$report );
  }
}

function makeImportForm($report) {
  global $debug;

  $form = new HTML_QuickForm('main', 'POST');
  $form->addElement('header', null, $report);
  $frmName = $form->addElement('hidden','form','editImport');
  $frmName->setValue('editImport');
  $form->addElement('hidden', 'report', $report );
  $form->addElement('hidden', 'debug', $debug );

  $file =& $form->addElement('file','reportfile',"Report File");
  $form->addElement('submit', null, 'Import');

  return $form;
}

function processEditImportForm($report) {
  global $debug;

  $form = makeImportForm($report);
  $values = $form->exportValues();
    if ( $debug > 0 ) {
      echo "<pre>processEditImportForm values\n"; print_r($values); echo "</pre>\n";
    }

  $file =& $form->getElement('reportfile');

  if ($file->isUploadedFile()) {
    # Move the upgrade file to /tmp
    $uploadParam = $file->getValue();
    $filename = $uploadParam['name'];
    $file->moveUploadedFile('/tmp');
    $xml = simplexml_load_file('/tmp/' . $filename);

    if ( $debug > 0 ) {
      echo "<pre>processEditImportForm xml\n"; print_r($xml); echo "</pre>\n";
    }

    saveReport($report,$xml);
  } else {
    if ( $debug > 0 ) { echo "<pre>isUploadedFile returned false</pre>\n"; }
  }

  $form = makeEditForm($report);
  $form->display();
}

function makeEditAddForm($report) {
  global $debug;

  $form = new HTML_QuickForm('main', 'POST');
  $form->addElement('header', null, $report);
  $frmName = $form->addElement('hidden','form','editAdd');
  $frmName->setValue('editAdd');
  $form->addElement('hidden', 'report', $report );
  $form->addElement('hidden', 'debug', $debug );

  $form->addElement('text', 'label', "Label", array('size' => 20, 'maxlength' => 255));
  $form->addElement('text', 'url', "URL", array('size' => 128, 'maxlength' => 2048));

  $form->addElement('submit', 'btnEditSave', 'Save');

  return $form;
}

function processEditAddForm($report) {
  global $debug;

  $form = makeEditAddForm($report);
  $values = $form->exportValues();

  if ( $values['label'] != '' && $values['url'] != '' ) {
    $reportXML = loadReport($report);
    $graph = $reportXML->addChild("graph");
    $graph->addChild("label", $values['label']);

    $url = $values['url'];
    if ( $debug > 0 ) echo "<pre>processEditAddForm url=$url</pre>\n";
    $url = preg_replace('/action=appletplot/', 'action=imgplot', $url );
    if ( $debug > 0 ) echo "<pre>processEditAddForm url replace applet plot=$url</pre>\n";
    $url = preg_replace('/width=\d+/', 'width=640', $url );
    if ( $debug > 0 ) echo "<pre>processEditAddForm url replace width=$url</pre>\n";

    $graph->addChild("url", htmlentities($url));

    saveReport($report,$reportXML);
  }

  $form = makeEditForm($report);
  $form->display();
}

function processReport( $tstart, $tend, $report ) {
  global $debug;

  $table = new HTML_Table("border=0");

  $reportXML = loadReport($report);

  foreach ($reportXML->graph as $graph) {
    $label = (string)$graph->label;
    $url = (string)$graph->url;

    if ( $debug > 0 ) echo "<pre>processReport label=$label url=$url</pre>\n";
    $url = preg_replace('/tstart=[^&]+/', htmlentities("tstart=" . $tstart), $url);
    $url = preg_replace('/tend=[^&]+/', htmlentities("tend=" . $tend), $url);
    if ( $debug > 0 ) echo "<pre>processReport url post update=$url</pre>\n";

    $table->addRow(array( $label, '<img src="' . $url . '" />' ));
  }
  echo $table->toHTML();
}

function outputTimeForm( $tstart, $tend, $report )
{
  global $debug;

  $myURL = $_SERVER['PHP_SELF'];

  echo <<<EOT
    <form action="$myURL" method="get" name="timerange" id="timerange">
    <input type='hidden' name='debug' value="$debug"/>
    <table border="0">
    <tr>
    <td align="right" valign="top"><b>Report:</b></td>
    <td valign="top" align="left">  <input size="20" maxlength="20" name="report" type="text" value="$report" /></td>

    <td align="right" valign="top"><b>From:</b></td>
    <td valign="top" align="left">  <input size="20" maxlength="20" name="tstart" type="text" value="$tstart" /></td>

    <td align="right" valign="top"><b>To:</b></td>
    <td valign="top" align="left">  <input size="20" maxlength="20" name="tend" type="text" value="$tend" /></td>

    <td valign="top" align="left">  <input name="update" value="Update" type="submit" /></td>
    <td valign="top" align="left">  <input name="edit" value="Edit" type="submit" /></td>

    </tr>
    </table>
    </form>
EOT;

}

//phpinfo(INFO_VARIABLES);

$debug = 0;
if ( isset($_REQUEST["debug"]) ) {
  $debug = $_REQUEST["debug"];
}

if ( $debug > 0 ) {
  echo "<pre>main REQUEST_METHOD=" . $_SERVER["REQUEST_METHOD"] . "\n";
  print_r($_REQUEST);
  if ( $debug > 1 ) {
    phpinfo();
  }
  echo "</pre>\n";

}

if ( $_SERVER["REQUEST_METHOD"] == "GET") {
  $report = ''; if ( isset($_GET['report']) ) $report = $_GET['report'];
  if ( isset($_GET['edit']) ) {
    $form = makeEditForm($report);
    $form->display();
  } else {
    $tstart = ''; if ( isset($_GET['tstart']) ) $tstart = $_GET['tstart'];
    $tend   = ''; if ( isset($_GET['tend']) ) $tend = $_GET['tend'];
    outputTimeForm( $tstart,$tend,$report );
    processReport( $tstart,$tend,$report );
  }
} else {
  $formName = $_POST["form"];
  $report = $_POST["report"];
  if ( $formName == "editMain" ) {
    processEditForm($report);
  } else if ( $formName == "editAdd" ) {
    processEditAddForm($report);
  } else if ( $formName == "editImport" ) {
    processEditImportForm($report);
  }
}
?>

