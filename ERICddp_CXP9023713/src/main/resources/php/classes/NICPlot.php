<?php

require_once PHP_ROOT . "/SqlPlotParam.php";

class NICPlot {
    const SERV_NIC_DIR = 1;
    const SERV = 2;
    const NIC = 3;

    const RX = 'rx';
    const TX = 'tx';

    var $date;
    var $nicIdsStr;
    var $queries;

    function __construct($statsDB,$date,$nicIdsStr,$seriesFormat=self::SERV_NIC_DIR) {
        $this->statsDB = new StatsDB();
        $this->date = $date;
        $this->nicIdsStr=$nicIdsStr;
        $this->seriesFormat=$seriesFormat;
        $this->initQueries();
    }

    function openQPlot() {
        global $debug;

        $queryList=array();

        $sqlPlotParam = array(
            'title' => "NIC Bandwidth Usage",
            'type' => 'tsc',
            'ylabel' => "MBit/s",
            'useragg' => 'true',
            'persistent' => 'false',
            'querylist' => array_merge($this->queries['rx'],$this->queries['tx'])
        );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlPlotParam);
        $url =  $sqlParamWriter->getURL( $id,
                                         $this->date . " 00:00:00", $this->date . " 23:59:59");

        header("Location:" . $url );
    }

    function getGraph($type,$title,$width=800,$height=300) {
        $sqlPlotParam = array(
            'title' => $title,
            'type' => 'tsc',
            'ylabel' => "MBit/s",
            'useragg' => 'true',
            'persistent' => 'false',
            'querylist' => $this->queries[$type],
        );
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlPlotParam);
        return $sqlParamWriter->getImgURL( $id,
                                           "$this->date 00:00:00", "$this->date 23:59:59",
                                           true,
                                           $width,$height);

    }

    function initQueries() {
        global $debug;

        $this->queries = array( 'rx' => [], 'tx' => [] );
        $this->statsDB->query("
SELECT
 servers.id AS srvid, servers.hostname AS hostname,
 network_interfaces.id AS nicid, network_interfaces.name AS nicname
FROM servers, network_interfaces
WHERE
 network_interfaces.id IN ($this->nicIdsStr) AND
 network_interfaces.serverid = servers.id");
        while ( $row = $this->statsDB->getNextNamedRow() ) {
            if ( $this->seriesFormat == self::SERV_NIC_DIR ) {
                $rxColName = sprintf("%s-%s RX", $row['hostname'], $row['nicname']);
                $txColName = sprintf("%s-%s TX", $row['hostname'], $row['nicname']);
            } else if ( $this->seriesFormat == self::SERV ) {
                $rxColName = $row['hostname'];
                $txColName = $row['hostname'];
            } else if ( $this->seriesFormat == self::NIC ) {
                $rxColName = $row['nicname'];
                $txColName = $row['nicname'];
            }

            $this->queries['rx'][] = array(
                'timecol' => 'time',
                'whatcol'=>
                array(
                    '((ibytes_per_sec * 8)/1000000)' => $rxColName,
                ),
                'tables' => "nic_stat",
                'where' => 'nic_stat.serverid = ' . $row['srvid'] . ' AND nic_stat.nicid = ' . $row['nicid'],
            );
            $this->queries['tx'][] = array(
                'timecol' => 'time',
                'whatcol'=> array(
                    '((obytes_per_sec * 8)/1000000)' => $txColName,
                ),
                'tables' => "nic_stat",
                'where' => 'nic_stat.serverid = ' . $row['srvid'] . ' AND nic_stat.nicid = ' . $row['nicid']
            );
        }

        if ( $debug ) { echo "<pre>NICPlot::initQueries queries: "; print_r($this->queries); echo "</pre>\n"; }
    }
}

?>
