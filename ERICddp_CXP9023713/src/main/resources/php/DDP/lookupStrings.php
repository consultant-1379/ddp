<?php
$pageTitle = "Lookup Table Data";

include_once "../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

const PARAM = 'param';

function getIdColumn( $table ) {
    global $statsDB;

    $sql = "
SELECT
    COLUMN_NAME
FROM
    information_schema.COLUMNS
WHERE
    TABLE_NAME = '$table' AND
    EXTRA = 'auto_increment'";

    $statsDB->query($sql);

    return $statsDB->getNextRow()[0];
}

function getColumnNames( $table ) {
    global $statsDB;

    $columns = array();
    $sql = "
SELECT
    COLUMN_NAME
FROM
    information_schema.columns
WHERE
    table_name = '$table' AND
    EXTRA != 'auto_increment'";

    $statsDB->query($sql);
    while ( $row = $statsDB->getNextRow() ) {
        $columns[] = $row[0];
    }

    return $columns;
}

function displayData( $table, $idCol, $columns, $range ) {
    drawHeader("$table Data", '1', $table);

    if ( $range ) {
        $hi = max($range);
        $low = min($range);
        $where = "$idCol BETWEEN $low AND $hi";
    } else {
        $where = null;
    }

    $output = SqlTableBuilder::init()
        ->name('Lookup_Data')
        ->tables( array($table) )
        ->where( $where )
        ->addColumn($idCol, $idCol, $idCol);

    foreach ( $columns as $column ) {
        $output->addSimpleColumn($column, $column);
    }

    $output->sortBy($idCol, DDPTable::SORT_DESC)
           ->paginate()
           ->dbScrolling();

    echo $output->build()->getTable();
}

function buildRangeQuery( $table, $date ) {
    return "
SELECT
    today.maxid,
    yesterday.maxid
FROM
    (SELECT
        maxid
    FROM
        ddpadmin.ddp_id_tables,
        ddpadmin.ddp_table_names
    WHERE
        ddp_table_names.id = ddp_id_tables.tableid AND
        ddp_table_names.name = '$table' AND
        date = '$date') as today,
    (SELECT
        maxid
    FROM
        ddpadmin.ddp_id_tables,
        ddpadmin.ddp_table_names
    WHERE
        ddp_table_names.id = ddp_id_tables.tableid AND
        ddp_table_names.name = '$table' AND
        date = '$date' - INTERVAL 1 DAY) as yesterday;
    ";
}

function getRange( $table ) {
    global $date, $statsDB;

    $sql = buildRangeQuery( $table, $date );
    $statsDB->query($sql);

    return $statsDB->getNextRow();
}

function filterCols($cols) {
    $result = "";

    foreach ( $cols as $col ) {
        $splitComma = preg_split("/\",\s*\"/", $col);
        foreach ( $splitComma as $pair ) {
            $splitValues = preg_split("/\":\s*\"/", $pair);
            while ( count($splitValues) > 0 ) {
                $a = array_shift($splitValues);
                $b = array_shift($splitValues);
                $result .= "$a => $b, ";
            }
        }
    }

    return substr( substr( $result, 1 ), 0, -3 );
}

function formatWhere( $arr ) {
    $whereStr = "";

    foreach ( $arr as $where ) {
        $w = str_replace( array("\\n", "\\r"), ' ', $where );
        $whereStr .= $w . " ::: ";
    }
    return substr( $whereStr, 0, -5);
}

function getSqlPlotParamData( $range ) {
    global $statsDB;

    if ( $range ) {
        $hi = max($range);
        $low = min($range);
        $statsDB->query("SELECT * FROM sql_plot_param WHERE id BETWEEN $low AND $hi ORDER BY id ASC");
    } else {
        $statsDB->query("SELECT * FROM sql_plot_param ORDER BY id ASC");
    }

    $data = array();
    while ( $row = $statsDB->getNextNamedRow() ) {
        preg_match_all('/"title":\s*"(.*?)"/', $row[PARAM], $title);
        preg_match_all('/"where":\s*"(.*?)"/s', $row[PARAM], $where);
        preg_match_all('/"whatcol":\s*\{(.*?)}/s', $row[PARAM], $cols);

        $cols = filterCols($cols[1]);
        $where = formatWhere( $where[1] );
        $data[] = array('id' => $row['id'], 'title' => $title[1], 'where' => $where, 'cols' => $cols);
    }

    return $data;
}

function showSqlPlotParamData( $data ) {
    $table = new DDPTable(
        "Info",
        array(
            array(DDPTable::KEY => 'id', DDPTable::LABEL => 'ID', 'type' => 'int'),
            array(DDPTable::KEY => 'title', DDPTable::LABEL => 'Title'),
            array(DDPTable::KEY => 'where', DDPTable::LABEL => 'Where'),
            array(DDPTable::KEY => 'cols', DDPTable::LABEL => 'Cols')
        ),
        array('data' => $data)
    );

    echo $table->getTableWithHeader("sql_plot_param Data", 1, "", "");
}

function main() {
    $table = requestValue('table');

    if ( requestValue('range') ) {
        $range = getRange( $table );
    } else {
        $range = null;
    }

    if ( $table == 'sql_plot_param' ) {
        $data = getSqlPlotParamData( $range );
        showSqlPlotParamData( $data );
    } else {
        $columns = getColumnNames( $table );
        $idCol = getIdColumn( $table );

        displayData( $table, $idCol, $columns, $range );
    }
}

main();

include_once PHP_ROOT . "/common/finalise.php";
