package cm::AddNode;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    $self->{'srvIdMap'} = {};
    foreach my $sg ( 'cmservice', 'conscmeditor' ) {
        my $r_srvMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, $sg);
        while ( my ($srv, $srvId) = each %{$r_srvMap} ) {
            $self->{'srvIdMap'}->{$srv} = $srvId;
        }
    }

    if ( exists $r_incr->{'cm::AddNode'} ) {
        $self->{'data'} = $r_incr->{'cm::AddNode'};
    } else {
        $self->{'data'} = {
            'neTime'    => {},
            'cppTime'   => {}
        };
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("cm::AddNode::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "cm::AddNode::handle timestamp=$timestamp message=$message\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, CREATE_MO_(?:ENTRY|EXIT).*NetworkElement\s*=/ ) {
        $self->parseAddNode($timestamp,$host,$message);
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("cm::AddNode::done self", $self); }

    # Calculate the required 'Add-Node' stats (i.e., 'NE Time', 'CPP Time' & 'Total Time')
    my ($addNodeStats, $neNames, $datasetSize) =  calculateAddNodeStats($self->{'data'}->{'neTime'}, $self->{'data'}->{'cppTime'});

    # Store the 'Add-Node' stats in 'enm_cm_addnode_stats' table under 'statsdb' database
    storeAddNodeStats($dbh, $self->{'siteId'}, $addNodeStats, $neNames, $datasetSize);

    $r_incr->{'cm::AddNode'} = $self->{'data'};
}

#
# Internal functions
#

# Subroutine to parse the logs and extract 'Add-Node' NE and CPP MO's entry as well as exit point timestamps
# The '$neTime' and '$cppTime' variables are references to nested data structures with the following layout:
#    $neTime (or $cppTime) = {
#                             '<NetworkElement Name>' => {
#                                                         '<SERIAL_NO>' => [
#                                                                           '<NE (or CPP) CREATE_MO_ENTRY Timestamp>',
#                                                                           '<NE (or CPP) CREATE_MO_EXIT Timestamp>'
#                                                                          ],
#                                                         '<SERIAL_NO>' => [
#                                                                           '<NE (or CPP) CREATE_MO_ENTRY Timestamp>',
#                                                                           '<NE (or CPP) CREATE_MO_EXIT Timestamp>'
#                                                                          ],
#                                                         '<SERIAL_NO>' => ...
#                                                         ...
#                                                        },
#                             '<NetworkElement Name>' => ...
#                             '<NetworkElement Name>' => ...
#                             ...
#                            };
# NOTE: The key <SERIAL_NO> refers to the 'n'th number of instance for which the given node is being added in the given dataset/log.
# Please refer below one such '$neTime' nested data structure with realtime data:
#    $neTime = {
#               'ieatnetsimv6002-03_LTE21ERBS00017' => {
#                                                       '1' => [ '2015-08-05T11:02:04.265409-04:00', '2015-08-05T11:02:05.617425-04:00' ],
#                                                       '2' => [ '2015-08-05T11:54:55.334459-04:00', '2015-08-05T11:54:55.384063-04:00' ]
#                                                      },
#               'ieatnetsimv6002-03_LTE21ERBS00022' => {
#                                                       '1' => [ '2015-08-05T11:02:04.872212-04:00', '2015-08-05T11:02:05.003166-04:00' ]
#                                                      },
#               'ieatnetsimv6002-03_LTE21ERBS00030' => {
#                                                       '1' => [ '2015-08-05T11:01:57.621865-04:00', '2015-08-05T11:01:59.466439-04:00' ]
#                                                      }
#              };
sub parseAddNode($$$$) {
    my ($self,$timestamp,$host,$message) = @_;

    if ( $::DEBUG > 5 ) { print "cm::AddNode timestamp=$timestamp message=$message\n"; }

    if ( $message !~ /NetworkElement\s*=\s*(.*?)[,']/ ) {
        return;
    }
    my $netElement = $1;

    # Check whether the logline represents NE MO or CPP MO
    my $cppFlag   = 0;
    my $neCppTime = $self->{'data'}->{'neTime'};
    if ($message =~ /CppConnectivityInformation/) {
        $cppFlag   = 1;
        $neCppTime = $self->{'data'}->{'cppTime'};
    }

    # Add NE (or CPP) MO's entry point stats to $neTime (or $cppTime) nested data structures
    if ($message =~ /CREATE_MO_ENTRY/) {
        my $instanceKey = 1;
        # Skip the first CPP MO Entry Logline with no corresponding NE MO Loglines
        if ( ($cppFlag) && (scalar (keys %{$self->{'data'}->{'neTime'}->{$netElement}}) == 0) ) {
            if ($::DEBUG > 5) {
                print "parseLogs: Skipping the first CPP MO Entry Logline with no corresponding NE MO Loglines. Add_Node_MO_Logline=$message";
            }
            return;
        }

        if (scalar (keys %{$neCppTime->{$netElement}}) > 0) {
            $instanceKey = scalar (keys %{$neCppTime->{$netElement}}) + 1;

            my $epoch_current  = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC);
            my $epoch_previous = parseTime($neCppTime->{$netElement}->{$instanceKey-1}->[0], $StatsTime::TIME_ELASTICSEARCH_MSEC);
            # Skip the duplicate NE (or CPP) MO Entry Logline
            if ( ($epoch_current - $epoch_previous) <= 2 ) {
                if ($::DEBUG > 5) { print "parseLogs: Skipping the duplicate Add-Node MO Entry Loglines. Add_Node_MO_Logline=$message"; }
                return;
            }

            # Delete and ignore NE MO's entry and exit points for which no corresponding CPP MO entry and exit points exist
            my $neTimeSize  = scalar (keys %{$self->{'data'}->{'neTime'}->{$netElement}});
            my $cppTimeSize = scalar (keys %{$self->{'data'}->{'cppTime'}->{$netElement}});
            if ( (! $cppFlag) && ($neTimeSize > $cppTimeSize) ) {
                if ($::DEBUG > 5) {
                    print "parseLogs: Ignoring the NE MO's entry and exit points with no corresponding CPP MO entry and exit points\n\t",
                        "Network Element  : $netElement\n\t",
                        "NE MO Entry Point: $self->{'data'}->{'neTime'}->{$netElement}->{$neTimeSize}->[0]\n\t",
                        "NE MO Exit Point : $self->{'data'}->{'neTime'}->{$netElement}->{$neTimeSize}->[1]\n";
                }
                delete $self->{'data'}->{'neTime'}->{$netElement}->{$neTimeSize};
                $instanceKey--;
            }
        }

        # Add NE (or CPP) MO's entry point timestamp to $self->{'data'}->{'neTime'} (or $cppTime)
        $neCppTime->{$netElement}->{$instanceKey}->[0] = $timestamp;
    } else {
        # Add NE (or CPP) MO's exit point stats to $self->{'data'}->{'neTime'} (or $cppTime) nested data structures
        if (scalar (keys %{$neCppTime->{$netElement}}) > 0) {
            my $instanceKey = scalar (keys %{$neCppTime->{$netElement}});
            if ( (defined $neCppTime->{$netElement}->{$instanceKey}) && (scalar @{$neCppTime->{$netElement}->{$instanceKey}} < 2) ) {
                # Add NE (or CPP) MO's exit point timestamp to $self->{'data'}->{'neTime'} (or $cppTime)
                $neCppTime->{$netElement}->{$instanceKey}->[1] = $timestamp;
            } elsif ($::DEBUG > 5) {
                print "parseLogs: Skipping the duplicate Add-Node MO Exit Loglines. Add_Node_MO_Logline=$message";
            }
        } elsif ($::DEBUG > 5) {
            print "parseLogs: Skipping the first NE (or CPP) MO Exit Loglines with no corresponding NE MO Entry Loglines. Add_Node_MO_Logline=$message";
        }
    }
}

