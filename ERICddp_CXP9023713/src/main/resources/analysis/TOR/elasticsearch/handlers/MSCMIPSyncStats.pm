package MSCMIPSyncStats;

use strict;
use warnings;

use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DBI;
use StatsTime;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self, $r_cliArgs, $r_incr, $dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    if ( exists $r_incr->{'MSCMIPSyncStats'} ) {
        $self->{'r_instrDataEvent'} = $r_incr->{'MSCMIPSyncStats'}->{'r_instrDataEvent'};
    }
    else {
        $self->{'r_instrDataEvent'} = {};
    }

    my @subscriptions = ();

    foreach my $service( "mscmip" ) {
        my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'},$service);
        while ( my ($server,$serverId) = each %{$r_serverMap} ) {
            push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS'} );
            $self->{'serverMap'}->{$server} = $serverId;
        }
    }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    if ( $::DEBUG > 9 ) { print "MSCMIPSyncStats::handle got message from $host $program : $message\n"; }

    if ( $severity ne 'info' ) {
        return;
    }

    my ($date, $time) = $timestamp =~ m/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2}).*/g;
    my ($timeStr)=$timestamp=~/(\d{2}:\d{2}:\d{2}).*/;

    if ( $::DEBUG > 7 ) { print "MSCMIPSyncStats::handle got message from $host $program : $message\n"; }
    if ( $timestamp !~ /\d{2,2}:\d{2,2}:\d{2,2}\.\d{6,6}/ ) {
        my $usec = '.000000';
        if ( $timestamp =~ /\d{2,2}:\d{2,2}:\d{2,2}\.(\d{3,3})/ || $message =~ /\d{2,2}:\d{2,2}:\d{2,2},(\d{3,3})/ ) {
            $usec = '.' . $1 . '000';
        }
        $timestamp =~ s/(\d{2,2}:\d{2,2}:\d{2,2})[\.\d]*/$1$usec/;
    }
    my $serverid=$self->{'r_serverMap'}->{$host};

