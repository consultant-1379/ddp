<?php
$pageTitle = "ECI NBI Mediation";

include_once "../../common/init.php";
include_once PHP_ROOT . "/common/graphFunctions.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';
const STARTECISUPERVISION = 'startEciSupervision';
const STARTECISYNCHRONIZATION = 'startEciSynchronization';
const SERVERSHOSTNAME = 'servers.hostname';
const ENM_MSSNMPCM_INSTR ='enm_mssnmpcm_instr';
const ECI_CM_SUPERVISION_AND_SYNC_TOTALS = 'ECI_CM_Supervision_And_Sync_Totals';

function getECISyncStatsData($cols) {
    global $statsDB;
    $title = "ECI Sync Stats";
    $where = $statsDB->where("enm_mssnmpcm_eci_syncstat");
    $where .= " AND enm_msInstances.id = enm_mssnmpcm_eci_syncstat.msInstanceid AND
                enm_mssnmpcm_eci_syncstat.serverid=servers.id";
    $reqBind = SqlTableBuilder::init()
              ->name("mssnmpcm_eci_syncstat")
              ->tables(array('enm_mssnmpcm_eci_syncstat', 'enm_msInstances', StatsDB::SITES, StatsDB::SERVERS))
              ->where($where);
    foreach ($cols as $key => $value) {
        $reqBind->addSimpleColumn($key, $value);
    }
    $reqBind->paginate();
    echo $reqBind->build()->getTableWithHeader($title, 2, '');
}

function getECICMSyncStatsData() {
    global $statsDB;
    $title = "ECI Supervision And Sync Totals";
    $where = $statsDB->where(ENM_MSSNMPCM_INSTR);
    $where .= " AND (enm_mssnmpcm_instr.startEciSupervision != 0 OR enm_mssnmpcm_instr.startEciSynchronization != 0)
                AND enm_mssnmpcm_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP";
    $reqBind = SqlTableBuilder::init()
              ->name(ECI_CM_SUPERVISION_AND_SYNC_TOTALS)
              ->tables(array(ENM_MSSNMPCM_INSTR, StatsDB::SITES, StatsDB::SERVERS))
              ->where($where)
              ->addSimpleColumn("IFNULL(".SERVERSHOSTNAME.",'Totals')", 'Mediation Instance')
              ->addSimpleColumn("sum(".STARTECISUPERVISION.")", 'Supervisions')
              ->addSimpleColumn("sum(".STARTECISYNCHRONIZATION.")", 'Synchronizations')
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader($title, 2, '');
}

function getParams() {
    return array(
            array(
                TITLE => 'Supervisions',
                'cols' => array( STARTECISUPERVISION => 'Start Eci Supervision' )
            ),
            array(
                TITLE => 'Synchronizations',
                'cols' => array( STARTECISYNCHRONIZATION => 'Start Eci Synchronization' )
            )
    );
}

function showECICMGraphs() {
    global $date;

    drawHeader("ECI Instrumentation", 1, "ECI_CM_Supervision_And_Sync_anchor");

    $params = getParams();

    $sqlParamWriter = new SqlPlotParam();

    $where = "enm_mssnmpcm_instr.siteid = sites.id AND sites.name = '%s' AND
              enm_mssnmpcm_instr.serverid = servers.id";

    $graphs = array();

    foreach ( $params as $param ) {
        $sqlParam = SqlPlotParamBuilder::init()
              ->title($param[TITLE])
              ->type(SqlPlotParam::STACKED_BAR)
              ->barwidth(60)
              ->yLabel('')
              ->makePersistent()
              ->forceLegend()
              ->addQuery(
                  SqlPlotParam::DEFAULT_TIME_COL,
                  $param['cols'],
                  array(ENM_MSSNMPCM_INSTR, StatsDB::SITES, StatsDB::SERVERS),
                  $where,
                  array('site'),
                  'servers.hostname'
                  )
              ->build();

        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 300 );
    }

    plotGraphs($graphs);
}

function eciSyncStatsCols() {
    return array(
                SERVERSHOSTNAME => 'Mediation Instance',
                'mstype' => 'Type',
                'TIMEDIFF(time(time), SEC_TO_TIME(ROUND(duration/1000)))' => 'Start',
                'time(time)' => 'End',
                'SEC_TO_TIME(ROUND(duration/1000))' => 'Sync Duration',
                'enm_msInstances.name' => 'MS Instance',
                'nesAdded' => 'NEs Added',
                'nesDeleted' => 'NEs Deleted',
                'nesInFile' => 'Total NEs',
                'msSyncStaus' => 'Status'
    );
}
function mainFlow( $statsDB) {
    global $statsDB;

    $cmEditURLs = array();
    $cmEditURLs[] = makeAnchorLink(ECI_CM_SUPERVISION_AND_SYNC_TOTALS, 'ECI Supervision And Sync Totals');
    $cmEditURLs[] = makeAnchorLink("ECI_CM_Supervision_And_Sync_anchor", 'ECI Instrumentation');
    echo makeHTMLList($cmEditURLs);

    getECISyncStatsData(eciSyncStatsCols());
    getECICMSyncStatsData();

    if ( $statsDB->hasData( ENM_MSSNMPCM_INSTR ) ) {
        showECICMGraphs();
        echo addLineBreak(2);
    }

}

mainFlow( $dbTable);

include_once PHP_ROOT . "/common/finalise.php";
