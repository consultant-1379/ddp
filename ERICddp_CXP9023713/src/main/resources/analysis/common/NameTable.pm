package NameTable;

use strict;
use warnings;

use Data::Dumper;
use DBI;

use StatsDB;

sub removeUnused($$$$$$) {
    my ( $dbh, $nameTable, $idCol, $nameCol, $r_references, $doUpdate ) = @_;

    lockTables( $dbh, $nameTable, $r_references );

    my $r_usedIdsByTabCol = getUsedIds($dbh, $nameTable, $r_references);
    my %usedIds = ();
    foreach my $r_usedIds ( values %{$r_usedIdsByTabCol} ) {
        while ( my ($id,$count) = each %{$r_usedIds} ) {
            $usedIds{$id} += $count;
        }
    }

    dbLogMsg(" Get exists list from $nameTable");
    my $r_idMap = getIdMap( $dbh, $nameTable, $idCol, $nameCol, [] );

    my $unusedCount = 0;
    foreach my $nameValue ( keys %{$r_idMap} ) {
        if ( !exists $usedIds{ $r_idMap->{$nameValue} } ) {
            $unusedCount++;
            dbLogMsg(
                sprintf( "  %5d %s", $r_idMap->{$nameValue}, $nameValue ) );

            if ($doUpdate) {
                dbDo( $dbh,
                    "DELETE FROM $nameTable WHERE $idCol = "
                      . $r_idMap->{$nameValue} )
                  or die "Failed to delete from $nameTable";
            }
        }
    }

    if ($doUpdate) { dbDo( $dbh, "UNLOCK TABLES" ) or die "Failed to unlock"; }

    dbLogMsg("Total unused count $unusedCount");

    return $unusedCount;
}

sub removeDuplicates($$$$$$) {
    my ( $dbh, $nameTable, $idCol, $nameCol, $r_references, $doUpdate ) = @_;

    lockTables( $dbh, $nameTable, $r_references );

    dbLogMsg("Checking for duplicate names in $nameTable");

    my $r_duplicateNames = dbSelectAllArr($dbh, "SELECT $nameCol FROM $nameTable GROUP BY name HAVING COUNT(*) > 1");
    if ( $#{$r_duplicateNames} > -1 ) {
        my $r_usedIdsByTabCol = getUsedIds($dbh, $nameTable, $r_references);

        my $idSth = $dbh->prepare("SELECT $idCol FROM $nameTable WHERE name = ?")
          or die "Couldn't prepare statement: " . $dbh->errstr;

        foreach my $r_duplicateName ( @{$r_duplicateNames} ) {
            my $theName = $r_duplicateName->[0];
            $idSth->execute($theName) or die "Couldn't execute statement: " . $idSth->errstr;

            my @ids = ();
            while ( my $r_IdRow = $idSth->fetchrow_hashref() ) {
                push @ids, $r_IdRow->{$idCol};
            }
            dbLogMsg(
                " Found multiple ids (" . join( ",", @ids ) . ") for " . $theName );
            my $keepId = pop @ids;
            foreach my $replaceId (@ids) {
                dbLogMsg("  Replacing $replaceId with $keepId");

                foreach my $r_reference ( @{$r_references} ) {
                    my $refTable = $r_reference->{'table'};
                    my $refColumn = $r_reference->{'column'};
                    if ( exists $r_usedIdsByTabCol->{$refTable . $refColumn}->{$replaceId} ) {
                        my $updateSql = sprintf(
                            "UPDATE %s SET %s = %d WHERE %s = %d",
                            $refTable,
                            $refColumn,
                            $keepId,
                            $refColumn,
                            $replaceId
                        );
                        if ( $doUpdate ) {
                            dbDo($dbh, $updateSql ) or die "Failed to change $refColumn to from $replaceId to $keepId in $refTable";
                        }
                    }
                }

                if ( $doUpdate ) {
                    dbDo( $dbh, "DELETE FROM $nameTable WHERE $idCol = $replaceId" );
                }
            }
        }

        $idSth->finish;
    }

    dbDo( $dbh, "UNLOCK TABLES" ) or die "Failed to unlock";
}

