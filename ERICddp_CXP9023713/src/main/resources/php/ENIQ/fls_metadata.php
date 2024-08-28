<?php
$pageTitle = "Metadata And Symlink Information";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class MetadataTable extends DDPObject {
    var $cols = array(
                    'node'       => 'Node',
                    'file_count' => 'Number of files fetched',
                    'total_time' => 'Time taken(sec)'
                    );

    function __construct() {
        parent::__construct("MetadataTable");
    }

    function getData() {
        global $date, $site;
        $sql = "
            SELECT
             eniq_stats_fls_file_details.nodeId,
             fls_file_symlink_nodeType_id_mapping.nodeType As node,
             sum(eniq_stats_fls_file_details.fileCount) As file_count,
             round(sum(eniq_stats_fls_file_details.timeTaken)/1000,2) As total_time
            FROM
             eniq_stats_fls_file_details, fls_file_symlink_nodeType_id_mapping, sites
            WHERE
             eniq_stats_fls_file_details.siteId = sites.id AND sites.name = '$site' AND
             eniq_stats_fls_file_details.nodeId  = fls_file_symlink_nodeType_id_mapping.id AND
             eniq_stats_fls_file_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY
             nodeId;
            ";
        $this->populateData($sql);
        return $this->data;
    }
}

$pageHelp = <<<EOT
The table displays the following information:
<ul>
    <li><b>Node:</b> It displays name of the network element.</li>
    <li><b>Number of files fetched:</b> It displays the total number of files fetched by the node.</li>
    <li><b>Time taken(sec):</b> It displays the total time taken by the node to fetch the files.</li>
</ul>
The below graphs display the following information per ROP interval throughout the day for each node:
<ul>
    <li><b>File Count Graph:</b> It displays the number of files fetched by the node per rop interval throughout the day.</li>
    <li><b>Time taken Graph:</b> It displays the time taken to fetch the files by the node per rop interval throughout the day.</li>
</ul>
EOT;
drawHeaderWithHelp("Metadata Information", 1, "pageHelp", $pageHelp);
$totalTable = new MetadataTable();
echo $totalTable->getClientSortableTableStr();
echo "<br>";

$statsDB = new StatsDB();
$graphs = new HTML_Table("border=0");
$qPlots = array();
$qPlots["file"] = array(
                      'title'   => 'File Count',
                      'ylabel'  => 'Number of files fetched',
                      'type'    => 'tsc',
                      'whatcol' => array('fileCount' => 'File Count')
                      );
$qPlots["timetaken"] = array(
                           'title'   => 'Time Taken',
                           'ylabel'  => 'Time taken to fetch files(sec)',
                           'type'    => 'tsc',
                           'whatcol' => array('round(timeTaken/1000,2)' => 'Time Taken')
                           );

foreach ( $qPlots as $key => $param ) {
    $sqlParam = array(
        'title'      => $param['title'],
        'ylabel'     => $param['ylabel'],
        'type'       => $param['type'],
        'useragg'    => 'true',
        'persistent' => 'true',
        'querylist'  => array(
            array (
                'timecol'     => 'time',
                'multiseries' => 'fls_file_symlink_nodeType_id_mapping.nodeType',
                'whatcol'     => $param['whatcol'],
                'tables'      => "eniq_stats_fls_file_details, fls_file_symlink_nodeType_id_mapping, sites",
                'where'       => "sites.id = eniq_stats_fls_file_details.siteId AND sites.name = '$site' AND
                                 eniq_stats_fls_file_details.nodeId = fls_file_symlink_nodeType_id_mapping.id",
                'qargs'       => array('site')
            )
        )
    );

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 )));
}
echo $graphs->toHTML();

$fileTypeFlag = false;

class SymlinkTable extends DDPObject {
    var $cols = array(
                    'node'         => 'Node',
                    'fileType'     => 'File Type',
                    'symlinkCount' => 'Number of symbolic links',
                    'total_time'   => 'Time Taken(sec)'
                    );

    function __construct() {
        parent::__construct("SymlinkTable");
    }

