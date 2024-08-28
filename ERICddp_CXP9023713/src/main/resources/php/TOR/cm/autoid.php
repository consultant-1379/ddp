<?php
$pageTitle = "Auto ID";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

const ATT_CHANGED_NOTIFICATIONS = 'Attribute Changed Notifications';
const DPS_ATT_CHANGED_EVENT_COUNT = 'dpsAttributeChangedEventCount';
const DPS_EVENT_NOT_PROCESSED_COUNT = 'dpsEventNotProcessedCount';
const OBJECT_CREATED_NOTIFICATION = 'Object Created Notification';
const OBJECT_DELETED_NOTIFICATION = 'Object Deleted Notification';
const DPS_OBJECT_DELETED_EVENT_COUNT = 'dpsObjectDeletedEventCount';
const DPS_OBJECT_CREATED_EVENT_COUNT = 'dpsObjectCreatedEventCount';
const NUM_HIGH_PRIORITY_NETWORK_SYNC_EVENTS = 'numberOfHighPriorityNetworkSyncEvents';
const NUM_LOW_PRIORITY_NETWORK_SYNC_EVENTS = 'numberOfLowPriorityNetworkSyncEvents';
const NUM_OF_CHECK_CONFLICTS = 'numberOfCheckConflicts';
const UNIQUE_CELLS_CONFLICTING = 'Unique Cells Conflicting';
const NUM_OF_UNIQUE_CELLS_CONFLICTING = 'numberOfUniqueCellsConflicting';
const CELLS_PROPOSED = 'Cells Proposed';
const NUM_OF_CONFLICTS_RESOLVED = 'numberOfConflictsResolved';
const CELLS_NOT_PROPOSED = 'Cells Not Proposed';
const NUM_OF_CELLS_NOT_PROPOSED = 'numberOfCellsNotProposed';
const CELLS_RESOLVED = 'Cells Resolved';
const NUM_OF_CELLS_RESOLVED = 'numberOfCellsResolved';
const NUM_OF_HIST_CONFLICT_REPORTS_COUNT = 'numberOfHistoricalConflictReportsCount';
const CONFLICT_REPORTS_LABEL = 'Number Of Historical Conflict ReportsCount';
const NUM_OF_HIST_EXCLUDED_RES_REPORTS_COUNT = 'numberOfHistoricalExcludedResultsReportsCount';
const EXCLUDED_RES_REPORTS_LABEL = 'Number Of Historical Excluded Results ReportsCount';
const NUM_OF_HIST_PCI_RESOLVE_RES_COUNT = 'numberOfHistoricalPCIResolveResultsCount';
const PCI_RESOLVE_RES_LABEL = 'Number Of Historical PCI Resolve ResultsCount';
const DPS_EUTRANCELL_PCI_CHANGES_COUNT = 'dpsEUtranCellPciChangesCount';
const TABLE = 'table';

