package PartitionManager;

#
# Two types of partitioning
#
# Quarterly: New partition every quarter, partition names start with Q
#
# Adaptive:
#  Each partition is name as PYYYYMMDD with one exception, PMAXVALUE
#  Each partitions holds all the data for the time peroid less then the time in the partition name,
#   i.e. time < YYYYYMMDD
#
#  The PMAXVALUE partition should always be empty
#
#  Then we have the daily partitions, tomorrows, todays, yesterdays, etc.
# "tomorrows" will be start getting data after midnight
#  There number of daily partitions is held in DAILY_PARTITION_COUNT
#
#  The next partition holds all data from prior to the oldest daily patitions to the start of the current month
#  The following 12 partitions hold the data for 1 month, except of for the oldest one. This holds all data
#   until the start of it's year
#  The remaining partitions are named PYYYY0101 and hold a year of data, i.e. data where YYYY - 1 < time < YYYY
#
# When new monthly partitions are added, the data from the oldest monthly partition is merged into the next
# oldest monthly partition, e.g. data for P20080601 (which holds all the data 2008-01-01 < time < 2008-06-01) is
# merged into P20080701.
# The exception to this is if MMDD is 0101, this "becomes" the yearly partition
#

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use DBI;
use Time::Local;
use DateTime;
use DateTime::Duration;
use JSON;
use IO::Handle;
use Time::HiRes;
use POSIX ":sys_wait_h";

use StatsDB;
use StatsTime;

our $PMAX_DEF="PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE";
our $QMAX_DEF="PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE";
our $WRITE_MODE = 1;
our $DAILY_PARTITION_COUNT = 9;
our $MONTHLY_PARTITION_COUNT = 12;

our $EXIT_FILE;
our $EXIT_FILE_FOUND = 0;

our $DAY_IN_SEC = 24 * 60 * 60;

our $MERGE_OLD_MONTHLY = 0;

our $PARTITION_ADAPTIVE = 1;
our $PARTITION_QUARTERLY = 2;

our %AGG_INTERVAL = (
    'hour' => 3600,
    'fifteen_min' => 900
);

sub logMsg($) {
    my ($msg) = @_;
    my $timestamp = formatTime(time(),$StatsTime::TIME_SQL);
    printf "%5d %s %s\n", $$, $timestamp, $msg;
    STDOUT->flush();
}

sub checkExit() {
    if ( (! $EXIT_FILE) ) {
        return 0;
    } elsif ( $EXIT_FILE_FOUND == 1 ) {
        return 1;
    } elsif ( -r $EXIT_FILE ) {
        $EXIT_FILE_FOUND = 1;
        logMsg("Found Exit File");
        unlink($EXIT_FILE);
        return 1;
    } else {
        return 0;
    }
}

sub getPartitionName($$) {
    my ($date,$type) = @_;

    my ($year,$month,$mday) = $date =~ /^(\d{4,4})-(\d{2,2})-(\d{2,2})$/;
    my $result = undef;
    if ( $type == $PARTITION_ADAPTIVE ) {
        $result = sprintf "P%04d%02d%02d", $year, $month, $mday;
    } elsif ( $type == $PARTITION_QUARTERLY ) {
        $result = sprintf "Q%04d%02d", $year, $month;
    } else {
        die "Unknown partition type $type";
    }

    if ( $::DEBUG > 5 ) { print "getPartitionName: date=$date type=$type result=$result\n"; }
    return $result;
}

sub getPartitionDef($$) {
    my ($date,$type) = @_;

    my $result = undef;
    if ( $date eq "0000-00-00" ) {
        if ( $type == $PARTITION_ADAPTIVE ) {
            $result = $PMAX_DEF;
        } else {
            $result = $QMAX_DEF;
        }
    } else {
        my ($year,$month,$mday) = $date =~ /^(\d{4,4})-(\d{2,2})-(\d{2,2})$/;
        $result = sprintf "PARTITION %s VALUES LESS THAN (TO_DAYS('%s'))", getPartitionName($date, $type), $date;
    }

    if ( $::DEBUG > 5 ) { print "getPartitionDef: date=$date type=$type result=$result\n"; }
    return $result;
}