sub compact($$$$$$) {
    my ( $dbh, $nameTable, $idCol, $nameCol, $r_references, $exitFile ) = @_;

    lockTables( $dbh, $nameTable, $r_references );
    my $r_usedIdsByTabCol = getUsedIds($dbh, $nameTable, $r_references);

    dbLogMsg("Compact $idCol in $nameTable");
    dbLogMsg("Remove AUTO_INCREMENT property $nameTable");

    my $r_schemaRows =
        dbSelectAllArr($dbh,
                       sprintf("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '%s' AND TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = '%s'",
                               $nameTable, $idCol));
    my $idColType = $r_schemaRows->[0]->[0];
    dbDo( $dbh,
          "ALTER TABLE $nameTable MODIFY COLUMN $idCol $idColType NOT NULL" )
        or die "Failed";

    # Can't use getIdMap here if we want to support name tables
    # which have an extra key, e.g. emc_lun
    my $r_rows = dbSelectAllHash( $dbh, "SELECT $idCol, $nameCol FROM $nameTable");
    my @unsortedIds = ();
    my %idToName = ();
    foreach my $r_row ( @{$r_rows} ) {
        push @unsortedIds, $r_row->{'id'};
        $idToName{$r_row->{$idCol}} = $r_row->{$nameCol};
    }
    my @idList = sort { $a <=> $b } @unsortedIds;
    my $countName = ( $#idList + 1 );
    dbLogMsg( " Number of rows=$countName max id = " . $idList[$#idList] );

    my $header = "      From  To    ";
    my $index = 0;
    foreach my $r_reference ( @{$r_references} ) {
        my $refTable = $r_reference->{'table'};
        my $refColumn = $r_reference->{'column'};
        $index++;
        printf("  %2d => %s\n", $index, $refTable . "." . $refColumn);
        $header .= sprintf(" %8d", $index);
    }
    $header .= " Cmd";
    dbLogMsg($header);

    my $remappedCount = 0;
    for (
        my $id = 1 ;
        ( $id <= $countName ) && ( !dbCheckExit($exitFile) ) ;
        $id++
        )
    {
        if ( !exists $idToName{$id} ) {
            # id value is unused so move the highest number id to this value
            $remappedCount++;
            my $idToRemap   = pop @idList;
            my $nameToRemap = $idToName{$idToRemap};

            dbDo( $dbh, "UPDATE $nameTable SET $idCol = $id WHERE $idCol = $idToRemap" )
                or die
                "Failed to change id to from $idToRemap to $id in $nameTable";

            my $msg = sprintf( "%5d %5d %5d", $remappedCount, $idToRemap, $id );

            foreach my $r_reference ( @{$r_references} ) {
                my $refTable = $r_reference->{'table'};
                my $refColumn = $r_reference->{'column'};
                my $rowsUpdated = "-";
                if ( exists $r_usedIdsByTabCol->{$refTable . $refColumn}->{$idToRemap} ) {
                    $rowsUpdated = dbDo(
                        $dbh,
                        sprintf(
                            "UPDATE %s SET %s = %d WHERE %s = %d",
                            $refTable, $refColumn, $id, $refColumn, $idToRemap
                        )
                    ) or die "Failed to change $refColumn to from $idToRemap to $id in $refTable";
                }
                $msg .= sprintf( " %8s", $rowsUpdated );
            }
            $msg .= " " . $nameToRemap;
            dbLogMsg($msg);
        }
    }

    dbLogMsg(" Reset AUTO_INCREMENT property $nameTable");
    dbDo(
        $dbh,
        "ALTER TABLE $nameTable MODIFY COLUMN $idCol $idColType NOT NULL AUTO_INCREMENT"
    ) or die "Failed to ALTER $idCol column";

    $r_rows = dbSelectAllArr( $dbh, "SELECT MAX($idCol) FROM $nameTable" );
    my $autoIncr = $r_rows->[0]->[0];
    dbLogMsg(" Set AUTO_INCREMENT value for $nameTable to $autoIncr");
    dbDo(
        $dbh,
        sprintf( 'ALTER TABLE %s AUTO_INCREMENT = %d', $nameTable, $autoIncr )
    ) or die "Failed to ALTER AUTO_INCREMENT";

    dbDo( $dbh, "UNLOCK TABLES" ) or die "Failed to unlock";
}

sub dbCheckExit($) {
    my ($file) = @_;

    if ( !defined $file ) {
        return 0;
    }

    if ( -r $file ) {
        return 1;
    }
    else {
        return 0;
    }
}

sub dbLogMsg($) {
    my ($msg) = @_;
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime();
    printf "%02d-%02d-%02d:%02d:%02d:%02d %s\n", ($year-100),$mon+1,$mday, $hour, $min, $sec, $msg;
}

sub lockTables($$$) {
    my ( $dbh, $nameTable, $r_references ) = @_;

    my %referencesTables = ();
    foreach my $r_reference ( @{$r_references} ) {
        $referencesTables{$r_reference->{'table'}} = 1;
    }

    my $sql = "LOCK TABLE $nameTable WRITE";
    foreach my $refTable ( sort keys %referencesTables ) {
        $sql .= ",$refTable WRITE";
    }
    dbDo( $dbh, $sql ) or die "Failed to lock tables";
}

sub getUsedIds($$$) {
    my ( $dbh, $nameTable, $r_references ) = @_;

    my %usedIdsByTabCol = ();
    foreach my $r_reference ( @{$r_references} ) {
        my $tableName = $r_reference->{'table'};
        my $colName = $r_reference->{'column'};
        dbLogMsg(" Get used list from $tableName $colName");
        my $usedSth = $dbh->prepare("SELECT DISTINCT($colName) FROM $tableName")
          or die "Couldn't prepare statement: " . $dbh->errstr;
        $usedSth->execute()
          or die "Couldn't execute statement: " . $usedSth->errstr;
        my %usedIds = ();
        while ( my @row = $usedSth->fetchrow_array() ) {
            if ( defined $row[0] ) {
                $usedIds{ $row[0] }++;
            }
        }
        $usedSth->finish();
        $usedIdsByTabCol{$tableName . $colName} = \%usedIds;
    }

    return \%usedIdsByTabCol;
}

1;
