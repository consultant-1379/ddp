<?php
$pageTitle = "FM Syncs";
#
### If the user has clicked on a graph
###
if ( isset($_GET["qplot"]) || isset($_GET["format"]) ) {
    $UI = false;
}

include "common/init.php";

require_once "SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

function qPlotURL($date, $objName) {
    $sqlParam =
        array( 'title'  => "FM Syncs: " . $objName,
            'targs'  => array( 'procname' ),
            'ylabel' => "Syncs",
            'useragg' => 'true',
            'persistent' => 'true',
            'querylist' =>
            array (
                array (
                    'timecol' => 'starttime',
                    'whatcol' => array( "*" => "Count" ),
                    'tables'  => "fm_sync, fm_obj, sites",
                    'where'   => "fm_sync.siteid = sites.id AND sites.name = '%s' AND fm_sync.objid = fm_obj.id AND fm_obj.name = '%s'",
                    'qargs'   => array( 'site', 'obj' )
                )
            )
        );
    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $url = $sqlParamWriter->getURL($id, "$date 00:00:00", "$date 23:59:59") . "&obj=" . htmlentities($objName);
    return $url;
}

class SyncList extends DDPObject {
    var $cols = array (
        "starttime" => "Start Time",
        "duration"  => "Duration",
        "result" => "Result",
        "fail_reason" => "Failure Reason",
        "objname" => "Object Name"
    );

    var $title = "FM Syncs";
    var $defaultOrderBy = "starttime";
    var $defaultOrderDir = "ASC";

    var $defaultLimit = 25;
    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");

    function __construct() {
        parent::__construct("synclist");
    }

    function getData() {
        global $date, $site;
        $sql = "SELECT starttime, TIMEDIFF(endtime, starttime) AS duration, result, fm_failures.name AS fail_reason, fm_obj.name AS objname
            FROM fm_sync, fm_failures, fm_obj, sites WHERE
            starttime BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND
            fm_sync.siteid = sites.id AND sites.name = '" . $site . "' AND
            fm_sync.reason = fm_failures.id AND
            fm_sync.objid = fm_obj.id
            ";
        $this->populateData($sql);
        #foreach ($this->data as $key => $d) {
        #    $d['objname'] = '<a href="' . qPlotURL($date, $d['objname']) . '">' . $d['objname'] . '</a>';
        #    $this->data[$key] = $d;
        #}
        return $this->data;
    }
}

$statsDB = new StatsDB();
$syncList = new SyncList();

if ( isset($_GET["format"]) ) {
    $excel = new ExcelWorkbook();
    $excel->addObject($syncList);
    $excel->write();
    exit;
}

?>
<H1>FM Alarm Syncs</H1>
<p/><a href="?<?=$webargs?>&format=xls">Export to Excel</a>
<?php
$syncList->getSortableHtmlTable();

include "common/finalise.php";
?>
