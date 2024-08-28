<?php
class ExcelWorkbook {
    var $objects = array();
    var $createdDate;

    function __construct() {
        // send headers as early as possible
        $this->createdDate = date('Y-m-d\TH:i:s');
        header( "content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" );
        header("Cache-Control: private, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) $displayType = "inline";
        else $displayType = "attachment";
        $displayType = "attachment";
        header("Content-Disposition: " . $displayType . "; filename=\"ddp-report-" . $this->createdDate . ".xml\"");
    }

    function addObject($obj) {
        if (is_subclass_of($obj, "DDPObject")) {
            $this->objects[] = $obj;
            return true;
        } elseif ( is_a( $obj, "SqlTable" ) ) {
            $this->objects[] = $obj->getDDPTable();
            return true;
        } elseif ( is_a( $obj, "DDPTable" ) ) {
            $this->objects[] = $obj;
            return true;
        } elseif( is_string($obj)) {
            $jsonData = json_decode($obj, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                $this->objects[] = $jsonData;
                return true;
            } else {
                return false;
            }
        } else return false;
    }

    function write() {
        echo <<< EHTML
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:html="http://www.w3.org/TR/REC-html40">
<DocumentProperties
     xmlns="urn:schemas-microsoft-com:office:office">
<Author>Diagnostic Data Processing</Author>
<LastAuthor>Diagnostic Data Processing</LastAuthor>
<Created>$this->createdDate</Created>
<LastSaved>$this->createdDate</LastSaved>
<Company>Ericsson</Company>
<Version>11.6360</Version>
  </DocumentProperties>
  <ExcelWorkbook
     xmlns="urn:schemas-microsoft-com:office:excel">
  <WindowHeight>8535</WindowHeight>
  <WindowWidth>12345</WindowWidth>
  <WindowTopX>480</WindowTopX>
  <WindowTopY>90</WindowTopY>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
  </ExcelWorkbook>
  <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
  <Alignment ss:Vertical="Bottom"/>
  <Borders/>
  <Font/>
  <Interior/>
  <NumberFormat/>
  <Protection/>
  </Style>
  <Style ss:ID="s21" ss:Name="Content">
  <Font ss:Color="#000000"/>
  </Style>
  <Style ss:ID="s23" ss:Name="Header">
  <Font x:Family="Swiss" ss:Bold="1"/>
  </Style>
  </Styles>
EHTML;

  $pageNum = 0;
        foreach($this->objects as $obj) {
            $pageNum++;
            $title = "Page $pageNum";
            $columns;
            $rows;

            if (is_subclass_of($obj, "DDPObject")) {
                if (isset($obj->title)) $title = $obj->title;
                elseif (isset($obj->id)) $title = $obj->id;
                $rows = $obj->getData();
                $columns = $obj->cols;
            } elseif (is_a( $obj, "DDPTable" )) {
                if (isset($obj->title)) $title = $obj->title;
                elseif ( $obj->getName() ) $title = $obj->getName();
                $columns = $obj->getColumns();
                # Remove any invisible columns
                foreach ( $columns as $colKey => $colVal) {
                    if ( array_key_exists('visible', $colVal ) && !$colVal['visible'] ) {
                        unset($columns[$colKey]);
                    }
                }
                $dataSource = $obj->getDataSource();
                $rows = $dataSource['data'];
            } else { # Is data from JSON
                if (isset($obj['name'])) $title = $obj['name'];
                $columns = $obj['columns'];
                # Remove any invisible columns
                foreach ( $columns as $colKey => $colVal) {
                    if ( array_key_exists('visible', $colVal ) && !$colVal['visible'] ) {
                        unset($columns[$colKey]);
                    }
                }
                $rows = $obj['data'];
                debugMsg("rows", $rows);
            }
            $rowCount = count($rows) + 1;
            $columnCount = count($columns);
            echo <<< EHTML
  <Worksheet ss:Name="$title">
  <Table ss:ExpandedColumnCount="$columnCount" ss:ExpandedRowCount="$rowCount" x:FullColumns="1" x:FullRows="1">
   <Row ss:StyleID="s23">

EHTML;
            foreach ($columns as $col) {
                $name = is_array($col) ? $col['label'] : $col;
                echo "    <Cell><Data ss:Type=\"String\">" . $name . "</Data></Cell>\n";
            }
            echo "   </Row>\n";

            foreach( $rows as $row ) {
                debugMsg("row", $row);
                echo "   <Row>\n";
                foreach ($columns as $colKey => $colVal) {
                    $key = is_array($colVal) ? $colVal['key'] : $colKey;
                    $val = "UNKNOWN";
                    if (isset($row[$key])) $val = $row[$key];
                    else if (isset($row[$obj->cols[$key]])) $val = $row[$obj->cols[$key]];
                    if (is_numeric($val)) $type = "Number";
                    else $type = "String";
                    echo <<< EHTML
    <Cell ss:StyleID="s21"><Data ss:Type="$type">$val</Data></Cell>

EHTML;
                }
                echo "   </Row>\n";
            }
            echo <<< EHTML
  </Table>
  <WorksheetOptions
     xmlns="urn:schemas-microsoft-com:office:excel">
  <Print>
  <ValidPrinterInfo/>
  <HorizontalResolution>300</HorizontalResolution>
  <VerticalResolution>300</VerticalResolution>
  </Print>
  <Selected/>
  <Panes>
  <Pane>
  <Number>3</Number>
  <ActiveRow>1</ActiveRow>
  </Pane>
  </Panes>
  <ProtectObjects>False</ProtectObjects>
  <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>

EHTML;
        }
    echo "</Workbook>\n";
    }
}
