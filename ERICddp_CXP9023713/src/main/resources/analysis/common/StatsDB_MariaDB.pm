package StatsDB_MariaDB;

use strict;
use warnings;

use Carp;
use Data::Dumper;

sub connect_db {
    my $dbName = StatsDB::getStatsDbName();

    my $dsn = "DBI:mysql:database=$dbName";

    if ( exists $ENV{"STATS_DB_DSN_ARG"} ) {
        $dsn .= ";" . $ENV{"STATS_DB_DSN_ARG"};
    }
    elsif ( exists $ENV{"STATS_DB_HOST"} ) {
        $dsn .= ";host=" . $ENV{"STATS_DB_HOST"};
    } else {
        # Default to connecting to dbhost
        $dsn .= ";host=dbhost";
    }

    my $useTLS = 1;
    if ( exists $ENV{"STATS_DB_TLS"} && $ENV{"STATS_DB_TLS"} eq 'false' ) {
        $useTLS = 0;
    }
    if ( $useTLS ) {
        $dsn = join(
            ";",
            $dsn,
            "mysql_ssl=1",
            "mysql_ssl_verify_server_cert=1",
            "mysql_ssl_ca_file=/etc/certs/db-srv-ca.cer",
            "mysql_ssl_client_key=/etc/certs/db-client.key",
            "mysql_ssl_client_cert=/etc/certs/db-client-statsadm.cer"
        );
    }

    if ( $StatsDB::StatsDB_DEBUG > 3 ) { printf( "StatsDB_MariaDB::connect_db dsn=%s\n", $dsn ); }
    my $dbh = DBI->connect( $dsn, "statsadm", "_sadm" );

    if ( exists $ENV{"SQL_MODE"} ) {
        my $sqlMode = $ENV{"SQL_MODE"};
        my $result = $dbh->do("SET sql_mode=$sqlMode");
        if ( !defined $result ) {
            print "connect_db: ERROR setting sql mode to: $sqlMode\n";
        }
    }

    return $dbh;
}

sub dbDo {
    my ( $dbh, $sql ) = @_;

    if ( $StatsDB::StatsDB_DEBUG > 2 ) { print "StatsDB_MariaDB::dbDo: sql=$sql\n"; }
    my $result = $dbh->do($sql);
    if ( !defined $result ) {
        print "StatsDB_MariaDB::dbDo: ERROR processing $sql\n", $dbh->errstr, "\n";
    }
    return $result;
}

sub dbSelectAllArr {
    my ( $dbh, $sql ) = @_;

    if ( $StatsDB::StatsDB_DEBUG > 2 ) { print "StatsDB_MariaDB::dbSelectAllArr: sql=$sql\n"; }
    my $r_rows = $dbh->selectall_arrayref($sql);
    if ( !defined $r_rows ) {
        print "StatsDB_MariaDB::dbSelectAllArr: ERROR processing $sql\n", $dbh->errstr, "\n";
    }
    return $r_rows;
}

sub dbSelectAllHash {
    my ( $dbh, $sql ) = @_;

    if ( $StatsDB::StatsDB_DEBUG > 2 ) { print "StatsDB_MariaDB::dbSelectAllHash: sql=$sql\n"; }
    my $r_rows = $dbh->selectall_arrayref( $sql, { Slice => {} } );
    if ( !defined $r_rows ) {
        print "StatsDB_MariaDB::dbSelectAllHash: ERROR processing $sql\n", $dbh->errstr, "\n";
    }
    return $r_rows;
}

sub getSiteId {
    my ( $dbh, $site ) = @_;

    my $result = -1;

    my $sth = $dbh->prepare('SELECT id FROM sites WHERE name = ?')
      or die "Couldn't prepare statement: " . $dbh->errstr;
    $sth->execute($site)    # Execute the query
      or die "Couldn't execute statement: " . $sth->errstr;
    if ( $StatsDB::StatsDB_DEBUG > 3 ) { printf( "StatsDB_MariaDB::getSiteId rows=%d\n", $sth->rows ); }
    if ( $sth->rows == 1 ) {
        my @data = $sth->fetchrow_array();
        $result = $data[0];
    }
    $sth->finish;

    if ( $StatsDB::StatsDB_DEBUG > 3 ) { print "StatsDB_MariaDB::getSiteId site=$site result=$result\n"; }

    return $result;
}

