<?php
$pageTitle = "Instrumentation Stats";
if (isset($_GET['chart'])) $UI = false;
include "common/init.php";
$statsDB = new StatsDB();

class Instr { 
    var $name = "";
    var $table = "";
    var $site;
    var $date;

    var $data = array();

    function __construct($table, $name, $site, $date) {
        global $statsDB;
        $this->table = $table;
        $this->name = $name;
        $this->site = $site;
        $this->date = $date;

	$sql = "SELECT " . $table . ".* FROM " . $table . ",sites WHERE siteid = sites.id AND sites.name = '" . $site . "' AND date = '" . $date . "'";
        $statsDB->query($sql);
        while ($row = $statsDB->getNextNamedRow()) {
            foreach ($row as $key => $val) {
                # Eliminate "null" columns in this version of OSS - not to be displayed
                if (!is_null($val)) {
                    $this->data[$key] = $val;
                }
            }
        }
    }

    function getHtml() {
        global $webargs;
?>
    <a name="<?=$this->table?>" />
    <h2><?=$this->name?></h2>
<table border=1>
<?php
        foreach ($this->data as $k => $v) {
            if ($k == "siteid" || $k == "date") continue;
?>
    <tr><td><b><?=$k?></b></td><td>
<?php
            $link = "?" . $webargs . "&detail=" . $this->table . "&metric=" . $k . "&lastn=30";
            $div = $this->table . "_" . $k;
?>
<a href="<?=$link?>"><?=$v?></a>
</td></tr>
<?php
        }
?>
</table>
<?php
    }
}


$tables = array (
    "pdm_instr" => "PDM",
    "pdm_snmp_instr" => "PDM-SNMP",
    "sgw_instr" => "SGW",
    "smia_instr" => "SMIA"
);

$info = array();
if (isset($_POST['lastn'])) $lastn = $_POST['lastn'];
else if (isset($_GET['lastn'])) $lastn = $_GET['lastn'];
else $lastn = 30;

if (isset($_GET['detail'])) {
    $chart = $_GET['detail'];
    $metric = $_GET['metric'];
    $link = "?" . $webargs . "&chart=" . $chart . "&metric=" . $metric . "&lastn=" . $lastn;
    $nlink = "?" . $webargs . "&detail=" . $chart . "&metric=" . $metric;
?>
    <h1><?=$metric?> (last <?=$lastn?> days)</h1>
    <form method=post action=<?=$nlink?>>
Set range: last <input type=text name=lastn size=4 value=<?=$lastn?> /> days
<input type=hidden name=metric value=<?=$metric?> />
<input type=hidden name=chart value=<?=$chart?> />
<input type=submit name=submit value="update" />
</form>
<br />
<img src=<?=$link?> />
<br />
<a href="?<?=$webargs?>">back to overview</a>
<?php
    include "common/finalise.php";
    exit(0);
}

if (isset($_GET['chart'])) {
    $chart = $_GET['chart'];
    $metric = $_GET['metric'];
    include "classes/Graph.class.php";
    $sql = "SELECT date, " . $metric . " FROM " . $chart . ", sites " .
        "WHERE siteid = sites.id AND sites.name = '" . $site . "' AND date BETWEEN " .
        "date_sub('" . $date . "', interval " . $lastn . " day) AND '" . $date . "' ORDER BY date";
    $statsDB->query($sql);
    $data = array();
    $data[$metric] = array();
    while ($row = $statsDB->getNextNamedRow()) {
        $data[$metric][$row['date']] = $row[$metric];
    }
    $dates = array_keys($data[$metric]);
    $g = new Graph(array_shift($dates), array_pop($dates), "day");
    $g->colours = array("red");
    $g->addData($data, true);
    $g->display();
    exit;
}
?>
<a name=top /><h1>Daily Instrumentation Statistics</h1>
<ul>
<?php
foreach ($tables as $tbl => $name) {
    $obj = new Instr($tbl, $name, $site, $date);
    if (count($obj->data) > 0)
        echo "<li><a href=\"#" . $obj->table . "\">" . $obj->name . "</a></li>\n";
    $info[] = $obj;
}
?>
</ul>
<?php

foreach ($info as $obj) {
    if (count($obj->data) > 0) {
        $obj->getHtml();
        echo "<a href=\"#top\">back to top</a><br/>\n";
    }
}

include "common/finalise.php";
?>
