<?php
$pageTitle = "NEAD Statistics";

/* Disable the UI for non-main flow */
if (isset($_GET["getdata"]) || isset($_REQUEST['action'])) {
    $UI = false;
}

$YUI_DATATABLE = true;

include "common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/Jms.php";
require_once PHP_ROOT . "/classes/Routes.php";

require_once 'HTML/Table.php';
$display = 'none';

function getNotifRecTables($statsDB,$site,$date)
{
    global $webargs;

  $row = $statsDB->queryRow("
SELECT COUNT(*)
 FROM nead_notifrec, sites
 WHERE
   nead_notifrec.date = '$date' AND
   nead_notifrec.siteid = sites.id AND sites.name = '$site'
");
  if ( $row[0] == 0 ) {
    return NULL;
  }

  return array(
           new SqlTable("notif_rec_details",
                array(
                  array( 'key' => 'id', 'visible' => false, 'db' => 'CONCAT(nead_notifrec.eventtype,":",mo_names.name,":",nead_attrib_names.name)'),
                  array( 'key' => 'eventtype', 'label' => 'Event Type' ),
                  array( 'key' => 'nodetype', 'label' => 'Node Type' ),
                  array( 'key' => 'mo', 'db' => 'mo_names.name', 'label' => 'MO' ),
                  array( 'key' => 'attrib', 'db' => 'nead_attrib_names.name', 'label' => 'Attribute' ),
                  array( 'key' => 'count', 'label' => 'Count' )
                  ),
                array( 'nead_notifrec', 'mo_names', 'sites', 'nead_attrib_names' ),
                "nead_notifrec.date = '$date' AND nead_notifrec.siteid = sites.id AND sites.name = '$site' AND nead_notifrec.moid = mo_names.id AND nead_notifrec.attribid = nead_attrib_names.id",
                TRUE,
                array( 'order' => array( 'by' => 'count', 'dir' => 'DESC'),
                       'rowsPerPage' => 25,
                       'rowsPerPageOptions' => array(50, 100, 1000, 10000),
                       'ctxMenu' => array('key' => 'action',
                                          'multi' => true,
                                          'menu' => array( 'plotnotifrec' => 'Plot for last month'),
                                          'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                          'col' => 'id')
                   )
                ),
           new SqlTable("notif_top_details",
                array(
                  array( 'key' => 'id', 'db' => 'ne.id', 'visible' => false ),
                  array( 'key' => 'name', 'db' => 'ne.name', 'label' => 'Network Element' ),
                  array( 'key' => 'count', 'db' => 'nead_notiftop.count', 'label' => 'Count' ),
                  ),
                array( 'nead_notiftop', 'ne', 'sites' ),
                "nead_notiftop.date = '$date' AND nead_notiftop.siteid = sites.id AND sites.name = '$site' AND nead_notiftop.neid = ne.id AND nead_notiftop.count > 1000",
                TRUE,
                array( 'order' => array( 'by' => 'count', 'dir' => 'DESC'),
                       'rowsPerPage' => 25,
                       'rowsPerPageOptions' => array(50, 100, 1000, 10000),
                       'ctxMenu' => array('key' => 'action',
                                          'multi' => true,
                                          'menu' => array( 'plotnotiftop' => 'Plot for last month'),
                                          'url' => $_SERVER['PHP_SELF'] . "?" . $webargs,
                                          'col' => 'id')
                   )
                ),
           );
}

function plotNotifRec($statsDB,$site,$date,$selectedStr) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
nead_notifrec.siteid = sites.id AND sites.name = '%s' AND
nead_notifrec.eventtype = '%s' AND
nead_notifrec.moid = mo_names.id AND mo_names.name = '%s' AND
nead_notifrec.attribid = nead_attrib_names.id AND nead_attrib_names.name = '%s'
";
    $queryList = array();
    foreach ( explode(",",$selectedStr) as $selected ) {
        $selectedParts = explode(":",$selected);
        $queryList[] = array(
            'timecol' => 'date',
            'whatcol' => array( 'count' => $selected ),
            'tables' => "nead_notifrec, mo_names, sites, nead_attrib_names",
            'where' => sprintf($where,$site,$selectedParts[0],$selectedParts[1],$selectedParts[2])
        );
    }

    $sqlParam = array(
        'title' => "Notifications Received",
        'type' => 'tsc',
        'ylabel' => "#Notifications",
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' => $queryList
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59"));
}

function plotNotifTop($statsDB,$site,$date,$selectedStr) {
    $fromDate=date('Y-m-d', strtotime($date.'-1 month'));
    $where = "
nead_notiftop.siteid = sites.id AND sites.name = '%s' AND
nead_notiftop.neid = ne.id AND ne.id IN ( %s )";

    $sqlParam = array(
        'title' => "Top Notification Nodes",
        'type' => 'tsc',
        'ylabel' => "#Notifications",
        'useragg' => 'true',
        'persistent' => 'false',
        'querylist' => array(
            array(
                'timecol' => 'date',
                'multiseries'=> 'ne.name',
                'whatcol' => array( 'count' => '#Notifications' ),
                'tables' => "nead_notiftop, ne, sites",
                'where' => $where,
                'qargs'   => array( 'site', 'neids' )
            )
        )
    );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    header("Location:" .  $sqlParamWriter->getURL($id, "$fromDate 00:00:00", "$date 23:59:59","neids=$selectedStr"));
}

function mainFlow($statsDB) {
    global $debug, $webargs, $php_webroot, $date, $site, $rootdir;
$notifRecTables = getNotifRecTables($statsDB,$site,$date);
$neadURL = $php_webroot . "/nead.php?$webargs";
echo "<a href=\"$neadURL\">Go back to previous page</a>\n";
    if ( ! is_null($notifRecTables) ) {
      echo $notifRecTables[0]->getTableWithHeader("Notifications Received", 1, "DDP_Bubble_77_OSS_NEAD_Notifications");
      echo $notifRecTables[1]->getTableWithHeader("Top Notification Nodes", 1, "DDP_Bubble_77_OSS_Top_Notifications");
    }
}

$statsDB = new StatsDB();

if (isset($_GET['getdata'])) {
    getData($statsDB);
} else {
     if (isset($_REQUEST['action'])) {
        if ( $_REQUEST['action'] === 'plotnotifrec' ) {
            plotNotifRec($statsDB,$site,$date,$_REQUEST['selected']);
        } elseif ( $_REQUEST['action'] === 'plotnotiftop' ) {
            plotNotifTop($statsDB,$site,$date,$_REQUEST['selected']);
        }
    } else {
        mainFlow($statsDB);
    }
    include PHP_ROOT . "/common/finalise.php";
}
?>
