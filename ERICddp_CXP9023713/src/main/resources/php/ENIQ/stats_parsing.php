<?php
$pageTitle = "Parsing";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

$parsingHelp = <<<EOT
The below graphs and the Daily Total table display data for Non-FRH workflow type.
EOT;
drawHeaderWithHelp("Parsing", 1, "parsingHelp", $parsingHelp);

$sessionHelp = <<<EOT
The below graphs display the session duration and counters parsed data for Non-FRH workflow type.
<ul>
    <li><b>Duration graph :</b>It displays the maximum session time taken by each source for parsing.</li>
    <li><b>Counters Parsed :</b>It displays the total number of counters parsed for each source.</li>
    <li><b>Parsing Time:</b> It displays the difference of the maximum session end time of source type and the ROP start time of ROP interval.</li>
</ul>
EOT;
drawHeaderWithHelp("Sessions", 2, "sessionHelp", $sessionHelp);
$statsDB = new StatsDB();
$graphs = new HTML_Table("border=0");
$qPlots = array();
$qPlots["duration"] = array( 'title'  => 'Duration',
                 'ylabel' => 'Seconds',
                 'type'   => 'tsc',
                 'whatcol'   => array( 'TIME_TO_SEC(TIMEDIFF(maxend,minstart))' => 'Duration')
                 );
$qPlots["counters"] = array( 'title'  => 'Counters Parsed',
                 'ylabel' => 'Counter',
                 'type'   => 'sb',
                 'sb.barwidth' => 900,
                 'whatcol'   => array('cntr_sum' => 'Counters' )
            );
$qPlots["parsingTime"] = array( 'title'  => 'ParsingTime',
                 'ylabel' => 'Seconds',
                 'type'   => 'tsc',
                 'whatcol'   => array( 'TIME_TO_SEC(TIMEDIFF(maxend,timeslot))' => 'parsingTime')
                 );
$workflow_type = "'FRH_ROP', 'FRH_BATCH'";
foreach ( $qPlots as $key => $param ) {
  $sqlParam =
    array( 'title'      => $param['title'],
       'ylabel'     => $param['ylabel'],
       'type'       => $param['type'],
       'useragg'    => 'true',
       'persistent' => 'true',
       'querylist' =>
       array(
         array (
            'timecol' => 'timeslot',
            'multiseries' => 'eniq_stats_source.name',
            'whatcol' => $param['whatcol'],
            'tables'  => "eniq_stats_adaptor_sessions, eniq_stats_source, sites, eniq_stats_workflow_types",
            'where'   => "eniq_stats_adaptor_sessions.sourceid = eniq_stats_source.id AND
                          (eniq_stats_workflow_types.workflow_type_id = eniq_stats_adaptor_sessions.workflow_type OR eniq_stats_adaptor_sessions.workflow_type = 0) AND
                          eniq_stats_workflow_types.workflow_type NOT IN ( %s ) AND
                          eniq_stats_adaptor_sessions.siteid = sites.id AND sites.name = '%s'",
            'qargs'   => array('workflow_type', 'site')
           )
         )
       );
  if ( $param['type'] == 'sb' ) {
    $sqlParam['sb.barwidth'] = $param['sb.barwidth'];
  }
  $extraArgs = "workflow_type=$workflow_type";
  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240, $extraArgs) ) );
}
echo $graphs->toHTML();

echo addLineBreak();
$table = new ModelledTable('ENIQ/stats_loader_execution_count_table', 'loaderExecutionCountHelp');
echo $table->getTableWithHeader("Loader's Average Execution Count");

echo addLineBreak();
$table = new ModelledTable('ENIQ/stats_parsing_daily_totals', 'parsingDailyHelp');
echo $table->getTableWithHeader("Daily Totals");

include "../common/finalise.php";
?>
