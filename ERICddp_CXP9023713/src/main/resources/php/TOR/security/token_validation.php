<?php
$pageTitle = "Token Validation";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

const TITLE = "title";
const SUCCESS = "success";
const FAILURE = "failure";
const ENM_SSO_TOKEN_INSTR = "enm_sso_token_instr";

function eniqAppCols() {
    return array(
        'IFNULL(servers.hostname,"Total")' => 'Instance',
        'SUM(UserTokenValidationSuccess)' => 'User Token Validation Success',
        'SUM(UserTokenValidationFailure)' => 'User Token Validation Failure',
        'MIN(Usertokenvalidationresponsetime)' => 'Min User Token Validation ResponseTime',
        'MAX(UserTokenValidationResponseTime)' => 'Max User Token Validation ResponseTime',
        'AVG(UserTokenValidationResponseTime)' => 'Avg User Token Validation ResponseTime'
    );
}

function eniqAppParams($param) {
    if ($param == SUCCESS) {
        return array(
            array(
                SqlPlotParam::TITLE => 'User Token Validation Success',
                'cols' => array( "UserTokenValidationSuccess" => 'tokenvalidationsuccess' )
            )
        );
    } elseif ($param == FAILURE) {
        return array(
            array(
                SqlPlotParam::TITLE => 'User Token Validation Failure',
                'cols' => array( "UserTokenValidationFailure" => 'tokenvalidationfailure' )
            )
        );
    }
}

function showTable($cols) {
    global $statsDB;
    $name = "Daily_Eniq_Applications_Launch_Success";
    $header = "Daily statistics";
    $where = $statsDB->where(ENM_SSO_TOKEN_INSTR);
    $where .= " AND enm_sso_token_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP;";
    $reqBind = SqlTableBuilder::init()
              ->name("$name")
              ->tables(array(ENM_SSO_TOKEN_INSTR, StatsDB::SERVERS, StatsDB::SITES))
              ->where($where);
    foreach ($cols as $key => $value) {
        $reqBind->addSimpleColumn($key, $value);
    }
    $reqBind->paginate();
    $reqBind = $reqBind->build();

    if ( $reqBind->hasRows() ) {
        echo $reqBind->getTableWithHeader("$header", 2, "", "", "");
    }
}

function showGraphs($type) {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $table = ENM_SSO_TOKEN_INSTR;

    if ($type == SUCCESS) {
        $params = eniqAppParams(SUCCESS);
        drawHeader("Token Validation Success Count", 1, "Token_Validation_Success_Count");
    } elseif ($type == FAILURE) {
        $params = eniqAppParams(FAILURE);
        drawHeader("Token Validation Failure Count", 1, "Token_Validation_Failure_Count");
    }
    $where = "$table.siteid = sites.id AND sites.name = '%s' AND
              $table.serverid = servers.id";
    $graphs = array();
    foreach ( $params as $param ) {
        $sqlParam = SqlPlotParamBuilder::init()
                   ->title($param[TITLE])
                   ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                   ->barwidth(60)
                   ->yLabel('Count')
                   ->makePersistent()
                   ->forceLegend()
                   ->addQuery(
                       SqlPlotParam::DEFAULT_TIME_COL,
                       $param['cols'],
                       array($table, StatsDB::SITES, StatsDB::SERVERS),
                       $where,
                       array('site'),
                       "servers.hostname"
                       )
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 300 );
    }

    plotGraphs($graphs);
}

function mainFlow() {
    /* Daily Summary table */
    showTable(eniqAppCols());

    /* Get the graphs */
    showGraphs(SUCCESS);
    showGraphs(FAILURE);
}

mainFlow();
include_once PHP_ROOT . "/common/finalise.php";
