package DataStore;

use warnings;
use strict;

use DBI;

use StatsDB;
use StatsCommon;

use Net::Graphite;
use Instr;
use Data::Dumper;
use Storable qw(dclone);
use Time::HiRes;

our $ONE_MINUTE = 60;
our $FIVE_MINUTE = 300;
our $FIFTEEN_MINUTE = 900;
our $THIRTY_MINUTE = 1800;
our $ONE_HOUR = 3600;
our $TWELVE_HOUR = 43200;
our $ONE_DAY = 86400;

our $GRAPHITE_FILE = '/data/ddp/graphite';

#
sub processReferences($$$$$$$$) {
    my ($dbh,
        $r_storeDataSets, $r_tableModel, $r_columnMap, $r_propertyValues,
        $r_referenceProperties, $r_referenceMetrics, $r_idMaps) = @_;

    my %foreignKeys = ();
    if ( $::DEBUG > 4 ) {
        print Dumper("processReferences: keycol", $r_tableModel->{'keycol'});
        print Dumper("processReferences: r_columnMap", $r_columnMap);
    }
    foreach my $r_dataSet ( @{$r_storeDataSets} ) {
        if ( $::DEBUG > 4 ) {
            print Dumper("processReferences: dataProperties", $r_dataSet->{'properties'});
        }
        # Check for reference properties
        while ( my ($propertyName,$r_property) = each %{$r_dataSet->{'properties'}} ) {
            ((ref $r_property) eq 'HASH') or die "dataset property $propertyName must be a HASH";
            # Examine keycol in table to see if this is a foreign key parameter
            my $refTable = undef;
            foreach my $r_keycol ( @{$r_tableModel->{'keycol'}} ) {
                if ( $r_keycol->{'name'} eq $propertyName ) {
                    $refTable = $r_keycol->{'reftable'};
                }
            }
            if ( defined $refTable ) {
                my $sourceValue = $r_property->{'sourcevalue'};
                if (defined $sourceValue) {
                    $foreignKeys{$refTable}->{$r_property->{'sourcevalue'}} = 1;
                    $r_referenceProperties->{$propertyName} = $refTable;
                } else {
                    print "ERROR: Invalid property\n";
                }
            }
        }

        # Check for reference metrics
        while ( my ($source,$target) = each %{$r_columnMap} ) {
            my $refTable = undef;
            foreach my $r_keycol ( @{$r_tableModel->{'keycol'}} ) {
                if ( $r_keycol->{'name'} eq $target ) {
                    $refTable = $r_keycol->{'reftable'};
                }
            }
            if ( defined $refTable ) {
                # Now see if this column is a property or a metric
                my $r_property = $r_dataSet->{'properties'}->{$target};
                if ( defined $r_property ) {
                    print "WARN: Found property $target in columnMap\n";
                } else {
                    $r_referenceMetrics->{$source} = $refTable;
                }
            }
        }
    }

    if ( $::DEBUG > 4 ) {
        print Dumper("processReferences: referenceProperties", $r_referenceProperties);
        print Dumper("processReferences: referenceMetrics", $r_referenceMetrics);
    }

    # Get the set of values for any foreign key in the samples
    while ( my ($source,$refTable) = each %{$r_referenceMetrics} ) {
        foreach my $r_dataSet ( @{$r_storeDataSets} ) {
            foreach my $r_row ( @{$r_dataSet->{'samples'}} ) {
                my $sourceValue = $r_row->{$source};
                if ( defined $sourceValue ) {
                    $foreignKeys{$refTable}->{$sourceValue} = 1;
                }
            }
        }
    }

    my %refNameCol = ();
    my %refFilterCol = ();
    foreach my $r_keycol ( @{$r_tableModel->{'keycol'}} ) {
        if ( exists $r_keycol->{'refnamecol'} ) {
            $refNameCol{$r_keycol->{'reftable'}} = $r_keycol->{'refnamecol'};
        } else {
            $refNameCol{$r_keycol->{'reftable'}} = 'name';
        }
        if ( exists $r_keycol->{'reffiltercol'} ) {
            $refFilterCol{$r_keycol->{'reftable'}} = $r_keycol->{'reffiltercol'}
        }
    }
    if ( $::DEBUG > 4 ) { print Dumper("storeInStatsDB: foreignKeys", \%foreignKeys); }

    while ( my ($refTable,$r_valueMap) = each %foreignKeys ) {
        my @values = keys %{$r_valueMap};
        # Now check if we have a filtercol for the refTable (if used, it's normally siteid or serverid)
        my $extraColValue = undef;
        my $extraColName = $refFilterCol{$refTable};
        if ( defined $extraColName ) {
            $extraColValue = $r_propertyValues->{$extraColName};
            (defined $extraColValue) or die "No value for $extraColName found";
        }
        $r_idMaps->{$refTable} = getIdMap($dbh, $refTable, "id", $refNameCol{$refTable}, \@values,
                                      $extraColValue, $extraColName );
    }
}

