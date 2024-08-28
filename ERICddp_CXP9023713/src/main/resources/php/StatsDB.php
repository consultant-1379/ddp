<?php

// This works on Linux, not on Solaris
$included_files = get_included_files();
foreach ($included_files as $filename) {
    // already included
    if (realpath($filename) == realpath(__FILE__)) return;
}

class StatsDB
{
    private $queryResult;
    private $mysqli;

    private static $instances = array();

    const STRING = 'string';
    const INT = 'int';
    const FLOAT = 'float';

    private static $typenames = array(
        1=>self::INT,
        2=>self::INT,
        3=>self::INT,
        4=>self::FLOAT,
        5=>self::FLOAT,
        7=>'timestamp',
        8=>self::INT,
        9=>self::INT,
        10=>'date',
        11=>'time',
        12=>'datetime',
        13=>'year',
        16=>'bit',
        252=>self::STRING,
        253=>self::STRING,
        254=>self::STRING,
        246=>self::FLOAT
    );

    const ACCESS_READ_ONLY   = 1;
    const ACCESS_READ_WRITE  = 2;
    const ACCESS_REPLICATION = 3;

    // consts for common tables
    const SITES = 'sites';
    const SERVERS = 'servers';

    // const for common DB expressions
    const ROW_COUNT = 'COUNT(*)';

    public function __construct($accessType = self::ACCESS_READ_ONLY, $host = null, $port = null, $cert = null) {
        global $debug, $DBhost, $DBName, $dbUseTls;
        global $DBuser, $DBpass, $DBwuser, $DBwpass, $ReplHost, $ReplUser, $ReplPass;

        $db = $DBName;
        if ( $accessType == self::ACCESS_READ_ONLY ) {
            if ( is_null($host) ) {
                $host = $DBhost;
            }
            $userid = $DBuser;
            $passwd = $DBpass;
        } else if ( $accessType == self::ACCESS_READ_WRITE ) {
            $host = $DBhost;
            $userid = $DBwuser;
            $passwd = $DBwpass;
        } else {
            // self::ACCESS_REPLICATION
            $userid = $ReplUser;
            $passwd = $ReplPass;
        }

        debugMsg("StatsDB.__construct: DBName=$DBName host=$host userid=$userid");

        if ( is_null($port) ) {
            $port = ini_get("mysqli.default_port");
        }

        // Only connect once, even if we use multiple StatsDB objects
        $instanceKey = $accessType . ":" . $host;
        // Looks like there's a SonarQube bug here, its incorrectly complaining about
        // references to static variables via self
        if ( array_key_exists($instanceKey, self::$instances)) { // NOSONAR
            $this->mysqli = self::$instances[$instanceKey];      // NOSONAR
        } else {
            $connectFlags = null;
            // We have to create this here so disable NOSONAR
            $this->mysqli = mysqli_init();
            if (!$this->mysqli) {
              die("mysqli_init failed");
            }
            $connectFlags = $this->initTLS($accessType, $userid, $connectFlags, $cert);
            if ( !$this->mysqli->real_connect($host, $userid, $passwd, $db, $port, null, $connectFlags) ) {
                die("Unable to connect to MySQL. " . $this->mysqli->connect_error);
            }
            self::$instances[$instanceKey] = $this->mysqli;      // NOSONAR
        }
    }

    function disconnect()
    {
        // Removing this, see comments on http://ie.php.net/manual/en/function.mysql-close.php
        // as well as the description:
        // "Using mysqli_close() isn't usually necessary, as non-persistent
        // open links are automatically closed at the end of the script's execution."
        // We may have multiple connections open, calling this multiple times results in
        // errors in the log.
    }

    // Use this for DML statements CREATE, INSERT, UPDATE, DELETE
    // returns the number of rows affected
    function exec($sql, $dieOnFailure = true) {
        global $debug;
        if ($debug) { echo "<p>StatsDB.exec: sql=$sql</p>\n"; }
        $this->result = $this->mysqli->query($sql);
        if( $this->result ) {
            return $this->mysqli->affected_rows;
        } else {
            $this->error = "Query Failed. $sql " . $this->mysqli->error;
            if ($debug) {
                echo "<pre>StatsDB.exec $this->error</pre>\n";
            }
            if ( $dieOnFailure ) {
                die("Query Failed. $sql " . $this->mysqli->error);
            }
            return -1;
        }
    }

    public function query($sql = "", $dieOnFailure = true) {
        debugMsg("StatsDB.query: sql=$sql");
        $this->result = $this->mysqli->query($sql);
        if(! $this->result ) {
            if ($dieOnFailure) {
                die("Query Failed. $sql " . $this->mysqli->error);
            }
            $this->error = "Query Failed. $sql " . $this->mysqli->error;
            return false;
        }
        debugMsg("StatsDB.query: done");
        return true;
    }

    public function getNumRows() {
        global $debug;

        $numRows = $this->result->num_rows;
        if ($debug) {
            echo "<p>StatsDB.getNumRows: numsRows = $numRows</p>\n";
        }
        return $numRows;
    }

