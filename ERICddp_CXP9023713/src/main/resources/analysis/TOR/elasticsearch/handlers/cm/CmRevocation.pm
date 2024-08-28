package cm::CmRevocation;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

#
# Internal functions
#
our $cmRev_regex = 'undoJobId=(\d+),user=([^,]+),startTime=(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2}).*,endTime=(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2}).*,totalCreate=(\d+),totalDelete=(\d+),totalModify=(\d+),totalHistoryItems=(\d+),totalExcludedUnsupportedOperations=(\d+),totalExcludedNonNrmMos=(\d+),totalExcludedSystemCreatedMos=(\d+),queryDuration=(\d+),processingDuration=(\d+),fileWriteDuration=(\d+),application=(\w+),applicationJobId=(\d+)';

sub parseLogEntry($$$) {
    my ($self,$timestamp,$host,$message) = @_;

    if($message =~ /$cmRev_regex/) {
        if ( $::DEBUG ) { print "cm::CmRevocation::parseLogEntry matched $message\n"; }

        my $r_jobStat = {
            'serverid' => $self->{'srvIdMap'}->{$host},
            'undoJobId' => $1,
            'starttime' => $3 . " " . $4,
            'endtime' => $5 . " " . $6,
            'totalCreate' => $7,
            'totalDelete' => $8,
            'totalModify' => $9,
            'totalHistoryItems' => $10,
            'totalExcludedUnsupportedOperations' => $11,
            'totalExcludedNonNrmMos' => $12,
            'totalExcludedSystemCreatedMos' => $13,
            'queryDuration' => $14,
            'processingDuration' => $15,
            'fileWriteDuration' => $16,
            'application' => $17,
            'applicationJobId' => $18
        };

        if ( $::DEBUG > 5 ) { print Dumper("cm::CmRevocation::parseLogEntry r_jobStat", $r_jobStat); }
        push @{$self->{'jobStats'}}, $r_jobStat;
    }
}

sub storeCmservRevocationLogs($$$) {

    my($cmservRevocationJobStats,$siteId,$dbh) = @_;

    my $bcpFileCmservRevocationLogs = getBcpFileName("enm_cm_revocation_instr");
    open (BCP, "> $bcpFileCmservRevocationLogs") or die "Failed to open $bcpFileCmservRevocationLogs";
    foreach my $r_jobStat ( @{$cmservRevocationJobStats} ) {
        print BCP "$siteId\t$r_jobStat->{'serverid'}\t$r_jobStat->{'undoJobId'}\t$r_jobStat->{'starttime'}\t$r_jobStat->{'endtime'}\t$r_jobStat->{'totalCreate'}\t$r_jobStat->{'totalDelete'}\t$r_jobStat->{'totalModify'}\t$r_jobStat->{'totalHistoryItems'}\t$r_jobStat->{'totalExcludedUnsupportedOperations'}\t$r_jobStat->{'totalExcludedNonNrmMos'}\t$r_jobStat->{'totalExcludedSystemCreatedMos'}\t$r_jobStat->{'queryDuration'}\t$r_jobStat->{'processingDuration'}\t$r_jobStat->{'fileWriteDuration'}\t$r_jobStat->{'application'}\t$r_jobStat->{'applicationJobId'}\n";
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_cm_revocation_instr WHERE siteid = %d AND endTime BETWEEN '%s' AND '%s'",
                        $siteId,
                        $cmservRevocationJobStats->[0]->{'endtime'},
                        $cmservRevocationJobStats->[$#{$cmservRevocationJobStats}]->{'endtime'}) )
        or die "Failed to delete from enm_cm_revocation_instr" . $dbh->errstr;

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmservRevocationLogs' INTO TABLE enm_cm_revocation_instr" )
        or die "Failed to load new data from '$bcpFileCmservRevocationLogs' file to 'enm_cm_revocation_instr' table" . $dbh->errstr;
}


#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'jobStats'} = [];

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

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("cm::CmRevocation::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $::DEBUG > 7 ) { print "cm::CmRevocation::handle timestamp=$timestamp program=$program message=$message\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, CM_REVOCATION/ ) {
        $self->parseLogEntry($timestamp,$host,$message);
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("cm::CmRevocation::done self", $self); }

    if ( $#{$self->{'jobStats'}} > -1 ) {
        storeCmservRevocationLogs($self->{'jobStats'},$self->{'siteId'},$dbh);
    }
}


1;
