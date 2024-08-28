<?php
$pageTitle = "ENM Upgrade";

$YUI_DATATABLE = true;

if ( isset($_REQUEST['action']) && $_REQUEST['action'] === 'export' ) {
    $UI = false;
}
include_once "../../common/init.php";

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

const MDT_EXEC = "MDT_EXEC";
const ACTION = "action";
const ACTION_SHOW_TASKS = "showtasks";
const PARAM_TASK_FILES = "taskfiles";
const DURATION = "duration"; // NOSONAR
const UPGRADE = "upgrade";
const UNLOCK = "unlock";
const PRE_INFRASTRUCTURE_HEALTHCHECKS = "PRE_INFRASTRUCTURE_HEALTH_CHECKS";
const INFRASTRUCTURE_CREATEPLAN = "INFRASTRUCTURE_CREATE_PLAN";
const INFRASTRUCTURE_RUNPLAN = "INFRASTRUCTURE_RUN_PLAN";
const INFRA_UPGRADE = "infra_upgrade";
const CLUSTER = "cluster";
const EVENTTYPE = "eventtype";
const IMPORTENM_ISO = "IMPORT_ENM_ISO";
const LITP_ISO = "litp_iso";
const ENM_ISO ="enm_iso";
const LOCK = "lock";
const STAGES = "stages";
const PRE_UPGRADE_SNAPSHOTS = "PRE_UPGRADE_SNAPSHOTS";
const RHEL_OS_PATCHES = "RHEL_OS_PATCHES";
const ENM_UPGRADE_HEALTH_CHECKS = "ENM_UPGRADE_HEALTH_CHECKS";
const CREATE_PLAN = "CREATE_PLAN";
const RUN_PLAN = "RUN_PLAN";
const INFRASTRUCTURE_CREATE_PLAN_LABEL = "Infrastructure Create Plan";
const INFRASTRUCTURE_RUN_PLAN_LABEL = "Infrastructure Run Plan";
const LOAD_DD_XML = "LOAD_DD_XML";
const REMOVE_MODELS = "REMOVE_MODELS";
const KERNEL_REBOOT = "KERNEL_REBOOT";
const POST_UPGRADE_STEPS_LABEL = "Post Upgrade Steps";
const RH6TORH7 = "RH6toRH7";
const POST_UPGRADE = "POST_UPGRADE";

function getPlanTasksFile($startTime, $endTime) {
    global $rootdir_base;

    $searchDates = array();
    if ( ! is_null($startTime) ) {
        $searchFile = "PLAN_TASKS_" . $startTime;
        $searchDates[] = date("dmy", $startTime);
    } else {
        $searchFile = "PLAN_TASKS_NA_" . $endTime;
        $searchDates[] = date("dmy", $endTime);
    }
    foreach ($searchDates as $searchDate) {
        $searchDir = $rootdir_base . "/" . $searchDate  . "/enm_upgrade";
        debugMsg("getPlanTasksFile: Checking $searchDir for $searchFile");
        if ( is_dir($searchDir) ) {
            $files = array_diff(scandir($searchDir), array('.', '..'));
            debugMsg("getPlanTasksFile: Checking files", $files);
            foreach ($files as $file) {
                if (preg_match("/^$searchFile/", $file) === 1) {
                    debugMsg("getPlanTasksFile: matched $file");
                    return $searchDate . "/enm_upgrade/" . $file;
                }
            }
        }
    }

    return null;
}

function showTasks($taskFiles) {
    global $rootdir_base;

    $allPlanTasks = array();
    foreach ($taskFiles as $planTaskFile) {
        $filePath = $rootdir_base . "/" . $planTaskFile;
        $tasks = json_decode(file_get_contents($filePath), true); // NOSONAR
        $allPlanTasks = array_merge($allPlanTasks, $tasks);
    }

    $table = new DDPTable(
        "plan_tasks",
        array(
            array(DDPTable::KEY => "phase", DDPTable::LABEL => "Phase"),
            array(DDPTable::KEY => "start_time", DDPTable::LABEL => "Start Time"),
            array(DDPTable::KEY => "end_time", DDPTable::LABEL => "End Time"),
            array(DDPTable::KEY => DURATION, DDPTable::LABEL => "Duration"),
            array(
                DDPTable::KEY => "task_info",
                DDPTable::LABEL => "Tasks",
                DDPTable::FORMATTER => "formatTasksTable",
            )
        ),
        array("data" => $allPlanTasks)
    );

    echo <<<EOF

<script type="text/javascript">
function formatTasksTable(elCell, oRecord, oColumn, oData) {
    var html = "<table><tr><td>Item</td><td>Info</td></tr>";
    for (var i = 0; i < oData.length; i++ ) {
       html += "<tr><td>" + oData[i].item + "</td><td>" + oData[i].info + "</td></tr>";
    }
    html += "</table>"
    elCell.innerHTML = html;
}
</script>

EOF;

    echo $table->getTable();
}

function getLockUnlockLink($stage, $cluster, $node, $lockUnlock) {
    global $webargs;

    return '<a href="' . $_SERVER['PHP_SELF'] . "?" . $webargs . "&action=showlock" .
                       "&duration=" . urlencode(toTime($stage['duration'])) .
                       "&from=" . urlencode($stage['start']) .
                       "&to=" . urlencode($stage['end']) .
                       "&cluster=" . $cluster .
                       "&node=" . $node .
                       "&lu=" . $lockUnlock .
                       '">' . toTime($stage['duration']) . '</a>';
}

function getVcsLink($stage) {
    global $webargs;

    return '<a href="' . PHP_WEBROOT . "/TOR/vcs_events.php?" . $webargs .
                       "&from=" . urlencode($stage['start']) .
                       "&to=" . urlencode($stage['end']) .
                       '">' . toTime($stage['duration']) . '</a>';
}

function toTime($seconds) {
    return sprintf(
        "%02d:%02d:%02d",
        floor((float)$seconds / 3600),
        floor(((float)$seconds % 3600) / 60),
        floor((float)$seconds % 60)
    );
}

