<?php

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

class Caches {
    private $site;
    private $date;
    private $height;
    private $width;
    private $servIdList;

    const COLUMN = "column";
    const STORES = "stores";

    public function __construct($statsDB, $site, $date, $servIdList, $width=640, $height=240) {
        $this->statsDB = $statsDB;
        $this->site = $site;
        $this->date = $date;
        $this->servIdList = $servIdList;
        $this->height = $height;
        $this->width = $width;
    }

    public function getGraphs() {
        $results = array();

        $servIdStr = implode(",", $this->servIdList);
        $this->statsDB->query("
SELECT DISTINCT
 enm_cache_names.id AS id,
 enm_cache_names.name AS name
FROM enm_cache_names, enm_cache_instr, sites
WHERE
 enm_cache_instr.cacheid = enm_cache_names.id AND
 enm_cache_instr.siteid = sites.id AND sites.name = '$this->site' AND
 enm_cache_instr.serverid IN ( $servIdStr ) AND
 enm_cache_instr.time BETWEEN '$this->date 00:00:00' AND '$this->date 23:59:59'");
        while ( $row = $this->statsDB->getNextRow() ) {
            $results[$row[1]] = $this->getGraphsForCacheId($row[0]);
        }
        return $results;
    }

    public function getGraphsForCache($cacheName) {
        $row = $this->statsDB->queryRow("SELECT id FROM enm_cache_names WHERE name = '$cacheName'");
        return $this->getGraphsForCacheId($row[0]);
    }

    public function getGraphsForCacheId($cacheId) {
        $graphArray = array();

        $where = "
enm_cache_instr.siteid = sites.id AND sites.name = '%s' AND
enm_cache_instr.cacheid = %d AND
enm_cache_instr.serverid = servers.id";

        if ( ! is_null($this->servIdList) ) {
            $servIdStr = implode(",", $this->servIdList);
            $where .= " AND enm_cache_instr.serverid IN ( $servIdStr )";
        }

        $graphParams =
            array(
                STORES => array( self::COLUMN => STORES, "type" => "sb" ),
                "removeHits" => array( self::COLUMN => "removeHits", "type" => "sb" ),
                "numberOfEntries" => array( self::COLUMN => "numberOfEntries", "type" => "tsc" )
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
                            "tables" => "enm_cache_instr, sites, servers",
                            "where" => $where,
                            "qargs" => array( "site", "cacheid" )
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
                "cacheid=$cacheId"
            );
        }

        return $graphArray;
    }

    public function getTable($callbackURL) {
        $where = "
enm_cache_instr.siteid = sites.id AND sites.name = '$this->site' AND
enm_cache_instr.time BETWEEN '$this->date 00:00:00' AND '$this->date 23:59:59' AND
enm_cache_instr.cacheid = enm_cache_names.id
GROUP BY enm_cache_names.id";
        if ( ! is_null($this->servIdList) ) {
            $servIdStr = implode(",", $this->servIdList);
            $where = "enm_cache_instr.serverid IN ( $servIdStr ) AND " . $where;
        }
        $builder = SqlTableBuilder::init()
            ->name("caches")
            ->tables( array("enm_cache_instr", "sites", "enm_cache_names") )
            ->where($where)
            ->addHiddenColumn("id", "enm_cache_instr.cacheid")
            ->addSimpleColumn("enm_cache_names.name", "Cache")
            ->addColumn(STORES, "SUM(enm_cache_instr.stores)", "Stores")
            ->addSimpleColumn("SUM(removeHits)", "Remove Hits")
            ->addSimpleColumn("MAX(numberOfEntries)", "Number of Entries")
            ->sortBy(STORES, DDPTable::SORT_DESC)
            ->paginate();

        if ( ! is_null($callbackURL) ) {
            $builder->ctxMenu(
                "action",
                true,
                array("plot" => "Plot"),
                $callbackURL,
                "id"
            );
        }

        return $builder->build();
    }
}

