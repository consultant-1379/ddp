<?php

$pageTitle = "SHM REST NBI";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

function shmNbiParams() {
    $job = array(
            'enm_shm_rest_nbi_job',
            'SHM REST NBI - Job Interface Usage',
            'shmNbiJob'
    );

    $backup = array(
            'enm_shm_rest_nbi_backup',
            'SHM REST NBI - Node Backup Management Interface Usage',
            'shmNbiNodebackup'
    );

    return array( $job, $backup );
}

function mainFlow() {
    $params = shmNbiParams();

    foreach ( $params as $param ) {
        drawHeader( $param[1], 2, $param[2] );
        $tbl = new ModelledTable( 'TOR/shm/' . $param[0], $param[2] );
        echo $tbl->getTable();
    }
}

mainFlow();

include_once PHP_ROOT . "/common/finalise.php";

