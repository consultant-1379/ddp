<?php
$pageTitle = "Workflow Executions";
$YUI_DATATABLE = true;

include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class GrpWfExecs extends DDPObject {
  private static $instCount = 0;

  var $cols = array(
		    'wf' => 'Workflow', 
		    'numexec' => 'Executions',
		    'totaltime' => 'Total Duration',
		    'avgtime' => 'Average Duration',
		    'maxtime' => 'Max Duration',
		    'loadtime' => 'Loading Duration',
		    'aborted' => 'Aborted Executions'
		    );

    var $title = "Workflow Totals";

    var $grp;
    var $url;

    function __construct($grp,$url) {
      parent::__construct("workflowTotals" . self::$instCount);
      self::$instCount++;

      $this->grp = $grp;
      $this->url = $url;
    }

    function getData() {
        global $date;
	global $site;
	$sql = "
SELECT 
 eniq_workflow_names.name AS wf,
 COUNT(*) AS numexec,
 SEC_TO_TIME( SUM( TIME_TO_SEC( TIMEDIFF( tidle, tload ) ) ) ) AS totaltime,
 SEC_TO_TIME( ROUND(AVG(TIME_TO_SEC(TIMEDIFF(tidle, tload))),0) ) AS avgtime,
 SEC_TO_TIME( MAX( TIME_TO_SEC( TIMEDIFF( tidle, tload ) ) ) ) AS maxtime,
 SEC_TO_TIME( SUM( TIME_TO_SEC( TIMEDIFF( trun, tload ) ) ) ) AS loadtime,
 SUM(aborted) AS aborted
FROM
 eniq_workflow_executions, eniq_workflowgroup_names, eniq_workflow_names , sites
WHERE
 eniq_workflow_executions.siteid = sites.id AND sites.name = '$site' AND
 eniq_workflow_executions.grpid = eniq_workflowgroup_names.id AND eniq_workflowgroup_names.name = '$this->grp' AND
 eniq_workflow_executions.wfid = eniq_workflow_names.id AND
 eniq_workflow_executions.tload BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY wfid
ORDER BY eniq_workflowgroup_names.name
";
	$this->populateData($sql);
	
	foreach ($this->data as &$row) {
	  $wfName = $row['wf'];
	  $link = sprintf("<a href=\"%s&wf=%s\">%s</a>", $this->url, $wfName, $wfName);
	  $row['wf'] = $link;
	}

	return $this->data;
    }
}

class MultiGrpWfExecs extends DDPObject {
  private static $instCount = 0;

  var $cols = array(
		    'wf' => 'Workflow', 
		    'grp' => 'Group',
		    'numexec' => 'Executions',
		    'totaltime' => 'Total Duration',
		    'avgtime' => 'Average Duration',
		    'maxtime' => 'Max Duration',
		    'loadtime' => 'Loading Duration',
		    'aborted' => 'Aborted Executions'
		    );

    var $title = "Workflow Totals";

    var $wf;
    var $url;

    function __construct($wf,$url) {
      parent::__construct("grpWorkflowTotals" . self::$instCount);
      self::$instCount++;
      $this->wf = $wf;
      $this->url = $url;
    }

    function getData() {
        global $date;
	global $site;
	$sql = "
SELECT 
 eniq_workflow_names.name AS wf, eniq_workflowgroup_names.name AS grp,
 COUNT(*) AS numexec,
 SEC_TO_TIME( SUM( TIME_TO_SEC( TIMEDIFF( tidle, tload ) ) ) ) AS totaltime,
 SEC_TO_TIME( ROUND(AVG(TIME_TO_SEC(TIMEDIFF(tidle, tload))),0) ) AS avgtime,
 SEC_TO_TIME( MAX( TIME_TO_SEC( TIMEDIFF( tidle, tload ) ) ) ) AS maxtime,
 SEC_TO_TIME( SUM( TIME_TO_SEC( TIMEDIFF( trun, tload ) ) ) ) AS loadtime,
 SUM(aborted) AS aborted
FROM
 eniq_workflow_executions, eniq_workflowgroup_names, eniq_workflow_names , sites
WHERE
 eniq_workflow_executions.siteid = sites.id AND sites.name = '$site' AND
 eniq_workflow_executions.grpid = eniq_workflowgroup_names.id AND eniq_workflow_names.name LIKE  '$this->wf%' AND
 eniq_workflow_executions.wfid = eniq_workflow_names.id AND
 eniq_workflow_executions.tload BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY wfid
ORDER BY eniq_workflow_names.name
";
	$this->populateData($sql);
	
	foreach ($this->data as &$row) {
	  $wfName = $row['wf'];
	  $link = sprintf("<a href=\"%s&wf=%s\">%s</a>", $this->url, $wfName, $wfName);
	  $row['wf'] = $link;
	}

	return $this->data;
    }
}

