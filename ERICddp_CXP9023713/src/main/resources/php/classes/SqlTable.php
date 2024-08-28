<?php
require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

/**
 * A Builder class for SqlTable. To use
 * $sqlTable = SqlTable::init()
 *     ->title('Table Name')
 *     ->tables( array( 'table1', 'table2' )
 *     ->addSimpleColumn('table1.col1', 'Column Label 1')
 *     ->addSimpleColumn('table1.col2', 'Column Label 2')
 *     ->build();
 */
class SqlTableBuilder {
    private static $DEFAULT_PAGE_SIZES = array( 20, 100, 1000, 10000, 100000 );

    var $name;
    var $tables;
    private $sqlExtras = array();
    var $columns =  array();
    var $where;
    var $prefetch = TRUE;
    var $options;
    public static function init() {
        return new self;
    }

    /*
     * Name of the table, needs to be distinct on the page, shouldn't have any spaces
     */
    public function name($name) {
        $this->name = $name;
        return $this;
    }

    /*
     * Tables in the query, i.e. what goes in the FROM
     */
    public function tables($tables) {
        $this->tables = $tables;
        return $this;
    }

    /*
     * Join on table
     */
    public function join($reftable, $condition, $join = SqlTable::JOIN, $reftableas = null) {
        $joinSql = $join . " " . $reftable;
        if ( ! is_null($reftableas) ) {
            $joinSql .= " AS " . $reftableas;
        }
        $joinSql .= " ON " . $condition;

        if ( ! array_key_exists(SqlTable::JOIN, $this->sqlExtras) ) {
            $this->sqlExtras[SqlTable::JOIN] = array();
        }
        $this->sqlExtras[SqlTable::JOIN][] = $joinSql;

        return $this;
    }

    /*
     * Content of the WHERE in the query
     */
    public function where($where) {
        $this->where = $where;
        return $this;
    }

    /*
     * Add a column to the table. This should be the "normal" case
     * If you plan on using the column for initial sorting, use addColumn
     * instead
     *
     *  dbColumn: table column or SQL expression (e.g. IFNULL(col,0))
     *  label: Label used for the column header
     */
    public function addSimpleColumn($dbColumn, $label) {
        $this->_addColumn(
            'column_' . count($this->columns),
            $dbColumn,
            $label
            );
        return $this;
    }

    /*
     * Add a column to the table. Normally, you should use addSimpleColumn
     * unless you require special behaviour for this column
     *
     *  key: id of the column, can be referred to by sortBy
     *  dbColumn: table column or SQL expression (e.g. IFNULL(col,0))
     *  label: Label used for the column header
     *  formatter: Name of formatting function for column. This is normally
     *    not used as SqlTable will automatically pick the correct formatter
     *    based on the datatype of dbColumn. One valid usage is when dbColumn
     *    is a DATETIME and you only what to display time, then formatter
     *    should be 'ddpFormatTime'
     */
    public function addColumn($key, $dbColumn, $label, $formatter = NULL) {
        $this->_addColumn($key, $dbColumn, $label, $formatter);
        return $this;
    }

    /*
     * Add a column that will not be display. This is used when you have a
     * content menu and you want to provide an "id" for the selected rows
     *
     *  key: id of the column, can be referred to by $selectedIdCol in ctxMenu
     *  dbColumn: table column or SQL expression (e.g. IFNULL(col,0))
     */
    public function addHiddenColumn($key, $dbColumn) {
        $this->_addColumn($key, $dbColumn, NULL, NULL, false);
        return $this;
    }

    /*
     * Set the column to sort by, note the column identified by key
     * must have been added using addColumn
     *
     * key: key of the column to sort by
     * direction: DDPTable::SORT_ASC or DDPTable::SORT_DESC
     */
    public function sortBy($key, $direction) {
        if ( is_null($this->options) ) {
            $this->options = array();
        }
        $this->options[DDPTable::ORDER] = array(
            DDPTable::SORT_BY => $key,
            DDPTable::SORT_DIRECTION => $direction
        );

        return $this;
    }

    /*
     * List of attributes to GROUP BY
     *
     */
    public function groupBy($groupBy) {
        $this->sqlExtras[SqlTable::GROUP_BY] = $groupBy;
        return $this;
    }

