<?php

function getGenJmxTree($showAllPlots = false) {
    $genjmxHTML = "";
    $jvmInstances = getGenJmxJvms();
    if ( count($jvmInstances) > 0 ) {
        $sortFunction = function($a, $b) {
            $hostCompare = strcmp($a['servername'], $b['servername']);
            if ( $hostCompare == 0 ) {
                return strcmp($a['jvmname'], $b['jvmname']);
            } else {
                return $hostCompare;
            }
        };
        usort($jvmInstances, $sortFunction);
        debugMsg("getGenJmxTree: jvmInstances", $jvmInstances);
        $genjmxHTML =  getGenJmxTreeSub($showAllPlots, $jvmInstances);
    }

    return $genjmxHTML;
}

function getGenJmxTreeSub($showAllPlots, $jvmInstances) {
    global $statsDB;
    $genjmxHTML = "General JMX \n";
    $currentServer = "";
    $items = array();
    $subitems = array();
    $subsubitems = array();
    foreach ($jvmInstances as $jvmInstance) {
        $row = array($jvmInstance['servername'], $jvmInstance['jvmname']);
        $server = $row[0];
        // Match a server which contains svc in it's name e.g. svc-1-cmserv
        // and strip away the jmx name (cmserv) to leave only svc-1
        if ( strpos($row[0], 'svc') !== false ) {
            $row[0] = substr($row[0], 0, 5);
        }
        elseif ( strpos($row[0], 'scp')) {
            $row[1] = $row[0];
        }
        elseif ( strpos($row[0], 'evt')) {
            $row[1] = substr($row[0], 6);
            $value = "eventcluster";
            $row[0] = "$value-"  . substr($row[0], 4, 1);
        }
        if ( (! is_null($row[0])) && ($row[0] != $currentServer) ) {
            if ( $currentServer != "" ) {
                $subitems[] = $currentServer . makeHTMLList($subsubitems);
                $subsubitems = array();
            }
            $currentServer = $row[0];
        }
        $subsubitems[] = makeLink( '/genjmx.php', " $row[1]", array('server' => $server, 'name' => $row[1] ) );
    }
    if ( !empty($currentServer) ) {
        // Close the last server list
        $subitems[] = $currentServer . makeHTMLList($subsubitems);
        $genjmxHTML .= makeHTMLList($subitems);
    }
    if ( $showAllPlots ) {
        $genJmxTypes = array(
                           'Total GC Time' => 'total_gc_time'
                       );
        foreach ( $genJmxTypes as $key => $value ) {
            $items[] = makeLink(
                '/genjmx.php',
                "{$key} - All Plots",
                array('showallplots' => '1','type' => "$value")
            );
            $genjmxHTML .= makeHTMLList($items);
        }
    }
    return $genjmxHTML;
}
