<?php
$pageTitle = "Single Logon Service";

$YUI_DATATABLE = true;

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once 'HTML/Table.php';

class ProcessingDailyTotalsTable extends DDPObject {
    var $cols = array(
                  array('key' => 'inst', 'label' => 'Instance'),
                  array('key' => 'credentialManagerGeneratePKCS12CredentialCalls', 'label' => 'PKCS12 Credentials Calls', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'credentialManagerGeneratePKCS12CredentialCallsTotalTime', 'label' => 'PKCS12 Credentials Calls Time', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'credentialManagerGenerateXMLCredentialCalls', 'label' => 'XML Credentials Calls', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'credentialManagerGenerateXMLCredentialCallsTotalTime', 'label' => 'XML Credentials Time', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'credentialManagerListUsersTotalTime', 'label' => 'List Users Time', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'credentialManagerRevokeCredentialsTotalTime', 'label' => 'Revoke Credentials Time', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'generateCredentialsErrors', 'label' => 'Credentials Errors', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'generateCredentialsRequests', 'label' => 'Credentials Requests', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'generateCredentialsTotalTime', 'label' => 'Credentials Requests Time', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'listUsersRequests', 'label' => 'List Users with Credentials', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'listUsersTotalTime', 'label' => 'List Users with Credentials Time', 'formatter' => 'ddpFormatNumber')
      );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("SlsDailyTotals");
    }

    function getData() {
        global $date, $site;
$sql = "
SELECT
 IFNULL(servers.hostname,'Totals') AS inst,
 SUM(esi.credentialManagerGeneratePKCS12CredentialCalls) AS credentialManagerGeneratePKCS12CredentialCalls,
 SUM(esi.credentialManagerGeneratePKCS12CredentialCallsTotalTime) AS credentialManagerGeneratePKCS12CredentialCallsTotalTime,
 SUM(esi.credentialManagerGenerateXMLCredentialCalls) AS credentialManagerGenerateXMLCredentialCalls,
 SUM(esi.credentialManagerGenerateXMLCredentialCallsTotalTime) AS credentialManagerGenerateXMLCredentialCallsTotalTime,
 SUM(esi.credentialManagerListUsersTotalTime) AS credentialManagerListUsersTotalTime,
 SUM(esi.credentialManagerRevokeCredentialsTotalTime) AS credentialManagerRevokeCredentialsTotalTime,
 SUM(esi.generateCredentialsErrors) AS generateCredentialsErrors,
 SUM(esi.generateCredentialsRequests) AS generateCredentialsRequests,
 SUM(esi.generateCredentialsTotalTime) AS generateCredentialsTotalTime,
 SUM(esi.listUsersRequests) AS listUsersRequests,
 SUM(esi.listUsersTotalTime) AS listUsersTotalTime
FROM
 enm_secserv_sls_instr esi, sites, servers
WHERE
 esi.siteid = sites.id
 AND sites.name = '$site'
 AND esi.serverid = servers.id
 AND esi.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname WITH ROLLUP";
    $this->populateData($sql);
    return $this->data;
    }
}

