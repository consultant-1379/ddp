<?php
$pageTitle = "Script Execution";
$YUI_DATATABLE = TRUE;
include "../common/init.php";

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

$statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);


class ScriptExecTbl extends DDPObject {
    var $cols = array(
        "script" => "Script",
        "execs" => "Count",
        'avg' => "Average Execution Time",
        'total' => "Total Execution Time"
    );

    function __construct() {
        parent::__construct("page_exec");
    }

    function getData() {
      global $startdate,$enddate;

      $sql = "
SELECT
 sn.name AS script,
 se.execs AS execs,
 ROUND( (se.duration/ se.execs)/1000, 2) AS avg,
 SEC_TO_TIME(SUM( ROUND(se.duration/1000,0) )) AS total,
 sn.id AS scriptid
FROM
 ddpadmin.ddp_script_names AS sn, ddpadmin.ddp_script_exec AS se
WHERE
  sn.id = se.scriptid AND
 se.date BETWEEN '$startdate' AND '$enddate'
GROUP BY scriptid";
        $this->populateData($sql);


        # each value needs to be surrounded in a link to qplot
    $sqlParam =
      array( 'title'       => 'Script Execution Time' ,
         'ylabel'      => 'Seconds',
         'useragg'    => 'true',
         'persistent' => 'false',
         'querylist' =>
         array(
               array (
                  'timecol' => 'date',
                  'whatcol' => array ( 'duration/1000' => "Seconds" ),
                  'tables'  => "ddpadmin.ddp_script_exec",
                  'where'   => "ddpadmin.ddp_script_exec.scriptid = %d",
                  'qargs'   => array( 'scriptid' )
                  )
               )
         );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
        foreach ($this->data as $key => $d) {
      $d['script'] =   '<a href="' .
        $sqlParamWriter->getURL( $id, "$startdate 00:00:00", "$enddate 23:59:59", "scriptid=" . $d['scriptid'] ) .
        '">' . $d['script'] . "</a>";
      $this->data[$key] = $d;
        }
        return $this->data;
    }
}

if ( $date ) {
  $startdate = $date;
  $enddate = $date;
} else {
  $startdate = $_GET['start'];
  $enddate   = $_GET['end'];
}


echo "<H1>Script Execution Table</H1>\n";
$table = new ScriptExecTbl();
echo $table->getClientSortableTableStr();

include PHP_ROOT . "/common/finalise.php";
?>
