<?php

require_once PHP_ROOT . "/SqlPlotParam.php";

class ServiceJvmStats {
    var $site;
    var $serverids;
    var $service;
    var $fromDate;
    var $toDate;
    var $height;
    var $witdh;
    var $nCpu;

    function __construct($statsDB,$site,$srvArr,$service,$fromDate,$toDate,$nCpu,$width=800,$height=400) {
        global $debug;

        $this->statsDB = new StatsDB();
        $this->srvArr = $srvArr;
        $this->site = $site;
        $this->service = $service;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->nCpu = $nCpu;
        $this->height = $height;
        $this->width = $width;

        if ( $debug ) { echo "<pre>ServiceJvmStats: service=$service srvArr="; print_r($srvArr); echo "</pre>\n"; }
    }

    function getQPlot($title,$ylabel,$whatcol,$type='tsc') {
        global $debug;

        $colNames = array_keys($whatcol);

        if ( $debug ) { echo "<pre>Server: $this->server</pre>\n"; }

        $sql = "
generic_jmx_stats.serverid IN ( %s ) AND
generic_jmx_stats.serverid = servers.id AND
generic_jmx_stats.nameid = jmx_names.id AND jmx_names.name ='%s'
AND '%s' IS NOT NULL";

        $sqlParam =
                  array(
                      'type'       => $type,
                      'title'      => $title,
                      'ylabel'     => $ylabel,
                      'useragg'    => 'true',
                      'persistent' => 'true',
                      'querylist' =>
                      array(
                          array (
                              'timecol' => 'time',
                              'multiseries' => 'servers.hostname',
                              'whatcol' => $whatcol,
                              'tables'  => "generic_jmx_stats, jmx_names, servers",
                              'where'   => $sql,
                              'qargs'   => array( 'srvArr', 'service', 'col' )
                          )
                      )
                  );
        if ( $type == 'sb' ) {
            $sqlParam['sb.barwidth'] = 60;
        }
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);

        $args = "srvArr=" . implode(",",array_values($this->srvArr)) . "&service=" . $this->service . "&col=" . $whatcol[$colNames[0]];
        $url =  $sqlParamWriter->getImgURL( $id,
                                            $this->fromDate . " 00:00:00", $this->toDate . " 23:59:59",
                                            false, $this->width, $this->height, $args );
        return $url;
    }

    function getGraphArray() {
        $graphArray = array();

        if ( isset($this->nCpu) ) {
            $dbCol = "IF( generic_jmx_stats.cputime >= ( ( $this->nCpu ) * 60 ), 100, ( generic_jmx_stats.cputime / ( ( $this->nCpu ) * 60 ) ) * 100 )";
            $graphArray['cpupercent'] = $this->getQPlot("CPU Load (%)", "Load (%)", array( $dbCol => 'CPU' ) );
        } else {
            $graphArray['cputime'] = $this->getQPlot("CPU Time (sec)", "Time (sec)", array( 'cputime' => 'CPU Time' ));
        }
        $graphArray['gc'] = $this->getQPlot("Total GC Time", "Time (millisec)", array( 'gc_youngtime+gc_oldtime' => 'Total GC Time' ) );
        $graphArray['heap'] = $this->getQPlot("Used Heap Memory", "MB", array( 'hp_used' => 'Used' ),'sb');
        $graphArray['threads'] = $this->getQPlot("Threads", "Threads", array( 'threadcount' => 'Current' ));

        $graphArray['nonheap'] = $this->getQPlot("Used Non-Heap Memory", "MB", array( 'nh_used' => 'Used' ));
        $graphArray['niodirect'] = $this->getQPlot("Direct NIO Memory", "MB", array( 'nio_mem_direct' => 'Direct' ));
        $graphArray['fd'] = $this->getQPlot("Open File Descriptors", "Count", array('fd' => 'File Descriptors' ));

        return $graphArray;
    }


    function getServiceQPlot($hostname,$serverId,$title,$ylabel,$whatcol,$type='tsc') {
        global $debug;

        $colNames = array_keys($whatcol);

        $sql = "
generic_jmx_stats.serverid = %d AND
generic_jmx_stats.nameid = jmx_names.id AND jmx_names.name ='%s'
AND '%s' IS NOT NULL";

        $sqlParam =
                  array(
                      'type'       => $type,
                      'title'      => $title . " for %s",
                      'targs'      => array('server'),
                      'ylabel'     => $ylabel,
                      'useragg'    => 'true',
                      'persistent' => 'false',
                      'querylist' =>
                      array(
                          array (
                              'timecol' => 'time',
                              'whatcol' => $whatcol,
                              'tables'  => "generic_jmx_stats, jmx_names",
                              'where'   => $sql,
                              'qargs'   => array( 'serverid', 'service', 'col' )
                          )
                      )
                  );
        if ( $type == 'sb' ) {
            $sqlParam['sb.barwidth'] = 60;
        }
        $sqlParamWriter = new SqlPlotParam();
        $id = $sqlParamWriter->saveParams($sqlParam);

        $args = "server=" . $hostname . "&serverid=" . $serverId . "&service=" . $this->service . "&col=" . $whatcol[$colNames[0]];
        $url =  $sqlParamWriter->getImgURL( $id,
                                            $this->fromDate . " 00:00:00", $this->toDate . " 23:59:59",
                                            true, $this->width, $this->height, $args );
        return $url;
    }

    function getPerServiceGraphs($key) {
        $graphArray = array();

        foreach ( $this->srvArr as $hostname => $serverid ) {
            if ( $key == 'cpupercent' ) {
                $dbCol = "IF( generic_jmx_stats.cputime >= ( ( $this->nCpu ) * 60 ), 100, ( generic_jmx_stats.cputime / ( ( $this->nCpu ) * 60 ) ) * 100 )";
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"CPU Load (%%)", "Load (%)", array( $dbCol => 'CPU' ) );
            } else if ( $key == 'cputime' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"CPU Time (sec)", "Time (sec)", array( 'cputime' => 'CPU Time' ));
            } else if ( $key == 'gc' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"Total GC Time", "Time (millisec)", array( 'gc_youngtime' => 'Young Generation', 'gc_oldtime' => 'Old Generation'),'sb');
            } else if ( $key == 'heap' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"Heap Memory", "MB", array( 'hp_committed' => 'Committed', 'hp_used' => 'Used' ));
            } else if ( $key == 'threads' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"Threads", "Threads", array( 'threadcount' => 'Current', 'peakthreadcount' => 'Peak' ));
            } else if ( $key == 'nonheap' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"Non-Heap Memory", "MB", array( 'nh_committed' => 'Committed', 'nh_used' => 'Used' ));
            } else if ( $key == 'niodirect' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"NIO Memory", "MB", array( 'nio_mem_direct' => 'Direct', 'nio_mem_mapped' => 'Mapped' ));
            } else if ( $key == 'fd' ) {
                $graphArray[] = $this->getServiceQPlot($hostname,$serverid,"Open File Descriptors", "Count", array('fd' => 'File Descriptors' ));
            }
        }

        return $graphArray;
    }
}

?>
