<?php
    drawHeader('DDP Statistics', 1, '');

    $links = array();
    $links[] = makeLink('/DDP/makestats.php', 'Makestats', $argsArray);
    $links[] = makeLink('/DDP/page_exec.php', 'PHP Page Execution', $argsArray);

    $argsArray['tbl'] = 'ddp_table_stats';
    $types = array('statsdb' => 'Stats DB Tables', 'ddpadmin' => 'Admin DB Tables');
    foreach ( $types as $type => $lbl ) {
        $argsArray['type'] = $type;
        $links[] = makeLink('/DDP/db_tables.php', $lbl, $argsArray);
    }
    unset($argsArray['type']);

    $links[] = makeLink('/DDP/mysql.php', 'MySQL Stats', $argsArray);

    echo makeHTMLList($links);

