<?php
$pageTitle = "Modelled Instrumentation Viewer";
$DISABLE_UI_PARAMS = array( "action" );

include_once "../../common/init.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

const SERVER_KEY = "server";
const METRIC_GROUP_KEY = "metricgroup";

function redirectToMBeanViewer($id) {
    global $debug;

    $idParts = explode("!", $id);
    $url = makeURL(
        "/TOR/system/mBeanViewer.php",
        array( "display" => 1, SERVER_KEY => $idParts[0], "file" => $idParts[1], "mBean" => $idParts[2] )
    );
    if ( $debug ) {
        debugMsg("redirectToMBeanViewer: id=$id idParts", $idParts);
        debugMsg("redirectToMBeanViewer: url=$url");
    } else {
        header("Location:" . $url);
    }
}

function getInstrMetricGroups($server) {
    global $datadir, $dir;

    $instrDir = sprintf("%s/tor_servers/%s_TOR/%s/instr", $datadir, $server, $dir);
    debugMsg("getInstrMetricGroups: instrDir=$instrDir");
    $metricGroups = array();
    foreach ( glob("$instrDir/*.xml") as $xmlPath ) {
        debugMsg("getInstrMetricGroups: processing XML file $xmlPath");
        $modelStr = file_get_contents($xmlPath); // NOSONAR
        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($modelStr);

        $xmlFile = basename($xmlPath);
        foreach ( $xmlDoc->getElementsByTagName("profile") as $profileElement ) {
            $profileName = $profileElement->getAttribute("name");
            if ( ! array_key_exists($profileName, $metricGroups) ) {
                $metricGroups[$profileName] = array();
            }
            foreach ( $profileElement->getElementsByTagName("provider") as $providerElement ) {
                $providerName = $providerElement->getAttribute("name");
                debugMsg("getInstrMetricGroups: provider=$providerName");
                foreach ( $providerElement->getElementsByTagName("metricGroup") as $metricGroupElement ) {
                    $metricGroupName = $metricGroupElement->getAttribute("name");
                    debugMsg("getInstrMetricGroups: metricGroupName=$metricGroupName");
                    $metricGroups[$profileName]["$providerName-$metricGroupName"] = $xmlFile;
                }
            }
        }
    }

    debugMsg("getInstrMetricGroups server=$server", $metricGroups);
    return $metricGroups;
}

function parseModel($modelArg) {
    global $php_root;

    $ddpRoot = dirname($php_root);
    $modelFile = $ddpRoot . "/analysis/modelled/instr/models/" . $modelArg . ".xml";
    $modelStr = file_get_contents($modelFile); // NOSONAR
    $xmlDoc = new DOMDocument();
    $xmlDoc->loadXML($modelStr);

    $namespace = $xmlDoc->getElementsByTagName("modelledinstr")->item(0)->getAttribute("namespace");
    debugMsg("parseModel: namespace=$namespace");

    $tableElement = $xmlDoc->getElementsByTagName("table")->item(0);
    $dbTableName = $tableElement->getAttribute("name");
    $hasServerId = false;
    foreach ( $xmlDoc->getElementsByTagName("keycol") as $keyColElement ) {
        if ( $keyColElement->getAttribute("name") === "serverid" ) {
            $hasServerId = true;
        }
    }

    $metricgroups = array();
    foreach ( $xmlDoc->getElementsByTagName(METRIC_GROUP_KEY) as $metricGroupElement ) {
        $metricgroups[] = $metricGroupElement->getAttribute("name");
    }

    return array($dbTableName, $hasServerId, $metricgroups, $namespace);
}

