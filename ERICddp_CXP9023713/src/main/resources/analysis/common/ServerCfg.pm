package ServerCfg;

use warnings;
use strict;

use DBI;
use StatsDB;
use Data::Dumper;

sub store($$$$$) {
    my ($dbh, $siteId, $serverId, $date, $r_data) = @_;

    # Lets optimise for the most common cases
    # where the data has already been loaded for today
    my $r_Cfg = dbSelectAllArr($dbh, "SELECT cfgid FROM servercfg WHERE serverid = $serverId AND date = '$date'");
    if ( $#{$r_Cfg} == 0 ) {
        # Validate that the config is still the same
        if ( compareConfig($dbh,$r_Cfg->[0]->[0],$r_data) == 1 ) {
            # Config already stored, nothing more to do
            return;
        } else {
            dbDo($dbh,"DELETE FROM servercfg WHERE serverid = $serverId AND date = '$date'")
                or die "storeData: Failed to remove existing data for today";
        }
    }


    # Next option, see if we have a cfg for yesterday
    $r_Cfg = dbSelectAllArr($dbh, "SELECT cfgid FROM servercfg WHERE serverid = $serverId AND (date = SUBDATE('$date',1) OR date = ADDDATE('$date',1)) LIMIT 1");
    if ( $#{$r_Cfg} == -1 ) {
        # Okay, config not already stored for yesterday, so look for the most recent one
        # This seems to be a pretty expensive query
        $r_Cfg = dbSelectAllArr($dbh, "SELECT cfgid FROM servercfg WHERE serverid = $serverId ORDER BY date DESC LIMIT 1")
            or die "storeData: Failed to search for existing row in servercfg";
    }

    my $cfgId;
    if ( $#{$r_Cfg} == 0 ) {
        if ( compareConfig($dbh,$r_Cfg->[0]->[0],$r_data) == 1 ) {
            $cfgId = $r_Cfg->[0]->[0];
        }
    }
    if ( ! defined $cfgId ) {
        $cfgId = getConfig($dbh,$r_data);
    }

    my $insertSql;
    if ( exists $r_data->{'biosver'} ) {
        $insertSql = sprintf("INSERT INTO servercfg (date,serverid,cfgid,biosver) VALUES('%s',%d,%d,'%s')",
                             $date,$serverId,$cfgId,$r_data->{'biosver'});
    } else {
        $insertSql = "INSERT INTO servercfg (date,serverid,cfgid) VALUES('$date',$serverId,$cfgId)";
    }
    dbDo($dbh,$insertSql)
        or die "storeData: Failed to insert row into servercfg";
}

sub getConfig
{
    my ($dbh,$r_data) = @_;

    my $r_possibleCfgs = dbSelectAllArr($dbh,
                                       sprintf("SELECT id FROM servercfgtypes WHERE system = '%s' AND mbram = %d",
                                               $r_data->{'systemtype'}, $r_data->{'memory'}))
        or die "getConfig: Failed to search servercfgtypes";
    foreach my $r_Cfg ( @{$r_possibleCfgs} ) {
        if ( $::DEBUG > 4 ) { print "getConfig: checking possible config $r_Cfg->[0]\n"; }
        if ( compareCpu($dbh,$r_Cfg->[0],$r_data) == 1 ) {
            return $r_Cfg->[0];
        }
    }

    dbDo($dbh,sprintf("INSERT INTO servercfgtypes (system,mbram) VALUES ('%s',%d)",
                      $r_data->{'systemtype'}, $r_data->{'memory'}))
        or die "getConfig: Failed to insert row into servercfgtypes";
    my $cfgId = $dbh->last_insert_id(undef,undef,"servercfgtypes","id");

    foreach my $r_cpuGrp ( values %{$r_data->{'cpugrp'}} ) {
        my $cpuTypeSql = "SELECT id FROM cputypes WHERE name = '" . $r_cpuGrp->{'info'}->{'name'} . "'";
        if ( exists $r_cpuGrp->{'info'}->{'freq'} ) {
            $cpuTypeSql .= " AND mhz = " . $r_cpuGrp->{'info'}->{'freq'};
        } else {
            $cpuTypeSql .= " AND mhz IS NULL";
        }
        if ( exists $r_cpuGrp->{'info'}->{'cache'} ) {
            $cpuTypeSql .= " AND kbCache = " . $r_cpuGrp->{'info'}->{'cache'};
        } else {
            $cpuTypeSql .= " AND kbCache IS NULL";
        }
        if ( exists $r_cpuGrp->{'info'}->{'thr_per_core'} ) {
            $cpuTypeSql .= " AND threadsPerCore = " . $r_cpuGrp->{'info'}->{'thr_per_core'} .
                " AND cores = " . $r_cpuGrp->{'info'}->{'cores'};
        } else {
            $cpuTypeSql .= " AND threadsPerCore IS NULL AND cores IS NULL";
        }

        my $cpuTypeId;
        my $r_allRows = dbSelectAllArr($dbh,$cpuTypeSql)
            or die "getConfig: Failed search cputypes";
        if ( $#{$r_allRows} == -1 ) {
            my @insertCols = ();
            my @insertVals = ();
            push @insertCols, "name";
            push @insertVals, "'" . $r_cpuGrp->{'info'}->{'name'} . "'";
            if ( exists $r_cpuGrp->{'info'}->{'freq'} ) {
                push @insertCols, "mhz";
                push @insertVals, $r_cpuGrp->{'info'}->{'freq'};
            }
            if ( exists $r_cpuGrp->{'info'}->{'cache'} ) {
                push @insertCols, "kbCache";
                push @insertVals, $r_cpuGrp->{'info'}->{'cache'};
            }
            if ( exists $r_cpuGrp->{'info'}->{'thr_per_core'} ) {
                push @insertCols, "threadsPerCore";
                push @insertVals, $r_cpuGrp->{'info'}->{'thr_per_core'};
                push @insertCols, "cores";
                push @insertVals, $r_cpuGrp->{'info'}->{'cores'};
            }

            dbDo($dbh, sprintf("INSERT INTO cputypes (%s) VALUES (%s)",
                               join(",", @insertCols), join (",", @insertVals)))
                or die "getConfig: Failed to insert cpu type";
            $cpuTypeId = $dbh->last_insert_id(undef,undef,"cputypes","id");
        } else {
            $cpuTypeId = $r_allRows->[0]->[0];
        }

        dbDo($dbh, "INSERT INTO servercpu (cfgid,typeid,num) VALUES ($cfgId,$cpuTypeId,$r_cpuGrp->{'count'})")
             or die "getConfig: Failed to insert row into servercpu";
    }

    return $cfgId;
}