function showTotalRequestsGraph()
{
    global $date;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();

    $sqlParam = array(
        'title' => 'Total requests',
        'ylabel' => 'Count',
        'useragg' => 'true',
        'persistent' => 'true',
        'type' => 'sb',
        'sb.barwidth' => 60,
        'querylist' => array(
            array (
                'timecol' => 'time',
                'whatcol' => array('(IFNULL(generateCredentialsRequests,0)+IFNULL(listUsersRequests,0)+IFNULL(revokeCredentialsRequests,0))' => 'Total Requests'),
                'tables' => "enm_secserv_sls_instr, sites, servers",
                'multiseries' => 'servers.hostname',
                'where' => "enm_secserv_sls_instr.siteid = sites.id AND sites.name = '%s'  AND enm_secserv_sls_instr.serverid = servers.id",
                'qargs' => array( 'site' )
                )
            )
    );

    $id = $sqlParamWriter->saveParams($sqlParam);
    $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showSLSGraphs()
{
    global $date;
    $graphTable = new HTML_Table("border=0");
    $sqlParamWriter = new SqlPlotParam();
    /* SLS Instrumentation Graphs */
    $instrGraphParams = array(
        array('averagegeneratecredentialrequesttime' => array(
            'title' => 'Average generate credential request time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols' => array('(generateCredentialsTotalTime) / (generateCredentialsRequests)'  => 'averagegeneratecredentialrequesttime')
        ),
        'averagelistusersrequesttime' => array(
            'title' => 'Average list users request time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols'=> array('(listUsersTotalTime) / (listUsersRequests)' => 'averagelistusersrequesttime')
                              )
        ),
        array('averagerevokecredentialrequesttime' => array(
            'title' => 'Average revoke credential request time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols' => array('(revokeCredentialsTotalTime) / (revokeCredentialsRequests)' => 'averagerevokecredentialrequesttime')
        ),
        'averagegeneratecredentialPKCS12subcalltime' => array(
            'title' => 'Average generate credential PKCS12 subcall time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols' => array('(credentialManagerGeneratePKCS12CredentialCallsTotalTime) / (credentialManagerGeneratePKCS12CredentialCalls)' => 'averagegeneratecredentialPKCS12subcalltime')
                                    )
        ),
        array('averagegeneratecredentialXMLsubcalltime' => array(
            'title' => 'Average generate credential XML subcall time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols' => array('(credentialManagerGenerateXMLCredentialCallsTotalTime) / (credentialManagerGenerateXMLCredentialCalls)' => 'averagegeneratecredentialXMLsubcalltime')
        ),
        'averagelistuserssubcalltime' => array(
            'title' => ' Average list users subcall time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols' => array('(credentialManagerListUsersTotalTime) / (listUsersRequests)' => 'averagelistuserssubcalltime')
                                     )
        ),
        	array('averagerevolecredentialssubcalltime' => array(
            'title' => 'Average revole credentials subcall time',
            'ylabel' => 'Time (MilliSec)',
            'type' => 'tsc',
            'cols' => array('(credentialManagerRevokeCredentialsTotalTime) / (revokeCredentialsRequests)' => 'averagerevolecredentialssubcalltime')
        ),
        'percentageshareofPKIcallsforgeneratecredentials' => array(
            'title' => ' Percentage share of PKI calls for generate credentials',
            'ylabel' => 'Percent(%)',
            'type' => 'tsc',
            'cols' => array('(((IFNULL(credentialManagerGeneratePKCS12CredentialCallsTotalTime,0)+IFNULL(credentialManagerGenerateXMLCredentialCallsTotalTime,0)) / generateCredentialsTotalTime) * 100)' => 'percentageshareofPKIcallsforgeneratecredentials')
                                     )
        ),
	array('PercentageshareofPKIcallsforlistusers' => array(
            'title' => 'Percentage share of PKI calls for list users',
            'ylabel' => 'Percent(%)',
            'type' => 'tsc',
            'cols' => array('((credentialManagerListUsersTotalTime/listUsersTotalTime) * 100)' => 'PercentageshareofPKIcallsforlistusers')
        )
        )
    );

    foreach ( $instrGraphParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                'title' => $instrGraphParamName['title'],
                'ylabel' => $instrGraphParamName['ylabel'],
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => $instrGraphParamName['type'],
                'querylist' => array(
                    array (
                        'timecol' => 'time',
                        'whatcol' => $instrGraphParamName['cols'],
                        'tables' => "enm_secserv_sls_instr, sites, servers",
                        'multiseries' => 'servers.hostname',
                        'where' => "enm_secserv_sls_instr.siteid = sites.id AND sites.name = '%s'  AND enm_secserv_sls_instr.serverid = servers.id",
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
    global $date, $site;
    /* Daily Totals table */
    $SlsDailyTotals = "DDP_Bubble_201_ENM_Security_SingleLogonService_DailyTotalsHelp";
    drawHeaderWithHelp("Daily Totals", 1, "SlsDailyTotals", $SlsDailyTotals);

    $dailyTotalsTable = new ProcessingDailyTotalsTable();
    echo $dailyTotalsTable->getClientSortableTableStr();

    echo "<ul>\n";
    echo "<li>Generic JMX\n";
    echo "<ul>\n";
    echo "<li><a href=\"" .  makeGenJmxLink("securityservice"). "\">Single Logon Service</a></li>\n";
    echo "</ul>\n";
    echo " </li>\n";
    echo "</ul>\n";

    $slsInstrumentationHelp = "DDP_Bubble_202_ENM_Security_SingleLogonService_InstrumentationHelp";
    drawHeaderWithHelp("Instrumentation", 1, "slsInstrumentationHelp", $slsInstrumentationHelp);
    showTotalRequestsGraph();
    showSLSGraphs();
}

mainFlow();
include PHP_ROOT . "/common/finalise.php";
?>