function getInstrParams() {
                return  array(
        array(DPS_ATT_CHANGED_EVENT_COUNT => array(
            SqlPlotParam::TITLE => ATT_CHANGED_NOTIFICATIONS,
            'type' => 'sb',
            'cols' => array(DPS_ATT_CHANGED_EVENT_COUNT => 'Total Attribute Changed Notifications')
                                 ),
            DPS_EVENT_NOT_PROCESSED_COUNT => array(
                SqlPlotParam::TITLE => 'Notification Not Processed',
                'type' => 'sb',
                'cols' => array(DPS_EVENT_NOT_PROCESSED_COUNT => 'Total Notification Not Processed')
                                 )
        ),
        array(DPS_OBJECT_CREATED_EVENT_COUNT => array(
            SqlPlotParam::TITLE => OBJECT_CREATED_NOTIFICATION,
            'type' => 'sb',
            'cols' => array(DPS_OBJECT_CREATED_EVENT_COUNT => 'Total Object Created Notification')
                                   ),
            DPS_OBJECT_DELETED_EVENT_COUNT => array(
                SqlPlotParam::TITLE => OBJECT_DELETED_NOTIFICATION,
                'type' => 'sb',
                'cols' => array(DPS_OBJECT_DELETED_EVENT_COUNT => 'Total Object Deleted Notification')
                                          )
        ),
        array(NUM_OF_CHECK_CONFLICTS => array(
                SqlPlotParam::TITLE => 'Detected Conflicts',
                'type' => 'sb',
                'cols' => array(NUM_OF_CHECK_CONFLICTS => 'Total Detected Conflicts')
                                           ),
            NUM_OF_UNIQUE_CELLS_CONFLICTING => array(
                SqlPlotParam::TITLE => UNIQUE_CELLS_CONFLICTING,
                'type' => 'sb',
                'cols' => array(NUM_OF_UNIQUE_CELLS_CONFLICTING => 'Total Unique Cell Conflicts')
                                           )
        ),
        array(NUM_OF_CONFLICTS_RESOLVED => array(
            SqlPlotParam::TITLE => CELLS_PROPOSED,
            'type' => 'sb',
            'cols' => array(NUM_OF_CONFLICTS_RESOLVED => 'Total Cells Proposed')
                                           ),
        NUM_OF_CELLS_NOT_PROPOSED  => array(
            SqlPlotParam::TITLE => CELLS_NOT_PROPOSED,
            'type' => 'sb',
            'cols' => array(NUM_OF_CELLS_NOT_PROPOSED => CELLS_NOT_PROPOSED)
                                           )
        ),
        array(NUM_OF_CELLS_RESOLVED => array(
            SqlPlotParam::TITLE => CELLS_RESOLVED,
            'type' => 'sb',
            'cols' => array(NUM_OF_CELLS_RESOLVED => 'Total Cells Resolved')
                                           ),
        DPS_EUTRANCELL_PCI_CHANGES_COUNT => array(
            SqlPlotParam::TITLE => 'PCI changes notification count',
            'type' => 'sb',
            'cols' => array(DPS_EUTRANCELL_PCI_CHANGES_COUNT => 'EUtran Cell Pci Changes Count')
        )
       )
    );
}

function getNetworkSyncEventsGraphs() {
                 return array(
           array(NUM_LOW_PRIORITY_NETWORK_SYNC_EVENTS => array(
            SqlPlotParam::TITLE => 'Low Priority Network Sync Events',
            'type' => 'sb',
            'cols' => array(NUM_LOW_PRIORITY_NETWORK_SYNC_EVENTS => 'Total Low Priority Network Sync Events')
                                            ),
                NUM_HIGH_PRIORITY_NETWORK_SYNC_EVENTS => array(
                SqlPlotParam::TITLE => 'High Priority Network Sync Events',
                'type' => 'sb',
                'cols' => array(NUM_HIGH_PRIORITY_NETWORK_SYNC_EVENTS => 'Total High Priority Network Sync Events')
                                             )
                )
    );
}

function getHistoricalexecutiondataGraphs() {
    return array(
            array(NUM_OF_HIST_CONFLICT_REPORTS_COUNT => array(
            SqlPlotParam::TITLE => CONFLICT_REPORTS_LABEL,
	        'type' => 'sb',
            'cols' => array(NUM_OF_HIST_CONFLICT_REPORTS_COUNT => CONFLICT_REPORTS_LABEL)                  	      ),
            NUM_OF_HIST_EXCLUDED_RES_REPORTS_COUNT => array(
            SqlPlotParam::TITLE => EXCLUDED_RES_REPORTS_LABEL,
            'type' => 'sb',
            'cols' => array(NUM_OF_HIST_EXCLUDED_RES_REPORTS_COUNT => EXCLUDED_RES_REPORTS_LABEL)
	        )
            ),
            array(NUM_OF_HIST_PCI_RESOLVE_RES_COUNT => array(
            SqlPlotParam::TITLE => PCI_RESOLVE_RES_LABEL,
            'type' => 'sb',
            'cols' => array(NUM_OF_HIST_PCI_RESOLVE_RES_COUNT => PCI_RESOLVE_RES_LABEL)                     )
            )
       );
}

