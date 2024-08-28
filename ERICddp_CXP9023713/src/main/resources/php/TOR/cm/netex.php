<?php
$pageTitle = "Network Explorer";

include "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/GenericJMX.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

const CNT = 'Count';
const LABEL_COUNT = "Labels Count";

function privateNetworkParams() {
    return array(
        "privatePrivateNetwork" => "Private Network",
        "publicPrivateNetwork" => "Public Network"
    );
}

function topologyCollectionParams() {
    return array(
        "labelsCount" => LABEL_COUNT
    );
}

function getInstrParamsTopologySearch() {
    $tables = "enm_netexserv_topologysearch_instr, sites, servers";
    $where = "enm_netexserv_topologysearch_instr.siteid = sites.id AND sites.name = '%s' AND
              enm_netexserv_topologysearch_instr.serverid = servers.id";
    $instrGraphParamsTopologySearch = array(
        array('objectsResponseTime' => array(
            SqlPlotParam::TITLE => 'Objects Response Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('objectsTotalResponseTime' => 'Total objectsTotalResponseTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                          ),
            'cmTime' => array(
            SqlPlotParam::TITLE => 'Cm Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('totalCmTime' => 'Total totalCmTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                          )
        ),
        array('mergeQueryResultsTotalTime' => array(
            SqlPlotParam::TITLE => 'Merge Query Results Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('mergeQueryResultsTotalTime' => 'Total mergeQueryResultsTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                          ),
            'totalSearchTime' => array(
            SqlPlotParam::TITLE => 'Search Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('totalSearchTime' => 'Total totalSearchTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                          )
        )
    );
    return $instrGraphParamsTopologySearch;
}

