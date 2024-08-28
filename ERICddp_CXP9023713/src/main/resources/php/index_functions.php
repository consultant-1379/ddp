<?php

function displayExternalLinks() {
    global $statsDB, $AdminDB, $site;

    $siteId = getSiteId($statsDB, $site);
    $sql = "SELECT link, label FROM $AdminDB.ddp_links WHERE siteId = $siteId";
    $linkData = array();

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextNamedRow() ) {
        $url = $row['link'];
        $lbl = $row['label'];

        $linkData[] = '<a href="' . $url . "\">$lbl</a>";
    }

    if ( $linkData ) {
        drawHeader( 'External Links', 1, 'extLinks' );
        echo makeHTMLList( $linkData );
    }
}


