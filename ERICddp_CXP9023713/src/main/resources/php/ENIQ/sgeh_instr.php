<?php
$pageTitle = "SGEH Instrumentation";
$YUI_DATATABLE = true;
include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$EVENT_NAMES = array(
                    '5'  => 'L_ATTACH',
                    '6'  => 'L_DETACH',
                    '7'  => 'L_HANDOVER',
                    '8'  => 'L_TAU',
                    '9'  => 'L_DEDICATED_BEARER_ACTIVATE',
                    '10' => 'L_DEDICATED_BEARER_DEACTIVATE',
                    '11' => 'L_PDN_CONNECT',
                    '12' => 'L_PDN_DISCONNECT',
                    '13' => 'L_SERVICE_REQUEST',
                    '16' => 'L_DEDICATED_BEARER_MODIFY'
                );

function getEventName($eventId){
    global $EVENT_NAMES;
    if (array_key_exists($eventId, $EVENT_NAMES)){
        return $EVENT_NAMES[$eventId];
    }
    else{
        return $eventId;
    }
}

class ProcessingNfsWf extends DDPObject {
  var $cols = array(
            'wfName'          => 'Workflow',
            'Files'           => 'Files',
            'Events'          => 'Events',
            'gb'              => 'Volume (GB)',
            'Succ23'          => '2G/3G Success Events',
            'Err23'           => '2G/3G Error Events',
            'Succ4'           => '4G Success Events',
            'Err4'            => '4G Error Events',
            'CorruptedEvents' => 'CorruptedEvents',
            'Delay'           => 'Average Processing Delay'
            );

    var $title = "ProcessingNfs Workflow";

    function __construct() {
        parent::__construct("sgehProcessingNFS");
    }

    function getData() {
        global $date;
    global $site;
    $sql = "
        SELECT
            IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
            FORMAT(SUM(eniq_sgeh_processing_nfs.Files),0) AS Files,
            FORMAT(SUM(eniq_sgeh_processing_nfs.Events),0) AS Events,
            ROUND(SUM(eniq_sgeh_processing_nfs.Bytes)/(1024*1024*1024),2) AS gb,
            FORMAT(SUM(eniq_sgeh_processing_nfs.Succ23),0) AS Succ23,
            FORMAT(SUM(eniq_sgeh_processing_nfs.Err23),0) AS Err23,
            FORMAT(SUM(eniq_sgeh_processing_nfs.Succ4),0) AS Succ4,
            FORMAT(SUM(eniq_sgeh_processing_nfs.Err4),0) AS Err4,
            FORMAT(SUM(eniq_sgeh_processing_nfs.CorruptedEvents),0) AS CorruptedEvents,
            SEC_TO_TIME(SUM(eniq_sgeh_processing_nfs.Delay)/SUM(eniq_sgeh_processing_nfs.Files)) AS Delay
        FROM
            eniq_sgeh_processing_nfs, eniq_workflow_names, sites
        WHERE
            eniq_sgeh_processing_nfs.siteid = sites.id AND sites.name = '$site' AND
            eniq_sgeh_processing_nfs.wfid = eniq_workflow_names.id AND
            eniq_sgeh_processing_nfs.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        GROUP BY eniq_workflow_names.name WITH ROLLUP
        ";
    $this->populateData($sql);
    return $this->data;
    }
}

if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}

echo "<H1>SGEH Processing NFS</H1>\n";

echo "<H2>Daily Totals</H2>\n";
$dailyTotals = new ProcessingNfsWf();
echo $dailyTotals->getHtmlTableStr();

echo "<H2>Hourly Totals</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table('border=0');

$params =
  array(
    'Files' => array('title' => 'Files Consumed'),
    'Events' => array('title' => 'Events Consumed'),
    'Bytes' => array('title' => 'Volume Consumed (GB)', 'ylabel' => 'GB', 'counterMod' => '/(1024*1024*1024)'),
    'Delay' => array('title' => 'Average Processing Delay', 'ylabel' => 'Secs', 'counterMod' => '/AGG_FUNC(Files)')
    );

foreach ( array('Files','Events','Bytes','Delay') as $counterType ) {
  $thisGraphParams = array(
                        'title'      => $counterType,
                        'ylabel'     => $counterType,
                        'counterMod' => "",
                        'agg'        => 'SUM'
                        );
  foreach ($thisGraphParams as $key => $value) {
    if ( array_key_exists($key, $params[$counterType]) ) {
        $thisGraphParams[$key] = $params[$counterType][$key];
    }
  }

if ( $counterType == 'Delay' ) {
   $dbColExpr = 'AGG_FUNC(Delay)/AGG_FUNC(Files)';
} else {
  $dbColExpr = $counterType . $thisGraphParams['counterMod'];
}

$sqlParam =
    array(
        'title'       => $thisGraphParams['title'],
        'ylabel'      => $thisGraphParams['ylabel'],
        'type'        => 'sb',
        'sb.barwidth' => '3600',
        'presetagg'   => $thisGraphParams['agg'] . ":Hourly",
        'persistent'  => 'false',
        'useragg'     => 'false',
        'querylist'   =>
     array(
       array (
            'timecol' => 'time',
            'whatcol' => array(  $dbColExpr => $counterType ),
            'tables'  => "eniq_sgeh_processing_nfs, sites",
            'where'   => "eniq_sgeh_processing_nfs.siteid = sites.id AND sites.name = '%s'",
            'qargs'   => array( 'site' )
            )
         )
       );

$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}

