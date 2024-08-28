<?php
$pageTitle = "CM Edit Instrumentation";

include_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once 'HTML/Table.php';

const CMSERV_CLISTATISTICS_INSTR = "cmserv_clistatistics_instr";
const YLABEL = 'ylabel';
const INST = 'Instance';
const CNT = 'count';
const TITLE = 'title';
const REQUEST_SCRIPT = 'Requests From Scripting';
const REQUEST_CLI = 'Requests From CLI';
const TABLES = ' sites, servers';
const INST_COL = 'IFNULL(servers.hostname,"Totals")';
const SERVERS_HOSTNAME = 'servers.hostname';
const CMSERV_CMREADER_INSTR = 'enm_cmserv_cmreader_instr';
const CMSERV_CMWRITER_INSTR = 'enm_cmserv_cmwriter_instr';
const CMSERV_CMSEARCHREADER_INSTR = 'enm_cmserv_cmsearchreader_instr';
const GETCMDAVGEXETIME = 'GetCommandsAverageExecutionTime';
const CMSRAVGEXETIME = 'CMSearchReaderAverageExecutionTime';
const GET_CMD_AVG_EXE_TIME = 'Get Commands Average Execution Time';
const CMSR_AVG_EXE_TIME = 'CM Search Reader Average Execution Time';
const SENDBACKTOCMEDITORREQUESTQUEUEVISITS = 'sendBackToCmEditorRequestQueueVisits';

function getCmReaderVisits() {
    global $site, $date;
    $cols = array(
        array( 'key' => 'inst',
               'db' => INST_COL,
               DDPTABLE::LABEL => INST
        ),
        array( 'key' => 'allDeployedNetypesVisits',
               'db' => 'SUM(allDeployedNetypesVisits)',
               DDPTABLE::LABEL => 'All Deployed Nettypes Visits'
        ),
        array( 'key' => 'descriptionsForNetypeVisits',
               'db' => 'SUM(descriptionsForNetypeVisits)',
               DDPTABLE::LABEL => 'Desciptions For Nettype Visits'
        ),
        array( 'key' => 'descriptionsWithListOfOutputSpecificationsVisits',
               'db' => 'SUM(descriptionsWithListOfOutputSpecificationsVisits)',
               DDPTABLE::LABEL => 'Descriptions With ListOf Output Specifications Visits'
        ),
        array( 'key' => 'moByFdnVisits',
               'db' => 'SUM(moByFdnVisits)',
               DDPTABLE::LABEL => 'MO By Fdn Visits'
        ),
        array( 'key' => 'posByPoIdsVisits',
               'db' => 'SUM(posByPoIdsVisits)',
               DDPTABLE::LABEL => 'POs By PoIds Visits'
        ),
        array( 'key' => 'searchWithListOfOutputSpecificationsVisits',
               'db' => 'SUM(searchWithListOfOutputSpecificationsVisits)',
               DDPTABLE::LABEL => 'Search With List Of Output Specifications'
        )
    );

    $where = "enm_cmserv_cmreader_instr.siteid = sites.id AND sites.name = '$site' AND
             enm_cmserv_cmreader_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             enm_cmserv_cmreader_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";

    $table = new SqlTable(
        "CM_Reader_Visits",
        $cols,
        array( CMSERV_CMREADER_INSTR, TABLES ),
        $where,
        true
    );
    echo $table->getTableWithHeader("CM Reader Visits", 1, "", "", "CM_Reader_Visits");
}

