<?php

/**
 *Generates Queues Link
 *
 *@return string A link to Queues including anchor and hover over text
*/
function queueLink() {
    return makeAnchorLink("Queues", "Queues", 'Click here to go to the Queues stats on this page.');
}

/**
 *Generates Route Instrumentation Link
 *
 *@return string A link to Route Instrumentation including anchor and hover over text
*/
function routesLink() {
    $hoverText = 'Click here to go to the Routes stats on this page.';
    return makeAnchorLink("routes", "Routes", $hoverText);
}