# Subroutine to calculate 'NE Time', 'CPP Time' & 'Total Time' stats in milliseconds from the parsed MO entry/exit point timestamps
sub calculateAddNodeStats($$) {
    my $neTime  = shift;
    my $cppTime = shift;

    my %dbRows  = ();
    my @neNames = ();
    my $datasetSize = 0;

    foreach my $netElement (keys %{$neTime}) {
        foreach my $instance (sort keys %{$neTime->{$netElement}}) {
            if ($::DEBUG > 8) { print "calculateAddNodeStats: Calculating Add-Node stats for the Network Element $netElement . Instance Number: $instance\n"; }

            # Skip NetworkElements with missing NE MO entry or exit points
            my @neTimestamps = @{$neTime->{$netElement}->{$instance}};
            if ( (! defined $neTimestamps[0]) || (! defined $neTimestamps[1]) ) {
                if ($::DEBUG > 5) { print "calculateAddNodeStats: Skipping the calculation of Add-Node stats for Network Element $netElement due to missing NE MO entry or exit points\n"; }
                next;
            }
            # Calculate 'NE Time' in milliseconds
            my $neCreateMOEntry  = getElasticsearchTimeInMilliSeconds($neTimestamps[0]);
            my $neCreateMOExit   = getElasticsearchTimeInMilliSeconds($neTimestamps[1]);
            my $neTimeInMillisec = $neCreateMOExit - $neCreateMOEntry;
            print "$netElement" . ' - ' . "NE Time\t$neTimestamps[0]\t$neTimestamps[1]\t$neTimeInMillisec\n";

            # Skip NetworkElements with missing CPP MO entry or exit points
            if (! defined $cppTime->{$netElement}->{$instance}) {
                if ($::DEBUG > 5) { print "calculateAddNodeStats: Skipping the calculation of Add-Node stats for Network Element $netElement due to missing CPP MO entry or exit points\n"; }
                next;
            }
            my @cppTimestamps = @{$cppTime->{$netElement}->{$instance}};
            if ( (! defined $cppTimestamps[0]) || (! defined $cppTimestamps[1]) ) {
                if ($::DEBUG > 5) { print "calculateAddNodeStats: Skipping the calculation of Add-Node stats for Network Element $netElement due to missing CPP MO entry or exit points\n"; }
                next;
            }
            # Calculate 'CPP Time' in milliseconds
            my $cppCreateMOEntry  = getElasticsearchTimeInMilliSeconds($cppTimestamps[0]);
            my $cppCreateMOExit   = getElasticsearchTimeInMilliSeconds($cppTimestamps[1]);
            my $cppTimeInMillisec = $cppCreateMOExit - $cppCreateMOEntry;
            print "$netElement" . ' - ' . "CPP Time\t$cppTimestamps[0]\t$cppTimestamps[1]\t$cppTimeInMillisec\n";

            # Calculate 'Total Time' in milliseconds
            my $totalTimeInMillisec = $neTimeInMillisec + $cppTimeInMillisec;
            print "$netElement" . ' - ' . "Total Time\t$totalTimeInMillisec\n";

            # Add 'Add-Node' stats to '%dbRows' hash. The NE MO Entry point timestamp will be used to sort the data before adding it to DB
            $dbRows{"$neTimestamps[0]\t$netElement\t$neCreateMOEntry"} = "$neTimeInMillisec\t$cppTimeInMillisec\t$totalTimeInMillisec";

            # Push NetworkElement names to '@neNames' array which will be later used to get respective NetworkElement IDs
            push (@neNames, $netElement);
            $datasetSize++;

            if ($::DEBUG > 5) {
                print "calculateAddNodeStats: Successfully calculated Add-Node stats for the Network Element $netElement . Start Time: $neTimestamps[0]\n",
                        "NE MO Entry Point: $neTimestamps[0]\tNE MO Exit Point: $neTimestamps[1]\n",
                        "NE Time (Millisec): $neTimeInMillisec\n",
                        "CPP MO Entry Point: $cppTimestamps[0]\tCPP MO Exit Point: $cppTimestamps[1]\n",
                        "CPP Time (Millisec): $cppTimeInMillisec\n",
                        "Total Time (Millisec): $totalTimeInMillisec\n";
            }
        }
    }

    return (\%dbRows, \@neNames, $datasetSize);
}


