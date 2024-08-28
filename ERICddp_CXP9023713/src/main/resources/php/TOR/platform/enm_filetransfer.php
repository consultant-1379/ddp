<?php
$pageTitle = "File Transfer";

include_once "../../common/init.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
include_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/classes/ModelledGraphSet.php";
require_once PHP_ROOT . "/common/graphFunctions.php";
require_once PHP_ROOT . "/SqlPlotParam.php";

const TITLE = 'title';
const YLABEL = 'ylabel';
const COUNT = 'Count';
const ENM_FILETRANSFER_CONNECTIONS = 'enm_filetransfer_connections';

function smrsParams() {
    return array(
        array(
            TITLE => 'Transfer Status(Success)',
            'type' => 'sb',
            YLABEL => COUNT,
            'cols' => array(
                'successSessionCount'  => 'Transfer Status(Success)'
            )
        ),
        array(
            TITLE => 'Transfer Status(Failure)',
            'type' => 'sb',
            YLABEL => COUNT,
            'cols' => array(
                'numOfSessions - successSessionCount'  => 'Transfer Status(Failure)'
            )
        ),
        array(
            TITLE => 'Transfer Size(Read)',
            'type' => 'sb',
            YLABEL => 'MB',
            'cols' => array(
                'readSize'  => 'Read'
            )
        ),
        array(
            TITLE => 'Transfer Size(Write)',
            'type' => 'sb',
            YLABEL => 'MB',
            'cols' => array(
                'writeSize'  => 'Read'
            )
        ),
        array(
            TITLE => 'Transfer Sessions',
            'type' => 'sb',
            YLABEL => COUNT,
            'cols' => array(
                'numOfSessions'  => 'Transfer Sessions'
            )
        )
    );
}

function plotStats($sets, $instance) {
    global $date;

    $sqlParamWriter = new SqlPlotParam();
    $instrGraphParams = smrsParams();
    $instances = getInstances(
        'enm_filetransfer_connections',
        'time',
        "AND enm_filetransfer_connections.serverid =  $instance"
    );

    drawHeader($instances[0], 1, ENM_FILETRANSFER_CONNECTIONS);
    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = SqlPlotParamBuilder::init()
            ->title($instrGraphParam[TITLE])
            ->type($instrGraphParam['type'])
            ->ylabel($instrGraphParam[YLABEL])
            ->disableUserAgg()
            ->forceLegend()
            ->makePersistent();
        $tables = array(ENM_FILETRANSFER_CONNECTIONS, 'sites', 'servers');
        foreach ( $sets as $set ) {
            $data = explode(":", $set);
            $where = "
                enm_filetransfer_connections.siteid = sites.id AND sites.name = '%s' AND
                enm_filetransfer_connections.serverid = servers.id AND
                servers.id IN ($instance) AND
                enm_filetransfer_connections.connectionType = '$data[0]' AND
                enm_filetransfer_connections.usecase = '$data[1]' ";

            $sqlParam = $sqlParam->addQuery(
                'time',
                $instrGraphParam['cols'],
                $tables,
                $where,
                array( 'site'),
                'CONCAT(enm_filetransfer_connections.connectionType,":",enm_filetransfer_connections.usecase)'
            );
        }
        $sqlParam = $sqlParam->build();
        $id = $sqlParamWriter->saveParams($sqlParam);
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300);
    }
    plotGraphs($graphs);
}

function mainFlow() {
    $graphs = array();

    $selfLink = array( ModelledTable::URL => makeSelfLink() );
    $table = new ModelledTable(
        "TOR/platform/enm_filetransfer_connections_summary",
        'enm_filetransfer_connections_summary',
        $selfLink
    );
    echo $table->getTableWithHeader("File Transfer Summary");

    echo addLineBreak();
    drawHeader('File Transfer Connections Graphs', 1, ENM_FILETRANSFER_CONNECTIONS);
    $modelledGraph = new ModelledGraph( 'TOR/platform/enm_filetransfer_transfer_count' );
    $graphs[] = array( $modelledGraph->getImage() );

    getGraphsFromSet( 'all', $graphs, 'TOR/platform/enm_filetransfer_connections', null, 640, 320 );
    plotGraphs($graphs);
}

$selected = requestValue('selected');
if ( requestValue('plot') == 'connections' ) {
    $instance = requestValue('serverId');
    $sets = explode(",", $selected);
    plotStats($sets, $instance);
} elseif ( requestValue('action') == 'instance' ) {
    $callbackURL = makeSelfLink() . "&serverId=$selected";
    $params = array(serverId => $selected, ModelledTable::URL => $callbackURL);
    $table = new ModelledTable( "TOR/platform/enm_filetransfer_connections", ENM_FILETRANSFER_CONNECTIONS, $params );
    echo $table->getTableWithHeader('File Transfer Connections Per Instance');
} else {
    mainFlow();
}

include_once PHP_ROOT . "/common/finalise.php";