    /*
     * Paginate the table
     *
     * page_sizes: If not given, then use the DEFAULT_PAGE_SIZES. This should be the normal usage
     *             If you need to customise the behaviour, the page_sizes should be an array
     *             of numbers, first number is the initial page size. If there is more then one
     *             element in the array, then the remaining entries will be used to populate the
     *             alternate pages sizes in the drop down menu.
     */
    public function paginate($page_sizes = NULL) {
        if ( is_null($this->options) ) {
            $this->options = array();
        }

        if ( is_null($page_sizes) ) {
            $page_sizes = self::$DEFAULT_PAGE_SIZES;
        }

        $this->options[DDPTable::ROWS_PER_PAGE] = $page_sizes[0];
        if ( count($page_sizes) > 1 ) {
            $other_page_sizes = $page_sizes;
            array_shift($other_page_sizes);
            $this->options[DDPTable::ROWS_PER_PAGE_OPTIONS] = $other_page_sizes;
        }

        return $this;
    }

    /*
     * Provide a context menu that will open a URL which includes the selected row/rows
     *
     * menuItemParam: This is used to identify which item in the menu was selected, e.g. if
     *  menuItemParam='mytable' and the user selected the first menu item where
     *  menuEntries=( 'item1' => 'Item 1', 'item2' => 'Item 2' )
     *  then the URL opened will contain mytable=item1
     * multiSelect: TRUE to allow multiple entries to be select, FALSE to only allow a single entry
     * menuEntries: array of menuItemId => menuItemLabel, menuItemId should not contain spaces or special chars
     * callbackURL: The URL to open, menuItemParam will be appended and a "selected" param will be
     *  added which will a comma seperated list of id column values for the selected rows
     * selectedIdCol: the key of the id column
     */
    public function ctxMenu($menuItemParam, $multiSelect, $menuEntries, $callbackURL, $selectedIdCol) {
        if ( is_null($this->options) ) {
            $this->options = array();
        }
        $this->options[DDPTable::CTX_MENU] = array(
            DDPTable::KEY => $menuItemParam,
            DDPTable::MULTI => $multiSelect,
            DDPTable::MENU => $menuEntries,
            DDPTable::URL => $callbackURL,
            DDPTable::COL => $selectedIdCol
        );

        return $this;
    }

    /*
     * Use DB scrolling. For this, the data is not fetched in a single query. Instead, only the rows
     * to display the current page are fetched. When the user selects another page, then we go back
     * to the DB and fetch the next page of rows
     * This should only be used if the number of rows matching the query can be in the thousands
     */
    public function dbScrolling() {
        $this->prefetch = FALSE;
        return $this;
    }


    /*
     * Last function to call, returns the SqlTable.
     */
    public function build() {
        return new SqlTable(
            $this->name,
            $this->columns,
            $this->tables,
            $this->where,
            $this->prefetch,
            $this->options,
            $this->sqlExtras
        );
    }

    private function &_addColumn($key, $dbColumn, $label = NULL, $formatter = NULL, $visible = NULL) {
        $columnDef = array(
            DDPTable::KEY => $key,
            DDPTable::DB => $dbColumn,
        );
        # label can be null for hidden columns
        if ( ! is_null($label) ) {
            $columnDef[DDPTable::LABEL] = $label;
        }
        if ( ! is_null($formatter) ) {
            $columnDef[DDPTable::FORMATTER] = $formatter;
        }
        if ( ! is_null($visible) ) {
            $columnDef[DDPTable::VISIBLE] = $visible;
        }

        $this->columns[] = $columnDef;

        return $columnDef;
    }
}

/*
  Generates a Javascript table which display data fetched from the database.

  The table will operate in one of two modes, based on the prefetch parameter
  - If prefetch is true, all the rows a read from the database and provided in the
  generated javascript.
  - If prefetch is false, the query is executed to find out the numbe of rows
  it returns but the rows are not fetched. Instead, the query is written to a temporary
  file and the id of the file is return in the generated javascript. The Javascript
  table will then issue GET requests to query.js with the id of the query file, the
  request will also specify the offset and number of results to return, allowing the
  table to "page" through the result set as the user click prev/next on the table.
  The main use case for this is where the number of rows returned by the query can
  be over several thousand, i.e. beyond the memory limits of a php page.

  See DDPTable for info about columns and options args.
  Note for SqlTable columns will also have db field
  db => column read from the DB, if it's not present, then it's assumed to be the same as key
  Also the type of the column is automatically determined from the database
*/
class SqlTable {
    public static $UNKNOWN = -1;
    private $name;
    private $columns;
    private $tables;
    private $where;
    private $prefetch;
    private $options;
    private $dataSource;
    private $totalRows;

