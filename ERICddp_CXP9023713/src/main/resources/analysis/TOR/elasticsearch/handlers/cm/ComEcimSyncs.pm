package cm::ComEcimSyncs;

use strict;
use warnings;

use Data::Dumper;
use JSON;
use File::Basename;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

our %ATTR_MAP = (
    'Total Time(ms)' => 'complete_duration',
    'Number of ManagedObjects Parsed' => 'MOparsed',
    'Time Read ManagedObjects from NE(ms)' => 'readfromNE',
    'Time Transformed ManagedObjects from NE(ms)' => 'transfromNE',
    'Time ManagedObjects Write(ms)' => 'timeMOwrite',
    'Time ManagedObjects Delta Calculation(ms)' => 'delta',
    'Number of ManagedObjects Attribute Read' => 'attrread',
    'Number of ManagedObjects Attribute Transformed' => 'attrtrans',
    'Number of ManagedObjects Attribute NULL value' => 'attrnull',
    'Number of ManagedObjects Attribute Delegate' => 'delegate',
    'Number of ManagedObjects Write' => 'NOMOWrite',
    'Number of ManagedObjects Created' => 'NOMOCreate',
    'Number of ManagedObjects Deleted' => 'NOMODelete',
    'Number of ManagedObjects Updated' => 'NOMOUpdate'
    );

