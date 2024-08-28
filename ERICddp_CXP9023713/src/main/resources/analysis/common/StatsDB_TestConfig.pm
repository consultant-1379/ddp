package StatsDB_TestConfig;

use strict;
use warnings;

use JSON;
use Data::Dumper;

require StatsDB_TestConfig_Statement;

our $r_testConfig;
our $r_errors;

sub load($) {
    my ($jsonFile) = @_;

    open INPUT, $jsonFile or die "Cannot open $jsonFile";
    my $file_content = do { local $/; <INPUT> };
    close INPUT;

    $r_testConfig = decode_json($file_content);
    $r_testConfig->{'qkeys'} = {};
    for my $r_entry ( @{$r_testConfig->{'queries'}} ) {
        $r_testConfig->{'qkeys'}->{sqlToKey($r_entry->{'sql'})} = $r_entry;
    }

    $r_testConfig->{'doKeys'} = {};
    if ( exists $r_testConfig->{'dbDo'} ) {
        foreach my $sql ( @{$r_testConfig->{'dbDo'}} ) {
            $r_testConfig->{'doKeys'}->{sqlToKey($sql)} = 1;
        }
    }

    $r_testConfig->{'last_ids'} = {};
    if ( exists $r_testConfig->{'last_insert_ids'} ) {
        $r_testConfig->{'last_ids'} = $r_testConfig->{'last_insert_ids'};
    }

    $r_testConfig->{'last_ids'} = {};

    if ( ! exists $r_testConfig->{'idmaps'} ) {
        $r_testConfig->{'idmaps'} = {};
    }

    $r_testConfig->{'prepstmtkeys'} = {};
    if ( exists $r_testConfig->{'prepstmt'} ) {
        while ( my ($stmt,$data) = each %{$r_testConfig->{'prepstmt'}} ) {
            my $key = sqlToKey($stmt);
            $r_testConfig->{'prepstmtkeys'}->{$key} = $data;
        }
    }

    $r_errors = [];

    if ( $StatsDB::StatsDB_DEBUG ) { print Dumper("StatsDB_TestConfig::load", $r_testConfig); }
}

sub connect_db() {
    return bless {};
}

sub createServer($$$$) {
    my ( $dbh, $siteId, $host, $type ) = @_;

    return 1;
}

sub dbDo($$) {
    my ( $dbh, $sql ) = @_;

    my $found = exists $r_testConfig->{'doKeys'}->{sqlToKey($sql)};
    if ( ! $found ) {
        recordError("Could not find a matching query for $sql");
    }
    return $found;
}

sub dbSelectAllArr($$) {
    my ( $dbh, $sql ) = @_;

    return findSqlMatch($sql);
}

sub dbSelectAllHash($$) {
    my ( $dbh, $sql ) = @_;

    return findSqlMatch($sql);
}

sub getIdMap {
    my ( $dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName ) = @_;

    my %result = ();
    my $index = 0;
    if ( exists $r_testConfig->{'idmaps'}->{$tableName} ) {
        while ( my ($name,$value) = each %{$r_testConfig->{'idmaps'}->{$tableName}} ) {
            $result{$name} = $value;
            if ( $value > $index ) {
                $index = $value;
            }
        }
    }

    $index++;
    foreach my $value ( @{$r_ValList} ) {
        if ( ! exists $result{$value} ) {
            $result{$value} = $index;
            $index++;
        }
    }
    return \%result;
}

sub getServerIdWithoutFail($$$) {
    my ( $dbh, $siteId, $host ) = @_;

    return 1;
}

sub getSiteId($$) {
    my ( $dbh, $site ) = @_;

    return 1;
}

sub findSqlMatch($) {
    my ($sql) = @_;

    my $key = sqlToKey($sql);
    if ( $StatsDB::StatsDB_DEBUG ) { print "findSqlMatch: key=$key\n"; }

    my $r_entry = $r_testConfig->{'qkeys'}->{$key};
    if ( defined $r_entry ) {
        if ( $StatsDB::StatsDB_DEBUG ) { print Dumper("findSqlMatch matched r_entry", $r_entry); }
        return $r_entry->{'results'};
    } else {
        recordError("Could not find a matching query for $sql");
        return [];
    }
}

sub disconnect() {}

sub begin_work() {}
sub commit() {}

sub prepare($) {
    my ($dbh, $statement) = @_;

    my $r_data = $r_testConfig->{'prepstmtkeys'}->{sqlToKey($statement)};
    return StatsDB_TestConfig_Statement->new($statement, $r_data);
}

sub last_insert_id($$$$)  {
    my ($dbh, $ignored1, $ignored2, $table, $column) = @_;
    my $last_id = $r_testConfig->{'last_ids'}->{$table};
    if ( defined $last_id ) {
        return $last_id;
    } else {
        return 1;
    }
}

sub errstr() {
    if ( $#{$r_errors} > -1 ) {
        return $r_errors->[$#${r_errors}];
    } else {
        return undef;
    }
}

sub recordError($) {
    my ($error) =  @_;
    print "WARN: $error\n";
    push @{$r_errors}, $error;
}

sub sqlToKey($) {
    my ($sql) = @_;

    $sql =~ s/\s+//g;
    $sql =~ s/\n//g;

    return $sql;
}

1;
