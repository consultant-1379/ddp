<?php
$pageTitle = "ROP Loading Performance";
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";

const MINUTE = 'min';
const SEC = 'sec';

$statsDB = new StatsDB();

$result = $statsDB->query("
              SELECT
               ropStartTime, parsingStartTime, loadingEndTime, loadingTimeDuration, duration
              FROM
               eniq_loading_parsing_duration, sites
              WHERE
               sites.name = '$site' AND
               eniq_loading_parsing_duration.siteid = sites.id AND
               eniq_loading_parsing_duration.ropStartTime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              ORDER BY eniq_loading_parsing_duration.ropStartTime
              ");

$averageParsingLoadingHelp = <<<EOT
    This page displays the loader processing statistics of each ROP in tabular as well as in graphical form.
    <p>
    <ol>
        <li><b>ROP Start Time</b>: Starting time of the ROP.</li>
        <li><b>Loading Start Time</b>: Time when loading of ROP started.</li>
        <li><b>Loading End Time</b>: Time when loading of ROP ended.</li>
        <li><b>Loading Duration(sec)</b>: Time taken to loading the ROP.</li>
        <li><b>Processing Duration (sec)</b>: Time taken to process the ROP.</li>
    </ol>
    <b>NOTE:</b>
    <li>The processing duration of each ROP is the difference of Loading End Time and ROP Start Time.</li>
    <li>The Loading duration of each ROP is the difference of Loading End Time and Loading Start Time.</li>
EOT;

$numberOfRows = $statsDB->getNumRows();
$averageParsingLoadingTable = new HTML_Table();
$averageParsingLoadingTable->addRow( array('<b>ROP Start Time</b>', '<b>Loading Start Time</b>', '<b>Loading End Time</b>', '<b>Loading Duration(sec)</b>', '<b>Processing Duration (sec)</b>') );
if($numberOfRows > 0) {
    $totalOfDurationColumn = 0;
    while ($result = $statsDB->getNextNamedRow()) {
        $totalOfDurationColumn = $totalOfDurationColumn + $result['duration'];
        if($result['loadingTimeDuration'] == 0)
        {
            $result['loadingTimeDuration'] = "-:-:-";
        }
        $averageParsingLoadingTable->addRow( array($result['ropStartTime'], $result['parsingStartTime'], $result['loadingEndTime'], $result['loadingTimeDuration'], $result['duration']) );
    }
}
else {
    $averageParsingLoadingTable->addRow( array('No records found.') );
    $averageParsingLoadingTable->setCellAttributes(1, 0, "colspan='4'");
}

$table = new HTML_Table("border=1");
$result = $statsDB->queryRow("
              SELECT
               min(loadingTimeDuration) as min ,max(loadingTimeDuration) as max, avg(loadingTimeDuration) as avg, min(duration) as minimum, max(duration) as maximum, avg(duration) as average
              FROM
               eniq_loading_parsing_duration, sites
              WHERE
               sites.name = '$site' AND
               eniq_loading_parsing_duration.siteid = sites.id AND
               eniq_loading_parsing_duration.ropStartTime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
              ");
$table->addRow( array('<b>Duration Summary<b>', '<b>Minimum Time<b>', '<b>Maximum Time<b>', '<b>Average Time<b>') );
if($result[0]== 0 && $result[1] == 0 && $result[2] == 0 ){
    $table->addRow( array('<b>Loading Duration<b>','-:-:-','-:-:-','-:-:-'));
}else{
    $table->addRow( array('<b>Loading Duration<b>', floor($result[0]/60) . MINUTE . " " . $result[1]%60 . SEC, floor($result[1]/60) . MINUTE . " " . $result[1]%60 . SEC, floor($result[2]/60) . MINUTE . " " . $result[2]%60 . SEC));
}
$table->addRow( array('<b>Total(Parsing+Loading) Duration<b>', floor($result[3]/60) . MINUTE . " " . $result[3]%60 . SEC, floor($result[4]/60) . MINUTE . " " . $result[4]%60 . SEC, floor($result[5]/60). MINUTE . " " . $result[5]%60 . SEC));

$summaryParsingLoadingHelp = <<<EOT
    This page displays the minimum,average and maximum value of loading and processing duration across all the ROPs for the day.
    <p>
    <ol>
        <li><b>Minimum Time</b>: It shows the minimum time of loading and processing duration across all the ROPs for the day.</li>
        <li><b>Maximum Time</b>: It shows the maximum time of loading and processing duration across all the ROPs for the day.</li>
        <li><b>Average Time</b>: It shows the average time of loading and processing duration across all the ROPs for the day.</li>
    </ol>
EOT;

drawHeaderWithHelp("Summary", 1, "summaryParsingLoadingHelp", $summaryParsingLoadingHelp);
echo $table->toHTML();
echo "<br>";
drawHeaderWithHelp("ROP Loading Performance", 1, "averageParsingLoadingHelp", $averageParsingLoadingHelp);
echo $averageParsingLoadingTable->toHTML();
echo "<br>";

$sqlParam =
    array( 'title'   => 'ROP Loading Performance',
        'ylabel'     => 'Processing Duration (sec)',
        'type'       => 'sb',
        'useragg'    => 'true',
        'persistent' => 'true',
        'querylist'  =>
        array(
            array(
                'timecol' => 'ropStartTime',
                'whatcol' => array('duration' => 'ROP Processing Duration (sec)'),
                'tables'  => "eniq_loading_parsing_duration, sites",
                'where'   => "eniq_loading_parsing_duration.siteid = sites.id AND sites.name = '%s'",
                'qargs'   => array( 'site' )
            )
        )
    );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );
echo "<br><br>";

$sqlParam =
    array( 'title'   => 'Loading Performance',
        'ylabel'     => 'Loading Duration (sec)',
        'type'       => 'sb',
        'useragg'    => 'true',
        'persistent' => 'true',
        'querylist'  =>
        array(
            array(
                'timecol' => 'ropStartTime',
                'whatcol' => array('loadingTimeDuration' => 'Loading Duration (sec)'),
                'tables'  => "eniq_loading_parsing_duration, sites",
                'where'   => "eniq_loading_parsing_duration.siteid = sites.id AND sites.name = '%s'",
                'qargs'   => array( 'site' )
            )
        )
    );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );
echo "<br><br>";

include "../common/finalise.php";
?>