function cmSearchReaderAverageExecutionTimeParams() {
    return array(
                'IF(cmContainmentQueryVisits>0,cmContainmentQueryTotalExecutionTime/cmContainmentQueryVisits,0)' =>
                'CM Containment Query Average Execution Time',
                'IF(cmFdnQueryVisits>0,cmFDNQueryTotalExecutionTime/cmFdnQueryVisits,0)' =>
                'CM FDN Query Average Execution Time',
                'IF(cmParentChildQueryVisits>0, cmParentChildQueryTotalExecutionTime/cmParentChildQueryVisits, 0)' =>
                'CM Parent Child Query Average Execution Time',
                'IF(cmPoQueryVisits>0, cmPoQueryTotalExecutionTime/cmPoQueryVisits, 0)' =>
                'CM PoQuery Average Execution Time',
                'IF(cmTypeQueryVisits>0, cmTypeQueryTotalExecutionTime/cmTypeQueryVisits, 0)' =>
                'CM Type Query Average Execution Time',
                'IF(compositeCmQueryVisits>0, compositeCmQueryTotalExecutionTime/compositeCmQueryVisits, 0)' =>
                'Composite CM Query Average Execution Time',
                'IF(fastCmTypeQueryVisits>0, fastCmTypeQueryTotalExecutionTime/fastCmTypeQueryVisits, 0)' =>
                'FastCM Type Query Average Execution Time',
    );
}

function getCmWriterTotals() {
    global $date, $site;
    $cols = array(
        array( 'key' => 'inst',
               'db' => INST_COL,
               DDPTABLE::LABEL => INST
        ),
        array( 'key' => 'createManagedObjectVisits',
               'db' => 'SUM(createManagedObjectVisits)',
               DDPTABLE::LABEL => 'Create Managed Object Visits'
        ),
        array( 'key' => 'createMibRootVisits',
               'db' => 'SUM(createMibRootVisits)',
               DDPTABLE::LABEL => 'Create Mib Root Visits'
        ),
        array( 'key' => 'createPersistenceObjectVisits',
               'db' => 'SUM(createPersistenceObjectVisits)',
               DDPTABLE::LABEL => 'Create Persistence Object Visits'
        ),
        array( 'key' => 'performActionVisits',
               'db' => 'SUM(performActionVisits)',
               DDPTABLE::LABEL => 'Perform Action Visits'
        ),
        array( 'key' => 'performBatchActionVisits',
               'db' => 'SUM(performBatchActionVisits)',
               DDPTABLE::LABEL => 'Perform Batch Action Visits'
        ),
        array( 'key' => 'setManagedObjectAttributesVisits',
               'db' => 'SUM(setManagedObjectAttributesVisits)',
               DDPTABLE::LABEL => 'Set Managed Object Attributes Visits'
        ),
        array( 'key' => 'setManagedObjectsAttributesBatchVisits',
               'db' => 'SUM(setManagedObjectsAttributesBatchVisits)',
               DDPTABLE::LABEL => 'Set Managed Objects Attributes Batch Visits'
        ),
        array( 'key' => 'deleteCmObjectsBatchVisits',
               'db' => 'SUM(deleteCmObjectsBatchVisits)',
               DDPTABLE::LABEL => 'Delete Cm Objects Batch Visits'
        )
    );

    $where = "enm_cmserv_cmwriter_instr.siteid = sites.id AND sites.name = '$site' AND
             enm_cmserv_cmwriter_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             enm_cmserv_cmwriter_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";

    $table = new SqlTable(
        "CM_Writer_Visits",
        $cols,
        array( CMSERV_CMWRITER_INSTR, TABLES ),
        $where,
        true
    );
    echo $table->getTableWithHeader("CM Writer Visits", 1, "", "", "CM_Writer_Visits");
}

