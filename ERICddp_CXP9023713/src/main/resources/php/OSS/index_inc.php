<?php

require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/common/indexFunctions.php";

const CLOSE_UL_LI_TAGS = "</ul></li>\n";

/* Give an overview of MC restarts - get the number of each type of restart */
class RestartCounts extends DDPObject {
  var $cols = array (
      "type" => "Restart Type",
      "num" => "Count"
  );

  var $showHeader = false;

  function __construct() {
    parent::__construct("restart_counts");
  }

  function getData() {
    global $date, $site;
    $sql = "SELECT COUNT(*) AS num, mc_restart_types.type AS type
            FROM mc_restarts, mc_restart_types, sites WHERE
            mc_restarts.siteid = sites.id AND
            sites.name = '" . $site . "' AND
            mc_restarts.time >= '" . $date . " 00:00:00' AND
            mc_restarts.time <= '" . $date . " 23:59:59' AND
            mc_restarts.typeid = mc_restart_types.id AND
            mc_restart_types.type <> 'SYSTEM_SHUTDOWN' AND
            mc_restarts.ind_warm_cold = 'COLD' AND
            mc_restarts.groupstatus <> 'GROUP_MEMBER'
            GROUP BY type";
    $this->populateData($sql);
    $newData = array();
    foreach ($this->data as $key => $d) {
      $colour = "orange";
      if ($d['type'] == "PROCESS_DIED") $colour = "red";
      else if ($d['type'] == "OPERATOR_COMMAND") $colour = "green";
      $d['num'] = "<font style='color: " . $colour . "'>" . $d['num'] . "</font>";
      $newData[$key] = $d;
    }
    $this->data = $newData;
    return $this->data;
  }
}

