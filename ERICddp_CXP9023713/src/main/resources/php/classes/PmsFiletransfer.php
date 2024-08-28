<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class PmsFiletransfer extends DDPObject {
    var $title = "PM File Transfer";
    var $cols = array(
        "name" => "NE Name",
        "nodetype" => "NE Type",
	"filetype" => "File Type",
	"files" => "Num Files",
        "totalkb" => "Total (Kb)",
        "avgthroughput" => "Avg. Throughput (B/s)",
        "minthroughput" => "Min. Throughput (B/s)"
    );

    // Override defaults for these
    var $defaultLimit = 20;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "avgthroughput";

    var $limits = array(20 => 20, 50 => 50, 100 => 100, "" => "Unlimited");
    var $filter = "";

    function __construct($filter = "") {
        // pass an id into the parent constructor
        parent::__construct("pms_filetx");
        $this->filter = $filter;
    }

    function getData($site = SITE, $date = DATE) {
        $sql = "
SELECT 
 ne.name AS name, ne_types.name AS nodetype, pms_filetransfer_node.totalkb, 
 pms_filetransfer_node.avgthroughput, 
 pms_filetransfer_node.minthroughput,
 pms_filetransfer_node.files, pms_filetransfer_node.filetype 
FROM 
 pms_filetransfer_node, ne, ne_types, sites
WHERE 
 pms_filetransfer_node.siteid = sites.id AND sites.name = '$site' AND
 pms_filetransfer_node.date = '$date' AND
 pms_filetransfer_node.neid = ne.id AND
 ne.netypeid = ne_types.id" . 
	  $this->filter;
        $this->populateData($sql);
        return $this->data;
    }
}
?>
