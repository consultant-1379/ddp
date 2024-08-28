<?php

$AGG_TYPE = array(
    0 => 'None',
    1 => 'AVG',
    2 => 'MIN',
    3 => 'MAX',
    4 => 'SUM',
    5 => 'COUNT'
);

$AGG_INTERVAL = array(
    1 => 'Per Minute',
    2 => 'Hourly',
    3 => 'Daily',
    4 => 'Monthly',
    5 => 'NA'
);

$DATE_FORMATS = array(
    0 => "%Y-%m-%d:%H:%i:%s",
    1 => "%Y-%m-%d:%H:%i:00",
    2 => "%Y-%m-%d:%H:00:00",
    3 => "%Y-%m-%d:00:00:00",
    4 => "%Y-%m-01:00:00:00",
);

$UNIX_TIME_FORMAT = "UNIX";
$APPLET_TIME_FORMAT = "APPLET";

class QPlotQueryBuilder {
    function getQuery($tstart,$tend,$aggType,$aggInterval,$aggCol,$qp,$timeFormat,$type) {
        global $debug;
        global $DATE_FORMATS;
        global $AGG_TYPE;
        global $UNIX_TIME_FORMAT;
        global $APPLET_TIME_FORMAT;

        $sqlTempl = "SELECT %s %s %s FROM %s WHERE %s BETWEEN '%s' AND '%s' %s %s %s";

        if ( $debug ) { echo "<pre>getQuery: type=$type qp\n"; print_r($qp); echo "</pre>\n";}

        $orderByCols = array( 'xaxis' );

        if ( $type == 'cat' ) {
            $xAxisStr =  $qp['cat.column'] . " AS xaxis, ";
        } else {
            /* Time formatting */
            /* Force stacked series to align by not including seconds in the time stamp */
            $dateFormatIndex = $aggInterval;
            if ( ($type == 'sa' || $type == 'sb') && $aggInterval == 0 ) {
                $dateFormatIndex = 1;
            }
            $xAxisStr = sprintf("DATE_FORMAT(%s,'%s')", $qp['timecol'], $DATE_FORMATS[$dateFormatIndex]);
            if ( $timeFormat == $UNIX_TIME_FORMAT ) {
                $xAxisStr = sprintf("UNIX_TIMESTAMP(%s)", $xAxisStr);
            }
            $xAxisStr = $xAxisStr . " AS xaxis,";
            if ( $debug ) { echo "<pre>getQuery: timeFormat=$timeFormat aggType=$AGG_TYPE[$aggType] aggCol=$aggCol</pre>\n"; }
        }
        if ( $debug ) { echo "<pre>getQuery:  xAxisStr=$xAxisStr</pre>\n"; }
        
        /* Multi-series */
        $seriesIdStr = "";
        if ( array_key_exists( "multiseries", $qp ) ) {
            $seriesIdStr =  $qp["multiseries"] . " AS seriesid, ";
            $orderByCols[] = 'seriesid';
        }

        /* WHERE condition */
        $whereStr = $qp['where'];
        if ( array_key_exists( 'qargs', $qp ) ) {
            if ( $debug > 0 ) { echo "<pre>whereStr where template=$whereStr, _GET\n"; print_r($_GET); echo "</pre>\n"; }
            $qvals = array();
            foreach ( $qp['qargs'] as $qarg ) {
                if ( isset($_GET[$qarg] ) ) {
                    $qvals[] = $_GET[$qarg];
                } else {
                    echo "<pre>ERROR: Invalid URL, no value found for \"$qarg\"</pre>";
                    exit;
                }
            }
            $whereStr = vsprintf($whereStr, $qvals);
            if ( $debug > 0 ) { echo "<pre>whereStr = $whereStr, qvals = \n"; print_r($qvals); echo "</pre>\n"; }
        }
        if ( $whereStr != "" ) {
            $whereStr = "AND $whereStr";
        }

        /* What columns */
        $whatColStr = "";
        $groupByStr = "";
        if ( $AGG_TYPE[$aggType] == "None" ) {
            $aggInterval = 0;
            foreach ( $qp['whatcol'] as $colName => $displayName ) {
                if ( $whatColStr != "" ) {
                    $whatColStr = $whatColStr . ", ";
                }
                $whatColStr = $whatColStr . "$colName as '$displayName'";
            }
        } else {
            foreach ( $qp['whatcol'] as $colName => $displayName ) {
                if ( $whatColStr != "" ) {
                    $whatColStr = $whatColStr . ", ";
                }
                if ( $debug ) { echo "<pre>strpos " . strpos($colName,'AGG_FUNC') . "</pre>\n"; }
                if ( strpos($colName,'AGG_FUNC') === FALSE ) {
                    $whatColStr = $whatColStr . $AGG_TYPE[$aggType] . "($colName) as '$displayName'";
                } else {
                    if ( $debug ) { echo "<pre>Replacing AGG_FUNC with $AGG_TYPE[$aggType] in $colName</pre>\n"; }
                    $whatColStr = $whatColStr . str_replace('AGG_FUNC', $AGG_TYPE[$aggType], $colName) . " AS '$displayName'";
                }
            }

            $groupByStr = "";
            $aggColumns = array();
            if ( $aggCol != "" ) {
                $aggColumns[] = $aggCol;
            }
            if ( array_key_exists( "multiseries", $qp ) ) {
                $aggColumns[] = "seriesid";
            }
            
            if ( array_key_exists( "cat.column", $qp ) ) {
                $aggColumns[] = "xaxis";
            }
            
            if ( count($aggColumns) > 0 ) {
                $groupByStr = "GROUP BY " . implode(",",$aggColumns);
            }
        }

        $orderByStr = "ORDER BY " . implode(",", $orderByCols);

        $timeWhereCol = $qp[SqlPlotParam::TIME_COL];
        if ( array_key_exists(SqlPlotParam::TIME_WHERE, $qp) ) {
            $timeWhereCol = $qp[SqlPlotParam::TIME_WHERE];
        }
        /* Make the sql statement */
        $sql = sprintf($sqlTempl,
                       $xAxisStr, $seriesIdStr, $whatColStr, $qp['tables'],
                       $timeWhereCol, $tstart, $tend,
                       $whereStr,
                       $groupByStr,
                       $orderByStr);
        return $sql;
    }

    function autoAggQuery($tstart,$tend,$qp) {
        global $DATE_FORMATS;

        $tstartnum = strtotime($tstart);
        $tendnum = strtotime($tend);
        $delta = strtotime($tend) - strtotime($tstart);

        if ( $delta < (60 * 60 * 24 * 2) ) {
            $dateForm = $DATE_FORMATS[0];
        } else if ( $delta < (60 * 60 * 24 * 8) ) {
            $dateForm = $DATE_FORMATS[1];
        } else {
            $dateForm = $DATE_FORMATS[2];
        }
        $sql = sprintf("SELECT DATE_FORMAT(%s,'%s') AS ts, %s FROM %s WHERE %s BETWEEN '%s' AND '%s' %s GROUP BY ts",
                       $qp['timecol'], $dateForm, $qp['what'], $qp['tables'], $qp['timecol'], $tstart, $tend, $qp['where']);

    }

}

?>
