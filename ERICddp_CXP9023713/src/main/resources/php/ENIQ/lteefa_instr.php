<?php
$pageTitle = "LTE CFA HFA Instrumentation";
$YUI_DATATABLE = true;
include "../common/init.php";

require_once 'HTML/Table.php';

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
$statsDB = new StatsDB();
$eventNameId = array(
                    '4125'  => 'INTERNAL_PROC_UE_CTXT_RELEASE',
                    '4114'  => 'INTERNAL_PROC_ERAB_RELEASE',
                    '4106'  => 'INTERNAL_PROC_INITIAL_CTXT_SETUP',
                    '4099'  => 'INTERNAL_PROC_ERAB_SETUP'
               );

class ProcessorWf extends DDPObject {
    var $cols = array(
                    'wfName'       => 'Workflow',
                    'Files'        => 'Files',
                    'Events'       => 'Events',
                    'gb'           => 'Volume (GB)',
                    'Delay'        => 'Average Processing Delay',
                    'CFA'          => 'CFA Events',
                    'HFA'          => 'HFA Events'
                );

    var $title = "Counter Workflow";

    function __construct() {
        parent::__construct("lteefaCounter");
    }

    function getData() {
        global $date;
        global $site;
        $sql = "
            SELECT
             IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
             FORMAT(SUM(eniq_lteefa_processor.Files),0) AS Files,
             FORMAT(SUM(eniq_lteefa_processor.Events),0) AS Events,
             ROUND(SUM(eniq_lteefa_processor.Bytes)/(1024*1024*1024),2) AS gb,
             FORMAT(SUM(eniq_lteefa_processor.CFA),0) AS CFA,
             FORMAT(SUM(eniq_lteefa_processor.HFA),0) AS HFA,
             SEC_TO_TIME(AVG(eniq_lteefa_processor.Delay/eniq_lteefa_processor.Files)) AS Delay
            FROM
             eniq_lteefa_processor, eniq_workflow_names, sites
            WHERE
             eniq_lteefa_processor.siteid = sites.id AND sites.name = '$site' AND
             eniq_lteefa_processor.wfid = eniq_workflow_names.id AND
             eniq_lteefa_processor.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY eniq_workflow_names.name WITH ROLLUP
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

if ( isset($_GET['start']) ) {
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
}
else {
    $fromDate = $date;
    $toDate = $date;
}

echo "<H1>LTE-CFA HFA Processor</H1>\n";
$dailyTotalsHelp = <<<EOT
The table shows the information about ENIQ Events LTE CFA HFA workflow processing statistics:
<p>
<ol>
    <li><b>Workflow: </b>This shows the name of workflow.</li>
    <li><b>Files: </b>This shows the number of files processed by the workflow.</li>
    <li><b>Events: </b>This shows the number of events processed by the workflow.</li>
    <li><b>Volume(GB): </b>This shows the space occupied by the files processed by the workflow.</li>
    <li><b>Average Processing Delay: </b>Average processing time indicates delay incurred/observed for stream terminated files for CFA and HFA features.</li>
    <li><b>CFA Events: </b>This shows the number of CFA events processed by the workflow.</li>
    <li><b>HFA Events: </b>This shows the number of HFA events processed by the workflow.</li>
</ol>
EOT;
drawHeaderWithHelp("Daily Totals", 2, "dailyTotalsHelp", $dailyTotalsHelp);
$totalTable = new ProcessorWf();
echo $totalTable->getHtmlTableStr();

echo "<H2>Hourly Totals</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table('border=0');

$params =
    array(
        'Files'  => array('title' => 'Files Consumed'),
        'Events' => array('title' => 'Events Consumed'),
        'Bytes'  => array('title' => 'Volume Consumed (GB)', 'ylabel' => 'GB', 'counterMod' => '/(1024*1024*1024)'),
        'Delay'  => array('title' => 'Average Processing Delay', 'ylabel' => 'Secs', 'counterMod' => '/Files', 'agg' => 'AVG')
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

    $sqlParam =
        array( 'title'    => $thisGraphParams['title'],
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
                                     'whatcol' => array( $counterType . $thisGraphParams['counterMod'] => $counterType ),
                                     'tables'  => "eniq_lteefa_processor, sites",
                                     'where'   => "eniq_lteefa_processor.siteid = sites.id AND sites.name = '%s'",
                                     'qargs'   => array( 'site' )
                                  )
                              )
        );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}

function getEventGraphs($title, $whatCol, $requiredTable) {
    global $graphs, $sqlParamWriter, $date;
    $sqlParam = array(
        'title'       => $title,
        'ylabel'      => 'Events',
        'type'        => 'sb',
        'sb.barwidth' => '3600',
        'presetagg'   => "SUM:Hourly",
        'persistent'  => 'true',
        'useragg'     => 'false',
        'querylist'   => array(
            array (
                'timecol' => 'time',
                'whatcol' => $whatCol,
                'tables'  => "$requiredTable, sites",
                'where'   => "$requiredTable.siteid = sites.id AND sites.name = '%s'",
                'qargs'   => array( 'site' )
            )
        )
    );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}