function hourlyStats($wf,$date) {
  $sqlParamWriter = new SqlPlotParam();

  echo "<H2>Hourly Stats $wf</H2>\n";

  $graphs = new HTML_Table("border=0");    
  $sqlParam = 
    array( 'title'      => 'Average Processing Time',
	   'ylabel'     => 'Secs',
	   'type'        => 'sb',
	   'sb.barwidth' => '3600',
	   'presetagg'  => 'AVG:Hourly',
	   'persistent' => 'true',
	   'useragg'    => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'tload',
			'whatcol' => array('TIME_TO_SEC(TIMEDIFF(tidle,tload))' => 'Duration'),
			'tables'  => "eniq_workflow_executions, eniq_workflow_names, sites",
			'where'   => "eniq_workflow_executions.siteid = sites.id AND sites.name = '%s' AND eniq_workflow_executions.wfid = eniq_workflow_names.id AND eniq_workflow_names.name LIKE '%s%%'",
			'qargs'   => array( 'site', 'wf' )
			)
		 )
	   );      
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "wf=$wf" ) ) );

  $sqlParam =  
    array( 'title'      => 'Max Processing Time',
	   'ylabel'     => 'Secs',
	   'type'        => 'sb',
	   'sb.barwidth' => '3600',
	   'presetagg'  => 'MAX:Hourly',
	   'persistent' => 'true',
	   'useragg'    => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'tload',
			'whatcol' => array('TIME_TO_SEC(TIMEDIFF(tidle,tload))' => 'Duration'),
			'tables'  => "eniq_workflow_executions, eniq_workflow_names, sites",
			'where'   => "eniq_workflow_executions.siteid = sites.id AND sites.name = '%s' AND eniq_workflow_executions.wfid = eniq_workflow_names.id AND eniq_workflow_names.name LIKE '%s%%'",
			'qargs'   => array( 'site', 'wf' )
			)
		 )
	   );      
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "wf=$wf" ) ) );

  $sqlParam =  
    array( 'title'      => 'Total Processing Time',
	   'ylabel'     => 'Secs',
	   'type'        => 'sb',
	   'sb.barwidth' => '3600',
	   'presetagg'  => 'SUM:Hourly',
	   'persistent' => 'true',
	   'useragg'    => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'tload',
			'whatcol' => array('TIME_TO_SEC(TIMEDIFF(tidle,tload))' => 'Duration'),
			'tables'  => "eniq_workflow_executions, eniq_workflow_names, sites",
			'where'   => "eniq_workflow_executions.siteid = sites.id AND sites.name = '%s' AND eniq_workflow_executions.wfid = eniq_workflow_names.id AND eniq_workflow_names.name LIKE '%s%%'",
			'qargs'   => array( 'site', 'wf' )
			)
		 )
	   );      
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "wf=$wf" ) ) );

  $sqlParam =  
    array( 'title'      => 'Executed Workflows',
	   'ylabel'     => 'Count',
	   'type'        => 'sb',
	   'sb.barwidth' => '3600',
	   'presetagg'  => 'COUNT:Hourly',
	   'persistent' => 'true',
	   'useragg'    => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'tload',
			'whatcol' => array('*' => 'Executed'),
			'tables'  => "eniq_workflow_executions, eniq_workflow_names, sites",
			'where'   => "eniq_workflow_executions.siteid = sites.id AND sites.name = '%s' AND eniq_workflow_executions.wfid = eniq_workflow_names.id AND eniq_workflow_names.name LIKE '%s%%' AND eniq_workflow_executions.aborted = 0",
			'qargs'   => array( 'site', 'wf' )
			)
		 )
	   );      
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "wf=$wf" ) ) );

  $sqlParam =  
    array( 'title'      => 'Aborted Workflows',
	   'ylabel'     => 'Count',
	   'type'        => 'sb',
	   'sb.barwidth' => '3600',
	   'presetagg'  => 'SUM:Hourly',
	   'persistent' => 'true',
	   'useragg'    => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'tload',
			'whatcol' => array('aborted' => 'Aborted'),
			'tables'  => "eniq_workflow_executions, eniq_workflow_names, sites",
			'where'   => "eniq_workflow_executions.siteid = sites.id AND sites.name = '%s' AND eniq_workflow_executions.wfid = eniq_workflow_names.id AND eniq_workflow_names.name LIKE '%s%%'",
			'qargs'   => array( 'site', 'wf' )
			)
		 )
	   );      
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, "wf=$wf" ) ) );

  echo $graphs->toHTML();
}

if ( isset($_GET['start']) ) { 
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}


$folder = $_GET['folder'];

$statsDB = new StatsDB();

