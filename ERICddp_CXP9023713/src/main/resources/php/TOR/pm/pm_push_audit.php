<?php
$pageTitle = "PM PUSH MEDIATION";
include_once "../../common/init.php";
include_once "pmFunctions.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

require_once 'HTML/Table.php';

const TITLE = 'title';
const FILES = 'Files';
const TRANSFER_TYPE = 'transfertype';
const MLO = 'miniLinkOutdoor';

function createSnmpLink($statsDB) {
    global $date, $site, $webargs;
    $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_mspmip_instr, sites
WHERE
 enm_mspmip_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_mspmip_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

    $row2 = $statsDB->queryRow("
SELECT COUNT(*)
FROM enm_pmpolicy_instr, sites
WHERE
 enm_pmpolicy_instr.siteid = sites.id AND sites.name = '$site' AND
 enm_pmpolicy_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");

  if ( $row[0] > 0 || $row2[0] > 0 ) {
      $snmplink = makeLink('/TOR/pm/snmp_pm_instr.php', 'Snmp PM instrumentation');
      echo makeHTMLList(array($snmplink));
  }
}

function createRopStatsLink($statsDB, $transferType) {
    global $date, $site, $webargs;
    $flsRopTypes = array();

    $statsDB->query("SELECT DISTINCT rop,transfertype FROM enm_pmic_rop_fls, sites
WHERE
 enm_pmic_rop_fls.siteid = sites.id AND sites.name = '$site' AND
 enm_pmic_rop_fls.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND transfertype = '$transferType'
ORDER BY rop, transfertype");

    while ( $row = $statsDB->getNextNamedRow() ) {
        $flsRopTypes[] = $row['rop'];
    }

    if ( count($flsRopTypes) > 0 ) {
        echo "<ul>\n";
        echo "<li> ROP Stats\n";
        foreach ( $flsRopTypes as $rop ) {
            if ( $rop == '1440MIN' ) {
               $rop1 = '24 Hour';
            }
            else {
                //Extracting and converting the rop value to specific format like '15 Min'
                $rop1 = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $rop)[0] . " " .
                ucwords(strtolower(preg_split('/(?<=[0-9])(?=[a-z]+)/i', $rop)[1])
                );
            }
            $link = makeAnchorLink( "rop_" . $rop, $rop1 );
            echo makeHTMLList(array($link));
        }
        echo " </li>\n";
        echo "  </ul>\n";
    }
    return $flsRopTypes;
}

function displayROPdata($flsropTimes, $transferType) {
    global $date, $site, $webargs;

    foreach ( $flsropTimes as $rop ) {
        if ( $rop == '1440MIN' ) {
            $rop1 = '24 Hour';
        }
        else {
           $rop1 = $rop;
        }
        echo "<H2 id=\"rop_$rop\">$rop1 ROP</H1>\n";
        $where = "
enm_pmic_rop_fls.siteid = sites.id AND sites.name = '$site' AND
enm_pmic_rop_fls.fcs BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
enm_pmic_rop_fls.rop = '$rop' AND
enm_pmic_rop_fls.transfertype = '$transferType' AND
enm_pmic_rop_fls.netypeid = ne_types.id AND
enm_pmic_rop_fls.datatypeid = enm_pmic_datatypes.id
GROUP BY ne_types.id, enm_pmic_datatypes.id";
        $table = SqlTableBuilder::init()
            ->name("fls_" . $rop."_".$transferType)
            ->tables(array('enm_pmic_rop_fls', 'enm_pmic_datatypes', 'ne_types', StatsDB::SITES))
            ->where($where)
            ->addHiddenColumn('id', 'CONCAT(ne_types.id,":",enm_pmic_datatypes.id)')
            ->addSimpleColumn('ne_types.name', 'NE Type')
            ->addSimpleColumn('enm_pmic_datatypes.name', 'Data Type')
            ->addSimpleColumn('SUM(files)', 'Files')
            ->addSimpleColumn('SUM(volumekb)', 'Stored Volume(KB)')
            ->addSimpleColumn('SUM(outside)', 'File Outside ROP')
            ->ctxMenu(
                "fls",
                "true",
                array( 'plot' => 'Plot' ),
                fromServer(PHP_SELF) . "?" . $webargs . "&rop=$rop&transfertype=$transferType",
                'id'
            )
            ->build();
        echo "<H4>$transferType</H4>\n";
        echo $table->getTable();
    }
}

function getSMRSAuditTable() {
    $table = new ModelledTable(
        "TOR/pm/enm_smrsAudit",
        'smrs_audit',
        array(
            ModelledTable::URL => makeSelfLink(),
        )
    );
    echo $table->getTableWithHeader("SMRS Audit");
}

function getSmrsHousekeepingTable() {
    $table = new ModelledTable(
        "TOR/pm/enm_smrs_housekeeping",
        'smrs_housekeeping'
    );
    echo $table->getTableWithHeader("PM SMRS HouseKeeping");
}

function smrsParams() {
   return array(
       'auditProcessingTime',
       'totalNumberOfDirectoriesScanned',
       'totalNumberOfDetectedFiles',
       'totalNumberOfMTRsSent',
       'totalBytesTransferred'
   );
}

function minilinkOutParams() {

    $mlOut15min = array (
        'ml_outdoor_filesCollected15min',
        'ml_outdoor_filesRecovered15min',
        'ml_outdoor_minProcessingTime15min',
        'ml_outdoor_maxProcessingTime15min',
        'ml_outdoor_minUploadTime15min',
        'ml_outdoor_maxUploadTime15min',
        'ml_outdoor_numberOfEmptyFilePathFailures15min',
        'ml_outdoor_numberOfParsedDataFailures15min',
        'ml_outdoor_numberOfSshConnectionFailures15min',
        'ml_outdoor_numberOfUploadCommandFailures15min',
        'ml_outdoor_overallFilesCollected15min'
    );
    $mlOut24h = array (
        'ml_outdoor_filesCollected24hr',
        'ml_outdoor_filesRecovered24hr',
        'ml_outdoor_minProcessingTime24hr',
        'ml_outdoor_maxProcessingTime24hr',
        'ml_outdoor_minUploadTime24hr',
        'ml_outdoor_maxUploadTime24hr',
        'ml_outdoor_numberOfEmptyFilePathFailures24h',
        'ml_outdoor_numberOfParsedDataFailures24h',
        'ml_outdoor_numberOfSshConnectionFailures24h',
        'ml_outdoor_numberOfUploadCommandFailures24h',
        'ml_outdoor_overallFilesCollected24h'
    );
    return array(
        array ('MiniLink Outdoor Pm File Collection 15min', $mlOut15min, 'mlout15min'),
        array ('MiniLink Outdoor Pm File Collection 24h', $mlOut24h, 'mlout24hr')
    );
}

function miniLinkOutdoor() {

    $links = array();
    $links[] = makeAnchorLink('mlout15min', 'MiniLink Outdoor Pm File Collection 15min');
    $links[] = makeAnchorLink('mlout24hr', 'MiniLink Outdoor Pm File Collection 24h');

    echo makeHTMLList($links);

    $params = minilinkOutParams();

    foreach ( $params as $param ) {
        $graphs = array();

        $secTitle = $param[0];
        $graphParams = $param[1];
        $help = $param[2];
        drawHeader($secTitle, 2, $help);

        if ( $graphParams ) {
            foreach ( $graphParams as $graphParam ) {
                $modelledGraph = new ModelledGraph( 'TOR/pm/' . $graphParam);
                $graphs[] = $modelledGraph->getImage();
            }
            plotgraphs( $graphs );
        }
     }
}

function plotSMRS($selected, $smrsParams) {
    $params = array( 'nodetypeid' => $selected );
    drawHeader("SMRS Audit", 2, "smrsAuditGraph");
    $graphs = array();
    foreach ( $smrsParams as $col ) {
        $modelledGraph = new ModelledGraph("TOR/pm/smrs_" . $col);
        $graphs[] = $modelledGraph->getImage($params);
    }
    plotgraphs($graphs);
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site;
    createSnmpLink($statsDB);
    $transferType = requestValue(TRANSFER_TYPE);
    if ( $statsDB->hasData( "enm_mspmip_instr" ) ) {
        $snmplink = makeLink(
            '/TOR/pm/minilink_pm_instr.php',
            'MINI-LINK Indoor',
            array(TRANSFER_TYPE => "$transferType")
        );
        $mloutdoorLink = makeLink(
            '/TOR/pm/pm_push_audit.php',
            'MINI-LINK Outdoor',
            array(MLO => "1")
        );
        echo makeHTMLList(array($snmplink, $mloutdoorLink));
    }
    $flsropTimes = createRopStatsLink($statsDB, $transferType);
    if ( $transferType == 'PUSH' ) {
        getSMRSAuditTable();
    }
    getSmrsHousekeepingTable();
    displayROPdata($flsropTimes, $transferType);

}

$statsDB = new StatsDB();
$selected = requestValue('selected');

if ( issetURLParam('fls') ) {
    plotFls(requestValue('rop'), requestValue('transfertype'), requestValue('selected'));
} elseif ( $selected ) {
    plotSMRS($selected, smrsParams());
} elseif (issetURLParam(MLO)) {
    miniLinkOutdoor();
} else {
    mainFlow($statsDB);
}
include_once PHP_ROOT . "/common/finalise.php";
