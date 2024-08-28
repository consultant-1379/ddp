<?php
$pageTitle = 'Os Memory Profile Rhel';

include_once "../common/init.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";

function mainflow() {
    $host = requestValue('server');
    drawHeader( 'OS Memory Profile', 1, 'memoryHelp' );
    $metrics = "'MemTotal', 'MemFree', 'MemAvailable',
                'Buffers', 'Cached', 'Active', 'Inactive',
                'Dirty', 'Mapped', 'Slab', 'AnonPages',
                'SwapTotal', 'SwapFree', 'KernelStack',
                'VmallocTotal', 'VmallocUsed'";

    $modelledGraph = new ModelledGraph('ENIQ/osMemoryProfileRhel');
    $params = array( 'hostname' => $host, 'metrics' => $metrics );
    plotgraphs( array( $modelledGraph->getImage($params) ) );
}

mainflow();
include_once PHP_ROOT . "/common/finalise.php";
