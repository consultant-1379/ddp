<?php

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

class Routes {
    var $site;
    var $date;
    var $height;
    var $width;
    var $servIdList;

    const EXCHANGES_COMPLETED = "ExchangesCompleted";
    const EXCHANGES_FAILED = "ExchangesFailed";

    const COLUMN = "column";

    function __construct($statsDB,$site,$date,$servIdList,$width=640,$height=240) {
        $this->statsDB = new StatsDB();
        $this->site = $site;
        $this->date = $date;
        $this->servIdList = $servIdList;
        $this->height = $height;
        $this->width = $width;

        $this->hasSummary = $this->statsDB->hasData("sum_enm_route_instr", "date", true);
    }

    public function getGraphs() {
        $results = array();

        $servIdStr = implode(",", $this->servIdList);
        if ( $this->hasSummary ) {
            $this->statsDB->query("
SELECT DISTINCT enm_route_names.id AS id, enm_route_names.name AS name
FROM sum_enm_route_instr
JOIN enm_route_names ON sum_enm_route_instr.routeid = enm_route_names.id
JOIN sites ON sum_enm_route_instr.siteid = sites.id
WHERE
 sites.name = '$this->site' AND
 sum_enm_route_instr.serverid IN ( $servIdStr ) AND
 sum_enm_route_instr.date = '$this->date'");
        } else {
            $this->statsDB->query("
SELECT DISTINCT
 enm_route_names.id AS id,
 enm_route_names.name AS name
FROM enm_route_names, enm_route_instr, sites
WHERE
 enm_route_instr.routeid = enm_route_names.id AND
 enm_route_instr.siteid = sites.id AND sites.name = '$this->site' AND
 enm_route_instr.serverid IN ( $servIdStr ) AND
 enm_route_instr.time BETWEEN '$this->date 00:00:00' AND '$this->date 23:59:59'");
        }
        while ( $row = $this->statsDB->getNextRow() ) {
            $results[$row[1]] = $this->getGraphsForRouteId($row[0]);
        }
        return $results;
    }

    public function getGraphsForRoute($routeName) {
        $row = $this->statsDB->queryRow("SELECT id FROM enm_route_names WHERE name = '$routeName'");
        return $this->getGraphsForRouteId($row[0]);
    }

    public function getGraphsForRouteId($routeId) {
        $graphArray = array();

        $where = "
enm_route_instr.siteid = sites.id AND sites.name = '%s' AND
enm_route_instr.routeid = %d AND
enm_route_instr.serverid = servers.id";

        if ( ! is_null($this->servIdList) ) {
            $servIdStr = implode(",", $this->servIdList);
            $where .= " AND enm_route_instr.serverid IN ( $servIdStr )";
        }

        $avgProcTime = "IF(ExchangesCompleted>0,TotalProcessingTime/ExchangesCompleted,0)";
        $graphParams =
            array(
                self::EXCHANGES_COMPLETED => array( self::COLUMN => self::EXCHANGES_COMPLETED, "type" => "sb" ),
                "Average Processing Time" => array( self::COLUMN => $avgProcTime, "type" => "tsc" ),
                self::EXCHANGES_FAILED => array( self::COLUMN => self::EXCHANGES_FAILED, "type" => "sb" )
            );
        $sqlParamWriter = new SqlPlotParam();

        foreach ( $graphParams as $title => $param ) {
            $sqlParam =
                array(
                    "title" => $title,
                    "type" => $param["type"],
                    "ylabel" => "",
                    "useragg" => "true",
                    "persistent" => "false",
                    "querylist" => array(
                        array(
                            "timecol" => "time",
                            "multiseries"=> "servers.hostname",
                            "whatcol" => array( $param[self::COLUMN] => $title ),
                            "tables" => "enm_route_instr, sites, servers",
                            "where" => $where,
                            "qargs" => array( "site", "routeid" )
                        )
                    )
                );
            if ( $param["type"] == "sb" ) {
                $sqlParam["sb.barwidth"] = 60;
            }
            $id = $sqlParamWriter->saveParams($sqlParam);
            $graphArray[] = $sqlParamWriter->getImgURL(
                $id,
                "$this->date 00:00:00",
                "$this->date 23:59:59",
                true,
                480,
                240,
                "routeid=$routeId"
            );
        }

        return $graphArray;
    }

    public function getTable($callbackURL) {
        $avgProcTime = "ROUND(IF(SUM(ExchangesCompleted>0),SUM(TotalProcessingTime)/SUM(ExchangesCompleted),0),1)";
        if ( $this->hasSummary ) {
            $instrTable = 'sum_enm_route_instr';
            $where = <<<EOT
sum_enm_route_instr.siteid = sites.id AND sites.name = '$this->site' AND
sum_enm_route_instr.date = '$this->date' AND
sum_enm_route_instr.routeid = enm_route_names.id
GROUP BY enm_route_names.id
EOT;
        } else {
            $instrTable = 'enm_route_instr';
            $where = <<<EOT
enm_route_instr.siteid = sites.id AND sites.name = '$this->site' AND
enm_route_instr.time BETWEEN '$this->date 00:00:00' AND '$this->date 23:59:59' AND
enm_route_instr.routeid = enm_route_names.id
GROUP BY enm_route_names.id
EOT;
        }
        if ( ! is_null($this->servIdList) ) {
            $servIdStr = implode(",", $this->servIdList);
            $where = $instrTable . ".serverid IN ( $servIdStr ) AND " . $where;
        }
        $builder = SqlTableBuilder::init()
            ->name("routes")
            ->tables( array($instrTable, "sites", "enm_route_names") )
            ->where($where)
            ->addHiddenColumn("id", "routeid")
            ->addSimpleColumn("enm_route_names.name", "Route")
            ->addColumn(self::EXCHANGES_COMPLETED, "SUM(ExchangesCompleted)", self::EXCHANGES_COMPLETED)
            ->addSimpleColumn($avgProcTime, "Average Processing Time")
            ->addSimpleColumn("SUM(ExchangesFailed)", self::EXCHANGES_FAILED)
            ->sortBy(self::EXCHANGES_COMPLETED, DDPTable::SORT_DESC)
            ->paginate();

        if ( ! is_null($callbackURL) ) {
            $builder->ctxMenu(
                "action",
                true,
                array("plotRouteGraphs" => "Plot"),
                $callbackURL,
                "id"
            );
        }

        return $builder->build();
    }
}

?>