# Private sub, see storePeriodicData
sub storeInStatsDB($$$$$$$) {
    my ($r_propertyValues, $r_tableModel, $r_multiId, $r_storeDataSets, $r_columnMap, $r_opts, $r_metrics) = @_;

    # Flag used for prototyping - not really used
    if ( (exists $r_propertyValues->{'store_statsdb'}) && ($r_propertyValues->{'store_statsdb'} == 1) ) {
        return;
    }

    setStatsDB_Debug($::DEBUG);
    setInstr_Debug($::DEBUG);


    my $dbh = undef;
    if ( exists $r_opts->{'dbh'} ) {
        $dbh = $r_opts->{'dbh'};
    } else {
        $dbh = connect_db();
    }

    my $siteId = $r_propertyValues->{'siteid'};
    if ( ! defined $siteId ) {
        $siteId = getSiteId($dbh, $r_propertyValues->{'site'});
        ( $siteId != -1 ) or die "Failed to get siteid for " . $r_propertyValues->{'site'};
        $r_propertyValues->{'siteid'} = $siteId;
    }

    my $server = $r_propertyValues->{'server'};
    if ( defined $server && (! exists $r_propertyValues->{'serverid'}) ) {
        $r_propertyValues->{'serverid'} = getServerId( $dbh, $siteId, $server );
    }

    #
    # Special handling for serverid. It gets added as a property if
    # we have a ref col in the table and serverid is not a value in r_columnMap
    #
    my $hasServerIdRefCol = 0;
    my $hasServerIdProperty = 0;
    foreach my $r_keycol ( @{$r_tableModel->{'keycol'}} ) {
        if ( $r_keycol->{'name'} eq 'serverid' ) {
            $hasServerIdRefCol = 1;
        }
    }
    if ( $hasServerIdRefCol ) {
        $hasServerIdProperty = 1;
        foreach my $target ( values %{$r_columnMap} ) {
            if ( $target eq 'serverid' ) {
                $hasServerIdProperty = 0;
            }
        }
    }

    my %referenceProperties = ();
    my %referenceMetrics = ();
    my %idMaps = ();

    processReferences($dbh,
                      $r_storeDataSets, $r_tableModel, $r_columnMap, $r_propertyValues,
                      \%referenceProperties, \%referenceMetrics, \%idMaps);

    my @statsDbDataSets = ();
    foreach my $r_dataSet ( @{$r_storeDataSets} ) {
        my %statsDbProperties = ( 'siteid' => $r_propertyValues->{'siteid'} );
        if ( $hasServerIdProperty ) {
            $statsDbProperties{'serverid'} = $r_propertyValues->{'serverid'};
        }

        while ( my ($propertyName,$r_property) = each %{$r_dataSet->{'properties'}} ) {
            my $refTable = $referenceProperties{$propertyName};
            if ( defined $refTable ) {
                $statsDbProperties{$propertyName} = $idMaps{$refTable}->{$r_property->{'sourcevalue'}};
            } else {
                # If there is no keycol for this property then we simply store the
                # value of the property
                $statsDbProperties{$propertyName} = $r_property->{'sourcevalue'};
            }
        }

        # If we have metrics in the samples that are foreignKeys, we need to
        # copy the samples and replace the metric values with the foreignKeys
        my $r_samples = $r_dataSet->{'samples'};
        if ( %referenceMetrics ) {
            my $r_refSamples = dclone($r_samples);
            foreach my $r_sample ( @{$r_refSamples} ) {
                while ( my ($source,$refTable) =  each %referenceMetrics ) {
                    my $refValue = $r_sample->{$source};
                    if ( defined $refValue ) {
                        my $refId = $idMaps{$refTable}->{$refValue};
                        (defined $refId) or die "Cannot find reference value for $source with value $refValue in $refTable";
                        $r_sample->{$source} = $refId;
                    }
                }
            }
            $r_samples = $r_refSamples;
        }

        push @statsDbDataSets, {
            'samples' => $r_samples,
            'properties' => \%statsDbProperties,
            'property_options' => $r_dataSet->{'property_options'}
        };
    }

    my %groupedDataSets = ();
    #
    # instrStoreDataSets will only allow one property to be variable, if we have more then
    # one multi key, we will have to split up the data sets
    #
    if ( (! defined $r_multiId) || ($#{$r_multiId} == 0) ) {
        $groupedDataSets{'single'} = \@statsDbDataSets;
    } else {
        # Get the properties to group by (all but the last multi key)
        my @groupProperties = @{$r_multiId};
        pop @groupProperties;
        foreach my $r_dataSet ( @statsDbDataSets ) {
            my @groupIdParts = ();
             foreach my $groupProperty ( @groupProperties ) {
                push @groupIdParts, $r_dataSet->{'properties'}->{$groupProperty};
            }
            my $groupId = join(":",@groupIdParts);
            my $r_group = $groupedDataSets{$groupId};
            if ( ! defined $r_group ) {
                $r_group = [];
                $groupedDataSets{$groupId} = $r_group;
            }
            push @{$r_group}, $r_dataSet;
        }
    }
    if ( $::DEBUG > 4 ) { print "processModel: groupedDataSets keys=" . join(",", keys %groupedDataSets) . "\n"; }

    my $deleteOld = 1;
    if ( exists $r_propertyValues->{'incremental'} && $r_propertyValues->{'incremental'} == 1 ) {
        $deleteOld = 0;
    }
    foreach my $r_groupedDataSet ( values %groupedDataSets ) {
        # siteid has already been added to the properties for each dataset so
        # pass undef in the site arg
        my $r_instrStoreMetrics = instrStoreDataSets($dbh, $r_tableModel->{'name'}, undef, $r_groupedDataSet, $r_columnMap, $deleteOld);
        $r_metrics->{'instr'} = $r_instrStoreMetrics->{'t_end'} - $r_instrStoreMetrics->{'t_start'};
        $r_metrics->{'instr_store'} = $r_instrStoreMetrics->{'t_store_end'} -$r_instrStoreMetrics->{'t_store_start'};
        if ( exists $r_instrStoreMetrics->{'t_deletion_end'} ) {
            $r_metrics->{'instr_delete'} = $r_instrStoreMetrics->{'t_deletion_end'} - $r_instrStoreMetrics->{'t_deletion_start'};
        } else {
            $r_metrics->{'instr_delete'} = 0;
        }
    }

    if ( ! exists $r_opts->{'dbh'} ) {
        $dbh->disconnect();
    }
}

# Private sub, see storePeriodicData
sub storeInGraphite($$$$$$$) {
    my ($period, $r_propertyValues, $r_tableModel, $r_multiId, $r_storeDataSets, $r_columnMap, $service) = @_;

    if ( ! -r $GRAPHITE_FILE ) {
        return;
    }

    open my $handle, '<', $GRAPHITE_FILE;
    chomp(my @lines = <$handle>);
    close $handle;

    my $graphiteConn = Net::Graphite->new(
        'host' => $lines[0],
        'trace' => ($::DEBUG > 8)
        );

    my $timeColumn = $r_columnMap->{'time'};
    if ( ! defined $timeColumn ) {
        $timeColumn = 'time';
    }

    my $suffix = "";
    if ( $period == $FIFTEEN_MINUTE ) {
        $suffix = ".15m";
    } elsif ( $period == $ONE_DAY ) {
        $suffix = ".1d";
    } elsif ( $period == $FIVE_MINUTE ) {
        $suffix = ".5m";
    }

    foreach my $r_dataSet ( @{$r_storeDataSets} ) {
        # Construct the metric parent path
        # Generally looks like ddp/site/service/table/server/datasetname
        my @path = ( 'ddp', $r_propertyValues->{'site'} );

        if ( defined $service ) {
            push @path, $service;
        } else {
            push @path, "generic";
        }

        push @path, $r_tableModel->{'name'};
        if ( exists $r_propertyValues->{'server'} ) {
            push @path, $r_propertyValues->{'server'};
        }

        my %usedProperties = ();
        if ( defined $r_multiId ) {
            foreach my $multiId ( @{$r_multiId} ) {
                my $multiValue = $r_dataSet->{'properties'}->{$multiId}->{'sourcevalue'};
                $multiValue =~ s/\//_/g;
                $multiValue =~ s/\./_/g;
                push @path, $multiValue;
                $usedProperties{$multiId} = 1;
            }
        }

        while ( my ($propertyName,$r_property) = each %{$r_dataSet->{'properties'}} ) {
            if ( ! exists $usedProperties{$propertyName} ) {
                my $propertyValue = $r_property->{'sourcevalue'};
                $propertyValue =~ s/\//_/g;
                $propertyValue =~ s/\./_/g;
                push @path, $propertyValue;
            }
        }

        my $pathStr = join(".",@path);
        if ( $::DEBUG > 3 ) { print Dumper("storeInGraphite: path=$pathStr properties", $r_dataSet->{'properties'}); }

        foreach my $r_sample ( @{$r_dataSet->{'samples'}} ) {
            while ( my ($from,$to) = each %{$r_columnMap} ) {
                my $value = $r_sample->{$from};
                if ( defined $value ) {
                    my $fullPath = $pathStr . "." . $to . $suffix;
                    $graphiteConn->send(
                        path => $fullPath,
                        value => $value,
                        time => $r_sample->{$timeColumn});
                }
            }
        }
    }

    $graphiteConn->close();
}

#
# period:           should be one of $ONE_MINUTE or $FIFTEEN_MINUTE or $ONE_DAY
# r_tableModel:     hash on containing the table definiation, see modelledinstr.xsd
# r_multiId:        array of the column names contain the multi instance ids, can be undef
# service:          service name
# r_propertyValues: hash containing values of properties used in the datasets, must contain site, and server if applicable
#                     There are some "special" properties
#                      store_statsdb: if this exists and it's zero then no data will be stored in statsdb (used for prototyping)
#                      incremental: if this exists and it's zero we don't delete old data
# r_columnMap:      hash containing the source name and target name (stored name) of the columns in the datasets
# r_dataSets:       array of hashes, where each hash has
#                     'samples'    => array of hashes, each hash is a sample, which must contain time (number) and timestamp (string)
#                     'properties' => hash
#                       key is property name
#                       if key refers to a key column, then we store the id for the value, otherwise we store the value
#                       Note: siteid and serverid should not be added to the properties
# r_opts            hash containing optional parameters
#                    dbh => db handle to use for StatsDB
sub storePeriodicDataWithOpts($$$$$$$$) {
    my ($period, $r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets, $r_opts ) = @_;

    my %metrics = ( 't_start' => Time::HiRes::time() );
    exists $r_propertyValues->{'site'} or die "propertyValues must contain site";

    if ( $::DEBUG > 3 ) {
        print Dumper("storePeriodicData: r_propertyValues", $r_propertyValues);
        foreach my $r_dataset ( @{$r_datasets} ) {
            print Dumper("storePeriodicData: dataset properties", $r_dataset->{'properties'});
            StatsCommon::debugMsgWithObj(10, $r_dataset->{'samples'}, "storePeriodicData: dataset samples")
        }
    }

    storeInStatsDB($r_propertyValues, $r_tableModel, $r_multiId, $r_datasets, $r_columnMap, $r_opts, \%metrics );
    storeInGraphite($period, $r_propertyValues, $r_tableModel, $r_multiId, $r_datasets, $r_columnMap, $service);
    $metrics{'t_end'} = Time::HiRes::time();

    return \%metrics;
}

sub storePeriodicData($$$$$$$) {
    my ($period, $r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
    return storePeriodicDataWithOpts($period, $r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets, {});
}

#
# r_tableModel:     hash on containing the table definiation, see modelledinstr.xsd
# r_multiId:        array of the column names contain the multi instance ids, can be undef
# service:          service name
# r_propertyValues: hash containing values of properties used in the datasets, must contain site, and server if applicable
# r_columnMap:      hash containing the source name and target name (stored name) of the columns in the datasets
# r_dataSets:       array of hashes, where each hash has
#                     'samples'    => array of hashes, each hash is a sample, which must contain time (number) and timestamp (string)
#                     'Properties' => hash
#                       key is property name
#                       value is hash containing the key 'sourcevalue' and value is the value of property
#                       if key refers to a key column, then we store the id for the value, otherwise we store the value
#                       Note: siteid and serverid should not be added to the properties
#
sub storeIrregularData($$$$$$) {
    my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;

    my %metrics = ();
    exists $r_propertyValues->{'site'} or die "propertyValues must contain site";

    if ( $::DEBUG > 3 ) {
        print Dumper("storeIrregularData: r_tableModel", $r_tableModel);
        print Dumper("storeIrregularData: r_propertyValues", $r_propertyValues);
    }

    storeInStatsDB($r_propertyValues, $r_tableModel, $r_multiId, $r_datasets, $r_columnMap, {}, \%metrics);
}

1;
