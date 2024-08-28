<?php
$pageTitle = "MSPM";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/Routes.php";
require_once PHP_ROOT . "/common/routeFunctions.php";

const ACT = 'action';

function mainFlow() {
    global $statsDB, $site, $date;

    /* Links */
    echo makeHTMLList(
        array(
            makeLink( '/TOR/dps.php', 'DPS', array('servers' => makeSrvList('mspm') ) ),
            "<a href=\"" . makeGenJmxLink("mspm") . "\">GenJmx</a>"
        )
    );

    $processingSrv = enmGetServiceInstances($statsDB, $site, $date, 'mspm');
    $srvIdArr = array_values($processingSrv);

    getRouteInstrTable( $srvIdArr );
}

if ( issetUrlParam(ACT) ) {
    $action = requestValue(ACT);
    $selected = requestValue('selected');

    if ($action === 'plotRouteGraphs') {
        plotRoutes($selected);
    }
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
