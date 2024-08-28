<?php
# Generic class for DDP data
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class GenericData extends DDPObject {

    var $title = "Generic Data";

    var $cols = array();
    var $timeCol = "time";
    var $tables = array();
    var $filter = "";
    var $start = "";
    var $end = "";
    var $aggFunc = "";
    var $aggInt = "";
    var $timeFormat = "";

    function __construct($xml, $attrs) {
        parent::__construct("generic_data");

        # process XML configuration file
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->load($xml);
        $tc = $dom->getElementsByTagName("timecol")->item(0)->getAttribute("name");
        if ($tc != "" && $tc == "time" || $tc == "date") {
            $this->timeCol = $tc;
            $this->cols[$tc] = ucfirst($tc);
        }
        $colItems = $dom->getElementsByTagName("metric");
        foreach ($colItems as $metric) {
            $this->cols[$metric->getAttribute("name")] = $metric->getAttribute("title");
        }
        $tables = $dom->getElementsByTagName("table");
        foreach($tables as $table) {
            $this->tables[] = $table->getAttribute("name");
        }
        $filters = $dom->getElementsByTagName("filter");
        $fArr = array();
        foreach($filters as $f) {
            $filterText = $f->textContent;
            # do we have to substitute a string in this filter?
            if (isset($attrs[$f->getAttribute("name")])) $filterText = sprintf($filterText, $attrs[$f->getAttribute("name")]);
            $fArr[] = $this->statsDB->escape($filterText);
        }
        $this->filter = implode(" AND ", $fArr);

        # process user defined attributes
        if (isset($attrs["aggfunc"])) {
            if (strcasecmp($attrs["aggfunc"], "MIN") == 0 || strcasecmp($attrs["aggfunc"], "MAX") == 0 || strcasecmp($attrs["aggfunc"], "AVG") == 0)
                $this->aggFunc = $attrs["aggfunc"];
        }
        # TODO: how to aggregate?
        if (isset($attrs["aggint"])) $this->aggInt = $attrs["aggint"];
        $timeRegexp = '/^2[0-9][0-9][0-9]-[0-1][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/';
        if (isset($attrs["start"]) && preg_match($timeRegexp, $attrs["start"]))
            $this->start = $attrs["start"];
        if (isset($attrs["end"]) && preg_match($timeRegexp, $attrs["end"]))
            $this->end = $attrs["end"];
        if (isset($attrs['timeformat']) && (strcmp($attrs['timeformat'], "unix") == 0 || strcmp($attrs['timeformat'], "millis") == 0))
            $this->timeFormat = $attrs['timeformat'];
    }

    function getData($useName = false) {
        $sql = "SELECT ";
        $delim = "";
        foreach ($this->cols as $col => $name) {
            if (strcmp($col, $this->timeCol) == 0) {
                if (strcmp($this->timeFormat, "unix") == 0) $sql .= $delim . "UNIX_TIMESTAMP(" . $col . ")";
                else if (strcmp($this->timeFormat, "millis") == 0) $sql .= $delim . "UNIX_TIMESTAMP(" . $col . ") * 1000";
                else $sql .= $delim . $col;
                if ($useName) $sql .= " AS '" . ucfirst($col) . "'";
                else if (strcmp($this->timeFormat, "") != 0) $sql .= " AS " . $col;
            } else {
                if ($this->aggFunc != "" && $this->aggInt != "") $sql .= $delim . $this->aggFunc . "(" . $col . ")";
                else $sql .= $delim . $col;
            }
            if ($useName) $sql .= " AS '" . $name . "'";
            $delim = ",";
        }
        $sql .= " FROM " . implode(", ", $this->tables);
        # do we have any filters at all - time, columns, anything?
        if ($this->filter != "" || $this->start != "" || $this->end != "") {
            $sql .= " WHERE ";
            $delim = "";
            if ($this->filter != "") {
                $sql .= $this->filter;
                $delim = " AND ";
            }
            if ($this->start != "" && $this->end != "") $sql .= $delim . $this->timeCol . " BETWEEN '" . $this->start . "' AND '" . $this->end . "'";
            else if ($this->start != "") $sql .= $delim . $this->timeCol . " > '" . $this->start . "'";
            else if ($this->end != "") $sql .= $delim . $this->timeCol . " < '" . $this->end . "'";
            if ($this->start != "" || $this->end != "") $sql .= " GROUP BY " . $this->timeCol . " ORDER BY " . $this->timeCol;
        }
        $this->populateData($sql);
        return $this->data;
    }

    function getJSON() {
        $results = array();
        $prefix = "";
        while ($row = $this->getNext()) {
            foreach ($row as $k => $v) {
                if ($k == $this->timeCol) continue;
                if (! isset($results[$k])) $results[$k] = "{label: \"" . $k . "\", data: [";
                $results[$k] .= $prefix . "\n[" . $row[$this->timeCol] . "," . $v . "]";
            }
            $prefix = ",";
        }
        echo "[";
        $prefix = "";
        foreach ($results as $k => $v) {
            $v .= "]}";
            echo $prefix . $v;
            $prefix = ",";
        }
        echo "]";
        $this->rewind();
    }
}
?>
