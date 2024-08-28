<?php
$pageTitle = "ASR";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/NICPlot.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

require_once 'HTML/Table.php';

const QUERY_STRING = 'QUERY_STRING';
const SELF_LINK = '/TOR/pm/asrl.php';
const ASRL_SPARK_BATCH = 'Spark Batches (ASR-L)';
const ASRN_SPARK_BATCH = 'Spark Batches (ASR-N)';
const STAGE1_ASRN = 'Stage 1 - Read/Filter/Key Stage (ASR-N)';
const STAGE1_ASRL = 'Stage 1 - Read/Filter/Key Stage (ASR-L)';
const STAGE2_ASRN = 'Stage 2 - State Handling/Write Stage (ASR-N)';
const STAGE2_ASRL = 'Stage 2 - State Handling/Write Stage (ASR-L)';

function showVMs() {
    global $date, $site, $statsDB;

    $graphTable = new HTML_Table("border=0");
    $serverIds = enmGetServiceInstances($statsDB, $site, $date, "sparkworkerdef");

    drawHeader("Worker VMs", 1, "Worker_Vms");
    $sqlParamWriter = new SqlPlotParam();

    $queryList = array();
    foreach ( $serverIds as $hostname => $serverId ) {
        $queryList[] =
                     array('timecol' => 'time',
                           'whatcol' => array( "iowait+sys+user+IFNULL('steal',0)" => "$hostname" ),
                           'tables' => "hires_server_stat",
                           'where' => "hires_server_stat.serverid = $serverId"
                     );
    }
    $sqlParam = array(
        'title' => 'CPU Load',
        'ylabel'=> "%",
        'type'  => "tsc",
        'useragg'    => 'true',
        'persistent' => 'false',
        'querylist' => $queryList
    );
    $id1 = $sqlParamWriter->saveParams($sqlParam);

    $queryList = array();
    foreach ( $serverIds as $hostname => $serverId ) {
        $row = $statsDB->queryRow("SELECT diskid FROM raw_devices WHERE raw_devices.date = '$date' AND raw_devices.serverid = $serverId");
        $queryList[] =
                     array('timecol' => 'time',
                           'whatcol' => array( "blks" => $hostname ),
                           'tables' => "hires_disk_stat",
                           'where' => "hires_disk_stat.serverid = $serverId AND hires_disk_stat.diskid = " . $row[0]
                     );
    }
    $sqlParam = array(
        'title' => 'Disk Blks/s',
        'ylabel'=> "Blks/s",
        'type'  => "sb",
        'useragg'    => 'true',
        'persistent' => 'false',
        'querylist' => $queryList
    );
    $id2 = $sqlParamWriter->saveParams($sqlParam);

    $graphTable->addRow(
        array(
            array($sqlParamWriter->getImgURL($id1, "$date 00:00:00", "$date 23:59:59", true, 640, 320)),
            array($sqlParamWriter->getImgURL($id2, "$date 00:00:00", "$date 23:59:59", true, 640, 320))
        )
    );

    $nicIds = array();
    $servIdStr = implode(",", array_values($serverIds));
    $statsDB->query("SELECT id FROM network_interfaces WHERE name = 'eth0'
AND serverid IN (" . $servIdStr . ')');
    while ( $row = $statsDB->getNextRow() ) {
        $nicIds[] = $row[0];
    }
    $nicIdStr = implode(",", $nicIds);
    $nicPlot = new NICPlot($statsDB, $date, $nicIdStr, NICPlot::SERV);
    $graphTable->addRow(
        array(
            array($nicPlot->getGraph(NICPlot::RX, 'RX eth0', 640, 320)),
            array($nicPlot->getGraph(NICPlot::TX, 'TX eth0', 640, 320))
        )
    );

    echo $graphTable->toHTML();

}

function workerVMInstrumentation() {

    $workerVMGraphs = array(
         'asr_workerVMCpuLoad',
         'asr_workerVMBlks',
    );

    return array(
         array( 'Worker VMs', $workerVMGraphs, 'Worker_Vms' ),
    );
}

function asrInstrumentation() {

    $asrInstrumentationGraphs = array(
         'asr_inputEventCount',
         'asr_filteredEvent',
         'asr_completeRecords',
         'asr_suspectRecords',
         'asr_noOfBearers'
    );

    return array(
         array( 'ASR Instrumentation', $asrInstrumentationGraphs, 'Asrl_Instrumentation' ),
    );
}

function sparkBatches() {

    $sparkBatchesGraphs = array(
         'spark_batch_duration',
         'spark_batch_InputEvents',
         'spark_batch_filteredEvents',
         'spark_batch_procCompleteRecords',
         'spark_batch_suspectRecords'
    );

    return array(
         array( ASRL_SPARK_BATCH, $sparkBatchesGraphs, 'Spark_Batches' ),
    );
}

function sparkBatchesASR() {

    $sparkBatchesGraphsASR = array(
         'asrn_spark_batch_duration'
    );

    return array(
         array( 'Spark Batch Duration', $sparkBatchesGraphsASR, 'Spark_Duration' ),
    );
}

function sparkStage1() {

    $sparkStage1Graphs = array(
         'spark_stage1Duration',
         'spark_stage1KafkaReadTime',
         'spark_stage1InputEventCount',
         'spark_stage1FilteredEventCount',
    );

    return array(
         array( 'Stage 1 - Read/Filter/Key Stage', $sparkStage1Graphs, 'Read_Filter_Key_Stage' ),
    );
}

function sparkStage2() {

    $sparkStage2Graphs = array(
         'spark_stage2Duration',
         'spark_stage2CompleteRecords',
         'spark_stage2EndTriggeredSuspectSessions',
         'spark_stage2InactiveSuspectSessions',
         'spark_stage2OutputWriteTime',
         'spark_stage2MapWithStateTime',
    );

    return array(
         array( 'Stage 2 - State Handling/Write Stage', $sparkStage2Graphs, 'State_Handling_Write_Stage' ),
    );
}

function makeGraphs($params) {

    foreach ( $params as $param ) {
        $graphs = array();
        $secTitle = $param[0];
        $help = $param[2];
        drawHeader($secTitle, 1, $help);

        $graphParams = $param[1];

        foreach ( $graphParams as $graphParam ) {
            $modelledGraph = new ModelledGraph( 'TOR/pm/asr/' . $graphParam);
            $graphs[] = $modelledGraph->getImage();
        }
        plotgraphs( $graphs );
        echo addLineBreak(2);
    }
}

function drawGraphGroup($group, $graphParam) {
    $graphs = array();
    getGraphsFromSet( $group, $graphs, 'TOR/pm/enm_asr_job', $graphParam );
    plotgraphs( $graphs );
}

function mainFlow() {
    global $site, $date, $statsDB;

    $hasBatchData = $statsDB->hasData( "enm_str_asrl_spark" );
    $hasASRBatchData = $statsDB->hasData( "enm_asr_batch" );
    $hasASRNJobData = $statsDB->hasData( "enm_asr_job", 'time', false, "enm_asr_job.jobType = 'ASRN'" );
    $hasASRLJobData = $statsDB->hasData( "enm_asr_job", 'time', false, "enm_asr_job.jobType = 'ASRL'" );

    $links[] = makeLink(SELF_LINK, "VM Stats", array('show'=> 'vms'));
    $links[] = makeLink('/common/kafka.php', "Kafka", array('topics' => 'asrl,asrn'));
    $links[] = makeLink('/TOR/pm/streaming_fwd.php', "Fowarder", array('sg' => 'asrlforwarderdef'));
    $links[] = makeAnchorLink('Asrl_Instrumentation', 'ASR Instrumentation');
    if ( $hasBatchData ) {
        $links[] = makeAnchorLink('Spark_Batches', ASRL_SPARK_BATCH);
        $links[] = makeAnchorLink('Read_Filter_Key_Stage', STAGE1_ASRL);
        $links[] = makeAnchorLink('State_Handling_Write_Stage', STAGE2_ASRL);
    }

    if ( $hasASRBatchData ) {
        $links[] = makeAnchorLink('Spark_Duration', 'Spark Duration');
    }

    if ( $hasASRLJobData ) {
        $links[] = makeAnchorLink('Spark_Batches_ASRL', ASRL_SPARK_BATCH);
        $links[] = makeAnchorLink('Read_Filter_Key_Stage_ASRL', STAGE1_ASRL);
        $links[] = makeAnchorLink('State_Handling_Write_Stage_ASRL', STAGE2_ASRL);
    }

    if ( $hasASRNJobData ) {
        $links[] = makeAnchorLink('Spark_Batches_ASRN', ASRN_SPARK_BATCH);
        $links[] = makeAnchorLink('Read_Filter_Key_Stage_ASRN', STAGE1_ASRN);
        $links[] = makeAnchorLink('State_Handling_Write_Stage_ASRN', STAGE2_ASRN);
    }

    echo makeHTMLList( $links );

    $asrInstrumentationParams = asrInstrumentation();
    makeGraphs($asrInstrumentationParams);

    if ( $hasBatchData ) {
        $sparkBatchesParams = sparkBatches();
        makeGraphs($sparkBatchesParams);

        $sparkStage1Params = sparkStage1();
        makeGraphs($sparkStage1Params);

        $sparkStage2Params = sparkStage2();
        makeGraphs($sparkStage2Params);
    }

    if ( $hasASRBatchData ) {
        $sparkBatchesASRParams = sparkBatchesASR();
        makeGraphs($sparkBatchesASRParams);
    }

    if ( $hasASRLJobData ) {
        $graphParam = array( 'jobType' => 'ASRL' );

        drawHeader(ASRL_SPARK_BATCH, 2, 'Spark_Batches_ASRL');
        drawGraphGroup('batch', $graphParam);

        drawHeader(STAGE1_ASRL, 2, 'Read_Filter_Key_Stage_ASRL');
        drawGraphGroup('read', $graphParam);

        drawHeader(STAGE2_ASRL, 2, 'State_Handling_Write_Stage_ASRL');
        drawGraphGroup('write', $graphParam);
    }

    if ( $hasASRNJobData ) {
        $graphParam = array( 'jobType' => 'ASRN' );

        drawHeader(ASRN_SPARK_BATCH, 2, 'Spark_Batches_ASRN');
        drawGraphGroup('batch', $graphParam);

        drawHeader(STAGE1_ASRN, 2, 'Read_Filter_Key_Stage_ASRN');
        drawGraphGroup('read', $graphParam);

        drawHeader(STAGE2_ASRN, 2, 'State_Handling_Write_Stage_ASRN');
        drawGraphGroup('write', $graphParam);
    }

}

if ( requestValue('show') === 'vms' ) {
    showVMs();
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
