<?php

require_once 'StatsDB.php';

const OSSPARAM = '&oss=';
/**
 * A Builder class for SqlPlotParam. To use
 * $params = SqlPlotParamBuilder::init()
 *     ->title('MyTitle')
 *     ->type(SqlPlotParam::TIME_SERIES_COLLECTION)
 *     ->yLabel('Count')
 *     ->addQuery( SqlPlotParam::DEFAULT_TIME_COL,
 *                 array( 'mycol' => 'My Column' ),
 *                 "x = 1 ANY y = 2",
 *                 ( 'site' = $site ) )
 *     ->build();
 */
class SqlPlotParamBuilder {
    var $params = array(
        SqlPlotParam::PERSISTENT => SqlPlotParam::FALSE_VALUE,
        SqlPlotParam::USER_AGG => SqlPlotParam::TRUE_VALUE
    );

    public static function init() {
        return new self;
    }

    public function title($title) {
        $this->params[SqlPlotParam::TITLE] = $title;
        return $this;
    }

    /**
     * Provides an alternative title that will be used when showing the
     * Javascript version of the graph. Main usage is where the graph
     * is used in a grid of images where you want short titles but
     * when you click through to the Javascript graph you want to add
     * something more to the title. e.g. the server name
     */
    public function longTitle($title) {
        $this->params[SqlPlotParam::LONG_TITLE] = $title;
        return $this;
    }

    public function titleArgs($targs) {
        is_array($targs) or die('targs must be an array');
        $this->params[SqlPlotParam::T_ARGS] = $targs;
        return $this;
    }

    public function type($type) {
        $this->params[SqlPlotParam::TYPE] = $type;
        return $this;
    }

    public function yLabel($yLabel) {
        $this->params[SqlPlotParam::Y_LABEL] = $yLabel;
        return $this;
    }

    public function disableUserAgg() {
        $this->params[SqlPlotParam::USER_AGG] = SqlPlotParam::FALSE_VALUE;
        return $this;
    }

    public function presetAgg($aggType,$aggInterval) {
        $this->params[SqlPlotParam::PRESET_AGG] = $aggType . ":" . $aggInterval;
        return $this;
    }

    public function makePersistent() {
        $this->params[SqlPlotParam::PERSISTENT] = SqlPlotParam::TRUE_VALUE;
        return $this;
    }

    public function forceLegend() {
        $this->params[SqlPlotParam::FORCE_LEGEND] = SqlPlotParam::TRUE_VALUE;
        return $this;
    }

    public function barWidth($width) {
        $this->params[SqlPlotParam::SB_BARWIDTH] = $width;
        return $this;
    }

    public function seriesFromFile($seriesFile) {
        $this->params[SqlPlotParam::SERIES_FILE] = $seriesFile;
        return $this;
    }

    /*
     * Add query which returns the series data
     *
     * timeCol: name of the time column, normally SqlPlotParam::DEFAULT_TIME_COL
     * columns: array of columns, key the db column name/expression, value is the label for the series
     * tables: array of table names uses in the query
     * qargs: array of the query parameters replaced in the where clause where
     *        the values are taken from the qplot URL. Can be NULL
     * multiSeries: name of the multiSeries column, NULL for non-multiSeries graphs
     * catColumn: name of the category column, NULL for non-category graphs
     * timeWhere: name of the column used for time in the where column, defaults to $timeCol
     */
    public function addQuery( // NOSONAR
        $timeCol,
        $columns,
        $tables,
        $where,
        $qargs,
        $multiSeries = null,
        $catColumn = null,
        $timeWhere = null
    ) {
        is_string($timeCol) or die('timeCol must be a string');
        is_array($columns) or die('columns must be an array');
        is_array($tables) or die('tables must be an array');
        is_string($where) or die('where must be a string');
        is_array($qargs) or die('qargs must be an array');
        if ( ! is_null($multiSeries) ) {
            is_string($multiSeries) or die('multiSeries must be a string');
        }
        if ( ! is_null($catColumn) ) {
            is_string($catColumn) or die('catColumn must be a string');
        }

        $queryDef = array(
            SqlPlotParam::TIME_COL => $timeCol,
            SqlPlotParam::WHAT_COL => $columns,
            SqlPlotParam::TABLES => implode(",", $tables),
            SqlPlotParam::WHERE => $where,
            SqlPlotParam::Q_ARGS => $qargs
        );
        if ( ! is_null($multiSeries) ) {
            $queryDef[SqlPlotParam::MULTI_SERIES] = $multiSeries;
        }
        if ( ! is_null($catColumn) ) {
            $queryDef[SqlPlotParam::CAT_COLUMN] = $catColumn;
        }
        if ( ! is_null($timeWhere) ) {
            $queryDef[SqlPlotParam::TIME_WHERE] = $timeWhere;
        }

        $this->params[SqlPlotParam::QUERY_LIST][] = $queryDef;
        return $this;
    }

