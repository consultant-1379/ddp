<?php
$pageTitle = "CLI Scripting Statitics";


include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const CMSERV_CLISTATISTICS_INSTR = "cmserv_clistatistics_instr";

function cliScriptingDailyTotals( $table, $cols, $title, $tableName ) {
    global $statsDB;

    $where = $statsDB->where($table);
    $where .= " AND $table.serverid = servers.id
              GROUP BY servers.hostname WITH ROLLUP";
    $tables = array( $table, StatsDB::SITES, StatsDB::SERVERS );

    $table = SqlTableBuilder::init()
        ->name($tableName)
        ->tables($tables)
        ->where($where)
        ->addColumn('inst', "IFNULL(servers.hostname,'Totals')", 'Instance');

    foreach ($cols as $key => $value) {
        $table->addSimpleColumn($key, $value);
    }

    echo drawHeader($title, 2, $tableName);
    echo $table->build()->getTable();
}

function dailyTotalsCMServ() {
    return array(
                'SUM(IFNULL(SCRsubmitCommandmethodInvocations, 0))' => 'Method Invocations (SCR POST)',
                'ROUND( IFNULL( SUM(IFNULL(SCRsubmitCommandexecutionTimeTotalMillis, 0))
                  /SUM(IFNULL(SCRsubmitCommandmethodInvocations, 0)), 0 ), 2 )' => 'Avg Execution Time (SCR POST)',
                'ROUND( IFNULL( SUM(IFNULL(SFDaddCommandexecutionTimeTotalMillis, 0))
                  /SUM(IFNULL(SFDaddCommandmethodInvocations, 0)), 0 ), 2 )' => 'Avg Execution Time (SFD POST)',
                'ROUND( IFNULL( SUM(IFNULL(SFDgetCommandStatusexecutionTimeTotalMillis, 0))
                  /SUM(IFNULL(SFDgetCommandStatusmethodInvocations, 0)), 0 ), 2 )' => 'Avg Execution Time (SFD HEAD)',
                'ROUND( IFNULL( SUM(IFNULL(SFDgetCommandResponseexecutionTimeTotalMillis, 0))
                  /SUM(IFNULL(SFDgetCommandResponsemethodInvocations, 0)), 0 ), 2 )' => 'Avg Execution Time (SFD GETS)'
    );
}

function cmservCLISCRGraphs() {
    global $date;
    $sqlParamWriter = new SqlPlotParam();

    $instances = getInstances(CMSERV_CLISTATISTICS_INSTR);

    foreach ( $instances as $instance ) {
        $header[] = "CLI Scripting " . $instance;
    }

    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($header, null, 'th');
    $graphParams = array(
                       'SCRsubmitCommandexecutionTimeTotalMillis' =>
                       'SCR submitCommand',
                       'SFDaddCommandexecutionTimeTotalMillis' =>
                       'SFD addCommand',
                       'SFDgetCommandStatusexecutionTimeTotalMillis' =>
                       'SFD getCommandStatus',
                       'SFDgetCommandResponseexecutionTimeTotalMillis' =>
                       'SFD getCommandResponse'
    );

    foreach ( $graphParams as $col => $label ) {
        $row = array();
        foreach ( $instances as $instance ) {
            $sqlParam =
                  array( 'title' => "CLI $label",
                         'ylabel' => 'Execution Time (millisec)',
                         'type' => 'tsc',
                         'useragg' => 'true',
                         'sb.barwidth' => '60',
                         'persistent' => 'false',
                         'querylist' =>
                         array(
                               array(
                                     'timecol' => 'time',
                                     'whatcol' => array ( 'cmserv_clistatistics_instr.' . $col => $label),
                                     'tables' => "cmserv_clistatistics_instr,sites,servers",
                                     'where' => "cmserv_clistatistics_instr.siteid = sites.id AND  sites.name = '%s' AND cmserv_clistatistics_instr.serverid = servers.id AND servers.hostname='$instance'",
                                     'qargs' => array( 'site' )
                                    )
                              )
                       );
            $id = $sqlParamWriter->saveParams($sqlParam);
            $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 180);
        }
        $graphTable->addRow($row);
    }

    drawHeaderWithHelp( "ScriptingCommandResource (SCR) (Times) AND ScriptEngineSessionFacadeDelegate (SFD) (Times)", 2, "scr times", "DDP_Bubble_18_1_Cmserv_Cli_Statistics_Scripting_Command_Resource_SFD" );
    echo $graphTable->toHTML();
    echo "<br/>";

    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($header, null, 'th');

    $graphParams =
    array('SCRsubmitCommandmethodInvocations' => 'SCR submitCommand');

    foreach ( $graphParams as $col => $label ) {
        $row = array();
        foreach ( $instances as $instance ) {
            $sqlParam =
                array( 'title' => "CLI $label",
                 'ylabel' => 'method Invocations',
                 'type' => 'tsc',
                 'useragg' => 'true',
                 'sb.barwidth' => '60',
                 'persistent' => 'false',
                 'querylist' =>
                 array(
                       array(
                             'timecol' => 'time',
                             'whatcol' => array ( 'cmserv_clistatistics_instr.' . $col => $label),
                             'tables' => "cmserv_clistatistics_instr,sites,servers",
                             'where' => "cmserv_clistatistics_instr.siteid = sites.id AND  sites.name = '%s' AND cmserv_clistatistics_instr.serverid = servers.id AND servers.hostname='$instance'",
                             'qargs' => array( 'site' )
                            )
                      )
               );
            $id = $sqlParamWriter->saveParams($sqlParam);

            $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 180);
        }
        $graphTable->addRow($row);
    }
    drawHeaderWithHelp( "ScriptingCommandResource (SCR) (Counts)", 2, "scr count", "DDP_Bubble_18_Cmserv_Cli_Statistics_Scripting_Command_Resource" );
    echo $graphTable->toHTML();

}

