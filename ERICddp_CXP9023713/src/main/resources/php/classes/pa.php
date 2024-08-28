<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";
class PAImports extends DDPObject {
    var $title = "Imports";
    var $cols = array(
        'start'    => "Start Time",
        'end'      => "End Time",
        'duration' => "Duration",
        'pa'       => "Planned Area",
        'file'     => "File",
        'numMo'    => "Number Of MOs Imported",
        'rate'    =>  "MOs/sec",
        'error'    => "Error",
        'totalcpu' => "Average CPU Usage",
        'freemem'  => "Average Free Memory (Kb)"
    );

    function __construct() {
        parent::__construct();
    }

    function getData() {
        $sql = "SELECT " .
   	    "pa_import.id AS id," .
            "pa_import.start AS 'start'," .
            "pa_import.end AS 'end'," .
            "TIMEDIFF(pa_import.end,pa_import.start) AS 'duration'," .
            "pa_import.pa AS 'pa'," .
            "pa_import.file AS 'file'," .
            "pa_import.numMo AS 'numMo'," .
	    "ROUND( numMo / TIME_TO_SEC(TIMEDIFF(pa_import.end,pa_import.start)), 1) AS 'rate', " .
            "pa_import.error AS 'error' " .
            "FROM pa_import, sites WHERE " .
            "pa_import.siteid = sites.id AND " .
            "sites.name = '" . SITE . "' AND " .
            "pa_import.start >= '" . DATE . " 00:00:00' AND " .
            "pa_import.start <= '" . DATE . " 23:59:59' " .
            "ORDER BY pa_import.start";
        $this->populateData($sql, true);
        return $this->data;
    }
}

class PAActivations extends DDPObject {
    var $title = "Activation";
    var $cols = array(
        'actstart'  => "Start Time",
        'actend'    => "End Time",
        'duration' => "Duration",
        'pa'     => "Planned Area",
        'result' => "Result",
        'type' => 'Activation Type',
        'mocount' => 'Number Of MOs',
        'rate' => 'MOs / sec',
        'totalcpu' => "Average CPU Usage",
        'freemem'  => "Average Free Memory (Kb)"
    );

    function getData() {
        $sql = "SELECT " .
   	    "pa_activation.id AS id," .
  	    "pa_activation.start AS start," .
 	    "pa_activation.end AS end," .  
            "DATE_FORMAT(pa_activation.start,'%H:%i:%s') AS actstart," .
            "DATE_FORMAT(pa_activation.end,'%H:%i:%s') AS actend," .
            "TIMEDIFF(pa_activation.end,pa_activation.start) AS 'duration'," .
            "pa_activation.pa AS 'pa'," .
            "pa_activation.result AS 'result', " .
            "pa_activation.type AS type, " .
  	    "pa_activation.mocount AS mocount, " .
            "ROUND( pa_activation.mocount / TIME_TO_SEC( TIMEDIFF(pa_activation.end,pa_activation.start) ), 2 ) AS rate " .
            "FROM pa_activation, sites WHERE " .
            "pa_activation.siteid = sites.id AND " .
            "sites.name = '" . SITE . "' AND " .
            "pa_activation.start >= '" . DATE . " 00:00:00' AND " .
            "pa_activation.start <= '" . DATE . " 23:59:59' ORDER BY pa_activation.start";
        $this->populateData($sql, true);
        return $this->data;
    }
}
?>
