<?php
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
    
function chart($statsDB, $date, $type) {
    global $AdminDB, $debug;

    $sqlParamWriter = new SqlPlotParam();
  
    $calGraphValue = array(
        'keyreadhitrate' => "IF(key_read_requests>0, ((key_read_requests - key_reads) / key_read_requests) * 100, 0)",
        'qcachehitrate' => "IF(questions>0, ((questions - qcache_hits)/questions) * 100, 0)"
    );  
    if ( array_key_exists( $type, $calGraphValue ) ) {
        $col = $calGraphValue[$type];
    } else {
        $col = $type;
    }
  
    $sqlParam = SqlPlotParamBuilder::init()
              ->title("")
              ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
              ->yLabel('')
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  array( $col => 'value' ),
                  array( "$AdminDB.ddp_mysql_stats" ),
                  "",
                  array()
              )
              ->build();

    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL(
        $id,
        "$date 00:00:00",
        "$date 23:59:59",
        true,
        800,
        320
    );  
}

$statsDB = new StatsDB();

if ( isset($_GET['start']) ) { 
    $date = $_GET['start'];
}

echo "<H1>Hit Rates</H1>\n";
echo "<H4>Index Read Hit Rate</H4>\n";
chart($statsDB, $date, "keyreadhitrate");
echo "<H4>Query Cache Hit Rate</H4>\n";
chart($statsDB, $date, "qcachehitrate");

echo "<H1>Raw Stats</H1>\n";
$statsDB->query("DESCRIBE $AdminDB.ddp_mysql_stats");
while($row = $statsDB->getNextNamedRow()) {
    if ($row['Field'] != "time" ) {
        echo "<H4>" . $row['Field'] . "</H4>\n";
        chart($statsDB, $date, $row['Field']);
    }
}

require_once PHP_ROOT . "/common/finalise.php";
