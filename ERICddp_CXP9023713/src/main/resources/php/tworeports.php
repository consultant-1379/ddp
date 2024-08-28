<?php
$pageTitle = "Side By Side";
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

function outputTimeForm( $r1_tstart, $r1_tend, $r1_report, 
			 $r2_tstart, $r2_tend, $r2_report )
{
  global $debug;

  $myURL = $_SERVER['PHP_SELF'];

  echo <<<EOT
    <form action="$myURL" method="get" name="timerange" id="timerange">
    <input type='hidden' name='debug' value="$debug"/>
    <table border="0">

    <tr>
    <td align="right" valign="top"><b>Left Report:</b></td>
    <td valign="top" align="left">	<input size="20" maxlength="20" name="r1_report" type="text" value="$r1_report" /></td>
    
    <td align="right" valign="top"><b>From:</b></td>
    <td valign="top" align="left">	<input size="20" maxlength="20" name="r1_tstart" type="text" value="$r1_tstart" /></td>
    
    <td align="right" valign="top"><b>To:</b></td>
    <td valign="top" align="left">	<input size="20" maxlength="20" name="r1_tend" type="text" value="$r1_tend" /></td>

    <td valign="top" align="left" rowspan="2">	<input name="submit" value="Submit" type="submit" /></td>

    </tr>

    <tr>
    <td align="right" valign="top"><b>Right Report:</b></td>
    <td valign="top" align="left">	<input size="20" maxlength="20" name="r2_report" type="text" value="$r2_report" /></td>
    
    <td align="right" valign="top"><b>From:</b></td>
    <td valign="top" align="left">	<input size="20" maxlength="20" name="r2_tstart" type="text" value="$r2_tstart" /></td>
    
    <td align="right" valign="top"><b>To:</b></td>
    <td valign="top" align="left">	<input size="20" maxlength="20" name="r2_tend" type="text" value="$r2_tend" /></td>

    </tr>

    </table>
    </form>
EOT;

}

function processReports( $r1_tstart,$r1_tend,$r1_report, $r2_tstart,$r2_tend,$r2_report ) {
  global $debug;
  if ( $debug ) { echo "<pre>processReports: $r1_tstart,$r1_tend,$r1_report, $r2_tstart,$r2_tend,$r2_report</pre>\n"; }

  $left  = processReport( $r1_tstart,$r1_tend,$r1_report );
  $right = processReport( $r2_tstart,$r2_tend,$r2_report );

  $table = new HTML_Table("border=0");

  $thead =& $table->getHeader();
  $thead->addRow(array( $r1_report . " " . $r1_tstart . "->" . $r1_tend, "",
			$r2_report . " " . $r2_tstart . "->" . $r2_tend, "" ), null, 'th' );  
  $thead->setCellAttributes(0, 0, 'align="center" colspan="2"');
  $thead->setCellAttributes(0, 2, 'align="center" colspan="2"');

  $numRows = count($left);
  for ( $index = 0; $index < $numRows; $index++ ) {
    $table->addRow( array( $left[$index][0], $left[$index][1], $right[$index][1], $right[$index][0] ) );
  }

  echo $table->toHTML();
}

function processReport( $tstart, $tend, $report ) {
  global $debug;

  $rows = array();

  $reportXML = loadReport($report);

  foreach ($reportXML->graph as $graph) {
    $label = (string)$graph->label;
    $url = (string)$graph->url;

    if ( $debug > 0 ) echo "<pre>processReport label=$label url=$url</pre>\n";
    $url = preg_replace('/tstart=[^&]+/', htmlentities("tstart=" . $tstart), $url);
    $url = preg_replace('/tend=[^&]+/', htmlentities("tend=" . $tend), $url);
    if ( $debug > 0 ) echo "<pre>processReport url post update=$url</pre>\n";

    $rows[] = array( $label, '<img src="' . $url . '" />' );
  }
  
  return $rows;
}

$debug = 0;
if ( isset($_REQUEST["debug"]) ) {
  $debug = $_REQUEST["debug"];
}

if ( $debug > 0 ) {
  echo "<pre>main REQUEST_METHOD=" . $_SERVER["REQUEST_METHOD"] . "\n";
  print_r($_REQUEST); 
  echo "</pre>\n";
}

$r1_report = ''; if ( isset($_GET['r1_report']) ) $r1_report = $_GET['r1_report'];
$r1_tstart = ''; if ( isset($_GET['r1_tstart']) ) $r1_tstart = $_GET['r1_tstart'];
$r1_tend   = ''; if ( isset($_GET['r1_tend']) ) $r1_tend = $_GET['r1_tend'];
$r2_report = ''; if ( isset($_GET['r2_report']) ) $r2_report = $_GET['r2_report'];
$r2_tstart = ''; if ( isset($_GET['r2_tstart']) ) $r2_tstart = $_GET['r2_tstart'];
$r2_tend   = ''; if ( isset($_GET['r2_tend']) ) $r2_tend = $_GET['r2_tend'];


outputTimeForm( $r1_tstart,$r1_tend,$r1_report, $r2_tstart,$r2_tend,$r2_report );
if ( $r1_report ) {
  processReports( $r1_tstart,$r1_tend,$r1_report, $r2_tstart,$r2_tend,$r2_report );
}  
