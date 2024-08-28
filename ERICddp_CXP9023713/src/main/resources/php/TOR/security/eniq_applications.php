<?php
$pageTitle = "ENIQ Applications";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";

require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

const TITLE = "title";
const SITES = "sites";
const SERVERS = "servers";
const ENM_SECSERV_SSO_INSTR = "enm_secserv_sso_instr";
const FAILURE = "failure";
const NETANWEBPLAYER = "NetanWebplayer";
const BOCMC = "BoCmc";
const BOBILAUNCHPAD = "BoBilaunchpad";
const NETWORKANALYTICSSERVER = "NetworkAnalyticsServer";
const SUCCESS = "success";

function eniqAppCols($param) {
    if ($param == SUCCESS) {
        return array(
            'IFNULL(servers.hostname,"Total")' => 'Instance',
            'SUM(enm_secserv_sso_instr.enmEniqCitrixIdtSuccess)' => 'Idt',
            'SUM(enm_secserv_sso_instr.enmEniqCitrixNetworkAnalyticsServerSuccess)' => NETWORKANALYTICSSERVER,
            'SUM(enm_secserv_sso_instr.enmEniqCitrixUdtSuccess)' => 'Udt',
            'SUM(enm_secserv_sso_instr.enmEniqCitrixWircSuccess)' => 'Wirc',
            'SUM(enm_secserv_sso_instr.enmEniqWebBoBilaunchpadSuccess)' => BOBILAUNCHPAD,
            'SUM(enm_secserv_sso_instr.enmEniqWebBoCmcSuccess)' => BOCMC,
            'SUM(enm_secserv_sso_instr.enmEniqWebNetanWebplayerSuccess)' => NETANWEBPLAYER
        );
    } elseif ($param == FAILURE) {
        return array(
            'IFNULL(servers.hostname,"Total")' => 'Instance',
            'SUM(enm_secserv_sso_instr.enmEniqCitrixIdtFailure)' => 'Idt',
            'SUM(enm_secserv_sso_instr.enmEniqCitrixNetworkAnalyticsServerFailure)' => NETWORKANALYTICSSERVER,
            'SUM(enm_secserv_sso_instr.enmEniqCitrixUdtFailure)' => 'Udt',
            'SUM(enm_secserv_sso_instr.enmEniqCitrixWircFailure)' => 'Wirc',
            'SUM(enm_secserv_sso_instr.enmEniqWebBoBilaunchpadFailure)' => BOBILAUNCHPAD,
            'SUM(enm_secserv_sso_instr.enmEniqWebBoCmcFailure)' => BOCMC,
            'SUM(enm_secserv_sso_instr.enmEniqWebNetanWebplayerFailure)' => NETANWEBPLAYER
        );
    }
}

function showTable($cols, $param) {
    global $statsDB;
    if ($param == SUCCESS) {
        $name = "Daily_Eniq_Applications_Launch_Success";
        $header = "Daily Eniq Applications Launch Success";
    } elseif ($param == FAILURE) {
        $name = "Daily_Eniq_Applications_Launch_Failure";
        $header = "Daily Eniq Applications Launch Failure";
    }
    $where = $statsDB->where(ENM_SECSERV_SSO_INSTR);
    $where .= " AND enm_secserv_sso_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP;";
    $reqBind = SqlTableBuilder::init()
              ->name("$name")
              ->tables(array(ENM_SECSERV_SSO_INSTR, StatsDB::SERVERS, StatsDB::SITES))
              ->where($where);
    foreach ($cols as $key => $value) {
        $reqBind->addSimpleColumn($key, $value);
    }
    $reqBind->paginate();
    echo $reqBind->build()->getTableWithHeader("$header", 2, "", "", "");
}

