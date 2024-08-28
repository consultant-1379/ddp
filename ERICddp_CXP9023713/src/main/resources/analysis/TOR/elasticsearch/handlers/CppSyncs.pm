package CppSyncs;

use strict;
use warnings;

use Data::Dumper;
use JSON;

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
    $self->{'analysisDir'} = $r_cliArgs->{'analysisDir'};

    if ( exists $r_incr->{'CppSyncs'} ) {
        $self->{'syncEvents'} = $r_incr->{'CppSyncs'}->{'syncEvents'};
    } else {
        $self->{'syncEvents'} = {};
    }

    $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},"mscm");
    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $::DEBUG > 6 ) { print "CppSyncs::handle $timestamp $severity $message\n"; }

    my $r_event = undef;
    if ( $severity eq 'info' ) {
        if ( $message =~ /\(Thread-(\d+) .* Starting READ_([A-Z]+)_SYNC_PROCESS \('([^']+)'\)/ ) {
            my ( $threadId, $task, $fdn ) = ( $1, $2, $3 );
            $r_event = {
                'type'      => 'READ_' . $task . '_STARTED',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn
            };
        } elsif ( $message =~ /LOGGER\] \(Thread-(\d+) .* READ_([A-Z]+)_SYNC_PROCESS \('([^']+)'\) took \[(\d+)\]/ ) {
            my ( $threadId, $task, $fdn, $duration ) = ( $1, $2, $3, $4 );
            $r_event = {
                'type'      => 'READ_' . $task . '_DURATION',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'duration'  => $duration
            };
        } elsif ( $message =~ /LOGGER\] \(Thread-(\d+) .* ([A-Z]+) DPS HANDLER \('([^']+)'\) took \[(\d+)\]/ ) {
            my ( $threadId, $task, $fdn, $duration ) = ( $1, $2, $3, $4 );
            $r_event = {
                'type'      => $task . '_DPS_DURATION',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'duration'  => $duration
            };

        } elsif ( $message =~ /TopologyWriteDpsOperation\] \(Thread-(\d+) / ) {
            my $threadId = $1;
            my ( $mo_total, $mo_created, $mo_deleted );
            # To accommodate any future changes to the separator from ':' to something else (eg: from
            #  'Total MOs: 832' to 'Total MOs=832'), the pattern " [^,\d]* " has been used in the below regexes.
            if ( $message =~ /Total MOs[^,\d]*(\d+)/ ) {
                $mo_total = $1;
            }
            if ( $message =~ /MOs created[^,\d]*(\d+)/ ) {
                $mo_created = $1;
            }
            if ( $message =~ /MOs deleted[^,\d]*(\d+)/ ) {
                $mo_deleted = $1;
            }
            $r_event = {
                'type'       => 'DPS_MO',
                'timestamp'  => $timestamp,
                'threadid'   => $threadId,
                'mo_total'   => $mo_total,
                'mo_created' => $mo_created,
                'mo_deleted' => $mo_deleted
            };
        } elsif ( $message =~ /\(Thread-(\d+) .* Invoked Topology Write DPS operation for FDN/ ) {
            my $threadId = $1;
            my ( $fdn, $mo_total, $mo_created, $mo_deleted );
            # To accommodate any future changes to the separator from ':' to something else (eg: from
            #  'Total MOs: 832' to 'Total MOs=832'), the pattern " [^,\d]* " has been used in the below regexes.
            if ( $message =~ /FDN \[([^\]]+)\]/ ) {
                $fdn = $1;
            }
            if ( $message =~ /Total MOs[^,\d]*(\d+)/ ) {
                $mo_total = $1;
            }
            if ( $message =~ /MOs created[^,\d]*(\d+)/ ) {
                $mo_created = $1;
            }
            if ( $message =~ /MOs deleted[^,\d]*(\d+)/ ) {
                $mo_deleted = $1;
            }
            $r_event = {
                'type'       => 'DPS_MO',
                'timestamp'  => $timestamp,
                'threadid'   => $threadId,
                'fdn'        => $fdn,
                'mo_total'   => $mo_total,
                'mo_created' => $mo_created,
                'mo_deleted' => $mo_deleted,
            };
        } elsif ( $message =~ /\(Thread-(\d+) .* Invoked Attribute Write DPS operation for FDN \[([^\]]+)\] - \(Attributes persisted: \[(\d+)/ ) {
            my ( $threadId, $fdn, $attributes ) = ( $1, $2, $3 );
            $r_event = {
                'type'       => 'DPS_ATTR',
                'timestamp'  => $timestamp,
                'threadid'   => $threadId,
                'fdn'        => $fdn,
                'attributes' => $attributes
            };
        } elsif ( $message =~ /.AttributeWriteDpsOperation\] \(Thread-(\d+) .* - \(Attributes persisted: \[(\d+)/ ) {
            my ( $threadId, $attributes ) = ( $1, $2 );
            $r_event = {
                'type'       => 'DPS_ATTR',
                'timestamp'  => $timestamp,
                'threadid'   => $threadId,
                'attributes' => $attributes
            };
        } elsif ( $message =~ /AttributeSyncHandler\] \(Thread-(\d+) .* A total of \[(\d+)\] attributes/ ) {
            my ( $threadId, $attributes ) = ( $1, $2 );
            $r_event = {
                'type'       => 'DPS_ATTR',
                'timestamp'  => $timestamp,
                'threadid'   => $threadId,
                'attributes' => $attributes
            };
        } elsif ( $message =~ /\(Thread-(\d+) .* COMPLETE SYNC \('([^']+)'\) took \[(\d+)\]/ ) {
            my ( $threadId, $fdn, $duration ) = ( $1, $2, $3 );
            $r_event = {
                'type'      => 'COMPLETE_SYNC_FINISHED',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'duration'  => $duration
            };
        } elsif ( $message =~ /\(Thread-(\d+) .* Starting DELTA ([A-Z ]+) HANDLER \('([^']+)'\)/ ) {
            my ( $threadId, $task, $fdn ) = ( $1, $2, $3 );
            $task =~ s/ /_/g;
            $r_event = {
                'type'      => 'DELTA_'. $task . '_STARTED',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn
            };
        } elsif ( $message =~ /LOGGER\] \(Thread-(\d+) .* DELTA ([A-Z ]+) HANDLER \('([^']+)'\) took \[(\d+)\]/ ) {
            my ( $threadId, $task, $fdn, $duration ) = ( $1, $2, $3, $4 );
            $task =~ s/ /_/g;
            $r_event = {
                'type'      => 'DELTA_' . $task . '_DURATION',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'duration'  => $duration
            };
        } elsif ( $message =~ /\(Thread-(\d+) .* Starting READ_DELTA_([A-Z ]+)_SYNC_PROCESS \('([^']+)'\)/ ) {
            my ( $threadId, $task, $fdn ) = ( $1, $2, $3 );
            $task =~ s/ /_/g;
            $r_event = {
                'type'      => 'READ_DELTA_'. $task . '_STARTED',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn
            };
        } elsif ( $message =~ /LOGGER\] \(Thread-(\d+) .* READ_DELTA_([A-Z ]+)_SYNC_PROCESS \('([^']+)'\) took \[(\d+)\]/ ) {
            my ( $threadId, $task, $fdn, $duration ) = ( $1, $2, $3, $4 );
            $task =~ s/ /_/g;
            $r_event = {
                'type'      => 'READ_DELTA_' . $task . '_DURATION',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'duration'  => $duration
            };
        } elsif ( $message =~ /\(Thread-(\d+) .* Invoked Delta Sync DPS operation for FDN \[([^\]]+)\] \(Create\/Update changes: \[(\d+)\], Delete changes: \[(\d+)]/ ) {
            my ( $threadId, $fdn, $createUpdate, $delete ) = ( $1, $2, $3, $4 );
            $r_event = {
                'type'      => 'DELTA_DPS_CHANGES',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'createUpdate' => $createUpdate,
                'delete' => $delete
            };
        }
    } elsif ( $severity eq 'err' ) {
        if ( $message =~ /ERROR_LOGGER\] \(Thread-(\d+) .* Error in: ([A-Z ]+) DPS HANDLER \('([^']+)'\)(.*)/ ) {
            my ( $threadId, $task, $fdn, $remainder ) = ( $1, $2, $3, $4 );
            $r_event = {
                'type'      => 'ERROR',
                'timestamp' => $timestamp,
                'threadid'  => $threadId,
                'fdn'       => $fdn,
                'task'      => $task,
            };

            if ( $remainder =~ /Error message: (.*)\]$/ ) {
                $r_event->{'error'} = $1;
            } elsif ( $remainder =~ /Exception message: (.*)\]/ ) {
                $r_event->{'error'} = $1;
            }
        }
    }

    if ( defined $r_event ) {
        my ( $hour, $min, $sec, $msec ) = $r_event->{'timestamp'} =~ /T(\d{2,2}):(\d{2,2}):(\d{2,2})\.(\d{3,3})/;
        if ( defined $msec ) {
            $r_event->{'timeindex'} = ( ( ($hour * 3600) + ($min * 60) + $sec ) * 1000 ) + $msec;
            $r_event->{'srv'} = $host;
            if ( $::DEBUG > 5 ) {
                print Dumper( "CppSyncs::handle r_event", $r_event );
            }

            my $r_eventsForSrv = $self->{'syncEvents'}->{$host};
            if ( ! defined $r_eventsForSrv ) {
                $r_eventsForSrv = [];
                $self->{'syncEvents'}->{$host} = $r_eventsForSrv;
            }
            push @{$r_eventsForSrv}, $r_event;
        }
    }
}


sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    my $r_syncs = processSyncEvents($self->{'syncEvents'});
    storeSyncs($dbh, $self->{'siteId'}, $r_syncs->{'successful'}, $r_syncs->{'failures'}, $self->{'date'}, $self->{'srvIdMap'} );
    writeFailures($self->{'analysisDir'} . "/cpp_failed_syncs.json", $r_syncs->{'failures'});

    $r_incr->{'CppSyncs'} = { 'syncEvents' => $self->{'syncEvents'} };
}

sub processSyncEvents($) {
    my ($r_syncEvents) = @_;

    my @incompleteSyncs = ();
    my @syncs = ();
    my @failedSyncs = ();
    my $discardedEvents = 0;

    while ( my ($host,$r_syncEventsForHost) = each %{$r_syncEvents} ) {
        my %activeSyncs       = ();
        my %activeSyncThreads = ();
        my %lastSyncByNode    = ();

        foreach my $r_event ( @{$r_syncEventsForHost} ) {
            if ( $::DEBUG > 8 ) { print Dumper( "CppSync::processSyncEvents : Processing r_event", $r_event ); }

            if ( $r_event->{'type'} eq 'READ_TOPOLOGY_STARTED' ||
                     $r_event->{'type'} eq 'DELTA_NODE_INFO_STARTED' ) {

                my $r_incompleteSync =
                    delete $activeSyncThreads{ $r_event->{'threadid'} };
                if ( defined $r_incompleteSync ) {
                    printf("WARN: Sync started for %s @ %s in thread %s while already active with %s  @ %s\n",
                           $r_event->{'fdn'}, $r_event->{'timestamp'},
                           $r_event->{'threadid'},
                           $r_incompleteSync->{'fdn'},
                           $r_incompleteSync->{'ne_top_start'}
                       );
                    push @incompleteSyncs, $r_incompleteSync;

                    delete $activeSyncs{ $r_incompleteSync->{'fdn'} };
                }

                if ( exists $activeSyncs{ $r_event->{'fdn'} } ) {
                    printf("WARN: Sync started for %s @ %s while already started @ %s\n",
                           $r_event->{'fdn'},
                           $r_event->{'timestamp'},
                           $activeSyncs{ $r_event->{'fdn'} }->{'ne_top_start'}
                       );
                    push @incompleteSyncs, $activeSyncs{ $r_event->{'fdn'} };
                    delete $activeSyncs{ $r_event->{'fdn'} };
                }

                my $syncType = 'FULL';
                if ( $r_event->{'type'} eq 'DELTA_NODE_INFO_STARTED' ) {
                    $syncType = 'DELTA';
                }

                my $r_activeSync = {
                    'type'         => $syncType,
                    'fdn'          => $r_event->{'fdn'},
                    'start'        => $r_event->{'timestamp'},
                    'ne_top_start' => $r_event->{'timestamp'},
                    'threadid'     => $r_event->{'threadid'},
                    'srv'         => $r_event->{'srv'}
                };
                $activeSyncs{ $r_activeSync->{'fdn'} } = $r_activeSync;
                $activeSyncThreads{ $r_activeSync->{'threadid'} } =
                    $r_activeSync;
            } else {
                my $r_sync = undef;
                if ( exists $r_event->{'fdn'} && $r_event->{'fdn'} ne 'null' ) {
                    # Some events like
                    # Invoked Topology Write DPS operation for FDN [SubNetwork=ERBS-SUBNW-1,MeContext=ieatnetsimv6009-03_LTE06ERBS00076] - (Total MOs: 1209, MOs created: 1209, MOs deleted: 129)
                    # have the SubNetwork FDN instead of the NetworkElement fdn
                    if ( $r_event->{'fdn'} =~ /MeContext=(.*)/ ) {
                        my $neFdn = "NetworkElement=" . $1 . ",CmFunction=1";
                        $r_sync = $activeSyncs{$neFdn};
                    } else {
                        $r_sync = $activeSyncs{ $r_event->{'fdn'} };
                    }

                    if ( !defined $r_sync ) {
                        # Sometimes the ATTRIBUTE_DPS_DURATION and COMPLETE_SYNC_FINISHED come in reverse order
                        # Same story for DELTA_DPS_DURATION and DELTA_DPS_CHANGES
                        if ( $r_event->{'type'} eq 'ATTRIBUTE_DPS_DURATION' ||
                                 $r_event->{'type'} eq 'DPS_ATTR' ||
                                 $r_event->{'type'} eq 'DELTA_DPS_DURATION'  ||
                                 $r_event->{'type'} eq 'DELTA_DPS_CHANGES' ) {
                            my $r_lastSync = $lastSyncByNode{ $r_event->{'fdn'} };
                            if ( defined $r_lastSync && ( $r_event->{'timeindex'} - $r_lastSync->{'timeindex'} ) < 10000 ) {
                                $r_sync = $syncs[$#syncs];
                            }
                        }
                        if ( !defined $r_sync ) {
                            print "WARN: No active sync found for FDN $r_event->{'fdn'} @ $r_event->{'timestamp'}\n";
                        }
                    } elsif ( $r_sync->{'threadid'} ne $r_event->{'threadid'} ) {
                        print "WARN: Threadid mis-match @ $r_event->{'timestamp'}\n";
                        $r_sync = undef;
                    }
                } elsif ( exists $r_event->{'threadid'} ) {
                    $r_sync = $activeSyncThreads{ $r_event->{'threadid'} };
                    if ( !defined $r_sync ) {
                        print "WARN: No active sync found for thread $r_event->{'threadid'} @ $r_event->{'timestamp'}\n";
                    }
                }

                if ( !defined $r_sync ) {
                    $discardedEvents++;
                    next;
                }

                if ( $r_event->{'type'} eq 'COMPLETE_SYNC_FINISHED' ) {
                    $r_sync->{'complete_duration'} = $r_event->{'duration'};
                    $r_sync->{'timeindex'}         = $r_event->{'timeindex'};

                    push @syncs, $r_sync;
                    $lastSyncByNode{ $r_event->{'fdn'} } = $r_sync;
                    delete $activeSyncs{ $r_event->{'fdn'} };
                    delete $activeSyncThreads{ $r_event->{'threadid'} };
                } elsif ( $r_event->{'type'} eq 'ERROR' ) {
                    $r_sync->{'error_task'} = $r_event->{'task'};
                    if ( exists $r_event->{'error'} ) {
                        $r_sync->{'error_msg'} = $r_event->{'error'};
                    }
                    push @failedSyncs, $r_sync;
                    delete $activeSyncs{ $r_event->{'fdn'} };
                    delete $activeSyncThreads{ $r_event->{'threadid'} };
                } else {
                    if ( $r_sync->{'type'} eq 'FULL' ) {
                        if ( $r_event->{'type'} eq 'READ_TOPOLOGY_DURATION' ) {
                            $r_sync->{'ne_top_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'TOPOLOGY_DPS_DURATION' ) {
                            $r_sync->{'dps_top_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'DPS_MO' ) {
                            $r_sync->{'dps_top_mo'}         = $r_event->{'mo_total'};
                            $r_sync->{'dps_top_mo_created'} = $r_event->{'mo_created'};
                            $r_sync->{'dps_top_mo_deleted'} = $r_event->{'mo_deleted'};
                        } elsif ( $r_event->{'type'} eq 'READ_ATTRIBUTES_DURATION' ) {
                            $r_sync->{'ne_attr_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'ATTRIBUTE_DPS_DURATION' ) {
                            $r_sync->{'dps_attr_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'DPS_ATTR' ) {
                            $r_sync->{'dps_attr_attr'} = $r_event->{'attributes'};
                        }
                    } else {
                        if ( $r_event->{'type'} eq 'DELTA_NODE_INFO_DURATION' ) {
                            $r_sync->{'ne_top_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'READ_DELTA_CHANGES_DURATION' ) {
                            $r_sync->{'ne_attr_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'DELTA_MERGER_DURATION' ) {
                            $r_sync->{'dps_top_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'DELTA_DPS_DURATION' ) {
                            $r_sync->{'dps_attr_duration'} = $r_event->{'duration'};
                        } elsif ( $r_event->{'type'} eq 'DELTA_DPS_CHANGES' ) {
                            $r_sync->{'dps_top_mo_created'} = $r_event->{'createUpdate'};
                            $r_sync->{'dps_top_mo_deleted'} = $r_event->{'delete'};
                        }
                    }
                }
            }
        }
    }


    print "Found "
        . ( $#syncs + 1 )
        . " completed syncs and "
        . ( $#incompleteSyncs + 1 )
        . " incomplete, discardedEvents $discardedEvents, failedSyncs "
        . ( $#failedSyncs + 1 ) . "\n";

    if ( $::DEBUG > 5 ) { print Dumper( "CppSyncs::done : syncs", \@syncs ); }
    if ( $::DEBUG > 5 ) { print Dumper( "CppSyncs::done : failedSyncs", \@failedSyncs ); }

    return { 'successful' => \@syncs, 'failures' => \@failedSyncs };
}

sub storeSyncs($$$$$) {
    my ( $dbh, $siteId, $r_syncs, $r_failures, $date, $r_srvIdMap ) = @_;

    my %neNames = ();
    foreach my $r_sync ( @{$r_syncs} ) {
        my ($neName) =
            $r_sync->{'fdn'} =~ /^NetworkElement=([^,]+),CmFunction=1/;
        if ( defined $neName ) {
            $neNames{$neName} = 1;
        } else {
            print "WARN: Could not get NE name from $r_sync->{'fdn'}\n";
        }
    }
    my @neNameList = keys %neNames;
    my $r_neIdMap =
        getIdMap( $dbh, "enm_ne", "id", "name", \@neNameList, $siteId );

    my $tmpDir = "/data/tmp";
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    my $bcpFileName = "$tmpDir/enm_cm_syncs.bcp";
    open BCP, ">$bcpFileName" or die "Cannot open $bcpFileName";

    foreach my $r_sync ( @{$r_syncs} ) {
        my ($neName) =
            $r_sync->{'fdn'} =~ /^NetworkElement=([^,]+),CmFunction=1/;
        if ( !defined $neName ) {
            next;
        }

        my @row = (
            $siteId,
            formatTime(
                parseTime( $r_sync->{'start'}, $StatsTime::TIME_ELASTICSEARCH_MSEC ),
                $StatsTime::TIME_SQL
            ),
            $r_srvIdMap->{$r_sync->{'srv'}},
            $r_neIdMap->{$neName},
            $r_sync->{'type'}
        );
        for my $col (
            'ne_top_duration',    'dps_top_duration',
            'ne_attr_duration',   'dps_attr_duration',
            'complete_duration',  'dps_top_mo',
            'dps_top_mo_created', 'dps_top_mo_deleted',
            'dps_attr_attr'
        ) {
            if ( exists $r_sync->{$col} ) {
                push @row, $r_sync->{$col};
            } else {
                push @row, '\N';
            }
        }
        print BCP join( "\t", @row ), "\n";
    }
    close BCP;

    dbDo($dbh,
         sprintf("DELETE FROM enm_cm_syncs WHERE siteid = %d AND start BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                 $siteId, $date, $date)
     )
        or die "Failed to delete from enm_cm_syncs";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileName' INTO TABLE enm_cm_syncs" )
        or die "Failed to load data in $bcpFileName into enm_cm_syncs";
}

sub writeFailures($$) {
    my ($file,$r_failures) = @_;

    open OUTPUT, ">$file" or die "Cannot open $file";
    foreach my $r_failure ( @{$r_failures} ) {
        my ($ne) = $r_failure->{'fdn'} =~ /^NetworkElement=([^,]+),/;
        my $start = formatTime(parseTime($r_failure->{'start'}, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                               $StatsTime::TIME_SQL);
        print OUTPUT encode_json( { 'ne' => $ne, 'start' => $start, 'error' => $r_failure->{'error_msg'} } ), "\n";
    }
    close OUTPUT;
}

1;