    const GROUP_BY = 'GROUP BY';
    const JOIN = 'JOIN';
    const LEFT_OUTER_JOIN = 'LEFT OUTER JOIN';
    
    public function __construct($name, $columns, $tables, $where, $prefetch, $options = null, $sqlExtras = null) {
        global $php_webroot, $web_temp_dir;

        $this->name = $name;
        $this->columns = $columns;
        $this->tables = $tables;
        $this->where = $where;
        $this->prefetch = $prefetch;
        $this->options = $options;

        $statsDB = new StatsDB();

        $baseQuery = $this->getSql($sqlExtras);

        // baseQuery doesn't have the sort order because the query.php may append a sort order
        $querySql = $baseQuery;
        if ( ! is_null($this->options) && array_key_exists(DDPTable::ORDER, $this->options) ) {
            $querySql .= " ORDER BY " . $this->options[DDPTable::ORDER]['by'] . " " .
                      $this->options[DDPTable::ORDER]['dir'];
        }


        $statsDB->query($querySql);

        $colTypes = $statsDB->getColumnTypes();
        foreach ( $this->columns as &$column ) {
            $column['type'] = $colTypes[$column['key']];
        }

        $this->totalRows = $statsDB->getNumRows();

        $this->dataSource = array();
        if ( $this->prefetch ) {
            $this->dataSource['data'] = $this->getData($statsDB, $colTypes);
        } else {
            $filename = tempnam($web_temp_dir, "");
            file_put_contents($filename, $baseQuery);
            $this->dataSource['query'] =  array(
                'totalRows' => $this->totalRows,
                'id' => basename($filename),
                'url' => $php_webroot . "/query.php"
            );
        }
    }

    function getDDPTable() {
        return new DDPTable($this->name, $this->columns, $this->dataSource, $this->options);
    }

    function getTable() {
        return $this->getDDPTable()->getTable();
    }

    function getTableWithHeader($headerText, $headerLevel = 2, $helpTextOrID = '', $subText = '', $label = '') {
        $ddpTable = $this->getDDPTable();
        return $ddpTable->getTableWithHeader( $headerText, $headerLevel, $helpTextOrID, $subText, $label);
    }

    function hasRows() {
        return $this->totalRows > 0;
    }

    private function getSql($sqlExtras) {
        $colAs = array();
        foreach ($this->columns as $column) {
            if ( array_key_exists('db', $column) ) {
                $colAs[] = $column['db'] . " AS " . $column['key'];
            } else {
                $colAs[] = $column['key'] . " AS " . $column['key'];
            }
        }

        $querySql = "SELECT " . implode(",", $colAs) . " FROM " . implode(",", $this->tables);

        // JOIN
        if ( (! is_null($sqlExtras)) && array_key_exists(self::JOIN, $sqlExtras) ) {
            $querySql .= " " . implode(" ", $sqlExtras[self::JOIN]);
        }

        // WHERE
        if ( ! is_null($this->where) ) {
            $querySql .= " WHERE " . $this->where;
        }

        // GROUP BY
        if ( (! is_null($sqlExtras)) && array_key_exists(self::GROUP_BY, $sqlExtras) ) {
            $querySql .= " " . self::GROUP_BY . " " . implode(",", $sqlExtras[self::GROUP_BY]);
        }

        return $querySql;
    }

    private function getData($statsDB, $colTypes) {
        $result = array();
        while ( $row = $statsDB->getNextNamedRow() ) {
            /* Need to fix up numeric data types as the driver returns them as strings */
            $resultRow = array();
            foreach ( $row as $name => $value ) {
                # In the case where we get a NULL back, then the column doesn't have
                # value so we don't add it to the row
                if ( ! is_null($value) ) {
                    if ( $colTypes[$name] == 'int' || $colTypes[$name] == 'float' || $colTypes[$name] == 'real' ) {
                        $resultRow[$name] = $value + 0;
                    } else {
                        $resultRow[$name] = $value;
                    }
                }
            }
            $result[] = $resultRow;
        }
        return $result;
    }
}

?>
