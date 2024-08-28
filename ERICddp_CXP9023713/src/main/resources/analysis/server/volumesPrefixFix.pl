#! /usr/bin/env perl
#=====================================================================
# Script  : volumesAddPrefix.pl
# Author  : Patrick O Connor (epatoco)
# Purpose : The purpose of this script is to, for all sites
#           on a specific DDP server, find the prefix and then
#           to tidy the volumes table using the prefix
# Usage   : perl volumesPrefixFix.pl --fix (fix||test) --exit ${EXIT_FILE} --action (dump|insert) --path ${PATH}
#=====================================================================

use strict;
use warnings;

use StatsDB;
use NameTable;
use DBI;
use Getopt::Long;
use Data::Dumper;
use XML::Simple qw(:strict);
use IO::Uncompress::Unzip;
use IO::Uncompress::Gunzip qw(gunzip $GunzipError) ;
use JSON;

our $DEBUG = 0;
our $EXIT_FILE = undef;
our $EXIT_FILE_FOUND = 0;

sub checkExit {
    if ( (! defined $EXIT_FILE) ) {
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

sub getSiteInfo {
    my $dbh = connect_db();
    my %sites;

    my $r_rows = dbSelectAllArr($dbh, "SELECT name, date(lastupload) FROM sites") or die "Failed to get site list";

    foreach my $r_row ( @{$r_rows} ) {
        $sites{$r_row->[0]} = $r_row->[1];
    }

    $dbh->disconnect();
    return %sites;
}

sub getDirDate($) {
    my ($date) = @_;
    if ( $date =~ m/^\d{2,2}(\d{2,2})-(\d{2,2})-(\d{2,2})$/ ) {
        return $3.$2.$1;
    }
}

sub getFileSystemPrefix($$) {
    my ($site, $date) = @_;
    my $dataFile = "/data/stats/tor/$site/data/$date/TOR/sw_inventory/LITP2_deployment_description";
    my $gzFile = $dataFile.'.gz';

    if ( -e $dataFile || -e $gzFile) {
        unless ( -e $dataFile ) {
            gunzip $gzFile => $dataFile or die "gunzip failed: $GunzipError\n";
        }

        my $r_rawModel = XMLin($dataFile, ForceArray => ['litp:storage-container'],  KeyAttr => []);

        return $r_rawModel->{'litp:infrastructure'}->{'litp:storage'}->{'litp:storage-storage_providers-collection'}->{'litp:sfs-service'}->{'litp:sfs-service-pools-collection'}->{'litp:sfs-pool'}->{'name'};
    }
    return undef;
}

sub doModifyIndex($$$$) {
    my ($dbh, $type, $usingTables, $doUpdate) = @_;

    if ( $doUpdate ) {
        my $lc_type = lc($type);
        while (my ($table, $col) = each($usingTables)) {
            my $tabCol = "$table($col)";
            logMsg("Trying to $lc_type temp index on $tabCol");

            if ( $type eq "ADD" ) {
                dbDo( $dbh, "ALTER TABLE $table ADD INDEX tmpIDX ($col)" ) or die "Failed to add temp index to $table => $col";
            } elsif ( $type eq "DROP" ) {
                dbDo( $dbh, "ALTER TABLE $table DROP INDEX tmpIDX" ) or die "Failed to drop temp index from $table => $col";
            } else {
                die "Error: doModifyIndex Failure, $lc_type : $tabCol";
            }

            logMsg("$lc_type temp index on $tabCol successful");
        }
    }
}

sub logMsg($) {
    my ($msg) = @_;
    print scalar(localtime(time())) . " ". $msg . "\n";
}

sub fixNameTable($$$$) {
    my ($doUpdate, $dbh, $prefixes, $usingTables) = @_;

    if ( $doUpdate ) {
        NameTable::removeDuplicates($dbh, "volumes", "id", "name", $usingTables);
        NameTable::removeUnused($dbh, "volumes","id", "name", $usingTables, $doUpdate);
        if ( NameTable::compact($dbh, "volumes", "id", "name", $usingTables, $EXIT_FILE) != 0 ) {
            return 1;
        }
    }

    my ($remapExisting, $remapNew) = &getRemapLists($dbh, $prefixes);

    logMsg("**** Remap existing");
    if ( doRemap($remapExisting, $dbh, $doUpdate, $usingTables) != 0 ) {
        return 1;
    }

    if ( NameTable::compact($dbh, "volumes", "id", "name", $usingTables, $EXIT_FILE) != 0 ) {
        return 1;
    }

    logMsg("****  Remap new");
    if ( doRemap($remapNew, $dbh, $doUpdate, $usingTables) != 0 ) {
        return 1;
    }

    if ( $doUpdate ) {
        NameTable::compact($dbh, "volumes", "id", "name", $usingTables, $EXIT_FILE);
    }
    return 0;
}

sub doRemap($$$$) {
    my ($r_remap, $dbh, $doUpdate, $usingTables) = @_;

    my @fromList = keys %{$r_remap};
    my @toList = values %{$r_remap};

    my $r_idMap = getIdMap($dbh, "volumes", "id", "name", \@toList );
    logMsg(sprintf("%5s %5s %s %s", 'Frm Id', 'To Id', 'From => To', 'Table'));

    foreach my $name ( sort keys %{$r_remap} ) {
        if ( checkExit() ) {
            return 1;
        }
        while (my ($table, $col) = each($usingTables)) {
            my $fromId = $r_idMap->{$name};

            my $toId = $r_idMap->{$r_remap->{$name}};
            my $sql = sprintf("UPDATE $table SET $col = %d WHERE $col = %d", $toId, $fromId);
            if ( $doUpdate ) {
                dbDo($dbh, $sql);
            }
            logMsg(sprintf("%5d %5d %s %s", $fromId, $toId, ($name . " => " . $r_remap->{$name}, $table)));
        }
        if ( $doUpdate ) {
            dbDo( $dbh, "DELETE FROM volumes WHERE id = " . $r_idMap->{$name} ) or die "Failed to delete from volumes";
        }
    }
    return 0;
}

sub getRemapLists($$) {
    my ($dbh, $prefixes) = @_;

    logMsg("Fetching Existing List");
    my @nameList = ();
    my $r_idMap = getIdMap( $dbh, "volumes", "id", "name", \@nameList );

    logMsg("Analyse Existing Names in List");
    my %remapExisting = ();
    my %remapNew = ();

    foreach my $volName ( keys %{$r_idMap} ) {
        my $newVolName;
        foreach my $prefix (@$prefixes) {
            if ( $volName =~ /^$prefix(-.*)/ ) {
                $newVolName = "[PREFIX]" . $1;
                last;
            } elsif ($volName =~ /^(SNAP\d?-)$prefix(-.*)/) {
                $newVolName = $1 . "[PREFIX]" . $2;
                last;
            }
        }
        if ( defined $newVolName && $newVolName ne $volName ) {
            if ( exists $r_idMap->{$newVolName} ) {
                $remapExisting{$volName} = $newVolName;
            } else {
                $remapNew{$volName} = $newVolName;
            }
        }
    }
    return (\%remapExisting, \%remapNew);
}

sub main {
    my $fixMode;
    my $action;
    my $path;
    my $result = GetOptions(
        "fix=s" => \$fixMode,
        "exit=s" => \$EXIT_FILE,
        "action=s" => \$action,
        "path=s" => \$path,
        "debug=s" => \$DEBUG
    );
    ( $result == 1 ) or die "Invalid args";
    setStatsDB_Debug($DEBUG);
    my $dbh = connect_db();
    my %sites = getSiteInfo();
    my @prefixes;
    my $doUpdate;
    my $remapExisting;
    my $remapNew;

    logMsg("Generating prefix list");
    if ( ! defined $action || $action eq 'dump' ) {
        my %prefixHash;
        while (my ($key, $value) = each(%sites)) {
            if ( checkExit() ) {
                    return 1;
            }
            if ( defined $value ) {
                my $dirDate = getDirDate($value);
                my $prefix = getFileSystemPrefix($key, $dirDate);
                if ( defined $prefix ) {
                    $prefixHash{$prefix} = '';
                }
            }
        }
        @prefixes = keys %prefixHash;
    } elsif ( $action eq 'insert' ) {
        my $jsonStr = undef;
        {
            local $/ = undef;
            open FILE, $path or die "Couldn't open file: $!";
            binmode FILE;
            $jsonStr = <FILE>;
            close FILE;
        }
        my $json = decode_json($jsonStr);
        @prefixes = @{$json};
    } else {
        logMsg("Invalid value for --action Exiting");
        return 1;
    }

    my $size = @prefixes;
    logMsg("Prefix list generation finished ($size prefixes found)");

    if ( @prefixes ) {
        if ( defined $action && $action eq 'dump' ) {
            logMsg("Dumping Prefixes to file");
            open OUTPUT, ">$path" or die "Cannot open file";
            print OUTPUT encode_json(\@prefixes);
            close OUTPUT;
            return 1;
        }
        foreach my $prefix ( @prefixes) {
            logMsg("Prefix: $prefix");
        }
        ($remapExisting, $remapNew) = &getRemapLists($dbh, \@prefixes);
        if ( (! %{$remapNew}) && (! %{$remapExisting}) ) {
            logMsg("Exiting as no remap needed.");
            return 1;
        }
    } else {
        logMsg("Exiting as no Prefixes found");
        return 1;
    }

    if ( $fixMode eq 'test' ) {
        $doUpdate = 0;
    } elsif ( $fixMode eq 'fix' ) {
        $doUpdate = 1;
    } else {
        die "Invalid value for fix $fixMode";
    }

    my %usingTables = (
        'volume_stats'  => 'volid',
        'vxstat' => 'volid'
    );

    if ( @prefixes ) {
        doModifyIndex($dbh, "ADD", \%usingTables, $doUpdate);
        fixNameTable($doUpdate, $dbh, \@prefixes, \%usingTables);
        doModifyIndex($dbh, "DROP", \%usingTables, $doUpdate);
    }
}

main();