function getInstrParamsTopologyCollection() {
    $tables = "enm_netexserv_topologycollection_instr, sites, servers";
    $where = "enm_netexserv_topologycollection_instr.siteid = sites.id AND sites.name = '%s' AND
              enm_netexserv_topologycollection_instr.serverid = servers.id";

    $instrGraphParams = array(
        array('collectionTotalTime' => array(
            SqlPlotParam::TITLE => 'Collection Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('collectionTotalTime' => 'Total collectionTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                        ),
        'collectionDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Collection Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('collectionDatabaseTotalTime' => 'Total collectionDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                         )
        ),
        array('collectionWithContentsTotalTime' => array(
            SqlPlotParam::TITLE => 'Collection With Contents Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('collectionWithContentsTotalTime' =>
                                            'Total collectionWithContentsTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                   ),
        'collectionWithContentsDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Collection With Contents Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('collectionWithContentsDatabaseTotalTime' =>
                                            'Total collectionWithContentsDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                      )
        ),
        array('createCollectionTotalTime' => array(
            SqlPlotParam::TITLE => 'Create Collection Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('createCollectionTotalTime' => 'Total createCollectionTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                             ),
        'createCollectionDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Create Collection Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('createCollectionDatabaseTotalTime' =>
                                            'Total createCollectionDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                )
        ),
        array('updateCollectionTotalTime' => array(
            SqlPlotParam::TITLE => 'Update Collection Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('updateCollectionTotalTime' => 'Total updateCollectionTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                              ),
        'updateCollectionDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Update Collection Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('updateCollectionDatabaseTotalTime' =>
                                            'Total updateCollectionDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                               )
        ),
        array('deleteCollectionTotalTime' => array(
            SqlPlotParam::TITLE => 'Delete Collection Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('deleteCollectionTotalTime' => 'Total deleteCollectionTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                              ),
        'deleteCollectionDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Delete Collection Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('deleteCollectionDatabaseTotalTime' =>
                                            'Total deleteCollectionDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                               )
        ),
        array('collectionBatchesTotalTime' => array(
            SqlPlotParam::TITLE => 'Collection Batches Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('collectionBatchesTotalTime' => 'Total collectionBatchesTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                               ),
        'savedSearchesTotalTime' => array(
            SqlPlotParam::TITLE => 'Saved Searches Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('savedSearchesTotalTime' => 'Total savedSearchesTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                    )
        ),
        array('savedSearchesDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Saved Searches Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('savedSearchesDatabaseTotalTime' => 'Total savedSearchesDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                  ),
        'createSavedSearchTotalTime' => array(
            SqlPlotParam::TITLE => 'Create Saved Search Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('createSavedSearchTotalTime' => 'Total createSavedSearchTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                        )
        ),
        array('createSavedSearchDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Create Saved Search Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('createSavedSearchDatabaseTotalTime' =>
                                            'Total createSavedSearchDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                      ),
        'deleteSavedSearchTotalTime' => array(
            SqlPlotParam::TITLE => 'Delete Saved Search Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('deleteSavedSearchTotalTime' => 'Total deleteSavedSearchTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                        )
        ),
        array('deleteSavedSearchDatabaseTotalTime' => array(
            SqlPlotParam::TITLE => 'Delete Saved Search Database Time',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::WHAT_COL => array('deleteSavedSearchDatabaseTotalTime' =>
                                            'Total deleteSavedSearchDatabaseTotalTime'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                      ),
        'autoGeneratedCollectionsCount' => array(
            SqlPlotParam::TITLE => 'Auto Generated Collection Count',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('autoGeneratedCollectionsCount' => 'Total autoGeneratedCollectionsCount'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                            )
        ),
        array('publicSavedSearchesCount' => array(
            SqlPlotParam::TITLE => 'Public Saved Search Count',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('publicSavedSearchesCount' => 'Total publicSavedSearchesCount'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                            ),
        'privateSavedSearchesCount' => array(
            SqlPlotParam::TITLE => 'Private Saved Search Count',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('privateSavedSearchesCount' => 'Total privateSavedSearchesCount'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                       )
        ),
        array('publicCollectionsCount' => array(
            SqlPlotParam::TITLE => 'Public Collection Count',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('publicCollectionsCount' => 'Total publicCollectionsCount'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                          ),
        'privateCollectionsCount' => array(
            SqlPlotParam::TITLE => 'Private Collection Count',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('privateCollectionsCount' => 'Total privateCollectionsCount'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                     )
        ),
        array('createCollectionDatabaseLargestSize' => array(
            SqlPlotParam::TITLE => 'Create Collection Largest Size',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('createCollectionDatabaseLargestSize' =>
                                            'Total createCollectionDatabaseLargestSize'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                       ),
        'createCollectionDatabaseMedianSize' => array(
            SqlPlotParam::TITLE => 'Create Collection Median Size',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('createCollectionDatabaseMedianSize' =>
                                            'Total createCollectionDatabaseMedianSize'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                 )
        ),
        array('createCollectionDatabaseAverageSize' => array(
            SqlPlotParam::TITLE => 'Create Collection Average Size',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('createCollectionDatabaseAverageSize' =>
                                            'Total createCollectionDatabaseAverageSize'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                       ),
        'createCollectionDatabaseTotalObjectCount' => array(
            SqlPlotParam::TITLE => 'Create Collection Total Object Count',
            SqlPlotParam::TYPE => 'sb',
            SqlPlotParam::Y_LABEL => CNT,
            SqlPlotParam::WHAT_COL => array('createCollectionDatabaseTotalObjectCount' =>
                                            'Total createCollectionDatabaseTotalObjectCount'),
            SqlPlotParam::TABLES => $tables,
            SqlPlotParam::WHERE => $where
                                                       )
        )

    );
    return $instrGraphParams;
}

function plotInstrGraphs($statsDB, $instrParams) {
    global $date, $site;
    $sqlParamWriter = new SqlPlotParam();
    $graphTable = new HTML_Table("border=0");

    foreach ( $instrParams as $instrGraphParam ) {
        $row = array();
        foreach ( $instrGraphParam as $instrGraphParamName ) {
            if (! isset($instrGraphParamName[SqlPlotParam::Y_LABEL]) ) {
                $instrGraphParamName[SqlPlotParam::Y_LABEL] = '';
            }
            $sqlParam = array(
                SqlPlotParam::TITLE => $instrGraphParamName[SqlPlotParam::TITLE],
                SqlPlotParam::Y_LABEL => $instrGraphParamName[SqlPlotParam::Y_LABEL],
                'useragg' => 'true',
                'persistent' => 'true',
                SqlPlotParam::TYPE => $instrGraphParamName[SqlPlotParam::TYPE],
                'sb.barwidth' => 60,
                'querylist' => array(
                    array (
                        'timecol' => 'time',
                        SqlPlotParam::WHAT_COL => $instrGraphParamName[SqlPlotParam::WHAT_COL],
                        SqlPlotParam::TABLES =>  $instrGraphParamName[SqlPlotParam::TABLES],
                        'multiseries' => 'servers.hostname',
                        'where' => $instrGraphParamName[SqlPlotParam::WHERE],
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

function showGraphs($params, $dbTable, $title, $help) {

    global $date;

    drawHeader($title, 2, $help);
    $sqlParamWriter = new SqlPlotParam();
    $graphs = array();
    $dbTables = array($dbTable, StatsDB::SITES);
    $where = "$dbTable.siteid = sites.id AND sites.name = '%s'";

    foreach ($params as $col => $title) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($col)
            ->type(SqlPlotParam::STACKED_BAR)
            ->yLabel(CNT)
            ->makePersistent()
            ->forceLegend()
            ->addQuery(
                SqlPlotParam::DEFAULT_TIME_COL,
                array($col => $title),
                $dbTables,
                $where,
                array( 'site' )
            )
            ->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 600, 300);
    }
    plotGraphs($graphs);
}

function mainFlow($statsDB) {
    echo "<H1>Network Explorer Instrumentation</H1>\n";
    echo "<a href=\"" . makeGenJmxLink("networkexplorer") . "\">Generic JMX</a>\n";
    $links = array();
    $links[] = makeAnchorLink("topologySearchQueriesHelp_anchor", "Topology Search Service Queries");
    $links[] = makeAnchorLink("topologySearchInstrumentationHelp_anchor", "Topology Search Service");
    $links[] = makeAnchorLink("topologyCollectionInstrumentationHelp_anchor", "Topology Collection Service");
    $links[] = makeAnchorLink("privateNetwork_anchor", "Private Networks Count");
    $links[] = makeAnchorLink("labelCount_anchor", LABEL_COUNT);

    echo makeHTMLList($links);

    $dbTables = array(
        'enm_netex_queries',
        StatsDB::SITES
    );
    $where = $statsDB->where('enm_netex_queries', 'date', true);
    $queryTable = SqlTableBuilder::init()
                ->name("queries")
                ->tables($dbTables)
                ->where($where)
                ->addSimpleColumn('count', CNT)
                ->addSimpleColumn('ROUND(results/count,0)', '#Results (Average)')
                ->addSimpleColumn('ROUND(duration/count,0)', 'Duration (Average)')
                ->addSimpleColumn('query', 'Query')
                ->paginate()
                ->build();
    if ( $queryTable->hasRows() ) {
        drawHeaderWithHelp(
            "Topology Search Service Queries",
            1,
            "topologySearchQueriesHelp"
        );
        echo $queryTable->getTable();
    }

    $topologySearchInstrumentationHelp = "DDP_Bubble_273_ENM_netex_topologysearch_help";
    
    drawHeaderWithHelp(
        "Topology Search Service",
        1,
        "topologySearchInstrumentationHelp",
        $topologySearchInstrumentationHelp
    );

    $instrGraphParamsTopologySearch = getInstrParamsTopologySearch();
    plotInstrGraphs($statsDB, $instrGraphParamsTopologySearch);

    $topologyCollectionInstrumentationHelp = "DDP_Bubble_274_ENM_netex_topologycollection_help";

    drawHeaderWithHelp(
        "Topology Collection Service",
        1,
        "topologyCollectionInstrumentationHelp",
        $topologyCollectionInstrumentationHelp
    );

    $instrGraphParamsTopologyCollection = getInstrParamsTopologyCollection();
    plotInstrGraphs($statsDB, $instrGraphParamsTopologyCollection);
    $params = privateNetworkParams();
    showGraphs($params, "enm_private_network_count", "Private Networks Count", "privateNetwork");
    $params = topologyCollectionParams();
    showGraphs($params, "enm_netexserv_topologycollection_instr", LABEL_COUNT, "labelCount");
}

$statsDB = new StatsDB();
mainFlow($statsDB);

include PHP_ROOT . "/common/finalise.php";

