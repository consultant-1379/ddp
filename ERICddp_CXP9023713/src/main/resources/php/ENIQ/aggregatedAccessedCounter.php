<?php

$pageTitle = 'Counter Tool Statistics';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
const SELF = '/ENIQ/aggregatedAccessedCounter.php';

function showLinks() {
    global $statsDB;
    $links = array();
    if ($statsDB->hasData('eniq_agg_fail_counter_date', 'date')) {
        $links[] = makeLink(SELF, 'failed_aggregation_file_found', array('counterCell' => '1'));
        echo makeHTMLList($links);
    }
}

function main() {
    showLinks();

    if ( issetURLParam('counterCell') ) {
        $table = new ModelledTable('ENIQ/eniq_failed_data', 'eniq_failed_data');
        echo $table->getTableWithHeader("Aggregation Failure Date");
        echo addLineBreak();
    }

    $table = new ModelledTable('ENIQ/aggregated_accessed_counter', 'aggregatedAccessedCounterHelp');
    echo $table->getTableWithHeader("Accessed Counter Information");
    echo addLineBreak();
}

main();

include_once PHP_ROOT . "/common/finalise.php";