$sqlParam = 
    array( 'title'      => 'Execution Time for Workflow %s',
           'targs' => array( 'wf' ),
	   'ylabel'     => 'Seconds',
           'type'        => 'tb',
	   'useragg'     => 'true',
           'persistent' => 'true',
	   'useragg'    => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'tload',
			'whatcol' => array('TIME_TO_SEC(TIMEDIFF(tidle,tload))' => 'Duration', 'TIME_TO_SEC(TIMEDIFF(eniq_workflow_executions.tidle,eniq_workflow_executions.tload))' => 'barwidth'),
			'tables'  => "eniq_workflow_executions, eniq_workflow_names, sites",
			'where'   => "eniq_workflow_executions.siteid = sites.id AND sites.name = '%s' AND eniq_workflow_executions.wfid = eniq_workflow_names.id AND eniq_workflow_names.name = '%s'",
			'qargs'   => array( 'site', 'wf' )
			)
		 )
	   );	 
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
$url = $sqlParamWriter->getURL( $id, "$fromDate 00:00:00", "$toDate 23:59:59" );

$statsDB->query("
SELECT DISTINCT eniq_workflowgroup_names.name, eniq_workflow_names.name
FROM eniq_workflow_executions, eniq_folder_names, eniq_workflowgroup_names, eniq_workflow_names, sites
WHERE
 eniq_workflow_executions.siteid = sites.id AND
 eniq_workflow_executions.fldrid = eniq_folder_names.id AND 
 eniq_workflow_executions.grpid = eniq_workflowgroup_names.id AND
 eniq_workflow_executions.wfid = eniq_workflow_names.id AND
 sites.name = '$site' AND
 eniq_folder_names.name = '$folder' AND
 eniq_workflow_executions.tload BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
$wfGrps = array();
$grpsWithWf = array();
while ( $row = $statsDB->getNextRow() ) {
  $grp = $row[0]; 
  $wfInst = $row[1];

  if ( ! array_key_exists($grp,$wfGrps) ) {
    $wfGrps[$grp] = array();
  }
  $wfGrps[$grp][] = $wfInst;

  list($wf,$instance) = explode(".", $row[1]);
  if ( ! array_key_exists($wf,$grpsWithWf) ) {
    $grpsWithWf[$wf] = array();
  }
  $grpsWithWf[$wf][$instance] = $row[0];  
}
if ( $debug ) { 
  echo "<pre>"; 
  print_r($wfGrps); 
  print_r($grpsWithWf); 
  echo "</pre>\n"; 
}

$multiGrpWf = array();
foreach ( $grpsWithWf as $wf => $grps ) {
  /* Does the WF appear in multiple grps */
  if ( count($grps) > 1 ) {
    if ( $debug ) { echo "<pre>checking $wf</pre>\n"; }
    /* Is it the only WF in the grp */    
    $onlyWfInGrp = 1;
    foreach ($grps as $inst => $grp ) {
      foreach ($wfGrps[$grp] as $wfInst ) {
	if ( strpos($wfInst,$wf) === false ) {	 
	  if ( $debug ) { echo "<pre>no match for \"$wf\" in \"$wfInst\"</pre>\n"; }
	  $onlyWfInGrp = 0;
	}
      }
    }

    if ( $onlyWfInGrp ) {
      $multiGrpWf[] = $wf;
      foreach ( $grps as $wfInst => $grp ) {
	unset($wfGrps[$grp]);
      }
    }
  }
}
if ($debug) { print "<pre>after multi-Wf code wfGrps"; print_r($wfGrps); echo "multiGrpWf"; print_r($multiGrpWf); echo "</pre>"; }

if ( $debug ) { 
  echo <<<EOT
<div id="myLogger" class="yui-skin-sam"></div>
<script type="text/javascript">
var myLogReader = new YAHOO.widget.LogReader("myLogReader");
</script>

EOT;
} 

foreach ( $wfGrps as $grp => $wfs ) {
  echo "<H1>Group $grp</H1>\n";
  $table = new GrpWfExecs($grp,$url);
  echo $table->getClientSortableTableStr();

  /* Group the instances by wf */
  $instByWf = array();
  foreach ($wfs as $wfInst) {
    list($wf,$instance) = explode(".", $wfInst);
    if ( ! array_key_exists($wf,$instByWf) ) {
      $instByWf[$wf] = array();
    }
    $instByWf[$wf][] = $wfInst;
  }
  if ( $debug ) { echo "<pre>instByWf"; print_r($instByWf); echo "</pre>\n"; }

  /* Now for any workflow that appears more then 4 times in a group 
     display hourly stats */
  foreach ($instByWf as $wf => $wfInsts) {
    if ( count($wfInsts) > 4 ) {
      hourlyStats($wf,$fromDate);
    }
  }
}


foreach ( $multiGrpWf as $wf ) {
  hourlyStats($wf,$fromDate);
  echo "<H2>Daily Totals for $wf</H2>\n";
  $table = new MultiGrpWfExecs($wf,$url);
  echo $table->getClientSortableTableStr();
}


include "../common/finalise.php";
?>