function cellsGraphs() {

    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $instances = getInstances("enm_saidserv_instr");

    foreach ( $instances as $instance ) {
        $header[] = $instance;
    }
    $graphTable = new HTML_Table("border=0");
    $graphTable->addRow($header, null, 'th');

    $row = array();
    foreach ( $instances as $instance ) {
    $sqlParam =
      array( SqlPlotParam::TITLE => '',
             'ylabel' => 'Count',
             'type' => 'sb',
             'sb.barwidth' => '60',
             'useragg' => 'true',
             'persistent' => 'true',
             'querylist' =>
             array(
                   array(
                         'timecol' => 'time',
                         'whatcol' => array (
                             NUM_OF_UNIQUE_CELLS_CONFLICTING  =>
                             UNIQUE_CELLS_CONFLICTING,NUM_OF_CONFLICTS_RESOLVED => CELLS_PROPOSED ,
                             NUM_OF_CELLS_NOT_PROPOSED =>
                             CELLS_NOT_PROPOSED ,
                             NUM_OF_CELLS_RESOLVED  =>
                             CELLS_RESOLVED
                         ),
                         'tables' => "enm_saidserv_instr,servers,sites",
                         'where' => "enm_saidserv_instr.siteid = sites.id AND  sites.name = '%s' AND
                                     enm_saidserv_instr.serverid = servers.id AND servers.hostname='%s'",
                         'qargs' => array( 'site', 'inst' )
                        )
                   )
            );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320, "inst=$instance");
    }
    $graphTable->addRow($row);
    drawHeaderWithHelp("Cells", 2, "Cells");
    echo $graphTable->toHTML();

}

function plotInstrGraphs($instrParams) {
    global $date;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                SqlPlotParam::TITLE => $instrGraphParamName[SqlPlotParam::TITLE],
                'ylabel' => 'Count',
                'useragg' => 'true',
                'persistent' => 'false',
                'type' => $instrGraphParamName['type'],
                'sb.barwidth' => 60,
                'querylist' => array(
                    array (
                        'timecol' => 'time',
                        'whatcol' => $instrGraphParamName['cols'],
                        'tables' => "enm_saidserv_instr, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_saidserv_instr.siteid = sites.id AND sites.name = '%s'  AND
                                   enm_saidserv_instr.serverid = servers.id",
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

if (requestValue('format') == "xls" && issetURLParam(TABLE)) {
    $table;
    if ( requestValue(TABLE) == "MODetailsTable" ) {
        $table = new MODetailsTable();
    } else {
        echo "Invalid table name: " . requestValue(TABLE);
        exit;
    }
    $table->title = "Mo Details Table";
    $excel = new ExcelWorkbook();
    $excel->addObject($table);
    $excel->write();
    exit;
}

function mainFlow() {
    global $site, $date;

    $sg = requestValue('SG');
    $link = makeFullGenJmxLink("$sg", "Generic JMX");
    echo makeHTMLList( array($link) );

    $dailyTotals = new ModelledTable( 'TOR/cm/autoid_daily_totals', 'Daily_Totals' );
    echo $dailyTotals->getTableWithHeader('Daily Totals');

    $moDetailsTable = new ModelledTable( 'TOR/cm/autoid_mo_details', 'MO_Details_Table' );
    echo $moDetailsTable->getTableWithHeader('MO Details Table');

    drawHeaderWithHelp("Auto Cell ID Instrumentation", 1, "Auto_Cell_ID_Instrumentation");
    $instrGraphParams = getInstrParams();
    plotInstrGraphs($instrGraphParams);
    cellsGraphs();
    $networkSyncEvents = getNetworkSyncEventsGraphs();
    drawHeaderWithHelp("Network Sync Events", 2, "Network_Sync_Events");
    plotInstrGraphs($networkSyncEvents);
    $historicalExecutionDataGraphs = getHistoricalexecutiondataGraphs();
    drawHeaderWithHelp("Historical Execution Data Graphs", 2, "Historical_Execution_Data_Graphs");
    plotInstrGraphs($historicalExecutionDataGraphs);
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

