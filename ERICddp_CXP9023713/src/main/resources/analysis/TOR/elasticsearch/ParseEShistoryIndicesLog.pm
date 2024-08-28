package ParseEShistoryIndicesLog;

use strict;
use warnings;
use Getopt::Long;
use Data::Dumper;
use StatsDB;
use StatsTime;
use DBI;

our $DEBUG = 0;

sub parseLog($) {
    my ($logFile) = @_;
    my $timestamp = undef;
    my %indices = ();
    my @indicesData = ();
    my @result = ();

    open INPUT, $logFile or die "Cannot open $logFile";
    while ( my $line = <INPUT> ) {
        chomp $line;
        if ( $DEBUG > 9 ) { print "parseLog: $line\n"; }

        # Skip commented [OR] empty [OR] header lines, if any
        if ( $line =~ /^\s*#/ || $line !~ /\S/ || $line =~ /^\s*health/i ) {
            if ( $DEBUG > 5 ) { print "parseLog: Skipping the line '$line'\n"; }
            next;
        }

        if ($line =~ /([\d+]{6}:[\d+]{2}:[\d+]{2})/) {
            $timestamp = formatTime( parseTime($1, $StatsTime::TIME_DDMMYY_HM), $StatsTime::TIME_SQL );
        } else {
            my $cname;
            my @indexSplit = split(/\s+/, $line);
            if ( $indexSplit[0] ne 'red' ) {
                if ($indexSplit[2] =~ /(\S+)_\d+/) {
                    $cname = $1;
                } elsif ($indexSplit[2] =~ /(\S+)_migration/) {
                    $cname = $1;
                } else {
                    $cname = $indexSplit[2];
                }

                if ( exists  $indices{$cname}{$timestamp} ) {
                    $indices{$cname}{$timestamp}{'numIndex'}++;
                } else {
                    $indices{$cname}{$timestamp}{'numIndex'} = 1;
                }

                $indices{$cname}{$timestamp}{'noOfDocs'} +=  $indexSplit[6];
                $indices{$cname}{$timestamp}{'noOfDeletedDocs'} += $indexSplit[7];

                #storing size as MB
                my ($size, $unit) = $indexSplit[8] =~ /^([\d\.]+)([bkgm]{1,2})/;
                if ( "$unit" eq 'b' ) {
                    $size /= 1024 * 1024;
                } elsif ( $unit eq 'kb' ) {
                    $size /= 1024;
                } elsif ($unit eq 'gb' ) {
                    $size *= 1024;
                }
                $indices{$cname}{$timestamp}{'size'} += $size;
            }
        }
    }
    close INPUT;

    if ( $DEBUG > 5 ) { print Dumper("parseLog: indicesHelath", %indices); }
    return \%indices;
}

sub main() {
    my ($logFile, $site, $date) = @_;
    my $result = GetOptions(
        "log=s"   => \$logFile,
        "date=s"  => \$date,
        "site=s"  => \$site,
        "debug=s" => \$DEBUG
    );
    ($result == 1) or die "Invalid args";
    setStatsDB_Debug($DEBUG);

   my $indicesEshistory = parseLog($logFile);

    my $dbh = connect_db();
    my $siteId = getSiteId($dbh, $site);
    ( $siteId != -1) or die "Failed to get siteid for $site";

    my @indexNames = keys %{$indicesEshistory};

    # Get ID maps for EShistory index names
    my $indexNameIdMap = getIdMap($dbh, 'enm_es_index_names', 'id', 'name', \@indexNames);
    my $bcpFile = getBcpFileName("enm_eshistory_indices_stats");

    open BCP, ">$bcpFile" or die "Failed to open $bcpFile";
    while ( my ($indexName, $r_indexData) = each %{$indicesEshistory} ) {
        while ( my ($timestamp, $r_sample) = each %{$r_indexData} ) {
            printf BCP "%d\t%s\t%d\t%d\t%d\t%d\t%d\n",
            $siteId,
            $timestamp,
            $indexNameIdMap->{$indexName},
            $r_sample->{'noOfDocs'},
            $r_sample->{'noOfDeletedDocs'},
            $r_sample->{'size'},
            $r_sample->{'numIndex'};
        }
    }
    close BCP;

    # Remove the old data from table for the selected site and date
    dbDo($dbh, "DELETE FROM enm_eshistory_indices_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
       or die "Failed to remove old data from 'enm_eshistory_indices_stats' table";

    #Load the new data into the table
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_eshistory_indices_stats" )
        or die "Failed to load new data from '$bcpFile' file to 'enm_eshistory_indices_stats' table";

    $dbh->disconnect();
}

1;
