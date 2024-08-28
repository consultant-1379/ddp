<?php
$pageTitle = "Dump AdminDB";

include_once "init.php";
require_once 'HTML/Table.php';

function getTableList() {
    global $statsDB, $AdminDB;

    $tables = array();
    $statsDB->exec("use $AdminDB");
    $sql = "SHOW TABLES;";
    $statsDB->query($sql);

    while ( $row = $statsDB->getNextRow() ) {
        $tables[] = $row[0];
    }
    array_unshift($tables, 'All');
    return $tables;
}

function drawTable($data) {
    $table = new HTML_Table("border=0");
    while ( count($data) > 0 ) {
        $list = array();
        for ($i = 0; $i < 4; $i++) {
            if ( !empty($data) ) {
                $list[] = array_shift($data);
            }
        }
        $table->addRow( makeLinks($list) );
    }
    echo $table->toHTML();
}

function makeLinks( $list ) {
    $linkList = array();
    foreach ($list as $item) {
        $linkList[] = makeLinkForURL("/../adminui/dumpAdminDB.php?table=$item", $item);
    }
    return $linkList;
}

function createTmpFile( $array, $fileName ) {
    $fp = fopen($fileName, 'w'); //NOSONAR
    fwrite($fp, print_r($array[0], true));
    fclose($fp);
}

function getData( $table ) {
    global $AdminDB, $statsDB;

    $output = null;
    $path = "/data/stats/temp/dump_$table.sql";
    if ( $table == 'All' ) {
        $cmd = "mysqldump --no-create-info --compact $AdminDB > $path";
    } else {
        $cmd = "mysqldump --no-create-info --compact $AdminDB $table > $path";
    }
    exec($cmd, $output); //NOSONAR
    return $output;
}

function displayLink( $output, $table ) {
    if ( !is_null($output) ) {
        $fileName = "/data/stats/temp/dump_$table.sql";
        $hyperLink = makeLinkForURL(getUrlForFile($fileName, true), "Download dump_$table.sql");
        echo "<H1>$hyperLink</H1>";
    } else {
        echo "No data returned.";
    }
}

function main() {
    drawHeader('Admin DB Tables', 1, '');
    echo addLineBreak();
    $tables = getTableList();
    drawTable($tables);
}

$table = requestValue('table');
if ( $table ) {
    $output = getData( $table );
    displayLink( $output, $table );
} else {
    main();
}

include_once "../php/common/finalise.php";

