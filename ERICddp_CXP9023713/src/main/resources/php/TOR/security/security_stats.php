<?php
$pageTitle = "Security Stats";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once 'HTML/Table.php';
const BORDER_0 = "border=0";
const SECSERV_INSTR_TABLE = "enm_secserv_instr";
const LABEL = 'label';

function securityStatsTotals() {
    global $date,$site;

    $dbTable = SECSERV_INSTR_TABLE;
    $where = "
$dbTable.siteid = sites.id AND sites.name = '$site' AND
$dbTable.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
$dbTable.serverid = servers.id
GROUP BY servers.hostname WITH ROLLUP";

    $outTable = SqlTableBuilder::init()
              ->name($dbTable)
              ->tables(array( $dbTable, StatsDB::SITES, StatsDB::SERVERS ))
              ->where($where)
              ->addColumn('server', "IFNULL(servers.hostname,'Totals')", 'Security Service Instance')
              ->addSimpleColumn('SUM(numOfSuccessfulIscfInvocations)', 'Total Successful ISCF Invocations')
              ->addSimpleColumn('SUM(numOfFailedIscfInvocations)', 'Total Failed ISCF Invocations')
              ->addSimpleColumn('SUM(numOfSuccessfulWorkflows)', 'Total Successful Workflows')
              ->addSimpleColumn('SUM(numOfFailedWorkflows)', 'Total Failed Workflows')
              ->addSimpleColumn('SUM(numOfErroredWorkflows)', 'Total Errored Workflows')
              ->addSimpleColumn('SUM(numOfTimedOutWorkflows)', 'Total Timed-Out Workflows')
              ->build();
    echo $outTable->getTableWithHeader("Daily Totals", 1, "", "", "Daily_Totals");
    echo addLineBreak();

    $param = array(
        array( 'db'  => 'numOfSuccessfulIscfInvocations',
                LABEL => 'Number of Successful ISCF Invocations'
        ),
        array( 'db'  => 'numOfFailedIscfInvocations',
                LABEL => 'Number of Failed ISCF Invocations'
        ),
        array( 'db'  => 'numOfSuccessfulWorkflows',
                LABEL => 'Number of Successful Workflows'
        ),
        array( 'db'  => 'numOfFailedWorkflows',
                LABEL => 'Number of Failed Workflows'
        ),
        array( 'db'  => 'numOfErroredWorkflows',
                LABEL => 'Number of Errored Workflows'
        ),
        array( 'db'  => 'numOfTimedOutWorkflows',
                LABEL => 'Number of Timed-Out Workflows'
        ));
    drawHeaderWithHelp("Node Security Counter Graphs", 1, "Node_Security_Counter_Graphs");
    $graphTable = new HTML_Table(BORDER_0);
    foreach ( $param as $column ) {
        $dbCol = $column['db'];
        $label = $column[LABEL];
        $dbTablesGraph = array( $dbTable, StatsDB::SITES );
        $whereGraph = "$dbTable.siteid = sites.id AND sites.name = '%s'";
        $sqlParamWriter = new SqlPlotParam();
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($label)
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel($label)
            ->makePersistent()
            ->presetAgg(SqlPlotParam::USER_AGG, '')
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $dbCol => $label ),
                $dbTablesGraph,
                $whereGraph,
                array( 'site' )
            )
            ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $url =  $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 800, 300);
        $graphTable->addRow( array( $url ) );
    }

    echo $graphTable->toHTML();
}