function getCmSearchReaderTotals() {
    global $date, $site;
    $cols = array(
        array( 'key' => 'inst',
               'db' => INST_COL,
               DDPTABLE::LABEL => 'MSCM Instance'
        ),
        array( 'key' => 'cmContainmentQueryVisits',
               'db' => 'SUM(cmContainmentQueryVisits)',
               DDPTABLE::LABEL => 'CM Containment Query Visits'
        ),
        array( 'key' => 'cmFdnQueryVisits',
               'db' => 'SUM(cmFdnQueryVisits)',
               DDPTABLE::LABEL => 'CM Fdn Query Visits'
        ),
        array( 'key' => 'cmParentChildQueryVisits',
               'db' => 'SUM(cmParentChildQueryVisits)',
               DDPTABLE::LABEL => 'CM Parent Child Query Visits'
        ),
        array( 'key' => 'cmPoQueryVisits',
               'db' => 'SUM(cmPoQueryVisits)',
               DDPTABLE::LABEL => 'CM Po Query Visits'
        ),
        array( 'key' => 'cmTypeQueryVisits',
               'db' => 'SUM(cmTypeQueryVisits)',
               DDPTABLE::LABEL => 'CM Type Query Visits'
        ),
        array( 'key' => 'compositeCmQueryVisits',
               'db' => 'SUM(compositeCmQueryVisits)',
               DDPTABLE::LABEL => 'Composite CM Query Visits'
        ),
        array( 'key' => 'fastCmTypeQueryVisits',
               'db' => 'SUM(fastCmTypeQueryVisits)',
               DDPTABLE::LABEL => 'Fast CM Type Query Visits'
        )
    );

    $where = "enm_cmserv_cmsearchreader_instr.siteid = sites.id AND sites.name = '$site' AND
             enm_cmserv_cmsearchreader_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             enm_cmserv_cmsearchreader_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";

    $table = new SqlTable(
        "CM_Search_Reader",
        $cols,
        array( CMSERV_CMSEARCHREADER_INSTR, TABLES ),
        $where,
        true
    );
    echo $table->getTableWithHeader("CM Search Reader Visits", 1, "", "", "CM_Search_Reader");
}

function getCmCliTotals() {
    global $date, $site;
    $cliRequests = "IFNULL(SUM(requestsFromCLIVisits),
                   (SUM(scriptEngineExecuteCommandmethodInvocations) - SUM(SFDaddCommandmethodInvocations)))";
    $cols = array(
        array( 'key' => 'inst',
               'db' => INST_COL,
               DDPTABLE::LABEL => INST
        ),
        array( 'key' => 'totalRequests',
               'db' => 'SUM(scriptEngineExecuteCommandmethodInvocations)',
               DDPTABLE::LABEL => 'Total  Requests'
        ),
        array( 'key' => 'scriptingRequests',
               'db' => 'SUM(SFDaddCommandmethodInvocations)',
               DDPTABLE::LABEL => REQUEST_SCRIPT
        ),
        array( 'key' => 'cliRequests',
               'db' => "$cliRequests",
               DDPTABLE::LABEL => REQUEST_CLI
        )
    );

    $where = "cmserv_clistatistics_instr.siteid = sites.id AND sites.name = '$site' AND
             cmserv_clistatistics_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
             cmserv_clistatistics_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";

    $table = new SqlTable(
        "CM_CLI_Totals",
        $cols,
        array( CMSERV_CLISTATISTICS_INSTR, TABLES ),
        $where,
        true
    );
    echo $table->getTableWithHeader("CM CLI Totals", 1, "", "", "CM_CLI_Totals");
}

function getInstrParamsSERs() {
    $where = ' AND IFNull(scriptEngineExecuteCommandmethodInvocations, 0) >= IFNull(SFDaddCommandmethodInvocations, 0)
             AND (scriptEngineExecuteCommandmethodInvocations - SFDaddCommandmethodInvocations) IS NOT NULL';
    $cliRequests = "IFNULL(requestsFromCLIVisits,
                   (scriptEngineExecuteCommandmethodInvocations - SFDaddCommandmethodInvocations))";
    return array(
        array('scriptEngineExecuteCommandmethodInvocations' => array(
            SqlPlotParam::TITLE => 'Total Requests',
            'type' => 'sb',
            'cols' => array('scriptEngineExecuteCommandmethodInvocations' => 'Total Requests')
                                            ),
            'SFDaddCommandmethodInvocations' => array(
            SqlPlotParam::TITLE => REQUEST_SCRIPT,
            'type' => 'sb',
            'cols' => array('SFDaddCommandmethodInvocations' => REQUEST_SCRIPT)
                                            )
        ),
        array('scriptEngineExecuteCommandmethodInvocations - SFDaddCommandmethodInvocations' => array(
                SqlPlotParam::TITLE => REQUEST_CLI,
                'type' => 'sb',
                'cols' => array(
                    "$cliRequests" => REQUEST_CLI
                ),
                SqlPlotParam::WHERE => $where
            )
        )
    );
}

