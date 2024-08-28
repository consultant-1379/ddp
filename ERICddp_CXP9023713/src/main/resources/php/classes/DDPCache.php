<?php

require_once PHP_ROOT . "/StatsDB.php";

class DDPCache {
    var $cacheDB;
    var $siteId;
    var $site;
    var $date;

    function __construct( $site, $date ) {
        $this->site = $site;
        $this->date = $date;

        $this->cacheDB = new StatsDB( StatsDB::ACCESS_READ_WRITE );
        $row = $this->cacheDB->queryRow("SELECT id FROM sites WHERE name = '$site'");
        $this->siteid = $row[0];

        // Switch to ddpadmin
        $this->cacheDB->exec("use ddpadmin");
    }

    function get( $componentName ) {
        $row = $this->cacheDB->queryRow("SELECT data FROM ddp_cache WHERE date = '" . $this->date .
                                        "' AND siteid = $this->siteid AND component = '$componentName'");
        if ( $row ) {
            return $row[0];
        } else {
            return NULL;
        }
    }

    function set( $componentName, $value ) {
	// Store a given component under DDP cache only if its size is <= 1 MB
	if ( strlen($value) <= 1048576 ) {
            $sql = sprintf("INSERT INTO ddp_cache (date,siteid,component,data) VALUES ('%s', %d, '%s', '%s')",
                           $this->date, $this->siteid, $componentName, $this->cacheDB->escape($value));
            $this->cacheDB->exec($sql);
        } else {
            error_log("Failed to cache the '{$componentName}' data for '{$this->site}' for the date " .
                      "'{$this->date}' as its size exceeded the 1 MB limit");
            return;
        }
    }

    function clear( $componentNames ) {
        $componentQuery = "";
        foreach ($componentNames as $componentName) {
            $componentQuery .= "component = '$componentName' OR ";
        }
        $componentQuery = preg_replace('/(\s+OR)*\s*$/', '', $componentQuery);

        $this->cacheDB->exec("DELETE FROM ddp_cache WHERE date = '" . $this->date .
                             "' AND siteid = $this->siteid AND (${componentQuery})");
    }
}

?>
