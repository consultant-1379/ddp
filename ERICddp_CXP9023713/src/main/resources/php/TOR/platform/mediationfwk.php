<?php
$pageTitle = "Mediation Framework";

require_once "../../common/init.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/Jms.php";

require_once 'HTML/Table.php';

const TITLE = 'title';
const DB = 'db';
const LABEL = 'label';
const COUNT_LABEL = 'Count';
const ROUTERPOLICY_TABLE = 'enm_routerpolicy';
const ROUTERPOLICY_NAME_TABLE = 'enm_routerpolicy_names';
const NO_BORDER = 'border=0';
const HOSTNAME_DB_COL = 'servers.hostname';

function showQueue($statsDB, $site, $date, $queue, $consumers, $mtrSrv) {
    drawHeaderWithHelp($queue, 2, $queue);

    $graphTable = new HTML_Table(NO_BORDER);

    $qGraphs = new Jms($statsDB, $site, "queue", $queue, $date, $date);
    $graphTable->addRow($qGraphs->getMessageGraphs());

    $hasMtrData = false;
    foreach ( $consumers as $consumer ) {
        if ( array_key_exists($consumer, $mtrSrv) ) {
            $hasMtrData = true;
        }
    }
    if ( $hasMtrData ) {
        $srvs = implode("','", $consumers);
        $dbTables = array( "enm_mtr_processing", StatsDB::SITES, StatsDB::SERVERS );
        $where = <<<EOQ
enm_mtr_processing.siteid = sites.id AND sites.name = '%s' AND
enm_mtr_processing.serverid = servers.id AND servers.hostname IN ( '$srvs' )
EOQ;
        $row = array();
        $sqlParamWriter = new SqlPlotParam();

        $row = array();
        $sqlParamWriter = new SqlPlotParam();

        $graphs = array(
            array(
                TITLE => "Mediation Task Requests Processed",
                DB => "n_count",
                LABEL => COUNT_LABEL
            ),
            array(
                TITLE => "Average MTR Delay (msec)",
                DB => "IF(n_count>0,t_delay/n_count,0)",
                LABEL => "Delay"
            )
        );
        foreach ( $graphs as $graph ) {
            $sqlParam = SqlPlotParamBuilder::init()
                      ->title($graph[TITLE])
                      ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                      ->yLabel('')
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array( $graph[DB] => $graph[LABEL] ),
                          $dbTables,
                          $where,
                          array( 'site', ),
                          HOSTNAME_DB_COL
                      )
                      ->forceLegend()
                      ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $link = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                640,
                240
            );
            $row[] = $link;
        }
        $graphTable->addRow($row);
    }
    echo $graphTable->toHTML();
}

function showRouting($date) {
    drawHeaderWithHelp('Routing', 1, 'routing_header');
    $dbTables = array( "enm_eventbasedclient", StatsDB::SITES, StatsDB::SERVERS );
    $where = <<<EOQ
enm_eventbasedclient.siteid = sites.id AND sites.name = '%s' AND
enm_eventbasedclient.serverid = servers.id
EOQ;
    $row = array();
    $sqlParamWriter = new SqlPlotParam();

    $graphs = array(
        array(
            TITLE => "# Select Mediation Service",
            DB => "n_selectms",
            LABEL => COUNT_LABEL
        ),
        array(
            TITLE => "Average Select Mediation Time (msec)",
            DB => "IF(n_selectms>0,t_selectms/n_selectms,0)",
            LABEL => "Time"
        )
    );
    foreach ( $graphs as $graph ) {
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title($graph[TITLE])
                  ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                  ->yLabel('')
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      array( $graph[DB] => $graph[LABEL] ),
                      $dbTables,
                      $where,
                      array( 'site', ),
                      HOSTNAME_DB_COL
                  )
                  ->forceLegend()
                  ->makePersistent()
                  ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $link = $sqlParamWriter->getImgURL(
            $id,
            "$date 00:00:00",
            "$date 23:59:59",
            true,
            640,
            320
        );
        $row[] = $link;
    }

    $graphTable = new HTML_Table(NO_BORDER);
    $graphTable->addRow($row);
    echo $graphTable->toHTML();
}

