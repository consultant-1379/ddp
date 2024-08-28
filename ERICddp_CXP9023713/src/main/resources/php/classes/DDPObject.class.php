<?php
require_once PHP_ROOT . "/StatsDB.php";

abstract class DDPObject {
    var $pageTitle = "";
    var $statsDB;
    var $subQueriesDB;
    var $serverId;
    var $data = array();
    var $arrayPointer = 0;
    var $cols = array();
    var $id = "";
    var $defaultDateTimeFormat = "H:i";
    var $defaultLimit = "";
    var $defaultOrderBy = "";
    var $defaultOrderDir = "";
    var $limits = array();
    var $serverlist;
    var $rows;
    var $timeCol = "time";
    var $columnTypes = array();
    var $systemStatusCols = array(
        'start' => 'start',
        'end' => 'end'
    );

    function __construct($id = "") {
        $this->statsDB = new StatsDB();
        $this->id = $id;
    }

    abstract function getData();

    function getSystemStatus($start, $end) {
        if (! isset($this->subQueriesDB))
            $this->subQueriesDB = new StatsDB();
        if (! isset($this->serverId)) {
            $sql = "SELECT servers.id FROM servers,sites WHERE servers.type = " . "'MASTER' AND servers.siteid = sites.id AND sites.name = '" . SITE . "'";
            $this->subQueriesDB->query($sql);
            // WP00615 - Added extra code to cater for multiple Master Servers [29-02-2012 eronkeo]
            $data = $this->subQueriesDB->getNextRow();
            $rows = $this->subQueriesDB->getNumRows();
            while ( $rows > 0 ) {
                // Add isset() to check if $serverlist defined
                if (isset($serverlist)) {
                    $serverlist .= "$data[0],";
                } else {
                    $serverlist = "$data[0],";
                }
                $data = $this->subQueriesDB->getNextRow();
                $rows--;
            }
            $this->serverId = substr($serverlist, 0, strlen($serverlist) - 1);
        }

        $sql = "SELECT " .
          "AVG(hires_server_stat.user + hires_server_stat.sys + hires_server_stat.iowait) AS totalcpu," .
          "AVG(hires_server_stat.freeram) AS freemem " .
          "FROM hires_server_stat,servers where time BETWEEN " .
          "'" . $start . "' AND '" . $end . "' AND " .
          "hires_server_stat.serverid in (" . $this->serverId . ")";
        $this->subQueriesDB->query($sql);
        $stats = $this->subQueriesDB->getNextNamedRow();
        foreach ( $stats as $key => $val )
            $stats[$key] = number_format($val, 2, '.', '');
        return $stats;
    }

    function populateData($sql, $doSystemStatus = false) {
        // figure out if we need to limit or order
        $orderBy = $this->getOrderBy();
        if ($orderBy != "")
            $sql .= " ORDER BY " . $orderBy;
        $orderDir = $this->getOrderDir();
        if ($orderDir != "")
            $sql .= " " . $orderDir;
        $limit = $this->getLimit();
        if ($limit != "")
            $sql .= " LIMIT " . $limit;
            // logIt($sql);
        $this->statsDB->query($sql);
        $this->columnTypes = $this->statsDB->getColumnTypes();
        while ( $row = $this->statsDB->getNextNamedRow() ) {
            if ($doSystemStatus) {
                // Assume there is a start and an end
                foreach ( $this->getSystemStatus($row[$this->systemStatusCols['start']], $row[$this->systemStatusCols['end']]) as $key => $val )
                    $row[$key] = $val;
            }
            $this->data[] = $row;
        }
    }

    function getCount() {
      return count($this->data);
    }

    function getNext() {
        if (count($this->data) == 0) {
            if (count($this->getData()) == 0) {
                return false;
            }
        }
        if (count($this->data) <= $this->arrayPointer) {
            return false;
        }
        return $this->data[$this->arrayPointer ++];
    }

    function rewind() {
        $this->arrayPointer = 0;
    }

