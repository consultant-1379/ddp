<?php
$pageTitle = "SSO OpenAM";

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";

const ACTIVE_SESSION = 'activeSession';
const TIME_MILLISEC = "Time (Millisec)";
const OPENAM_AUTHORIZATION = 'OpenAM Authorization Daily Statistics for PM File Access NBI';

class openAMTotalsTable extends DDPObject {
    var $cols = array(
                  array('key' => 'instance', 'label' => 'Instance'),
                  array('key' => 'AuthenticationSuccessCount', 'label' => 'Total Authentication Success Count', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'AuthenticationFailureCount', 'label' => 'Total Authentication Failure Count', 'formatter' => 'ddpFormatNumber'),
                  array('key' => 'SessionCreatedCount', 'label' => 'Total Session Created Count', 'formatter' => 'ddpFormatNumber')
      );

    var $title = "Daily Totals";

    function __construct() {
        parent::__construct("SlsDailyTotals");
    }

    function getData() {
        global $date, $site;
$sql = "
SELECT
  IFNULL(servers.hostname, 'All Instances') as instance,
  sum(enm_sso_openam_instr.AuthenticationSuccessCount) as AuthenticationSuccessCount,
  sum(enm_sso_openam_instr.AuthenticationFailureCount) as AuthenticationFailureCount,
  sum(enm_sso_openam_instr.SessionCreatedCount) as SessionCreatedCount
FROM enm_sso_openam_instr
  JOIN sites
    ON sites.id = enm_sso_openam_instr.siteid
  JOIN servers
    ON servers.id = enm_sso_openam_instr.serverid
WHERE
  sites.name = '$site'
  AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY servers.hostname WITH ROLLUP
";
    $this->populateData($sql);
    return $this->data;
    }
}

function getInstrParams() {
    return array(
        array('AuthenticationSuccessCount' => array(
            'title' => 'Authentication Success Count',
            'type' => 'tsc',
            'cols' => array('AuthenticationSuccessCount' => 'Count')
                                 ),
            'AuthenticationFailureCount' => array(
                'title' => 'Authentication Failure Count',
                'type' => 'tsc',
                'cols' => array('AuthenticationFailureCount' => 'Count')
            )
        ),
        array('AuthenticationSuccessRate' => array(
            'title' => 'Authentication Success Rate',
            'type' => 'tsc',
            'cols' => array('AuthenticationSuccessRate' => 'Count')
                                 ),
            'AuthenticationFailureRate' => array(
                'title' => 'Authentication Failure Rate',
                'type' => 'tsc',
                'cols' => array('AuthenticationFailureRate' => 'Count')
            )
        ),
        array('SessionActiveCount' => array(
            'title' => 'Session Active Count',
            'type' => 'tsc',
            'cols' => array('SessionActiveCount' => 'Count')
                                 ),
            'SessionCreatedCount' => array(
                'title' => 'Session Created Count',
                'type' => 'tsc',
                'cols' => array('SessionCreatedCount' => 'Count')
            )
        ),
        array('IdRepoCacheEntries' => array(
            'title' => 'Id Repo Cache Entries',
            'type' => 'tsc',
            'cols' => array('IdRepoCacheEntries' => 'Count')
                                 ),
            'IdRepoCacheHits' => array(
                'title' => 'Id Repo Cache Hits',
                'type' => 'tsc',
                'cols' => array('IdRepoCacheHits' => 'Count')
            )
        ),
        array('IdRepoGetRqts' => array(
            'title' => 'Id Repo Get Requests',
            'type' => 'tsc',
            'cols' => array('IdRepoGetRqts' => 'Count')
                                 ),
            'IdRepoSearchCacheHits' => array(
                'title' => 'Id Repo Search Cache Hits',
                'type' => 'tsc',
                'cols' => array('IdRepoSearchCacheHits' => 'Count')
            )
        ),
        array('IdRepoSearchRqts' => array(
            'title' => 'Id Repo Search Requests',
            'type' => 'tsc',
            'cols' => array('IdRepoSearchRqts' => 'Count')
                                 )
        )
    );
}

