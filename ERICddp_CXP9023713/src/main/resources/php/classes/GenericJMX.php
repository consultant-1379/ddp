<?php

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';

class GenericJMX {
    var $server;
    var $site;
    var $name;
    var $fromDate;
    var $toDate;
    var $height;
    var $witdh;
    var $serverId;
    var $siteId;
    var $numCpu;

    function __construct($statsDB,$site,$server,$name,$fromDate,$toDate,$height=240,$width=640) {
        $this->statsDB = new StatsDB();
        $this->server = $server;
        $this->site = $site;
        $this->name = $name;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->height = $height;
        $this->width = $width;

        $this->siteId = getSiteId($this->statsDB,$this->site);
        if ( !is_null($this->server) && $this->server !== "NULL" ) {
            $this->serverId = getServerId($this->statsDB,$this->site,$this->server);
            if ( !is_null($this->serverId) ) {
                $this->numCpu = getNumCpus($this->statsDB,$this->site,$this->server,$this->fromDate);
            }
        }
    }

    public function getQPlot($title, $ylabel, $whatcol, $type='tsc') {
        return $this->igetQPlot("generic_jmx_stats", $title, $ylabel, $whatcol, $type);
    }

    public function getLrQPlot($title, $ylabel, $whatcol, $type='tsc') {
        return $this->igetQPlot("jvm_lr", $title, $ylabel, $whatcol, $type);
    }

    public function getGraphArray() {
        $graphArray = array();
        $graphArray[] = $this->getQPlot(
            "Heap Memory",
            "MB",
            array( 'hp_committed' => 'Committed', 'hp_used' => 'Used' )
        );
        $graphArray[] = $this->getQPlot(
            "Non-Heap Memory",
            "MB",
            array( 'nh_committed' => 'Committed', 'nh_used' => 'Used' )
        );

        if ( $this->lrDataAvail("meta_used") ) {
            $graphArray[] = $this->getLrQPlot(
                "Non-Heap Used Memory",
                "MB",
                array(
                    "cc_used" => "Code Cache",
                    "ccs_used" => "Compressed Class Space",
                    "meta_used" => "Meta Space"
                ),
                SqlPlotParam::STACKED_AREA
            );
        }

        if ( $this->dataAvail("nio_mem_direct") ) {
            $graphArray[] = $this->getQPlot(
                "NIO Memory",
                "MB",
                array( 'nio_mem_direct' => 'Direct', 'nio_mem_mapped' => 'Mapped' )
            );
        }

        $graphArray[] = $this->getQPlot(
            "Threads",
            "Threads",
            array( 'threadcount' => 'Current', 'peakthreadcount' => 'Peak' )
        );

        if ( $this->dataAvail("cputime") ) {
            $graphArray[] = $this->getQPlot(
                "CPU Time (sec)",
                "Time (sec)",
                array( 'cputime' => 'CPU Time' )
            );
            if ( ! is_null($this->numCpu) ) {
                $cpuLoadDB = "IF( generic_jmx_stats.cputime >= ( ( " . $this->numCpu .
                    " ) * 60 ), 100, ( generic_jmx_stats.cputime / ( ( " . $this->numCpu .
                    " ) * 60 ) ) * 100 )";
                $graphArray[] = $this->getQPlot(
                    "CPU Load (%)",
                    "Load (%)",
                    array($cpuLoadDB => 'CPU Load')
                );
            }
        }

        $graphArray[] = $this->getQPlot(
            "Total GC Time",
            "Time (millisec)",
            array( 'gc_youngtime' => 'Young Generation', 'gc_oldtime' => 'Old Generation'),
            "sb"
        );
        $graphArray[] = $this->getQPlot(
            "Open File Descriptors",
            "Count",
            array('fd' => 'File Descriptors')
        );

        return $graphArray;
    }

    public function getGraphTable() {
        $graphTable = new HTML_Table('border=0');
        $graphTable->addCol($this->getGraphArray());

        return $graphTable;
    }

    public function dataAvail($metric) {
        return $this->idataAvail("generic_jmx_stats", $metric);
    }

    public function lrDataAvail($metric) {
        return $this->idataAvail("jvm_lr", $metric);
    }

    private function idataAvail($table, $metric) {
        global $debug;

        if ( !is_null($this->serverId) ) {
            $where = "$table.serverid = $this->serverId";
        } else {
            $where = "$table.siteid = $this->siteId";
        }
        $where .= " AND
$table.time BETWEEN '$this->fromDate 00:00:00' AND '$this->toDate 23:59:59' AND
$table.nameid = jmx_names.id AND jmx_names.name ='$this->name' AND
$metric IS NOT NULL";

        $row = $this->statsDB->queryRow("SELECT COUNT(*) FROM $table, jmx_names WHERE $where");
        return $row[0] > 0;
    }

    private function igetQPlot($table, $title, $ylabel, $whatcol, $type='tsc') {
        global $debug;

        $colNames = array_keys($whatcol);

        if ( $debug ) { echo "<pre>Server: $this->server</pre>\n"; }

        $dbTables = array($table, "jmx_names");
        $qArgsArr = array();
        if ( !is_null($this->serverId) ) {
            $where = "$table.serverid = %d";
            $qArgsArr[] = 'serverid';
        } else {
            $where = "$table.siteid = %d";
            $qArgsArr[] = 'siteid';
        }
        $where .= " AND $table.nameid = jmx_names.id AND jmx_names.name ='%s' AND $colNames[0] IS NOT NULL";
        $qArgsArr[] = 'name';

        $sqlParam = SqlPlotParamBuilder::init()
                  ->title($title)
                  ->type($type)
                  ->barwidth(60)
                  ->yLabel($ylabel)
                  ->makePersistent()
                  ->addQuery(
                      SqlPlotParam::DEFAULT_TIME_COL,
                      $whatcol,
                      $dbTables,
                      $where,
                      $qArgsArr
                  )
                  ->build();

        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);

        $args = "name=" . $this->name;
        if ( !is_null($this->serverId) ) {
            $args .= "&serverid=" . $this->serverId;
        } else {
            $args .= "&siteid=" . $this->siteId;
        }
        $url =  $sqlParamWriter->getImgURL( $id,
                                            $this->fromDate . " 00:00:00", $this->toDate . " 23:59:59",
                                            true, $this->width, $this->height, $args );
        return $url;
    }

}

?>
