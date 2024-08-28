package StatsDB;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(connect_db createServer dbDo dbSelectAllArr dbSelectAllHash getBcpFileName getIdMap getServerId getServerIdWithoutFail getSiteId getStatsDbName setStatsDB_Debug);

use strict;
use warnings;
use Carp;
use strict;

use StatsDB_MariaDB;
use StatsDB_TestConfig;
use StatsDB_NoOp;

our $StatsDB_DEBUG = 0;

our $TEST_MODE_OFF = 0;
our $TEST_MODE_NOOP = 1;
our $TEST_MODE_CONFIG = 2;

our $TEST_MODE = $TEST_MODE_OFF;

sub connect_db() {
    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::connect_db();
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::connect_db();
    } else {
        return StatsDB_NoOp::connect_db();
    }
}

sub createServer($$$$) {
    my ( $dbh, $siteId, $host, $type ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::createServer($dbh, $siteId, $host, $type);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::createServer($dbh, $siteId, $host, $type);
    } else {
        return StatsDB_NoOp::createServer($dbh, $siteId, $host, $type);
    }
}

sub dbDo($$) {
    my ( $dbh, $sql ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::dbDo($dbh, $sql);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::dbDo($dbh, $sql);
    } else {
        return StatsDB_NoOp::dbDo($dbh, $sql);
    }
}

sub dbSelectAllArr($$) {
    my ( $dbh, $sql ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::dbSelectAllArr($dbh, $sql);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::dbSelectAllArr($dbh, $sql);
    } else {
        return StatsDB_NoOp::dbSelectAllArr($dbh, $sql);
    }
}

sub dbSelectAllHash($$) {
    my ( $dbh, $sql ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::dbSelectAllHash($dbh, $sql);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::dbSelectAllHash($dbh, $sql);
    } else {
        return StatsDB_NoOp::dbSelectAllHash($dbh, $sql);
    }
}

# Get a file name for loading data into DB
sub getBcpFileName($) {
    my ($name) = @_;

    my $tmpDir = "/data/tmp";
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    my $bcpFileName = "$tmpDir/$name.bcp";

    return $bcpFileName;
}

sub getIdMap {
    my ( $dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::getIdMap($dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::getIdMap($dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName);
    } else {
        return StatsDB_NoOp::getIdMap($dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName);
    }
}

sub getServerId($$$) {
    my ( $dbh, $siteId, $host ) = @_;

    my $result = getServerIdWithoutFail($dbh, $siteId, $host);
    ($result > 0) or die "Could not find server \"$host\"";
    return $result;
}

sub getServerIdWithoutFail($$$) {
    my ( $dbh, $siteId, $host ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::getServerIdWithoutFail($dbh, $siteId, $host);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::getServerIdWithoutFail($dbh, $siteId, $host);
    } else {
        return StatsDB_NoOp::getServerIdWithoutFail($dbh, $siteId, $host);
    }
}

sub getSiteId($$) {
    my ( $dbh, $site ) = @_;

    if ( $TEST_MODE == $TEST_MODE_OFF ) {
        return StatsDB_MariaDB::getSiteId($dbh, $site);
    } elsif ( $TEST_MODE == $TEST_MODE_CONFIG ) {
        return StatsDB_TestConfig::getSiteId($dbh, $site);
    } else {
        return StatsDB_NoOp::getSiteId($dbh, $site);
    }
}

sub getStatsDbName() {
    my $dbName = "statsdb";
    if ( exists $ENV{"STATS_DB"} ) {
        $dbName = $ENV{"STATS_DB"};
    }
    return $dbName;
}

sub setStatsDB_Debug($) {
    my ($newDebug) = @_;
    $StatsDB_DEBUG = $newDebug;
}

sub testMode($) {
    my ($testConfig) = @_;

    if ( defined $testConfig ) {
        StatsDB_TestConfig::load($testConfig);
        $TEST_MODE = $TEST_MODE_CONFIG;
    } else {
        $TEST_MODE = $TEST_MODE_NOOP;
    }
}

1;
