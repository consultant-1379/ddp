<?php
require_once "TOR/util.php";

function mainFlow() {
    global $site, $oss, $statsDB;

    drawHeader("Site Overview: $site", 1, '');

    if ($oss == "oss" ) {
        $sql = "
SELECT
    oss_ver.date AS date,
    oss_ver_names.name AS name
FROM
    oss_ver,
    oss_ver_names,
    sites
WHERE
    oss_ver.siteid = sites.id AND
    oss_ver.verid = oss_ver_names.id AND
    sites.name = '$site'
ORDER BY oss_ver.date";
    } elseif ($oss == "tor" ) {
        //date is needed to exclue times when we has the incorrect data in this field
        $sql = "
SELECT
    tor_ver.date AS date,
    tor_ver_names.name AS name
FROM
    tor_ver,
    tor_ver_names,
    sites
WHERE
    tor_ver.siteid = sites.id AND
    tor_ver.verid = tor_ver_names.id AND
    sites.name = '$site' AND
    date > '2015-09-08'
ORDER BY tor_ver.date";
    } elseif ($oss == "eniq" ) {
        $sql = "
SELECT
    eniq_ver.date AS date,
    eniq_ver_names.name AS name
FROM
    eniq_ver,
    eniq_ver_names,
    sites
WHERE
    eniq_ver.siteid = sites.id AND
    eniq_ver.verid = eniq_ver_names.id AND
    sites.name = '$site'
ORDER BY eniq_ver.date";
    } elseif ($oss == "eo" ) {
        $sql = "
SELECT
    eo_ver.date AS date,
    eo_ver_names.name AS name
FROM
    eo_ver,
    eo_ver_names,
    sites
WHERE
    eo_ver.siteid = sites.id AND
    eo_ver.verid = eo_ver_names.id AND
    sites.name = '$site'
ORDER BY
    eo_ver.date";
    } else {
        return;
    }

    $statsDB->query($sql);
    // Sort the versions into an array of version instances with a version text, start, and end
    $instances = array();

    while ($row = $statsDB->getNextNamedRow()) {
        $data = array(
            "version" => $row['name'],
            "start" => $row['date'],
            "end" => $row['date']
        );

        if ( count($instances) == 0  ) {
            // initialisation Case
            $instances[0]= $data;
        } else if (!strcmp( $row['name'], $instances[ ( count($instances) - 1 ) ]["version"] )) {
            // Version name of this row same as 'latest' instance
            $instances[ (count($instances) - 1) ]["end"] = $row['date'];
        }
        else {
            // Version change
            array_push($instances, $data);
        }
    }

    if ($oss == "oss" ) {
        drawHeaderWithHelp( "OSS Revision History", 2, "revisionHistoryHelp", "DDP_Bubble_446_OSS_Site_Overview" );
    } elseif ($oss == "tor" ) {
        drawHeaderWithHelp( "Revision History", 2, "revisionHistoryHelp", "DDP_Bubble_444_ENM_Site_Overview" );
    } elseif ($oss == "eniq" ) {
        drawHeaderWithHelp( "ENIQ Revision History", 2, "revisionHistoryHelp", "DDP_Bubble_447_ENIQ_Site_Overview" );
    } elseif ($oss == "eo" ) {
        drawHeaderWithHelp( "EO Revision History", 2, "revisionHistoryHelp", "DDP_Bubble_447_EO_Site_Overview" );
    }

    $instances = array_reverse($instances, true);
    echo "<table><tr><th>From</th><th>To</th><th>Version</th></tr>\n";
    foreach ($instances as $instance => $values) {
        if ($oss == "tor") {
            $values["version"] = formatTorVersion($values["version"]);
        }
        $output = "<tr><td>" . $values["start"] . "</td><td>" . $values["end"] . "</td><td>" . $values["version"];
        $output .= "</td></tr>\n";
        echo $output;
    }
    echo "</table>\n";

    if ($oss == "oss" ) {
        echo "<h2>Master Server</h2>";
        if (isset($cal)) {
            $latestDir = $cal->getLatestDir();
            if (valueExists($latestDir)) {
                $latestDate = str_split($latestDir, 2);
                $hwFile = $rootdir_base . "/" . $latestDir . "/server/hw.html";
                if (file_exists($hwFile)) {
                    $dt = date( "Y-m-d", mktime( 0, 0, 0, $latestDate[1], $latestDate[0], $latestDate[2] ) );
                    drawHeader("Configuration on $dt", 3, '');
                    echo "<table>\n";
                    include $hwFile;
                    echo "</table>\n";
                }
            }
        }
    }
}

mainFlow();