function dynamiccounters() {
    global $date,$site;
    $dbTable = SECSERV_INSTR_TABLE;

    $param = array(
        array( 'db'  => 'numOfRunningWorkflows',
                LABEL => 'Number of Running Workflows'
        ),
        array( 'db'  => 'numOfPendingWorkflows',
                LABEL => 'Number of Pending Workflows'
        ));
    drawHeaderWithHelp("Dynamic Counters", 1, "Dynamic_Counters");
    $graphTable = new HTML_Table(BORDER_0);
    foreach ( $param as $column ) {
        $dbCol = $column['db'];
        $label = $column[LABEL];
        $dbTablesGraph = array( $dbTable, StatsDB::SITES );
        $whereGraph = "$dbTable.siteid = sites.id AND sites.name = '%s'";
        $sqlParamWriter = new SqlPlotParam();
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($label)
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel($label)
            ->makePersistent()
            ->presetAgg(SqlPlotParam::USER_AGG, '')
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $dbCol => $label ),
                $dbTablesGraph,
                $whereGraph,
                array( 'site' )
            )
            ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $url =  $sqlParamWriter->getImgURL($id, $date . " 00:00:00", $date . " 23:59:59", true, 800, 300);
        $graphTable->addRow( array( $url ) );
    }

    echo $graphTable->toHTML();
}


function nodeSecurityCounters() {
    global $debug, $webargs, $php_webroot;

    $dailytotals = makeAnchorLink("Daily_Totals_anchor", "Daily Totals");
    $nodeSecurityCountersGraphs = makeAnchorLink("Node_Security_Counter_Graphs_anchor", "Node Security Counter Graphs");
    $dynamicCountersGraphs = makeAnchorLink("Dynamic_Counters_anchor", "Dynamic Counters");
    echo makeHTMLList(array($dailytotals, $nodeSecurityCountersGraphs, $dynamicCountersGraphs));

    securityStatsTotals();
    dynamiccounters();
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;
    echo makeLink('/TOR/security/security_stats.php', 'Node Security Counters', array('nodeSecurityCounters' => '1'));
    echo addLineBreak();
    echo makeLink('/TOR/security/node_security_workflows.php', 'Node Security Workflow Counters');
    echo addLineBreak();
    echo "<a href='#GenericJMX_anchor'>Generic JMX</a>\n";
    $hasData = array();
    $table = "enm_radionode_filetransfer";
    $nodetypes = array( "Radionode", "Controller6610" );
    foreach ( $nodetypes as $type ) {
       $hasData[$type] = $statsDB->hasData($table, 'date', false, "$table.type = '$type'");
          if ( $hasData[$type] ) {
             echo makeHTMLList(array(makeAnchorLink("ftpesNodes_$type", "FTPes Statistics $type")));
          }
    }

    $instances = getInstances(SECSERV_INSTR_TABLE);
    /* GenericJMX */
    $jmxGraphArray = array();
    drawHeaderWithHelp("Generic JMX", 2, "GenericJMX", "DDP_Bubble_194_Generic_JMX_Help");
    foreach ( $instances as $instance ) {
        $jmxObject = new GenericJMX($statsDB, $site, $instance, "securityservice", $date, $date, 240, 480);
        $jmxGraphArray[] = $jmxObject->getGraphArray();
    }
    $graphTable = new HTML_Table(BORDER_0);
    $graphTable->addRow($instances, null, 'th');

    /* For each graph type (specified by index), add all JMX's graphs to row and add row to table */
    if ( !isset($jmxGraphArray[0]) ) {
        $jmxGraphArray[0] = '';
    }
    $jmxCount = 0;
    if (is_array($jmxGraphArray[0])) {
        $jmxCount = count($jmxGraphArray[0]);
    }
    for($i = 0; $i < $jmxCount; $i ++) {
        $row = array();
        foreach ( $jmxGraphArray as $graphSet ) {
            $row[] = $graphSet[$i];
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
    $xmlFile = "TOR/cm/enm_file_transfer";
    foreach ( $nodetypes as $type ) {
       if ( $hasData[$type] ) {
          $enmFileTransfer = new ModelledTable( $xmlFile, "ftpesNodes_".$type, array('type' => $type));
          echo $enmFileTransfer->getTableWithHeader("FTPes Statistics $type");
       }
    }
}

$statsDB = new StatsDB();

if ( issetURLParam('nodeSecurityCounters') ) {
    nodeSecurityCounters();
} else {
    mainFlow($statsDB);
}

include PHP_ROOT . "/common/finalise.php";

?>
