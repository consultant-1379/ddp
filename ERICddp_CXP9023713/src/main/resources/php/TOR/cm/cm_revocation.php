<?php
$pageTitle = "CM Revocation";

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

const EXCLUDED_UNSUPPORTED_OPS = 'totalExcludedUnsupportedOperations';
const EXCLUDED_NON_NRM_MOS = 'totalExcludedNonNrmMos';
const EXCLUDED_SYSTEM_CREATED_MOS = 'totalExcludedSystemCreatedMos';
const HISTORY_ITEMS = 'totalHistoryItems';

function mainFlow() {
    global $statsDB;

    echo <<<ESCRIPT
        <script type="text/javascript">
function formatStatus(elCell, oRecord, oColumn, oData) {
    var totalUndoMOs = oRecord.getData("totalUndomos");
    var totalExcludedMOs = oRecord.getData("totalExcludedMos");
    var totalOperations = oRecord.getData("totalHistoryItems");

    if ( totalUndoMOs > 0 && ((totalUndoMOs + totalExcludedMOs) == totalOperations) ) {
        elCell.innerHTML = "SUCCESS";
    } else {
        elCell.innerHTML = "FAILURE";
    }
}
</script>

ESCRIPT;

    $totalExcludedMosDbColParts = array(
        EXCLUDED_UNSUPPORTED_OPS,
        EXCLUDED_NON_NRM_MOS,
        EXCLUDED_SYSTEM_CREATED_MOS,
        'IFNULL(totalExcludedNotDeletableMos,0)'
    );
    $table = SqlTableBuilder::init()
    ->name("cmRevocation")
    ->tables(array("enm_cm_revocation_instr", "sites"))
    ->where($statsDB->where("enm_cm_revocation_instr", "endTime"))
    ->addSimpleColumn('undoJobId', 'Undo Job ID')
    ->addColumn('start', 'startTime', 'Start Time', DDPTable::FORMAT_TIME)
    ->addColumn('end', 'endTime', 'End Time', DDPTable::FORMAT_TIME)
    ->addSimpleColumn('TIME_TO_SEC(TIMEDIFF(endTime, startTime))', 'Undo Duration')
    ->addSimpleColumn('totalCreate', 'Created MO')
    ->addSimpleColumn('totalModify', 'Modified MO')
    ->addSimpleColumn('totalDelete', 'Deleted MO')
    ->addColumn('totalUndomos', 'totalCreate + totalModify + totalDelete', 'Total Undo MO')
    ->addColumn(HISTORY_ITEMS, HISTORY_ITEMS, 'Total Operations')
    ->addSimpleColumn(EXCLUDED_UNSUPPORTED_OPS, 'Unsupported Operations')
    ->addSimpleColumn(EXCLUDED_NON_NRM_MOS, 'Non NRM MOs')
    ->addSimpleColumn(EXCLUDED_SYSTEM_CREATED_MOS, 'System Created MOs')
    ->addSimpleColumn('totalExcludedNotDeletableMos', 'Not Deletable MOs')
    ->addColumn('totalExcludedMos', implode('+', $totalExcludedMosDbColParts), 'Total Excluded MOs')
    ->addColumn('status', '""', 'Result', 'formatStatus')
    ->addSimpleColumn('queryDuration', 'Query Time (ms)')
    ->addSimpleColumn('processingDuration', 'Processing Time (ms)')
    ->addSimpleColumn('fileWriteDuration', 'File Writing Time (ms)')
    ->addSimpleColumn('application', 'Application')
    ->addSimpleColumn('applicationJobId', 'App Job ID')
    ->paginate()
    ->build();
    echo $table->getTableWithHeader("CM Revocation", 2, "DDP_Bubble_203_ENM_CM_Revocation_InstrumentationHelp");
}

mainFlow();

include PHP_ROOT . "/common/finalise.php";
