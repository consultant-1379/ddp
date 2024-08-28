use strict;
use warnings;

use Data::Dumper;
use Test::More;

use StatsDB;

our $DEBUG = 0;

sub test_NoOpCreateServer() {
    my $dbh = connect_db();
    my $got = createServer($dbh, 1, "testhost", "testtype");
    ok(
        $got == 1,
        "Verify NoOp  createServer returns 1"
    );
}

sub test_NoOpDbDo() {
    my $dbh = connect_db();
    my $got = dbDo($dbh, "DELETE FROM sites WHERE id = 1");
    ok(
        $got == 0,
        "Verify NoOp dbDo returns 0"
    );
}

sub test_NoOpDbSelectAllArr() {
    my $dbh = connect_db();
    my $r_got = dbSelectAllArr($dbh, "SELECT * FROM sites");
    is_deeply($r_got, [], "Verify that NoOp dbSelectAllArr returns an empty array");
}

sub test_NoOpDbSelectAllHash() {
    my $dbh = connect_db();
    my $r_got = dbSelectAllHash($dbh, "SELECT * FROM sites");
    is_deeply($r_got, [], "Verify that NoOp dbSelectAllHash returns an empty array");
}

sub test_NoOpGetIdMap() {
    my $dbh = connect_db();
    my $r_got = getIdMap($dbh, undef, undef, undef, ['value1']);
    is_deeply($r_got, {'value1' => 1}, "Verify that NoOp getIdMap returns a valid hash");
}

sub test_NoOpGetServerId() {
    my $dbh = connect_db();
    my $got = getServerId($dbh, 1, "testhost");
    ok(
        $got == 1,
        "Verify NoOp getServerId returns 1"
    );
}

sub test_NoOpGetServerIdWithoutFail() {
    my $dbh = connect_db();
    my $got = getServerIdWithoutFail($dbh, 1, "testhost");
    ok(
        $got == 1,
        "Verify NoOp getServerIdWithoutFail returns 1"
    );
}

sub test_NoOpGetSiteId() {
    my $dbh = connect_db();
    my $siteId = getSiteId($dbh, "TestSite");
    ok(
        $siteId == 1,
        "Verify NoOp  getSiteId returns 1"
    );
}

# Turn on NOOP mode in StatsDB
StatsDB::testMode(undef);
test_NoOpCreateServer();
test_NoOpDbDo();
test_NoOpDbSelectAllArr();
test_NoOpDbSelectAllHash();
test_NoOpGetIdMap();
test_NoOpGetServerId();
test_NoOpGetServerIdWithoutFail();
test_NoOpGetSiteId();

done_testing();
