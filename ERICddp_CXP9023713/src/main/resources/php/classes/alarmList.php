<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class AlarmList extends DDPObject {
    var $title = "Alarm List";
    var $cols = array();
    var $htmlTable;

    function __construct($table) {
        parent::__construct();
        $this->pageTitle = "Alarm List";
        $this->htmlTable = $table;
        $this->data = array();
    }

    function getData() {
        $fp = @fopen($this->htmlTable, "r");
        if ($fp) {
            $titleLine = str_replace("\n", "", str_replace("</b></td> <td><b>", ",",
                str_replace("</b></td> </tr>", "", str_replace(" <tr> <td><b>", "", fgets($fp)))));
            $this->cols = explode(",", $titleLine);
            while (! feof($fp)) {
                $dataLine = str_replace("\n", "", str_replace("</td> <td>", "###",
                    str_replace("</td>  </tr>", "", str_replace(" <tr> <td>", "", fgets($fp)))));
                $htmlEls = array("<pre>","<br>","</pre>");
                foreach ($htmlEls as $el) $dataLine = str_replace($el, "", $dataLine);
                $tmpcols = explode("###", $dataLine);
                $tmprow = array();
                for ($i = 0 ; $i < count($this->cols) ; $i++) {
                    $tmprow[$i] = $tmpcols[$i];
                }
                $this->data[] = $tmprow;
            }
        }
        @fclose($fp);
        return $this->data;
    }
}
?>