$sqlParam =
    array(
         'title'       => '2/3/4G Events',
         'ylabel'      => 'Events',
         'type'        => 'sb',
         'sb.barwidth' => '3600',
         'presetagg'   => "SUM:Hourly",
         'persistent'  => 'false',
         'useragg'     => 'false',
         'querylist'   =>
                         array(
                              array(
                                 'timecol' => 'time',
                                   'whatcol' => array(
                                            'Succ23' => '2/3G Success',
                                            'Err23'  => '2/3G Error',
                                            'Succ4'  => '4G Success',
                                            'Err4'   => '4G Error'),
                                   'tables'  => "eniq_sgeh_processing_nfs, sites",
                                   'where'   => "eniq_sgeh_processing_nfs.siteid = sites.id AND sites.name = '%s'",
                                   'qargs'   => array( 'site' )
                                   )
                               )
         );
$id = $sqlParamWriter->saveParams($sqlParam);
$graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
echo $graphs->toHTML();

class SuccessHandling extends DDPObject{
    var $cols = array(
                    'eventId'          => 'Event Name',
                    'totalSystemIngress' => 'Total System Ingress',
                    'rawIngress'         => 'Mediation Success Raw Ingress',
                    'egressDb'           => 'Mediation Success Raw Egress towards DB',
                    'rawSuccessLoaded'   => 'Candidates for filtering if raw success loaded',
                    'filterPercent'      => 'Candidates for filtering in percentage'
                );

    function __construct() {
        parent::__construct("successHandling");
    }

    function getData() {
        global $date;
        global $site;
        global $debug;

        $sql = "
            SELECT
             eventid AS eventId,
             SUM(total_ingress) AS totalSystemIngress,
             SUM(success_ingress) AS rawIngress,
             SUM(succ_db_egress) AS egressDb,
             SUM(succ_cand_for_filter) AS rawSuccessLoaded,
             ROUND(if(SUM(succ_cand_for_filter) != 0,(if(SUM(succ_db_egress) != 0,(SUM(succ_cand_for_filter)/SUM(succ_db_egress))*100,(SUM(succ_cand_for_filter)/SUM(success_ingress))*100)),0),2) AS filterPercent
            FROM
             eniq_sgeh_success_handling, sites
            WHERE
             eniq_sgeh_success_handling.siteid = sites.id AND sites.name = '$site' AND
             eniq_sgeh_success_handling.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY eventid
            ";
        $this->populateData($sql);
        $this->columnTypes['eventId'] = 'string';
        foreach ($this->data as &$row) {
            $row['eventId' ] = getEventName($row['eventId']);
            if ( $debug ) { echo "<pre>"; print_r($row); echo "</pre>\n"; }
        }
        return $this->data;
    }
}

$pageHelp = <<<EOT
Following is additional information about the columns in the given table:
<p>
<ol>
   <li><b>Event Name:</b> Displays the 4G MME EBM event name.</li>
   <li><b>Total System Ingress:</b> Displays the event wise count of Total System Ingress.(including both Success raw event and Error raw event)</li>
   <li><b>Mediation Success Raw Ingress:</b> Displays the event wise count of raw success event Ingress in Mediation.</li>
   <li><b>Mediation Success Raw Egress towards DB:</b> Displays the event wise count of Success raw events available for storage. The value of this column is dependent on the value of mediation parameter Success_Data_handling and value of filter for success raw events in table GROUP_TYPE_E_CPT_CC_SCC<br>
   <br>When Success_Data_handling = ROP_RAW or BULK_RAW and filtering = OFF for an event then the value for the event in this column will be the value of Mediation success ingress.
   <br>When Success_Data_handling = ROP_RAW or BULK_RAW and filtering = ON for an event then the value for the event in this column will be (value of Mediation success ingress - #events that match the filtering criteria in table GROUP_TYPE_E_CPT_CC_SCC).
   <br>When Success_Data_handling = AGGREGATES (filtering in this case is not valid) then the value of the event in this column will be 0.</li>
   <li><b>Candidate events for filtering if raw success loaded:</b>Displays the event wise count of the number of events that can be filtered.<br>
   <br>By default filtering will be "On" for L_SERVICE_REQUEST, L_TAU and L_HANDOVER,so this column will show 0 for these events indicating that no further filtering is possible.
   <br> When filtering is "Off" for one or more of these events then the value in this column will show the number of events that will be filtered when the filtering is turned "on".
   <br>For all other events the value will always be zero.</li>
   <li><b>Candidate events for filtering in percentage:</b>Displays event wise "Candidate events for filtering if raw success loaded" as a percentage of the "mediation success raw ingress". </li><br>
</ol>
For more information on event filtering refer section MME EBM Data Management in <a target = '_blank' href='http://cpistore.internal.ericsson.com/alexserv?AC=LINKEXT&SL=EN/LZN7030204R3E&LI=EN/LZN7030204/1R3E&FN=3_1543-AOM901139Uen.U.html'>ENIQ Events System Administrative Guide</a>.
EOT;

$headerLevel = 1;
$headerText = "MME/EBM Data Management";
$label = "pageHelp";
$headerWithHelp  = "<H" . $headerLevel . ">$headerText\n";
$headerWithHelp .= drawHelpLink($label, "ReturnContentAsString");
$headerWithHelp .= "</H" . $headerLevel . ">\n";
$headerWithHelp .= "<div id=\"" . $label . "\" style=\"width:1320px;\" class=\"helpbox\" helpClicked=false>\n";
$headerWithHelp .= drawHelpTitle($headerText, $label, 'ReturnContentAsString');
$headerWithHelp .= " <div class=helpbody>\n" .
                   "  $pageHelp\n" .
                   " </div>\n" .
                   "</div>\n";

echo $headerWithHelp;
$successHandlingTable = new SuccessHandling();
echo $successHandlingTable->getClientSortableTableStr();

include "../common/finalise.php";
?>