    function getExcelWorksheet() {
        $this->rewind();
        print "<?xml version=\"1.0\"?>\n";
        print "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $createdDate = date('Y-m-d\TH:i:s');
        $rowCount = count($this->getData()) + 1;
        ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40"> <DocumentProperties
    xmlns="urn:schemas-microsoft-com:office:office"> <Author>Diagnostic
Data Processing</Author> <LastAuthor>Diagnostic Data Processing</LastAuthor>
<Created><?=$createdDate?></Created> <LastSaved><?=$createdDate?></LastSaved>
<Company>Ericsson</Company> <Version>11.6360</Version> </DocumentProperties>
<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel"> <WindowHeight>8535</WindowHeight>
<WindowWidth>12345</WindowWidth> <WindowTopX>480</WindowTopX> <WindowTopY>90</WindowTopY>
<ProtectStructure>False</ProtectStructure> <ProtectWindows>False</ProtectWindows>
</ExcelWorkbook> <Styles>
<Style ss:ID="Default" ss:Name="Normal">
<Alignment ss:Vertical="Bottom"/>
<Borders/>
<Font/>
<Interior/>
<NumberFormat/>
<Protection/>
</Style>
<Style ss:ID="s21" ss:Name="Hyperlink">
<Font ss:Color="#0000FF" ss:Underline="Single"/>
</Style>
<Style ss:ID="s23">
<Font x:Family="Swiss" ss:Bold="1"/>
</Style>
</Styles> <Worksheet ss:Name="<?=$this->title?>">
<Table ss:ExpandedColumnCount="<?=count($this->cols);?>"
    ss:ExpandedRowCount="<?=$rowCount;?>" x:FullColumns="1" x:FullRows="1">
    <Row ss:StyleID="s23">
<?php
        foreach ( $this->cols as $name )
            echo "<Cell><Data ss:Type=\"String\">" . $name . "</Data></Cell>\n";
        ?>
</Row>
<?php
        while ( $row = $this->getNext() ) {
            echo "<Row>\n";
            foreach ( array_keys($this->cols) as $key ) {
                ?>
<Cell ss:StyleID="s21"> <Data ss:Type="String"><?=$row[$key]?></Data></Cell>
<?php
            }
            echo "</Row>\n";
        }
        ?>
</Table>
<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"> <Print>
<ValidPrinterInfo /> <HorizontalResolution>300</HorizontalResolution> <VerticalResolution>300</VerticalResolution>
</Print> <Selected /> <Panes> <Pane> <Number>3</Number> <ActiveRow>1</ActiveRow>
</Pane> </Panes> <ProtectObjects>False</ProtectObjects> <ProtectScenarios>False</ProtectScenarios>
</WorksheetOptions> </Worksheet> </Workbook>
<?php
        $this->rewind();
    }


    function getDataRows(){
        $keys = array();
        // Don't know what this check is for just fixing
        // 'Only variables should be passed by reference'
        $colKeys = array_keys($this->cols);
        if ( end($colKeys) === (count($this->cols)-1) ) {
            foreach ( $this->cols as &$columnDef ) {
                $keys[] = $columnDef['key'];
            }
        } else {
            $keys = array_keys($this->cols);
        }

        $dataRows = array();
        while ( $row = $this->getNext() ) {
            $rowValues = array();
            foreach ( $keys as $key ) {
                if ( $this->columnTypes[$key] == "string" || $this->columnTypes[$key] == "time" || $this->columnTypes[$key] == "datetime" || $this->columnTypes[$key] == "date" ) {
                    $row[$key] = '"' . addslashes($row[$key]) . '"';
                }
                $rowValues[] = $key . ": " . $row[$key];
            }
            $dataRows[] = "  { " . implode(', ', $rowValues) . " }";
        }
        return $dataRows;
    }

