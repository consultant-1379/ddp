<?php

require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/ModelledGraph.php";

class JBossThreadPool {
    function __construct($appName, $poolName) {
        $this->appName = $appName;
        $this->poolName = $poolName;
    }

    function getGraphs() {
        global $statsDB, $site, $date;

        $statsDB->query("
SELECT k8s_pod.serverid
FROM k8s_pod
JOIN sites ON k8s_pod.siteid = sites.id
JOIN k8s_pod_app_names ON k8s_pod.appid = k8s_pod_app_names.id
WHERE
 k8s_pod.date = '$date' AND
 sites.name = '$site' AND
 k8s_pod_app_names.name = '$this->appName'");
        $srvIds = array();
        while ($row = $statsDB->getNextRow()) {
            $srvIds[] = $row[0];
        }

        $params = array( 'tp' => $this->poolName, 'srvids' => implode(",", $srvIds));
        $graphs = array();
        foreach ( array('completedTaskCount', 'activeCount', 'queueSize', 'rejectedCount') as $column ) {
            $modelledGraph = new ModelledGraph('TOR/common/jboss_threadpool_' . $column);
            $graphs[] = $modelledGraph->getImage($params);
        }

        return $graphs;
    }

    function getGraphsAsTable() {
        $graphTable = new HTML_Table('border=0');
        $graphTable->addRow($this->getGraphs());
        return $graphTable->toHTML();
    }
}