function getInstModelGroup($modelMetricGroup, $modelNameSpace, $instrProfileName, $xmlFile) {
    $instrNameSpace = $instrProfileName . "@" . $xmlFile;
    debugMsg("getInstModelGroup: modelNameSpace=$modelNameSpace instrNameSpace=$instrNameSpace");
    $matches = array();
    preg_match("/$modelNameSpace/", $instrNameSpace, $matches);
    debugMsg("getInstModelGroup: matches", $matches);
    $modelInstMeticGroup = $modelMetricGroup;
    $index = 1;
    while ( $index < count($matches) ) {
        $key = '%' . $index . '%';
        debugMsg("getInstModelGroup: replacing $key with  $matches[$index]");
        $modelInstMeticGroup = str_replace($key, $matches[$index], $modelInstMeticGroup);
        $index++;
    }
    debugMsg("getInstModelGroup: modelInstMeticGroup $modelInstMeticGroup");
    return $modelInstMeticGroup;
}

// For the given modelMetricGroup add any matching metric groups found in the instr files
function addTableRows($modelMetricGroup, $modelNamespace, $instrMetricGroups, $server, &$tableData) {
    foreach ( $instrMetricGroups as $instrProfileName => $instrProfileMetricGroups ) {
        foreach ( $instrProfileMetricGroups as $instrMetricGroup => $xmlFile ) {
            $modelInstMeticGroup = getInstModelGroup(
                $modelMetricGroup,
                $modelNamespace,
                $instrProfileName,
                $xmlFile
            );
            debugMsg("addTableRows: Checking instrMetricGroup $instrMetricGroup v $modelInstMeticGroup");
            if ( preg_match("/$modelInstMeticGroup/", $instrMetricGroup) ) {
                debugMsg("addTableRows: matched");
                $tableData[] = array(
                    SERVER_KEY => $server,
                    METRIC_GROUP_KEY => $instrMetricGroup,
                    "id" => $server . "!" . $xmlFile . "!" . $instrMetricGroup
                );
            }
        }
    }
}

function getTableData($dbTableName, $modelMetricGroups, $modelNamespace) {
    global $statsDB, $site, $date;

    debugMsg("getTableData: dbTableName=$dbTableName modelNamespace=$modelNamespace");

    $siteId = getSiteId($statsDB, $site);

    $tableData = array();
    $statsDB->query("
SELECT
 DISTINCT servers.hostname
FROM $dbTableName, servers
WHERE
 $dbTableName.siteid = $siteId AND
 $dbTableName.time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
 $dbTableName.serverid = servers.id");
    while ( $row = $statsDB->getNextRow() ) {
        $server = $row[0];
        $instrMetricGroups = getInstrMetricGroups($server);
        foreach ( $modelMetricGroups as $modelMetricGroup ) {
            debugMsg("getTableData: modelMetricGroup $modelMetricGroup");
            addTableRows($modelMetricGroup, $modelNamespace, $instrMetricGroups, $server, $tableData);
        }
    }

    return $tableData;
}

function main() {
    $modelArg = requestValue("model");
    list($dbTableName, $hasServerId, $metricGroups, $namespace) = parseModel($modelArg);
    debugMsg("main: dbTableName=$dbTableName hasServerId=$hasServerId namespace=$namespace");

    if ( $hasServerId ) {
        $tableData = getTableData($dbTableName, $metricGroups, $namespace);
        $columns = array(
            array( DDPTable::KEY => SERVER_KEY, DDPTable::LABEL => "Instance"),
            array( DDPTable::KEY => METRIC_GROUP_KEY, DDPTable::LABEL => "Metric Group"),
            array( DDPTable::KEY => "id", DDPTable::VISIBLE => false)
        );
        $ctxMenu = array(
            DDPTable::KEY => DDPTable::ACTION,
            DDPTable::MULTI => false,
            DDPTable::MENU => array( "mbeanviewer" => 'Show Raw Data'),
            DDPTable::URL  => makeSelfLink(),
            DDPTable::COL => 'id'
        );
        $modelledTable = new DDPTable(
            "modelled",
            $columns,
            array('data' => $tableData),
            array(DDPTable::CTX_MENU => $ctxMenu)
        );
        echo $modelledTable->getTable();
    }
}

$action = requestValue(DDPTable::ACTION);
if ( is_null($action) ) {
    main();
} else {
    redirectToMBeanViewer(requestValue("selected"));
}

include_once PHP_ROOT . "/common/finalise.php";

