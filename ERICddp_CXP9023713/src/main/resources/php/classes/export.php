<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class Export extends DDPObject {
    var $title = "Export";
    var $cols = array (
        "start"    => "Start Time",
        "end"      => "End Time",
        "duration" => "Duration",
        "root"     => "Export Root",
        "file"     => "File",
        "numMo"    => "Number Of MOs",
	"numNode"  => "Nodes",
	"numCachedMo" => "Number Of MOs from cache",
	"numCachedNode"  => "Cached Nodes",
	"user" => "User",
	"filter" => "Filter",
        "rate"     => "MOs/sec",
        "error"    => "Error",
        "totalcpu" => "Average CPU usage",
        "freemem"  => "Average Free Memory  (Kb)"
    );

    function __construct() {
        parent::__construct();
        $this->pageTitle = "Bulk CM Export Stats";
    }

    function getData() {
        $sql = "SELECT " .
            "export.start AS 'start'," .
            "export.end AS 'end'," .
            "TIMEDIFF(export.end,export.start) AS 'duration'," .
            "export.root AS 'root'," .
            "export.file AS 'file'," .
            "export.numMo AS 'numMo'," .
            "export.numCachedMo AS 'numCachedMo'," .
            "export.numNode AS 'numNode'," .
            "export.numCachedNode AS 'numCachedNode'," .
            "export.user AS 'user'," .
            "export.filter AS 'filter'," .
            "ROUND( numMo / TIME_TO_SEC(TIMEDIFF(export.end,export.start)), 1) AS 'rate', " .
            "export.error AS 'error' " .
            "FROM export, sites WHERE " .
            "export.siteid = sites.id AND sites.name = '" . SITE . "' " .
            "AND export.start BETWEEN '" . DATE . " 00:00:00' " .
            "AND '" . DATE . " 23:59:59' ORDER BY export.start";

        $this->populateData($sql, true);
        return $this->data;
    }
}
?>
