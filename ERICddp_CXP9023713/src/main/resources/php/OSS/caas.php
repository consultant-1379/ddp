<?php
$pageTitle = "CAAS Performance";

include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class CaasStats extends DDPObject {
  var $cols = array(
		    'hostname' => 'Host',
		    'authentications' => '#Authentication', 
		    'authorizations' => '#Authorization',
		    'failures' => '#Failures',
		    'avg_proc_time' => 'Average Processing Time(ms)'
		    );

    var $title = "caasStats";

    function __construct() {
        parent::__construct("caasStats");
    }

    function getData() {
        global $date;
	global $site;
	$sql = "
SELECT 
  servers.hostname,
  FORMAT( SUM(caas_performance.authentications), 0) AS authentications,
  FORMAT( SUM(caas_performance.authorization), 0) AS authorizations,
  FORMAT( SUM(caas_performance.answered), 0 ) AS failures,
  ROUND( AVG(caas_performance.avg_proc_time), 1) AS avg_proc_time
FROM caas_performance, servers, sites
WHERE
   caas_performance.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
   caas_performance.siteid = sites.id AND sites.name = '$site' AND
   caas_performance.serverid = servers.id
GROUP BY
 caas_performance.serverid
";
	$this->populateData($sql);
	return $this->data;
    }
}
echo "<H2>Daily Totals</H2>\n";
$tbl = new CaasStats();
echo $tbl->getSortableHtmlTableStr();

echo "<H2>Statistics</H2>\n";
$graphTable = new HTML_Table('border=0');
$sqlParamWriter = new SqlPlotParam();

$graphParams = array( 'authentications' => 'Authentications', 'authorization' => 'Authorizations', 
		      'answered' => 'Failures', 'avg_proc_time' => 'Average Processing Time' );
foreach ( $graphParams as $dbCol => $title ) {
  $sqlParam = 
    array( 'title'      => $title,
	   'ylabel'     => '',
	   'useragg'    => 'true',
	   'persistent' => 'false',
	   'querylist' => 
	   array(
		 array (
			'timecol' => 'time',
			'multiseries' => 'servers.hostname',
			'whatcol' => array( $dbCol => $title ),
			'tables'  => "caas_performance, servers, sites",
			'where'   => "caas_performance.siteid = sites.id AND sites.name = '%s' AND caas_performance.serverid = servers.id",
			'qargs'   => array( 'site' )
			)
		 )
	   );  
  $id = $sqlParamWriter->saveParams($sqlParam);
  $url = $sqlParamWriter->getImgURL( $id, 
				     "$date 00:00:00", "$date 23:59:59", 
				     true, 640, 240 );
  $graphTable->addRow(array($url)); 	      	      
}
echo $graphTable->toHTML();

include "../common/finalise.php"; 

?>
