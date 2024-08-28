<?php

include "init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

function replicationStatus() {
    global $ddp_dir;

    $replArr = array();

    callGenericPhpToRootWrapper( 'updaterepl', '-c', '/data/tmp/updatereplstatus.txt' );

    $filecontents = shell_exec("cat /data/tmp/updatereplstatus.txt");
    $fileArr = array_filter(explode("\n", $filecontents));

    foreach ( $fileArr as $lines_array ) {
        if ( preg_match('/Replication Status for ([^\s]+) (.+)$/', $lines_array, $matches) ) {
            array_push( $replArr, array($matches[1], $matches[2]) );
        }
    }

    $statuscmd = "ps -ef | grep 'sh .*[u]pdateRepl'";
    $result = exec($statuscmd);

    if(preg_match("/updateRepl/", $result)){
        $replRunning = true;
    } else {
        $replRunning = false;
    }

    $rows = array();
    foreach ( $replArr as $repl ) {
        if ( $replRunning ) {
            $rows[] = array($repl[0], "REPLICATION UPDATE IN PROGRESS...");
        } else {
            $rows[] = array( $repl[0], strtoupper($repl[1]) );
        }
    }

    if ( $rows ) {
        $table = new DDPTable(
            "repl",
            array(
                array('key' => 'Status', DDPTable::LABEL => 'Replication Status'),
                array('key' => 'Info', DDPTable::LABEL => 'Info'),
            ),
            array('data' => $rows)
        );
        echo $table->getTableWithHeader("Database Replicas:");
        echo addLineBreak();
    }
}

function buildTable( $name, $tables, $where, $cols, $heading ) {
    $tableBuilder = SqlTableBuilder::init()
        ->name($name)
        ->tables( $tables )
        ->where( $where );

    foreach ( $cols as $db => $lbl ) {
        $tableBuilder->addSimpleColumn($db, $lbl);
    }
    echo $tableBuilder->build()->getTableWithHeader($heading);
}

function filesStatus( $type ) {
    global $AdminDB;

    $tables = array( $AdminDB . ".file_processing" );

    if ( $type == 'wait' ) {
        $name = 'waiting';
        $cols = array('site' => 'Site', 'date' => 'Date', 'TIMEDIFF(NOW(),uploaded)' => 'Waiting');
        $where = "starttime IS NULL ORDER BY date DESC, uploaded";
        $heading = 'Files waiting to process: ';
    } elseif ( $type == 'proc' ) {
        $name = 'running';
        $cols = array('site' => 'Site', 'date' => 'Date', 'TIMEDIFF(NOW(),starttime)' => 'Processing Run Time');
        $where = "starttime IS NOT NULL ORDER BY starttime";
        $heading = 'Files currently processing:';
    } else {
        return;
    }
    buildTable( $name, $tables, $where, $cols, $heading );
}

function retention() {
    global $stats_dir, $debug;

    $config = array();
    $contents = file_get_contents($stats_dir . "/config"); //NOSONAR
    $configLines = explode("\n", $contents);

    foreach ( $configLines as $config_line ) {
        if ( preg_match("/^export\s+(\S+)=(\S+)/", $config_line, $parts) ) {
            $config[$parts[1]] = $parts[2];
        }
    }
    if ( $debug ) {
        debugMsg("CONFIG: $config");
    }

    $retentionRows = array();
    $fileRetention = 0;
    $retentionUnit = 'Months';
    foreach ( array('MONTHS_TO_KEEP', 'MONTHS_TO_ARCHIVE') as $key ) {
        if ( array_key_exists($key, $config) ) {
            $fileRetention = $fileRetention + $config[$key];
        }
    }
    foreach ( array('DAYS_TO_KEEP', 'DAYS_TO_ARCHIVE') as $key ) {
        if ( array_key_exists($key, $config) ) {
            $retentionUnit = 'Days';
            $fileRetention = $fileRetention + $config[$key];
        }
    }

    if ( $fileRetention > 0 ) {
        $retentionRows[] = array("Files", sprintf("%d %s", $fileRetention, $retentionUnit));
    }
    if ( array_key_exists("DB_MONTHS_TO_KEEP", $config) ) {
        $retentionRows[] = array("Database", sprintf("%d Months", $config["DB_MONTHS_TO_KEEP"]));
    }
    if ( count($retentionRows) > 0 ) {

        drawHeader("Data Retention Limits:", 2, "ddpServiceDataRentention");
        $table = new DDPTable(
            "retention",
            array(
                array('key' => 'Type', DDPTable::LABEL => 'Data Type'),
                array('key' => 'Limit', DDPTable::LABEL => 'Retention Limit'),
            ),
            array('data' => $retentionRows)
        );

        echo $table->getTable();
        echo addLineBreak();
    }
}

function isDDPRunning() {
    global $debug;

    exec("/bin/systemctl show ddpd.service --property=ActiveState", $output); //NOSONAR

    if ( $debug ) {
        debugMsg("/bin/systemctl show ddpd.service --property=ActiveState : $output");
    }

    if ( $output[0] == "ActiveState=active" ) {
        return 1;
    }
    return false;
}

function isUpgradeRunning() {
    $statuscmd = "ps -ef | grep -i [u]pgrade | grep [s]tatsadm | grep [s]udo";
    $result = exec($statuscmd);
    if(preg_match("/upgrade/", $result)){
        $resultArr = preg_split('/\s+/', $result);
        return $resultArr[1];
    }
    return false;
}

function checkProcess( $proc ) {
    exec("pgrep -u statsadm -f \"/data/ddp/.*/analysis/main/$proc\"", $pids); //NOSONAR

    if ($pids) {
        return 1;
    }
    return false;
}

function main() {
    $ddpRunning = isDDPRunning();
    $maintenanceRunning = checkProcess( "maintenance start" );
    $upgradeRunning = isUpgradeRunning();

    drawHeader('Service Status:', 2, 'ddpStatus');
    if ( $ddpRunning ) {
        if ( $maintenanceRunning ) {
            echo "Stopping for maintenance";
        } elseif ( $upgradeRunning ) {
            echo "Stopping for upgrade";
        } else {
            echo "Running";
        }
    } else {
        if ( $maintenanceRunning ) {
            echo "Stopped for maintenance";
        } elseif ( $upgradeRunning ) {
            echo "Stopped for upgrade";
        } else {
            echo "Stopped";
        }
    }
    echo addLineBreak();

    retention();
    replicationStatus();
    filesStatus( 'proc' );
    filesStatus( 'wait' );
}

main();

include "../php/common/finalise.php";