function getInstrParams() {
    $where = 'CM Parent Child Query Total Query Result Count';
    return  array(
        array('cmContainmentQueryTotalQueryResultCount' => array(
            SqlPlotParam::TITLE => 'CM Containment Query Total Query ResultCount',
            'type' => 'sb',
            'cols' => array('cmContainmentQueryTotalQueryResultCount' => 'CM Containment Query Total Query ResultCount')
                                 ),
            'cmFdnQueryTotalQueryResultCount' => array(
                SqlPlotParam::TITLE => 'CM Fdn Query Total Query Result Count',
                'type' => 'sb',
                'cols' => array('cmFdnQueryTotalQueryResultCount' => 'CM Fdn Query Total Query Result Count')
                                 )
        ),
        array('cmParentChildQueryTotalQueryResultCount' => array(
            SqlPlotParam::TITLE => 'CM Parent Child Query Total Query Result Count',
            'type' => 'sb',
            'cols' => array('cmParentChildQueryTotalQueryResultCount' => $where)
                                   ),
            'cmPoQueryTotalQueryResultCount' => array(
                SqlPlotParam::TITLE => 'CM PoQuery Total Query Result Count',
                'type' => 'sb',
                'cols' => array('cmPoQueryTotalQueryResultCount' => 'CM PoQuery Total Query Result Count')
                                          )
        ),
        array('cmTypeQueryTotalQueryResultCount' => array(
            SqlPlotParam::TITLE => 'CM Type Query Total Query Result Count',
            'type' => 'sb',
            'cols' => array('cmTypeQueryTotalQueryResultCount' => 'CM Type Query Total Query Result Count')
                                             ),
            'compositeCmQueryTotalQueryResultCount' => array(
                SqlPlotParam::TITLE => 'Composite CM Query Total Query Result Count',
                'type' => 'sb',
                'cols' => array('compositeCmQueryTotalQueryResultCount' => '')
                                           )
        ),
        array('fastCmTypeQueryTotalQueryResultCount' => array(
            SqlPlotParam::TITLE => 'FastCM Type Query Total Query Result Count',
            'type' => 'sb',
            'cols' => array('fastCmTypeQueryTotalQueryResultCount' => 'FastCM Type Query Total Query Result Count')
                                            ),
        )
    );
}

function setRequest() {
    $avgProcTime = "(cmWriterhandleSetRequestTotalExecutionTime/cmWriterhandleSetRequestVisits)";

    $instrGraphParams = array (
        array(
            'cmWriterhandleSetRequestVisits' => array (
                TITLE => 'CM Writer Set Request Count',
                YLABEL => CNT,
                'cols' => array(
                    'cmWriterhandleSetRequestVisits' => 'CM Writer Set Request Count'
                )
            ),
            'totalNumberOfTransmitExpiryTimeSetOnNode' => array (
                SqlPlotParam::TITLE => 'CM Writer Set Request Average Execution Time',
                YLABEL => 'ms',
                'cols' => array(
                    $avgProcTime => 'CM Writer Set Request Average Execution Time'
                )
            )
        )
    );
    showInstanceGraph( $instrGraphParams, CMSERV_CMWRITER_INSTR );
}