function cmservCLISCRSFDGraphs() {

    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $instances = getInstances(CMSERV_CLISTATISTICS_INSTR);

    foreach ( $instances as $instance ) {
        $header[] = "cmserv CLI Scripting " . $instance;
    }

    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($header, null, 'th');

    $row = array();
    $cols =  array (
       '(IFNULL(SFDaddCommandexecutionTimeTotalMillis,0)+
       IFNULL(SFDgetCommandStatusexecutionTimeTotalMillis,0)+
       IFNULL(SFDgetCommandResponseexecutionTimeTotalMillis,0)
       )' => 'SFD Statistics',
       '((IFNULL(SCRsubmitCommandexecutionTimeTotalMillis,0))-
       (IFNULL(SFDaddCommandexecutionTimeTotalMillis,0)+
       IFNULL(SFDgetCommandStatusexecutionTimeTotalMillis,0)+
       IFNULL(SFDgetCommandResponseexecutionTimeTotalMillis,0))
       )' => 'SCR - SFD Statistics'
    );
    foreach ( $instances as $instance ) {
    $sqlParam =
      array( 'title' => 'CMSERV CLI SFD AND SCR - SFD(Times)',
             'ylabel' => 'Execution Time (millisec)',
             'type' => 'sb',
             'sb.barwidth' => '60',
             'useragg' => 'true',
             'persistent' => 'false',
             'querylist' =>
             array(
                   array(
                         'timecol' => 'time',
                          'whatcol' => $cols,
                         'tables' => "cmserv_clistatistics_instr,servers,sites",
                         'where' => "cmserv_clistatistics_instr.siteid = sites.id AND  sites.name = '%s' AND cmserv_clistatistics_instr.serverid = servers.id AND servers.hostname='$instance'",
                         'qargs' => array( 'site' )
                        )
                   )
            );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 180);
    }
    $graphTable->addRow($row);
    drawHeaderWithHelp("SFD And SCR - SFD Statistics(Times)", 2, "instrSCRSFD", "DDP_Bubble_25_Cmserv_Cli_Statistics_ScriptEngine_SCR_SFD");
    echo $graphTable->toHTML();
}

function mainFlow() {
    /* Daily Totals - Summary Table */
    $params = dailyTotalsCMServ();
    cliScriptingDailyTotals(CMSERV_CLISTATISTICS_INSTR, $params, 'Daily Totals', 'DT_CliScriptingHelp');
    echo "<br/>";

    cmservCLISCRSFDGraphs();
    echo "<br/>";
    cmservCLISCRGraphs();
}

mainFlow();
include PHP_ROOT . "/common/finalise.php";