$title = "CFA/HFA Events";
$whatCol = array('CFA' => 'CFA', 'HFA' => 'HFA');
$requiredTable = "eniq_lteefa_processor";
getEventGraphs($title, $whatCol, $requiredTable);
$title = "RF Workflow Events";
$whatCol = array('RfEvents' => 'RF Events');
$requiredTable = "eniq_lteefa_rfevents_load_balance";
$rfWorkflowPerformance = $statsDB->queryRow("
    SELECT
     COUNT(*)
    FROM
     $requiredTable,sites
    WHERE
     $requiredTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
     $requiredTable.siteid = sites.id AND sites.name = '$site'
    ");
if ($rfWorkflowPerformance[0] > 0) {
    getEventGraphs($title, $whatCol, $requiredTable);
}
echo $graphs->toHTML();

class RfEnrichment extends DDPObject {
    var $cols = array(
        'eventId'        => 'Event name',
        'enrichedEvents' => 'Number of Enriched Events',
        'enrichPercent'  => 'Enrichment %',
        'failuredEvents' => 'Total Event Enrichment Failures',
        'windowCriteria' => 'Event Enrichment Failure - Timing Criteria'
    );

    function __construct() {
        parent::__construct("rfEnrichmentInstance");
    }

    function getData() {
        global $date;
        global $site;

        $sql = "
            SELECT
             IFNULL(eventid,'Totals') AS eventId,
             SUM(success) as enrichedEvents,
             ROUND((SUM(success)/(SUM(success)+SUM(failure)))*100, 2) AS enrichPercent,
             SUM(failure) as failuredEvents,
             SUM(outofwindow) as windowCriteria
            FROM
             eniq_lteefa_rf_enrichment,sites
            WHERE
             sites.name = '$site' AND
             eniq_lteefa_rf_enrichment.time between '$date 00:00:00' AND '$date 23:59:59' AND
             eniq_lteefa_rf_enrichment.siteid = sites.id
            GROUP BY
             eniq_lteefa_rf_enrichment.eventid WITH ROLLUP
        ";
        $this->populateData($sql);
        $this->columnTypes['eventId'] = 'string';
        foreach ($this->data as &$row)
        {
            $row['eventId' ] = getEventName($row['eventId']);
            if ( $debug ) { echo "<pre>"; print_r($row); echo "</pre>\n"; }
        }
        return $this->data;
    }
}

function getEventName($eventId){
    global $eventNameId;
    if (array_key_exists($eventId, $eventNameId)){
        return $eventNameId[$eventId];
    }
    else{
        return $eventId;
    }
}
$rfEnrichmentHelp = <<<EOT
The table gives below information about RF Enrichment:
<p>
<ol>
    <li><b>Event name: </b>This shows the name of the event.</li>
    <li><b>Number of Enriched Events: </b>This shows the total number of enriched events.</li>
    <li><b>Enrichment percentage: </b>This shows the enrichment percentage of the events.(Sum(Enriched Success)/Sum(Enriched Success+Enriched Failure))</li>
    <li><b>Total Event Enrichment Failures: </b>This shows the number of events which were not enriched.</li>
    <li><b>Event Enrichment Failure-Timing Criteria: </b>This shows the number of events which are not enriched due to lack of enrichment information (30 sec)prior to call failure.</li>
</ol>
EOT;
drawHeaderWithHelp("RF Enrichment", 1, "rfEnrichmentHelp", $rfEnrichmentHelp);

$rfEnrichment = new RfEnrichment();
echo $rfEnrichment->getHtmlTableStr();

class RfWorkflowPerformance extends DDPObject {
    var $cols = array(
        'wfName' => 'Workflow',
        'Files'  => 'Files',
        'Events' => 'Events',
        'gb'     => 'Volume(GB)'
    );

    function __construct() {
        parent::__construct("rfWorkflowPerformanceInstance");
    }

    function getData() {
        global $date;
        global $site;

        $sql = "
            SELECT
             IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
             FORMAT(SUM(eniq_lteefa_rfevents_load_balance.RfFiles),0) AS Files,
             FORMAT(SUM(eniq_lteefa_rfevents_load_balance.RfEvents),0) AS Events,
             ROUND(SUM(eniq_lteefa_rfevents_load_balance.Bytes)/(1024*1024*1024),2) AS gb
            FROM
             eniq_lteefa_rfevents_load_balance, eniq_workflow_names, sites
            WHERE
             eniq_lteefa_rfevents_load_balance.siteid = sites.id AND sites.name = '$site' AND
             eniq_lteefa_rfevents_load_balance.wfid = eniq_workflow_names.id AND
             eniq_lteefa_rfevents_load_balance.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY
             eniq_workflow_names.name WITH ROLLUP
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

$rfWorkflowPerformanceHelp = <<<EOT
The table gives below information about RF Workflow Performance:
<p>
<ol>
    <li><b>Workflow: </b>This shows the name of the workflow.</li>
    <li><b>Number of Files: </b>This shows the number of files processed by the workflow.</li>
    <li><b>Number of Events: </b>This shows the number of events processed by the workflow.</li>
    <li><b>Volume in GB: </b>This shows the space occupied by files processed by the workflow.</li>
</ol>
EOT;
drawHeaderWithHelp("Daily RF Workflow Statistics", 1, "rfWorkflowPerformanceHelp", $rfWorkflowPerformanceHelp);

$rfWorkflowPerformance = new RfWorkflowPerformance();
echo $rfWorkflowPerformance->getHtmlTableStr();

include "../common/finalise.php";
?>