function showInstanceGraph( $instrGraphParams, $dbTable ) {
    global $date;

    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id";
    foreach ( $instrGraphParams as $instrGraphParam ) {
        foreach ( $instrGraphParam as $instrGraphParamName ) {
          $sqlParam = SqlPlotParamBuilder::init()
              ->title($instrGraphParamName[SqlPlotParam::TITLE])
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel($instrGraphParamName[YLABEL])
              ->forceLegend()
              ->makePersistent()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $instrGraphParamName['cols'],
                  $dbTables,
                  $where,
                  array('site'),
                  SqlPlotParam::SERVERS_HOSTNAME
                  )
              ->build();
          $sqlParamWriter = new SqlPlotParam();
          $id = $sqlParamWriter->saveParams($sqlParam);
          $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        }
    }
    plotGraphs($row);
}

function plotInstrGraphs($instrParams, $table) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                SqlPlotParam::TITLE => $instrGraphParamName[SqlPlotParam::TITLE],
                YLABEL => 'Count',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => $instrGraphParamName['type'],
                'sb.barwidth' => 60,
                'querylist' => array(
                    array (
                       'timecol' => 'time',
                       'whatcol' => $instrGraphParamName['cols'],
                       'tables' => "$table, sites, servers",
                       'multiseries' => SERVERS_HOSTNAME,
                       SqlPlotParam::WHERE => "$table.siteid = sites.id AND sites.name = '%s'  AND
                                              $table.serverid = servers.id",
                       'qargs' => array( 'site' )
                    )
                )
            );
           $id = $sqlParamWriter->saveParams($sqlParam);
           $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
        }
    $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function plotCmSearchReaderAverageExecutionTime( $dbTable ) {
    global $date;
    /* Graphs  */
    $sqlParamWriter = new SqlPlotParam();
    drawHeader("CM Search Reader Average Execution Time", HEADER_1, "CMSearchReaderAverageExecutionTime");
    $graphParams = cmSearchReaderAverageExecutionTimeParams();
    $dbTables = array( $dbTable, StatsDB::SITES, StatsDB::SERVERS );

    $where = "$dbTable.siteid = sites.id AND sites.name = '%s' AND $dbTable.serverid = servers.id ";

    foreach ( $graphParams as $column => $lable ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($lable)
            ->type(SqlPlotParam::STACKED_BAR)
            ->barwidth(60)
            ->yLabel("ms")
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $column => $lable ),
                $dbTables,
                $where,
                array( 'site' ),
                SERVERS_HOSTNAME
            )
            ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59 ", true, 640, 320);
    }
    plotGraphs($graphs);
}

function plotCmReaderVisits() {
    $swlostet = "IF(searchWithListOfOutputSpecificationsVisits>0,
                    searchWithListOfOutputSpecificationsTotalExecutionTime/searchWithListOfOutputSpecificationsVisits, 0
                   )";

    $instrGraphParams = array (
        array(
            'searchWithListOfOutputSpecificationsTotalExecutionTime' => array (
                TITLE => 'Search With List Of Output Specification Average Execution Time',
                YLABEL => 'ms',
                'cols' => array(
                    "$swlostet" => 'Search With List Of Output Specification Average Execution Time'
                )
            )
        )
    );
    showInstanceGraph( $instrGraphParams, CMSERV_CMREADER_INSTR );
}

function plotGetCommandsAverageExecutionTime() {
    $gcaet = 'IF(getCommandProcessorVisits>0, getCommandProcessorTotalExecutionTime/getCommandProcessorVisits, 0)';

    $instrGraphParams = array (
        array(
            'getCommandProcessorTotalExecutionTime' => array (
                TITLE => 'Cmedit Get Commands Average Execution Time',
                YLABEL => 'ms',
                'cols' => array(
                    "$gcaet" => 'Cmedit Get Commands Average Execution Time'
                )
            )
        )
    );
    showInstanceGraph( $instrGraphParams, CMSERV_CMREADER_INSTR );
}

