<?php
$pageTitle = "JMX Data";

include "common/init.php";
include "classes/GenericJMX.php";
require_once PHP_ROOT . "/StatsDB.php";

$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
    $fromDate = $_GET['start'];
    $toDate = $_GET['end'];
} else {
    $fromDate = $date;
    $toDate = $date;
}

if ( isset($_GET['name']) ) {
    $name = $_GET['name'];
    drawHeaderWithHelp("JMX Data for $name", 2, "dailyApplicationNotificationTotalsHelp", "DDP_Bubble_194_Generic_JMX_Help");

    if ( isset($_GET['server']) ) {
        $server = $_GET['server'];
    } else {
        $server = "NULL";
    }

    $genJMX = new GenericJMX($statsDB, $site, $server, $name, $fromDate, $toDate);
    $graphTable = $genJMX->getGraphTable();
    echo $graphTable->toHTML();
} else if ( isset($_GET['names']) ) {
    $graphTable = new HTML_Table('border=0');
    $serverNameArray = explode(";",$_GET['names']);
    foreach ( $serverNameArray as $serverName ) {
        list($server,$name) = explode(",",$serverName);
        $genJMX = new GenericJMX($statsDB, $site, $server, $name, $fromDate, $toDate);
        $graphs = $genJMX->getGraphArray();
        array_unshift($graphs,"<b>$name on $server</b>");
        $graphTable->addCol($graphs);
    }
    drawHeaderWithHelp("JMX Data for $name", 2, "bubble for service cluster table content in cluster page", "DDP_Bubble_194_Generic_JMX_Help");
    echo $graphTable->toHTML();
} else if ( isset($_GET['showallplots']) ) {
    # Show all the plots for the given type (eg: 'Total GC Time') of generic JMX data
    $type = "";
    if ( isset($_GET['type']) ) {
        if ( $_GET['type'] == 'total_gc_time' ) {
            $type = $_GET['type'];
        } else {
            echo "<H2>Show All Plots: Unknown 'type' of generic JMX data found in URL - '{$_GET['type']}'</H2>\n";
        }
    } else {
        echo "<H2>Show All Plots:  Please specify the type of generic JMX data to be plotted.</H2>\n";
    }

    $statsDB->query("
SELECT
    servers.hostname,
    jmx_names.name
FROM
    generic_jmx_stats,
    jmx_names,
    servers,
    sites
WHERE
    generic_jmx_stats.siteid = sites.id AND
    generic_jmx_stats.serverid = servers.id AND
    generic_jmx_stats.nameid = jmx_names.id AND
    sites.name = '$site' AND
    generic_jmx_stats.time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
GROUP BY servers.hostname, jmx_names.name
ORDER BY servers.hostname, jmx_names.name");

    $previousServer = '';
    while ( $row = $statsDB->getNextRow() ) {
        if ( valueExists($row[0]) && $row[0] != $previousServer ) {
            if ( valueExists($previousServer) ) {
                echo "<br/>\n";
            }
            $previousServer = $row[0];
            echo "<H1>{$row[0]}</H1>\n";
        }

        if ( valueExists($row[1]) ) {
            drawHeaderWithHelp($row[1], 2, "totalGcTimeHelp", "DDP_Bubble_405_Gen_JMX_Total_GC_Time");
            $genJMX = new GenericJMX($statsDB, $site, $row[0], $row[1], $fromDate, $toDate);
            if ( $type == 'total_gc_time' ) {
                $gcPlot = $genJMX->getQPlot("Total GC Time", "Time (millisec)",
                                            array(
                                                  'gc_youngtime' => 'Young Generation',
                                                  'gc_oldtime' => 'Old Generation'
                                                  ),
                                            'sb');
                echo $gcPlot;
            }
        }
    }
} else {
    echo "<H1>Missing variable - Please contact the System Administrator</H1>\n";
}

include "common/finalise.php";
?>