    public function getNextRow() {
        global $debug;

        $row = $this->result->fetch_row();
        if ($debug) {
            $rowStr = htmlspecialchars( print_r($row, true) );
            echo "StatsDB.query getNextRow row=$rowStr</p>\n";
        }
        return $row;
    }

    public function getNextNamedRow() {
        global $debug;
        $row = $this->result->fetch_assoc();
        if ($debug) {
            echo "<pre>StatsDB.query getNextNamedRow row"; print_r($row); echo "</pre>\n";
        }
        return $row;
    }

    function printRow($row) {
        for ($i = 0 ; $i < count($row) ; $i++) {
            echo $row[$i];
            if ($i < count($row) - 1) {
                echo ",";
            } else {
                echo "\n";
            }
        }
    }

    public function queryRow($sql) {
        $this->query($sql);
        return $this->getNextRow();
    }

    public function queryNamedRow($sql) {
        $this->query($sql);
        return $this->getNextNamedRow();
    }

    public function getNumFields() {
        return $this->result->field_count;
    }

    public function getFieldName($index) {
        return $this->result->fetch_field_direct($index)->name;
    }

    function getColumnTypes() {
        global $debug;
        $result = array();
        for ($i = 0 ; $i < $this->result->field_count ; $i++) {
            $meta = $this->result->fetch_field_direct($i);
            // Looks like there's a SonarQube bug here, its incorrectly complaining about
            // references to static variables via self
            $result[$meta->name] = self::$typenames[$meta->type]; // NOSONAR
        }
        if ($debug) {
            $str = print_r($result, true);
            echo "StatsDB.getColumnTypes result=$str</p>\n";
        }
        return $result;
    }

    function getColumnNames() {
        $result = array();
        for ($i = 0 ; $i < $this->result->field_count ; $i++) {
            $meta = $this->result->fetch_field_direct($i);
            $result[] = $meta->name;
        }
        return $result;
    }

    function lastInsertId() {
        return $this->mysqli->insert_id;
    }

    function escape( $str ) {
        return $this->mysqli->real_escape_string( $str );
    }

    //Builds and returns a query to check if the passed table has any data for that date.
    public function hasDataQuery( $table, $timeCol = 'time', $dateOnly = false, $condition = null ) {
        global $site, $date;

        $querySql = "SELECT $table.siteid FROM $table, sites WHERE " . $this->where($table, $timeCol, $dateOnly);
        if ( ! is_null($condition) ) {
            $querySql = $querySql . " AND " . $condition;
        }
        return $querySql .= " LIMIT 1"; //NOSONAR
    }

    //Uses hasDataQuery to build a query, then runs it.
    //Returns true if the query returns any data.
    public function hasData( $table, $timeCol = 'time', $dateOnly = false, $condition = null ) {
        $row = $this->queryRow( $this->hasDataQuery( $table, $timeCol, $dateOnly, $condition ) );
        //NOSONAR is used bellow as $row will return a boolean (false) only if it has no data,
        //but if it has it will return an int.
        return ($row != false); //NOSONAR
    }

    //Genereate the "normal" where cause,
    // table.siteid = sites.id AND sites.name = 'site'" AND
    // table.timeCol BETWEEN 'date 00:00:00' AND 'date 23:59:59'
    public function where( $table, $timeCol = 'time', $dateOnly = false ) {
        global $site, $date;

        $siteclause = "$table.siteid = sites.id AND sites.name = '$site'";
        if ( $dateOnly ) {
            $timeclause = "$table.$timeCol = '$date'";
        } else {
            $timeclause = "$table.$timeCol BETWEEN '$date 00:00:00' AND '$date 23:59:59'";
        }

        return $siteclause . " AND " . $timeclause;
    }

    private function initTLS($accessType, $userid, $connectFlags, $cert) {
        global $dbUseTls, $debug;

        if ( isset($dbUseTls) && (!$dbUseTls) ) {
            return $connectFlags;
        }

        if ( $accessType == self::ACCESS_REPLICATION ) {
            if ( is_null($cert) ) {
                $certFile = "repl-client-repladm.cer";
                $caFile = "repl-ca.cer";
            } else {
                $certFile = $cert . ".cer";
                $caFile = $cert . "-ca.cer";
            }
            // replication servers use certificate from ericsson certificate site
            // This doesn't allow SANs so we're stuck with ddprepl.athtem.eei.ericsson.se
            // which means verification will fail as we're connecting via the private interface
            // So we need to turn verfication off
            $connectFlags = MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        } else {
            $certFile = "db-client-" . $userid . ".cer";
            $caFile = "db-srv-ca.cer";
        }

        if ( $debug ) {
            echo "StatsDB.initTLS certFile=$certFile caFile=$caFile</p>\n";
        }

        $this->mysqli->ssl_set(
            "/etc/certs/db-client.key",
            "/etc/certs/" . $certFile,
            "/etc/certs/" . $caFile,
            null,
            null
        );

        return $connectFlags;
    }
}
