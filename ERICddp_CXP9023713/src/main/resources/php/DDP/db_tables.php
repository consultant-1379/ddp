<?php
$pageTitle = "StatsDB Tables";
if ( isset($_GET["chart"]) ) {
    $UI = false;
}

include_once "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

$statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

class TableList extends DDPObject {
    var $cols = array (
        "tbl" => "Table",
        "data" => "Data Size (Mb)",
        "idx" => "Index Size (Mb)",
        "avglen" => "Avg Row Length (B)",
        "data/avglen" => "# of Rows (approx.)",
    );

    function __construct() {
        parent::__construct("tables");
        $this->defaultOrderBy = "data";
        $this->defaultOrderDir = "DESC";
    }

    function getData() {
        global $startdate, $enddate, $addChartLinks, $type;
        $sql = "
SELECT
  tn.name AS tbl,
  ROUND( AVG(ts.data) / 1024 / 1024,3) AS data,
  ROUND( AVG(ts.idx) / 1024 / 1024, 3) AS idx,
  ROUND( AVG(ts.avglen), 0 ) AS avglen,
  ROUND( AVG(ts.data / ts.avglen), 0) AS 'data/avglen',
  tn.id AS tableid
FROM
  ddpadmin.ddp_table_stats AS ts,
  ddpadmin.ddp_table_names AS tn
WHERE
  ts.tableid = tn.id AND
  ts.date BETWEEN '$startdate' AND '$enddate' AND
  ts.type = '$type'
GROUP BY ts.tableid
";
        $this->populateData($sql);

        if ( $addChartLinks ) {
            $graphCols = array( 'data' => 'Data', 'idx' => 'Index', 'data/avglen' => 'Rows');
            $graphIds = array();
            $sqlParamWriter = new SqlPlotParam();
            $where = "ddpadmin.ddp_table_stats.tableid = %d AND ddpadmin.ddp_table_stats.type = '$type'";
            foreach ( $graphCols as $col => $label ) {
                $sqlParam =
                          array( 'title'  => $label,
                                 'type'   => 'tsc',
                                 'ylabel' => '',
                                 'useragg' => 'true',
                                 'persistent' => 'false',
                                 'querylist' =>
                                 array(
                                     array(
                                         'timecol' => 'date',
                                         'whatcol' => array( $col => $label ),
                                         'tables'  => "ddpadmin.ddp_table_stats",
                                         'where'   => $where,
                                         'qargs' => array( 'tableid' )
                                     )
                                 ),
                          );
                $graphIds[$col] = $sqlParamWriter->saveParams($sqlParam);
            }

            foreach ($this->data as $key => $d) {
                foreach ( $graphCols as $col => $label ) {
                    $link = $sqlParamWriter->getURL( $graphIds[$col], "$startdate 00:00:00", "$enddate 23:59:59", "tableid=" . $d['tableid'] );
                    $d[$col] = '<a href="' . $link . '">' . $d[$col] . "</a>\n";
                }
                $this->data[$key] = $d;
            }
        }

        return $this->data;
    }
}

function mainFlow( $addChartLinks, $startdate, $enddate, $type ) {
    $tableid = 0;
    if ( isset($_GET["tableid"]) ) {
        $tableid = $_GET["tableid"];
    }

    if ( $addChartLinks ) {
        $graphCols = array( 'data' => 'Data', 'idx' => 'Index',);
        $sqlParamWriter = new SqlPlotParam();
        foreach ( $graphCols as $col => $label ) {
            echo "<H1>$label Size</H1>\n";
            $sqlParam = array(
                'title'  => $label,
                'type'   => 'tsc',
                'ylabel' => '',
                'useragg' => 'false',
                'presetagg'  => 'SUM:Daily',
                'persistent' => 'false',
                'querylist' => array(
                                   array(
                                       'timecol' => 'date',
                                       'whatcol' => array( $col => $label ),
                                       'tables'  => "ddpadmin.ddp_table_stats",
                                       'where'   => "ddpadmin.ddp_table_stats.type = '$type'"
                                   )
                               ),
            );
            $id = $sqlParamWriter->saveParams($sqlParam);
            echo $sqlParamWriter->getImgURL( $id, "$startdate 00:00:00", "$enddate 23:59:59" );
        }
    }

    if ( ! $tableid ) {
        $tableStats = new TableList();
        $tableStats->getSortableHtmlTable();
    }
}

if ( $date ) {
    $startdate = $date;
    $enddate = $date;
    $addChartLinks = FALSE;
} else {
    $startdate = getArgs('start');
    $enddate   = getArgs('end');
    $addChartLinks = TRUE;
}

$type = getArgs('type');

if ( $startdate === null || $enddate === null ) {
    echo "ERROR: Date is null. Please provide the date to proceed";
} else {
    mainFlow( $addChartLinks, $startdate, $enddate, $type );
}

include_once PHP_ROOT . "/common/finalise.php";