sub getPartitionColumn($$$) {
    my ($dbh,$table,$type) = @_;

    my $partitionName = "PMAXVALUE";
    if ( $type == $PARTITION_QUARTERLY ) {
        $partitionName = "QMAXVALUE";
    }

    my $sth = $dbh->prepare("
SELECT PARTITION_EXPRESSION
FROM INFORMATION_SCHEMA.PARTITIONS
WHERE
 TABLE_SCHEMA = (SELECT DATABASE()) AND
 PARTITION_NAME = '$partitionName' AND
TABLE_NAME = ?")
        or die "Failed to prepare query to get partition list for $table: " . $dbh->errstr;
    $sth->execute($table) or die "Failed to execute query for $table: " . $dbh->errstr;

    my @row = $sth->fetchrow_array();
    $sth->finish();

    my ($column) = $row[0] =~ /TO_DAYS\(\s*([^\) ]+)/i;
    if ( $::DEBUG > 3 ) { print "getPartitionColumn: table=$table row[0]=$row[0] column=$column\n"; }
    ( defined $column ) or die "Cannot find partition column for table $table PARTITION_EXPRESSION=$row[0]";
    return $column;
}

sub updatePartitions
{
    my ($dbh,$table,$time) = @_;

    my $r_tableList;
    if ( defined $table )
    {
        $r_tableList = [];
        push @{$r_tableList}, $table;
    }
    else
    {
        $r_tableList = getPartitionedTables($dbh);
    }

    foreach my $partTable ( @{$r_tableList} )
    {
        updatePartitioning($dbh,$partTable,$time);
    }
}

sub updatePartitioningParallel
{
    my ($dbh,$time,$numParallel) = @_;

    my  $r_tableList = getPartitionedTables($dbh);

    # Sort the list of partitioned tables by size
    # this way all the sub-process should get an
    # equal amount of work
    my $r_tableSizes = getTableSizes($dbh);
    my %partTableSizes = ();
    foreach my $table ( @{$r_tableList} ) {
        $partTableSizes{$table} = $r_tableSizes->{$table};
    }
    if ( $::DEBUG > 3 ) { print Dumper("updatePartitioningParallel: partTableSizes", \%partTableSizes); }
    my @sortedPartTables = ();
    foreach my $table ( sort { $partTableSizes{$b} <=> $partTableSizes{$a} } keys %partTableSizes ) {
        if ( $::DEBUG > 4 ) { printf "updatePartitioningParallel: size=%8d, table=%s\n", $partTableSizes{$table}/(1024*1024), $table; }
        push @sortedPartTables, $table;
    }
    if ( $::DEBUG > 3 ) { print Dumper("updatePartitioningParallel: sortedPartTables", \@sortedPartTables); }

    my @tablesPerChilds = ();
    for ( my $ci = 0; $ci < $numParallel; $ci++ ) {
        push @tablesPerChilds, [];
    }

    for ( my $index = 0; $index <= $#sortedPartTables; $index++ ) {
        push @{$tablesPerChilds[$index%$numParallel]}, $sortedPartTables[$index];
    }

    if ( $::DEBUG > 3 ) { print Dumper("updatePartitioningParallel: tablesPerChilds", \@tablesPerChilds); }

    my $database = dbSelectAllArr($dbh, "SELECT DATABASE()")->[0]->[0];

    my %childPids = ();
    for ( my $ci = 0; $ci < $numParallel; $ci++ ) {
        my $r_childTables = $tablesPerChilds[$ci];
        my $pid = fork;
        if ( $pid == 0 ) {
            # In child
            # Need new connection to DB
            $dbh = connect_db();
            dbDo($dbh, "use $database") or die "Failed to switch to $database";

            if ( defined $EXIT_FILE ) {
                $EXIT_FILE = $EXIT_FILE . "." . $$;
            }

            if ( $::DEBUG > 0 ) { print "updatePartitioningParallel: Child $$ tables=" . join(",", @{$r_childTables}) . "\n"; }
            foreach my $partTable ( @{$r_childTables} )
            {
                my $result = updatePartitioning($dbh,$partTable,$time);
                if ( $result != 0 ) {
                    if ( $::DEBUG > 0 ) { print "updatePartitioningParallel: Child $$ 1 exiting\n"; }
                    exit 1;
                }
            }
            if ( $::DEBUG > 0 ) { print "updatePartitioningParallel: Child $$ 0 exiting\n"; }
            exit 0;
        } else {
            $childPids{$pid} = 1;
        }
    }

    # In parent here
    my $done = 0;
    while ( ! $done ) {
        if ( defined $EXIT_FILE && -r $EXIT_FILE ) {
            foreach my $childPid ( keys %childPids ) {
                open EXIT_FILE, ">" . $EXIT_FILE . "." . $childPid;
                close EXIT_FILE;
            }
            checkExit();
        }

        sleep(5);

        my $exitedPid;
        do {
            $exitedPid = waitpid(-1,WNOHANG);
            if ( exists $childPids{$exitedPid} ) {
                if ( $::DEBUG > 0 ) { print "updatePartitioningParallel: Parent child $exitedPid exited\n"; }
                delete $childPids{$exitedPid};
            }
        } while ( $exitedPid > 0 );

        my @remainingPids = keys %childPids;
        if ( $::DEBUG > 0 ) { print "updatePartitioningParallel: remainingPids=" . join(",",@remainingPids) . "\n"; }

        if ( $#remainingPids == -1 ) {
            $done = 1;
        }
    }
}

sub reorgParts($$$$$) {
    my ($dbh,$table, $r_fromPartitions, $r_toPartitions, $type) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("reorgParts r_fromPartitions", $r_fromPartitions, ", r_toPartitions", $r_toPartitions); }
    my @fromPartNames = ();
    foreach my $r_part ( @{$r_fromPartitions} ) {
        push @fromPartNames, $r_part->{'name'};
    }

    my @toPartDefs = ();
    foreach my $r_part ( @{$r_toPartitions} ) {
        push @toPartDefs, getPartitionDef($r_part->{'date'},$type);
    }

    updateTable($dbh,
                sprintf("ALTER TABLE $table REORGANIZE PARTITION\n\t%s\nINTO (\n\t%s\n)",
                        join(",\n\t",@fromPartNames),
                        join(",\n\t", @toPartDefs))
                );
}

