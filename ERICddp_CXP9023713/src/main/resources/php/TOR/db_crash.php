<?php
$pageTitle = "Crash Info";

/* Disable the UI for non-main flow */
if (isset($_GET["getdata"])) {
    $UI = false;
}

$YUI_DATATABLE = true;

include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/Jms.php";

require_once 'HTML/Table.php';

class crashStats extends DDPObject {
    var $cols = array(
        'date' => 'Time',
        'filename' => 'File Name'
    );

    function __construct() {
        parent::__construct("crash");
    }
    var $title = "Crash Info";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    date, filename
FROM
    enm_versantcrashinfo,
    sites
WHERE
    enm_versantcrashinfo.siteid = sites.id
    AND sites.name = '$site'
    AND enm_versantcrashinfo.date BETWEEN '$date 00:00:00' AND '$date 23:59:59'";

        $this->populateData($sql);

        // removing yyyy-mm-dd to get only time
        foreach ($this->data as &$row) {
            $row['date']=substr($row['date'],11);
        }

        return $this->data;
    }
}

drawHeaderWithHelp("Crash Stats", 2, "crashStatsHelp", "DDP_Bubble_42_Crash_Stats");
$crashTable = new crashStats();
echo $crashTable->getClientSortableTableStr();

include PHP_ROOT . "/common/finalise.php";

?>
