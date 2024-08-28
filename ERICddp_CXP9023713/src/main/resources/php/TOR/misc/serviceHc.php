<?php
$pageTitle = "Service Health Status";

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

const TABLE = 'enm_vm_hc';
const HOST = 'hostname';

function drawSummaryTable( $instance ) {
    global $statsDB;

    $where = $statsDB->where(TABLE);
    $where .= " AND servers.id = serverid AND servers.id IN ($instance) AND
                enm_vm_hc_summarys.id = enm_vm_hc.summaryId";

    $table = SqlTableBuilder::init()
           ->name('SummaryTable')
           ->tables(array(TABLE, 'enm_vm_hc_summarys', StatsDB::SITES, StatsDB::SERVERS))
           ->where($where)
           ->addColumn('time', 'time', 'Time', "ddpFormatTime")
           ->addColumn(HOST, 'servers.hostname', 'Instance', 'formatHostname')
           ->addSimpleColumn('status', 'Status')
           ->addSimpleColumn('enm_vm_hc_summarys.name', 'Summary')
           ->addSimpleColumn('summaryData', 'summaryData')
           ->sortBy('time', DDPTable::SORT_ASC);

    echo $table->paginate()->dbScrolling()->build()->getTable();
}


function instanceTable() {
    global $statsDB, $webargs;

    $where = $statsDB->where(TABLE) . " AND servers.id = serverid GROUP BY servers.hostname";

    $table = SqlTableBuilder::init()
           ->name('InstanceTable')
           ->tables(array(TABLE, StatsDB::SITES, StatsDB::SERVERS))
           ->where($where)
           ->addHiddenColumn('id', 'servers.id')
           ->addSimpleColumn('servers.hostname', 'Instance')
           ->addSimpleColumn('COUNT(enm_vm_hc.status)', 'Failures');

    $url = fromServer(PHP_SELF) . "?" . $webargs;

    $table->ctxMenu(
        'action',
        true,
        array( true => 'Show Summary Data'),
        $url,
        'id'
    );

    echo $table->paginate()->build()->getTable();
}

function mainFlow() {
    drawHeader('Health Check Failures per Instance', 1, 'serviceHC');
    instanceTable();
}

$serverURL = PHP_WEBROOT . "/server.php?" . fromServer('QUERY_STRING') . "&server=";

echo <<<EOS
<script type="text/javascript">
    function formatHostname(elCell, oRecord, oColumn, oData) {
        var hostname = oRecord.getData("hostname");
        elCell.innerHTML = "<a href=\"$serverURL" + hostname + "\">" + hostname + "</a>";
    }
</script>
EOS;

if ( issetURLParam('action') ) {
    drawHeader('Health Check Failures Summary', 1, 'serviceHCSummary');
    drawSummaryTable( requestValue('selected') );
} else {
    mainFlow();
}

require_once PHP_ROOT . "/common/finalise.php";