#Sample Log
#2017-12-11 12:00:56,257 INFO [com.ericsson.oss.itpf.EVENT_LOGGER] (Thread-15 (HornetQ-client-global-threads-1343575800)) 
#[NO USER DATA, COM_ECIM_SYNC_NODE.SYNCFINALIZINGCHECKPOINTHANDLER_COMPLETE_SYNC, DETAILED, Flow Engine, DPS Database, 
#COMPLETE SYNC fdn=NetworkElement=SPFRER60001,CmFunction=1 NE Type=Router6672, Sync Type=FULL, Total Time(ms)=46381, 
#Num ECIM MOs Parsed=371, Time Read ECIM MOs from NE(ms)=9088, Time Transformed ECIM MOs from NE(ms)=3600, Num YANG MOs Parsed=116, 
#Num Total MOs Parsed=487, Time Read YANG MOs from NE(ms)=232, Time Read MOs from NE(ms)=9320, Time Transformed YANG MOs from NE(ms)=4752, 
#Time Total Transformed MOs from NE(ms)=8352, Num MOs Write=487, Time MOs Write(ms)=26777, Time MOs Delta Calculation(ms)=804, 
#Num ECIM MOs Attribute Read=1235, Num ECIM MOs Attribute Transformed=978, Num ECIM MOs Attribute Null value=1027, 
#Num ECIM MOs Attribute Delegate=3102, Num ECIM MOs Attribute Error in Transformation=0, Num ECIM MOs Mo Error =0]
	
    if( $message =~ /fdn=([^,]*),.*NE\sType=(\S+),.*Sync\s+Type=(\S+),.*Total Time\s*\(ms\)\s*=\s*(\d+).*Num ECIM MOs Parsed=(\d+).*Time Read ECIM MOs from NE\(ms\)\s*=\s*(\d+).*Time Transformed ECIM MOs from NE\(ms\)\s*=\s*(\d+).*Num YANG MOs Parsed=(\d+).*Num Total MOs Parsed=(\d+).*Time Read YANG MOs from NE\(ms\)\s*=\s*(\d+).*Time Read MOs from NE\(ms\)\s*=\s*(\d+).*Time Transformed YANG MOs from NE\(ms\)\s*=\s*(\d+).*Time Total Transformed MOs from NE\(ms\)\s*=\s*(\d+).*Num MOs Write=(\d+).*Time MOs Write\(ms\)\s*=\s*(\d+).*Time MOs Delta Calculation\(ms\)\s*=\s*(\d+).*Num ECIM MOs Attribute Read=(\d+).*Num ECIM MOs Attribute Transformed=(\d+).*Num ECIM MOs Attribute Null value=(\d+).*Num ECIM MOs Attribute Delegate=(\d+).*Num ECIM MOs Attribute Error in Transformation=(\d+).*Num ECIM MOs Mo Error\s+=(\d+)/ ) {
    my ($fdn,$netype,$sync_type,$duration,$ecim_mo_parsed,$Tr_ecim_mo_NE,$Tt_ecim_mo_NE,$yang_mo_parsed,$total_mo_parsed,$Tr_yang_mo_NE,$Tr_mo_NE,$Tt_yang_mo_NE,
$time_total_Tr_mo_NE,$mo_write,$Tmo_write,$delta,$ecim_mo_attrR,$ecim_mo_attrTrans,$ecim_mo_attr_null,$ecim_mo_attr_del,$ecim_mo_attr_err_Tr,$ecim_mo_err) = ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21,$22);    
    my $mspmipStatKey = $date . '@@' . $time . '@@' . $host;

    my $serverid = $self->{'serverMap'}->{$host};
    $self->{'r_instrDataEvent'}->{$mspmipStatKey} = {
            'timestamp'         => $timestamp,
            'fdn'               => $fdn,
            'netype'            => $netype, 
            'complete_duration' => $duration,
            'ecimMOparsed'      => $ecim_mo_parsed,
            'ecimreadfromNE'    => $Tr_ecim_mo_NE,
            'ecimtransfromNE'   => $Tt_ecim_mo_NE,
            'yangMOparsed'      => $yang_mo_parsed,
            'totalMOparsed'      => $total_mo_parsed,
            'yangreadfromNE'    => $Tr_yang_mo_NE,
            'readfromNE'        => $Tr_mo_NE,
            'yangtransfromNE'   => $Tt_yang_mo_NE,
            'totaltransfromNE'   => $time_total_Tr_mo_NE,
            'NOMOWrite'         => $mo_write,
            'timeMOwrite'       => $Tmo_write,
            'delta'             => $delta,
            'ecimattrread'      => $ecim_mo_attrR,
            'ecimattrtrans'     => $ecim_mo_attrTrans,
            'ecimattrnull'      => $ecim_mo_attr_null,
            'ecimdelegate'      => $ecim_mo_attr_del,
            'ecimattr_errTr'    => $ecim_mo_attr_err_Tr,
            'ecimMOerr'         => $ecim_mo_err,
            'srv'               => $serverid,
            'sync_type'         => $sync_type
        };
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("MSCMIPSyncStats::done r_instrDataEvent", $self->{'r_instrDataEvent'}); }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    my %neNames = ();
    foreach my $mspmipStatKey (sort keys %{$self->{'r_instrDataEvent'}}) {
        my ($neName) = $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{'fdn'} =~ /^NetworkElement=([^,]+)/;
        if ( defined $neName ) {
            $neNames{$neName} = 1;
        }
        else {
            print "WARN: Could not get NE name from $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{'fdn'}\n";
        }
    }

    my @neNameList = keys %neNames;
    my $r_neIdMap = getIdMap( $dbh, "enm_ne", "id", "name", \@neNameList, $self->{'siteId'} );

    my $bcpFileMscmipSyncStats = "$tmpDir/mscmip_sync_stats.bcp";
    open (BCP, ">$bcpFileMscmipSyncStats");

    foreach my $mspmipStatKey (sort keys %{$self->{'r_instrDataEvent'}}) {

        my ($neName) = $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{'fdn'} =~ /^NetworkElement=([^,]+)/;

        if ( !defined $neName ) {
            next;
        }

        my @row = (
            $self->{'siteId'},
            formatTime(
                parseTime( $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{'timestamp'}, $StatsTime::TIME_ELASTICSEARCH ),
                $StatsTime::TIME_SQL
            ),
            $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{'srv'},
            $r_neIdMap->{$neName},
        );
	
        for my $col ('netype', 'complete_duration', 'ecimMOparsed', 'ecimreadfromNE', 'ecimtransfromNE', 'yangMOparsed', 'totalMOparsed', 'yangreadfromNE',
                        'readfromNE', 'yangtransfromNE', 'totaltransfromNE', 'NOMOWrite', 'timeMOwrite','delta', 'ecimattrread', 'ecimattrtrans', 'ecimattrnull', 'ecimdelegate', 'ecimattr_errTr', 'ecimMOerr', 'sync_type') {
            if ( exists $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{$col} ) {
                push @row, $self->{'r_instrDataEvent'}->{$mspmipStatKey}->{$col};
            }
            else {
                push @row, '\N';
            }
        }

        print BCP join( "\t", @row ), "\n";
    }
    close BCP;

    # Store 'Mscmip Sync Stats Details'
    dbDo( $dbh, sprintf("DELETE FROM enm_mscmip_syncs_stats WHERE siteid = %d AND start BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $self->{'siteId'}, $self->{'date'}, $self->{'date'}) )
       or die "Failed to delete old data from enm_mscmip_syncs_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileMscmipSyncStats' INTO TABLE enm_mscmip_syncs_stats" )
        or die "Failed to load new data from '$bcpFileMscmipSyncStats' file to 'enm_mscmip_syncs_stats' table";

    unlink($bcpFileMscmipSyncStats);

    $r_incr->{'MSCMIPSyncStats'} = {
                                'r_instrDataEvent' => $self->{'r_instrDataEvent'}
                            };
}

1;