# Subroutime to store the required 'Add-Node' stats in 'enm_cm_addnode_stats' table under 'statsdb' database
#   The stats that are being presently stored under DB are 'Start Time', 'Network Element', 'NE Time', 'CPP Time' & 'Total Time'
sub storeAddNodeStats($$$$$) {
    my $dbh          = shift;
    my $siteId       = shift;
    my $addNodeStats = shift;
    my $neNames      = shift;
    my $datasetSize  = shift;


    # Get NetworkElement 'Names' -> 'ID' mapping
    my $neName2IdMap = getIdMap($dbh, "enm_ne", "id", "name", $neNames, $siteId);

    # Write the 'Add-Node' stats to 'enm_cm_addnode_stats.bcp' file
    my $bcpFile = getBcpFileName("enm_cm_addnode_stats");

    my $datasetStartTime = '';
    my $datasetEndTime   = '';
    my $counter = 0;
    open (BCP, "> $bcpFile");
    foreach my $addNodeStatsKey (sort {$a cmp $b} keys %{$addNodeStats}) {
        my ($timestamp, $netElementName, $timestampInMilliSec) = $addNodeStatsKey =~ /(.*?)\t(.*?)\t(.*)/;
        my $netElementId = $neName2IdMap->{$netElementName};
        my $timestampSQL = formatTime(
                                      int ($timestampInMilliSec/1000),
                                      $StatsTime::TIME_SQL
                                     );

        $counter++;
        if ($counter == 1) {
            $datasetStartTime = $timestampSQL;
        }
        elsif ($counter == $datasetSize) {
            $datasetEndTime   = $timestampSQL;
        }
        print BCP "$siteId\t$timestampSQL\t$netElementId\t$addNodeStats->{$addNodeStatsKey}\n";
    }
    close BCP;

    # Delete the old 'Add-Node' stats from 'enm_cm_addnode_stats' table for the given dataset time-range
    dbDo( $dbh, sprintf("DELETE FROM enm_cm_addnode_stats WHERE siteid = %d AND start BETWEEN '%s' AND '%s'",
                        $siteId, $datasetStartTime, $datasetEndTime) )
        or die "Failed to remove old data from 'enm_cm_addnode_stats' table";

    # Populate the 'enm_cm_addnode_stats' DB table with the new 'Add-Node' stats
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_cm_addnode_stats" )
        or die "Failed to load new data from '$bcpFile' file to 'enm_cm_addnode_stats' table";
}

sub getElasticsearchTimeInMilliSeconds($) {
    my $timestamp = shift;
    my ($msec) = $timestamp =~ /\.(\d{3,3})/;
    return ( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC) * 1000 ) + $msec;
}

1;
