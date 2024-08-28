<?php

class HelpBubbles {
    private $baseName;

    // Hold the class instance.
    private static $instance = null;

    private function __construct($filter = null) {
        if ( is_null($filter) ) {
            $pageName =  pathinfo(fromServer(PHP_SELF), PATHINFO_FILENAME);
            $this->baseName = 'DDP_Bubble.' . $pageName;
        } else {
            $this->baseName = 'DDP_Bubble.' . $filter;
        }
    }

    // The object is created from within the class itself
    // only if the class has no instance.
    public static function getInstance($filter = null) {
        if (self::$instance == null) {
            self::$instance = new HelpBubbles($filter);
        }

        return self::$instance;
    }


    public function getHelp($helpId) {
        $id = $this->baseName . "." . $helpId;

        $statsDB = new StatsDB();
        $sqlquery = <<<ESQL
SELECT ddpadmin.help_bubble_texts.content
FROM ddpadmin.help_bubble_texts
WHERE
 ddpadmin.help_bubble_texts.help_id = '$id'
ESQL;
        $row = $statsDB->queryRow($sqlquery);
        if ( $row != false) { // NOSONAR
            return $row[0];
        } else {
            return null;
        }
    }
}

