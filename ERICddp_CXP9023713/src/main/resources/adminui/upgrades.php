<?php
include_once "init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function getData($ugDir) {
    global $AdminDB;
    $db = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $db->exec("use $AdminDB");
    $db->query("SELECT * from upgrade_history ORDER BY start_time DESC");
    $data = array();
    while ($row = $db->getNextRow()) {
        $fromTo = "$row[0]-$row[1]";
        $from = substr($row[0], 4);
        $to = substr($row[1], 4);

        if ( file_exists("$ugDir/$fromTo") ) {
            $row[2] = "<a href=?ug=" . $fromTo . ">$row[2]</a>";
        }

        $data[] = array(
            'FROM' => $from,
            'TO' => $to,
            'START' => $row[2],
            'END' => $row[3],
            'STATUS' => $row[4],
            'INITIATOR' => $row[5]
        );
    }
    return $data;
}

function drawTable($data) {
    $table = new DDPTable(
        "Upgrades",
        array(
            array('key' => 'START', DDPTable::LABEL => 'Start Time'),
            array('key' => 'END', DDPTable::LABEL => 'End Time', 'formatter' => 'ddpFormatTime'),
            array('key' => 'FROM', DDPTable::LABEL => 'From'),
            array('key' => 'TO', DDPTable::LABEL => 'To'),
            array('key' => 'STATUS', DDPTable::LABEL => 'Status'),
            array('key' => 'INITIATOR', DDPTable::LABEL => 'Initiator'),
        ),
        array('data' => $data),
        array(
            DDPTable::ROWS_PER_PAGE => 15,
            DDPTable::ROWS_PER_PAGE_OPTIONS => array( 25, 50, 100)
        )
    );
    echo '<H1>Upgrades</H1>';
    echo $table->getTable();
}

function addDir( &$list, $dir ) {
    for ($i = 0; $i < count($list); $i++) {
        $list[$i] = $dir . '/' . $list[$i];
    }
}

function displayFiles( $files ) {
    foreach ($files as $file) {
        if ( is_file($file) ) {
            echo makeLinkForURL(getUrlForFile($file), $file);
            echo '<BR>';
        }
    }
}

function getFiles($toFrom, $ugDir) {
    echo '<H1>Upgrade Files</H1>';
    $ugDir .= "$toFrom";
    $adminDir = "$ugDir/ddpadmin";
    $statsDir = "$ugDir/statsdb";
    $adminFiles = scandir($adminDir);
    addDir($adminFiles, $adminDir);
    displayFiles( $adminFiles );
    $statsFiles = scandir($statsDir);
    addDir($statsFiles, $statsDir);
    displayFiles( $statsFiles );
    $otherFiles = scandir($ugDir);
    addDir($otherFiles, $ugDir);
    displayFiles( $otherFiles );
}

function main($ugDir) {
    $data = getData($ugDir);
    drawTable($data);
}

global $ddp_dir;
$ddp_root = dirname($ddp_dir);
$ugDir = "$ddp_root/upgrade/";

if (isset($_REQUEST['ug'])) {
    getFiles($_REQUEST['ug'], $ugDir);
} else {
    main($ugDir);
}

include_once "../php/common/finalise.php";
