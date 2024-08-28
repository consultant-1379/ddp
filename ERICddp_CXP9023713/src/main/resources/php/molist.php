<?php
$pageTitle = "Managed Object List";
$YUI_DATATABLE = true;
include "common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";

function getEnmMoRatioAnalysisTable($vdbName,$mdl,$mos_to_be_filtered,$live_cell_count_per_mdl) {

    global $date,$site;

       if ( $live_cell_count_per_mdl > 0 ) {
            $cols = array(
                    array('key' => 'model_name', 'db' => 'model_names.name', 'label' => 'Model Name'),
                    array('key' => 'mo_name', 'db' => 'mo_names.name', 'label' => 'MO Name'),
                    array('key' => 'live_count', 'db' => 'IFNULL(mo.count - mo.planned, "")', 'label' => 'Live MO Count', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'mo_ratio', 'db' => "TRUNCATE(IFNULL(mo.count - mo.planned, \"\")/$live_cell_count_per_mdl,2)", 'label' => 'Live MO Count / Live cell count', 'formatter' => 'ddpFormatNumber')
                );
        } else {

            $cols = array(
                    array('key' => 'model_name', 'db' => 'model_names.name', 'label' => 'Model Name'),
                    array('key' => 'mo_name', 'db' => 'mo_names.name', 'label' => 'MO Name'),
                    array('key' => 'live_count', 'db' => 'IFNULL(mo.count - mo.planned, "")', 'label' => 'Live MO Count', 'formatter' => 'ddpFormatNumber'),
                    array('key' => 'mo_ratio', 'db' => '""', 'label' => 'Live MO Count / Live cell count', 'formatter' => 'ddpFormatNumber')
            );
        }

        $where = " mo.moid = mo_names.id AND
mo_names.name IN ($mos_to_be_filtered) AND
mo.modelid = model_names.id AND
model_names.name = '$mdl' AND
mo.vdbid=vdb_names.id AND
vdb_names.name='$vdbName' AND
mo.siteid = sites.id AND sites.name = '$site' AND
mo.date = '$date'";

        $enmMoRatio = new SqlTable("enmMoRatiofor$mdl",
                                $cols,
                                array('mo', 'mo_names', 'model_names', 'sites', 'vdb_names'),
                                $where,
                                TRUE,
                                array('rowsPerPage' => 20,
                                'rowsPerPageOptions' => array(50, 100, 1000, 10000)
                                )
                           );
    echo $enmMoRatio->getTableWithHeader("MO Ratio Analysis for $mdl", 1, "", "", "ENM_Mo_Ratio_Analysis");
}

function getMOListTable($statsDB, $hasNonLive, $vdbId, $vdbName)
{
        global $site, $date, $debug;
        if ($debug) {
            echo "<pre>hasNonLive=$hasNonLive</pre>\n";
        }
        if ($hasNonLive) {
            $cols = array(
                array('key' => 'model_name', 'db' => 'model_names.name', 'label' => 'Model Name'),
                array('key' => 'mo_name', 'db' => 'mo_names.name', 'label' => 'MO Name'),
                array('key' => 'live_count', 'db' => 'IFNULL(mo.count - mo.planned, mo.count)', 'label' => 'Live MO Count'),
                array('key' => 'nonlive_count', 'db' => 'mo.planned', 'label' => 'Non-Live MO Count')
            );
        } else {
            $cols = array(
                array('key' => 'model_name', 'db' => 'model_names.name', 'label' => 'Model Name'),
                array('key' => 'mo_name', 'db' => 'mo_names.name', 'label' => 'MO Name'),
                array('key' => 'live_count', 'db' => 'IFNULL(mo.count - mo.planned, mo.count)', 'label' => 'Live MO Count')
            );
        }

        $where = "mo.date = '$date' AND sites.name = '$site' AND mo.siteid = sites.id AND mo.vdbid = $vdbId AND mo.modelid = model_names.id AND mo.moid = mo_names.id ORDER BY model_name ASC, mo_name ASC";

        $table = new SqlTable("MO_List_Table",
            $cols,
            array('model_names', 'mo_names', 'mo', 'sites'),
            $where,
            TRUE,
            array(
                'rowsPerPage' => 100,
                'rowsPerPageOptions' => array(500, 1000, 10000)
            )
        );
        echo $table->getTableWithHeader("$vdbName", 1, "", "", "MO_List_Table");
}

function getMOSummary($statsDB, $vdbId)
{
    global $site, $date;
    $cols = array(
        array( 'key' => 'modelname', 'db' => 'IFNULL(model_names.name,"Total")', 'label' => 'Model Name'),
        array( 'key' => 'count', 'db' => 'SUM(IFNULL(mo.count - mo.planned, mo.count))', 'label' => 'Model Count' )
    );

    $where = "mo.date = '$date' AND sites.name = '$site' AND mo.siteid = sites.id AND mo.vdbid = $vdbId AND mo.modelid = model_names.id AND mo.moid = mo_names.id GROUP BY model_names.name with ROLLUP";

    $table = new SqlTable("MO_Summary",
        $cols,
        array( 'model_names', 'mo_names', 'mo', 'sites'),
        $where,
        TRUE,
        array(
            'rowsPerPage' => 50,
            'rowsPerPageOptions' => array(100,500, 1000, 10000)
        )
    );
    echo $table->getTableWithHeader("MO Summary", 1, "", "", "MO_Summary");
}