sub getServerIdWithoutFail {
    my ( $dbh, $siteId, $host ) = @_;
    my $sql = "SELECT id from servers where siteid = $siteId and hostname = '" . $host . "'";
    if ( $StatsDB::StatsDB_DEBUG > 1 ) { print "select sql: $sql\n"; }
    my $sth = $dbh->prepare($sql)
        or die "Couldn't prepare statement: " . $dbh->errstr;
    $sth->execute()    # Execute the query
        or die "Couldn't execute statement: " . $sth->errstr;
    ( $sth->rows == 1 ) or return 0;
    my @row      = $sth->fetchrow_array();
    my $serverId = $row[0];
    $sth->finish();
    return $serverId;
}

sub createServer {
    my ( $dbh, $siteId, $host, $type ) = @_;

    my $sql = "SELECT id,type from servers where siteid = $siteId and hostname = '" . $host . "'";
    if ( $StatsDB::StatsDB_DEBUG > 1 ) { print "StatsDB_MariaDB::createServer: select sql: $sql\n"; }

    my $serverId = undef;

    my $sth = $dbh->prepare($sql)
        or die "Couldn't prepare statement: " . $dbh->errstr;
    $sth->execute()             # Execute the query
        or die "Couldn't execute statement: " . $sth->errstr;
    if ($sth->rows == 0) {
        dbDo($dbh,sprintf("INSERT into servers (siteid,hostname,type) VALUES ( %d, %s, %s )",
                          $siteId,
                          $dbh->quote($host),
                          $dbh->quote($type))
            ) or die "Failed Failed to insert $host ";
        $serverId = $dbh->last_insert_id( undef, undef, "servers", "id" );
    } else {
        my ($currentType);
        ($serverId,$currentType) = $sth->fetchrow_array();
        if ( $StatsDB::StatsDB_DEBUG > 1 ) { print "StatsDB_MariaDB::createServer: serverId=$serverId currentType=$currentType\n"; }
        if ( $type ne $currentType ) {
            dbDo($dbh, sprintf("UPDATE servers SET type = %s WHERE id = %d",
                               $dbh->quote($type),
                               $serverId)
                ) or die "Failed to update $host";
        }
    }

    $sth->finish();

    return $serverId;
}

