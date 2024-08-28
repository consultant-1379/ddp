<?php
$pageTitle = "LCM Statistics";

$YUI_DATATABLE = true;

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once 'HTML/Table.php';

const SGSN_LICENSE_USAGE = 'sgsnLicenseUsage';
const ERBS_LICENSE_USAGE = 'erbsLicenseUsage';
const MGW_LICENSE_USAGE = 'mgwLicenseUsage';
const R6000_LICENSE_USAGE = 'r6000LicenseUsage';
const SGSN_LICENSE_PLUGIN_USAGE = 'sgsnLicensePluginUsage';
const ERBS_LICENSE_PLUGIN_USAGE = 'erbsLicensePluginUsage';
const MGW_LICENSE_PLUGIN_USAGE = 'mgwLicensePluginUsage';
const SGSN_USAGE_COLLECTING_TIME = 'sgsnUsageCollectingTime';
const ERBS_USAGE_COLLECTING_TIME = 'erbsUsageCollectingTime';
const MGW_USAGE_COLLECTING_TIME = 'mgwUsageCollectingTime';
const R6000_USAGE_COLLECTING_TIME = 'r6000UsageCollectingTime';
const TIMES_OF_SGSN_USAGE_TRIGGERED = 'timesOfSgsnUsageTriggered';
const TIMES_OF_ERBS_USAGE_TRIGGERED = 'timesOfErbsUsageTriggered';
const TIMES_OF_MGW_USAGE_TRIGGERED = 'timesOfMgwUsageTriggered';
const TIMES_OF_R6000_USAGE_TRIGGERED = 'timesOfR6000UsageTriggered';
const COUNT_LABEL = 'count';