    function getClientSortableTableStr($defaultRowsPerPage = 0, $rowsPerPageOptions = NULL, $customFunction = NULL, $customRowFormatterFunction = "") {
        global $php_webroot;
        global $debug;

        if ( $debug ) { echo "<pre>DDPObject.getClientSortableTableStr cols"; print_r($this->cols); echo "</pre>\n"; }

        $suffix = "_" . $this->id;

        # Compose the 'columnDefs' string
        $columnDefs = array();
        // Don't know what this check is for just fixing
        // 'Only variables should be passed by reference'
        $colKeys = array_keys($this->cols);
        if ( end($colKeys) === (count($this->cols)-1) ) {
            foreach ( $this->cols as &$columnDef ) {
                $columnDefStr = '  {key: "' . $columnDef['key'] . '", label: "' . $columnDef['label'] . '", sortable: true';
                if ( array_key_exists('formatter', $columnDef) ) {
                    $columnDefStr .= ', formatter: ' . $columnDef['formatter'];
                }
                if ( array_key_exists('className', $columnDef) ) {
                    $columnDefStr .= ', className: "' . $columnDef['className'] . '"';
                }
                if ( array_key_exists('sortOptions', $columnDef) ) {
                    $columnDefStr .= ', sortOptions: {';
                    if ( array_key_exists('sortFunction', $columnDef['sortOptions']) ) {
                        $columnDefStr .= 'sortFunction: ' . $columnDef['sortOptions']['sortFunction'];
                    }
                    $columnDefStr .= '}';
                }
                 $columnDefs[] = $columnDefStr . '}';
            }
        } else {
            foreach ( array_keys($this->cols) as $key ) {
                $columnDefs[] = '  {key: "' . $key . '", label: "' . $this->cols[$key] . '", sortable: true}';
            }
        }
        $columnDefStr = " var columnDefs = [\n" . implode(",\n", $columnDefs) . "\n ];\n";

        # Get the 'tableData' string
        $dataRows = $this->getDataRows();
        $tableDataStr = " var tableData = [\n" . implode(",\n", $dataRows) . "\n ];\n";

        # Add the paginator configuration
        $configStrs = array();
        if ($defaultRowsPerPage > 0) {
            if ($rowsPerPageOptions == NULL) {
                $paginatorStr = <<<EOC
  paginator: new YAHOO.widget.Paginator({
   rowsPerPage: $defaultRowsPerPage
  })
EOC;
            } else {
                $rowsPerPageStr = implode(",", $rowsPerPageOptions);
                $paginatorStr = <<<EOC
  paginator: new YAHOO.widget.Paginator({
   rowsPerPage: $defaultRowsPerPage,
   template: YAHOO.widget.Paginator.TEMPLATE_ROWS_PER_PAGE,
   rowsPerPageOptions: [$defaultRowsPerPage,$rowsPerPageStr]
   })
EOC;
            }
            $configStrs[] = $paginatorStr;
        }

        # Add the default column and its order ('ASC' or 'DESC') by which the table got sorted
        $orderBy = $this->getOrderBy();
        if ( $debug ) { echo "<pre>getClientSortableTableStr orderBy=$orderBy</pre>\n"; }
        if ($orderBy != "") {
            # Validate that the requested sort column exists (could have come from the URL)
            $sortColumnExists = FALSE;
            // Don't know what this check is for just fixing
            // 'Only variables should be passed by reference'
            $colKeys = array_keys($this->cols);
            if ( end($colKeys) === (count($this->cols)-1) ) {
                foreach ($this->cols as $col) {
                    if ( $col['key'] == $orderBy ) {
                        $sortColumnExists = TRUE;
                    }
                }
            } else if ( array_key_exists($orderBy, $this->cols) ) {
                $sortColumnExists = TRUE;
            }
            if ( $sortColumnExists ) {
                $orderDir = "asc";
                if ($this->getOrderDir() == 'DESC') {
                    $orderDir = "desc";
                }
                $configStrs[] = <<<EOC
  sortedBy: { key: '$orderBy', dir: '$orderDir' }
EOC;
            }
        }

        # Add the custom row-formatter function, if any
        if ($customRowFormatterFunction != "") {
            $configStrs[] = <<<EOC
  formatRow: $customRowFormatterFunction
EOC;
        }

        # Prepare the final 'oConfigs' configuration string
        $configStr = implode(",", $configStrs);
        $configStr = <<<EOC
  var oConfigs = {
$configStr
  };
EOC;

        # Add the name of custom function, if any, like 'setupMenu'
        $customFunctionStr = "";
        if ( $customFunction != NULL ) {
          $customFunctionStr = " " . $customFunction . "(dt);";
        }

        # Instantiate an YUI datatable object
        $codeStr = <<<EOC
 var ds = new YAHOO.util.DataSource(tableData);
 ds.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
 var dt = new YAHOO.widget.DataTable("tablediv_$this->id",
                                     columnDefs,
                                     ds,
                                     oConfigs
                                  );

EOC;
        $str = <<<EOS
<script type="text/javascript" src="$php_webroot/classes/ddptable.js"></script>
<div id="tablediv_$this->id" class="yui-skin-sam"></div>
<script type="text/javascript">
function showTable$suffix() {
$columnDefStr
$tableDataStr
$configStr
$codeStr
$customFunctionStr
}
YAHOO.util.Event.addListener(window, "load", showTable$suffix);
</script>
EOS;

        return $str;
    }

    function getHtmlTableStr($doLinkOnStart = false) {
        $dateTimeFormat = $this->defaultDateTimeFormat;
        $str = "<table border=1>\n";
        $str .= "<tr>";
        foreach ( $this->cols as $name )
            $str .= "<th>" . $name . "</th>";
        $str .= "</tr>\n";
        while ( $row = $this->getNext() ) {
            $str .= "<tr>";
            foreach ( array_keys($this->cols) as $key ) {
                if ($key == "start" || $key == "end") {
                    $time = date($dateTimeFormat, strtotime($row[$key]));
                    if ($key == "start" && $doLinkOnStart) {
                        $str .= "<td><a href=\"#" . $this->arrayPointer . "\">" . $time . "</a></td>";
                    } else {
                        $str .= "<td>" . $time . "</td>";
                    }
                } else {
                    $str .= "<td>" . $row[$key] . "</td>";
                }
            }
            $str .= "</tr>\n";
        }
        $str .= "</table>\n";
        $this->rewind();

        return $str;
    }

