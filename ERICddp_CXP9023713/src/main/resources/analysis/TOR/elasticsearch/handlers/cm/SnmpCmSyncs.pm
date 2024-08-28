package cm::SnmpCmSyncs;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

our %MINI_COLS = (
    'syncTime' => 'duration',
    'numberOfMOsSynced' => 'mo_synced',
    'numberOfMOsCreatedOrUpdated' => 'mo_createdUpdated',
    'numberOfMOsDeleted' => 'mo_deleted'
);

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
    $self->{'stn'} = [];
    $self->{'minilink'} = [];
    $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},'mssnmpcm');
    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("SnmpCmSyncs::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 5 ) { print "SnmpCmSyncs::handle timestamp=$timestamp host=$host message=$message\n" ; }

    #2018-02-27 04:45:09,882 INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (EJB default - 1) [ejbuser, STN_CM_SYNC_DPS, COARSE, NetworkElement=CORE16SIU02001,CmFunction=1, total time for sync of the node, TOTAL_TIME=1571 with MO's Count:45]
    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, STN_CM_SYNC_DPS, COARSE,\s+(.*)\]/ ) {
        if ( $1 =~ /^NetworkElement=([^,]+),CmFunction=1, .* TOTAL_TIME=(\d+),MO_Count=(\d+)/ ) {
            push @{$self->{'stn'}}, {
                'time' => parseTime( $timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC ),
                'host' => $host,
                'ne' => $1,
                'duration' => $2,
                'mo' => $3
            };
        }
    #2018-02-20 12:00:55,816 INFO [com.ericsson.oss.itpf.EVENT_LOGGER] (Thread-23 (HornetQ-client-global-threads-1904904238)) [NO USER DATA, SNMP_CM_FLOWS.SNMPCMSYNCHRONIZATIONHANDLER, DETAILED, Flow Engine, SNMP_CM_FLOWS, - STATUS SYNCHRONIZED-MssnmpcmInstrumentationBean [syncId=0, syncTime=8407, numberOfMOsCreatedOrUpdated=4, numberOfMOsDeleted=0, numberOfMOsSynced=4, creationMOTime=0, maxMOCreationTime=0] (NetworkElement=CORE19MLTN001)]
    } elsif ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\]\s+\(\S+ \(\S+\)\) \[NO USER DATA, SNMP_CM_FLOWS.SNMPCMSYNCHRONIZATIONHANDLER, DETAILED, Flow Engine, SNMP_CM_FLOWS,\s+- STATUS SYNCHRONIZED-MssnmpcmInstrumentationBean\s+(.*)\]$/ ) {
        my ($statsStr,$ne) = $1 =~ /^\[([^\]]+)\]\s+\(NetworkElement=([^\)]+)\)$/;
        if ( $::DEBUG > 4 ) { print "SnmpCmSyncs::handle minilink ne=$ne statsStr=$statsStr\n" };
        if ( defined $statsStr && defined $ne ) {
            my $r_sync = {
                'time' => parseTime( $timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC ),
                'host' => $host,
                'ne' => $ne,
            };
            foreach my $nameValue ( split(/, /, $statsStr) ) {
                my ($name,$value) = split(/=/, $nameValue);
                if ( defined $name && defined $value ) {
                    $r_sync->{$name} = $value;
                }
            }

            push @{$self->{'mini'}}, $r_sync;
        } else {
            print "WARN: SnmpCmSyncs failed to extract stats/ne from $message\n";
        }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("SnmpCmSyncs::done self", $self); }

    $self->storeMiniSyncs($dbh);
    $self->storeStnSyncs($dbh);
}

sub storeMiniSyncs($$) {
    my ($self,$dbh) = @_;

    my $r_syncs = $self->{'mini'};
    if ( $#{$r_syncs} == -1 ) {
        return;
    }

    my %neNames = ();
    foreach my $r_sync ( @{$r_syncs} ) {
        $neNames{$r_sync->{'ne'}} = 1;
    }
    my @neNameList = keys %neNames;
    my $siteId = $self->{'siteId'};
    my $r_neIdMap = getIdMap( $dbh, "enm_ne", "id", "name", \@neNameList, $siteId );

    my $r_srvIdMap = $self->{'srvIdMap'};

    my $bcpFileName = getBcpFileName('enm_minilink_cmsync');
    open BCP, ">$bcpFileName" or die "Cannot open $bcpFileName";

    my @logNames = sort keys %MINI_COLS;

    foreach my $r_sync ( @{$r_syncs} ) {
        my @row = (
            $siteId,
            formatTime($r_sync->{'time'},$StatsTime::TIME_SQL),
            $r_srvIdMap->{$r_sync->{'host'}},
            $r_neIdMap->{$r_sync->{'ne'}}
        );
        foreach my $logName ( @logNames ) {
            push @row, $r_sync->{$logName};
        }
        print BCP join( "\t", @row ), "\n";
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_minilink_cmsync WHERE siteid = %d AND time BETWEEN '%s' AND '%s'",
                        $siteId,
                        formatTime($r_syncs->[0]->{'time'}, $StatsTime::TIME_SQL),
                        formatTime($r_syncs->[$#{$r_syncs}]->{'time'}, $StatsTime::TIME_SQL)
                    ) )
        or die "Failed to delete from enm_minilink_cmsync";

    my @dbNames = ('siteid','time','serverid','neid');
    foreach my $logName ( @logNames ) {
        push @dbNames, $MINI_COLS{$logName};
    }
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileName' INTO TABLE enm_minilink_cmsync (" . join(",",@dbNames) . ")"   )
        or die "Failed to load data in $bcpFileName into enm_ecim_syncs";
}

sub storeStnSyncs($$) {
    my ($self,$dbh) = @_;

    my $r_syncs = $self->{'stn'};
    if ( $#{$r_syncs} == -1 ) {
        return;
    }

    my %neNames = ();
    foreach my $r_sync ( @{$r_syncs} ) {
        $neNames{$r_sync->{'ne'}} = 1;
    }
    my @neNameList = keys %neNames;
    my $siteId = $self->{'siteId'};
    my $r_neIdMap = getIdMap( $dbh, "enm_ne", "id", "name", \@neNameList, $siteId );

    my $r_srvIdMap = $self->{'srvIdMap'};

    my $bcpFileName = getBcpFileName('enm_stn_cmsync');
    open BCP, ">$bcpFileName" or die "Cannot open $bcpFileName";

    foreach my $r_sync ( @{$r_syncs} ) {
        my @row = (
            $siteId,
            formatTime($r_sync->{'time'},$StatsTime::TIME_SQL),
            $r_srvIdMap->{$r_sync->{'host'}},
            $r_neIdMap->{$r_sync->{'ne'}},
            $r_sync->{'duration'},
            $r_sync->{'mo'}
        );
        print BCP join( "\t", @row ), "\n";
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_stn_cmsync WHERE siteid = %d AND time BETWEEN '%s' AND '%s'",
                        $siteId,
                        formatTime($r_syncs->[0]->{'time'}, $StatsTime::TIME_SQL),
                        formatTime($r_syncs->[$#{$r_syncs}]->{'time'}, $StatsTime::TIME_SQL)
                    ) )
        or die "Failed to delete from enm_stn_cmsync";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileName' INTO TABLE enm_stn_cmsync (siteid,time,serverid,neid,duration,num_mo)" )
        or die "Failed to load data in $bcpFileName into enm_ecim_syncs";
}

1;