function showSelectMS($statsDB, $site, $date) {
    $statsDB = new StatsDB();
    $where = sprintf(
        "%s AND %s.policyid = %s.id",
        $statsDB->where(ROUTERPOLICY_TABLE),
        ROUTERPOLICY_TABLE,
        ROUTERPOLICY_NAME_TABLE
    );
    $dbAvgTime = sprintf(
        "ROUND( SUM(%s.t_selectms) / SUM(%s.n_selectms), 1)",
        ROUTERPOLICY_TABLE,
        ROUTERPOLICY_TABLE
    );
    $totalTable = SqlTableBuilder::init()
                ->name("selectms")
                ->tables(array(ROUTERPOLICY_TABLE, ROUTERPOLICY_NAME_TABLE, StatsDB::SITES))
                ->where($where . " GROUP BY " . ROUTERPOLICY_TABLE . '.policyid')
                ->addSimpleColumn( ROUTERPOLICY_NAME_TABLE . '.name', "Policy" )
                ->addSimpleColumn( 'SUM(' . ROUTERPOLICY_TABLE . '.n_selectms)', 'Calls')
                ->addSimpleColumn( $dbAvgTime, 'Avg Time' )
                ->build();
    if ( ! $totalTable->hasRows() ) {
        return;
    }

    echo $totalTable->getTableWithHeader("Policy Select Mediation Service");

    echo addLineBreak();

    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table(NO_BORDER);
    $graphTable->addRow(array('Policy', 'Select Calls', 'Average Time'), null, 'th');
    $graphWhere = <<<EOT
 enm_routerpolicy.siteid = sites.id AND sites.name = '%s' AND
 enm_routerpolicy.policyid = %d AND
 enm_routerpolicy.serverid = servers.id
EOT;
    $dbTables = array( ROUTERPOLICY_TABLE, StatsDB::SITES, StatsDB::SERVERS );
    $statsDB->query("
SELECT DISTINCT enm_routerpolicy_names.id AS policyid, enm_routerpolicy_names.name AS policyname
FROM enm_routerpolicy, enm_routerpolicy_names, sites
WHERE
 enm_routerpolicy.siteid = sites.id AND sites.name = '$site' AND
 enm_routerpolicy.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_routerpolicy.policyid = enm_routerpolicy_names.id
ORDER BY policyname
");
    while ( $row = $statsDB->getNextRow() ) {
        $graphRow = array( $row[1] );
        $graphs = array(
            array(
                TITLE => "# Select Mediation Service",
                DB => "n_selectms",
                LABEL => COUNT_LABEL
            ),
            array(
                TITLE => "Average Select Mediation Time (msec)",
                DB => "IF(n_selectms>0,t_selectms/n_selectms,0)",
                LABEL => "Time"
            )
        );

        foreach ( $graphs as $graph ) {
            $sqlParam = SqlPlotParamBuilder::init()
                      ->title($graph[TITLE])
                      ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
                      ->yLabel('')
                      ->addQuery(
                          SqlPlotParam::DEFAULT_TIME_COL,
                          array( $graph[DB] => $graph[LABEL] ),
                          $dbTables,
                          $graphWhere,
                          array( 'site', 'policyid' ),
                          HOSTNAME_DB_COL
                      )
                      ->forceLegend()
                      ->makePersistent()
                      ->build();
            $id = $sqlParamWriter->saveParams($sqlParam);
            $link = $sqlParamWriter->getImgURL(
                $id,
                "$date 00:00:00",
                "$date 23:59:59",
                true,
                480,
                240,
                'policyid=' . $row[0]
            );
            $graphRow[] = $link;
        }
        $graphTable->addRow($graphRow);
    }

    echo $graphTable->toHTML();
}

function getOutgoingQs($dataFile, $ebcInstances) {
    debugMsg("getOutgoingQs: ebcInstances", $ebcInstances);
    $config = json_decode(file_get_contents($dataFile), true);
    $outQs = array();
    foreach ( $config['destinations']['queue'] as $qName => $qConfig ) {
        $isEbcProducer = false;
        foreach ( $qConfig['producers'] as $producer ) {
            if ( array_key_exists($producer['client'], $ebcInstances) ) {
                $isEbcProducer = true;
            }
        }
        if ( $isEbcProducer ) {
            $outQs[$qName] = array();
            foreach ( $qConfig['consumers'] as $consumer ) {
                $outQs[$qName][] = $consumer['client'];
            }
        }
    }
    ksort($outQs);

    return $outQs;
}

function mainFlow() {
    global $site, $date, $debug, $rootdir;

    $mtrSrv = array();
    $statsDB = new StatsDB();
    $statsDB->query("
SELECT DISTINCT servers.hostname
FROM sites, servers, enm_mtr_processing
WHERE
 enm_mtr_processing.siteid = sites.id AND sites.name = '$site' AND
 enm_mtr_processing.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_mtr_processing.serverid = servers.id
");
    while ( $row = $statsDB->getNextRow() ) {
        $mtrSrv[$row[0]] = 1;
    }

    drawHeaderWithHelp('Incoming', 1, 'incoming_header');
    $srv = array_merge(
        enmGetServiceInstances($statsDB, $site, $date, "eventbasedclient"),
        enmGetServiceInstances($statsDB, $site, $date, "conscommonmed"),
        enmGetServiceInstances($statsDB, $site, $date, "medcore")
    );
    showQueue($statsDB, $site, $date, "ClusteredEventBasedMediationClient", array_keys($srv), $mtrSrv);

    showRouting($date);

    showSelectMS($statsDB, $site, $date);

    $dataFile = $rootdir . "/jms/config.json";
    if ( file_exists($dataFile) ) {
        $outQs = getOutgoingQs($dataFile, $srv);
        if ( $debug ) {
            echo "<pre>outQs:";
            print_r($outQs);
            echo "</pre>\n";
        }

        drawHeaderWithHelp('Outgoing', 1, 'outgoing_header');
        $listItems = array();
        foreach ( $outQs as $qName => $qClients ) {
            $listItems[] = makeAnchorLink($qName, $qName);
        }
        echo makeHTMLList($listItems);

        foreach ( $outQs as $qName => $qClients ) {
            showQueue($statsDB, $site, $date, $qName, $qClients, $mtrSrv);
        }
    }
}

mainFlow();

require_once PHP_ROOT . "/common/finalise.php";
