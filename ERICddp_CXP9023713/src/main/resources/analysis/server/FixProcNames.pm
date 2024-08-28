package FixProcNames;

use warnings;
use strict;

use DBI;
use Time::HiRes qw(gettimeofday tv_interval);
use Data::Dumper;

use StatsDB;
use StatsCommon;
use StatsTime;

our $doUpdate = 0;
our $EXIT_FILE;
our $EXIT_FILE_FOUND = 0;
our $FIX_MADE = 0;

our $CLEANUP_OLD_MAX_COUNT = 100; # proc_id must be used less then this number of times;
our $CLEANUP_OLD_MIN_DAYS = 365;  # proc_id must be last used older then this number of days ago

our $MAX_REMAP = 500000;

sub checkExit
{
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

sub logMsg($) {
    my ($msg) = @_;
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime();
    printf "%02d-%02d-%02d:%02d:%02d:%02d %s\n", ($year-100),$mon+1,$mday, $hour, $min, $sec, $msg;
}

sub analyseCommandNames($) {
    my ($dbh) = @_;

    #
    # Set some of the remapping vars
    #
    my $host = "NO_MATCH";
    my $omc = "NO_MATCH";

    #
    # Get existing list
    #
    logMsg("Fetching Existing List");
    my @procNameList = ();
    my $r_procIdMap = getIdMap($dbh, "process_names", "id", "name", \@procNameList );

    #
    # Look for commands that could be shortened
    #
    logMsg("Analyse Existing Commands in List");
    my %remapExisting = ();
    my %remapNew = ();
    my $checked = 0;
    foreach my $cmd ( keys %{$r_procIdMap} ) {
        if ( $::DEBUG > 1 ) { print "analyseCommandNames: cmd=$cmd\n"; }
        $checked++;
        my $shortCmd;
        if ( $cmd =~ /^\d+\s+/ ) {
            my @cmdParts = split(/\s+/,$cmd);
            while ( $cmdParts[0] =~ /^[\d\.:-]+$/ || $cmdParts[0] eq '[OMC]' ) {
                my $part = shift @cmdParts;
                if ( $::DEBUG > 2 ) { print "analyseCommandNames:: removing $part\n"; }
            }
            if ( $#cmdParts > -1 ) {
                my $newCmd = join(" ", @cmdParts);
                if ( $::DEBUG > 1 ) { print "analyseCommandNames:: checked=$checked corrupt cmd $cmd, newCmd=$newCmd\n"; }
                if ( $newCmd ) {
                    $shortCmd = jpsShortName($newCmd, $host, $omc);
                }
            } else {
                $shortCmd = "[INVALID_CMD]";
            }
        } elsif ( $cmd =~ /\(\S+\) CMD \(/ ) {
            # Remove entries from faulty cron logs
            # (netsim) CMD (/netsim_users/hc/bin/genstat_report.sh -p true >> /netsim_users/pms/logs/periodic_healthcheck.log 2>&1
            $shortCmd = "[CORRUPT_CRON]";
        } else {
            $shortCmd = jpsShortName($cmd, $host, $omc);
        }

        if ( $shortCmd ne $cmd )
        {
            if ( exists $r_procIdMap->{$shortCmd} )
            {
                if ( $::DEBUG > 2 ) { print "analyseCommandNames:: checked=$checked remapping cmd=$cmd to existing shortName=$shortCmd\n"; }
                $remapExisting{$cmd} = $shortCmd;
            }
            else
            {
                if ( $::DEBUG > 2 ) { print "analyseCommandNames:: checked=$checked remapping cmd=$cmd to new shortName=$shortCmd\n"; }
                $remapNew{$cmd} = $shortCmd;
            }
        }
    }

    if ( $::DEBUG > 2 ) { print Dumper("analyseCommandNames: cmds to remap to existing", \%remapExisting); }
    if ( $::DEBUG > 2 ) {
        my $fromCount = scalar(keys %remapNew);
        my %hashTemp = map { $_ => 1 } values %remapNew;
        my $toCount = scalar(keys %hashTemp);
        print Dumper("analyseCommandNames: $fromCount cmds to remap to $toCount new", \%remapNew);
    }

    return ( \%remapExisting, \%remapNew );
}

sub remapOneId($$$$$$$) {
    my ($dbh, $table, $tcol, $fromId, $toId, $fromSth, $toSth) = @_;

    $dbh->begin_work();

    my $t0 = [gettimeofday()];
    $fromSth->execute($fromId)
        or die "Couldn't execute statement: " . $fromSth->errstr;
    my $t1 = [gettimeofday()];

    my $updateInPlace  = 0;
    my $existsCount    = 0;
    my $updateExisting = 0;
    while ( my $r_fromRow = $fromSth->fetchrow_hashref() ) {
        if ( $::DEBUG > 4 ) { print Dumper("remapOneId: fromRow", $r_fromRow); }

        foreach my $key ( $tcol, 'serverid' ) {
            if ( ! defined $r_fromRow->{$key} ) {
                print Dumper("Problem fromRow for $fromId", $r_fromRow);
                die "faulty row";
            }
        }

        $toSth->execute($r_fromRow->{$tcol}, $r_fromRow->{'serverid'}, $toId)
            or die "Couldn't execute statement: " . $toSth->errstr;
        if ( my $r_toRow = $toSth->fetchrow_hashref() ) {
            $existsCount++;
            if ( $::DEBUG > 4 ) { print Dumper("remapOneId: checking if toRow need to be updated", $r_toRow); }

            my @setCols = ();
            if ( defined $r_fromRow->{'cpu'} && $r_fromRow->{'cpu'} > 0 ){
                push @setCols, sprintf("cpu = %d", ($r_fromRow->{'cpu'} +  $r_toRow->{'cpu'}));
            }

            foreach my $fieldName ( 'mem', 'thr', 'fd', 'rss' ) {
                if ( defined $r_fromRow->{$fieldName} && ($r_fromRow->{$fieldName} > $r_toRow->{$fieldName}) ) {
                    push @setCols, sprintf("%s = %d", $fieldName, $r_fromRow->{$fieldName});
                }
            }

            if ( @setCols ) {
                $updateExisting++;
                my $sql = sprintf("UPDATE %s SET %s WHERE %s = '%s' AND serverid = %d AND procid = %d",
                                  $table, join(",", @setCols), $tcol, $r_fromRow->{$tcol}, $r_fromRow->{'serverid'}, $toId );
                if ( $doUpdate ) { dbDo($dbh,$sql); }
            }
        } else {
            $updateInPlace++;
            my $sql = sprintf("UPDATE %s SET procid = %d WHERE %s = '%s' AND serverid = %d AND procid = %d",
                              $table,
                              $toId,
                              $tcol,
                              $r_fromRow->{$tcol}, $r_fromRow->{'serverid'},
                              $fromId );
            if ( $doUpdate ) { dbDo($dbh,$sql); }
        }
    }

    $dbh->commit();

    # If we did just update all the procid, then we still have rows with the old
    # procid which need to be deleted
    if ( $::DEBUG > 3 ) { print "remapOneId: existsCount=$existsCount\n"; }
    if ( $existsCount > 0 ) {
        my $sql = sprintf("DELETE FROM %s WHERE procid = %d", $table, $fromId );
        if ( $doUpdate ) { dbDo($dbh,$sql); }
    }

    return ($updateInPlace, $existsCount, $updateExisting);
}

sub doRemap($$$) {
    my ($r_remapCmds, $dbh, $checkCounts) = @_;

    my @fromList = keys %{$r_remapCmds};
    if ( $::DEBUG > 1 ) { print "doRemap: num procs to be mapped = ", ($#fromList + 1), "\n"; }
    if ( $#fromList > -1 )
    {
        $FIX_MADE = 1;
    }

    #
    # Make sure the shorten cmds are in process_names
    #
    my @toList = values %{$r_remapCmds};
    my $r_procIdMap = getIdMap($dbh, "process_names", "id", "name", \@toList );
    my @PROC_TABLES = ( 'proc_stats', 'sum_proc_stats' );
    my %timeCol = (
        'proc_stats' => 'time',
        'sum_proc_stats' => 'date'
    );
    my %sth = ();
    foreach my $table ( @PROC_TABLES ) {
        my $tCol = $timeCol{$table};
        $sth{$table} = {
            'from' => $dbh->prepare("SELECT $tCol, serverid, cpu, mem, thr, fd, rss FROM $table WHERE procid = ?"),
            'to'   => $dbh->prepare("SELECT cpu, mem, thr, fd, rss FROM $table WHERE $tCol = ? AND serverid = ? AND procid = ?")
        }
    }

    my $cmdCount = 0;
    logMsg("Mapping rows in proc_stats");
    logMsg(sprintf("%5s %5s %5s %5s %5s %5s %s", '', 'Frm Id', 'To Id', 'Up id', 'Id Ex', 'Up Ex', 'From => To Cmd'));
    foreach my $cmd ( sort keys %{$r_remapCmds} ) {
        if ( checkExit() ) { return 1; }

        $cmdCount++;

        my $fromId = $r_procIdMap->{$cmd};
        my $toId = $r_procIdMap->{$r_remapCmds->{$cmd}};

        my %counts = (
            '_total' => {
                'from' => 0,
                'to' => 0
            }
        );
        my $msg = "";
        my $skipCronAndName = 0;
        foreach my $table ( @PROC_TABLES ) {
            $counts{$table} = { 'from' => -1, 'to' => - 1};
            if ( $checkCounts ) {
                $counts{$table}->{'from'} = dbSelectAllArr($dbh, "SELECT COUNT(*) FROM proc_stats WHERE procid = $fromId")->[0]->[0];
                $counts{'_total'}->{'from'} += $counts{$table}->{'from'};
                # Don't query the toCount if fromCount is over MAX_REMAP
                if ( $counts{$table}->{'from'} < $MAX_REMAP ) {
                    $counts{$table}->{'to'} = dbSelectAllArr($dbh, "SELECT COUNT(*) FROM proc_stats WHERE procid = $toId")->[0]->[0];
                    $counts{'_total'}->{'to'} += $counts{$table}->{'to'};
                }
            }

            if ( $counts{$table}->{'from'} >= $MAX_REMAP ) {
                $msg = "Too many rows to remap";
                $skipCronAndName = 1;
            }
        }

        if ( ! $skipCronAndName ) {
            foreach my $table ( @PROC_TABLES ) {
                if ( $counts{$table}->{'from'} == 0 ) {
                    $msg .= " Nothing to do";
                } elsif ( $counts{$table}->{'to'} == 0 && $doUpdate ) {
                    # If there's no to existing rows, then we can just change the procid to the new one
                    my $sql = sprintf("UPDATE %s SET procid = %d WHERE procid = %d", $table, $toId, $fromId );
                    dbDo($dbh,$sql) or die "Failed to change procid from $fromId to $toId";
                    $msg .= " Direct remap";
                } elsif ( $counts{$table}->{'from'} < $MAX_REMAP ) {
                    if ( $::DEBUG > 2 ) { print( "doRemap: cmdCount=$cmdCount looking for the cmd=$cmd,procid=$fromId in process_stats\n"); }
                    my ($updateInPlace, $existsCount, $updateExisting) = remapOneId(
                        $dbh,
                        $table,
                        $timeCol{$table},
                        $fromId,
                        $toId,
                        $sth{$table}->{'from'},
                        $sth{$table}->{'to'}
                    );
                    $msg .= sprintf(" %5d %5d %5d", $updateInPlace, $existsCount, $updateExisting);
                }
            }

            logMsg(sprintf("%5d %5d %5d %7d %7d%s %s", $cmdCount, $fromId, $toId, $counts{'_total'}->{'from'}, $counts{'_total'}->{'to'},
                           $msg, $cmd . " => " . $r_remapCmds->{$cmd}));

            #
            # Crontab stats now use the process_names_table
            #
            if ( $doUpdate  ) {
                dbDo($dbh,
                     sprintf("UPDATE crontabs SET process_name_id = %d WHERE process_name_id = %d",
                             $toId,
                             $r_procIdMap->{$cmd}));
                dbDo($dbh,sprintf("DELETE FROM process_names WHERE id = %d", $r_procIdMap->{$cmd}));
            }
        }
    }

    foreach my $table ( @PROC_TABLES ) {
        $sth{$table}->{'from'}->finish;
        $sth{$table}->{'to'}->finish;
    }
}

sub getOldProcesses($) {
    my ($dbh) = @_;

    my $r_rows = dbSelectAllArr($dbh,
                                "SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE table_name = 'proc_stats' AND table_schema = DATABASE()");
    my $engine = $r_rows->[0]->[0];
    if ( $::DEBUG ) { print "cleanUpOldProcesses: proc_stats ENGINE = $engine\n"; }

    logMsg("Getting old processes");

    my @procNameList = ( "[AGGREGATED]" );
    my $r_procIdMap = getIdMap($dbh, "process_names", "id", "name", \@procNameList );
    my @idList = values %{$r_procIdMap};
    my %idMap = ();
    my $countCmd = 0;
    foreach my $cmd ( keys %{$r_procIdMap} ) {
        $idMap{$r_procIdMap->{$cmd}} = $cmd;
    }

    my %cronTables =
        (
         'crontabs'  => 'process_name_id'
        );
    my %cronProcIds = ();

    #
    # Get list of procids used by the cront tables, these cannot be AGG
    #
    foreach my $tableName ( keys %cronTables ) {
        my $colName = $cronTables{$tableName};
        logMsg(" Get used list from $tableName $colName");
        my $usedSth = $dbh->prepare("SELECT DISTINCT($colName) FROM $tableName")
            or die "Couldn't prepare statement: " . $dbh->errstr;
        $usedSth->execute() or die "Couldn't execute statement: " . $usedSth->errstr;

        while ( my @row = $usedSth->fetchrow_array() ) {
            $cronProcIds{$row[0]}++;
        }
        $usedSth->finish();
    }
    if ( checkExit() ) { return 1; }

    #
    # Look for proc_id that have been used let then X times
    #
    logMsg(" Getting counts from proc_stats");
    printf("  %5s %18s %s\n", "Count", "MAX Time", "Command");
    my $timeThreshold = time() - ($CLEANUP_OLD_MIN_DAYS * 24 * 60 * 60);
    my @oldProcIds = ();
    my $indexHint = "";
    if ( $engine ne 'MyISAM' ) {
        $indexHint = "IGNORE INDEX(pidIdx) ";
    }
    my $sth = $dbh->prepare("SELECT procid, UNIX_TIMESTAMP(MAX(time)), COUNT(*) AS num FROM proc_stats $indexHint GROUP BY procid HAVING num < $CLEANUP_OLD_MAX_COUNT")
        or die "Couldn't prepare statement: " . $dbh->errstr;
    $sth->execute() or die "Couldn't execute statement: " . $sth->errstr;
    while ( my @row = $sth->fetchrow_array() ) {
        my ( $procId, $maxTime, $rowCount ) = ( $row[0], $row[1], $row[2] );
        if ( (! exists $cronProcIds{$procId}) &&
             ($maxTime < $timeThreshold ) ) {
                 push @oldProcIds, $procId;
        }
    }
    $sth->finish();

    if ( $#oldProcIds > -1 ) {
        dbDo($dbh, "use ddpadmin");
        foreach my $procId ( @oldProcIds ) {
            dbDo($dbh, "INSERT INTO ddp_old_procids (procid) VALUES ($procId)")
                or die "Failed to insert $procId into ddp_old_procids";
        }
        dbDo($dbh, "use statsdb");
    }

    if ( checkExit() ) { return 1; }
}

sub cleanUpOldProcesses
{
    my ($dbh,$doUpdate) = @_;


    logMsg("Cleaning up old processes");

    my @procNameList = ( "[AGGREGATED]" );
    my $r_procIdMap = getIdMap($dbh, "process_names", "id", "name", \@procNameList );
    my @idList = values %{$r_procIdMap};
    my %idMap = ();
    my $countCmd = 0;
    foreach my $cmd ( keys %{$r_procIdMap} ) {
        $idMap{$r_procIdMap->{$cmd}} = $cmd;
    }

    #
    # Look for proc_id that have been used let then X times
    #
    logMsg(" Getting old process from ddp_old_procids");
    my %remapToAgg = ();
    my $sth = $dbh->prepare("SELECT DISTINCT procid FROM ddpadmin.ddp_old_procids")
        or die "Couldn't prepare statement: " . $dbh->errstr;
    $sth->execute() or die "Couldn't execute statement: " . $sth->errstr;
    my $numToRemap = 0;
    while ( my @row = $sth->fetchrow_array() ) {
        my ( $procId ) = ( $row[0] );
        if ( exists $idMap{$procId} ) {
            $remapToAgg{$idMap{$procId}} = "[AGGREGATED]";
            printf("  %s\n", $idMap{$procId} );
            $numToRemap++;
        } else {
            print "WARNING: Could not find $procId in process_names\n";
        }
    }
    $sth->finish();
    if ( checkExit() ) { return 1; }

    logMsg(" Remapping $numToRemap processes");
    doRemap(\%remapToAgg, $dbh, 0);
    if ( checkExit() ) { return 1; }

    dbDo($dbh, "use ddpadmin");
    dbDo($dbh, "TRUNCATE TABLE ddp_old_procids");
    dbDo($dbh, "use statsdb");
}

sub setDoUpdate($) {
    my ($val) = @_;
    $doUpdate = $val;
}

sub fix($$$) {
    my ($fixNames, $exitFile, $cleandays) = @_;

    if ( defined $cleandays ) {
        $CLEANUP_OLD_MIN_DAYS = $cleandays;
    }

    my $dbh = connect_db();

    if ( $fixNames eq 'getold' ) {
        getOldProcesses($dbh);
        $dbh->disconnect;
        return 0;
    }

    if ( $fixNames eq 'testnames' ) {
        $doUpdate = -1;
        if ( $::DEBUG > 0 ) { setStatsCommon_Debug($::DEBUG); }
    } elsif ( $fixNames eq 'test' ) {
        $doUpdate = 0;
    } elsif ( $fixNames eq 'fix' ) {
        $doUpdate = 1;
    }
    else
    {
        die "Invalid value for fixnames $fixNames";
    }

    $EXIT_FILE = $exitFile;


    # Remove any corrupt entries, i.e. older then 2000
    if ( $doUpdate == 1 ) {
        # The deletes are done in two seperate states to allow the partitioning
        # handle the time and the index handle the procid
        logMsg("Remove any corrupt rows");
        dbDo($dbh, "DELETE FROM proc_stats WHERE time < '2020-01-01'");
        dbDo($dbh, "DELETE FROM sum_proc_stats WHERE date < '2020-01-01'");
        dbDo($dbh, "DELETE FROM proc_stats WHERE procid = 0");
        dbDo($dbh, "DELETE FROM sum_proc_stats WHERE procid = 0");
        dbDo($dbh, "DELETE FROM crontabs WHERE date < '2020-01-01'");
        dbDo($dbh, "DELETE FROM crontabs WHERE process_name_id = 0");
    }

    my @usingTables = (
        {
            'table' => 'proc_stats',
            'column' => 'procid'
        },
        {
            'table' => 'sum_proc_stats',
            'column' => 'procid'
        },
        {
            'table' => 'crontabs',
            'column' => 'process_name_id'
        }
    );
    if ( $fixNames ne 'testnames' ) {
        logMsg("Removing un-used entries in process_names");
        NameTable::removeUnused($dbh,
                                "process_names","id", "name",
                                \@usingTables,
                                $doUpdate );
        logMsg("Removing duplicates in process_names");
        NameTable::removeDuplicates($dbh,
                                    "process_names", "id","name",
                                    \@usingTables,
                                    $doUpdate );
    }

    my ($r_remapExisting, $r_remapNew ) = analyseCommandNames($dbh);

    if ( $fixNames eq 'testnames' ) {
        exit;
    }

    # Do remapping for cmds where the "map to" cmd already exists
    # The driver for this is to make space for the new commands
    # that need to be added below
    my @fromList = keys %{$r_remapExisting};
    logMsg("Remapping " . ($#fromList + 1) . " cmds to existing commands");
    doRemap($r_remapExisting, $dbh, 1);
    if ( checkExit() ) { return 1; }

    # Removing the used and remapping to existing command should have made space
    # for new commands. Now we need to "compact" the ids in the name table so
    # the new command can be added
    if ( $doUpdate ) { NameTable::compact($dbh, "process_names","id", "name",\@usingTables, $EXIT_FILE ); }
    if ( checkExit() ) { return 1; }

    #
    # Do remapping where the "map to" command doesn't already exist, so there
    # must be enough "space" in the process_names to hold the new commands
    # (i.e. the id must fix in less then the max for a smallint (65k)
    #
    @fromList = keys %{$r_remapNew};
    my %toMap = ();
    foreach my $newCmd ( values %{$r_remapNew} ) {
        $toMap{$newCmd}++;
    }
    my @toList = keys %toMap;
    logMsg("Remapping " . ($#fromList + 1) . " cmds to " . ($#toList + 1) . " new commands");
    doRemap($r_remapNew, $dbh, 1);
    if ( checkExit() ) { return 1; }

    cleanUpOldProcesses( $dbh );
    if ( checkExit() ) { return 1; }

    if ( $doUpdate == 1 ) { NameTable::compact($dbh, "process_names" ,"id", "name",\@usingTables, $EXIT_FILE ); }

    $dbh->disconnect;

    return 0;
}

1;