    function getData() {
        global $date, $site, $fileTypeFlag;
        $sql = "
            SELECT
             eniq_stats_fls_symlink_details.nodeId,
             fls_file_symlink_nodeType_id_mapping.nodeType As node,
             eniq_stats_fls_symlink_details.fileType As fileType,
             count(*) AS symlinkCount,
             round(sum(eniq_stats_fls_symlink_details.timeTaken)/1000,2) As total_time
            FROM
             fls_file_symlink_nodeType_id_mapping,eniq_stats_fls_symlink_details, sites
            WHERE
             sites.id = eniq_stats_fls_symlink_details.siteId AND sites.name = '$site' AND
             eniq_stats_fls_symlink_details.nodeId  = fls_file_symlink_nodeType_id_mapping.id AND
             eniq_stats_fls_symlink_details.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY
             nodeId, fileType;
            ";
        $this->populateData($sql);
        foreach ($this->data as &$row) {
            $fileType = $row['fileType'];
            if(empty($fileType)) {
                $fileType = "- - -";
                $fileTypeFlag = true;
            }
            $row['fileType'] = $fileType;
        }
        return $this->data;
    }
}

$page = <<<EOT
    The table displays the below information:
    <ul>
        <li><b>Node:</b> It displays name of the network element.</li>
        <li><b>File Type:</b> It displays the type of file.</li>
        <li><b>Number of Symbolic links:</b> It displays the total number of symlinks created by the node for the file type.</li>
        <li><b>Time taken(sec):</b> It displays the total time taken to create symlinks by the node for the file type.</li>
    </ul>
    The below graphs display the following information per ROP interval throughout the day for each node along with it's file type:
    <ul>
        <li><b>Symlink Count Graph:</b> It displays the number of symlinks created by the node for a file type per rop interval throughout the day.</li>
        <li><b>Time taken Graph:</b> It displays the time taken to create symlinks by the node for a file type per rop interval throughout the day.</li>
    </ul>
    <b>NOTE</b>: If the file type is shown as &nbsp&nbsp "- - -" &nbsp&nbsp then it indicates that there is no file type information available for the node.
EOT;
drawHeaderWithHelp("Symbolic link Information", 1, "page", $page);

$totalTable = new SymlinkTable();
echo $totalTable->getClientSortableTableStr();
echo "<br>";

$graphs = new HTML_Table("border=0");
$qPlots = array();

$fileType = '';
if($fileTypeFlag) {
    $fileType = 'fls_file_symlink_nodeType_id_mapping.nodeType';
}
else {
    $fileType = 'CONCAT(fls_file_symlink_nodeType_id_mapping.nodeType, " ", fileType)';
}

$qPlots["symlink"] = array(
                         'title'       => 'Symlink Count',
                         'ylabel'      => 'Symlinks created',
                         'type'        => 'tsc',
                         'multiseries' => $fileType,
                         'whatcol'     => array('count(*)' => 'Symlink Count')
                         );
$qPlots["timetaken"] = array(
                           'title'       => 'Time Taken',
                           'ylabel'      => 'Time taken(sec)',
                           'type'        => 'tsc',
                           'multiseries' => $fileType,
                           'whatcol'     => array('round(sum(timeTaken)/1000,2)' => 'Time Taken')
                           );

foreach ( $qPlots as $key => $param ) {
    $sqlParam = array(
        'title'      => $param['title'],
        'ylabel'     => $param['ylabel'],
        'type'       => $param['type'],
        'useragg'    => 'false',
        'persistent' => 'false',
        'querylist'  => array(
            array (
                'timecol'     => 'time',
                'multiseries' => $param['multiseries'],
                'whatcol'     => $param['whatcol'],
                'tables'      => "eniq_stats_fls_symlink_details, fls_file_symlink_nodeType_id_mapping, sites",
                'where'       => "sites.id = eniq_stats_fls_symlink_details.siteId AND sites.name = '$site' AND
                                 eniq_stats_fls_symlink_details.nodeId = fls_file_symlink_nodeType_id_mapping.id group by nodeId, time",
                'qargs'       => array('site')
            )
        )
    );

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240)));
}
echo $graphs->toHTML();

include "../common/finalise.php";
?>