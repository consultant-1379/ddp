<?php
$pageTitle = "NetSim Network Information";

$YUI_DATATABLE = true;

include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

class NetSimNetworkConfigInfo extends DDPObject {
    var $cols = array(
                      array('key' => 'netsim_server', 'label' => 'NetSim Server'),
                      array('key' => 'simulation', 'label' => 'Simulation'),
                      array('key' => 'node_type', 'label' => 'Node Type'),
                      array('key' => 'bandwidth', 'label' => 'Bandwidth', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'latency', 'label' => 'Latency', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'num_of_nodes', 'label' => 'Number of Nodes')
                      );

    var $title = "Netsim Network Configuation Parameters";

    function __construct() {
        parent::__construct("netsimNetworkConfigInfo");
    }

    function getData($site = SITE, $date = DATE) {
        $sql = "
            SELECT
                servers.hostname AS netsim_server,
                netsim_network_stats.simulation AS simulation,
                ne_types.name AS node_type,
                netsim_network_stats.bandwidth AS bandwidth,
                netsim_network_stats.latency AS latency,
                netsim_network_stats.num_of_nodes AS num_of_nodes
            FROM netsim_network_stats, servers, ne_types, sites
            WHERE
                netsim_network_stats.siteid = sites.id AND
                netsim_network_stats.serverid = servers.id AND
                netsim_network_stats.netypeid = ne_types.id AND
                netsim_network_stats.date = '$date' AND
                sites.name = '$site'
            ORDER BY netsim_server ASC, simulation ASC
        ";

        $this->populateData($sql);
        return $this->data;
    }
}

echo "<h1>NetSim Network Information</h1>";
$netsimNetworkConfigHelp = <<<EOT
<p>
This data has been obtained with the help of  <b>'/usr/sbin/tc'</b>  command-line utility on NetSim servers.
<ul>
    <li><b>Bandwidth:</b> The bandwidth in Kbit/s. A value of 'NA' in the table stands for "Not Applied".</li>
    <li><b>Latency:</b> The latency in milliseconds. A value of 'NA' in the table stands for "Not Applied".</li>
</ul>
For more information <a target="_blank" href="http://confluence-oss.lmera.ericsson.se/display/HOME/Home">click here</a>.
</p>
EOT;
drawHeaderWithHelp("Netsim Network Configuation Parameters", 2, "netsimNetworkConfigHelp", $netsimNetworkConfigHelp);
$table = new NetSimNetworkConfigInfo();
echo $table->getClientSortableTableStr(100, array(500, 1000, 5000, 10000));

include PHP_ROOT . "/common/finalise.php";

?>