sub removeDailyPartitions($$$$) {
    my ($dbh,$table,$time,$r_partList) = @_;

    if ( $::DEBUG > 6 ) { print Dumper("removeDailyPartitions table=$table, r_partList", $r_partList); }
    # Partition with be in descending date order
    # PMAXVALUE
    # P<START_(MONTH+2)>
    # P<START_(MONTH+1)>

    #
    # First, reorg PMAXVALUE and any daily partitions in the current month into
    # PMAXVALUE, P<START_(MONTH+2)>, P<START_(MONTH+1)>
    #
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time);
    my $dt = DateTime->new(year => $year + 1900, month => $mon +1, day => 1);
    $dt->add(months => 1);
    my $startMonthPlus1 = $dt->ymd('-');
    $dt->add(months => 1);
    my $startMonthPlus2 = $dt->ymd('-');
    if ( $::DEBUG > 4 ) { print "removeDailyPartitions: startMonthPlus1=$startMonthPlus1 startMonthPlus2=$startMonthPlus2\n"; }

    my @toList = (
        {
            'name' => getPartitionName($startMonthPlus1, $PARTITION_ADAPTIVE),
            'date' => $startMonthPlus1
        },
        {
            'name' => getPartitionName($startMonthPlus2, $PARTITION_ADAPTIVE),
            'date' => $startMonthPlus2
        },
        $r_partList->[$#{$r_partList}]
    );

    my @fromList = ($r_partList->[$#{$r_partList}]);

    my $partitionIndex = $#{$r_partList} - 1;
    my $firstMonthlyIndex = undef;
    for ( ; $partitionIndex >= 0 && (! defined $firstMonthlyIndex); $partitionIndex-- ) {
        if ( $r_partList->[$partitionIndex]->{'date'} =~ /-01$/ ) {
            $firstMonthlyIndex = $partitionIndex;
        } else {
            unshift @fromList, $r_partList->[$partitionIndex];
        }
    }

    reorgParts($dbh, $table, \@fromList, \@toList, $PARTITION_ADAPTIVE );

    # Now if there are any daily partitions in the previous month, then reorg
    # them into the previous monthly
    if ( ! defined $firstMonthlyIndex ) {
        @toList = ($r_partList->[$firstMonthlyIndex]);
        @fromList = ($r_partList->[$firstMonthlyIndex]);
        my $prevMonthlyIndex = undef;
        for ( $partitionIndex = $firstMonthlyIndex - 1; $partitionIndex >= 0 && (! defined $prevMonthlyIndex); $partitionIndex-- ) {
            if ( $r_partList->[$partitionIndex]->{'date'} =~ /-01$/ ) {
                $prevMonthlyIndex = $partitionIndex;
            } else {
                unshift @fromList, $r_partList->[$partitionIndex];
            }
        }

        if ( $#fromList > 0 ) {
            reorgParts($dbh, $table, \@fromList, \@toList, $PARTITION_ADAPTIVE );
        }
    }
}

sub updateMonthlyPartitions
{
    my ($dbh,$table,$time,$r_partList) = @_;

    if ( $::DEBUG > 3 ) { print Dumper("updateMonthlyPartitions table=$table r_partList", $r_partList); }


    # Partition with be in descending date order
    # PMAXVALUE
    # P<START_(MONTH+2)>
    # P<START_(MONTH+1)>

    #
    # First, reorg PMAXVALUE and any daily partitions in the current month into
    # PMAXVALUE, P<START_(MONTH+2)>, P<START_(MONTH+1)>
    #
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time);
    my $startMonthPlus2 = DateTime->new(year => $year + 1900, month => $mon +1, day => 1);
    $startMonthPlus2->add(months => 2);

    my ($pYear,$pMonth,$pDay) = split(/-/, $r_partList->[$#{$r_partList}-1]->{'date'});
    my $mostRecentDate = DateTime->new(year => $pYear, month => $pMonth, day => $pDay);

    if ( $::DEBUG > 4 ) { print "updateMonthlyPartitions: startMonthPlus2=$startMonthPlus2\n"; }

    if ( DateTime->compare($mostRecentDate, $startMonthPlus2) < 0) {
        my $date = $startMonthPlus2->ymd('-');
        my $r_newPart = {
            'name' => getPartitionName($date,$PARTITION_ADAPTIVE),
            'date' => $date
        };
        reorgParts($dbh,
                   $table,
                   [ $r_partList->[$#{$r_partList}] ],
                   [ $r_newPart, $r_partList->[$#{$r_partList}] ],
                   $PARTITION_ADAPTIVE );
    }

    return 0;
}

sub getNextQuarterStart($) {
    my ($time) = @_;

    my $result = undef;
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time);
    for (my $month = 0; ($month < 12) && (! defined $result) ; $month += 3 ) {
        my $qStart = timelocal(0,0,0,1,$month,$year);
        my $nextQYear = $year;
        my $nextQMonth = $month + 3;
        if ( $nextQMonth > 11 ) {
            $nextQMonth = 0;
            $nextQYear++;
        }
        my $nextQStart = timelocal(0,0,0,1,$nextQMonth,$nextQYear);

        if ( $time < $nextQStart ) {
            $result = sprintf("%04d-%02d-%02d", $nextQYear + 1900, $nextQMonth + 1, 1);
        }
    }

    if ( $::DEBUG > 3 ) { printf "getNextQuarterStart: time=%d %04d-%02d-%02d result = %s\n", $time,$year+1900,$mon+1,$mday,$result; }
    return $result;
}

sub migrateAdaptiveToQuarterly($$$$) {
    my ($dbh,$table,$time,$r_partList) = @_;

    # We want to end up with
    # QMAXVALUE
    # Q_START_NEXT_QUARTER
    # P_MONTHLY for anything older then the start of this quarter

    # SQL to rename PMAXVALUE partition
    # ALTER TABLE t REORGANIZE PARTITION PMAXVALUE INTO ( PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE );

    my $nextQuarterStart = getNextQuarterStart( $time + $DAY_IN_SEC );
    my ($year,$month,$day) = split(/-/,$nextQuarterStart);
    $month -= 3;
    if ( $month <= 0 ) {
        $year--;
        $month += 12;
    }
    my $thisQuarterStart = sprintf("%04d-%02d-%02d",$year,$month,$day);
    my $thisQuarterStartTime = parseTime($thisQuarterStart . " 00:00:00", $StatsTime::TIME_SQL);

    if ( $::DEBUG > 3 ) { print "migrateAdaptiveToQuarterly: table=$table nextq=$nextQuarterStart, thisq=$thisQuarterStart, thisQuarterStartTime=$thisQuarterStartTime\n"; }

    my @fromPartitions = ( $r_partList->[$#{$r_partList}] );
    for ( my $partIndex = $#{$r_partList} - 1; $partIndex > -1; $partIndex-- ) {
        my $partitionDate = $r_partList->[$partIndex]->{'date'} . " 00:00:00";
        my $partitionTime = parseTime($partitionDate, $StatsTime::TIME_SQL);
        if ( $::DEBUG > 3 ) { print "migrateAdaptiveToQuarterly: checking $partitionTime for $partitionDate\n"; }
        if ( $partitionTime > $thisQuarterStartTime ) {
            push @fromPartitions, $r_partList->[$partIndex];
        }
    }

    my @toPartitions = (
        { 'name' => getPartitionName($nextQuarterStart,$PARTITION_QUARTERLY), 'date' => $nextQuarterStart },
        $r_partList->[$#{$r_partList}]
    );

    reorgParts($dbh,
               $table,
               \@fromPartitions,
               \@toPartitions,
               $PARTITION_QUARTERLY );
}

sub migrateQuarterlyToAdpative($$$$) {
    my ($dbh,$table,$time,$r_partList) = @_;

    # We want to go from
    # PMAXVALUE,Q_START_NEXT_QUARTER to
    # PMAXVALUE,P_MONTH_AFTER_NEXT_QUARTER,Q_START_NEXT_QUARTER
    #

    my @fromPartitions = ( $r_partList->[$#{$r_partList}] );

    # Get the date for the next month after the most recent quarterly partition
    my ($qYear,$qMonth,$qDay) = split(/-/, $r_partList->[$#{$r_partList}-1]->{'date'});
    my $dt = DateTime->new(year => $qYear, month => $qMonth, day => $qDay);
    $dt->add(months => 1);
    my $monthStart = $dt->ymd('-');
    my @toPartitions = (
        { 'name' => getPartitionName($monthStart,$PARTITION_ADAPTIVE), 'date' =>  $monthStart},
        $r_partList->[$#{$r_partList}]
    );

    reorgParts($dbh,
               $table,
               \@fromPartitions,
               \@toPartitions,
               $PARTITION_ADAPTIVE );
}

sub updateQuarterlyPartitions($$$$) {
    my ($dbh,$table,$time,$r_partList) = @_;

    # Should have at least the QMAXVALUE and current Quarter partition
    if ( $#{$r_partList} < 1 ) {
        print "WARNING: $table has less then two partitions";
        return;
    }

    my $newestDataPartitionName = $r_partList->[$#{$r_partList}-1]->{'name'};
    if ( $::DEBUG > 3 ) { print"updateQuarterlyPartitions table=$table newestDataPartitionName=$newestDataPartitionName\n"; }

    # Now check for a table that's being migrated
    if ( $newestDataPartitionName =~ /^P/ ) {
        migrateAdaptiveToQuarterly($dbh,$table,$time,$r_partList);
    } else {
        my $nextQuarterStart = getNextQuarterStart( $time + $DAY_IN_SEC );

        if ( $r_partList->[$#{$r_partList}-1]->{'date'} ne $nextQuarterStart ) {
            my $r_newPart = {
                'name' => getPartitionName($nextQuarterStart,$PARTITION_QUARTERLY),
                'date' => $nextQuarterStart
            };
            reorgParts($dbh,
                       $table,
                       [ $r_partList->[$#{$r_partList}] ],
                       [ $r_newPart, $r_partList->[$#{$r_partList}] ],
                       $PARTITION_QUARTERLY );
        }
    }
}

sub updatePartitioning($$$) {
    my ($dbh,$table,$time) = @_;

    my $r_partList = getPartitionInfo($dbh,$table);
    ( $#{$r_partList} > 0 ) or die "$table is not partitioned";

    # Should have at least the MAXVALUE and one other partition
    if ( $#{$r_partList} < 1 ) {
        print "WARNING: $table has less then two partitions";
        return;
    }

    if ( $::DEBUG > 3 ) { printf "updatePartitioning table=%s, last partition=%s\n", $table, $r_partList->[$#{$r_partList}]->{'name'}; }
    if ( $r_partList->[$#{$r_partList}]->{'name'} =~ /^P/ ) {
        my $newestDataPartitionName = $r_partList->[$#{$r_partList}-1]->{'name'};

        my $hasDaily = 0;
        foreach my $r_partiton ( @{$r_partList} ) {
            if ( ($r_partiton->{'name'} ne 'PMAXVALUE') && ($r_partiton->{'date'} !~ /-01$/) ) {
                $hasDaily = 1;
            }
        }

        if ( $::DEBUG > 3 ) { print"updatePartitioning table=$table newestDataPartitionName=$newestDataPartitionName hasDaily=$hasDaily\n"; }
        if ( $newestDataPartitionName =~ /^Q/ ) {
            migrateQuarterlyToAdpative($dbh,$table,$time,$r_partList);
        } elsif ( $hasDaily ) {
            removeDailyPartitions($dbh,$table,$time,$r_partList);
        } else {
            updateMonthlyPartitions($dbh,$table,$time,$r_partList);
            if ( checkExit() ) { return 1; }
        }
    } else {
        updateQuarterlyPartitions($dbh,$table,$time,$r_partList);
    }

    if ( checkExit() ) { return 1; }
}

sub onlineMergeMonthly($$) {
    my ($dbh,$table) = @_;

    my $r_tableList = undef;
    if ( defined $table ) {
        $r_tableList = [ $table ];
    } else {
        $r_tableList = getPartitionedTables($dbh);
    }

    foreach my $partTable ( @{$r_tableList} ) {
        if ( $::DEBUG > 2 ) { print "onlineMergeMonthly: Checking $partTable\n"; }
        my $r_partList = getPartitionInfo($dbh,$partTable);
        my $monthPartIndex = $#{$r_partList} - $DAILY_PARTITION_COUNT - 12;
        my $partIndex = $monthPartIndex;
        while ( $partIndex >= 0 ) {
            if ( $::DEBUG > 3 ) {
                print "onlineMergeMonthly: partIndex=$partIndex partList[partIndex]->date=" .
                    $r_partList->[$partIndex]->{'date'} . "\n";
            }

            if ( $r_partList->[$partIndex]->{'date'} !~ /01-01$/ ) {
                my ($year,$month,$day) = $r_partList->[$partIndex]->{'date'} =~ /^(\d+)-(\d+)-(\d+)/;
                my $r_thisPart = $r_partList->[$partIndex];
                my $r_nextPart = $r_partList->[$partIndex + 1];

                # Create empty tables for the exchange
                my $thisPartTable = $partTable . "_" . $r_thisPart->{'name'};
                updateTable($dbh, "CREATE TABLE $thisPartTable LIKE $partTable");
                updateTable($dbh, "ALTER TABLE $thisPartTable REMOVE PARTITIONING");

                my $nextPartTable = $partTable . "_" . $r_nextPart->{'name'};
                updateTable($dbh, "CREATE TABLE $nextPartTable LIKE $partTable");
                updateTable($dbh, "ALTER TABLE $nextPartTable REMOVE PARTITIONING");

                # Swap the tables and partitions
                updateTable($dbh, "ALTER TABLE $partTable EXCHANGE PARTITION " . $r_thisPart->{'name'} . " WITH TABLE " . $thisPartTable);
                updateTable($dbh, "ALTER TABLE $partTable EXCHANGE PARTITION " . $r_nextPart->{'name'} . " WITH TABLE " . $nextPartTable);

                # Merge the partitions in the original table, should be fast as they are empty
                updateTable($dbh,
                            sprintf("ALTER TABLE $partTable REORGANIZE PARTITION %s,%s INTO ( %s )",
                                    $r_thisPart->{'name'},
                                    $r_nextPart->{'name'},
                                    getPartitionDef($r_nextPart->{'date'},$PARTITION_ADAPTIVE))
                    );

                # Copy all the data into the table for the next partition
                # into the table for this partition
                # This will be the slowest part
                updateTable($dbh, "INSERT INTO $thisPartTable SELECT * FROM $nextPartTable");

                # Swap the merged data in prevPartTable back into the orignal table into
                # this partition
                updateTable($dbh, "ALTER TABLE $partTable EXCHANGE PARTITION " . $r_nextPart->{'name'} . " WITH TABLE " . $thisPartTable);

                # Finally drop the exchange tables
                updateTable($dbh, "DROP TABLE $thisPartTable");
                updateTable($dbh, "DROP TABLE $nextPartTable");

                splice( @{$r_partList}, $partIndex, 1 );
            }

            $partIndex--;
        }
    }

    return 0;
}

sub updateTable
{
    my ($dbh,$sql) = @_;
    logMsg($sql);
    if ( $WRITE_MODE ) {
        dbDo($dbh, $sql) or die "Failed to uopdate table";
    }
    logMsg("Done");
}

sub partitionTables
{
    my ($dbh,$table,$time) = @_;

    my @tableList = ();
    if ( $table eq "auto" ) {
        my $r_toBePart = dbSelectAllArr($dbh, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.PARTITIONS WHERE PARTITION_METHOD IS NOT NULL AND TABLE_SCHEMA = (SELECT DATABASE()) GROUP BY TABLE_NAME HAVING COUNT(*) = 1");
        if ( $::DEBUG > 5 ) { print Dumper("partitionTables: r_toBePart", $r_toBePart); }
        foreach my $r_row ( @{$r_toBePart} ) {
            push @tableList, $r_row->[0];
        }
    } else {
        push @tableList, $table;
    }

    if ( $::DEBUG > 5 ) { print Dumper("partitionTables: tableList", \@tableList); }

    foreach my $oneTable ( @tableList ) {
        print "Performing initial partitioning of $oneTable\n";
        partitionTable($dbh,$oneTable,$time);
        if ( checkExit() ) { return 1; }
    }
}

sub partitionTableAdaptive($$$$) {
    my ($dbh,$table,$time,$partitionColumn) = @_;

    # Partition with be in descending date order
    # PMAXVALUE
    # P<START_(MONTH+2)>
    # P<START_(MONTH+1)>
    # P<START_(MONTH+0)>

    my @toListDates = ();

    my $oneMonth = DateTime::Duration->new(months => 1);
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time);
    my $startMonth = DateTime->new(year => $year + 1900, month => $mon +1, day => 1);

    # If we have data in the table make sure we add a partition older then this
    # so the oldest partition is always empty
    my $oldestYear = getMinYearInTable($dbh,$table,$partitionColumn);
    if ( defined $oldestYear ) {
        my $oldestMonth = DateTime->new(year => $oldestYear, month => 1, day => 1);;
        my $oldMonth = $startMonth->clone();
        while ( DateTime->compare($oldMonth, $oldestMonth) >= 0 ) {
            unshift @toListDates, $oldMonth;
            $oldMonth = $oldMonth - $oneMonth;
        }
    } else {
        push @toListDates, $startMonth - $oneMonth;
        push @toListDates, $startMonth;
    }

    push @toListDates, $startMonth + $oneMonth;
    push @toListDates, $startMonth + $oneMonth + $oneMonth;


    my %pmaxPartition = (
        'name' => 'PMAXVALUE',
        'date' => '0000-00-00'
    );

    my @toList = ();
    foreach my $dt ( @toListDates ) {
        my $ymd = $dt->ymd('-');
        push @toList, {
            'name' => getPartitionName($ymd, $PARTITION_ADAPTIVE),
            'date' => $ymd
        }
    }
    push @toList, \%pmaxPartition;

    reorgParts($dbh, $table, [ \%pmaxPartition ], \@toList, $PARTITION_ADAPTIVE);
}

sub partitionTableQuarterly($$$$) {
    my ($dbh,$table,$time,$partitionColumn) = @_;

    my $nextQuarterStart = getNextQuarterStart($time + $DAY_IN_SEC);

    my @partitionDates = ();

    # Figure out the years
    my $year = getMinYearInTable($dbh,$table,$partitionColumn);
    if ( defined $year ) {
        my $month = 1;
        my $quarter = undef;
        do {
            $quarter = sprintf("%04d-%02d-%02d", $year, $month, 1);
            if ( $quarter ne $nextQuarterStart ) {
                push @partitionDates, $quarter;
                $month += 3;
                if ( $month > 12 ) {
                    $month = 1;
                    $year++;
                }
            }
        } while ( $quarter ne $nextQuarterStart );
    } else {
        # If the table is empty, we need to add a partiton so that the oldest
        # partiton is always empty
        my ($qYear,$qMonth,$qDay) = split(/-/, $nextQuarterStart);
        my $dt = DateTime->new(year => $qYear, month => $qMonth, day => $qDay);
        $dt->subtract(months => 3);
        push @partitionDates, $dt->ymd('-');
    }
    push @partitionDates, $nextQuarterStart;

    if ( $::DEBUG > 3 ) { print Dumper("partitionDates after year", \@partitionDates ); }

    my $sql = "ALTER TABLE $table REORGANIZE PARTITION QMAXVALUE INTO\n(\n";
    foreach my $partitionDate ( @partitionDates )
    {
        $sql .= sprintf("\t%s,\n", getPartitionDef($partitionDate, $PARTITION_QUARTERLY));
    }
    $sql .= "\t" . $QMAX_DEF .  "\n)";
    updateTable($dbh, $sql) or die "Failed to partition $table";
}

sub partitionTable($$$) {
    my ($dbh,$table,$time) = @_;

    my $r_partList = getPartitionInfo($dbh,$table);
    # If there is not one partition in the table then we have
    # already partitioned the table or the table is not setup for
    # partitioning
    if ( $#{$r_partList} != 0 )
    {
        die "$table is cannot be partitioned";
    }

    my $type = $PARTITION_ADAPTIVE;
    if ( $r_partList->[0]->{'name'} eq 'QMAXVALUE' ) {
        $type = $PARTITION_QUARTERLY;
    }

    my $partitionColumn = getPartitionColumn($dbh,$table,$type);

    # Ensure that there is no invalid values in the time columns
    dbDo($dbh,"DELETE FROM $table WHERE $partitionColumn < '2021-01-01'");

    if ( $type == $PARTITION_ADAPTIVE ) {
        partitionTableAdaptive($dbh,$table,$time,$partitionColumn);
    } elsif ( $type == $PARTITION_QUARTERLY ) {
        partitionTableQuarterly($dbh,$table,$time,$partitionColumn);
    }
}

sub getDate
{
    my ($time) = @_;
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time);
    return sprintf "%04d-%02d-%02d", ($year + 1900), $mon + 1, $mday;
}

sub getMinYearInTable
{
    my ($dbh,$table,$column) = @_;

    my $result;

    my $sql = "SELECT YEAR(MIN($column)) AS theyear FROM $table";
    if ( $::DEBUG > 2 ) { print "getMinYearInTable: sql=$sql\n"; }
    my $sth = $dbh->prepare($sql)
        or die "Couldn't prepare statement: " . $dbh->errstr;
    $sth->execute()             # Execute the query
        or die "Couldn't execute statement: " . $sth->errstr;
    my @row = $sth->fetchrow_array();
    if (  @row ) {
        $result = $row[0];
    }
    $sth->finish();

    if ( $::DEBUG > 5 ) { printf "getMinYearInTable result=%s\n", (defined $result ? $result : "null"); }

    return $result;
}

sub getYearStarts
{
    my ($lastMonthDate,$minYear) = @_;

    my ($year,$month,$day) = $lastMonthDate =~ /^(\d+)-(\d+)-(\d+)/;
    if ( $month == 1 ) {
        $year--;
    }

    if ( $::DEBUG > 5 ) { print "getYearStarts: lastMonthDate minYear=$minYear year=$year\n"; }

    # In the case where the last 12 months takes us into a year less then min year
    if ( $year < $minYear ) {
        $minYear = $year;
    }

    my @yearStarts = ();
    for ( ; $year >= $minYear; $year-- )
    {
        my $yearStart = sprintf("%04d-01-01",$year);
        if ( $::DEBUG > 8 ) { print "getYearStarts: $yearStart\n"; }
        push @yearStarts, $yearStart;
    }

    return \@yearStarts;
}

sub getLast12Months
{
    my ($date) = @_;

    my ($year,$mon,$day) = $date =~ /^(\d+)-(\d+)-(\d+)/;
    if ( $day == 1 ) {
        $mon--;
        if ( $mon < 1 ) {
            $mon = 12;
            $year--;
        }
    }
    if ( $::DEBUG > 8 ) { print "getLast12Months: date=$date starting with year=$year mon=$mon\n"; }

    my @monthStarts = ();
    for ( my $i = 1; $i <= 12; $i++ )
    {
        my $monthStart = sprintf("%04d-%02d-01", $year, $mon);
        if ( $::DEBUG > 8 ) { print "getLast12Months: added $monthStart\n"; }
        push @monthStarts, $monthStart;

        $mon--;
        if ( $mon < 1 )
        {
            $mon = 12;
            $year--;
        }
    }

    return \@monthStarts;
}

sub getTableSizes($) {
    my ($dbh) = @_;

    my $sth = $dbh->prepare('
SELECT TABLE_NAME, DATA_LENGTH
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = (SELECT DATABASE())
') or die "Failed to prepare query to get table sizes: " . $dbh->errstr;
    $sth->execute() or die "Failed to execute query: " . $dbh->errstr;

    my %tableSize = ();
    while ( my @row = $sth->fetchrow_array() ) {
        if ( $::DEBUG > 8 ) { print "getTableSizes table=$row[0] size=$row[1]\n"; }
        $tableSize{$row[0]} = $row[1];
    }
    $sth->finish();

    if ( $::DEBUG > 8 ) { print Dumper("getTableSizes tableSize", \%tableSize ); }

    return \%tableSize;
}


sub getPartitionedTables
{
    my ($dbh) = @_;

    my $sth = $dbh->prepare('
SELECT TABLE_NAME, COUNT(*)
FROM INFORMATION_SCHEMA.PARTITIONS
WHERE TABLE_SCHEMA = (SELECT DATABASE()) AND
PARTITION_NAME IS NOT NULL GROUP BY TABLE_NAME
') or die "Failed to prepare query to get partited tables: " . $dbh->errstr;

    if ( $::DEBUG > 3 ) { print "getPartitionedTables: executing query\n"; }
    $sth->execute() or die "Failed to execute query: " . $dbh->errstr;
    my @tableList = ();
    while ( my @row = $sth->fetchrow_array() )
    {
        if ( $::DEBUG > 8 ) { print "getPartitionedTables table=$row[0] partitionCount=$row[1]\n"; }
        if ( $row[1] > 1 )
        {
            push @tableList, $row[0];
        }
    }
    $sth->finish();

    if ( $::DEBUG > 8 ) { print Dumper("getPartitionedTables tableList", \@tableList ); }

    return \@tableList;
}

sub getPartitionInfo
{
    my ($dbh,$table) = @_;

    my $sth = $dbh->prepare('
SELECT PARTITION_NAME, PARTITION_ORDINAL_POSITION, FROM_DAYS(PARTITION_DESCRIPTION), TABLE_ROWS, PARTITION_COMMENT
FROM INFORMATION_SCHEMA.PARTITIONS
WHERE TABLE_SCHEMA = (SELECT DATABASE()) AND
PARTITION_NAME IS NOT NULL AND
TABLE_NAME = ?') or die "Failed to prepare query to get partition list for $table: " . $dbh->errstr;
    $sth->execute($table) or die "Failed to execute query for $table: " . $dbh->errstr;

    my @partInfo = ();
    while ( my @row = $sth->fetchrow_array() )
    {
        my $r_partition = {
            'name'    => $row[0],
            'num'     => $row[1],
            'date'    => $row[2],
            'rows'    => $row[3],
            'comment' => $row[4]
        };
        push @partInfo, $r_partition;
    }
    $sth->finish();

    if ( $::DEBUG > 8 ) { print Dumper("getPartitionInfo table=$table partInfo", \@partInfo ); }
    return \@partInfo;
}

sub ensureEmpty($$$) {
    my ($dbh, $partTable, $r_partition) = @_;

    if ($r_partition->{'rows'} > 0) {
        logMsg(sprintf("WARNING: First parition %s in table %s has %d rows", $r_partition->{'name'}, $partTable, $r_partition->{'rows'}));
        my $sql = sprintf("ALTER TABLE %s TRUNCATE PARTITION %s", $partTable, $r_partition->{'name'});
        updateTable($dbh,$sql);
    }
}

sub getDeleteTime($$$) {
    my ($table, $baseTime, $r_config) = @_;

    my $months = $r_config->{'default'}->{'normal'};
    if ( exists $r_config->{$table} ) {
        $months = $r_config->{$table};
    }

    my $dt = DateTime->from_epoch( epoch => $baseTime );
    $dt->add( months => 0 - $months );
    if ( $::DEBUG > 5) { printf("getDeleteTime: table=%s dt=%s\n", $table, $dt->ymd('-'));}
    return $dt->epoch();
}

sub deleteOldPartitions($$$$) {
    my ($dbh,$baseTime,$table,$r_configFiles) = @_;

    my $r_config = readConfigFiles($r_configFiles);

    my $r_tableList = undef;
    if ( defined $table ) {
        $r_tableList = [ $table ];
    } else {
        $r_tableList = getPartitionedTables($dbh);
    }

    foreach my $partTable ( @{$r_tableList} )
    {
        my $r_partitionList = getPartitionInfo($dbh,$partTable);

        my $deleteTime = getDeleteTime($partTable, $baseTime, $r_config);
        my $droppedRows = 0;
        my $totalRows = 0;
        my @partitionsToDelete = ();
        foreach my $r_partition ( @{$r_partitionList} ) {
            if ( $::DEBUG > 5 ) { print Dumper("deleteOldPartitions: checking partition", $r_partition); }
            $totalRows += $r_partition->{'rows'};
            my ($year,$month,$day) = $r_partition->{'date'} =~ /^(\d{4,4})-(\d{2,2})-(\d{2,2})/;
            if ( $year > 0 ) { # MAXVALUE partition has a date of 0000-00-00
                my $partitionTime = timelocal(0,0,0,$day,$month-1,$year-1900);
                if ( $partitionTime < $deleteTime ) {
                    push @partitionsToDelete, $r_partition;
                    $droppedRows += $r_partition->{'rows'};
                }
            }
        }

        # Make sure partitions are sorted oldest to newest
        my @partitionNamesToDelete = ();
        foreach my $r_partition ( sort { $a->{'date'} cmp $b->{'date'} } @partitionsToDelete ) {
            push @partitionNamesToDelete, $r_partition->{'name'};
        }

        if ( $::DEBUG > 4 ) { print "deleteOldPartitions: $partTable = [", join(",",@partitionNamesToDelete),"]\n"; }

        if ( $#partitionNamesToDelete > -1 ) {
            logMsg(sprintf("Removing %d rows in %d partitions from %s (which has %d total rows)", $droppedRows, ($#partitionNamesToDelete+1), $partTable, $totalRows));

            # If there are N partitions older then the delete time
            # we want to drop N-1 partions and TRUNCATE the Nth
            # This way, the date of the "oldest" partition moves over time
            # which speeds up queries that are looking for really old data
            # like (like in FixProcNames) because the optimizer will select
            # only the oldest partition and that will always be empty
            my $nthPartition = pop @partitionNamesToDelete;
            updateTable($dbh, "ALTER TABLE $partTable TRUNCATE PARTITION $nthPartition");

            if ( $#partitionNamesToDelete > -1 ) {
                my $nMinus1Partitions = join(",",@partitionNamesToDelete);
                updateTable($dbh, "ALTER TABLE $partTable DROP PARTITION $nMinus1Partitions");
            }
        }
    }

}

sub getColumns($$) {
    my ($dbh,$table) = @_;

    my $r_rows =
        dbSelectAllArr($dbh,
                       "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table' AND table_schema = DATABASE()")
        or die "Failed to get columns for $table";
    my @columns = ();
    foreach my $r_row ( @{$r_rows} ) {
        push @columns, $r_row->[0];
    }
    return \@columns;
}

sub createAggTable($$$) {
    my ($dbh, $sourceTableName, $aggTableName) = @_;

    my $schemaQuery = <<ESQL;
SELECT
 COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE
  table_name = '$sourceTableName' AND table_schema = DATABASE()
ORDER BY ORDINAL_POSITION
ESQL

    my $r_tableColumns = dbSelectAllHash($dbh,$schemaQuery) or die "Failed to get schema for $sourceTableName";
    my @columnLines = ();
    foreach my $r_tableColumn ( @{$r_tableColumns} ) {
        my $notNull = " NOT NULL";
        if ( $r_tableColumn->{'IS_NULLABLE'} eq 'YES' ) {
            $notNull = "";
        }
        push @columnLines, sprintf(" %s %s%s",
                                   $r_tableColumn->{'COLUMN_NAME'},
                                   $r_tableColumn->{'COLUMN_TYPE'},
                                   $notNull);
    }

    my $createTable = "CREATE TEMPORARY TABLE $aggTableName (\n" . join(",\n", @columnLines) . "\n)";
    #my $createTable = "CREATE TABLE $aggTableName (\n" . join(",\n", @columnLines) . "\n)";
    dbDo( $dbh, $createTable ) or die "Failed to create aggregation table";
}

sub aggregatePartition($$$$$$) {
    my ($dbh, $r_table, $r_partitionInfo, $partitionTime, $prevPartionTime, $aggInterval) = @_;

    my $aggTableName = "_agg_" . $r_table->{'name'} . '_' . $r_partitionInfo->{'name'};
    createAggTable($dbh, $r_table->{'name'}, $aggTableName);

    # Generate aggregated data into the temporary agg table
    my @insertCols = ($r_table->{'timecol'});
    my $aggTime = sprintf("FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(%s)/%d)* %d)",
                          $r_table->{'timecol'}, $AGG_INTERVAL{$aggInterval}, $AGG_INTERVAL{$aggInterval});

    my @selectCols = ( "$aggTime AS " . $r_table->{'timecol'} . "_agg" );
    my @groupBy = ( $r_table->{'timecol'} . "_agg" );
    foreach my $column ( @{$r_table->{'groupby'}} ) {
        push @insertCols, $column;
        push @selectCols, $column;
        push @groupBy, $column;
    }
    foreach my $r_aggColumnDef ( @{$r_table->{'agg_columns'}} ) {
        push @insertCols, $r_aggColumnDef->{'name'};
        push @selectCols, sprintf("%s(%s) AS %s", $r_aggColumnDef->{'aggregation'}, $r_aggColumnDef->{'name'}, $r_aggColumnDef->{'name'});
    }

    #
    # We write the aggregated data out a bcp file and load it back in. The purpose of this is to avoid
    # having the replication servers execute the aggreation query(as this would cause a significant delay
    # in replication)
    #
    my $bcpFile = "/data/db/var/" . $aggTableName . ".bcp"; #getBcpFileName($aggTableName);

    my $aggSql = sprintf("SELECT %s INTO OUTFILE '%s' FROM %s WHERE %s >= '%s' AND %s < '%s' GROUP BY %s",
                         join(",", @selectCols),
                         $bcpFile,
                         $r_table->{'name'},
                         $r_table->{'timecol'},
                         formatTime($prevPartionTime, $StatsTime::TIME_SQL),
                         $r_table->{'timecol'},
                         formatTime($partitionTime, $StatsTime::TIME_SQL),
                         join(",", @groupBy)
                     );
    my $aggStart = Time::HiRes::time;
    dbDo($dbh, $aggSql) or die "Failed to save aggregated data";

    my $loadStart = Time::HiRes::time;
    my $loadSql = sprintf("LOAD DATA INFILE '%s' INTO TABLE %s (%s)",
                          $bcpFile,
                          $aggTableName,
                          join(",", @insertCols)
                      );
    dbDo($dbh, $loadSql) or die "Failed to load aggregated data";

    # Replace raw data in partition with aggregated data
    dbDo($dbh, sprintf("ALTER TABLE %s TRUNCATE PARTITION %s", $r_table->{'name'}, $r_partitionInfo->{'name'}))
        or die "Failed to TRUNCATE " . $r_partitionInfo->{'name'} . " of " . $r_table->{'name'};
    my $copyStart = Time::HiRes::time;
    dbDo($dbh, sprintf("INSERT INTO %s SELECT * FROM %s",
                       $r_table->{'name'},
                       $aggTableName))
        or die "Failed to copy agg data from $aggTableName to " . $r_table->{'name'};
    my $copyEnd = Time::HiRes::time;

    dbDo($dbh, "DROP TABLE $aggTableName") or
        die "Failed to drop agg table $aggTableName";
    unlink($bcpFile);

    logMsg(sprintf("  Aggregation Save: %.1f, Aggregation Load: %.1f Copy: %.1f", $loadStart - $aggStart, $copyStart - $loadStart, $copyEnd - $copyStart));
}

sub aggregateTable($$$) {
    my ($dbh, $r_table, $r_defaultThresholds) = @_;

    my $r_thresholds = undef;
    if ( exists $r_table->{'thresholds'} ) {
        $r_thresholds = $r_table->{'thresholds'};
    } else {
        $r_thresholds = $r_defaultThresholds;
    }

    my %knownColumns = ( $r_table->{'timecol'} => 1 );
    foreach my $column ( @{$r_table->{'groupby'}} ) {
        $knownColumns{$column} = 1;
    }
    foreach my $r_aggColumnDef ( @{$r_table->{'agg_columns'}} ) {
        $knownColumns{$r_aggColumnDef->{'name'}} = 1;
    }

    my $r_tableColumns = getColumns($dbh,$r_table->{'name'});
    foreach my $column ( @{$r_tableColumns} ) {
        exists $knownColumns{$column} or die "Unknown column $column in " . $r_table->{'name'};
    }

    my $r_partitionList = getPartitionInfo($dbh, $r_table->{'name'});
    if ( $::DEBUG > 1 ) { printf "aggregateTable: %s has %d partitions\n", $r_table->{'name'}, $#{$r_partitionList} + 1; }
    # Need a min of 3 partitions
    if ( $#{$r_partitionList} <= 2 ) {
        return;
    }

    my $prevPartionTime =  parseTime($r_partitionList->[0]->{'date'} . " 00:00:00", $StatsTime::TIME_SQL);
    # Remove the oldest partition
    shift @{$r_partitionList};
    # Remove the newest partition, MAXVALUE
    pop @{$r_partitionList};

    my $r_rows = dbSelectAllArr($dbh, sprintf("SELECT partitionname, agginterval FROM partition_agg WHERE tablename = '%s'",$r_table->{'name'}))
        or die "Failed to partition_agg info";
    my %aggPartitions = ();
    foreach my $r_row ( @{$r_rows} ) {
        $aggPartitions{$r_row->[0]} = $r_row->[1];
    }

    my $aggPerformed = 0;
    foreach my $r_partitionInfo ( @{$r_partitionList} ) {
        if ( checkExit() ) {
            return;
        }

        # Loop through the thresholds, if the partition is older then the threshold
        # then is should have the aggregation interval specified by the threshold
        my $requiredAgg = '';
        my $pTime = parseTime($r_partitionInfo->{'date'} . " 00:00:00", $StatsTime::TIME_SQL);
        if ( $::DEBUG > 0 ) {
            printf "aggregateTable: partition=%s date=%s pTime=%d\n",
                $r_partitionInfo->{'name'},
                $r_partitionInfo->{'date'},
                $pTime;
        }

        foreach my $r_threshold ( @{$r_thresholds} ) {
            if ( $::DEBUG > 0 ) { printf "aggregateTable: %s %s\n", $r_threshold->{'interval'}, $r_threshold->{'time'}; }
            if ( $pTime <= $r_threshold->{'time'} ) {
                $requiredAgg = $r_threshold->{'interval'};
                last;
            }
        }

        my $existingAgg = $aggPartitions{$r_partitionInfo->{'name'}};
        if ( ! defined $existingAgg ) {
            $existingAgg = '';
        }

        if ( $::DEBUG > 0 ) {
            printf "aggregateTable: %s partition=%s date=%s rows=%d existingAgg=%s requiredAgg=%s\n",
                $r_table->{'name'}, $r_partitionInfo->{'name'}, $r_partitionInfo->{'date'},
                $r_partitionInfo->{'rows'},
                $existingAgg, $requiredAgg;
        }

        if ( $existingAgg ne $requiredAgg ) {
            if ( $aggPerformed == 0 ) {
                logMsg($r_table->{'name'});
            }
            logMsg(" " . $r_partitionInfo->{'name'} . " $requiredAgg " . $r_partitionInfo->{'rows'});
            aggregatePartition($dbh, $r_table, $r_partitionInfo, $pTime, $prevPartionTime, $requiredAgg);
            dbDo($dbh, sprintf("DELETE FROM partition_agg WHERE tablename = '%s' AND partitionname = '%s'", $r_table->{'name'}, $r_partitionInfo->{'name'}))
                or die "Failed to remove entry partition_agg";
            dbDo($dbh, sprintf("INSERT INTO partition_agg (tablename,partitionname,agginterval) VALUES ('%s', '%s', '%s' )", $r_table->{'name'}, $r_partitionInfo->{'name'}, $requiredAgg))
                or die "Failed to add entry to partition_agg";
            $aggPerformed++;
        }

        $prevPartionTime = $pTime;
    }

    if ( $aggPerformed ) {
        logMsg(" Aggregated " . $aggPerformed . " partition(s)");
    }
}

sub processThresholds($$) {
    my ($time, $r_thresholds) = @_;

    foreach my $r_threshold ( @{$r_thresholds} ) {
        my $aggregationAgeMonths = $r_threshold->{'age'};
        my $aggThreshold = DateTime->from_epoch( epoch => $time )->truncate( to => 'month' );
        $aggThreshold->subtract( months => $aggregationAgeMonths );
        logMsg("Aggregation threshold for " . $r_threshold->{'interval'} . ' = ' . $aggThreshold->datetime . "(" . $aggThreshold->epoch() . ")");
        $r_threshold->{'time'} = $aggThreshold->epoch();

        if ( ! exists $AGG_INTERVAL{$r_threshold->{'interval'}} ) {
            print "ERROR: Unknown aggregation interval $r_threshold->{'interval'}\n";
            exit 1;
        }
    }
}

sub aggregateTables($$$) {
    my ($dbh,$time,$r_configFiles) = @_;

    my $r_config = readConfigFiles($r_configFiles);

    # Validate thresholds
    processThresholds($time, $r_config->{'thresholds'});
    foreach my $r_table ( @{$r_config->{'tables'}} ) {
        if ( exists $r_table->{'thresholds'} ) {
            logMsg("Process threshold for " . $r_table->{'name'});
            processThresholds($time, $r_table->{'thresholds'});
        }
    }

    foreach my $r_table ( @{$r_config->{'tables'}} ) {
        if ( checkExit() ) {
            return;
        }
        aggregateTable($dbh,$r_table,$r_config->{'thresholds'});
    }
}

sub readConfigFiles($) {
    my ($r_configFiles) = @_;

    my %config = ();
    foreach my $r_configFile ( @{$r_configFiles} ) {
        my $r_config = readConfigFile($r_configFile);
        while ( my ($name,$value) = each %{$r_config} ) {
            $config{$name} = $value;
        }
    }

    while ( my ($name,$value) = each %{$config{'default'}} ) {
        if ( exists $config{$name} ) {
            foreach my $tableName ( @{$config{$name}} ) {
                if ( ! exists $config{$tableName} ) {
                    $config{$tableName} = $value;
                }
            }
        }
    }

    return \%config;
}

sub readConfigFile($) {
    my ($configFile) = @_;

    my $json;
    {
        local $/; #Enable 'slurp' mode
        open my $fh, "<", $configFile;
        $json = <$fh>;
        close $fh;
    }
    my $r_config = decode_json($json);

    return $r_config;
}

sub main() {
    my @configFiles = ();
    my ($action,$table,$date,$test,$parallel,$db);
    my $result = GetOptions('action=s' => \$action,
                            "table=s"  => \$table,
                            "date=s"   => \$date,
                            "test", => \$test,
                            "exit=s"      => \$EXIT_FILE,
                            "parallel=s" => \$parallel,
                            "config=s" => \@configFiles,
                            "db=s" => \$db,
                            "debug=s"  => \$::DEBUG
                            );
    ($result == 1) or die "Invalid args";

    if ( $test ) {
        logMsg("Test Mode: No updates will be applied");
        $WRITE_MODE = 0;
    }

    setStatsDB_Debug($::DEBUG);
    my $dbh = connect_db();

    if ( defined $db ) {
        dbDo($dbh, "use $db") or die "Failed to switch to $db";
    }

    my $time = time();
    if ( defined $date )
    {
        my ($year,$month,$mday) = $date =~ /^(\d{4,4})-(\d{2,2})-(\d{2,2})$/;
        $time = timelocal(0,0,0,$mday,$month-1,$year-1900);
    }

    if ( $action eq 'initial' )
    {
        partitionTables($dbh,$table,$time);
    }
    elsif ( $action eq 'update' )
    {
        if ( defined $parallel && $parallel > 0 ) {
            updatePartitioningParallel($dbh,$time,$parallel);
        } else {
            updatePartitions($dbh,$table,$time);
        }
    } elsif ( $action eq 'mergemonthly' ) {
        onlineMergeMonthly($dbh,$table);
    } elsif ( $action eq 'deleteold' ) {
        deleteOldPartitions($dbh,$time,$table,\@configFiles);
    } elsif ( $action eq 'aggregate' ) {
        aggregateTables($dbh,$time,\@configFiles);
    }


    $dbh->disconnect();

    if ( checkExit() ) {
        return 1;
    } else {
        return 0;
    }
}

1;