function eniqAppParams($param) {
    if ($param == SUCCESS) {
        return array(
            array(
                TITLE => 'ENM Eniq Citrix Idt Success',
                'cols' => array( "enmEniqCitrixIdtSuccess" => 'Idt' )
            ),
            array(
                TITLE => 'ENM Eniq Citrix NetworkAnalyticsServer Success',
                'cols' => array( "enmEniqCitrixNetworkAnalyticsServerSuccess" => NETWORKANALYTICSSERVER )
            ),
            array(
                TITLE => 'ENM Eniq Citrix Udt Success',
                'cols' => array( "enmEniqCitrixUdtSuccess" => 'Udt' )
            ),
            array(
                TITLE => 'ENM Eniq Web Wirc Success',
                'cols' => array( "enmEniqCitrixWircSuccess" => 'Wirc' )
            ),
            array(
                TITLE => 'ENM Eniq Web BoBilaunchpad Success',
                'cols' => array( "enmEniqWebBoBilaunchpadSuccess" => BOBILAUNCHPAD )
            ),
            array(
                TITLE => 'ENM Eniq Web BoCmc Success',
                'cols' => array( "enmEniqWebBoCmcSuccess" => BOCMC )
            ),
            array(
                TITLE => 'ENM Eniq NetanWebplayer Success',
                'cols' => array( "enmEniqWebNetanWebplayerSuccess" => NETANWEBPLAYER )
            )

        );
    } elseif ($param == FAILURE) {
        return array(
            array(
                TITLE => 'ENM Eniq Citrix Idt Failure',
                'cols' => array( "enmEniqCitrixIdtFailure" => 'Idt' )
            ),
            array(
                TITLE => 'ENM Eniq Citrix NetworkAnalyticsServer Failure',
                'cols' => array( "enmEniqCitrixNetworkAnalyticsServerFailure" => NETWORKANALYTICSSERVER )
            ),
            array(
                TITLE => 'ENM Eniq Citrix Udt Failure',
                'cols' => array( "enmEniqCitrixUdtFailure" => 'Udt' )
            ),
            array(
                TITLE => 'ENM Eniq Web CitrixWirc Failure',
                'cols' => array( "enmEniqCitrixWircFailure" => 'Wirc' )
            ),
            array(
                TITLE => 'ENM Eniq Web BoBilaunchpad Failure',
                'cols' => array( "enmEniqWebBoBilaunchpadFailure" => 'WebBoBilaunchpad' )
            ),
            array(
                TITLE => 'ENM Eniq Web BoCmc Failure',
                'cols' => array( "enmEniqWebBoCmcFailure" => BOCMC )
            ),
            array(
                TITLE => 'ENM Eniq NetanWebplayer Failure',
                'cols' => array( "enmEniqWebNetanWebplayerFailure" => NETANWEBPLAYER )
            )
        );
    }
}

function showEniqGraph($title, $apsAttributes) {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $instances = getInstances(ENM_SECSERV_SSO_INSTR);

    $row = array();
    $where = "enm_secserv_sso_instr.siteid = sites.id
              AND sites.name = '%s'
              AND enm_secserv_sso_instr.serverid = servers.id
              AND servers.hostname = '%s'";
    foreach ( $instances as $instance ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title("%s : %s")
            ->titleArgs(array('title', 'inst'))
            ->yLabel('Count')
            ->type(SqlPlotParam::STACKED_BAR)
            ->barWidth(100)
            ->makePersistent()
            ->addQuery(
                'time',
                $apsAttributes,
                array(ENM_SECSERV_SSO_INSTR, StatsDB::SITES, StatsDB::SERVERS),
                $where,
                array('site', 'inst')
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $extraArgs = "title=$title&inst=$instance";
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 480, 240, $extraArgs);
    }
    plotGraphs($row);
}

function eniqAttributes($param) {
    if ($param == SUCCESS) {
        return array(
            'enmEniqCitrixIdtSuccess' => 'Idt',
            'enmEniqCitrixNetworkAnalyticsServerSuccess' => NETWORKANALYTICSSERVER,
            'enmEniqCitrixUdtSuccess' => 'Udt',
            'enmEniqCitrixWircSuccess' => 'Wirc',
            'enmEniqWebBoBilaunchpadSuccess' => BOBILAUNCHPAD,
            'enmEniqWebBoCmcSuccess' => BOCMC,
            'enmEniqWebNetanWebplayerSuccess' => NETANWEBPLAYER
        );
    } elseif ($param == FAILURE) {
        return array(
            'enmEniqCitrixIdtFailure' => 'Idt',
            'enmEniqCitrixNetworkAnalyticsServerFailure' => NETWORKANALYTICSSERVER,
            'enmEniqCitrixUdtFailure' => 'Udt',
            'enmEniqCitrixWircFailure' => 'Wirc',
            'enmEniqWebBoBilaunchpadFailure' => BOBILAUNCHPAD,
            'enmEniqWebBoCmcFailure' => BOCMC,
            'enmEniqWebNetanWebplayerFailure' => NETANWEBPLAYER
        );
    }
}

function mainFlow() {

    /* Daily Summary table */
    showTable(eniqAppCols(SUCCESS), SUCCESS);
    showTable(eniqAppCols(FAILURE), FAILURE);

    /* Get the graphs */
    drawHeader("Eniq Applications Launch Graphs", 1, "Eniq_Applications_Launch_Success");
    $eniqsuccessAttributes = eniqAttributes('success');
    showEniqGraph('Eniq applications launch Success', $eniqsuccessAttributes);
    $eniqfailureAttributes = eniqAttributes('failure');
    showEniqGraph('Eniq applications launch Failure', $eniqfailureAttributes);
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