    public function build() {
        return $this->params;
    }
}

class SqlPlotParam
{
    var $monthlyArgs = "";

    // Graph types
    const TIME_SERIES_COLLECTION = 'tsc';
    const STACKED_BAR = 'sb';
    const STACKED_AREA = 'sa';
    const CATEGORY = 'cat';
    const XY = 'xy';

    // Normal name used for time colmnns
    const DEFAULT_TIME_COL = 'time';

    // Frequentely used parameter in graphs
    const SERVERS_HOSTNAME = 'servers.hostname';
    const COUNT_LABEL = 'Count';

    // Parameter names
    const TITLE = 'title';
    const LONG_TITLE = 'longtitle';
    const TYPE = 'type';
    const Y_LABEL = 'ylabel';
    const USER_AGG = 'useragg';
    const PRESET_AGG = 'presetagg';
    const PERSISTENT = 'persistent';
    const FORCE_LEGEND = 'forcelegend';
    const SB_BARWIDTH = 'sb.barwidth';
    const QUERY_LIST = 'querylist';
    const TIME_COL = 'timecol';
    const WHAT_COL = 'whatcol';
    const TABLES = 'tables';
    const WHERE = 'where';
    const Q_ARGS = 'qargs';
    const T_ARGS = 'targs';
    const MULTI_SERIES = 'multiseries';
    const SERIES_FILE = 'seriesfile';
    const CAT_COLUMN = 'cat.column';
    const TIME_WHERE = 'time.where';

    const TRUE_VALUE = 'true';
    const FALSE_VALUE = 'false';

    const AGG_SUM = 'SUM';
    const AGG_AVG = 'AVG';

    const AGG_MINUTE = 'Per Minute';
    const AGG_HOURLY = 'Hourly';

    function __construct($year = "", $month = "") {
        if ($year != "" && $month != "") {
            $this->monthlyArgs = "&year={$year}&month={$month}";
        }
    }

    function getImgURL( $id, $tstart, $tend, $addPlotLnk = true, $width = 640, $height = 480, $extraArgs = null ) {
        global $debug;

        $oss = getArgs('oss');
        $queryArgs = 'site=' . $_GET['site'] .
        OSSPARAM . $oss .
        '&tstart=' . urlencode($tstart) .
        '&tend=' . urlencode($tend) . "&id=$id";
        if(isset($_GET['server'])){
            $queryArgs .= "&server=" . $_GET['server'];
        }
        if ( $extraArgs ) {
            if ( $debug ) { echo "<pre>adding extraArgs=\"$extraArgs\"</pre>\n"; }
            $queryArgs .= "&" . $extraArgs;
        }

        $plotServerletLnk = "/plotsrv/?" .
                          $queryArgs . "&width=$width&height=$height&url="
                          . urlencode(PHP_WEBROOT . "/qplot.php");

        if ( $addPlotLnk ) {
            $action='jsplot';
            $qplotLnk = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER["HTTP_HOST"] . PHP_WEBROOT . "/qplot.php?" .
                $queryArgs . $this->monthlyArgs . "&action=" . $action;
            return '<a href="' . $qplotLnk . '"><img src="' . $plotServerletLnk . '" alt=""></a>';
        } else {
            return '<img src="' . $plotServerletLnk . '" alt="">';
        }
    }

#Resizes SON graphs once they're on a smaller screen