our $SYNC_START = 1;
our $SYNC_END = 2;

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
    $self->{'hostToSg'} = {};

    my @subscriptions = ();
    $self->{'srvIdMap'} = {};
    foreach my $service( "comecimmscm", "mscmapg" ) {
        if ( exists $r_incr->{'ComEcimSyncs'}->{'activeSyncCountsBySrv'}->{$service} ) {
           $self->{'activeSyncCountsBySrv'}->{$service} = $r_incr->{'ComEcimSyncs'}->{'activeSyncCountsBySrv'}->{$service};
        } elsif ( $service eq 'comecimmscm' && exists $r_incr->{'ComEcimSyncs'}->{'activeSyncCountsBySrv'}->{$service} ) {
            $self->{'activeSyncCountsBySrv'}->{$service} = $r_incr->{'ComEcimSyncs'}->{'activeSyncCountsBySrv'}->{$service};
        } else {
            $self->{'activeSyncCountsBySrv'}->{$service} = {};
        }
        $self->{'events'}->{$service} = [];
        my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'},$service);
        while ( my ($server,$serverId) = each %{$r_serverMap} ) {
            $self->{'hostToSg'}->{$server} = $service;
            push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS'} );
            $self->{'srvIdMap'}->{$service}->{$server} = $serverId;
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }
    my $service = $self->{'hostToSg'}->{$host};
    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(\S+ \(\S+\)\) \[NO USER DATA, COM_ECIM_SYNC_NODE\.FINALIZESYNCHANDLER_COMPLETE_SYNC, DETAILED, Flow Engine, DPS Database, COMPLETE SYNC\s+(.*)\]$/ ) {

        my $syncStatsStr = $1;
        if ( $syncStatsStr =~ /^FDN=(\S+) (.*)/ ) {
            my ($fdn,$remainder) = ($1,$2);
            if ( $fdn =~ /(.*),Total$/ ) {
                $fdn = $1;
                $remainder = 'Total ' . $remainder;
            }
            if ( $::DEBUG > 5 ) { print "ComEcimSyncs::handle fdn=$fdn remainder=$remainder\n"; }

            my $r_event = {
                'timestamp' => $timestamp,
                'time' => parseTime($timestamp,$StatsTime::TIME_ELASTICSEARCH_MSEC),
                'srv' => $host,
                'fdn' => $fdn
            };

            foreach my $nameValue ( split(/,/,$remainder) ) {
                my ($name,$value) = split(/=/,$nameValue);
                if ( $::DEBUG > 5 ) { print "ComEcimSyncs::handle name=$name , value=$value\n"; }

                if ( exists $ATTR_MAP{$name} ) {
                    $r_event->{$ATTR_MAP{$name}} = $value;
                }
            }
            push @{$self->{'events'}->{$service}}, $r_event;
        }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;
    my @serviceGroups = ("comecimmscm", "mscmapg");
    my $outputFile = "";
    my $servicegrp;
    if ( ( $#{$self->{'events'}->{'comecimmscm'}} != -1 ) || ($#{$self->{'events'}->{'mscmapg'}} != -1 )){
        storeSyncs($dbh,$self->{'siteId'},$self->{'events'},$self->{'srvIdMap'});
        foreach my $service(@serviceGroups) {
                if ( defined $self->{'analysisDir'} ) {
                    if ( $#{$self->{'events'}->{$service}} != -1 ) {
                        # The self->analysisDir points at the enmlogs directory
                        my $analysisDir = dirname($self->{'analysisDir'});
                        $outputFile = $analysisDir . "/cm/".$service."_activesyncs.json";
                        if ( $::DEBUG > 2 ) { print "ComEcimSyncs::done outputFile=$outputFile\n"; }
                        activeSyncs($self->{'events'}->{$service}, $self->{'activeSyncCountsBySrv'}->{$service}, $outputFile );
                    }
                }
            $r_incr->{'ComEcimSyncs'}->{'activeSyncCountsBySrv'}->{$service} = $self->{'activeSyncCountsBySrv'}->{$service};
        }
    }
}

sub activeSyncs($$$) {
    my ($r_syncs,$r_activeSyncCountsBySrv,$outputFile) = @_;
    my %eventsBySrv = ();
    foreach my $r_sync ( @{$r_syncs} ) {
        my $r_srvEvents = $eventsBySrv{$r_sync->{'srv'}};
        if ( ! defined $r_srvEvents ) {
            $r_srvEvents = [];
            $eventsBySrv{$r_sync->{'srv'}} = $r_srvEvents;
        }
        my $syncEndTime = $r_sync->{'time'};
        my ($msec) = $r_sync->{'timestamp'} =~ /\.(\d{3,3})/;
        $syncEndTime = ($syncEndTime*1000)+$msec;
        my $syncStartTime = $syncEndTime - $r_sync->{'complete_duration'};
        if ( $::DEBUG > 5 ) { print "activeSyncs: timestamp=" . $r_sync->{'timestamp'} . " syncEndTime=$syncEndTime\n"; }
        push @{$r_srvEvents}, { 'timestamp' => $syncStartTime, 'type' => $SYNC_START };
        push @{$r_srvEvents}, { 'timestamp' => $syncEndTime, 'type' => $SYNC_END };
    }

    while ( my ($srv,$r_srvEvents) = each %eventsBySrv ) {
        my @sortedEvents = sort { $a->{'timestamp'} <=> $b->{'timestamp'} } @{$r_srvEvents};

        my $activeCount = 0;
        my $lastSecond = 0;
        my $r_activeSyncCounts = $r_activeSyncCountsBySrv->{$srv};
        if ( defined $r_activeSyncCounts ) {
            my $lastSampleIndex = $#{$r_activeSyncCounts};
            if ( $lastSampleIndex > -1 ) {
                $lastSecond = $r_activeSyncCounts->[$lastSampleIndex]->[0] / 1000;
                $activeCount = $r_activeSyncCounts->[$lastSampleIndex]->[1];
            }
        } else {
            $r_activeSyncCounts = [];
            $r_activeSyncCountsBySrv->{$srv} = $r_activeSyncCounts;
        }
        foreach my $r_event ( @sortedEvents ) {
            my $second = int($r_event->{'timestamp'}/1000);
            if ( $r_event->{'type'} == $SYNC_START ) {
                $activeCount++;
            } else {
                $activeCount--;
                if ( $activeCount < 0 ) {
                    $activeCount = 0;
                }
            }

            if ( $second == $lastSecond ) {
                $r_activeSyncCounts->[$#{$r_activeSyncCounts}]->[1] = $activeCount;
            } else {
                if (
                    $lastSecond == 0 ||
                    (($lastSecond < ($second-1)) && ($activeCount > 0) && ($r_activeSyncCounts->[$#{$r_activeSyncCounts}][1] == 0))
                    ) {
                    push @{$r_activeSyncCounts}, [ ($second-1)*1000, 0 ];
                }
                push @{$r_activeSyncCounts}, [ $second*1000, $activeCount ];
            }

            $lastSecond = $second;
        }
        if ( $::DEBUG > 4 ) { print Dumper("activeSyncCounts: activeSyncCounts",$r_activeSyncCounts); }
    }
    open OUT, ">$outputFile" or die "Cannot open $outputFile";
    while ( my ($srv,$r_activeSyncCounts) = each %{$r_activeSyncCountsBySrv} ) {
        print OUT encode_json({'name' => $srv, 'data' => $r_activeSyncCounts}), "\n";
    }
    close OUT;
}

sub storeSyncs($$$$) {
    my ( $dbh, $siteId, $r_syncs, $r_srvIdMap ) = @_;
    my @serviceGroups = ("comecimmscm", "mscmapg");
    my %neNames = ();
    foreach my $service(@serviceGroups) {
        foreach my $r_sync ( @{$r_syncs->{$service}} ) {
            my ($neName) = $r_sync->{'fdn'} =~ /^NetworkElement=([^,]+),CmFunction=1/;
            if ( defined $neName ) {
                $neNames{$neName} = 1;
            }
            else {
                print "WARN: Could not get NE name from $r_sync->{'fdn'}\n";
            }
        }
    }
    my @neNameList = keys %neNames;
    my $r_neIdMap = getIdMap( $dbh, "enm_ne", "id", "name", \@neNameList, $siteId );

    my $bcpFileName1 = getBcpFileName("enm_ecim_syncs.bcp");
    open BCP, ">$bcpFileName1" or die "Cannot open $bcpFileName1";

    foreach my $service(@serviceGroups) {
        foreach my $r_sync ( @{$r_syncs->{$service}} ) {
            my ($neName) =
            $r_sync->{'fdn'} =~ /^NetworkElement=([^,]+),CmFunction=1/;

            if ( !defined $neName ) {
                next;
            }
            my @row = (
               $siteId,
               formatTime($r_sync->{'time'}, $StatsTime::TIME_SQL),
               $r_srvIdMap->{$service}->{$r_sync->{'srv'}},
               $r_neIdMap->{$neName},
            );
            for my $col ('complete_duration', 'MOparsed', 'readfromNE', 'transfromNE', 'NOMOWrite', 'NOMOCreate', 'NOMOUpdate',
                        'NOMODelete', 'timeMOwrite', 'delta', 'attrread', 'attrtrans','attrnull', 'delegate') {
               if ( exists $r_sync->{$col} ) {
                    push @row, $r_sync->{$col};
               }
               else {
                    push @row, '\N';
               }
            }
            print BCP join( "\t", @row ), "\n";
        }
    }
    close BCP;
    my($min_synctime,$max_synctime);
    foreach my $service(@serviceGroups) {
        if ( $#{$r_syncs->{$service}} != -1 ) {
            my $minservice_synctime = ($r_syncs->{$service}->[0]->{'time'})-($r_syncs->{$service}->[0]->{'complete_duration'}/1000);
            my $maxservice_synctime = ($r_syncs->{$service}->[$#{{$r_syncs}->{$service}}]->{'time'})-($r_syncs->{$service}->[$#{{$r_syncs}->{$service}}]->{'complete_duration'}/1000);
            if ( (!defined $min_synctime) || ($minservice_synctime < $min_synctime) ) {
                $min_synctime = $minservice_synctime;
            }
            if ( (!defined $max_synctime) || ($maxservice_synctime > $max_synctime) ) {
                $max_synctime = $maxservice_synctime;
            }
        }
    }
    dbDo( $dbh, sprintf("DELETE FROM enm_ecim_syncs WHERE siteid = %d AND start BETWEEN '%s' AND '%s'",
                    $siteId,
                    formatTime($min_synctime, $StatsTime::TIME_SQL),
                    formatTime($max_synctime, $StatsTime::TIME_SQL)
                ) )
    or die "Failed to delete from enm_ecim_syncs";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileName1' INTO TABLE enm_ecim_syncs" )
    or die "Failed to load data in $bcpFileName1 into enm_ecim_syncs";

}

1;
