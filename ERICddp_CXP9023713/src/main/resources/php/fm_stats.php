<?php
$pageTitle = "FM Instrumentation";
if ( isset($_GET["qplot"]) ) {
    $UI = false;
 }
include "common/init.php";

require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

$statsDB = new StatsDB();

function qPlot($statsDB,$site,$date,$whatCol,$table)
{
    echo "$whatCol : $table";
  $sqlParam =
    array( 'title'      => $table . ": " . $whatCol,
	   'ylabel'     => $whatCol,
	   'useragg'    => 'true',
	   'persistent' => 'true',
	   'querylist' => 
	   array(
		 array(
		       'timecol' => 'time',
		       'whatcol'    =>  array( $whatCol=> $whatCol ),
		       'tables'  => "$table, sites",
		       'where'   => "$table.siteid = sites.id AND sites.name = '%s'",
               'qargs'   => array ( 'site' )
		       )
		 )
	   );

  $sqlParamWriter = new SqlPlotParam();
  $id = $sqlParamWriter->saveParams($sqlParam);
  header("Location:" .  
	 $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") );
}


function getCreateDataTable($statsDB,$qplotBase,$site,$date,$dbTable,$dbColumn)
{
    $table = new HTML_Table("border=1");
    $table->addRow( array ("Name", "Min", "Max", "Avg"), null, 'th');

    foreach($dbColumn as $col) {
       
        $statsDB->query("
    SELECT '$col', MIN(t.$col), MAX(t.$col), 
        ROUND(AVG(t.$col))
    FROM fm_process_names p, $dbTable t, fm_metrics m, sites s 
    WHERE 
        p.id = t.nameid AND t.siteid = s.id AND s.name = '$site' AND 
        t.nameid = m.id AND 
        t.time BETWEEN '$date 00:00:01' AND '$date 23:59:59'
    GROUP BY t.nameid");

        $row = $statsDB->getNextRow();
        $link = "<a href=\"" . $qplotBase . $row[0] . "&mplot=$dbTable" . "\">$row[0]</a>";
        $table->addRow( array( $link, $row[1], $row[2], $row[3] ));
        
    }

    return $table;
}

if ( isset($_GET["qplot"]) && isset($_GET["mplot"]) ) {
  qPlot($statsDB,$site,$date,$_GET["qplot"],$_GET["mplot"]);
  exit;
 }

$qplotBase = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . "&qplot=";

?>

<h2> FM Supi Stats</h2>
<?php
$table = getCreateDataTable($statsDB,$qplotBase,$site,$date,"fm_supi_stats",array("total_events_from_manager", "total_actions_from_kernel", "total_commands_to_kernel","actions_from_kernel_queue_size","command_to_kernel_queue_size","event_from_manager_queue_size","start_time_secs"));
echo $table->toHTML();
?>

<br />
<h2> IMH FMAI Server Stats</h2>
<?php
$table1 = getCreateDataTable($statsDB,$qplotBase,$site,$date,"imh_fmai_server_stats",array("incoming_fm_xedd_events","incoming_alarms","incoming_context_d_correlated_mess","outgoing_alarm_total","outgoing_alarm_fmx","start_time_secs"));
echo $table1->toHTML();
?>

<br />
<h2> IMH Alarm Server Stats</h2>
<?php
$table2 = getCreateDataTable($statsDB,$qplotBase,$site,$date,"imh_alarm_server_stats",array("incoming_total_alarms","incoming_fmx_alarms","incoming_ack_events","incoming_unack_events","incoming_comment_events","outgoing_alarms","start_time_secs"));
echo $table2->toHTML();
?>
<br />
<h2> FMA Handler 1 Stats </h2>
<?php
$table3 = getCreateDataTable($statsDB,$qplotBase,$site,$date,"fma_handler_1_stats",array("received_alarms","received_admin_events","received_network_model_notifications","sent_fmii_comments","sent_fmii_ack","sent_fmii_unack","sent_fmii_alarm","sent_action_messages","sent_context_dcorrelated_message","sent_fmxi_messages","sent_fmai_ack","sent_fmai_unack","message_processed_by_list_thread_0","message_processed_by_list_thread_1","message_processed_by_list_thread_2","message_processed_by_list_thread_3","message_processed_by_list_thread_4","message_processed_by_list_thread_5","message_processed_by_list_thread_6","message_processed_by_list_thread_7","alarm_message_processed_by_log_thread_0","alarm_message_processed_by_log_thread_1","alarm_message_processed_by_log_thread_2","alarm_message_processed_by_log_thread_3","alarm_message_processed_by_log_thread_4","alarm_message_processed_by_log_thread_5","alarm_message_processed_by_log_thread_6","alarm_message_processed_by_log_thread_7","correlation_message_processed_by_log_thread_0","correlation_message_processed_by_log_thread_1","correlation_message_processed_by_log_thread_2","correlation_message_processed_by_log_thread_3","correlation_message_processed_by_log_thread_4","correlation_message_processed_by_log_thread_5","correlation_message_processed_by_log_thread_6","correlation_message_processed_by_log_thread_7","list_thread_qsize_0","list_thread_qsize_1","list_thread_qsize_2","list_thread_qsize_3","list_thread_qsize_4","list_thread_qsize_5","list_thread_qsize_6","list_thread_qsize_7","log_thread_qsize_0","log_thread_qsize_1","log_thread_qsize_2","log_thread_qsize_3","log_thread_qsize_4","log_thread_qsize_5","log_thread_qsize_6","log_thread_qsize_7","start_time_secs"));
echo $table3->toHTML();

$statsDB->disconnect();

include "common/finalise.php";
?>