function showSentinelGraph() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    /* E2E Instrumentation Graphs */
    $e2eInstrumentationSentinelHelp = "DDP_Bubble_34_ENM_LCM_Sentinel";
    drawHeaderWithHelp("Sentinel", 1, "e2eInstrumentationSentinelHelp", $e2eInstrumentationSentinelHelp);

    $sqlParam = array(
        SqlPlotParam::TITLE => 'Sentinel Connected',
        SqlPlotParam::Y_LABEL => 'Status',
        'useragg' => 'true',
        'persistent' => 'true',
        'type' => 'sb',
        'sb.barwidth' => 60,
        'querylist' => array(
            array (
                'timecol' => 'time',
                'whatcol' => array('sentinelConnected' => 'sentinelConnected'),
                'tables' => "enm_lcmserv_instr, sites, servers",
                'multiseries' => 'servers.hostname',
                'where' => "enm_lcmserv_instr.siteid = sites.id AND sites.name = '%s'  AND
                           enm_lcmserv_instr.serverid = servers.id",
                'qargs' => array( 'site' )
                )
            )
    );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showLcmGraphs() {
    global $debug, $webargs, $php_webroot, $date, $site;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    /* E2E Instrumentation Graphs */
    $e2eInstrumentationHelp = "DDP_Bubble_33_ENM_LCM_Statistics";
    drawHeaderWithHelp("License Usage", 1, "e2eInstrumentationHelp", $e2eInstrumentationHelp);
    $instrGraphParams = array(
        array(SGSN_LICENSE_USAGE => array(
            SqlPlotParam::TITLE => 'SGSN License Usage',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(SGSN_LICENSE_USAGE  => SGSN_LICENSE_USAGE)
        ),
        ERBS_LICENSE_USAGE => array(
            SqlPlotParam::TITLE => 'LTE License Usage',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols'=> array(ERBS_LICENSE_USAGE => ERBS_LICENSE_USAGE)
        ),
        MGW_LICENSE_USAGE => array(
            SqlPlotParam::TITLE => 'MGW License Usage',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols'=> array(MGW_LICENSE_USAGE => MGW_LICENSE_USAGE)
        ),
        R6000_LICENSE_USAGE => array(
            SqlPlotParam::TITLE => 'R6000 License Usage',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols'=> array(R6000_LICENSE_USAGE => R6000_LICENSE_USAGE)
        )
        ),
        array(SGSN_LICENSE_PLUGIN_USAGE => array(
            SqlPlotParam::TITLE => 'SGSN License Plugin Usage',
            SqlPlotParam::Y_LABEL => 'KSAU',
            'type' => 'sb',
            'cols' => array(SGSN_LICENSE_PLUGIN_USAGE => SGSN_LICENSE_PLUGIN_USAGE)
        ),
        ERBS_LICENSE_PLUGIN_USAGE => array(
            SqlPlotParam::TITLE => 'LTE License Plugin Usage',
            SqlPlotParam::Y_LABEL => '5MHZ Sector Carrier',
            'type' => 'sb',
            'cols' => array(ERBS_LICENSE_PLUGIN_USAGE => ERBS_LICENSE_PLUGIN_USAGE)
        ),
        MGW_LICENSE_PLUGIN_USAGE => array(
            SqlPlotParam::TITLE => 'MGW License Plugin Usage',
            SqlPlotParam::Y_LABEL => 'count',
            //need to know what label can be given here
            'type' => 'sb',
            'cols' => array(MGW_LICENSE_PLUGIN_USAGE => MGW_LICENSE_PLUGIN_USAGE)
        )
        ),
        array(SGSN_USAGE_COLLECTING_TIME => array(
            SqlPlotParam::TITLE => 'SGSN Usage Collecting Time',
            SqlPlotParam::Y_LABEL => 'Time (MilliSec)',
            'type' => 'sb',
            'cols' => array(SGSN_USAGE_COLLECTING_TIME => SGSN_USAGE_COLLECTING_TIME)
        ),
        ERBS_USAGE_COLLECTING_TIME => array(
            SqlPlotParam::TITLE => 'LTE Usage Collecting Time',
            SqlPlotParam::Y_LABEL => 'Time (MilliSec)',
            'type' => 'sb',
            'cols' => array(ERBS_USAGE_COLLECTING_TIME => ERBS_USAGE_COLLECTING_TIME)
        ),
        MGW_USAGE_COLLECTING_TIME => array(
            SqlPlotParam::TITLE => 'MGW Usage Collecting Time',
            SqlPlotParam::Y_LABEL=> 'Time (MilliSecs)',
            'type' => 'sb',
            'cols' =>  array(MGW_USAGE_COLLECTING_TIME => MGW_USAGE_COLLECTING_TIME)
        ),
        R6000_USAGE_COLLECTING_TIME => array(
            SqlPlotParam::TITLE => 'R6000 Usage Collecting Time',
            SqlPlotParam::Y_LABEL=> 'Time (MilliSecs)',
            'type' => 'sb',
            'cols' => array(R6000_USAGE_COLLECTING_TIME => R6000_USAGE_COLLECTING_TIME)
       )
       ),
       array(TIMES_OF_SGSN_USAGE_TRIGGERED => array(
            SqlPlotParam::TITLE => 'SGSN-MME License Plugin Usage Collection Count',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(TIMES_OF_SGSN_USAGE_TRIGGERED => TIMES_OF_SGSN_USAGE_TRIGGERED)
        ),
        TIMES_OF_ERBS_USAGE_TRIGGERED => array(
            SqlPlotParam::TITLE => 'LTE License Plugin Usage Collection Count',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(TIMES_OF_ERBS_USAGE_TRIGGERED => TIMES_OF_ERBS_USAGE_TRIGGERED)
        ),
        TIMES_OF_MGW_USAGE_TRIGGERED => array(
            SqlPlotParam::TITLE => 'MGW License Plugin Usage Collection Count',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(TIMES_OF_MGW_USAGE_TRIGGERED => TIMES_OF_MGW_USAGE_TRIGGERED)
        ),
        TIMES_OF_R6000_USAGE_TRIGGERED => array(
            SqlPlotParam::TITLE => 'R6000 License Plugin Usage Collection Count',
            SqlPlotParam::Y_LABEL => COUNT_LABEL,
            'type' => 'sb',
            'cols' => array(TIMES_OF_R6000_USAGE_TRIGGERED => TIMES_OF_R6000_USAGE_TRIGGERED)
        )
      )
    );

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                SqlPlotParam::TITLE => $instrGraphParamName[SqlPlotParam::TITLE],
                SqlPlotParam::Y_LABEL => $instrGraphParamName[SqlPlotParam::Y_LABEL],
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => $instrGraphParamName['type'],
                'sb.barwidth' => 60,
                'querylist' => array(
                    array (
                        'timecol' => 'time',
                        'whatcol' => $instrGraphParamName['cols'],
                        'tables' => "enm_lcmserv_instr, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_lcmserv_instr.siteid = sites.id AND sites.name = '%s'  AND
                                   enm_lcmserv_instr.serverid = servers.id",
                        'qargs' => array( 'site' )
                    )
                )
            );
           $id = $sqlParamWriter->saveParams($sqlParam);
           $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
       }
       $graphTable->addRow($row);
   }
echo $graphTable->toHTML();
}

function mainFlow() {

    $sg = requestValue('SG');

    echo "<ul>\n";
    echo "<li><a href=\"" .  makeGenJmxLink($sg) . "\">Generic JMX</a></li>\n";
    echo "</ul>\n";

    showSentinelGraph();
    showLcmGraphs();
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
