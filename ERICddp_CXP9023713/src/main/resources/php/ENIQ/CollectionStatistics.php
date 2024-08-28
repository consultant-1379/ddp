<?php
$pageTitle = "Collection Statistics";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class CollectionStatisticsTable extends DDPObject {
    var $cols = array(
        'node'        => 'Node Name',
        'ropCount'    => 'ROP Count',
        'avgDuration' => 'Avg Duration(sec)',
        'maxDuration' => 'Max Duration(sec)'
    );

    function __construct() {
        parent::__construct("CollectionStatisticsTable");
        $this->defaultOrderBy = "node";
        $this->defaultOrderDir = "ASC";
    }

    function getData() {
        global $date;
        global $site;
        global $webargs;
        $sql = "
            SELECT
                node as node,
                count(*) AS ropCount,
                round(avg(time_to_sec(timediff(stop_time,start_time))),1) AS avgDuration,
                max(time_to_sec(timediff(stop_time,start_time))) AS maxDuration
            FROM
                sim_node, sim_stats, sites
            WHERE
                sites.name = '$site' AND
                sites.id = siteid AND
                start_time BETWEEN '$date 00:00:00' and '$date 23:59:59' AND
                nodeid = sim_node.id
            GROUP BY
                nodeid
        ";
        $this->populateData($sql);
        $hyperlinkdata = array();
        foreach ($this->data as $key => $row) {
            $NodeName = $row['node'];
            $row['node'] = "<a href='" . PHP_WEBROOT . "/ENIQ/NodeAnalysis.php?" . $webargs . "&node=" . $NodeName . "'>" . $NodeName ."</a>";
            $hyperlinkdata[] = $row;
        }
        $this->data = $hyperlinkdata;
        return $this->data;
    }
}

$pageHelp = <<<EOT
The table presents the following metrics of SIM:
<ul>
    <li><b>Node Name:</b> Name of the network element.</li>
    <li><b>ROP Count:</b> Count of ROPs processed for the day.</li>
    <li><b>Avg Duration(sec):</b> Average time taken to process a ROP.</li>
    <li><b>Max Duration(sec):</b> Maximum time taken to process a ROP. </li>
</ul>
EOT;
drawHeaderWithHelp("Collection Statistics", 1, "pageHelp", $pageHelp);

$collectionStatistics = new CollectionStatisticsTable();
echo $collectionStatistics->getClientSortableTableStr();
include "../common/finalise.php";
?>