<?php
$pageTitle = "CS Notifications";

$YUI_DATATABLE = true;
include "../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class CsNotifTotals extends DDPObject {
  var $cols = array(
		    'keyattr' => '',
		    'AVC' => 'AVC',
		    'CREATE'     => 'Create',
		    'DELETE'      => 'Delete',
		    'ASSOC_CREATE' => 'Association Create',
		    'ASSOC_DELETE' => 'Association Delete',
		    'totalsize'      => 'Total Size',
		    'maxsize' => 'Max Size'
		    );
   var $columnTypes = array( 'keyattr' => 'string', 'AVC' => 'int', 'CREATE' => 'int',
                             'DELETE' => 'int', 'ASSOC_CREATE' => 'int', 'ASSOC_DELETE' => 'int',
			     'totalsize' => 'int', 'maxsize' => 'int' );

    var $title = "Notifications";

    var $type = "";

    function __construct($type) {
	if ( $type == "moc" ) {
	  $this->cols['keyattr'] = 'MO Type';
	} else {
	  $this->cols['keyattr'] = 'Application';
	}
        parent::__construct("notif_$type");
	$this->type = $type;
    }

    function getData() {
        global $date;
	global $site;
	global $cs;
	global $debug;
	global $webargs;

	if ( $this->type == "moc" ) {
	  $keyTable = "mo_names";
	  $keyJoinCol = "moid";
	} else {
	  $keyTable = "cs_application_names";
	  $keyJoinCol = "cs_application_nameid";
	}

	$sql = "
SELECT
 $keyTable.name AS keyattr,
 cs_notifications.type AS type,
 SUM(cs_notifications.count) AS count,
 SUM(cs_notifications.totalsize) AS totalsize,
 MAX(cs_notifications.maxsize) AS maxsize
FROM
 cs_notifications, cs_names, sites, $keyTable
WHERE
 cs_notifications.siteid = sites.id AND sites.name = '$site' AND
 cs_notifications.date = '$date' AND
 cs_notifications.csid = cs_names.id AND cs_names.name = '$cs' AND
 cs_notifications.$keyJoinCol = $keyTable.id
GROUP BY cs_notifications.$keyJoinCol, cs_notifications.type
ORDER BY $keyTable.name, cs_notifications.type
";
	$this->statsDB->query($sql);
	$currentKey = "";
	$keyStats = array();
        while($row = $this->statsDB->getNextRow()) {
	  if ( $row[0] != $currentKey ) {
             $currentKey = $row[0];
	     $keyStats[$currentKey] = array( 'AVC' => 0, 'CREATE' => 0, 'DELETE' => 0,
					     'ASSOC_CREATE' => 0, 'ASSOC_DELETE' => 0,
					     'totalsize' => 0, 'maxsize' => 0 );
	  }
	  $keyStats[$currentKey][$row[1]] += $row[2];
	  $keyStats[$currentKey]['totalsize'] += $row[3];
	  if ( $row[4] > $keyStats[$currentKey]['maxsize'] ) {
             $keyStats[$currentKey]['maxsize'] = $row[4];
	  }
	}

	$selfURL = $_SERVER['PHP_SELF'] . "?" . $webargs . "&cs=$cs&type=$this->type&key=";
	foreach ( $keyStats as $key => $stats ) {
	  $link = "<a href='" . $selfURL . $key . "'>$key</a>";
	  $row = array( 'keyattr' => $link );
	  foreach ( $stats as $name => $value ) {
	     $row[$name] = $value;
          }
 	  if ( $debug ) { echo "<pre>row\n"; print_r($row); echo "</pre>\n"; }

	  $this->data[] = $row;
	}
	if ( $debug ) { echo "<pre>data\n"; print_r($this->data); echo "</pre>\n"; }
	return $this->data;
    }
}

class CsNotifDetail extends DDPObject {
  var $cols = array(
		    'type'  => 'Type',
		    'app'   => 'Source Application',
		    'moc'   => 'Managed Object Class',
		    'model' => 'Model',
		    'attribute' => 'Attribute',
		    'count'     => 'Count',
		    'totsize'      => 'Total Size',
		    'maxsize'      => 'Maximum Size',
		    'asize'      => 'Average Size'
		    );

    var $title = "Notifications";
    var $type = "";
    var $key = "";

    function __construct($type,$key) {
        parent::__construct("notif");
	$this->defaultOrderBy = "count";
	$this->defaultOrderDir = "DESC";
	$this->type = $type;
	$this->key = $key;
    }

    function getData() {
        global $date;
	global $site;
	global $cs;

	$sql = "
SELECT
 cs_notifications.type AS type,
 cs_application_names.name AS app,
 mo_names.name AS moc,
 model_names.name AS model,
 nead_attrib_names.name AS attribute,
 cs_notifications.count AS count,
 cs_notifications.totalsize AS totsize,
 cs_notifications.maxsize AS maxsize,
 ROUND( cs_notifications.totalsize / cs_notifications.count, 0 ) AS asize
FROM
 cs_notifications, cs_names, cs_application_names, model_names, mo_names, nead_attrib_names, sites
WHERE
 cs_notifications.siteid = sites.id AND sites.name = '$site' AND
 cs_notifications.date = '$date' AND
 cs_notifications.csid = cs_names.id AND cs_names.name = '$cs' AND
 cs_notifications.cs_application_nameid = cs_application_names.id AND
 cs_notifications.moid = mo_names.id AND
 cs_notifications.modelid = model_names.id AND
 cs_notifications.attribid = nead_attrib_names.id
";
	if ( $this->type == "moc" ) {
	  $sql .= " AND mo_names.name = '$this->key'";
	} else {
	  $sql .= " AND cs_application_names.name = '$this->key'";
	}

	$this->populateData($sql);
	return $this->data;
    }
}

$cs = $_GET['cs'];
if ( isset($_GET['type']) ) {
  $type = $_GET['type'];
  $key = $_GET['key'];
  echo "<H2>Notifications for $key</H2>\n";
  $mocTable = new CsNotifDetail($type,$key);
  echo $mocTable->getClientSortableTableStr();
} else {
  echo "<H2>Daily Notification Totals for $cs</H2>\n";

  echo "<H3>By Application</H3>\n";
  $mocTotalTable = new CsNotifTotals("app");
  echo $mocTotalTable->getClientSortableTableStr();

  echo "<H3>By MO Type</H3>\n";
  $mocTotalTable = new CsNotifTotals("moc");
  echo $mocTotalTable->getClientSortableTableStr();
}

include "../common/finalise.php";
?>