function ossGenerateContent() {
  global $date, $site, $debug, $statsDB, $AdminDB;
  global $phpDir, $webroot, $webargs, $rootdir, $php_webroot, $oss, $datadir;

  $content = array();

  $statsDB->query("SELECT oss_ver_names.name FROM oss_ver, oss_ver_names, sites WHERE oss_ver.siteid = sites.id AND oss_ver.verid = oss_ver_names.id AND sites.name = '$site' AND oss_ver.date = '$date'");

  $content[] = "<H1>OSS Statistics</H1>\n";
  if ( $statsDB->getNumRows() == 1 ) {
    $row = $statsDB->getNextRow();
    $content[] = "<p><b>OSS Version</b>: $row[0]</p>\n";
  }

  if (file_exists($datadir. "/OSS/vappgatewayname.txt")) {
    $gatewayName = file_get_contents($datadir . "/OSS/vappgatewayname.txt");
    $content[] = "<p><b>VApp Gateway</b>: $gatewayName</p>\n";
  }
  $content[] = "<div id='menutree'>\n";
  $content[] = "<ul>";

  $content[] = "<li><a href=\"" . PHP_WEBROOT . "/common/hc.php?$webargs\">Health Status</a></li>";

  $content[] = "<li><a href=\"$phpDir/common/findfile.php?$webargs\">File Search</a></li>\n";

  if (file_exists($rootdir . "/cms/NEAD_statistics.html")) {
    $content[] = "<li><a href=\"" . $webroot . "/cms/NEAD_statistics.html\">NEAD</a></li>\n";
  } else if (file_exists($rootdir . "/cms")) {
    $content[] = "<li><a href=\"$phpDir/nead.php?$webargs\">NEAD</a> </li>\n";
  }

  if (file_exists($rootdir . "/cms/snad.html")) {
    $content[] = "<li><a href=\"" . $webroot . "/cms/snad.html\">SNAD</a></li>\n";
  } else if ( file_exists($rootdir . "/cms/snad_heap.jpg") ) {
    $content[] = "<li><a href=\"$phpDir/snad.php?$webargs\">SNAD</a> </li>\n";
  }

  $row = $statsDB->queryRow("SELECT COUNT(*) FROM son_mo, sites WHERE son_mo.siteid = sites.id AND sites.name = '$site' AND son_mo.date = '$date'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/OSS/son.php?$webargs\">SON Events</a></li>\n";
  }


  /* SEMA Statistics */
  $row = $statsDB->queryRow("SELECT count(*) FROM sema_stats, sites WHERE sema_stats.siteid = sites.id AND sites.name = '$site' AND sema_stats.time BETWEEN '$date 00:00:01' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/sema.php?$webargs\">SEMA Stats</a></li>\n";
  }

  if (file_exists($rootdir . "/EventRates.html")) {
    $content[] = "<li><a href=\"" . $webroot . "/EventRates.html\">Event Channels</a></li>\n";
  } else if ( file_exists($rootdir . "/event_rates") ) {
    $content[] = "<li><a href=\"$phpDir/event_rates.php?$webargs\">Event Channels</a> </li>\n";
  }

  $naLinks = "";
  if (file_exists($rootdir . "/int_na.html")) {
    $naLinks = "<li><a href=\"" . $phpDir . "/na.php?$webargs&agent=int\">Internal</a></li>\n";
  }
  if (file_exists($rootdir . "/ext_na.html")) {
    $naLinks .= "<li><a href=\"" . $phpDir . "/na.php?$webargs&agent=ext\">External</a></li>\n";
  }
  if ( $naLinks != "" ) {
    $content[] = "<li>Notification Agents\n<ul>\n" . $naLinks .  "\n</ul>\n</li>\n";
  }

  /* NotificationServ */
  $nsDir=$rootdir . "/ns";
  if (is_dir($nsDir)) {
    $content[] = "<li>Notification Services\n<ul>";

    if (is_dir($nsDir."/NotificationService")){
      if ($debug) { echo "<p> Found NotificationService</p>\n"; }
      $content[] = "<li><a href=\"$phpDir/ns.php?$webargs&ns=NotificationService\">Internal</a></li>\n";
    } elseif ($debug) {echo "<p> Cannot Find NotificationService</p>\n"; }

    if (is_dir($nsDir."/ExternalNotificationService")){
      if ($debug) { echo "<p> Found ExternalNotificationService</p>\n"; }
      $content[] = "<li><a href=\"$phpDir/ns.php?$webargs&ns=ExternalNotificationService\">External</a></li>\n";
    } elseif ($debug) {echo "<p> Cannot Find ExternalNotificationService</p>\n"; }

    $content[] = "</ul>\n</li>\n";

  } elseif ($debug) {echo "<p> Cannot Open NotificationService Dir</p>\n"; }

  /* FM */
  $content[] = "<li>FM\n<ul>\n";
  if ( file_exists($rootdir . "/alarmStatsNETable.html") ||
       file_exists($rootdir . "/fm/alarmStatsNETable.html") ) {
    $content[] = "<li><a href=\"$phpDir/alarmStats.php?$webargs\">Alarm Stats</a> </li>\n";
  }

  if ( file_exists($rootdir . "/alarmListTable.html") ||
       file_exists($rootdir . "/fm/alarmListTable.html") ) {
    $content[] = "<li><a href=\"$phpDir/alarmList.php?$webargs\">Alarm List</a> </li>\n";
  }

  if (file_exists($rootdir . "/fm/index.html") ) {
    $content[] = "<li><a href=\"" . $webroot . "/fm/index.html\">FM Instrumentation</a></li>\n";
  } else if (file_exists($rootdir . "/fm/instr.html") ) {
    $content[] = "<li><a href=\"" . $webroot . "/fm/instr.html\">FM Instrumentation</a></li>\n";
  }
  $content[] = "<li><a href=\"$phpDir/fm_syncs.php?$webargs\">FM Syncs</a></li>\n";
  $content[] = "<li><a href=\"$phpDir/fm_stats.php?$webargs\">FM Stats</a></li>\n";

  $content[] = "</ul>\n</li>\n";

  /* PMS */
  if (file_exists($rootdir . "/pms") ||
      file_exists($rootdir . "/pms_collected.jpg") ) {
    $content[] = "<li><a href=\"$phpDir/pms.php?$webargs\">PMS</a> </li>\n";
  }


  /* System/Error Log */
  $content[] = "<li>OSS Logs\n<ul>\n";
  if ( file_exists($rootdir . "/logs")) {
    if ( file_exists($rootdir . "/logs/error_mcCountTable.html") ) {
      $content[] = "<li><a href=\"$phpDir/log.php?$webargs&log=error\">Error Log</a> </li>\n";
    }
    if ( file_exists($rootdir . "/logs/system_mcCountTable.html") ) {
      $content[] = "<li><a href=\"$phpDir/log.php?$webargs&log=system\">System Log</a> </li>\n";
    }
  } else {
    if (file_exists($rootdir . "/error_log.html")) {
      $content[] = "<li><a href=\"" . $webroot . "/error_log.html\">Error Log</a></li>\n";
    }
    if (file_exists($rootdir . "/system_log.html")) {
      $content[] = "<li><a href=\"" . $webroot . "/system_log.html\">System Log</a></li>\n";
    }
  }

  /* Command Log */
  $row = $statsDB->queryRow("SELECT count(*) FROM cmds, sites WHERE cmds.siteid = sites.id AND sites.name = '$site' AND cmds.date = '$date'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/cmdlog.php?$webargs\">Command Log Summary</a> </li>\n";
  }

  /* LV Logs */
  $row = $statsDB->queryRow("SELECT count(*) FROM lvlog_entries_by_day, sites WHERE lvlog_entries_by_day.siteid = sites.id AND sites.name = '$site' AND lvlog_entries_by_day.date = '$date'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/lvlog.php?$webargs\">Log Viewer Logs</a></li>\n";
  }

  $content[] = "</ul>\n</li>\n";

  /* HA Logs */
  $sql = "
    SELECT COUNT(halog_events.time) + COUNT(halog_cmds.time)
    FROM halog_cmds,halog_events,sites WHERE
    halog_cmds.siteid = halog_events.siteid AND
    halog_events.siteid = sites.id AND
    sites.name = '" . $site . "' AND
    halog_events.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND
    halog_cmds.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
    ";
  $row = $statsDB->queryRow($sql);

  if ($row[0] > 0) {
    $content[] = "<li><a href=\"" . $phpDir . "/ha.php?" . $webargs . "\">HA Logs</a> </li>\n";
  }

  /* OPS Data */
  $sql = "
    SELECT COUNT(*)
    FROM ops_instrumentation, sites
    WHERE ops_instrumentation.siteid=sites.id
    AND ops_instrumentation.end_time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND sites.name = '" . $site . "'";
  $row = $statsDB->queryRow($sql);
  if ($row[0] > 0) {
    $content[] = "<li><a href=\"$phpDir/ops_stats.php?$webargs\">Ops Statistics</a> </li>\n";
  }

  /* CHA Stats */
  $row = $statsDB->queryRow("
    SELECT COUNT(*) FROM cha_instrumentation,sites WHERE cha_instrumentation.siteid=sites.id AND cha_instrumentation.end_time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND sites.name='$site'");
  if ($row[0] > 0) {
    $content[] = "<li><a href=\"$phpDir/cha_stats.php?$webargs\">CHA Statistics</a> </li>\n";
  }

  /* EAM Stats - To display the link check if EAM configuration parameters had been collected by DDC. If yes then EAM stats will be present */
  $row = $statsDB->queryRow("SELECT COUNT(*) FROM eam_init_stats, sites WHERE eam_init_stats.siteid=sites.id AND eam_init_stats.date='$date' AND sites.name='$site'");
  if ($row[0] > 0) {
    $content[] = "<li><a href=\"$phpDir/eam_stats.php?$webargs&log=error\">EAM Statistics</a> </li>\n";
  }

  /* Sybase */
  $row = $statsDB->queryRow("
SELECT COUNT(*) FROM sybase_dbspace, sites
WHERE sybase_dbspace.siteid = sites.id AND sites.name = '$site' AND sybase_dbspace.date = '$date'");
  if ( $row[0] > 0 ) {
     $content[] = "<li>Sybase\n<ul>\n<li><a href=\"$phpDir/sybase_space.php?$webargs\">DB Size</a></li>\n";
     $content[] = "<li><a href=\"$phpDir/sybase_usage.php?$webargs\">dataserver Usage</a> </li>\n</ul>\n</li>\n";
  }

  /* RSD */
  if (is_dir($rootdir . "/rsd")) {
    $content[] = "<li><a href=\"$phpDir/rsd.php?$webargs\">RSD</a> </li>\n";
  }

  /* SDM */
  if (is_dir($rootdir . "/sdm")) {
     $content[] = "<li><a href=\"$phpDir/sdm.php?$webargs\">SDM-UTRAN</a> </li>\n";
  }

  /* SDM-GRAN */
  if (is_dir($rootdir . "/sdmg")) {
    $content[] = "<li><a href=\"$phpDir/sdmg.php?$webargs\">SDM-GRAN</a> </li>\n";
  }


  /* BCG Instrumentation */
  $sql = "SELECT COUNT(*) FROM bcg_instr_import, sites WHERE siteid = sites.id AND sites.name = '" . $site . "' AND date = '" . $date . "'";
  $row = $statsDB->queryRow($sql);
  $bcgCnt = $row[0];
  $sql = "SELECT COUNT(*) FROM bcg_instr_export, sites WHERE siteid = sites.id AND sites.name = '" . $site . "' AND date = '" . $date . "'";
  $row = $statsDB->queryRow($sql);
  $bcgCnt += $row[0];
  if ($debug) { $content[] = "<p>$bcgCnt BCG records</p>\n"; }
  if ($bcgCnt > 0) {
    $content[] = "<li><a href=\"$phpDir/bcg_instr.php?$webargs\">BCG Instrumentation</a> </li>\n";
  }

  /* Export */
  if (is_dir($rootdir . "/export")) {
     $content[] = "<li><a href=\"$phpDir/export.php?$webargs\">Bulk CM Export</a> </li>\n";
  }

  /* Import/Activation */
  if (is_dir($rootdir . "/bulkcm")) {
    $content[] = "<li><a href=\"$phpDir/pa.php?$webargs\">Planned Area Imports/Activations</a> </li>\n";
  }

  #
    # PCI Metrics
    #
    $sql = "SELECT COUNT(*) FROM pci_cif_log_initialisation_of_service_stats, sites WHERE siteid = sites.id AND sites.name = '" . $site . "' AND time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'";
  $row = $statsDB->queryRow($sql);
  $pciCnt = $row[0];
  $sql = "SELECT COUNT(*) FROM pci_cif_log_notification_stats, sites WHERE siteid = sites.id AND sites.name = '" . $site . "' AND time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'";
  $row = $statsDB->queryRow($sql);
  $pciCnt += $row[0];
  $sql = "SELECT COUNT(*) FROM pci_cif_log_build_cache_stats, sites WHERE siteid = sites.id AND sites.name = '" . $site . "' AND time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'";
  $row = $statsDB->queryRow($sql);
  $pciCnt += $row[0];
  if ($debug) { echo "<p>$pciCnt PCI records</p>\n"; }
  if ($pciCnt > 0) {
    $content[] = "<li><a href=\"$phpDir/pci.php?$webargs\">PCI Metrics</a> </li>\n";
  }

  #
  # CS DB
  #
  $csHTML = "";
  $statsDB->query("SELECT DISTINCT(vdb_names.id), vdb_names.name FROM vdb_names,cs,sites WHERE cs.date = '$date' AND cs.siteid = sites.id AND sites.name = '$site' AND cs.vdbid = vdb_names.id");
  if ( $statsDB->getNumRows() > 0 ) {
    $csHTML .= "<li>Versant Databases\n<ul>\n";
    while ( $row = $statsDB->getNextRow() ) {
      $csHTML .= "<li><a href=\"$phpDir/csdb.php?$webargs&vdbid=$row[0]\">$row[1]</a></li>\n";
    }
    $csHTML .= "</ul>\n</li>\n";
  }

  #
  # CS
  #
  $csDir=$rootdir . "/cs";
  $csTxPrinted = false;
  if ($debug) { echo "<p>Checking $csDir</p>\n"; }
  if (is_dir($csDir)){
    if ($dh = opendir($rootdir . "/cs")) {
      while (($file = readdir($dh)) != false) {
        $entry = $csDir . "/" . $file;
        if ($debug) { echo "<p> Found $entry</p>\n"; }
        if ( is_dir($entry) && $file[0] != '.' ) {
          if ($debug) { echo "<p> Matched $file</p>\n"; }
          if ( $csTxPrinted == false ) {
            $csHTML .= "<li>Tx Logs <ul>\n";
            $csTxPrinted = true;
          }

          if (file_exists($rootdir . "/cs/" . $file . "/index.html") ) {
            $csHTML .= "<li><a href=\"" .
            $webroot .
            "/cs/" . $file . "/index.html\">" . $file . "</a></li>";
          } else if (file_exists($rootdir . "/cs/" . $file . "/countBySessTable.html")) {
              $csHTML .= "<li>$file (<a href=\"$phpDir/cs_session.php?$webargs&cs=$file\">Session</a> <a href=\"$phpDir/cs_resource.php?$webargs&cs=$file\">Resource</a>)</li>\n";
          } else {
            $csHTML .= "<li><a href=\"$phpDir/cs.php?$webargs&cs=$file\">$file</a> </li>\n";
          }
        }
      }

      if ( $csTxPrinted == true ) {
        $csHTML .= "</ul>\n";
      }
      closedir($dh);
    }
  }


  #
  # CSLIB
  #
  $csLibDir = $rootdir . "/cslib";
  $csLibPrinted = false;
  if (is_dir($csLibDir)) {
    if ($dh = opendir($csLibDir)) {
      while (($file = readdir($dh)) != false) {
        $entry = $csLibDir . "/" . $file;
        if ($debug) { echo "<p> Found $entry</p>\n"; }
        if ( is_dir($entry) && $file[0] != '.' ) {
          if ($debug) { echo "<p> Matched $file</p>\n"; }
          if ( $csLibPrinted == false ) {
            $csHTML .= "<li>CSLib <ul>\n";
            $csLibPrinted = true;
          }
          $csHTML .= "<li><a href=\"$phpDir/cslib.php?$webargs&cs=$file&cslibdir=cslib/$file\">$file</a></li>";
        }
      }
    }

    if ( $csLibPrinted == true ) {
      $csHTML .= CLOSE_UL_LI_TAGS;
    }
  }

  /*
   * CSLib new metrics - we don't generate the graphs any more
   *As of DDC R2
   */
  $cslibNames = array();
  $cslibTables = array("cslib_confighome_stats", "cslib_vdb_stats");
  foreach ($cslibTables as $table) {
    $row = $statsDB->query("
        SELECT DISTINCT(jmx_names.name) FROM jmx_names, $table, sites
        WHERE siteid = sites.id AND sites.name = '$site' AND
        nameid = jmx_names.id AND
        time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        ORDER BY jmx_names.name");
    while($row = $statsDB->getNextRow()) {
      $nameParts = preg_split("/-/", $row[0]);
      /* only the key we are interested in here .. we use this to link to the cslib page */
      $cslibNames[$nameParts[0]] = $row[0];
    }
  }
  if (count($cslibNames) > 0) {
    $csHTML .= "<li>CSLib JMX<ul>\n";
    foreach ($cslibNames as $name => $cslib) {
      $csHTML .= "<li><a href=\"$phpDir/cslib_jmx.php?$webargs&cs=$name\">$name</a></li>\n";
    }
    $csHTML .= CLOSE_UL_LI_TAGS;
  }

  /* CS Notification Analysis */
  $csEventDBs = array();
  $row = $statsDB->query("
SELECT DISTINCT(cs_names.name)
FROM cs_notifications, cs_names, sites
WHERE
 cs_notifications.siteid = sites.id AND sites.name = '$site' AND
 cs_notifications.csid = cs_names.id AND
 cs_notifications.date = '$date'");
  while($row = $statsDB->getNextRow()) {
    $csEventDBs[] = $row[0];
  }
  if ( count($csEventDBs) > 0 ) {
    $csHTML .= "<li>Notifications<ul>\n";
    foreach ($csEventDBs as $name) {
      $csHTML .= "<li><a href=\"$phpDir/OSS/cs_notifications.php?$webargs&cs=$name\">$name</a></li>\n";
    }
    $csHTML .= CLOSE_UL_LI_TAGS;
  }

  if ( $csHTML == "" ) {
    $csHTML = "<-- No CS stats for $site on $date -->";
  } else {
    $csHTML = "<li>CS <ul>\n" . $csHTML . "</ul> </li>\n";
  }
  $content[] = $csHTML;

  /* Generic JMX Beans */
  $content[] = '<li>' . getGenJmxTree(true) . '</li>';

  #
    # COSM
    #
    $sql = "SELECT COUNT(*) FROM cosm_mx_stats, sites WHERE cosm_mx_stats.siteid = sites.id AND sites.name = '" . $site . "' AND time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $cosmCnt = $row[0];
    $sql = "SELECT COUNT(*) FROM cosm_fileauditor_stats, sites WHERE cosm_fileauditor_stats.siteid = sites.id AND sites.name = '" . $site . "' AND time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $cosmCnt += $row[0];
    $sql = "SELECT COUNT(*) FROM cosm_os_stats, sites WHERE cosm_os_stats.siteid = sites.id AND sites.name = '" . $site . "' AND time BETWEEN '" . $date . " 00\:00\:00' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $cosmCnt += $row[0];
    if ($debug) { echo "<p>$cosmCnt COSM records</p>\n"; }
    if ($cosmCnt > 0) {
      $content[] = "<li><a href=\"$phpDir/cosm.php?$webargs\">COSM</a> </li>\n";
    }

  #
  # Network Element List
  #
    $row = $statsDB->queryRow("
    SELECT COUNT(*) FROM onrm_ne_counts, sites
    WHERE onrm_ne_counts.siteid = sites.id AND
    onrm_ne_counts.date = '$date' AND
    sites.name = '$site'");
  if ($row[0] > 0) {
    $content[] = "<li><a href=\"$phpDir/nodes.php?$webargs\">Network Elements</a> </li>\n";
  }


  #
  # NMA
  #
  $row = $statsDB->queryRow("SELECT COUNT(*) FROM nma_instr, sites WHERE nma_instr.siteid = sites.id AND sites.name = '$site' AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/OSS/nma.php?$webargs\">NMA</a> </li>\n";
    $row = $statsDB->queryRow("SELECT COUNT(*) FROM netconf_instr, sites WHERE netconf_instr .siteid = sites.id AND sites.name = '$site' AND netconf_instr.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
    if ( $row[0] > 0 ) {
        $content[] = "<li><a href=\"$phpDir/OSS/netconf.php?$webargs\">Netconf</a> </li>\n";
    }
  } else {
    $sql = "SELECT COUNT(*) FROM nma_node_sync_status_data, sites WHERE nma_node_sync_status_data.siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN '" . $date . " 00\:00\:01' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $nmaCnt = $row[0];
    $sql = "SELECT COUNT(*) FROM nma_sync_by_node_data, sites WHERE nma_sync_by_node_data.siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN '" . $date . " 00\:00\:01' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $nmaCnt += $row[0];
    $sql = "SELECT COUNT(*) FROM nma_stats_data, sites WHERE nma_stats_data.siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN '" . $date . " 00\:00\:01' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $nmaCnt += $row[0];
    $sql = "SELECT COUNT(*) FROM nma_notif_recieved_data, sites WHERE nma_notif_recieved_data.siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN '" . $date . " 00\:00\:01' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $nmaCnt += $row[0];
    $sql = "SELECT COUNT(*) FROM nma_con_status_data, sites WHERE nma_con_status_data.siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN '" . $date . " 00\:00\:01' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $nmaCnt += $row[0];
    $sql = "SELECT COUNT(*) FROM nma_notif_handling_data, sites WHERE nma_notif_handling_data.siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN '" . $date . " 00\:00\:01' AND '" . $date . " 23\:59\:59'";
    $row = $statsDB->queryRow($sql);
    $nmaCnt += $row[0];
    if ($debug) { echo "<p>$nmaCnt NMA records</p>\n"; }
    if ($nmaCnt > 0) {
      $content[] = "<li><a href=\"$phpDir/nma.php?$webargs\">NMA</a> </li>\n";
    }
  }



  #
  # Job Mgr Activities
  #
  $jobMgrHTML = "";
  $jmsql = array();
  // SQL statements to see if we should display the link to the JM page
  // Any scheduled, completed, terminated or failed jobs today?
  $jmsql[] = "SELECT sched_jobs + completed_jobs + terminated_jobs + failed_jobs AS count " .
    "FROM job_mgr_jobs,sites WHERE job_mgr_jobs.siteid = sites.id AND " .
      "sites.name = '" . $site . "' AND date='" . $date . "'";
  // any job complexity entries for today?
  $jmsql[] = "SELECT COUNT(job_mgr_complexity.jobcomplexid) AS count " .
    "FROM job_mgr_complexity, sites WHERE job_mgr_complexity.siteid=sites.id AND " .
    "sites.name = '" . $site . "' AND date='" . $date . "'";
  // any job mgr supervisor entries for today?
  $jmsql[] = "SELECT COUNT(job_mgr_supervisor.jobsuperid) AS count " .
    "FROM job_mgr_supervisor, sites WHERE " .
    "job_mgr_supervisor.siteid=sites.id AND sites.name = '" . $site . "' " .
    "AND date='" . $date . "'";

  $showJMLink = false;
  foreach ($jmsql as $sql) {
    $row = $statsDB->queryNamedRow($sql);
    if (is_numeric($row['count']) && $row['count'] > 0) {
      $showJMLink = true;
      break;
    }
  }

  if ($showJMLink) {
    $jobMgrHTML = "<li><a href=\"$phpDir/jm.php?site=$site&date=$date&oss=$oss\">Job Manager Statistics</a> </li>\n";
  } else {
    $jobMgrHTML = "<-- No JobManager Data -->\n";
  }
  $content[] = $jobMgrHTML;


  #
  # AMOS
  #
    $amosCnt = 0;
  $amosRow = $statsDB->queryRow("SELECT COUNT(*) FROM amos_sessions a, sites s WHERE a.siteid = s.id AND time BETWEEN '$date 00\:00\:00' AND '$date 23\:59\:59' AND s.name = '$site'");
  $amosCnt = $amosRow[0];
  $amosRow = $statsDB->queryRow("SELECT COUNT(*) FROM amos_commands a, sites s WHERE a.siteid = s.id AND date = '$date' AND s.name = '$site'");
  $amosCnt += $amosRow[0];
  if ( $amosCnt > 0 ) {
    $content[] = "<li><a href=\"$phpDir/amos.php?$webargs\">AMOS</a> </li>\n";
  }

  #
    # ARNE
    #
    $countARNE = $statsDB->queryRow("SELECT COUNT(*) FROM arne_import, sites WHERE arne_import.siteid = sites.id AND sites.name = '$site' AND arne_import.start BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if (is_dir($rootdir . "/arne") || ($countARNE[0] > 0) ) {
    $content[] = "<li><a href=\"$phpDir/arne.php?$webargs\">ARNE</a> </li>\n";
  }

  #
    # SMO
    #
    $row = $statsDB->queryRow("SELECT COUNT(*) FROM smo_execution, smo_job, sites
    WHERE
    smo_execution.jobid = smo_job.id AND
    smo_job.siteid = sites.id AND
    sites.name = '$site' AND
    ( (smo_execution.starttime BETWEEN '$date 00:00:00' AND  '$date 23:59:59') OR
    (smo_execution.stoptime  BETWEEN '$date 00:00:00' AND '$date 23:59:59') )
    ");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/smo_jobs.php?$webargs\">SMO</a> </li>\n";
  }

  #
    # EBA
    #
  $apps = array("rpmo","ebsw","ebss");
  $haveEba = false;
  $links = array();
  foreach ($apps as $app) {
    $row = $statsDB->queryRow("SELECT COUNT(*) FROM eba_mdc,sites WHERE " .
                  "begin_time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' " .
                  "AND neun = '" . $app . "'" . " AND " .
                  "eba_mdc.siteid = sites.id AND sites.name = '" . $site . "'");
    if ($row[0] > 0) {
      $haveEba = true;
      $links[] = "<li><a href=\"" . $phpDir . "/eba.php?" . $webargs .
      "&app=" . $app . "\">" . strtoupper($app) . "</a></li>\n";
    }
  }
  if ($haveEba) {
    $content[] = "<li>EBA\n<ul>\n";
    foreach ($links as $link) {
      $content[] = $link;
    }
    $content[] = "</ul>\n</li>\n";
    $content[] = "<li><a href='" . $phpDir . "/rpmo.php?" . $webargs . "'>RPMO Data</a></li>\n";
  }

  #
    # GRAN CM Activities
    #
    $sql = "SELECT COUNT(start) FROM gran_cm_activities,sites WHERE
    gran_cm_activities.siteid = sites.id AND sites.name = '" . $site . "'
    AND start BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'";
  $row = $statsDB->queryRow($sql);
  if ($row[0] > 0) {
    $content[] = "<li><a href=\"" . $phpDir . "/gran_cm.php?" . $webargs . "\">GRAN CM Activities (BSM and CNA)</a></li>\n";
  }

  #
    # various daily Instrumentation metrics
    #
    $hasDailyInstr = false;
    $tables = array (
        "pdm_instr" => "PDM",
        "pdm_snmp_instr" => "PDM-SNMP",
        "sgw_instr" => "SGW",
        "smia_instr" => "SMIA"
    );
  foreach ($tables as $tbl => $name) {
    if ( ! $hasDailyInstr ) {
      $row = $statsDB->queryRow("
            SELECT COUNT(*) FROM $tbl,sites
            WHERE siteid = sites.id AND sites.name = '$site' AND
            date = '$date'");
      if ( $row[0] > 0 ) {
        $hasDailyInstr = true;
      }
    }
  }
  if ( $hasDailyInstr ) {
    $content[] = "<li><a href=\"" . $phpDir . "/instr.php?" . $webargs . "\">Daily Instrumentation Metrics</a></li>\n";
  }

  #
    # Generic Measurements used by system test to record their own
    # measurements
    #
    $row = $statsDB->queryRow("
    SELECT COUNT(*) FROM gen_measurements, sites
    WHERE
    gen_measurements.siteid = sites.id AND sites.name = '$site' AND
    gen_measurements.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"" . $phpDir . "/generic_measurements.php?" . $webargs . "\">DDP Generic Measurements </a></li>\n";
  }


  #
    # Common Explorer
    #
  if ( is_dir($rootdir . "/cex")) {
    $content[] = "<li><a href=\"" . $phpDir . "/cex.php?" . $webargs . "\">Common Explorer</a></li>\n";
  }
  else {
    $tablesCEX = array("cex_tasks_stats","cex_nsd_pm_stats","cex_nsd_fm_stats","activemq_cexbroker_stats");
    $haveCEX = false;
    $links = array();
    $cntCEX = -1;
    foreach ($tablesCEX as $table) {
      $row = $statsDB->queryRow("SELECT COUNT(*) FROM " . $table . ",sites WHERE " .
                  "time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59' AND " .
                  $table . ".siteid = sites.id AND sites.name = '" . $site . "'");
      $cntCEX+= $row[0];
    }
    if ($cntCEX > 0) {
      $content[] = "<li><a href=\"$phpDir/cex_jmx.php?$webargs\">Common Explorer</a></li>\n";
    }
  }


  #
    # Commmon Explorer Usability
    #
    $row = $statsDB->queryRow("
    SELECT COUNT(*) FROM cex_usage_stats, sites
    WHERE
    cex_usage_stats.siteid = sites.id AND sites.name = '$site' AND
    cex_usage_stats.event_start BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"" . $phpDir . "/cex_usage_stats.php?" . $webargs . "\">Common Explorer Usage Statistics</a></li>\n";
  }


  #
    # Active MQ OSS Logging Broker
    #
    $amq_ossloggingbroker_HTML = "";
    $statsDB->query("
   SELECT DISTINCT(jmx_names.name) AS name FROM jmx_names, activemq_cexbroker_stats, sites
    WHERE activemq_cexbroker_stats.siteid = sites.id AND sites.name = '$site' AND
     activemq_cexbroker_stats.nameid = jmx_names.id AND
     activemq_cexbroker_stats.nameid = (select id from jmx_names where name = 'ossloggingbroker') AND
     activemq_cexbroker_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    ORDER BY name");
    if ( $statsDB->getNumRows() > 0 ) {
      $amq_ossloggingbroker_HTML = "<li>ActiveMQ OSS Logging Broker\n <ul>\n";
      $linkPattern = "  <li><a href=\"" . PHP_WEBROOT . "/amq_oss_loggingbroker_jmx.php?$webargs&name=%s\">%s</a></li>\n";
      while ( $row = $statsDB->getNextRow() ) {
        $amq_ossloggingbroker_HTML .= sprintf($linkPattern, $row[0], $row[0]);
      }
      $amq_ossloggingbroker_HTML .= " </ul>\n</li>\n";
    } else {
      $amq_ossloggingbroker_HTML = "<-- No AMQ data for $site on $date -->";
    }
    $content[] = $amq_ossloggingbroker_HTML;


  #
    # ActiveMQ
    #
    $statsDB->query("
   SELECT DISTINCT(jmx_names.name) AS name FROM jmx_names, activemq_queue_stats, sites
    WHERE activemq_queue_stats.siteid = sites.id AND sites.name = '$site' AND
     activemq_queue_stats.nameid = jmx_names.id AND
     activemq_queue_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
    ORDER BY name");
    if ( $statsDB->getNumRows() > 0 ) {
      $amqHTML = "<li>ActiveMQ Queues and Topics\n <ul>\n";
      $linkPattern = "  <li><a href=\"" . PHP_WEBROOT . "/activemq.php?$webargs&name=%s\">%s</a></li>\n";
      while ( $row = $statsDB->getNextRow() ) {
        $amqHTML .= sprintf($linkPattern, $row[0], $row[0]);
      }
      $amqHTML .= " </ul>\n</li>\n";
    } else {
      $amqHTML = "<-- No AMQ data for $site on $date -->";
    }
    $content[] = $amqHTML;

  #
    # Open LDAP Monitor
    #
  $row = $statsDB->queryRow("SELECT COUNT(*) FROM open_ldap_monitor_info, sites WHERE open_ldap_monitor_info.siteid = sites.id AND sites.name = '$site' AND open_ldap_monitor_info.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/openldap.php?$webargs\">Open LDAP Monitor Data</a></li>\n";
  }

  #
    # RTTFI Logging
    #
  $row = $statsDB->queryRow("SELECT count(*) FROM rttfi_ops, sites WHERE rttfi_ops.siteid = sites.id AND sites.name = '$site' AND rttfi_ops.starttime BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/rttfi.php?$webargs\">RTTFI Logging</a></li>\n";
  }

    # TSS (Telecom Security Services) Logging
    #
  $row = $statsDB->queryRow("SELECT COUNT(*) FROM tss_instr_stats, sites WHERE tss_instr_stats.siteid = sites.id AND sites.name = '$site' AND tss_instr_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/tss.php?$webargs\">TSS Statistics</a></li>\n";
  }

  #
    # GERAN Plugin Events
    #
  $row = $statsDB->queryRow("SELECT COUNT(*) FROM gpi_events,sites WHERE gpi_events.siteid = sites.id AND sites.name = '$site'
    AND gpi_events.end_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ($row[0] > 0) {
    $content[] = "<li><a href='$phpDir/gpi_events.php?$webargs'>GERAN Plugin Events</a></li>\n";
  }

  #
    # RRPM Instrumentation
    #
  $row = $statsDB->queryRow("SELECT count(*) FROM rrpm_opd, sites WHERE rrpm_opd.siteid = sites.id AND sites.name = '$site' AND rrpm_opd.date = '$date'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/rrpm.php?$webargs\">RRPM Instrumentation</a></li>\n";
  }

    //Application Servers
    $row = $statsDB->queryRow("
    SELECT COUNT(*) FROM smrs_master_gettar, sites
    WHERE smrs_master_gettar.siteid = sites.id AND sites.name = '$site' AND
    smrs_master_gettar.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  if ( $row[0] > 0 ){
    $content[] = "<li><a href=\"$phpDir/smrs.php?$webargs\">SMRS Servers</a>\n";
  }

  $row = $statsDB->queryRow("
SELECT COUNT(*) FROM servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 servers.type = 'UAS'");
  if ($row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/appserv.php?$webargs\">Application Servers</a>\n";
  }

  /* CAAS Stats */
  $row = $statsDB->queryRow("
SELECT COUNT(*)
 FROM caas_performance, servers, sites
 WHERE
  caas_performance.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
  caas_performance.siteid = sites.id AND sites.name = '$site' AND
  caas_performance.serverid = servers.id");
  if ($row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/OSS/caas.php?$webargs\">CAAS</a>\n";
  }

  /* EMC Storage */
  $statsDB->query("
SELECT DISTINCT emc_sys.name, emc_sys.id FROM emc_sys, emc_site, emc_nar, sites
 WHERE
  emc_site.siteid = sites.id AND sites.name = '$site' AND
  emc_sys.id = emc_site.sysid AND emc_site.filedate = '$date' AND
  emc_nar.sysid = emc_sys.id AND emc_nar.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'");
  while ( $row = $statsDB->getNextRow() ) {
    $content[] = "<li><a href=\"$phpDir/emc_stor.php?$webargs&sysid=$row[1]\">EMC Storage: $row[0]</a>\n";
  }

  /* SFS [OR] Access NAS Summary Page */
  $sfsNodeTypes = getSfsNodeTypes($statsDB, $site, $date);
  if ( array_key_exists('SFS', $sfsNodeTypes) ) {
    $content[] = "<li><a href=\"$phpDir/sfs.php?{$webargs}&nodetype=sfs\">SFS</a>\n";
  }
  if ( array_key_exists('ACCESSNAS', $sfsNodeTypes) ) {
    $content[] = "<li><a href=\"$phpDir/sfs.php?{$webargs}&nodetype=accessnas\">Access NAS</a>\n";
  }

  /* NetSim Network Information */
  $row = $statsDB->queryRow("
SELECT COUNT(*)
FROM netsim_network_stats, sites
WHERE
 netsim_network_stats.siteid = sites.id AND
 sites.name = '$site' AND
 netsim_network_stats.date = '$date'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/netsim/netsim_network_info.php?$webargs\">NetSim Network Information</a>\n";
  }

  $row = $statsDB->queryRow("
SELECT COUNT(*) FROM servers, sites, servercfg
WHERE
 servers.siteid = sites.id AND sites.name = '$site' AND
 servers.id = servercfg.serverid AND servercfg.date = '$date' AND
 servers.type = 'NETSIM'");
  if ( $row[0] > 0 ) {
    $content[] = "<li><a href=\"$phpDir/netsim/netsim.php?$webargs\">NetSim</a>\n";
  }

  $haveEsxi = false;
  $links = array();
  $row = $statsDB->query("
SELECT DISTINCT hostname
FROM esxi_servers, sites
WHERE
 esxi_servers.siteid = sites.id AND
 sites.name = '$site' AND
 esxi_servers.date = '$date'");
  if ( $statsDB->getNumRows() > 0 )
  {
    $haveEsxi = true;
    while($row = $statsDB->getNextRow())
    {
        $links[] = "<li><a href=\"$phpDir/esxi_server.php?$webargs&hostname=$row[0]\">$row[0]</a>\n";
    }

  }
    if($haveEsxi)
    {
        $content[] = "<li>ESXi Server\n<ul>\n";
        foreach ($links as $link)
        {
            $content[] = $link;
        }
        $content[] = "</ul>\n</li>\n";
    }


  # END OF LIST
  $content[] = "</ul>";
  $content[] = "</div>\n"; # menutree

  $statsDB->query("
    SELECT TIME(system_startstop.begintime), TIMEDIFF(system_startstop.endtime, system_startstop.begintime),
        system_startstop.type, system_startstop.id
        FROM system_startstop, sites
        WHERE system_startstop.siteid = sites.id AND sites.name = '$site' AND
        system_startstop.begintime BETWEEN '$date 00:00:00' AND '$date 23:59:59'
        ORDER BY system_startstop.begintime
        ");
  if ( $statsDB->getNumRows() > 0 ) {
    $content[] = "<H2>System Start/Stop</H2>\n";
    $table = new HTML_Table('border=1');
    $table->addRow( array( "Start", "Duration", "Type", ), null, 'th' );
    while($row = $statsDB->getNextRow()) {
      $url = $php_webroot . "/system_startstop.php?$webargs&ssid=$row[3]";
      $jsCode = " return popupWindow( '$url', 'startstop', 640, 480 )";
      $aRef = "<a href=\"#\" onclick=\" $jsCode \">$row[2]</a>";
      $table->addRow( array( $row[0], $row[1], $aRef ) );
    }
    $content[] = $table->toHTML();
  }

  $restarts = new RestartCounts();

  if ( count($restarts->getData()) > 0) {
    $content[] = "<h3>MC Restarts</h3>\n";
    $content[] = "<p><b><a href='" . $phpDir . "/mc_restarts.php?" . $webargs . "'>MC Restart Details</a></b><br/><br/>\n";
    $content[] = $restarts->getHtmlTableStr();
    $content[] = "<br/>\n";
  }

  return implode($content);
}
?>