    function getHtmlTable($doLinkOnStart = false) {
        echo $this->getHtmlTableStr($doLinkOnStart);
    }

    function getSortableHtmlTableStr($doLinkOnStart = false) {
        $str = "";

        $id = $this->id;
        $dateTimeFormat = $this->defaultDateTimeFormat;
        $orderBy = $this->getOrderBy();
        $limit = $this->getLimit();
        $orderDir = $this->getOrderDir();
        $str .= "<table border=1>\n<tr>";
        foreach ( $this->cols as $key => $name ) {
            $class = "";
            $dir = "";
            if ($orderBy == $key) {
                // $class = " class=sorted";
                if ($orderDir == "ASC")
                    $dir = "<img src=" . PHP_WEBROOT . "/common/images/dnarrow.png />";
                else if ($orderDir == "DESC")
                    $dir = "<img src=" . PHP_WEBROOT . "/common/images/uparrow.png />";
            }
            $str .= "<th" . $class . ">" . $dir . "<a href=\"" . $this->getLink($key) . "\">" . $name . "</a></th>\n";
        }
        $str .= "</tr>\n";
        while ( $row = $this->getNext() ) {
            $str .= "<tr>";
            foreach ( array_keys($this->cols) as $key ) {
                $class = "";
                if ($key == $orderBy)
                    $class = " class=sorted";
                if ($key == "start" || $key == "end") {
                    $time = date($dateTimeFormat, strtotime($row[$key]));
                    if ($key == "start" && $doLinkOnStart) {
                        $str .= "<td" . $class . '><a href="#' . $this->arrayPointer . '">' . $time . "</a></td>";
                    } else {
                        $str .= "<td" . $class . ">" . $time . "</td>";
                    }
                } else {
                    // use either the key or the name depending on the query passed
                    if (isset($row[$key]))
                        $str .= "<td" . $class . ">" . $row[$key] . "</td>";
                    else if (isset($row[$this->cols[$key]]))
                        $str .= "<td" . $class . ">" . $row[$this->cols[$key]] . "</td>";
                    else
                        $str .= "<td" . $class . "></td>";
                }
            }
            $str .= "</tr>\n";
        }
        $str .= "</table>\n";
        $this->rewind();
        if (is_array($this->limits) && count($this->limits) > 0) {
            $formStr = "<form name=limits>" . "Limit results by: " . "<select name=limit onChange=\"changeURL(this.form, '" . $this->getLink() . "', '" . $id . "');\">";
            foreach ( $this->limits as $key => $val ) {
                if ($limit == $key)
                    $formStr .= "<option value='" . $key . "' selected>" . $val . "</option>\n";
                else
                    $formStr .= "<option value='" . $key . "'>" . $val . "</option>\n";
            }
            $formStr .= "
</select>
</form>
";
            $str .= $formStr;
        }

        return $str;
    }

    function getSortableHtmlTable($doLinkOnStart = false) {
        echo $this->getSortableHtmlTableStr($doLinkOnStart);
    }

    function getLink($orderBy = "", $orderDir = "", $limit = "") {
        $id = $this->id;
        $currOrd = $this->getOrderDir();
        if ($orderDir == "") {
            if ($orderBy == $this->getOrderBy()) {
                switch ($currOrd) {
                    case "ASC" :
                        $orderDir = "DESC";
                        break;
                    case "DESC" :
                        $orderDir = "ASC";
                        break;
                }
            } else if ($currOrd == "")
                $orderDir = "ASC";
            else
                $orderDir = $currOrd;
        }
        if ($limit == "")
            $limit = $this->getLimit();
        if ($orderBy == "")
            $orderBy = $this->getOrderBy();
        if ($orderDir == "") {
            $orderDir = $this->getOrderDir();
        }
        $link = "?";
        foreach ( $_GET as $key => $val ) {
            if ($key != $id . "_orderby" && $key != $id . "_orderdir" && $key != $id . "_limit")
                $link .= $key . "=" . $val . "&";
        }
        $link .= $id . "_orderby=" . $orderBy . "&" . $id . "_orderdir=" . $orderDir . "&" . $id . "_limit=" . $limit;
        return $link;
    }

    function getIdParam($param, $default = "") {
        if (isset($_GET[$this->id . "_" . $param]))
            return $_GET[$this->id . "_" . $param];
        return $default;
    }

    function setDateTimeFormat($dateTimeFormat) {
        $this->defaultDateTimeFormat = $dateTimeFormat;
    }

    function getLimit() {
        return $this->getIdParam("limit", $this->defaultLimit);
    }

    function getOrderBy() {
        return $this->getIdParam("orderby", $this->defaultOrderBy);
    }

    function getOrderDir() {
        return $this->getIdParam("orderdir", $this->defaultOrderDir);
    }

    function __destruct() {
    }
}
?>