sub compareConfig
{
    my ($dbh,$cfgId,$r_data) = @_;

    my $r_Cfg = dbSelectAllArr($dbh, "SELECT system,mbram FROM servercfgtypes WHERE id = $cfgId")
        or die "compareConfig: Failed to read config FROM servercfgtypes";
    if ( $r_Cfg->[0]->[0] ne $r_data->{'systemtype'} ||
         $r_Cfg->[0]->[1] != $r_data->{'memory'} ) {
        return 0;
    }

    return compareCpu($dbh,$cfgId,$r_data);
}

sub compareCpu
{
    my ($dbh,$cfgId,$r_data) = @_;

    if ( $::DEBUG > 4 ) { print Dumper("compareCpu: checking if CPUs for cfg $cfgId matches", $r_data->{'cpugrp'}); }
    my %dbGrps = ();
    my $r_AllCpus = dbSelectAllHash($dbh,"
SELECT cputypes.name AS name, cputypes.mhz AS freq, cputypes.kbCache AS cache,
       cputypes.cores AS cores, cputypes.threadsPerCore AS thr_per_core,
       servercpu.num AS num
 FROM cputypes, servercpu
 WHERE servercpu.cfgid = $cfgId AND
       servercpu.typeid = cputypes.id")
        or die "compareCpu: Failed to query the cpus in cfg $cfgId";
    foreach my $r_CpuGrp ( @{$r_AllCpus} ) {
        if ( $::DEBUG > 7 ) { print Dumper("compareCpu checking r_CpuGrp", $r_CpuGrp); }
        my $key = $r_CpuGrp->{'name'};
        if ( defined $r_CpuGrp->{'freq'} ) {
            $key .= ":f" . $r_CpuGrp->{'freq'};
        }
        if ( defined $r_CpuGrp->{'cache'} ) {
           $key .= ":c" . $r_CpuGrp->{'cache'};
        }
        if ( defined $r_CpuGrp->{'thr_per_core'} ) {
           $key .= ":t" . $r_CpuGrp->{'thr_per_core'};
        }
        if ( $::DEBUG > 5 ) { print "compareCpu: key=$key\n"; }

        if ( ! exists $r_data->{'cpugrp'}->{$key} ) {
            if ( $::DEBUG > 4 ) { print "compareCpu: no match as no grp with \"$key\" found\n"; }
            return 0;
        } elsif ( $r_data->{'cpugrp'}->{$key}->{'count'} != $r_CpuGrp->{'num'} ) {
            if ( $::DEBUG > 4 ) { print "compareCpu: no match as grp with \"$key\" has different count\n"; }
            return 0;
        }

        $dbGrps{$key}++;
    }

    # Now check if there are any group in the current config that are not
    # in the db
    foreach my $grpKey ( keys %{$r_data->{'cpugrp'}} ) {
        if ( ! exists $dbGrps{$grpKey} ) {
            if ( $::DEBUG > 4 ) { print "compareCpu: no match as server has grp \"$grpKey\" which not in the db\n"; }
            return 0;
        }
    }

    return 1;
}

1;
