<?php
$YUI_DATATABLE = true;
include "init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
echo "<h1>Database Tables</h1>";

function getTableList()
{
    global $DBName;
    $cols = array(
        array( 'key' => 'tbl', 'db' => 'TABLE_NAME', 'label' => 'Table'),
        array( 'key' => 'data', 'db' => ' ROUND(DATA_LENGTH / 1024 / 1024,3)', 'label' => 'Data Size (Mb)' ),
        array( 'key' => 'idx', 'db' => 'ROUND(INDEX_LENGTH / 1024 / 1024, 3)', 'label' => 'Index Size (Mb)' ),
        array( 'key' => 'avglen', 'db' => 'AVG_ROW_LENGTH', 'label' => 'Avg Row Length (B)' ),
        array( 'key' => 'nrows', 'db' => 'ROUND(DATA_LENGTH / AVG_ROW_LENGTH,0)', 'label' => '# of Rows (approx.)' ),
        array( 'key' => 'ctime', 'db' => 'CREATE_TIME', 'label' => 'Creation Time' ),
        array( 'key' => 'utime', 'db' => 'UPDATE_TIME', 'label' => 'Update Time' )
    );

    $where = " TABLE_SCHEMA = '" . $DBName . "'";

    $table = new SqlTable("Table_List",
        $cols,
        array( 'information_schema.TABLES'),
        $where,
        TRUE,
        array('order' => array( 'by' => 'tbl', 'dir' => 'ASC'),
            'rowsPerPage' => 20,
            'rowsPerPageOptions' => array(50, 100, 250,2000)
        )
    );
    echo $table->getTable();
}

function mainFlow()
{
    getTableList();
}
$statsDB = new StatsDB();
mainFlow();
include "../php/common/finalise.php";
?>