function getMOTable($statsDB, $vdbId, $vdbName) {
    global $date,$site;
    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM mo, sites
WHERE
 mo.date = '$date' AND
 sites.name = '$site' AND mo.siteid = sites.id AND
 mo.vdbid = $vdbId AND
 mo.planned IS NOT NULL");
    getMOListTable($statsDB, $row[0] > 0, $vdbId, $vdbName);
}

function getGenericManagedObjectListTable($statsDB, $vdbId) {
    global $date, $site;
    $table = new HTML_Table("border=1");

    $sqlquery = "
        SELECT
           mo_names.name, IFNULL(mo.count - mo.planned, mo.count), mo.planned, model_names.name, mim_names.name
        FROM
           model_names, mim_names, mo_names, mo, sites
        WHERE
           mo.date = '$date' AND
           sites.name = '$site' AND mo.siteid = sites.id AND
           mo.vdbid = $vdbId AND
           mo.modelid = model_names.id AND
           mo.mimid = mim_names.id AND
           mo.moid = mo_names.id
        ORDER BY
           model_names.name, mim_names.name, mo_names.name
    ";

    $statsDB->query($sqlquery);

    $firstRow = true;
    $hasMim = false;
    $hasPlanned = true;
    while($row = $statsDB->getNextRow()) {
      if ( $firstRow == true ) {
        $firstRow = false;

        if ( $row[2] == '' ) {
          $hasPlanned = false;
          $headerRow = array( 'MO', 'Count', 'Model' );
        } else {
          $headerRow = array( 'MO', 'Valid', 'Planned', 'Model' );
        }

        if ( $row[4] != '' ) {
          $hasMim = true;
          $headerRow[] = 'MIM';
        }

        $table->addRow( $headerRow, null, 'th' );
      }

      if ( $hasMim == false ) {
        unset($row[4]);
        $row = array_values($row);
      }

      if ( $hasPlanned == false ) {
        unset($row[2]);
        $row = array_values($row);
      }

      $table->addRow($row);
    }
    echo $table->toHTML();
}

function mainFlow( $statsDB ) {
    global $date, $site;
    $oss = $_GET["oss"];
    $vdbId = $_GET["vdbid"];
    $vdbName = $_GET["vdbName"];

    if ($oss == "tor") {
        echo "<ul>";
        echo "<li><a href=\"#mo_summary\">MO Summary</a></li>";
        echo "<li><a href=\"#mo_ratio_analysis\">MO Ratio Analysis</a></li>";
        echo "</ul>";
        echo "<a name=\"dps_integration\"></a>";
        getMOTable($statsDB, $vdbId, $vdbName);
        echo "<br/>";
        echo "<a name=\"mo_summary\"></a>";
        getMOSummary($statsDB, $vdbId);
        echo "<br/>";
        echo "<a name=\"mo_ratio_analysis\"></a>";

        $models = array("ERBS_NODE_MODEL", "Lrat", "RNC_NODE_MODEL");
        foreach ($models as $mdl) {
            if ($mdl === "ERBS_NODE_MODEL" || $mdl === "Lrat") {
                $mos_to_be_filtered = "'EUtranCellFDD','EUtranCellTDD','EUtranCellRelation','EUtranFreqRelation','ExternalENodeBFunction','ExternalEUtranCellFDD','GeranCellRelation','GeranFreqGroupRelation','GeranFrequency','RetSubUnit','SectorCarrier','TermPointToENB'";
                $row = $statsDB->queryRow("SELECT SUM(IFNULL( mo.count - mo.planned, \"\")) AS livecellcount FROM mo,sites,mo_names,model_names,vdb_names WHERE mo.date = '$date' AND mo.siteid = sites.id AND sites.name = '$site' AND mo.moid = mo_names.id AND mo_names.name IN ('EUtranCellFDD','EUtranCellTDD') AND mo.modelid = model_names.id AND mo.vdbid = vdb_names.id AND model_names.name = '$mdl' AND vdb_names.name = '$vdbName'");
                if ($row[0] > 0 || $row[0] === "") {
                    $live_cell_count_per_mdl = $row[0];
                    $enmMoRatioTable = getEnmMoRatioAnalysisTable($vdbName, $mdl, $mos_to_be_filtered, $live_cell_count_per_mdl);
                }
            } elseif ($mdl === "RNC_NODE_MODEL") {
                $mos_to_be_filtered = "'UtranCell','CoverageRelation','EutranFreqRelation','GsmRelation','UtranRelation'";
                $row = $statsDB->queryRow("SELECT IFNULL( mo.count - mo.planned, \"\") AS livecellcount FROM mo,sites,mo_names,model_names,vdb_names WHERE mo.date = '$date' AND mo.siteid = sites.id AND sites.name = '$site' AND mo.moid = mo_names.id AND mo_names.name = 'UtranCell' AND mo.modelid = model_names.id AND mo.vdbid = vdb_names.id AND model_names.name = '$mdl' AND vdb_names.name = '$vdbName'");
                if ($row[0] > 0 || $row[0] === "") {
                    $live_cell_count_per_mdl = $row[0];
                    $enmMoRatioTable = getEnmMoRatioAnalysisTable($vdbName, $mdl, $mos_to_be_filtered, $live_cell_count_per_mdl);
                }
            }
        }
    } else {
        getGenericManagedObjectListTable($statsDB, $vdbId);
    }
}
$statsDB = new StatsDB();
mainFlow( $statsDB );
include "common/finalise.php";
?>
