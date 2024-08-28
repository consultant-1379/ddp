<?php
$pageTitle = "Page Execution";

include "../common/init.php";

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

$statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

function chart($startdate, $enddate, $pageid) {
  global $debug;

  $where = "";
  if ( $pageid ) {
    $where = "pageid = $pageid";
  }

  $sqlParam = SqlPlotParamBuilder::init()
      ->title("Page Executions")
      ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
      ->yLabel("Duration")
      ->addQuery(
          'ddpadmin.ddp_page_exec.time',
          array( 'duration' => 'duration' ),
          array( 'ddpadmin.ddp_page_exec' ),
          $where,
          array()
          )
     ->build();

   $sqlParamWriter = new SqlPlotParam();
   $id = $sqlParamWriter->saveParams($sqlParam);
   echo $sqlParamWriter->getImgURL($id, "$startdate 00:00:00", "$enddate 23:59:59", true, 800, 320 );
}

class PageExecTbl extends DDPObject {
    var $cols = array(
        "page" => "Page",
        "count" => "Count",
        'avg' => "Average Execution Time",
        'max' => "Maximum Execution Time"
    );

    function __construct() {
        parent::__construct("page_exec");
    }

    function getData() {
      global $startdate,$enddate;
      $sql = "
SELECT pn.name AS page, COUNT(*) AS count,
       ROUND( AVG(pe.duration), 2) AS avg, ROUND( MAX(pe.duration), 2) AS max,
       pn.id AS pageid
 FROM ddpadmin.ddp_page_names AS pn, ddpadmin.ddp_page_exec AS pe
 WHERE pn.id = pe.pageid AND
       pe.time BETWEEN '$startdate 00:00:00' AND '$enddate 23:59:59'
 GROUP BY pageid";
        $this->populateData($sql);

        # each value needs to be surrounded in a link to qplot
        $pageLink = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

        foreach ($this->data as $key => $d) {
          $d['page'] = '<a href="' . $pageLink . '&pageid=' . $d['pageid'] . '">' . $d['page'];
          $this->data[$key] = $d;
        }
        return $this->data;
    }
}

if ( $date ) {
  $startdate = $date;
  $enddate = $date;
  $per = "Hour";
} else {
  $startdate = $_GET['start'];
  $enddate   = $_GET['end'];
  $per = "Day";
}

$pageid = 0;
if ( isset($_GET["pageid"]) ) {
  $pageid = $_GET["pageid"];
}

if ( $pageid ) {
  $row = $statsDB->queryRow("SELECT name FROM ddpadmin.ddp_page_names WHERE id = $pageid");
  $for = $row[0];
} else {
  $for = "All Pages";
}

echo "<H1>Page Executions</H1>\n";
chart($startdate, $enddate, $pageid);

if ( ! $pageid ) {
  echo "<H1>Page Execution Table</H1>\n";
  $allPageTable = new PageExecTbl();
  $allPageTable->getSortableHtmlTable();
}

include PHP_ROOT . "/common/finalise.php";
