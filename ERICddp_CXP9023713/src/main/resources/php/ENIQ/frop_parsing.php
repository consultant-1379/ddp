<?php
$pageTitle = "Flexible ROP Handling";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class FrhDailyTotal extends DDPObject {
    var $cols = array(
        'source'    => 'Source',
        'type'      => 'Type',
        'rop_count' => 'Distinct ROP Count',
        'rows_avg'  => 'Average Rows',
        'rows_max'  => 'Max Rows',
        'rows_sum'  => 'Total Rows',
        'cntr_avg'  => 'Average Counters',
        'cntr_max'  => 'Max Counters',
        'cntr_sum'  => 'Total Counters'
    );
    function __construct() {
        parent::__construct("frhDailyTotal");
        $this->defaultOrderBy = "cntr_sum";
        $this->defaultOrderDir = "DESC";
    }
    function getData() {
        global $date, $site, $workflowType;
        unset($this->data);
        $sql = "
            SELECT
             eniq_stats_source.name AS source,
             eniq_stats_types.name AS type,
             eniq_stats_adaptor_totals.rop_count AS rop_count,
             eniq_stats_adaptor_totals.rows_avg AS rows_avg,
             eniq_stats_adaptor_totals.rows_max AS rows_max,
             eniq_stats_adaptor_totals.rows_sum AS rows_sum,
             eniq_stats_adaptor_totals.cntr_avg AS cntr_avg,
             eniq_stats_adaptor_totals.cntr_max AS cntr_max,
             eniq_stats_adaptor_totals.cntr_sum AS cntr_sum
            FROM
             eniq_stats_adaptor_totals, eniq_stats_source, eniq_stats_types, sites, eniq_stats_workflow_types
            WHERE
             eniq_stats_adaptor_totals.siteid = sites.id AND sites.name = '$site' AND
             eniq_stats_adaptor_totals.sourceid = eniq_stats_source.id AND
             eniq_stats_adaptor_totals.typeid = eniq_stats_types.id AND
             eniq_stats_adaptor_totals.workflow_type = eniq_stats_workflow_types.workflow_type_id AND
             eniq_stats_workflow_types.workflow_type = '$workflowType' AND
             eniq_stats_adaptor_totals.day = '$date'
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

$statsDB = new StatsDB();
function parseGraph ($title, $ylabel, $type, $whatColumn, $workflowType) {
    global $site, $date;
    $graphs = new HTML_Table("border=0");
    $sqlParam = array(
        'title'       => $title,
        'ylabel'      => $ylabel,
        'type'        => $type,
        'useragg'     => 'true',
        'persistent'  => 'true',
        'forcelegend' => 'true',
        'querylist'   => array(
                            array (
                                'timecol' => 'timeslot',
                                'multiseries' => 'eniq_stats_source.name',
                                'whatcol' => $whatColumn,
                                'tables'  => "eniq_stats_adaptor_sessions, eniq_stats_source, sites, eniq_stats_workflow_types",
                                'where'   => "eniq_stats_adaptor_sessions.sourceid = eniq_stats_source.id AND
                                              eniq_stats_adaptor_sessions.workflow_type = eniq_stats_workflow_types.workflow_type_id AND
                                              eniq_stats_workflow_types.workflow_type = '$workflowType' AND
                                              eniq_stats_adaptor_sessions.siteid = sites.id AND sites.name = '%s'",
                                'qargs'   => array( 'site' )
                            )
                        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow(array($sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 240)));
    echo $graphs->toHTML();
}
echo "<H1>Parsing</H1>\n";
$frhRopHelp = <<<EOT
The below graphs display the session duration and counters parsed data for FRH ROP(Base ROP).
<ul>
    <li><b>Duration graph : </b>It displays the maximum session time taken by each source for parsing.</li>
    <li><b>Counters Parsed : </b>It displays the total number of counters parsed for each source.
</ul>
EOT;
drawHeaderWithHelp("Sessions - FRH ROP", 2, "frhRopHelp", $frhRopHelp);
$workflowType = 'FRH_ROP';
parseGraph('Duration', 'Seconds', 'tsc', array('TIME_TO_SEC(TIMEDIFF(maxend,minstart))' => 'Duration'), $workflowType);
parseGraph('Counters Parsed', 'Counter', 'sb', array('cntr_sum' => 'Counters' ), $workflowType);

$frhRopDailyHelp = <<<EOT
The table displays below information.
<ul>
    <li><b>Source :</b>It displays the name of source.</li>
    <li><b>Type :</b>It displays the name of source type.</li>
    <li><b>Distinct ROP Count:</b>It displays the total number of ROP files processed for a particular source and type.</li>
    <li><b>Average Rows :</b>It displays the average number of rows processed for a particular source and type.</li>
    <li><b>Max Rows :</b>It displays the maximum number of rows processed for a particular source and type.</li>
    <li><b>Total Rows :</b>It displays the total rows processed for a particular source and type.</li>
    <li><b>Average Counters :</b>It displays the average number of counters processed for a particular source and type.</li>
    <li><b>Max Counters :</b>It displays the maximum number of columns processed for a particular source and type.</li>
    <li><b>Total Counters :</b>It displays the total number of counters processed for a particular source and type.</li>
</ul>
EOT;
drawHeaderWithHelp("Daily Totals - FRH ROP", 2, "frhRopDailyHelp", $frhRopDailyHelp);
$frhTable = new FrhDailyTotal();
echo $frhTable->getHtmlTable();
echo "<br>";
$frhBatchHelp = <<<EOT
The below graphs display the session duration and counters parsed data for FRH BATCH(Pre Aggregation).
<ul>
    <li><b>Duration graph : </b>It displays the maximum session time taken by each source for parsing.</li>
    <li><b>Counters Parsed : </b>It displays the total number of counters parsed for each source.
</ul>
EOT;
drawHeaderWithHelp("Sessions - FRH BATCH", 2, "frhBatchHelp", $frhBatchHelp);
$workflowType = 'FRH_BATCH';
parseGraph('Duration', 'Seconds', 'tsc', array('TIME_TO_SEC(TIMEDIFF(maxend,minstart))' => 'Duration'), $workflowType);
parseGraph('Counters Parsed', 'Counter', 'sb', array('cntr_sum' => 'Counters' ), $workflowType);
$frhBatchDailyHelp = <<<EOT
The table displays below information.
<ul>
    <li><b>Source :</b>It displays the name of source.</li>
    <li><b>Type :</b>It displays the name of source type.</li>
    <li><b>Distinct ROP Count:</b>It displays the total number of ROP files processed for a particular source and type.</li>
    <li><b>Average Rows :</b>It displays the average number of rows processed for a particular source and type.</li>
    <li><b>Max Rows :</b>It displays the maximum number of rows processed for a particular source and type.</li>
    <li><b>Total Rows :</b>It displays the total rows processed for a particular source and type.</li>
    <li><b>Average Counters :</b>It displays the average number of counters processed for a particular source and type.</li>
    <li><b>Max Counters :</b>It displays the maximum number of columns processed for a particular source and type.</li>
    <li><b>Total Counters :</b>It displays the total number of counters processed for a particular source and type.</li>
</ul>
EOT;
drawHeaderWithHelp("Daily Totals - FRH BATCH", 2, "frhBatchDailyHelp", $frhBatchDailyHelp);
$frhTable->getData();
echo $frhTable->getClientSortableTableStr();
include "../common/finalise.php";
?>
