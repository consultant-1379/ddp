<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class SumTableDisplay extends DDPObject {
    var $totalCount = 0;
    var $aggTable = "";

    function __construct() {
        parent::__construct("sum_table");
    }

    function getData() {
        global $date;
        global $site;
        $sql = "
            SELECT
             IFNULL(sum(success),0) as \"Successful Requests\",
             IFNULL(sum(failure),0) as \"Failed Requests\",
             IFNULL(sum(total_request),0) as \"Total Requests\"
            FROM
             $this->aggTable,sites
            WHERE
             $this->aggTable.siteid = sites.id AND
             $this->aggTable.date = '$date' AND
             sites.name = '$site'
            ";
        $this->populateData($sql);
        return $this->data;
    }

    function getCount() {
        return $this->totalCount;
    }

    function createRowBasedTable() {
        echo "<table border=1>";
        foreach ($this->data[0] as $column => $value) {
            echo "<tr>";
            echo "<td><b>$column</b></td>";
            echo "<td align=\"right\">$value</td>";
            echo "</tr>";
            if( strcmp($column,"Total Requests") == 0 ){
                $this->totalCount = $value;
            }
        }
        echo "</table>";
    }
}
?>