sub getIdMap {
    my ( $dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName ) = @_;

    if ( $StatsDB::StatsDB_DEBUG > 2 ) {
        printf(
            "StatsDB_MariaDB::getIdMap: tableName=%s #r_ValList=%d extraColValue=%s\n",
            $tableName,
            $#{$r_ValList},
            (defined $extraColValue ? $extraColValue : "undef")
        );
    }

    my $sql = sprintf( "SELECT %s,%s FROM %s", $nameCol, $idCol, $tableName );
    my $extraColName = "siteid";
    if ($extraColValue) {
        if ($colName) { $extraColName = $colName; }
        $sql .= sprintf( " WHERE %s = %d", $extraColName, $extraColValue );
    }

    $dbh->{AutoCommit} = 0;    # enable transactions, if possible
    $dbh->{RaiseError} = 1;

    my $r_IdMap;

    my $maxStringLen = undef;

    eval {
        $r_IdMap = readIdMap( $dbh, $sql );

        my $reloadRequired = 0;
        # The values are sorted to ensure consisent order of addition
        # (mainly so we can compare two DBs for test purposes)
        my @inserts = ();
        foreach my $val ( sort @{$r_ValList} ) {
            if ( $StatsDB::StatsDB_DEBUG > 12 ) {
                print "StatsDB_MariaDB::getIdMap: checking \"$val\"\n";
            }
            if ( !exists $r_IdMap->{$val} ) {
                if ( $StatsDB::StatsDB_DEBUG > 11 ) {
                    print "StatsDB_MariaDB::getIdMap: not found \"$val\"\n";
                }

                #
                # Verify that there's no non-ascii chars
                #
                if ( $val =~ /[^ -~]/ ) {
                    confess "StatsDB_MariaDB::getIdMap: Tried to add non-printable string to $tableName: $val";
                }

                #
                # Verify that there's no trailing white space HR81303
                #
                if ( $val =~ /\s+$/ ) {
                    confess "StatsDB_MariaDB::getIdMap: Tried to add string with trailing spaces to $tableName: \"$val\"";
                }

                #
                # Verify that the value fits in the name column
                #
                if ( !defined $maxStringLen ) {
                    my $dbName = "statsdb";
                    if ( exists $ENV{"STATS_DB"} ) {
                        $dbName = $ENV{"STATS_DB"};
                    }
                    my $r_rows = dbSelectAllArr(
                        $dbh,
                        sprintf("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '%s' AND table_schema = DATABASE() AND COLUMN_NAME = '%s'",
                            $tableName, $nameCol
                        )
                    );
                    $maxStringLen = $r_rows->[0]->[0];
                }
                my $valLength = length($val);
                if ( $valLength > $maxStringLen ) {
                    confess "StatsDB_MariaDB::getIdMap: Tried to add \"$val\" which is longer($valLength) then the width($maxStringLen) of $nameCol in $tableName";
                }

                # Temporarily printing the new values added to volumes to help with TORF-660507
                if ( $tableName eq 'volumes' ) {
                    print "Adding to volumes: $val\n";
                }

                my $insert_sql;
                if ($extraColValue) {
                    $insert_sql =
                      sprintf( "INSERT INTO %s (%s,%s) VALUES (%d,%s)",
                        $tableName, $extraColName, $nameCol, $extraColValue,
                        $dbh->quote($val) );
                }
                else {
                    $insert_sql = sprintf( "INSERT INTO %s (%s) VALUES (%s)",
                        $tableName, $nameCol, $dbh->quote($val) );
                }
                push @inserts, { 'sql' => $insert_sql, 'val' => $val };
                # Seems that in some cases the $r_ValList has duplicates so
                # we need to populate the r_IdMap here to prevent multiple INSERTS
                # for the duplicated values, this will get overwritten with a
                # real value when we do the actual insert
                $r_IdMap->{$val} = undef;
            }
        }

        # Inserts are all done together to avoid the issues with auto increment
        # if the transation is rolled back due to an issue with some of the values
        # https://mariadb.com/kb/en/auto_increment/#missing-values
        foreach my $r_insert ( @inserts ) {
            if ( $StatsDB::StatsDB_DEBUG > 2 ) { printf "StatsDB_MariaDB::getIdMap: %s\n", $r_insert->{'sql'}; }
            $dbh->do($r_insert->{'sql'})
                or die "Failed to insert " . $r_insert->{'val'} . " " . $dbh->errstr;

            my $id =
                $dbh->last_insert_id( undef, undef, $tableName, $idCol );
            if ( $StatsDB::StatsDB_DEBUG > 2 ) { print "StatsDB_MariaDB::getIdMap: id=$id\n"; }
            $r_IdMap->{$r_insert->{'val'}} = $id;
        }

        $dbh->commit;    # commit the changes if we get this far
    };
    if ($@) {
        warn "Transaction aborted because $@";

        # now rollback to undo the incomplete changes
        # but do it in an eval{} as it may also fail
        eval { $dbh->rollback };

        # add other application on-error-clean-up code here
        die "Update of $tableName failed";
    }

    $dbh->{AutoCommit} = 1;
    $dbh->{RaiseError} = 0;

    if ( $StatsDB::StatsDB_DEBUG > 4 ) { print Dumper( "StatsDB_MariaDB::getIdMap idMap", $r_IdMap ); }

    return $r_IdMap;
}

sub readIdMap {
    my ( $dbh, $sql ) = @_;

    if ( $StatsDB::StatsDB_DEBUG > 4 ) { print "readIdMap: $sql\n"; }

    my %idMap = ();
    my $sth   = $dbh->prepare($sql)
      or die "Couldn't prepare statement: $sql " . $dbh->errstr;
    $sth->execute()    # Execute the query
      or die "Couldn't execute statement: $sql " . $sth->errstr;
    my $countRows = 0;
    while ( my $r_row = $sth->fetchrow_arrayref() ) {
        $countRows++;
        $idMap{ $r_row->[0] } = $r_row->[1];
    }
    $sth->finish;

    if ( $StatsDB::StatsDB_DEBUG > 10 ) {
        my @keys = keys %idMap;
        print "StatsDB_MariaDB::readIdMap: countRows=$countRows #keys=$#keys\n";
        if ( $StatsDB::StatsDB_DEBUG > 11 ) { print Dumper( "StatsDB_MariaDB::readIdMap keys", \@keys ); }
    }

    return \%idMap;
}

1;