function showLockUnlock($statsDB,$lockUnlock,$cluster,$node,$from,$to) {
    global $date, $site, $webargs, $debug;

    $hostToNode = array();
    $statsDB->query("
SELECT servers.hostname, enm_cluster_host.nodename
FROM enm_cluster_host, servers, sites
WHERE
 enm_cluster_host.siteid = sites.id AND sites.name = '$site' AND
 enm_cluster_host.serverid = servers.id AND
 enm_cluster_host.date = '$date'");
    while ($row = $statsDB->getNextRow()) {
        $hostToNode[$row[0]] = $row[1];
    }

    $stateChanges = array();
    $lastByService = array();
    $statsDB->query("
SELECT
 enm_vcs_events.time AS time,
 servers.hostname AS server,
 enm_cluster_svc_names.name AS service,
 enm_vcs_events.eventtype AS eventtype
FROM enm_vcs_events, sites, servers, enm_cluster_svc_names
WHERE
 enm_vcs_events.siteid = sites.id AND sites.name = '$site' AND
 enm_vcs_events.serverid = servers.id AND
 enm_vcs_events.serviceid = enm_cluster_svc_names.id AND
 enm_vcs_events.time BETWEEN '$from' AND '$to'
ORDER BY time ASC, eventtype ASC");
    while ( $row = $statsDB->getNextNamedRow() ) {
        $type = '';
        if ( strstr($row[EVENTTYPE], 'Offline') ) {
            $type = 'Offline';
        } elseif ( strstr($row[EVENTTYPE], 'Online') ) {
            $type = 'Online';
        }

        $beginEnd = '';
        if ( strstr($row[EVENTTYPE], 'Start') ) {
            $beginEnd = 'Begin';
        } elseif ( strstr($row[EVENTTYPE], 'Completed') ) {
            $beginEnd = 'End';
        }

        $serverNode = $hostToNode[$row['server']];
        $service = $serverNode . "-" . $row['service'];

        if ( $debug > 0 ) { echo "<pre>showLockUnlock service=$service type=$type beginEnd=$beginEnd</pre>\n"; }

        if ( $type != '' ) {
            if ( $beginEnd == 'Begin' ) {
                if ( ! array_key_exists($row['service'],$lastByService) ) {
                    $lastByService[$service] = array( 'node' => $serverNode, 'service' => $row['service'], 'type' => $type, 'start' => $row['time'] );
                    $stateChanges[] = &$lastByService[$service];
                }
            } else {
                if ( array_key_exists($service,$lastByService) ) {
                    $lastByService[$service]['end'] = $row['time'];
                    if ( $debug > 0 ) { echo "<pre>showLockUnlock set end time\n"; print_r($lastByService[$service]); echo "</pre>\n"; }
                }
            }
        }
    }

    if ( $debug > 0 ) { echo "<pre>stateChanges\n"; print_r($stateChanges); echo "</pre>\n"; }

    $tableData = array();
    foreach ($stateChanges as $stateChange) {
        if ( array_key_exists('end',$stateChange) ) {
            $startTime = strtotime($stateChange['start']);
            $endTime = strtotime($stateChange['end']);
            $tableData[] =
                         array(
                             'start' => strftime("%H:%M:%S",$startTime),
                             'node' => $stateChange['node'],
                             'service' => $stateChange['service'],
                             'type' => $stateChange['type'],
                             'duration' => toTime($endTime-$startTime)
                         );
        }
    }

    $table = new DDPTable('lockunlock',
                          array(
                              array('key' => 'start', 'label' => 'Start Time'),
                              array('key' => 'node', 'label' => 'Node'),
                              array('key' => 'service', 'label' => 'Service'),
                              array('key' => 'type', 'label' => 'Action'),
                              array('key' => 'duration', 'label' => 'Time Taken')
                          ),
                          array('data' => $tableData)
    );

    echo "<H3>" . ucfirst($lockUnlock) . " " . $node . "</H3>\n";
    echo  '<p><a href="' . PHP_WEBROOT . "/TOR/vcs_events.php?" . $webargs .
                         "&from=" . $from .
                         "&to=" . $to .
                         '">Raw VCS Events</a></p>';

    echo $table->getTable();
}

function stageNameParams() {
    return array(
        'RH6' => array(
            'IMPORT_ENM_ISO' => 'Import ENM ISO',
            CREATE_PLAN => 'Create Plan',
            RUN_PLAN => 'Run Plan',
            'ENM_UPGRADE_HEALTH_CHECKS' => 'ENM Upgrade Health Checks',
            'INFRASTRUCTURE_CREATE_PLAN' => INFRASTRUCTURE_CREATE_PLAN_LABEL,
            'PRE_INFRASTRUCTURE_HEALTH_CHECKS' => 'Pre Infrastructure Health Checks',
            'INFRASTRUCTURE_RUN_PLAN' => INFRASTRUCTURE_RUN_PLAN_LABEL,
            'PRE_UPGRADE_SNAPSHOTS' => 'Pre Upgrade Snapshots',
            LOAD_DD_XML => 'Load DD Xml',
            REMOVE_MODELS => 'Remove Models',
            KERNEL_REBOOT => 'Kernel Reboot',
            'RHEL_OS_PATCHES' => 'Rhel OS Patches',
            POST_UPGRADE => POST_UPGRADE_STEPS_LABEL ),
         RH6TORH7 => array(
            'MCO_CONN_SETUP' => 'MCO Connection Setup',
            'CREATE_TO_STATE_DD' => 'Create to State DD',
            'CREATE_TO_STATE_DD_SEGMENTS' => 'Create to State DD Segments',
            'IMPORT_TO_STATE_ENM' => 'Import to State ENM',
            'INFOSCALE_PLAN' => 'Infoscale Plan',
            'INFRA_PLAN' => 'Infra Plan',
            'LOAD_SEGMENTS_FOR_MS_REDEPLOY' => 'Load Segments for MS Redeploy',
            'LOAD_TO_STATE_MODEL' => 'Load to State Model',
            'POST_NODES_REDEPLOYMENT_PLAN' => 'Post Nodes Redeployment Plan',
            'POST_PROCESS_RESTORED_DATA' => 'Post Process Restored Data',
            'POST_UPGRADE_STEPS' => POST_UPGRADE_STEPS_LABEL,
            'PRE_NODES_PUSH_ARTIFACTS' => 'Pre Nodes Push Artifacts',
            'PRE_NODES_REDEPLOYMENT_PLAN' => 'Pre Nodes Redeployment Plan',
            'REDEPLOY_MS' => 'Redeploy MS',
            'ROLLING_NODES_REDEPLOY' => 'Rolling Nodes Redeploy',
            'TAKE_SNAPSHOTS' => 'Take Snapshots',
            '08_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 08 Upgrade Prechecks and Health Checks',
            '10_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 10 Upgrade Prechecks and Health Checks',
            '12_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 12 Upgrade Prechecks and Health Checks',
            '14_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 14 Upgrade Prechecks and Health Checks',
            '17_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 17 Upgrade Prechecks and Health Checks',
            '20_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 20 Upgrade Prechecks and Health Checks',
            '22_UPGRADE_PRECHECKS_AND_HEALTH_CHECKS' => 'Stage 22 Upgrade Prechecks and Health Checks' )
    );
}

function showUpgrade($upgradeInfo, $upgradeIndex) {
    global $debug;
    global $webargs;
    global $site;
    global $web_temp_dir, $date;

    $overallSummaryHelp = "DDP_Bubble_70_ENM_Overall_Summary";
    drawHeaderWithHelp("Upgrade Summary", 2, "overallSummaryHelp", $overallSummaryHelp);
    $phaseDuration = 0;
    $overallUpgradeTable = new HTML_Table('border=1');
    $overallUpgradeTable->addRow(array("<center><b>Phase</b></center>","<center><b>Detail</b></center>","<center><b>Duration</b></center>","<center><b>Start Time</b></center>","<center><b>End Time</b></center>"));
    if ( array_key_exists('release_info',$upgradeInfo) ) {
        $overallUpgradeTable->addRow(array("<b>Release Versions</b>"));
        foreach ( $upgradeInfo['release_info'] as $product => $version ) {
            if ( $product == "LITP" ) {
                $split = explode(" ", $version);
                if ( ! isset($split[6]) ) {
                    $split[6] = '';
                }
                if ( ! isset($upgradeInfo[LITP_ISO]) ) {
                    $litpIso = '';
                } else {
                    $litpIso = $upgradeInfo[LITP_ISO];
                }
                $version = $split[0] . " " . $split[1] . " (ISO Version: $litpIso) " .
                    $split[2] . " " . $split[3] . " " . $split[4] . " " . $split[5] . " " . $split[6];
            }
            $overallUpgradeTable->addRow(array($product . ' Release', $version));
        }
    }
    $overallUpgradeTable->addRow(array("<b>Infrastructure Plan</b>"));
    if ( $upgradeInfo[INFRA_UPGRADE] == "Yes" ) {
        $overallUpgradeTable->addRow(array("Infrastructure Upgrade Plan","Yes"));
        if ( array_key_exists(PRE_INFRASTRUCTURE_HEALTHCHECKS, $upgradeInfo) ) {
            $duration = $upgradeInfo[PRE_INFRASTRUCTURE_HEALTHCHECKS]['duration'];
            $phaseDuration += $duration;
            $overallUpgradeTable->addRow(array("Pre-Infrastructure HealthCheck", "", toTime($duration), $upgradeInfo
                [PRE_INFRASTRUCTURE_HEALTHCHECKS]['start'], $upgradeInfo[PRE_INFRASTRUCTURE_HEALTHCHECKS]['end']));
        }
        if ( array_key_exists(INFRASTRUCTURE_CREATEPLAN, $upgradeInfo)  ) {
            $duration = $upgradeInfo[INFRASTRUCTURE_CREATEPLAN]['duration'];
            $phaseDuration += $duration;
            if ( array_key_exists('end', $upgradeInfo[INFRASTRUCTURE_CREATEPLAN]) ) {
                $overallUpgradeTable->addRow(array(INFRASTRUCTURE_CREATE_PLAN_LABEL, "", toTime($duration), $upgradeInfo
                    [INFRASTRUCTURE_CREATEPLAN]['start'], $upgradeInfo[INFRASTRUCTURE_CREATEPLAN]['end']));
            } else {
                $upgradeInfo[INFRASTRUCTURE_CREATEPLAN]['end'] = '';
                $overallUpgradeTable->addRow(array(INFRASTRUCTURE_CREATE_PLAN_LABEL, "", toTime($duration), $upgradeInfo
                    [INFRASTRUCTURE_CREATEPLAN]['start'], $upgradeInfo[INFRASTRUCTURE_CREATEPLAN]['end']));
            }
        }
        if ( array_key_exists(INFRASTRUCTURE_RUNPLAN, $upgradeInfo)  ) {
            $duration = $upgradeInfo[INFRASTRUCTURE_RUNPLAN]['duration'];
            $phaseDuration += $duration;
            $overallUpgradeTable->addRow(array(INFRASTRUCTURE_RUN_PLAN_LABEL, "", toTime($duration), $upgradeInfo
                [INFRASTRUCTURE_RUNPLAN]['start'], $upgradeInfo[INFRASTRUCTURE_RUNPLAN]['end']));
        }
    } else {
        $overallUpgradeTable->addRow(array("Infrastructure Upgrade Plan","No"));
        $overallUpgradeTable->addRow(array("Pre-Infrastructure HealthCheck","","NA"));
        $overallUpgradeTable->addRow(array(INFRASTRUCTURE_CREATE_PLAN_LABEL,"","NA"));
        $overallUpgradeTable->addRow(array(INFRASTRUCTURE_RUN_PLAN_LABEL,"","NA"));
    }
    if ( array_key_exists(PRE_UPGRADE_SNAPSHOTS, $upgradeInfo)  ) {
        $overallUpgradeTable->addRow(array("<b>Pre-Upgrade Snapshots</b>"));
        $duration = $upgradeInfo[PRE_UPGRADE_SNAPSHOTS]['duration'];
        $phaseDuration += $duration;
        if ( !array_key_exists('end', $upgradeInfo[PRE_UPGRADE_SNAPSHOTS]) ) {
            $upgradeInfo[PRE_UPGRADE_SNAPSHOTS]['end'] = '';
        }
        $overallUpgradeTable->addRow(
            array(
                "Pre-Upgrade Snapshots",
                "",
                toTime($duration),
                $upgradeInfo[PRE_UPGRADE_SNAPSHOTS]['start'],
                $upgradeInfo[PRE_UPGRADE_SNAPSHOTS]['end']
            )
        );
    }
    $overallUpgradeTable->addRow(array("<b>RHEL OS Patches</b>"));
    if ( array_key_exists(RHEL_OS_PATCHES, $upgradeInfo)  ) {
        $overallUpgradeTable->addRow(array("RHEL OS Patches Upgrade", "Yes"));
        $duration = $upgradeInfo[RHEL_OS_PATCHES]['duration'];
        $phaseDuration += $duration;
        if ( !array_key_exists('end', $upgradeInfo[RHEL_OS_PATCHES]) ) {
            $upgradeInfo[RHEL_OS_PATCHES]['end'] = '';
        }
        $overallUpgradeTable->addRow(
            array(
                "RHEL OS Patch Upgrade",
                "",
                toTime($duration),
                $upgradeInfo[RHEL_OS_PATCHES]['start'],
                $upgradeInfo[RHEL_OS_PATCHES]['end']
            )
        );
    } else {
        $overallUpgradeTable->addRow(array("RHEL OS Patches", "No"));
        $overallUpgradeTable->addRow(array("RHEL OS Patch Upgrade","","NA"));
    }
    if ( array_key_exists(KERNEL_REBOOT, $upgradeInfo)  ) {
        $overallUpgradeTable->addRow(array("RHEL Kernel Upgrade", "Yes"));
        $duration = $upgradeInfo[KERNEL_REBOOT]['duration'];
        $phaseDuration += $duration;
        $overallUpgradeTable->addRow(array("Time between LMS reboot & user resuming Upgrade", "", toTime($duration),
            $upgradeInfo[KERNEL_REBOOT]['start'], $upgradeInfo[KERNEL_REBOOT]['end']));
    } else {
        $overallUpgradeTable->addRow(array("RHEL Kernel Upgrade", "No"));
        $overallUpgradeTable->addRow(array("Time between LMS reboots & user resuming Upgrade","","NA"));
    }
    $overallUpgradeTable->addRow(array("<b>ENM Upgrade</b>"));
    if ( array_key_exists('from_ver',$upgradeInfo) ) {
        $status = $upgradeInfo['completion_status']." ( Upgrade from ENM ISO ". $upgradeInfo['from_ver'] . " )";
        if ( $upgradeInfo['completion_status'] == "SUCCESS"  ) {
            $overallUpgradeTable->addRow(array("Upgrade Outcome","<font color=GREEN>$status</font>"));
        } else {
            $overallUpgradeTable->addRow(array("Upgrade Outcome","<font color=RED>$status</font>"));
        }
    }
    if ( array_key_exists(ENM_UPGRADE_HEALTH_CHECKS, $upgradeInfo)  ) {
        $duration = $upgradeInfo[ENM_UPGRADE_HEALTH_CHECKS]['duration'];
        $phaseDuration += $duration;
        if ( !array_key_exists('end', $upgradeInfo[ENM_UPGRADE_HEALTH_CHECKS]) ) {
            $upgradeInfo[ENM_UPGRADE_HEALTH_CHECKS]['end'] = '';
        }
        $overallUpgradeTable->addRow(
            array(
                "Pre-Upgrade HealthCheck",
                "",
                toTime($duration),
                $upgradeInfo[ENM_UPGRADE_HEALTH_CHECKS]['start'],
                $upgradeInfo[ENM_UPGRADE_HEALTH_CHECKS]['end']
            )
        );
    }
    if ( array_key_exists('IMPORT_LITP_ISO',$upgradeInfo)  ) {
        $duration = $upgradeInfo['IMPORT_LITP_ISO']['duration'];
        $phaseDuration += $duration;
        $overallUpgradeTable->addRow(array("LITP ISO Import", "",toTime($duration), $upgradeInfo['IMPORT_LITP_ISO']['start'], $upgradeInfo['IMPORT_LITP_ISO']['end']));
    }
    if ( array_key_exists(IMPORTENM_ISO, $upgradeInfo)  ) {
        $duration = $upgradeInfo[IMPORTENM_ISO]['duration'];
        $phaseDuration += $duration;
         if ( !array_key_exists('end', $upgradeInfo[IMPORTENM_ISO]) ) {
             $upgradeInfo[IMPORTENM_ISO]['end'] = '';
         }
             $overallUpgradeTable->addRow(array("ENM ISO Import", "", toTime($duration), $upgradeInfo
                  [IMPORTENM_ISO]['start'], $upgradeInfo[IMPORTENM_ISO]['end']));
    }
    if ( array_key_exists(LOAD_DD_XML, $upgradeInfo) ) {
        $duration = $upgradeInfo[LOAD_DD_XML]['duration'];
        $phaseDuration += $duration;
        $overallUpgradeTable->addRow(array("Load new ENM Deployment Description XML", "", toTime($duration),
            $upgradeInfo[LOAD_DD_XML]['start'], $upgradeInfo[LOAD_DD_XML]['end']));
    }
    if ( array_key_exists(REMOVE_MODELS, $upgradeInfo) ) {
        $duration = $upgradeInfo[REMOVE_MODELS]['duration'];
        $phaseDuration += $duration;
        if ( $duration != 0 ) {
            $overallUpgradeTable->addRow(array("Remove items from LITP model", "", toTime($duration),
                $upgradeInfo[REMOVE_MODELS]['start'], $upgradeInfo[REMOVE_MODELS]['end']));
        }
    }
    if ( array_key_exists(CREATE_PLAN, $upgradeInfo) ) {
        $duration = $upgradeInfo[CREATE_PLAN]['duration'];
        $phaseDuration += $duration;
        if ( !array_key_exists('end', $upgradeInfo[CREATE_PLAN]) ) {
            $upgradeInfo[CREATE_PLAN]['end'] = '';
        }
        $overallUpgradeTable->addRow(
            array(
                "ENM Upgrade Create Plan",
                "",
                toTime($duration),
                $upgradeInfo[CREATE_PLAN]['start'],
                $upgradeInfo[CREATE_PLAN]['end']
            )
        );
    }
    if ( array_key_exists(RUN_PLAN, $upgradeInfo)  ) {
        $runPlan = $upgradeInfo[RUN_PLAN];
        $duration = $runPlan['duration'];
        $phaseDuration += $duration;

        $planTasksFiles = array();
        $runPlanStartTime = strtotime($runPlan["start"]);
        $planTasksFile = getPlanTasksFile($runPlanStartTime, null);
        if ( ! is_null($planTasksFile) ) {
            $planTasksFiles[] = $planTasksFile;
        }
        $runPlanEndTime = strtotime($runPlan["end"]);
        $planTasksFile = getPlanTasksFile(null, $runPlanEndTime);
        if ( ! is_null($planTasksFile) ) {
            $planTasksFiles[] = $planTasksFile;
        }
        $detailText ="";
        if ( count($planTasksFiles) > 0 ) {
            $detailText = makeLink(
                "/TOR/system/enm_upgrade.php",
                "Plan Tasks",
                array(
                    ACTION => ACTION_SHOW_TASKS,
                    PARAM_TASK_FILES => implode(",", $planTasksFiles)
                )
            );
        }
        $row = array(
            "ENM Upgrade Run Plan",
            $detailText,toTime($duration),
            $upgradeInfo[RUN_PLAN]['start'],
            $upgradeInfo[RUN_PLAN]['end']
        );
        $overallUpgradeTable->addRow($row);
    }
    if ( array_key_exists(MDT_EXEC, $upgradeInfo)  ) {
        $mdtExec = $upgradeInfo[MDT_EXEC];
        $mdtRow = array(
            "MDT Execution",
            "",
            toTime($mdtExec[DURATION]),
            $mdtExec["start"],
            $mdtExec["end"]
        );
        $overallUpgradeTable->addRow($mdtRow);
    }
    if ( array_key_exists(POST_UPGRADE, $upgradeInfo)  ) {
        $duration = $upgradeInfo[POST_UPGRADE]['duration'];
        $phaseDuration += $duration;
        $overallUpgradeTable->addRow(array("Post Upgrade Steps", "", toTime($duration),
            $upgradeInfo[POST_UPGRADE]['start'], $upgradeInfo[POST_UPGRADE]['end']));
    }
    $stageparams = stageNameParams();
    foreach ($stageparams[RH6TORH7] as $stageName => $stageLabel ) {
        if ( array_key_exists($stageName, $upgradeInfo)  ) {
            $duration = $upgradeInfo[$stageName]['duration'];
            $phaseDuration += $duration;
            $overallUpgradeTable->addRow(array($stageLabel, "", toTime($duration),
                $upgradeInfo[$stageName]['start'], $upgradeInfo[$stageName]['end']));
        }
    }
    $totalUpgradeTime = strtotime($upgradeInfo['end']) - strtotime($upgradeInfo['start']);
    $otherTime = $totalUpgradeTime - $phaseDuration;
    $overallUpgradeTable->addRow(array("Other Time", "",toTime($otherTime)));
    $overallUpgradeTable->addRow(array("<center><b>Total Upgrade Time</b></center>", "",toTime($totalUpgradeTime)));
    echo $overallUpgradeTable->toHTML();


    $clusterRows = array();
    $nodeRows = array();

    foreach ($upgradeInfo['clusters'] as $clusterName => $clusterInfo ) {
        if ( $debug > 2 ) { echo "<pre>processing cluster $clusterName\n"; print_r($clusterInfo); echo "</pre>\n"; }
        if ( $debug > 3 ) { echo "<pre>upgradeInfo\n"; print_r($upgradeInfo); echo "</pre>\n"; }


        $clusterLockTime = 0;
        $clusterUpgradeTime = 0;
        $clusterUnlockTime = 0;
        foreach ($clusterInfo['nodes'] as $node => $nodeInfo) {
            if ( $debug > 2 ) { echo "<pre>processing $node\n"; print_r($nodeInfo); echo "</pre>\n"; }
            if ( array_key_exists(LOCK, $nodeInfo[STAGES]) ) {
                $clusterLockTime += $nodeInfo[STAGES][LOCK]['duration'];
            }
            if ( array_key_exists(UPGRADE, $nodeInfo[STAGES]) ) {
                $clusterUpgradeTime += $nodeInfo[STAGES][UPGRADE]['duration'];
            }
            if ( array_key_exists(UNLOCK, $nodeInfo[STAGES]) ) {
                $clusterUnlockTime += $nodeInfo[STAGES][UNLOCK]['duration'];
            }

            $nodeTotal = 0;
            foreach ( array(LOCK, UPGRADE, UNLOCK) as $stage ) {
                if ( array_key_exists($stage, $nodeInfo[STAGES]) ) {
                    $nodeTotal += $nodeInfo[STAGES][$stage]['duration'];
                }
            }
            $upgradeNodeInfo = '';
            if ( array_key_exists(UPGRADE, $nodeInfo[STAGES]) ) {
                $upgradeNodeInfo = $nodeInfo[STAGES][UPGRADE]['duration'];
            }
            $unlock = '';
            if ( array_key_exists(UNLOCK, $nodeInfo[STAGES]) ) {
                $unlock = getLockUnlockLink($nodeInfo[STAGES][UNLOCK], $clusterName, $node, UNLOCK);
            }
            $start = '';
            $lock = '';
            if ( array_key_exists(LOCK, $nodeInfo[STAGES]) ) {
                $start = $nodeInfo[STAGES][LOCK]['start'];
                $lock = getLockUnlockLink($nodeInfo[STAGES][LOCK], $clusterName, $node, LOCK);
            }
            $nodeRows[] = array('node' => $node,
                                'total' => toTime($nodeTotal),
                                'start' => $start,
                                LOCK => $lock,
                                UPGRADE => toTime($upgradeNodeInfo),
                                UNLOCK => $unlock);
        }

        $clusterOther = 0;
        if ( isset($clusterInfo['duration']) ) {
            $clusterOther = $clusterInfo['duration']-
                ($clusterLockTime + $clusterUpgradeTime + $clusterUnlockTime);
        }

        if ( isset($clusterInfo['start']) ) {
            $start = $clusterInfo['start'];
        } else {
            $start = '';
        }
        if ( isset($clusterInfo['duration']) ) {
            $total = $clusterInfo['duration'];
        } else {
            $total = '';
        }
        $clusterRows[] = array(
            CLUSTER => $clusterName,
            'start' => $start,
            'total' => toTime($total),
            LOCK => toTime($clusterLockTime),
            UPGRADE => toTime($clusterUpgradeTime),
            UNLOCK => toTime($clusterUnlockTime),
            'other' => toTime($clusterOther)
        );
    }

    $dir=$_GET['dir'];
    $enminstLogPath=shell_exec("ls /data/stats/tor/$site/data/$dir/TOR/sw_inventory/enminst.log*");
    $fullpath=rtrim ($enminstLogPath);
    if (file_exists($fullpath)) {
        echo "<br><li>" . makeLinkForURL(getUrlForFile($fullpath), "Download enminst.log") . "</li>\n";
    }

    $clusterSummaryHelp = "DDP_Bubble_71_ENM_enm_upgrade_cluster_level_summary";
    drawHeaderWithHelp("Cluster Level Summary", 2, "clusterSummaryHelp", $clusterSummaryHelp);
    $clusterTable = new DDPTable('cluster_' . $upgradeIndex,
                                 array(
                                     array('key' => CLUSTER, 'label' => 'Cluster'),
                                     array('key' => 'start', 'label' => 'Start', 'formatter' => 'ddpFormatTime' ),
                                     array('key' => 'total', 'label' => 'Total'),
                                     array('key' => LOCK, 'label' => 'Lock'),
                                     array('key' => UPGRADE, 'label' => 'Upgrade'),
                                     array('key' => UNLOCK, 'label' => 'Unlock'),
                                     array('key' => 'other', 'label' => 'Other')
                                 ),
                                 array('data' => $clusterRows));
    echo $clusterTable->getTable();

    $nodeSummaryHelp = "DDP_Bubble_72_ENM_enm_upgrade_node_level_summary";
    drawHeaderWithHelp("Node Level Summary", 2, "nodeSummaryHelp", $nodeSummaryHelp);
    $nodeTable = new DDPTable('node_' . $upgradeIndex,
                              array(
                                  array('key' => 'node', 'label' => 'Node'),
                                  array('key' => 'start', 'label' => 'Start', 'formatter' => 'ddpFormatTime' ),
                                  array('key' => 'total', 'label' => 'Total'),
                                  array('key' => LOCK, 'label' => 'Lock'),
                                  array('key' => UPGRADE, 'label' => 'Upgrade'),
                                  array('key' => UNLOCK, 'label' => 'Unlock')
                              ),
                              array('data' => $nodeRows));

    echo $nodeTable->getTable();

    echo "<br><br><hr><hr><br>";
}

function getUpgradeInfo($statsDB, $upgrade) {
    global $debug, $site;

    $upgradeStartTime = $upgrade['start'];
    $upgradeEndTime = $upgrade['end'];
    $stageNames = stageNameParams();
    $upgradeStagesRH6toRH7 = array_keys($stageNames[RH6TORH7]);
    $upgradeStagesRH6 = array_keys($stageNames['RH6']);
    $statsDB->query("
SELECT
 enm_upgrade_events.time AS time,
 enm_upgrade_stage_names.name AS stage,
 enm_upgrade_events.state AS state,
 enm_upgrade_events.additionalInfo AS addInfo
FROM enm_upgrade_events, enm_upgrade_stage_names, sites
WHERE
 enm_upgrade_events.siteid = sites.id AND sites.name = '$site' AND
 enm_upgrade_events.stageid = enm_upgrade_stage_names.id AND
 enm_upgrade_events.time BETWEEN '$upgradeStartTime' AND '$upgradeEndTime'
ORDER BY time,seqno");
    $rows = array();
    while ( $row = $statsDB->getNextNamedRow() ) {
        $rows[] = $row;
    }

    # Try and pair the start and end events together
    $stageExecMap = array();
    foreach ($rows as $row ) {
        $events[] = $row;

        $key = $row['stage'];
        if ( $row['stage'] != 'UPGRADE_ENM' ) {
            $key = $key . $row['addInfo'];
        }

        if ( $row['state'] == 'START' ) {
            $stageExec = array('start' => $row['time'], 'stage' => $row['stage'], 'addInfo' => $row['addInfo'] );
            $stageExecMap[$key] = $stageExec;
        } else if ( $row['state'] == 'END' ) {
            if ( $row['stage'] == 'UPGRADE_ENM' ){
                $stageExecMap[$key]['addInfo'] = $stageExecMap[$key]['addInfo'] . ";" . $row['addInfo'];
            }
            if ( array_key_exists($key, $stageExecMap) ) {
                $stageExecMap[$key]['end'] = $row['time'];
            } else if ( $debug > 1 ) {
                echo "<pre>Could not find $key in stageExecMap\n"; print_r($stageExecMap); echo "</pre>\n";
            }
        }
    }
    if ( $debug > 0 ) { echo "<pre>stageExecMap\n"; print_r($stageExecMap); echo "</pre>\n"; }

    $upgradeInfo = array( 'clusters' => array() );
    $currentClusterName = "";
    $nodeName = "";

    foreach ( $stageExecMap as $key => $stageExec ) {
        if ( $debug > 0 ) { echo "<pre>key=$key stageExec\n"; print_r($stageExec); echo "</pre>\n"; }
        $addInfo = array();
        if ( $stageExec['addInfo'] != '' ) {
            foreach ( explode(';',$stageExec['addInfo']) as $addInfoPart ) {
                list($name,$value) = explode("=",$addInfoPart);
                $addInfo[$name] = $value;
            }
        }

        if ( $debug > 0 ) { echo "<pre>currentClusterName=$currentClusterName nodeNode=$nodeName addInfo\n"; print_r($addInfo); echo "</pre>\n"; }

        unset($treeNode);
        if ( $stageExec['stage'] == 'UPGRADE_ENM' ) {
            $treeNode = &$upgradeInfo;
            if ( array_key_exists(ENM_ISO, $addInfo) ) {
                $upgradeInfo[ENM_ISO] = $addInfo[ENM_ISO];
            }
            if ( array_key_exists(LITP_ISO, $addInfo) ) {
                $upgradeInfo[LITP_ISO] = $addInfo[LITP_ISO];
            }
            if ( array_key_exists('from_ver', $addInfo) ) {
                $upgradeInfo['from_ver'] = $addInfo['from_ver'];
            }
            if ( array_key_exists('completion_status', $addInfo) ) {
                $upgradeInfo['completion_status'] = $addInfo['completion_status'];
            }
            if ( array_key_exists('release_info', $addInfo) ) {
                $upgradeInfo['release_info'] = json_decode($addInfo['release_info']);
            }
            if ( array_key_exists(INFRA_UPGRADE, $addInfo) ) {
                $upgradeInfo[INFRA_UPGRADE] = $addInfo[INFRA_UPGRADE];
            }
        } elseif ( in_array($stageExec['stage'], $upgradeStagesRH6toRH7 ) ||
            in_array($stageExec['stage'], $upgradeStagesRH6 )) {
            $upgradeInfo[$stageExec['stage']] = array();
            $treeNode = &$upgradeInfo[$stageExec['stage']];
        } else if ( $stageExec['stage'] == 'UPGRADE_CLUSTER' ) {
            $currentClusterName = $addInfo[CLUSTER];
            $upgradeInfo['clusters'][$currentClusterName] = array( 'nodes' => array() );
            $treeNode = &$upgradeInfo['clusters'][$currentClusterName];
        } else if ( $stageExec['stage'] == 'LOCK_NODE' ) {
            $nodeName = $addInfo['node'];
            # parseUpgrade has been updated to store the cluster
            # to for LOCK/UNLOCK_NODE to deal with parallel upgrade of clusters
            if ( array_key_exists( CLUSTER, $addInfo ) ) {
                $nodeCluster = $addInfo[CLUSTER];
            } else {
                $nodeCluster = $currentClusterName;
            }
            $upgradeInfo['clusters'][$nodeCluster]['nodes'][$nodeName] = array( STAGES => array( LOCK => array() ) );
            $treeNode = &$upgradeInfo['clusters'][$nodeCluster]['nodes'][$nodeName][STAGES][LOCK];
        } else if ( $stageExec['stage'] == 'UNLOCK_NODE' ) {
            $nodeName = $addInfo['node'];
            if ( array_key_exists( CLUSTER, $addInfo ) ) {
                $nodeCluster = $addInfo[CLUSTER];
            } else {
                $nodeCluster = $currentClusterName;
            }
            $node = &$upgradeInfo['clusters'][$nodeCluster]['nodes'][$nodeName];

            if ( (is_array($node[STAGES][LOCK])) && (array_key_exists('end', $node[STAGES][LOCK])) ) {
                $node[STAGES][UPGRADE] =
                                           array( 'start' => $node[STAGES][LOCK]['end'],
                                                  'end'   => $stageExec['start'],
                                                  'duration' => strtotime($stageExec['start']) - strtotime($node[STAGES][LOCK]['end']));
            } else {
                $node[STAGES][UPGRADE] =
                                           array( "NA",
                                                  'end'   => $stageExec['start'],
                                                  'duration' => 0 );
            }

            $node[STAGES][UNLOCK] = array();
            $treeNode = &$node[STAGES][UNLOCK];
            unset($node);
        }

        if ( isset($treeNode) ) {
            if ( $debug > 1 ) { echo "<pre>treeNode\n"; print_r($treeNode); echo "</pre>\n"; }
            $treeNode['start'] = $stageExec['start'];
            if ( array_key_exists('end', $stageExec) ) {
                $treeNode['end'] = $stageExec['end'];
            }
            if ( array_key_exists('start', $stageExec) && array_key_exists('end', $stageExec) ) {
                $treeNode['duration'] = strtotime($treeNode['end']) - strtotime($treeNode['start']);
            } else {
                $treeNode['duration'] = 0;
            }
        }

        if ( $debug > 1 ) { echo "<pre>upgradeInfo\n"; print_r($upgradeInfo); echo "</pre>\n"; }
    }

    $upgradeInfo['start'] = $upgradeStartTime;
    $upgradeInfo['end'] = $upgradeEndTime;

    if ( $debug > 0 ) { echo "<pre>getUpgradeInfo: upgradeInfo\n"; print_r($upgradeInfo); echo "</pre>\n"; }

    return $upgradeInfo;
}

function getUpgradeEvents($statsDB, $site, $from, $to) {
    $statsDB->query("
(SELECT enm_upgrade_events.time AS time,
enm_upgrade_events.state AS state
FROM enm_upgrade_events, enm_upgrade_stage_names, sites
WHERE
 enm_upgrade_events.siteid = sites.id AND sites.name = '$site' AND
 enm_upgrade_events.stageid = enm_upgrade_stage_names.id AND
(enm_upgrade_stage_names.name = 'PRE_INFRASTRUCTURE_HEALTHCHECKS' OR enm_upgrade_stage_names.name = 'UPGRADE_ENM') AND
 enm_upgrade_events.state = 'START' AND enm_upgrade_events.time BETWEEN '$from' AND '$to')
UNION ALL
(SELECT enm_upgrade_events.time AS time,
enm_upgrade_events.state AS state
FROM enm_upgrade_events, enm_upgrade_stage_names, sites
WHERE
 enm_upgrade_events.siteid = sites.id AND sites.name = '$site' AND
 enm_upgrade_events.stageid = enm_upgrade_stage_names.id AND enm_upgrade_stage_names.name = 'UPGRADE_ENM' AND
 enm_upgrade_events.state = 'END' AND enm_upgrade_events.time BETWEEN '$from' AND DATE_ADD('$to', INTERVAL 2 DAY))
ORDER BY time");
    $upgrades = array();
    $currentUpgrade = array();
    $defineEnd = 1;
    while ( $row = $statsDB->getNextNamedRow() ) {
        if ( $row['state'] == 'START' ) {
            if ( $defineEnd == 1 ) {
                $currentUpgrade = array( 'start' => $row['time'] );
                $defineEnd = 0;
            }
        } else {
            if ( array_key_exists( 'start', $currentUpgrade ) ) {
                $currentUpgrade['end'] = $row['time'];
                $defineEnd = 1;
                $upgrades[] = $currentUpgrade;
            }
            $currentUpgrade = array();
        }
    }

    return $upgrades;
}

function getValue($upgradeInfo, $key) {
    if ( array_key_exists($key, $upgradeInfo) ) {
        return $upgradeInfo[$key];
    } else {
        return NULL;
    }
}

function jsonExport($statsDB, $from, $to) {
    global $site;

    $exportFields =  array ( 'start', 'end', 'completion_status', ENM_ISO, LITP_ISO, 'from_ver', 'release_info',
        'INFRA_UPGRADE' );
    $exportDurations = array( 'IMPORT_LITP_ISO', IMPORTENM_ISO, CREATE_PLAN );

    $upgrades = getUpgradeEvents($statsDB, $site, "$from 00:00:00", "$to 23:59:59");
    $export = array();
    foreach ( $upgrades as $upgrade ) {
        $upgradeInfo = getUpgradeInfo($statsDB, $upgrade);
        $outputInfo = array( 'site' => $site );
        foreach ( $exportFields as $field ) {
            $outputInfo[$field] = getValue($upgradeInfo, $field);
        }
        foreach ( $exportDurations as $field ) {
            if ( array_key_exists($field, $upgradeInfo) ) {
               $outputInfo[$field] = $upgradeInfo[$field];
            }
        }
        $clusters = array();
        foreach ($upgradeInfo['clusters'] as $clusterName => $clusterInfo ) {
            $clusters[$clusterName] = array( 'duration' => toTime($clusterInfo['duration']));
        }
        $outputInfo['clusters'] = $clusters;

        $export[] = $outputInfo;
    }

    header('Content-Type: application/json');
    echo json_encode($export,JSON_PRETTY_PRINT);
}

function mainFlow($statsDB) {
    global $site, $debug, $date;

    /* Look for the start and end of upgrades. We look for ends up to "tomorrow" end as the upgrade
       could span midnight */
    $upgrades = getUpgradeEvents($statsDB, $site, "$date 00:00:00", "$date 23:59:59");

    $upgradeIndex = 0;
    foreach ( $upgrades as $upgrade ) {
        $upgradeInfo = getUpgradeInfo($statsDB, $upgrade);

        $upgradeStartTime = $upgradeInfo["start"];
        $upgradeEndTime = $upgradeInfo["end"];
        $mdtRow = $statsDB->queryRow("
SELECT
 enm_mdt_execution.time AS start,
 DATE_ADD(enm_mdt_execution.time, INTERVAL ROUND( enm_mdt_execution.t_total / 1000, 0) SECOND) AS end,
 ROUND(enm_mdt_execution.t_total / 1000, 0) AS duration
FROM enm_mdt_execution, sites
WHERE
 enm_mdt_execution.siteid = sites.id AND sites.name = '$site' AND
 enm_mdt_execution.time BETWEEN '$upgradeStartTime' AND '$upgradeEndTime'
");
        if ( ! is_null($mdtRow) ) {
            $upgradeInfo[MDT_EXEC] = array(
                'start' => $mdtRow[0],
                'end' => $mdtRow[1],
                DURATION => $mdtRow[2]
            );
        }
        showUpgrade($upgradeInfo, $upgradeIndex);
        $upgradeIndex = $upgradeIndex + 1;
    }
}

$statsDB = new StatsDB();

if ( isset($_REQUEST['action']) ) {
    $action = $_REQUEST['action'];
    if ( $action === 'showlock' ) {
        showLockUnlock(
            $statsDB, $_REQUEST['lu'], $_REQUEST[CLUSTER], $_REQUEST['node'], $_REQUEST['from'], $_REQUEST['to']
        );
    } else if ( $action === 'export' ) {
        jsonExport($statsDB,$_REQUEST['from'],$_REQUEST['to']);
    } elseif ( $action === ACTION_SHOW_TASKS ) {
        showTasks(explode(",", requestValue(PARAM_TASK_FILES)));
    }
} else {
    mainFlow($statsDB);
}


include_once PHP_ROOT . "/common/finalise.php";

?>
