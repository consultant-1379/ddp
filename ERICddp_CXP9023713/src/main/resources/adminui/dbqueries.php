<?php
include "init.php";
include "../php/common/countries.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
$editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
class QueryList extends DDPObject {
    var $cols = array(
        "id" => "Thread ID",
        "user" => "User",
        "host" => "Host",
        "db" => "DB",
        "command" => "Command",
        "time" => "Time",
        "state" => "State",
        "info" => "Info"
    );

    function __construct() {
        parent::__construct("querylist");
    }

    function getData() {
        $sql = "SELECT id,user,host,db,command,time,state,info FROM information_schema.PROCESSLIST WHERE command <> 'Sleep' AND info NOT LIKE '%PROCESSLIST%'";
        $this->populateData($sql);
        return $this->data;
    }
}

echo "<h1>DB Query Management</h1>\n";

if ( $debug ) { print "<pre>main REQUEST_METHOD=" . $_SERVER['REQUEST_METHOD'] . " _REQUEST\n"; print_r($_REQUEST); echo "</pre>\n"; }

if ( $_SERVER["REQUEST_METHOD"] == "GET") {
        $idx = new QueryList();
        $idx->getSortableHtmlTable();
}

include "../php/common/finalise.php";
?>

