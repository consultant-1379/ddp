<?php
$pageTitle = "ROP File Integrity";
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";

function createGraph($date, $title, $whatcol) {
    $sqlParamWriter = new SqlPlotParam();
    $graphs = new HTML_Table();
    $sqlParam = array(
        'title'       => $title,
        'ylabel'      => "Count",
        'type'        => 'sb',
        'persistent'  => 'true',
        'useragg'     => 'true',
        'querylist'   =>
            array(
                array(
                    'timecol' => 'rop_time',
                    'whatcol' => $whatcol,
                    'tables'  => "eniq_stats_file_ingress_processed, sites",
                    'where'   => "eniq_stats_file_ingress_processed.site_id = sites.id AND sites.name = '%s'",
                    'qargs'   => array('site')
                )
            )
        );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
    echo $graphs->toHTML();
    echo "<br>";
}
$ropFileIntegrityHelp = <<<EOT
The below graphs display the number of filelist created and filelist parsed per ROP interval(15 mins) throughout the day.
EOT;
drawHeaderWithHelp("ROP File Integrity", 1, "ropFileIntegrityHelp", $ropFileIntegrityHelp);
createGraph($date, "Filelist Created", array('no_of_files_created' => 'Files created'));
createGraph($date, "Filelist Parsed", array('no_of_files_parsed' => 'Files Parsed'));
include "../common/finalise.php";
?>