<?php
$pageTitle = "JMS";

$YUI_DATATABLE = true;

include "../../common/init.php";
include PHP_ROOT . "/classes/GenericJMX.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/Jms.php";

require_once 'HTML/Table.php';

function generateServerLinks(){
    global $date, $site, $oss, $dir, $statsDB;

    $statsDB->query("
SELECT DISTINCT servers.hostname AS server, servers.id AS id
FROM enm_jmsqueue, sites, servers
WHERE
 enm_jmsqueue.siteid = sites.id AND sites.name = '$site' AND
 enm_jmsqueue.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 enm_jmsqueue.serverid = servers.id
");
    $jmsServerHosts = array();
    while( $row = $statsDB->getNextNamedRow() ) {
        $jmsServerHosts[$row['server']] = $row['id'];
    }

    $serverLinks = array();
    foreach ( $jmsServerHosts as $hostname => $serverid ) {
        $jmsInContainer = $statsDB->hasData('k8s_pod', 'date', true, "k8s_pod.serverid = $serverid");
        if ( $jmsInContainer ) {
            $link = makeLink("/k8s/cadvisor.php", $hostname, array('serverid' => $serverid));
        } else {
            $link = makeLink("/server.php", $hostname, array('server' => $hostname));
        }
        $serverLinks[] = $link;
    }
    if ( count($serverLinks) > 0 ) {
        drawHeaderWithHelp("Servers", 2, "Servers", "DDP_Bubble_134_ENM_JMS_Servers");
        echo makeHTMLList($serverLinks);
    }

    return $jmsServerHosts;
}

class Idle extends DDPObject {
    function __construct($type) {
        parent::__construct("idle_" . $type);

        $this->type = $type;
        $this->cols = array(
                            $this->type => ucfirst($this->type)
                            );
        $this->defaultOrderBy = $this->type;
    }

    function getData() {
        global $date;
        global $site;

        $name_table = "enm_jms" . $this->type . "_names";
        $stat_table = "enm_jms" . $this->type;
        $typeid_attr = $this->type . "id";
        $sql = "
            SELECT
                $name_table.name AS $this->type
            FROM $stat_table, $name_table, sites
            WHERE
                $stat_table.siteid = sites.id AND sites.name = '$site' AND
                $stat_table.$typeid_attr = $name_table.id AND
                $stat_table.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY $stat_table.$typeid_attr
            HAVING SUM(messagesAdded) = 0 AND MAX(messageCount) = 0
        ";
        $this->populateData($sql);
        return $this->data;
    }
}

class ActiveTotals extends DDPObject {
    function __construct($type, $hasJmsConfig) {
        parent::__construct("active_" . $type);

        $this->type = $type;

        $nameCol = array('key' => $this->type, 'label' => ucfirst($this->type));
        if ( $hasJmsConfig ) {
            $nameCol['formatter'] = 'jmsFormatDest';
        }
        $this->cols = array(
            $nameCol,
            array('key' => 'messagesAdded', 'label' => 'Total Messages Added', 'formatter' => 'ddpFormatNumber'),
            array('key' => 'messageCount', 'label' => 'Max Message Count', 'formatter' => 'ddpFormatNumber')
        );
        $this->defaultOrderBy = 'messageCount';
        $this->defaultOrderDir = 'desc';
    }

    function getData() {
        global $date;
        global $site;

        $name_table = "enm_jms" . $this->type . "_names";
        $stat_table = "enm_jms" . $this->type;
        $typeid_attr = $this->type . "id";

        $sql = "
            SELECT
                $name_table.name AS $this->type,
                SUM(messagesAdded) AS messagesAdded,
                MAX(messageCount) AS messageCount
            FROM $stat_table, $name_table, sites
            WHERE
                $stat_table.siteid = sites.id AND sites.name = '$site' AND
                $stat_table.$typeid_attr = $name_table.id AND
                $stat_table.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
            GROUP BY $stat_table.$typeid_attr
            HAVING messagesAdded > 0 OR messageCount > 0
        ";

        $this->populateData($sql);

        return $this->data;
    }
}

$formatterCode = '
function jmsFormatDest(elCell, oRecord, oColumn, oData) {
 var type = "";
 var dest = "";
 if ( oRecord.getData("queue") != null ) {
    type = "queue";
    dest = oRecord.getData("queue");
 } else {
    type = "topic";
    dest = oRecord.getData("topic");
 }

 elCell.innerHTML = "<a href=\"" + jmsConfigUrlBase + "&destination=" + dest + "&type=" + type + "\">" + dest + "</a>";
}
';

$menuCode = '
function ctxMenuHdlr(p_sType, p_aArgs, p_myDataTable) {
    YAHOO.log("entered", "info", "ctxMenuHdlr");

    var task = p_aArgs[1];

    if( ! task )
        return;

    YAHOO.log("task.groupIndex=" + task.groupIndex  + ", task.index=" + task.index, "info", "ctxMenuHdlr");

    var selRows = p_myDataTable.getSelectedRows();
    if ( selRows.length < 1 ) {
        YAHOO.log("invalid selected count for stat" + selRows.length, "warn", "ctxMenuHdlr");
        return;
    }

    var selectedItems = Array();
    var type = "";
    for ( var i = 0; i < selRows.length; i++ ) {
        var oRecord = p_myDataTable.getRecord(selRows[i]);
        if ( oRecord.getData("queue") != null ) {
            selectedItems.push(oRecord.getData("queue"));
            type = "queue";
        } else {
            selectedItems.push(oRecord.getData("topic"));
            type = "topic";
        }
    }

    url = jmsURL + "&plot=" + type + "&names=" + selectedItems.join();
    YAHOO.log("url=" + url, "info", "rgCtxMenuHdlr");
    window.open (url,"_self",false);
}

function setupMenu(myDataTable) {
    YAHOO.log("myDataTable=" + myDataTable.getContainerEl().id, "info", "setupMenu");

    myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow);
    myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow);
    myDataTable.subscribe("rowClickEvent", myDataTable.onEventSelectRow);

    var ctxMenu = new YAHOO.widget.ContextMenu(myDataTable.getContainerEl().id + "_ctxMenu",
                           {trigger:myDataTable.getTbodyEl()});
    ctxMenu.addItem("Plot");
    ctxMenu.render(myDataTable.getContainerEl());
    ctxMenu.clickEvent.subscribe(ctxMenuHdlr, myDataTable);
}
';

