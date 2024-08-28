<?php

$pageTitle = 'SFS Snapshot Cache Status';

require_once "../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function main() {
    drawHeader( 'SFS Snapshot Cache Status', 1, 'sfsSnapCacheStatusTableHelp' );
    $table = new ModelledTable('ENIQ/sfs_snapshot_cache_status', 'sfs_snapshot_cache_status');
    echo $table->getTable();
}

main();
include_once PHP_ROOT . "/common/finalise.php";
