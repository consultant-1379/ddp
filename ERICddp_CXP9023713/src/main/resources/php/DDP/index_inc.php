<?php
    global $datadir;

    $msg = getDataAvailabilityMsg();
    drawHeader('DDP Statistics', 1, '');
    drawHeader($msg, 5, '');

    $links = array();
    $links[] = makeLink('/DDP/makestats.php', 'Makestats');
    $links[] = makeLink('/DDP/page_exec.php', 'PHP Page Execution');
    $links[] = makeLink('/DDP/script_exec.php', 'Script Execution');

    $types = array('statsdb' => 'Stats DB Tables', 'ddpadmin' => 'Admin DB Tables');
    foreach ( $types as $type => $lbl ) {
        $argsArray['type'] = $type;
        $links[] = makeLink('/DDP/db_tables.php', $lbl, $argsArray);
    }

    $links[] = makeLink('/DDP/mysql.php', 'MySQL Stats');
    $links[] = makeLink('/common/hc.php', 'Health Status');
    /* File Search */
    if ( file_exists($datadir) ) {
        $links[] = makeLink('/common/findfile.php', 'File Search');
    }

    $statsDB->query("
SELECT DISTINCT emc_sys.name, emc_sys.id
FROM emc_sys
JOIN emc_site ON emc_sys.id = emc_site.sysid
JOIN sites ON emc_site.siteid = sites.id
WHERE
    sites.name = '$site' AND
    emc_site.filedate = '$date'
    ");
    while ( $row = $statsDB->getNextRow() ) {
        $links[] = makeLink( '/emc_stor.php', "EMC Storage: $row[0]", array('sysid' => $row[1]) );
    }

    echo makeHTMLList($links);
