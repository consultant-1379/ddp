<?php

/**
 *Creates table using an array data
 *Takes in an array of key value pair array, table div name, table header, header level.
 *
 *@author Niraj Mhatre
 *
 *@param array $data This is array of key value pair array.
 *@param string $tableDivName This is the table div name.
 *@param string $tableHeader This is the table header.
 *@param integer $tableHeader This is an integer value of header level.
 */
function drawTableFromData( $data, $tableDivName, $tableHeader, $headerLevel ) {
    $columns = array();
    $jsonData = $data[0];
    foreach ( $jsonData as $key => $val ) {
        $columns[] = array( DDPTable::KEY => $key, DDPTable::LABEL => $key);
    }
    $table = new DDPTable(
        $tableDivName,
        $columns,
        array('data' => $data)
    );
    echo $table->getTableWithHeader( $tableHeader, $headerLevel, "", "" );
    echo addLineBreak();
}

/**
 *Structures the data in a format that DDPTable can support and passes it to drawTableFromData() to create table.
 *Takes in an array of file data, table div name, table header, header level.
 *
 *Example in /php/ENIQ/installed_features.php.
 *
 *@author Niraj Mhatre
 *
 *@param array $arrayOfData This is an array of json file.
 *@param string $tableDivName This is the table div name.
 *@param string $tableHeader This is the table header.
 *@param integer $tableHeader This is an integer value of header level.
 */
function getTableFromData( $arrayOfData, $tableDivName, $tableHeader, $headerLevel ) {
    if ( function_exists("conditionHeaderCheck") ) {
        foreach ($arrayOfData as &$arrayElement) {
            $json = json_decode($arrayElement, true);
            $data = array();
            foreach ( $json as $key => $value ) {
                $arr = array();
                foreach ($value as $key => $val) {
                    conditionHeaderCheck( $key );
                    $arr[$key] = $val;
                }
                $data[] = $arr;
            }
            drawTableFromData( $data, $tableDivName, $tableHeader, $headerLevel );
        }
    }
}
