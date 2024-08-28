<?php
$pageTitle = "Workflow Event Info";

include "../common/init.php";
include "../common/graphs.php";

if (isset($_GET['date'])) {
    $date = $_GET['date'];
}

require_once PHP_ROOT . "/classes/DDPObject.class.php";

$statsDB = new StatsDB();

class WorkflowEventInfo extends DDPObject {
    var $cols = array (        
        "name" => "Workflow Event Name",
        "type" => "Workflow Event Type",
        "num" => "Event Count",
        "avg" => "Avg. Duration",
        "max" => "Max. Duration"
    );

    var $defaultOrderBy = "num";
    var $defaultOrderDir = "DESC";

    function __construct() {
                parent::__construct();
                    }

    function getData() {
        global $site, $date, $webargs;
        $sql = "
            SELECT eniq_workflow_names.name as name, eniq_workflow_types.name as type, eventcount as num, avgduration as avg, maxduration as max
            FROM eniq_workflow_events, eniq_workflow_names, eniq_workflow_types, sites
            WHERE eniq_workflow_names.id = nameid
            AND eniq_workflow_types.id = typeid
            AND siteid=sites.id AND sites.name = '$site'
            AND date = '$date' ";
        $this->populateData($sql);
        return $this->data;
    }
}

?>
<h2><?=$pageTitle?></h2>
<?php
$tbl = new WorkflowEventInfo();
$tbl->getSortableHtmlTable();

include "../common/finalise.php";
?>
