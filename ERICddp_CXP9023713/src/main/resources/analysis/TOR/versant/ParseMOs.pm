package ParseMOs;

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use File::Basename;
use DBI;
use JSON;

use StatsDB;
use StatsTime;

use Text::Unidecode;

our $DEBUG = 0;

our @KNOWN_TECHNOLOGYDOMAIN_VALUES = ('EPS', 'GSM', 'IMS', 'UMTS', '5GS' );

sub parseNeo4jFiles($) {
    my ($r_files) = @_;

    my %resultsByMoType = ();

    foreach my $file ( @{$r_files} ) {
        my $moModelType = basename($file);
        my ($model,$moType) = split(/:/, $moModelType);
        if ( ! defined $moType ) {
            print "WARNING: Skipping invalid file name $moModelType\n";
            next;
        }

        if ( $DEBUG > 7 ) { print "parseNeo4j: model=$model moType=$moType\n"; }

        my %moByFdn = ();
        my $jsonStr = undef;
        {
            local $/ = undef;
            open FILE, $file or die "Couldn't open file: $!";
            binmode FILE;
            $jsonStr = <FILE>;
            close FILE;
        }

        my $json = decode_json($jsonStr);
        if ( $DEBUG > 8 ) { print "parseNeo4j: json=", Dumper($json); }

        if ( $DEBUG > 7 ) { print "parseNeo4j: json->{'columns'}=", Dumper($json->{'columns'}); }
        my @attrNames = ();
        my $attrName = undef;
        for ( my $index = 2; $index <= $#{$json->{'columns'}}; $index++ ) {
            # Special handling for neProductVersion
            if ( $json->{'columns'}->[$index] eq 'neProductVersion' ) {
                $attrName = 'neProductVersion';
            } else {
                ($attrName) = $json->{'columns'}->[$index] =~ /m\.\`(\S+)\`$/;
            }
            defined $attrName or die "Failed to get attrName from " . $json->{'columns'}->[$index];
            push @attrNames, $attrName;
        }
        if ( $DEBUG > 7 ) { print "parseNeo4j: attrNames=" . Dumper(\@attrNames); }

        foreach my $r_moData ( @{$json->{'data'}} ) {
            if ( $r_moData->[1] eq 'Live' ) {
                my %mo = ();
                for ( my $index = 0; $index <= $#attrNames; $index++ ) {
                    if ( JSON::is_bool($r_moData->[$index+2]) ) {
                        if ( $r_moData->[$index+2] ) {
                            $mo{$attrNames[$index]} = 'TRUE';
                        } else {
                            $mo{$attrNames[$index]} = 'FALSE';
                        }
                    } else {
                        $mo{$attrNames[$index]} = $r_moData->[$index+2];
                    }
                }
                $moByFdn{$r_moData->[0]} = \%mo;
            }
        }
        $resultsByMoType{$moType} = \%moByFdn;
    }

    if ( $DEBUG > 7 ) { print "parseNeo4j: resultsByMoType=", Dumper(\%resultsByMoType); }

    return \%resultsByMoType;
}

sub extractValue($) {
    my ($value) = @_;
    if ( $value =~ /^\d+=\"([^\"]*)\"$/ ) {
        return $1;
    } else {
        return $value;
    }
}

sub parseVersantFiles($) {
    my ($r_files) = @_;
    my %resultsByMoType = ();

    foreach my $file ( @{$r_files} ) {
        my $moModelType = basename($file);
        my ($model,$moType) = split(/\./, $moModelType);
        $moType =~ s/^Pt_//;
        if ( $DEBUG > 9 ) { print "parseFiles: model=$model moType=$moType\n"; }

        my %moByFdn = ();
        my $r_mo = undef;
        my $r_listValues = undef;

        open INPUT, $file or die "Cannot open $file";
        while ( my $line = <INPUT> ) {
            if ( $DEBUG > 9 ) { print "parseFiles: line $.=$line"; }
            my ($name,$value) = $line =~ /^##\s+(\S+)\s+(.*)/;
            if ( ! defined $value ) {
                next;
            }

            # Strip any trailing spaces from the value
            $value =~ s/\s+$//;
            if ( $DEBUG > 8 ) { print "parseFiles: name=$name value=$value\n"; }
            if ( $name eq 'bucketName' ) {
                if ( $DEBUG > 7 && defined $r_mo ) { print Dumper("parseFiles: r_mo", $r_mo); }

                if ( $value eq '5="Live"' ) {
                    $r_mo = {};
                } else {
                    $r_mo = undef;
                }
            } elsif ( defined $r_mo ) {
                if ( defined $r_listValues ) {
                    if ( $name eq 'item:' ) {
                        push @{$r_listValues}, extractValue($value);
                    } else {
                        $r_listValues = undef;
                    }
                }
                if ( $name eq 'fdn' ) {
                    my ($fdn) = $value =~ /^\d+=\"([^\"]*)\"$/;
                    $moByFdn{$fdn} = $r_mo;
                } elsif ( $name =~ /at_(\S+)/ ) {
                    my $attributeName = $1;
                    if ( $attributeName !~ /#null$/) {
                        if ( $value =~ /^<list>/) {
                            $r_listValues = [];
                            $r_mo->{$attributeName} = $r_listValues;
                        }else {
                            $r_mo->{$attributeName} = extractValue($value);
                        }
                    }
                }
            }
        }
        close INPUT;
        $resultsByMoType{$moType} = \%moByFdn;
    }
    return \%resultsByMoType;
}

sub convertToAscii($) {
    my ($input) = @_;
    return unidecode( $input );
}

sub processMOs($) {
    my ($r_moByType) = @_;

    my $shm = 'SHMFunction';
    my $inventory = 'InventoryFunction';
    my %attrMap = (
        'CmNodeHeartbeatSupervision' => {
            'active' => 'cmSupervision'
        },
        'CmFunction' => {
            'syncStatus' => 'cmSyncStatus'
        },
        'FmAlarmSupervision' => {
            'active' => 'fmSupervision'
        },
        'FmFunction' => {
            'currentServiceState' => 'fmState',
        },
        'InventorySupervision' => {
            'active' => 'invSupervision',
        },
        'InventoryFunction' => {
            'syncStatus' => 'invStatus'
        },
        'ComConnectivityInformation' => {
            'fileTransferProtocol' => 'ftpesNodes'
        },
        'PmFunction' => {
            'pmEnabled' => 'pmSupervision'
        }
    );

    my @results = ();

    my %KNOWN_TECHNOLOGYDOMAIN = ();
    foreach my $value ( @KNOWN_TECHNOLOGYDOMAIN_VALUES ) {
        $KNOWN_TECHNOLOGYDOMAIN{$value} = 1;
    }

    foreach my $neFdn ( keys %{$r_moByType->{'NetworkElement'}} ) {
        my $r_networkElement = $r_moByType->{'NetworkElement'}->{$neFdn};
        if ( $DEBUG > 5 ) { print Dumper("processMOs: r_networkElement", $r_networkElement); }
        my ($neId) = $neFdn =~ /^NetworkElement=(.*)/;

        my $ossModelIdentity = $r_networkElement->{'ossModelIdentity'};
        if ( ! defined $ossModelIdentity ) {
            $ossModelIdentity = '';
        }

        my $technologyDomain = '\N';
        if ( exists $r_networkElement->{'technologyDomain'} ) {
            my @validTechnologyDomain = ();
            foreach my $value ( @{$r_networkElement->{'technologyDomain'}} ) {
                if ( exists $KNOWN_TECHNOLOGYDOMAIN{$value} ) {
                    push @validTechnologyDomain, $value;
                } else {
                    print "WARNING: Unknown value for technologyDomain \"$value\" for $neId\n";
                }
            }
            if ( $#validTechnologyDomain != -1 ) {
                $technologyDomain = join(",", sort @validTechnologyDomain);
            }
        }

        my $release = '';
        if ( defined $r_networkElement->{'release'} ) {
            use utf8;
            $release = convertToAscii( $r_networkElement->{'release'} );
            no utf8;
        }

        my %node = (
           'id' => $neId,
           'neType' => $r_networkElement->{'neType'},
           'platformType' => $r_networkElement->{'platformType'},
           'prefix' => $r_networkElement->{'ossPrefix'},
           'technologyDomain' => $technologyDomain,
           'release' => $release,
           'ossModelIdentity' => $ossModelIdentity
        );

        if ( defined $r_networkElement->{'neProductVersion'} && ref $r_networkElement->{'neProductVersion'} eq 'ARRAY' ) {
            if ( defined $r_networkElement->{'neProductVersion'}->[0] && defined $r_networkElement->{'neProductVersion'}->[1] ) {
                my $version = $r_networkElement->{'neProductVersion'}->[0] . "/" . $r_networkElement->{'neProductVersion'}->[1];
                $node{'neProductVersion'} = $version;
            }
        }

        foreach my $moType ( keys %attrMap ) {
            my $moFdn = '';
            if($moType eq  $inventory) {
                 $moFdn = $neFdn .",$shm=1" .",$moType=1" ;
            }
            else {
                $moFdn = $neFdn . ",$moType=1";
            }
            my $r_mo = $r_moByType->{$moType}->{$moFdn};

            if ( defined $r_mo ) {
                foreach my $attrName ( keys %{$attrMap{$moType}} ) {
                    $node{$attrMap{$moType}->{$attrName}} = $r_mo->{$attrName};
                }
            }
        }
        if ( $DEBUG > 5 ) { print Dumper("processMOs: node", \%node); }
        push @results, \%node;
    }

    return \@results;
}

sub storeFileTransfer($$$$$) {
    my ($dbh, $site, $date, $r_network, $type) = @_;
    my %counts = (
       'total' => 0,
       'ftptes' => 0,
       'sftp' => 0
    );

    my %nodesHash = ();
    my %radioNodesHash = ();
    my %controllerNodesHash = ();

    if ( $type =~ m/Radionode/ ) {
       foreach my $radioNode ( 'RadioNode', 'RadioTNode', '5GRadioNode') {
          $radioNodesHash{$radioNode} = 1;
          %nodesHash = %radioNodesHash;
       }
    } elsif ( $type =~ m/Controller6610/ ) {
       foreach my $contl_Node ( 'Controller6610') {
          $controllerNodesHash{$contl_Node} = 1;
          %nodesHash = %controllerNodesHash;
        }
    }

    foreach my $node ( @{$r_network} ) {
       if ( exists $nodesHash{$node->{'neType'}} ) {
           $counts{'total'}++;
           if ( exists $node->{'ftpesNodes'} ) {
               if ( $node->{'ftpesNodes'} eq 'FTPES' ) {
                   $counts{'ftptes'}++;
               } elsif ( $node->{'ftpesNodes'} eq 'SFTP' ) {
                   $counts{'sftp'}++;
               }
           }
       }
    }

    my $siteId = getSiteId($dbh,$site);
    ($siteId > -1 ) or die "Failed to get siteid for $site";
    dbDo($dbh,"DELETE FROM enm_radionode_filetransfer WHERE siteid = $siteId AND date = '$date' AND type = '$type'")
        or die "Failed to remove old data";
    dbDo($dbh, sprintf("INSERT INTO enm_radionode_filetransfer (siteid, date, type, total, ftpesCount, sftpCount) VALUES (%d,'%s','%s',%d,%d,%d)", $siteId, $date, $type, $counts{'total'}, $counts{'ftptes'}, $counts{'sftp'}))
        or die "Failed to insert new data";
}

sub storeNeProductVersion($$$$) {
    my ($dbh,$site,$date,$r_network) = @_;

    my %counts = ();

    foreach my $r_node ( @{$r_network} ) {
        if ( (! defined $r_node->{'neType'}) || (! defined $r_node->{'neProductVersion'}) ) {
            next;
        }
        if ( ! exists $counts{$r_node->{'neType'}} ) {
            $counts{$r_node->{'neType'}} = {};
        }
        $counts{$r_node->{'neType'}}->{$r_node->{'neProductVersion'}}++;
    }

    if ( ! %counts ) {
        return;
    }

    my $r_neTypeIdMap = getIdMap($dbh, "ne_types", "id", "name", [ keys %counts ]);
    my %allVersions = ();
    foreach my $r_versionCounts ( values %counts ) {
        foreach my $version ( keys %{$r_versionCounts} ) {
            $allVersions{$version} = 1;
        }
    }
    my $r_versionMap = getIdMap($dbh, "ne_up_ver", "id", "name", [ keys %allVersions ]);

    my $siteId = getSiteId($dbh, $site);
    ( $siteId != -1 ) or die "Failed to get siteid for $site";

    my $bcpFileName = getBcpFileName("ne_up");
    open (BCP, ">$bcpFileName");
    while ( my ($neType, $r_versionCount) = each %counts ) {
        my $neTypeId = $r_neTypeIdMap->{$neType};
        while ( my ($version, $count) = each %{$counts{$neType}} ) {
            my @values = ($siteId, $date, $neTypeId, $r_versionMap->{$version}, $count);
            print BCP join("\t", @values), "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM ne_up WHERE siteid = $siteId AND date='$date'" )
        or die "Failed to delete from ne_up";

    my @columns = ( 'siteid', 'date', 'netypeid', 'upid', 'numne' );
    dbDo( $dbh,
          sprintf("LOAD DATA LOCAL INFILE '%s' INTO TABLE ne_up (%s)",
                  $bcpFileName,
                  join(",", @columns)
              )
      ) or die "Failed to load new data from '$bcpFileName' file to 'ne_up' table";
}

sub storeNetworkElementsData($$$$) {
    my ($dbh,$site,$date,$r_network) = @_;

    # Create hash for holding the results while we count
    my %countsByKey = ();
    foreach my $r_node ( @{$r_network} ) {
        if ( (! defined $r_node->{'neType'}) ||
                 (! defined $r_node->{'release'}) ||
                 (! defined $r_node->{'technologyDomain'}) ) {
            print Dumper($r_node);
        }

        my $neKey = sprintf("%s@%s@%s@%s",
                            $r_node->{'neType'},
                            $r_node->{'release'},
                            $r_node->{'technologyDomain'},
                            $r_node->{'ossModelIdentity'}
                        );
        my $r_counts = $countsByKey{$neKey};
        if ( ! defined $r_counts ) {
            $r_counts = {
                'type' => $r_node->{'neType'},
                'release' => $r_node->{'release'},
                'technologyDomain' => $r_node->{'technologyDomain'},
                'ossModelIdentity' => $r_node->{'ossModelIdentity'},
                'nodes' => 0,
                'cmsuper' => 0,
                'cmsynced' => 0,
                'fmsuper' => 0,
                'shmsynced' => 0,
                'pmsuper' => 0
            };
            $countsByKey{$neKey} = $r_counts;
        }
        $r_counts->{'nodes'}++;
        if ( exists $r_node->{'cmSupervision'} && $r_node->{'cmSupervision'} eq 'TRUE' ) {
            $r_counts->{'cmsuper'}++;
        }
        if ( exists $r_node->{'fmSupervision'} && $r_node->{'fmSupervision'} eq 'TRUE' ) {
            $r_counts->{'fmsuper'}++;
        }
        if ( exists $r_node->{'cmSyncStatus'} && $r_node->{'cmSyncStatus'} eq 'SYNCHRONIZED' ) {
            $r_counts->{'cmsynced'}++;
        }
        if ( exists $r_node->{'invStatus'} && $r_node->{'invStatus'} eq 'SYNCHRONIZED' ) {
            $r_counts->{'shmsynced'}++;
        }
        if ( exists $r_node->{'pmSupervision'} && $r_node->{'pmSupervision'} eq 'TRUE' ) {
            $r_counts->{'pmsuper'}++;
        }
    }
    if ( $DEBUG > 4 ) { print Dumper("storeNetworkElementsData: countsByKey", \%countsByKey); }

    my %neTypeMap = ();
    my %releaseMap = ();
    my %modelIdMap = ();
    foreach my $r_counts ( values %countsByKey ) {
        $neTypeMap{$r_counts->{'type'}} = 1;
        $releaseMap{$r_counts->{'release'}} = 1;
        $modelIdMap{$r_counts->{'ossModelIdentity'}} = 1;
    }
    my $r_neTypeIdMap = getIdMap($dbh, "ne_types", "id", "name", [ keys %neTypeMap ]);
    my $r_releaseIdMap = getIdMap($dbh, "enm_ne_release", "id", "name", [ keys %releaseMap ]);
    my $r_modelIdMap = getIdMap($dbh, "enm_model_identity", "id", "name", [ keys %modelIdMap ]);

    my $siteId = getSiteId($dbh, $site);
    ( $siteId != -1 ) or die "Failed to get siteid for $site";

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    # Write Nodes related data to 'enm_network_element_details.bcp' file
    my $bcpFile = "$tmpDir/enm_network_element_details.bcp";

    open (BCP, "> $bcpFile");

    foreach my $r_counts ( values %countsByKey ) {
        my @values = (
            $date,
            $siteId,
            $r_neTypeIdMap->{$r_counts->{'type'}},
            $r_releaseIdMap->{$r_counts->{'release'}},
            $r_modelIdMap->{$r_counts->{'ossModelIdentity'}},
            $r_counts->{'technologyDomain'},
            $r_counts->{'nodes'},
            $r_counts->{'cmsuper'},
            $r_counts->{'fmsuper'},
            $r_counts->{'cmsynced'},
            $r_counts->{'shmsynced'},
            $r_counts->{'pmsuper'}
        );
        print BCP join("\t", @values), "\n";
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_network_element_details WHERE siteid = $siteId AND date='$date'" )
        or die "Failed to delete from enm_network_element_details";

    my @columns = ( 'date', 'siteid', 'netypeid', 'releaseid', 'modelid', 'technology_domain',
                    'count', 'cm_supervised_count', 'fm_supervised_count',
                    'cm_synced_count', 'shm_synced_count', 'pm_supervised_count' );
    dbDo( $dbh,
          sprintf("LOAD DATA LOCAL INFILE '%s' INTO TABLE enm_network_element_details (%s)",
                  $bcpFile,
                  join(",", @columns)
              )
      ) or die "Failed to load new data from '$bcpFile' file to 'enm_network_element_details' table";
}

sub writeNodesFile($$) {
    my ($nodesFile,$r_network) = @_;

    open OUTPUT, ">$nodesFile" or die "Cannot open $nodesFile";
    print OUTPUT encode_json($r_network);
    close OUTPUT;
}

sub main() {
    my ($inDir, $site, $date, $neo4j, $nodesFile);
    my $result = GetOptions(
        "neo4j" => \$neo4j,
        "dir=s"  => \$inDir,
        "date=s" => \$date,
        "site=s" => \$site,
        "nodes=s" => \$nodesFile,
        "debug=s" => \$DEBUG
    );
    ($result == 1) or die "Invalid args";
    setStatsDB_Debug($DEBUG);

    my @files = ();
    opendir(my $dh, $inDir) || die "can't opendir dir: $!";
    while ( my $file = readdir($dh) ) {
        if ( $file !~ /^\./ ) {
            push @files, $inDir . "/" . $file;
        }
    }
    closedir $dh;
    if ( $DEBUG > 3 ) { print Dumper("main: files", \@files); }

    my $r_moByType = undef;
    if ( defined $neo4j && $neo4j ) {
        $r_moByType = parseNeo4jFiles(\@files);
    } else {
        $r_moByType = parseVersantFiles(\@files);
    }

    my $r_network = processMOs($r_moByType);

    if ( defined $site ) {
       my $dbh = connect_db();
       my @nodetypes = ( "Radionode", "Controller6610" );
       foreach my $type ( @nodetypes ) {
          storeFileTransfer($dbh, $site, $date, $r_network, $type);
       }
       storeNetworkElementsData($dbh,$site,$date,$r_network);
       storeNeProductVersion($dbh,$site,$date,$r_network);
       $dbh->disconnect();
    }
    if ( defined $nodesFile ) {
        writeNodesFile($nodesFile,$r_network);
    }
}

1;