function plot($type,$names) {
    global $site, $date, $debug;

    $statsDB = new StatsDB();
    $jms = new Jms($statsDB, $site, $type, $names, $date, $date,640,320);
    $jmsGraphLinks = $jms->getGraphArray();
    if ( $debug ) { echo "<pre>jmsGraphLinks\n"; print_r($jmsGraphLinks); echo "</pre>\n"; }

    $graphTable = new HTML_Table("border=0");
    foreach ( $jmsGraphLinks as $link ) {
        $graphTable->addRow( array( $link ) );
    }

    echo $graphTable->toHTML();
}

function mainFlow() {
    global $debug, $webargs, $php_webroot, $date, $site, $menuCode, $formatterCode, $rootdir;

    if ( $debug > 1 ) {
        echo <<<EOS
        <div id="myLogger" class="yui-log-container yui-log"/>
        <script type="text/javascript">
        var myLogReader = new YAHOO.widget.LogReader("myLogReader", {verboseOutput:false});
        </script>

EOS;
    }

    $jmsURL = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
    $jmsConfigUrlBase = "jms_config.php?" . $_SERVER['QUERY_STRING'] . "&action=showdest";
    echo <<<EOS
    <script type="text/javascript">
    var jmsURL = "$jmsURL";
    var jmsConfigUrlBase = "$jmsConfigUrlBase";

    $formatterCode

    $menuCode

    </script>

EOS;

    $hasJmsConfig = file_exists($rootdir . "/jms/config.json");
    if ( $hasJmsConfig ) {
        echo "<p><a href=\"$php_webroot/TOR/platform/jms_config.php?$webargs\">JMS Configuration</a></p>\n";
    }

    $jmsServerHosts = generateServerLinks();

    foreach (array('queue', 'topic') as $type) {
        if ($type === 'queue'){
            $help_content_id ="DDP_Bubble_38_JMS_Active_Queue";
        } else{
            $help_content_id ="DDP_Bubble_39_JMS_Active_Topic";
        }

        drawHeaderWithHelp( "Active " . ucfirst($type) . "(s)", 2, "active $type help", $help_content_id);
        $table = new ActiveTotals($type, $hasJmsConfig);
        echo $table->getClientSortableTableStr(0,NULL,'setupMenu');
    }

    getJmsGraphs($jmsServerHosts);

    foreach (array('queue', 'topic') as $type) {
        echo "<H2>Idle " . ucfirst($type). "(s)" . "</H2>\n";
        $table = new Idle($type);
        echo $table->getClientSortableTableStr();
    }
}

function getDbJbossName($jmsServerHosts) {
    global $statsDB, $date;

    if ( count($jmsServerHosts) == 0 ) {
        return null;
    }
    $serverIdsStr = implode(array_values($jmsServerHosts), ",");
    $statsDB->query("
SELECT DISTINCT(jmx_names.name)
FROM generic_jmx_stats, jmx_names
WHERE
 generic_jmx_stats.serverid IN ($serverIdsStr) AND
 generic_jmx_stats.nameid = jmx_names.id AND
 generic_jmx_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 jmx_names.name LIKE 'jms%'
");

    if ( $statsDB->getNumRows() > 0 ) {
        $row = $statsDB->getNextRow();
        return $row[0];
    } else {
        return null;
    }
}

function getJmsGraphs($jmsServerHosts) {
    global $date, $site, $statsDB;

    $fromDate = $date;
    $toDate = $date;

    $jmsJmxName = getDbJbossName($jmsServerHosts);
    if ( ! is_null($jmsJmxName) ) {
        drawHeaderWithHelp(
            "JMX Data for $jmsJmxName",
            2,
            "dailyApplicationNotificationTotalsHelp",
            "DDP_Bubble_194_Generic_JMX_Help"
        );
        $headerRow = array();
        $graphRow = array();
        foreach ( $jmsServerHosts as $hostName => $serverid ) {
            $headerRow[] = $hostName;
            $genJMX = new GenericJMX($statsDB, $site, $hostName, $jmsJmxName, $fromDate, $toDate);
            $graphRow[] = $genJMX->getGraphTable()->toHTML();
        }
        $graphTable = new HTML_Table('border=0');
        $graphTable->addRow($headerRow);
        $graphTable->addRow($graphRow);
        echo $graphTable->toHTML();
    }
}

if (isset($_GET["plot"])) {
    $type = $_GET["plot"];
    $name = $_GET["names"];

    if ($type == "queue"){
        $help_content_id ="DDP_Bubble_40_JMS_Plot_Queue";
    }
    if ($type == "topic"){
        $help_content_id ="DDP_Bubble_41_JMS_Plot_Topic";
    }
    drawHeaderWithHelp( "Help", 2, "Help", $help_content_id );
    plot($type,$name);
} else {
    mainFlow();
}

include PHP_ROOT . "/common/finalise.php";
?>