function readerMessagesRetransmission() {
    $instrGraphParams = array (
        array(
            SENDBACKTOCMEDITORREQUESTQUEUEVISITS => array (
                TITLE => 'CM Reader Retransmissions Count',
                YLABEL => CNT,
                'cols' => array(
                    SENDBACKTOCMEDITORREQUESTQUEUEVISITS => 'CM Reader Retransmissions Count'
                )
            )
        )
    );
    showInstanceGraph( $instrGraphParams, CMSERV_CMREADER_INSTR );
}

function writerMessagesRetransmission() {
    $instrGraphParams = array (
        array(
            SENDBACKTOCMEDITORREQUESTQUEUEVISITS => array (
                TITLE => 'CM Writer Retransmissions Count',
                YLABEL => CNT,
                'cols' => array(
                    SENDBACKTOCMEDITORREQUESTQUEUEVISITS => 'CM Writer Retransmissions Count'
                )
            )
        )
    );
    showInstanceGraph( $instrGraphParams, CMSERV_CMWRITER_INSTR );
}

function mainFlow() {
    global $debug, $webargs;

    $cmEditURLs = array();
    $cmEditURLs['scriptEngine'] = makeAnchorLink("Script_Engine_Requests_anchor", 'Script-Engine Requests');
    $cmEditURLs['cmReaderVisits'] = makeAnchorLink("CM_Reader_Visits_anchor", 'CM Reader Visits');
    $cmEditURLs['cmdsAvgExeTime'] = makeAnchorLink( GETCMDAVGEXETIME, GET_CMD_AVG_EXE_TIME );
    $cmEditURLs['cmWriterVisits'] = makeAnchorLink("CM_Writer_Visits_anchor", 'CM Writer Visits');
    $cmEditURLs['cmSearchWriter'] = makeAnchorLink("CM_Search_Writer_anchor", 'CM Search Writer');
    $cmEditURLs['cmSearchReader'] = makeAnchorLink("CM_Search_Reader_anchor", 'CM Search Reader');
    $cmEditURLs['cmSRAvgExeTime'] = makeAnchorLink( CMSRAVGEXETIME, CMSR_AVG_EXE_TIME );
    $cmEditURLs['cmSearchReaderCounts'] = makeAnchorLink("CM_Search_Reader_Counts_anchor", 'CM Search Reader Counts');
    $cmEditURLs['setRequest'] = makeAnchorLink( "CM_Set_Request_anchor", 'CM Set Request' );
    $cmEditURLs['msgRetransmission'] = makeAnchorLink( "MessagesRetransmission", 'Messages Retransmission' );
    echo makeHTMLList($cmEditURLs);

    getCmCliTotals();
    $instrGraphParamsSERs = getInstrParamsSERs();

    drawHeaderWithHelp("Script-Engine Requests", 1, "Script_Engine_Requests");
    plotInstrGraphs($instrGraphParamsSERs, CMSERV_CLISTATISTICS_INSTR);

    /* Daily Totals table */
    getCmReaderVisits();
    echo addLineBreak(1);
    plotCmReaderVisits();
    drawHeader("Get Commands Average Execution Time", HEADER_1, "GetCommandsAverageExecutionTime");
    plotGetCommandsAverageExecutionTime();
    getCmWriterTotals();
    getCmSearchReaderTotals();
    plotCmSearchReaderAverageExecutionTime( CMSERV_CMSEARCHREADER_INSTR );

    $instrGraphParams = getInstrParams();
    drawHeaderWithHelp("CM Search Reader Counts", 1, "CM_Search_Reader_Counts");
    plotInstrGraphs( $instrGraphParams, CMSERV_CMSEARCHREADER_INSTR );
    drawHeaderWithHelp("CM Set Request", 1, "CM_Set_Request");
    setRequest();
    drawHeader("Messages Retransmission", HEADER_1, "MessagesRetransmission");
    readerMessagesRetransmission();
    writerMessagesRetransmission();
}
$statsDB = new StatsDB();
mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