    function getImgURLSON( $id, $tstart, $tend, $addPlotLnk = true, $width = 640, $height = 480, $extraArgs = null ) {
        global $debug;

        $oss = getArgs('oss');
        $queryArgs = 'site=' . $_GET['site'] .
        OSSPARAM . $oss .
        '&tstart=' . urlencode($tstart) .
        '&tend=' . urlencode($tend) . "&id=$id";
        if(isset($_GET['server'])){
            $queryArgs .= "&server=" . $_GET['server'];
        }
        if ( $extraArgs ) {
            if ( $debug ) { echo "<pre>adding extraArgs=\"$extraArgs\"</pre>\n"; }
            $queryArgs .= "&" . $extraArgs;
        }

        $plotServerletLnk = "/plotsrv/?" .
                          $queryArgs . "&width=$width&height=$height&url="
                          . urlencode(PHP_WEBROOT . "/qplot.php");

        if ( $addPlotLnk ) {
            $qplotLnk = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER["HTTP_HOST"] . PHP_WEBROOT . "/qplot.php?" .
                $queryArgs . $this->monthlyArgs . '&action=jsplot';
            return '<a href="' . $qplotLnk . '"><img src="' . $plotServerletLnk . '" alt="" style="width:100%; height:100%; max-width: 1600px; max-height: 800px;"></a>';
        } else {
            return '<img src="' . $plotServerletLnk . '" alt="">';
        }
    }

    function getURL( $id, $tstart, $tend, $extraArgs = null )
    {
        $action='jsplot';

        $oss = getArgs('oss');
        $qplotLnk = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER["HTTP_HOST"] .
            PHP_WEBROOT . "/qplot.php?" .
            'site=' . $_GET['site'] .
            OSSPARAM . $oss .
            '&tstart=' . urlencode($tstart) .
            '&tend=' . urlencode($tend) . $this->monthlyArgs . "&id=$id&action=$action";
        if ( $extraArgs ) {
            $qplotLnk .= "&" . $extraArgs;
        }

        return $qplotLnk;
    }

    function saveParams( $params ) {
        global $debug, $web_temp_dir;
        if ( array_key_exists( self::PERSISTENT, $params ) && $params[self::PERSISTENT] == SqlPlotParam::TRUE_VALUE ) {
            $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
            $paramStr = $statsDB->escape(json_encode($params));
            $statsDB->query("SELECT id FROM sql_plot_param WHERE param = '$paramStr'");
            if ( $statsDB->getNumRows() == 0 ){
                $statsDB->exec("INSERT INTO sql_plot_param (param) VALUES ( '$paramStr' )");
                $id = $statsDB->lastInsertId();
            }
            else {
                $row = $statsDB->getNextRow();
                $id = $row[0];
            }
        } else {
            $filename = tempnam($web_temp_dir, "");
            $id = basename($filename);
            file_put_contents( $filename, json_encode($params) );
        }

        if ( $debug ) { echo "<pre>SqlPlotParam.saveParams id=$id</pre>\n"; }
        return $id;
    }

    function save( $params, $queries ) {
        global $debug;

        $params[self::QUERY_LIST] = $queries;
        return self::saveParams( $params );
    }

    function load($id)
    {
        global $debug, $web_temp_dir;

        $params = NULL;

        // Try and get the query from the DB
        if ( is_numeric($id) ) {
            $statsDB = new StatsDB();
            $statsDB->query("SELECT param FROM sql_plot_param WHERE id = '$id'");
            if ( $statsDB->getNumRows() == 1 ) {
                $row = $statsDB->getNextRow();
                $params =  json_decode( $row[0], true );
            }
        }

        // Not found in DB, so check if this is an old query (using temp file)
        if ( ! isset($params) ) {
            $filename = $web_temp_dir . "/" . $id;
            if ( $debug ) { echo "<pre>SqlPlotParam.load id=$id filename=$filename</pre>\n"; }
            if ( file_exists($filename) ) {
                $params =  json_decode( file_get_contents($filename), true );
                if ( $debug ) { echo "<pre>SqlPlotParam param\n"; print_r($params); echo "</pre>\n"; }
                return $params;
            } else {
                return NULL;
            }
        }

        if ( $debug ) { echo "<pre>SqlPlotParam param\n"; print_r($params); echo "</pre>\n";}
        return $params;
    }
}

?>