function plotInstrGraphs($instrParams) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            $sqlParam = array(
                'title' => $instrGraphParamName['title'],
                'ylabel' => 'Count',
                'useragg' => 'true',
                'persistent' => 'true',
                'type' => $instrGraphParamName['type'],
                'forcelegend' => 'true',
                'querylist' => array(
                    array (
                           'timecol' => 'time',
                           'whatcol' => $instrGraphParamName['cols'],
                           'tables' => "sites, enm_sso_openam_instr, servers",
                           'multiseries' => 'servers.hostname',
                           'where' => "sites.id = enm_sso_openam_instr.siteid"
                                      . " AND sites.name = '%s'"
                                      . " AND servers.id = enm_sso_openam_instr.serverid",
                           'qargs' => array('site')
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

function buildGraphs($sectionTitle, $tableName, $graphParams, $graphType, $helpBubbleName, $yLabelType) {
    global $date;
    drawHeader($sectionTitle, HEADER_2, $helpBubbleName);
    $sqlParamWriter = new SqlPlotParam();
    $where = "$tableName.siteid = sites.id AND sites.name = '%s' AND $tableName.serverid = servers.id";
    $dbTables = array( $tableName, StatsDB::SITES, StatsDB::SERVERS );
    $row = array();
    foreach ( $graphParams as $title ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($title)
            ->type($graphType)
            ->yLabel($yLabelType)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array( $title => $title ),
                $dbTables,
                $where,
                array('site'),
                SqlPlotParam::SERVERS_HOSTNAME
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 750, 350);
    }
    plotGraphs($row);
}

function instrAppGraphParams($dbTable) {
    buildGraphs(
        'Active Session Count',
        $dbTable,
        array(
            ACTIVE_SESSION
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        ACTIVE_SESSION,
        'Count'
    );
    buildGraphs(
        'Authentication Login Count',
        $dbTable,
        array(
            'localFailedUserAuth',
            'localSuccessUserAuth',
            'remoteFailedUserAuth',
            'remoteSuccessUserAuth',
            'unknownFailedUserAuth'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'AuthLogin',
        'Count'
    );
    buildGraphs(
        'Authentication PAM Login Count',
        $dbTable,
        array(
            'localFailedUserPamAuth',
            'localSuccessUserPamAuth',
            'remoteFailedUserPamAuth',
            'remoteSuccessUserPamAuth',
            'unknownFailedUserPamAuth'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'AuthPamLogin',
        'Count'
    );
    buildGraphs(
        'Local Login Response Time',
        $dbTable,
        array(
            'minLocalLoginResponseTime',
            'avgLocalLoginResponseTime',
            'maxLocalLoginResponseTime'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'localLogin',
        TIME_MILLISEC
    );
    buildGraphs(
        'Remote Login Response Time',
        $dbTable,
        array(
            'minRemoteLoginResponseTime',
            'avgRemoteLoginResponseTime',
            'maxRemoteLoginResponseTime'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'remoteLogin',
        TIME_MILLISEC
    );
    buildGraphs(
        'Local PAM Login Response Time',
        $dbTable,
        array(
            'minLocalPamResponseTime',
            'avgLocalPamResponseTime',
            'maxLocalPamResponseTime'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'localPamLogin',
        TIME_MILLISEC
    );
    buildGraphs(
        'Remote PAM Login Response Time',
        $dbTable,
        array(
            'minRemotePamResponseTime',
            'avgRemotePamResponseTime',
            'maxRemotePamResponseTime'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'remotePamLogin',
        TIME_MILLISEC
    );
    buildGraphs(
        'Logout Success Count',
        $dbTable,
        array(
            'logoutSuccessCount',
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'logoutSuccessCount',
        'Count'
    );
    buildGraphs(
        'PAM Validation Count',
        $dbTable,
        array(
            'pamValidateErrorCount',
            'pamValidateSuccessCount'
        ),
        SqlPlotParam::TIME_SERIES_COLLECTION,
        'pamValidationCount',
        'Count'
    );
}

function mainFlow() {
    global $statsDB;
    $dbTable = 'enm_sso_app_openam_instr';
    $showData = $statsDB->hasData($dbTable);
    $authorizationData = $statsDB->hasData('enm_open_am_authorization');
    if ( $showData ) {
        $links[] = makeAnchorLink('activeSession_anchor', "Active Session Count" );
        $links[] = makeAnchorLink('AuthLogin_anchor', "Authentication Login Count" );
        $links[] = makeAnchorLink('AuthPamLogin_anchor', "Authentication PAM Login Count");
        $links[] = makeAnchorLink('localLogin_anchor', "Local Login Response Time");
        $links[] = makeAnchorLink('remoteLogin_anchor', "Remote Login Response Time");
        $links[] = makeAnchorLink('localPamLogin_anchor', "Local PAM Login Response Time");
        $links[] = makeAnchorLink('remotePamLogin_anchor', "Remote PAM Login Response Time");
        $links[] = makeAnchorLink('logoutSuccessCount_anchor', "Logout Success Count");
        $links[] = makeAnchorLink('pamValidationCount_anchor', "PAM Validation Count");
    }
    if ( $authorizationData ) {
        $links[] = makeAnchorLink('openAMAuthorizationTotal', OPENAM_AUTHORIZATION);
        $links[] = makeAnchorLink('authorizationGraphs', "Authorization");
    }
    echo makeHTMLList($links);
    if ( $statsDB->hasData('enm_sso_openam_instr') ) {
        drawHeaderWithHelp(
            "OpenAM Daily Statistics",
            2,
            "openAMTotalsTableHelp",
            "DDP_Bubble_219_ENM_SSO_OpenAM_Daily_Totals"
        );
        $totalsTable = new openAMTotalsTable();
        echo $totalsTable->getClientSortableTableStr();
        echo addLineBreak();
        $instrGraphParams = getInstrParams();
        plotInstrGraphs($instrGraphParams);
    } elseif ( $statsDB->hasData('enm_sso_app_openam_instr') ) {
        $table = new ModelledTable( "TOR/security/enm_sso_app_openam_instr", 'openAM_dailyStatisticsTable' );
        echo $table->getTableWithHeader("OpenAM Daily Statistics");
    }
    echo addLineBreak();
    if ( $showData ) {
        instrAppGraphParams($dbTable);
    }
    if ( $authorizationData ) {
        drawHeader(OPENAM_AUTHORIZATION, 2, 'openAMAuthorizationTotal');
        $table = new ModelledTable( "TOR/security/enm_open_am_authorization_instr", 'OpenAMAuthorizationTotal' );
        echo $table->getTable(OPENAM_AUTHORIZATION);

        drawHeader('Authorization of PM File Access NBI', 2, 'authorizationGraphs');
        getGraphsFromSet('all', $graphs, 'TOR/security/enm_open_am_authorization_instr_graphs');
        plotGraphs($graphs);
    }
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";

